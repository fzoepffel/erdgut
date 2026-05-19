<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxOverrideEditRequest;
use App\Http\Requests\Admin\AdminBoxUpdateRequest;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Admin\EggsUnfulfilledHandler;
use App\Services\Admin\InventoryService;
use App\Services\Admin\WeeklyBoxSetupService;
use App\Support\AuditLogger;
use App\Support\BoxDeliveryDateResolver;
use App\Support\BoxValueRecalculationService;
use App\Support\DeliveryProjector;
use App\Support\WeeklyDistributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminBoxController extends Controller
{
    public function show(Box $box, WeeklyDistributionService $distribution, WeeklyBoxSetupService $setupService): View
    {
        $this->authorize('admin');

        $box->load([
            'user:id,name,email,phone',
            'order:id,box_size,product_type',
            'items' => fn ($q) => $q->orderBy('id')
                ->with('product:id,allergens,dietary_tags,unit_price,metadata,is_egg_addon'),
            'items.product.translations',
        ]);

        $allergies = array_values((array) ($box->allergies_snapshot ?? []));
        $preferences = (array) ($box->preferences_snapshot ?? []);
        $eggsAddOn = (bool) ($preferences['eggs_dairy_add_on'] ?? false);
        $vegOnly = (bool) ($preferences['veg_only'] ?? false);

        $subscription = Subscription::query()
            ->where('user_id', $box->user_id)
            ->where('status', SubscriptionStatus::Active)
            ->latest()
            ->first();

        // Resolve box size: snapshot wins (if set), then subscription, then trial order.
        $boxSize = (string) ($box->box_size_snapshot ?? $subscription?->box_size ?? $box->order?->box_size ?? '');
        $marginRate = $this->planningMarginRate();
        $sizeMax = round((float) config('box.included_value_by_size.'.$boxSize, 0), 2);
        $eggsAddOnEur = (float) config('box.eggs_dairy_add_on_eur', 0);
        $sizeMaxWithEggs = $sizeMax + ($eggsAddOn ? $eggsAddOnEur : 0);
        $sizeTarget = round($sizeMaxWithEggs * (1 - $marginRate), 2);

        // Allergies note: subscription for sub boxes, order for trial boxes.
        $allergiesNote = (string) ($subscription?->allergies_note ?? $box->order?->allergies_note ?? '');

        // Weekly stock + catalog (for the add-product dropdown). The catalog
        // is keyed by product_id so the Alpine UI can look up name, price,
        // unit, allergens, and egg-addon flag without a round trip.
        $catalog = $this->buildCatalogForBox($box);

        // Eggs candidates — products with `is_egg_addon=true` AND stock for the
        // box's week. Used by the dedicated eggs slot.
        $eggCandidates = $box->week_start_date !== null
            ? $setupService
                ->availableEggAddOnProducts($box->week_start_date instanceof Carbon
                    ? $box->week_start_date
                    : Carbon::parse((string) $box->week_start_date))
                ->map(fn (Product $p): array => [
                    'product_id' => (int) $p->id,
                    'name' => (string) $p->name,
                    'producer_name' => (string) ($p->producer?->name ?? ''),
                    'unit_price' => round((float) $p->unit_price, 2),
                    'unit' => (string) ($p->metadata['unit'] ?? 'piece'),
                    'allergens' => array_map('strval', (array) $p->allergens),
                ])
                ->values()
                ->all()
            : [];

        // Split saved items into regular vs eggs based on the egg-addon flag.
        $regularLines = [];
        $eggsLines = [];
        $itemConflicts = [];
        foreach ($box->items as $item) {
            $product = $item->product;
            $isEgg = (bool) ($product?->is_egg_addon ?? false);
            $entry = [
                'product_id' => (int) $item->product_id,
                'quantity' => (float) $item->quantity,
                'is_extra' => (bool) $item->is_extra,
            ];
            if ($isEgg) {
                $eggsLines[] = $entry;
            } else {
                $regularLines[] = $entry;
            }
            if ($product !== null && $distribution->conflictsWithSnapshot($product, $allergies, $preferences)) {
                $itemConflicts[(int) $item->product_id] = true;
            }
        }

        $okState = [
            'flag' => $box->composition_admin_ok_at !== null,
            'at' => $box->composition_admin_ok_at?->translatedFormat('d.m.Y H:i'),
            'by' => $box->composition_admin_ok_by !== null
                ? optional(User::query()->find($box->composition_admin_ok_by))->name
                : null,
        ];

        // Deep-link back to Box-Setup for the box's week (more useful than the
        // flat "Boxen ansehen" list — admin lands where they can see the
        // Wochen-Bestand impact of any tweak made here).
        $boxSetupUrl = $box->week_start_date !== null
            ? route('app.admin.boxes.setup.show', [
                'week' => ($box->week_start_date instanceof Carbon
                    ? $box->week_start_date
                    : Carbon::parse((string) $box->week_start_date))->toDateString(),
            ])
            : null;

        // Weekly stock for the in-page "Wochen-Bestand" panel. Each row's
        // baseline is the sum of qty in **other** boxes for the same week —
        // i.e. all box_items minus this box's items. The Alpine layer adds
        // the live composition on top, so admins see total committed stock
        // update as they edit and a clear over-allocation warning when
        // their changes push the row past planned.
        $weeklyStock = $this->buildWeeklyStockForBox($box);

        return view('admin.operations.boxes.show', [
            'box' => $box,
            'subscription' => $subscription,
            'boxSetupUrl' => $boxSetupUrl,
            'adminCanMutateItems' => $box->allowsAdminLineEdits(),
            'allergiesSnapshot' => $allergies,
            'preferencesSnapshot' => $preferences,
            'eggsAddOn' => $eggsAddOn,
            'vegOnly' => $vegOnly,
            'allergiesNote' => $allergiesNote,
            'boxSize' => $boxSize,
            'itemConflicts' => $itemConflicts,
            // Alpine-state payload — everything the page needs without a server round trip.
            'initialState' => [
                'allergies' => $allergies,
                'eggs_addon' => $eggsAddOn,
                'box_size' => $boxSize,
                'size_max' => $sizeMaxWithEggs,
                'size_target' => $sizeTarget,
                'margin_rate' => $marginRate,
                'eggs_add_on_eur' => $eggsAddOnEur,
                'catalog' => $catalog,
                'egg_candidates' => $eggCandidates,
                'regular_lines' => $regularLines,
                'eggs_lines' => $eggsLines,
                'ok' => $okState['flag'],
                'weekly_stock' => $weeklyStock,
            ],
            'okState' => $okState,
        ]);
    }

    public function overrideEdit(AdminBoxOverrideEditRequest $request, Box $box): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validated();
        $minutes = (int) ($data['minutes'] ?? (int) config('admin.box_edit_override_minutes', 30));
        $minutes = max(5, min(240, $minutes));

        $previous = $box->admin_edit_override_until?->toIso8601String();
        $until = now()->addMinutes($minutes);

        $box->forceFill(['admin_edit_override_until' => $until])->save();

        AuditLogger::log(
            entityType: 'box',
            entityId: $box->id,
            action: 'admin_override_window',
            fromState: $previous,
            toState: $until->toIso8601String(),
            reason: $data['reason'] ?? null,
            metadata: [
                'minutes' => $minutes,
                'box_status' => $box->status->value,
                'modification_deadline_at' => $box->modification_deadline_at?->toIso8601String(),
            ],
            actorUserId: $request->user()?->id,
        );

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', 'box-edit-override-opened');
    }

    public function updateMeta(Request $request, Box $box): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'next_update_expected_at' => ['nullable', 'date'],
        ]);

        $box->update([
            'next_update_expected_at' => $validated['next_update_expected_at'] ?? null,
        ]);

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', 'box-meta-updated');
    }

    /**
     * Bulk-replace box items from the Alpine-driven single-page form. One
     * round trip persists the full composition (regular + eggs + extras),
     * the OK marker, and triggers the eggs-unfulfilled flow when needed —
     * mirroring `WeeklyBoxSetupService::applyPerCustomerDistribution()`.
     */
    public function update(
        AdminBoxUpdateRequest $request,
        Box $box,
        BoxValueRecalculationService $recalc,
        DeliveryProjector $deliveryProjector,
    ): RedirectResponse {
        $this->authorize('admin');

        $lines = $request->lines();
        $okFlag = $request->ok();
        $actorId = (int) ($request->user()?->id ?? 0);

        $unfulfilledEggsTriggered = false;

        DB::transaction(function () use ($box, $lines, $okFlag, $actorId, $recalc, &$unfulfilledEggsTriggered): void {
            // Replace regular lines (is_extra=false). Extras are kept as-is
            // unless they appear in $lines with is_extra=true; users may have
            // attached extras outside this admin view that we don't want to
            // wipe.
            $box->items()->where('is_extra', false)->delete();

            $hasEggsProduct = false;
            foreach ($lines as $line) {
                $product = Product::query()->find($line['product_id']);
                if ($product === null) {
                    continue;
                }
                if ((bool) $product->is_egg_addon) {
                    $hasEggsProduct = true;
                }
                $unit = (string) ($product->metadata['unit'] ?? 'piece');
                BoxItem::create([
                    'box_id' => $box->id,
                    'product_id' => $product->id,
                    'quantity' => (float) $line['quantity'],
                    'unit' => $unit,
                    'is_extra' => (bool) $line['is_extra'],
                ]);
            }

            $recalc->recalculate($box->fresh());

            // Persist OK state.
            $box->forceFill([
                'composition_admin_ok_at' => $okFlag ? now() : null,
                'composition_admin_ok_by' => $okFlag ? $actorId : null,
            ])->save();

            // Eggs-add-on accounting: same rules as Box-Setup. If the customer
            // pays for the add-on but no `is_egg_addon=true` item is in the
            // saved lines, mark unfulfilled and queue the refund + email.
            $eggsExpected = (bool) data_get($box->preferences_snapshot, 'eggs_dairy_add_on', false);
            if ($eggsExpected && ! $hasEggsProduct) {
                if ($box->eggs_addon_unfulfilled_at === null) {
                    $box->forceFill(['eggs_addon_unfulfilled_at' => now()])->save();
                    $unfulfilledEggsTriggered = true;
                }
            } elseif ($box->eggs_addon_unfulfilled_at !== null) {
                $box->forceFill(['eggs_addon_unfulfilled_at' => null])->save();
            }
        });

        // Outside the transaction: re-project delivery + trigger refund/notify.
        $fresh = $box->fresh();
        $deliveryProjector->projectForBox($fresh);
        if ($unfulfilledEggsTriggered) {
            app(EggsUnfulfilledHandler::class)->handle([$fresh], $actorId);
        }

        // K14 (UX notes): when the customer paid for the eggs add-on but
        // the saved box has no eggs product, the handler queues a partial
        // refund + customer email. Surface that side-effect to the
        // operator on the redirect so they don't only learn about it
        // by reading their inbox.
        $statusKey = $unfulfilledEggsTriggered ? 'box-saved-eggs-unfulfilled' : 'box-saved';

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', $statusKey);
    }

    /**
     * Build the catalog payload for the box's week.
     *
     * The dropdown-addable set = `visibleInCatalog` × weekly stock. We also
     * include any product already saved on the box even if it lacks weekly
     * stock — otherwise the live UI couldn't render its name when displaying
     * existing items. Those rows get `missing_stock=true` and are filtered
     * out of the addable list client-side.
     *
     * @return list<array<string, mixed>>
     */
    private function buildCatalogForBox(Box $box): array
    {
        $weeklyPlannedByPid = [];
        if ($box->week_start_date !== null) {
            // Use the box's resolved delivery date so inventory checks
            // are accurate even when subscriptions span multiple
            // weekdays — see {@see BoxDeliveryDateResolver}.
            $deliveryDate = BoxDeliveryDateResolver::deliveryDate($box);
            $inventory = app(InventoryService::class);
            $products = Product::query()
                ->visibleInCatalog()
                ->whereHas('stockInflows', fn ($q) => $q->activeOn($deliveryDate))
                ->get(['products.id']);
            foreach ($products as $p) {
                $planned = $inventory->grossInflowOnDate($p, $deliveryDate);
                if ($planned > 0) {
                    $weeklyPlannedByPid[(int) $p->id] = $planned;
                }
            }
        }

        // Products to surface in the catalog: those with weekly stock + those
        // already on the box (even if their stock plan was later removed).
        $needPids = array_keys($weeklyPlannedByPid);
        foreach ($box->items as $item) {
            $needPids[] = (int) $item->product_id;
        }
        $needPids = array_values(array_unique($needPids));
        if ($needPids === []) {
            return [];
        }

        $locale = app()->getLocale();
        $products = Product::query()
            ->visibleInCatalog()
            ->whereIn('products.id', $needPids)
            ->select('products.*')
            ->join('product_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->with('translations')
            ->orderBy('pt.name')
            ->get(['products.id']);

        $out = [];
        foreach ($products as $product) {
            $pid = (int) $product->id;
            $out[] = [
                'product_id' => $pid,
                'name' => (string) $product->name,
                'unit' => (string) ($product->metadata['unit'] ?? 'piece'),
                'unit_price' => round((float) $product->unit_price, 2),
                'allergens' => array_map('strval', (array) $product->allergens),
                'is_egg_addon' => (bool) $product->is_egg_addon,
                'missing_stock' => ! isset($weeklyPlannedByPid[$pid]),
            ];
        }

        return $out;
    }

    /**
     * Weekly stock rows for the box's week, with the baseline excluding the
     * current box's items. Format mirrors the Box-Setup stock summary so the
     * Alpine table can be rendered with the same logic.
     *
     * @return list<array<string, mixed>>
     */
    private function buildWeeklyStockForBox(Box $box): array
    {
        if ($box->week_start_date === null) {
            return [];
        }

        $week = $box->week_start_date instanceof Carbon
            ? $box->week_start_date
            : Carbon::parse((string) $box->week_start_date);
        $deliveryDate = BoxDeliveryDateResolver::deliveryDate($box);
        $inventory = app(InventoryService::class);

        $products = Product::query()
            ->visibleInCatalog()
            ->whereHas('stockInflows', fn ($q) => $q->activeOn($deliveryDate))
            ->with('translations')
            ->get();

        // Sum-of-quantities across every box for this week, minus this box's
        // own items — that's the baseline the Alpine layer adds the live
        // composition to. Excluding self avoids double-counting when the
        // admin loads the page with items already saved.
        $distributed = DB::table('box_items')
            ->join('boxes', 'boxes.id', '=', 'box_items.box_id')
            ->whereDate('boxes.week_start_date', $week->toDateString())
            ->where('boxes.id', '!=', $box->id)
            ->selectRaw('box_items.product_id as product_id, SUM(box_items.quantity) as q')
            ->groupBy('box_items.product_id')
            ->pluck('q', 'product_id')
            ->all();

        $out = [];
        foreach ($products as $product) {
            $planned = $inventory->grossInflowOnDate($product, $deliveryDate);
            if ($planned <= 0) {
                continue;
            }
            $pid = (int) $product->id;
            $out[] = [
                'product_id' => $pid,
                'name' => (string) $product->name,
                'unit' => (string) ($product->unit?->value ?? 'piece'),
                'unit_price' => round((float) $product->unit_price, 2),
                'planned' => round($planned, 2),
                'baseline_used' => round((float) ($distributed[$pid] ?? 0), 2),
            ];
        }

        usort($out, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    private function planningMarginRate(): float
    {
        $rate = (float) config('box.admin_planning_margin_rate', 0);
        if ($rate < 0 || $rate >= 1) {
            return 0.0;
        }

        return $rate;
    }
}
