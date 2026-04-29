<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxSetupApplyRequest;
use App\Models\Box;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Services\Admin\WeeklyBoxSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminBoxSetupController extends Controller
{
    public function __construct(
        private readonly WeeklyBoxSetupService $setupService,
        private readonly WeeklyBoxPlannerService $plannerService,
    ) {}

    public function index(): View
    {
        $this->authorize('admin');

        return view('admin.operations.boxes.index', [
            'weeks' => $this->setupService->calendarOverview(),
        ]);
    }

    public function show(string $week): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);
        $data = $this->setupService->buildInitialDistribution($monday);

        $subsBySize = $this->plannerService
            ->eligibleSubscriptionsForWeek($monday)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        // Trial orders that ship in the same week share inventory with the
        // subscription boxes, so they appear in the same Pro Kund:in list
        // (clearly labeled). They route through a separate apply path
        // server-side because they're keyed by order_id, not user_id.
        $trialsBySize = $this->plannerService
            ->eligibleTrialOrdersForWeek($monday)
            ->groupBy(fn (Order $o): string => (string) $o->box_size);

        // Eggs candidates for the per-customer eggs slot. The first entry is
        // the default selection; admin can pick a different producer.
        $eggCandidates = $this->setupService->availableEggAddOnProducts($monday)
            ->map(fn (Product $p): array => [
                'product_id' => (int) $p->id,
                'name' => (string) $p->name,
                'producer_name' => (string) ($p->producer?->name ?? ''),
                'unit_price' => (float) $p->unit_price,
                'allergens' => array_map('strval', (array) $p->allergens),
            ])
            ->values()
            ->all();

        $customers = $this->buildInitialCustomerState($data, $subsBySize, $monday, $eggCandidates, $trialsBySize);

        return view('admin.operations.boxes.setup', [
            'week' => $monday,
            'data' => $data,
            'subsBySize' => $subsBySize,
            'customers' => $customers,
            'eggCandidates' => $eggCandidates,
        ]);
    }

    /**
     * Derive each customer's initial box composition from the size template
     * + auto rules.
     *
     * Returns a list of customer payloads consumable by Alpine. Each payload
     * separates regular lines (size-template products) from eggs lines
     * (`is_egg_addon=true` products). Allergens per line are surfaced so the
     * UI can render ✓/✗ next to each product.
     *
     * Existing Box state is loaded if available — if the admin previously
     * saved this week's boxes, we re-derive from the box's actual items
     * (preserving per-customer overrides) and pre-fill the OK marker.
     *
     * @param  array<string, mixed>  $data  Output of buildInitialDistribution
     * @param  Collection<string, \Illuminate\Database\Eloquent\Collection<int, Subscription>>  $subsBySize
     * @param  list<array<string, mixed>>  $eggCandidates
     * @param  Collection<string, \Illuminate\Database\Eloquent\Collection<int, Order>>|null  $trialsBySize
     * @return list<array<string, mixed>>
     */
    private function buildInitialCustomerState(array $data, $subsBySize, Carbon $week, array $eggCandidates, $trialsBySize = null): array
    {
        $sizeBlocks = collect($data['size_blocks'] ?? [])->keyBy('box_size');
        $defaultEggsProduct = $this->setupService->resolveEggsAddOnProduct($week);
        $stockById = collect($data['stock'] ?? [])->keyBy('product_id');

        // Load eggs-candidate metadata (allergens etc.) so we can render lines that point at an
        // eggs product even if it's not in the standard `stock` list (eggs are filtered from there).
        $eggsById = collect($eggCandidates)->keyBy('product_id');
        $allergenLookup = [];
        foreach ($stockById as $row) {
            $allergenLookup[(int) $row['product_id']] = array_map('strval', (array) ($row['allergens'] ?? []));
        }
        foreach ($eggsById as $row) {
            $allergenLookup[(int) $row['product_id']] = array_map('strval', (array) ($row['allergens'] ?? []));
        }

        // Load existing boxes for the week so we can preserve admin overrides + OK state.
        $userIds = collect($subsBySize)->flatten()->pluck('user_id')->all();
        $existingBoxes = Box::query()
            ->forWeek($week->toDateString())
            ->whereIn('user_id', $userIds)
            ->whereNull('order_id')
            ->with(['items' => fn ($q) => $q->where('is_extra', false)->with('product:id,is_egg_addon')])
            ->get()
            ->keyBy('user_id');

        // Existing trial boxes are keyed by order_id (one box per trial order).
        $trialOrderIds = $trialsBySize !== null
            ? collect($trialsBySize)->flatten()->pluck('id')->all()
            : [];
        $existingTrialBoxes = $trialOrderIds === []
            ? collect()
            : Box::query()
                ->forWeek($week->toDateString())
                ->whereIn('order_id', $trialOrderIds)
                ->with(['items' => fn ($q) => $q->where('is_extra', false)->with('product:id,is_egg_addon')])
                ->get()
                ->keyBy('order_id');

        $eggsProductIds = collect($eggCandidates)->pluck('product_id')->map(fn ($i) => (int) $i)->all();

        // Leftover pool for pad-to-target: stock that the size-template's
        // baseline allocation hasn't yet claimed. `remaining` is
        // planned − (existing-boxes-distributed + proposed-template × n) so
        // it represents stock still available beyond every customer's
        // baseline composition. As we pad new customers we decrement this so
        // we don't double-spend the same unit twice.
        $runningAvailable = [];
        foreach ($stockById as $pid => $row) {
            $runningAvailable[(int) $pid] = (float) ($row['remaining'] ?? $row['planned'] ?? 0);
        }

        $out = [];
        foreach ($subsBySize as $size => $subs) {
            $block = $sizeBlocks->get($size);
            $templateLines = $block['lines'] ?? [];
            $sizeMax = (float) ($block['max'] ?? 0);
            $sizeTarget = (float) ($block['target'] ?? 0);

            // Reference fingerprint of the size template (canonical product_id+qty)
            // — used to decide whether a new customer's composition matches
            // what an existing same-size box already got. If it matches, the
            // admin doesn't need to OK that customer's box.
            $templateRef = $this->fingerprintLines($templateLines);

            foreach ($subs as $sub) {
                $allergies = array_values((array) ($sub->allergies ?? []));
                $eggsFlag = (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                $vegOnly = (bool) data_get($sub->preferences, 'veg_only', false);
                $note = trim((string) ($sub->allergies_note ?? ''));

                $existingBox = $existingBoxes->get($sub->user_id);
                $existingItems = $existingBox?->items ?? collect();
                $hasExistingBox = $existingItems->isNotEmpty();

                $regularLines = [];
                $eggsLines = [];
                $removedAllergenItems = [];
                $paddedFromLeftover = [];

                if ($hasExistingBox) {
                    // Re-derive from the customer's actual saved box (preserves
                    // any admin per-customer overrides from a previous save).
                    foreach ($existingItems as $item) {
                        $pid = (int) $item->product_id;
                        $isEgg = (bool) ($item->product?->is_egg_addon ?? in_array($pid, $eggsProductIds, true));
                        $line = ['product_id' => $pid, 'quantity' => (float) $item->quantity];
                        if ($isEgg) {
                            $eggsLines[] = $line;
                        } else {
                            $regularLines[] = $line;
                        }
                    }
                } else {
                    // Fresh derive from size template — strip allergen conflicts.
                    foreach ($templateLines as $line) {
                        $pid = (int) ($line['product_id'] ?? 0);
                        $productAllergens = $allergenLookup[$pid] ?? [];
                        $conflictingAllergens = array_values(array_intersect($allergies, $productAllergens));

                        if ($conflictingAllergens !== []) {
                            $removedAllergenItems[] = [
                                'product_id' => $pid,
                                'product_name' => (string) ($stockById->get($pid)['name'] ?? '—'),
                                'conflicts' => $conflictingAllergens,
                            ];

                            continue;
                        }

                        $regularLines[] = [
                            'product_id' => $pid,
                            'quantity' => (float) ($line['quantity'] ?? 0),
                        ];
                        // No decrement here — `remaining` already subtracted
                        // the template baseline × n for every customer.
                    }

                    // Pad-to-target with leftover stock. When a customer's
                    // template was reduced (allergies) we top up with cheap
                    // canonical leftover products so the box value rises back
                    // toward the size target. Caps at sizeMax to avoid going
                    // over the customer's paid amount.
                    if ($removedAllergenItems !== [] && $sizeTarget > 0) {
                        $padAdded = $this->padCustomerLinesFromLeftover(
                            $regularLines,
                            $allergies,
                            $allergenLookup,
                            $stockById,
                            $eggsProductIds,
                            $runningAvailable,
                            $sizeTarget,
                            $sizeMax,
                        );
                        $paddedFromLeftover = $padAdded;
                    }
                }

                // Auto-suggest eggs if customer has the add-on flag but no eggs
                // product is yet on the lines (works for both fresh derive and
                // re-opening an existing box that pre-dates eggs being stocked).
                if ($eggsFlag && $eggsLines === [] && $defaultEggsProduct !== null) {
                    $eggsLines[] = [
                        'product_id' => (int) $defaultEggsProduct->id,
                        'quantity' => 1.0,
                    ];
                }

                $eggsAdded = $eggsFlag && $eggsLines !== [];
                $eggsMissing = $eggsFlag && $eggsLines === [];

                $flags = [];
                if ($note !== '') {
                    $flags[] = 'note';
                }
                if ($removedAllergenItems !== []) {
                    $flags[] = 'allergy_conflict';
                }
                if ($eggsMissing) {
                    $flags[] = 'eggs_missing';
                }

                $previouslyOk = $existingBox?->composition_admin_ok_at !== null;

                // Composition status: drives UI sorting, color, and OK rules.
                //   'existing'        — box already saved this week (no further work)
                //   'new_match'       — new box, composition matches the size template (auto-OK)
                //   'new_diff'        — new box, composition differs (admin must OK)
                if ($hasExistingBox) {
                    $compositionStatus = 'existing';
                } else {
                    $derivedRef = $this->fingerprintLines($regularLines);
                    $matchesTemplate = $templateRef === $derivedRef
                        && $removedAllergenItems === []
                        && $paddedFromLeftover === []
                        && ! $eggsMissing
                        && $note === '';
                    $compositionStatus = $matchesTemplate ? 'new_match' : 'new_diff';
                }

                $out[] = [
                    'kind' => 'subscription',
                    'form_key' => 's_'.(int) $sub->user_id,
                    'user_id' => (int) $sub->user_id,
                    'subscription_id' => (int) $sub->id,
                    'order_id' => null,
                    'box_size' => (string) $size,
                    'name' => (string) ($sub->user?->name ?: $sub->user?->email ?: '—'),
                    'email' => (string) ($sub->user?->email ?? ''),
                    'eggs' => $eggsFlag,
                    'veg_only' => $vegOnly,
                    'allergies' => $allergies,
                    'note' => $note,
                    'regular_lines' => $regularLines,
                    'eggs_lines' => $eggsLines,
                    'removed_allergen_items' => $removedAllergenItems,
                    'padded_from_leftover' => $paddedFromLeftover,
                    'eggs_added' => $eggsAdded,
                    'eggs_missing' => $eggsMissing,
                    'flags' => $flags,
                    'ok' => $previouslyOk,
                    'composition_status' => $compositionStatus,
                ];
            }
        }

        // ---- Trial orders ----
        // Trial orders for the same week appear in the same Pro Kund:in list.
        // They use a different form key prefix (`t_<order_id>`) and are
        // labeled "Probebox" in the UI. They share the size template with
        // subscriptions, so we re-derive lines the same way.
        if ($trialsBySize !== null) {
            foreach ($trialsBySize as $size => $orders) {
                $block = $sizeBlocks->get($size);
                $templateLines = $block['lines'] ?? [];
                $sizeMax = (float) ($block['max'] ?? 0);
                $sizeTarget = (float) ($block['target'] ?? 0);
                $templateRef = $this->fingerprintLines($templateLines);

                foreach ($orders as $order) {
                    $allergies = array_values((array) ($order->allergies ?? []));
                    $eggsFlag = (bool) data_get($order->preferences, 'eggs_dairy_add_on', false);
                    $vegOnly = (bool) data_get($order->preferences, 'veg_only', false);
                    $note = trim((string) ($order->allergies_note ?? ''));

                    $existingBox = $existingTrialBoxes->get($order->id);
                    $existingItems = $existingBox?->items ?? collect();
                    $hasExistingBox = $existingItems->isNotEmpty();

                    $regularLines = [];
                    $eggsLines = [];
                    $removedAllergenItems = [];
                    $paddedFromLeftover = [];

                    if ($hasExistingBox) {
                        foreach ($existingItems as $item) {
                            $pid = (int) $item->product_id;
                            $isEgg = (bool) ($item->product?->is_egg_addon ?? in_array($pid, $eggsProductIds, true));
                            $line = ['product_id' => $pid, 'quantity' => (float) $item->quantity];
                            if ($isEgg) {
                                $eggsLines[] = $line;
                            } else {
                                $regularLines[] = $line;
                            }
                        }
                    } else {
                        foreach ($templateLines as $line) {
                            $pid = (int) ($line['product_id'] ?? 0);
                            $productAllergens = $allergenLookup[$pid] ?? [];
                            $conflictingAllergens = array_values(array_intersect($allergies, $productAllergens));
                            if ($conflictingAllergens !== []) {
                                $removedAllergenItems[] = [
                                    'product_id' => $pid,
                                    'product_name' => (string) ($stockById->get($pid)['name'] ?? '—'),
                                    'conflicts' => $conflictingAllergens,
                                ];

                                continue;
                            }
                            $regularLines[] = [
                                'product_id' => $pid,
                                'quantity' => (float) ($line['quantity'] ?? 0),
                            ];
                        }
                        if ($removedAllergenItems !== [] && $sizeTarget > 0) {
                            $paddedFromLeftover = $this->padCustomerLinesFromLeftover(
                                $regularLines,
                                $allergies,
                                $allergenLookup,
                                $stockById,
                                $eggsProductIds,
                                $runningAvailable,
                                $sizeTarget,
                                $sizeMax,
                            );
                        }
                    }

                    if ($eggsFlag && $eggsLines === [] && $defaultEggsProduct !== null) {
                        $eggsLines[] = [
                            'product_id' => (int) $defaultEggsProduct->id,
                            'quantity' => 1.0,
                        ];
                    }

                    $eggsAdded = $eggsFlag && $eggsLines !== [];
                    $eggsMissing = $eggsFlag && $eggsLines === [];

                    $flags = [];
                    if ($note !== '') {
                        $flags[] = 'note';
                    }
                    if ($removedAllergenItems !== []) {
                        $flags[] = 'allergy_conflict';
                    }
                    if ($eggsMissing) {
                        $flags[] = 'eggs_missing';
                    }

                    $previouslyOk = $existingBox?->composition_admin_ok_at !== null;

                    if ($hasExistingBox) {
                        $compositionStatus = 'existing';
                    } else {
                        $derivedRef = $this->fingerprintLines($regularLines);
                        $matchesTemplate = $templateRef === $derivedRef
                            && $removedAllergenItems === []
                            && $paddedFromLeftover === []
                            && ! $eggsMissing
                            && $note === '';
                        $compositionStatus = $matchesTemplate ? 'new_match' : 'new_diff';
                    }

                    $out[] = [
                        'kind' => 'trial',
                        'form_key' => 't_'.(int) $order->id,
                        'user_id' => (int) $order->user_id,
                        'subscription_id' => null,
                        'order_id' => (int) $order->id,
                        'box_size' => (string) $size,
                        'name' => (string) ($order->user?->name ?: $order->user?->email ?: '—'),
                        'email' => (string) ($order->user?->email ?? ''),
                        'eggs' => $eggsFlag,
                        'veg_only' => $vegOnly,
                        'allergies' => $allergies,
                        'note' => $note,
                        'regular_lines' => $regularLines,
                        'eggs_lines' => $eggsLines,
                        'removed_allergen_items' => $removedAllergenItems,
                        'padded_from_leftover' => $paddedFromLeftover,
                        'eggs_added' => $eggsAdded,
                        'eggs_missing' => $eggsMissing,
                        'flags' => $flags,
                        'ok' => $previouslyOk,
                        'composition_status' => $compositionStatus,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Canonical fingerprint of a list of lines (product_id+quantity), order-independent.
     * Used to decide whether two compositions are "the same" for OK-prompt purposes.
     *
     * @param  list<array{product_id?: int|string, quantity?: int|float|string}>  $lines
     */
    private function fingerprintLines(array $lines): string
    {
        $rows = [];
        foreach ($lines as $line) {
            $pid = (int) ($line['product_id'] ?? 0);
            $qty = (float) ($line['quantity'] ?? 0);
            if ($pid < 1 || $qty <= 0) {
                continue;
            }
            $rows[] = $pid.':'.number_format($qty, 4, '.', '');
        }
        sort($rows);

        return implode(',', $rows);
    }

    /**
     * Pad a new customer's regular lines from leftover stock until the size
     * target is reached, capped at sizeMax. Picks cheapest products first to
     * avoid blowing past the max with one expensive item. Decrements
     * `$runningAvailable` so each pad unit is taken from real leftover.
     *
     * Returns the lines that were added (so the UI can highlight them).
     *
     * @param  list<array{product_id: int, quantity: float}>  &$regularLines
     * @param  list<string>  $allergies
     * @param  array<int, list<string>>  $allergenLookup
     * @param  Collection<int, array<string, mixed>>  $stockById
     * @param  list<int>  $eggsProductIds  excluded so eggs stay in the eggs panel
     * @param  array<int, float>  &$runningAvailable
     * @return list<array{product_id: int, quantity: float, product_name: string}>
     */
    private function padCustomerLinesFromLeftover(
        array &$regularLines,
        array $allergies,
        array $allergenLookup,
        Collection $stockById,
        array $eggsProductIds,
        array &$runningAvailable,
        float $target,
        float $max,
    ): array {
        $existingPids = [];
        $retail = 0.0;
        foreach ($regularLines as $line) {
            $pid = (int) $line['product_id'];
            $price = (float) ($stockById->get($pid)['unit_price'] ?? 0);
            $retail += $price * (float) $line['quantity'];
            $existingPids[$pid] = true;
        }

        // Build a candidate pool sorted by unit_price ASC so we top up with cheap
        // staples instead of an expensive one-shot product that blows past max.
        $candidates = [];
        foreach ($stockById as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            if (! empty($row['missing_stock'])) {
                continue;
            }
            if (($runningAvailable[$pid] ?? 0) < 1) {
                continue;
            }
            // Eggs add-ons live in the eggs panel — never in regular lines.
            if (in_array($pid, $eggsProductIds, true)) {
                continue;
            }
            $productAllergens = $allergenLookup[$pid] ?? [];
            if (array_intersect($allergies, $productAllergens) !== []) {
                continue;
            }
            if (isset($existingPids[$pid])) {
                // Already in lines — allow boosting quantity, but only if
                // there's still leftover (= we didn't already reserve all of it).
            }
            $candidates[] = [
                'product_id' => $pid,
                'name' => (string) ($row['name'] ?? '—'),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ];
        }
        usort($candidates, function (array $a, array $b): int {
            $cmp = $a['unit_price'] <=> $b['unit_price'];

            return $cmp !== 0 ? $cmp : ($a['product_id'] <=> $b['product_id']);
        });

        $added = [];
        $progressed = true;
        // Cap iterations to a sane upper bound to avoid pathological loops.
        for ($pass = 0; $pass < 50 && $progressed; $pass++) {
            $progressed = false;
            foreach ($candidates as $cand) {
                $pid = $cand['product_id'];
                $price = $cand['unit_price'];
                if ($price <= 0) {
                    continue;
                }
                if (($runningAvailable[$pid] ?? 0) < 1) {
                    continue;
                }
                if ($retail + $price > $max + 0.005) {
                    continue;
                }
                if ($retail >= $target - 0.005) {
                    break 2;
                }

                // Add 1 unit (boost existing or push a new line).
                $foundIdx = null;
                foreach ($regularLines as $idx => $line) {
                    if ((int) $line['product_id'] === $pid) {
                        $foundIdx = $idx;
                        break;
                    }
                }
                if ($foundIdx !== null) {
                    $regularLines[$foundIdx]['quantity'] = (float) $regularLines[$foundIdx]['quantity'] + 1.0;
                } else {
                    $regularLines[] = ['product_id' => $pid, 'quantity' => 1.0];
                }

                $runningAvailable[$pid] = max(0.0, ($runningAvailable[$pid] ?? 0) - 1);
                $retail += $price;

                // Track in `added` (sum quantities if same pid added in multiple passes).
                $foundAddedIdx = null;
                foreach ($added as $idx => $a) {
                    if ($a['product_id'] === $pid) {
                        $foundAddedIdx = $idx;
                        break;
                    }
                }
                if ($foundAddedIdx !== null) {
                    $added[$foundAddedIdx]['quantity'] += 1.0;
                } else {
                    $added[] = [
                        'product_id' => $pid,
                        'quantity' => 1.0,
                        'product_name' => $cand['name'],
                    ];
                }

                $progressed = true;
            }
        }

        return $added;
    }

    public function apply(AdminBoxSetupApplyRequest $request): RedirectResponse
    {
        $this->authorize('admin');

        $monday = Carbon::parse($request->validated('week_start_date'))->startOfDay();

        $customers = $request->validated('customers');
        $customerOk = (array) $request->input('customer_ok', []);
        $actorId = (int) $request->user()->id;

        if (is_array($customers) && $customers !== []) {
            $updated = $this->setupService->applyPerCustomerDistribution(
                $monday,
                $customers,
                $customerOk,
                $actorId,
            );
        } else {
            // Backwards-compat: pre-per-customer payload (size templates only).
            $sizes = (array) $request->validated('sizes');
            $updated = $this->setupService->applyDistribution($monday, $sizes);
        }

        return redirect()
            ->route('app.admin.boxes.week.show', ['week' => $monday->toDateString()])
            ->with('status', __('admin.box_setup.flash_applied', ['count' => $updated]));
    }

    /**
     * Wipe every editable subscription box for the given week so the next
     * setup run rebuilds from scratch. Existing OK marks are gone with the
     * boxes; the admin must re-OK any flagged compositions afterwards.
     * Locked / delivered boxes are never touched.
     */
    public function reset(string $week): RedirectResponse
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);
        $reset = $this->setupService->resetWeek($monday);

        return redirect()
            ->route('app.admin.boxes.setup.show', ['week' => $monday->toDateString()])
            ->with('status', __('admin.box_setup.flash_reset', ['count' => $reset]));
    }

    public function weekBoxes(string $week): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);

        $boxes = Box::query()
            ->forWeek($monday->toDateString())
            ->with([
                'user:id,email,name',
                'order:id,box_size',
                'items:id,box_id,is_extra',
            ])
            ->orderBy('id')
            ->get();

        $subsByUserId = $this->plannerService
            ->eligibleSubscriptionsForWeek($monday)
            ->keyBy(fn (Subscription $s) => (int) $s->user_id);

        $rows = $boxes->map(function (Box $box) use ($subsByUserId): array {
            $sub = $subsByUserId->get((int) $box->user_id);
            $items = $box->items;
            $boxSize = $sub?->box_size ?? $box->order?->box_size;

            return [
                'box' => $box,
                'box_size' => $boxSize !== null ? (string) $boxSize : null,
                'non_extra_count' => $items->where('is_extra', false)->count(),
                'extra_count' => $items->where('is_extra', true)->count(),
            ];
        })->values()->all();

        return view('admin.operations.boxes.week', [
            'week' => $monday,
            'rows' => $rows,
        ]);
    }

    private function resolveMonday(string $week): Carbon
    {
        $candidate = Carbon::parse($week)->startOfDay();
        if (! $candidate->isMonday()) {
            return WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        }

        return $candidate;
    }
}
