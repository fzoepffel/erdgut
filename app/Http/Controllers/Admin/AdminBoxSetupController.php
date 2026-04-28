<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxSetupApplyRequest;
use App\Models\Box;
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

        $customers = $this->buildInitialCustomerState($data, $subsBySize, $monday, $eggCandidates);

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
     * @return list<array<string, mixed>>
     */
    private function buildInitialCustomerState(array $data, $subsBySize, Carbon $week, array $eggCandidates): array
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

        $eggsProductIds = collect($eggCandidates)->pluck('product_id')->map(fn ($i) => (int) $i)->all();

        $out = [];
        foreach ($subsBySize as $size => $subs) {
            $block = $sizeBlocks->get($size);
            $templateLines = $block['lines'] ?? [];

            foreach ($subs as $sub) {
                $allergies = array_values((array) ($sub->allergies ?? []));
                $eggsFlag = (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                $vegOnly = (bool) data_get($sub->preferences, 'veg_only', false);
                $note = trim((string) ($sub->allergies_note ?? ''));

                $existingBox = $existingBoxes->get($sub->user_id);
                $existingItems = $existingBox?->items ?? collect();

                $regularLines = [];
                $eggsLines = [];
                $removedAllergenItems = [];

                if ($existingItems->isNotEmpty()) {
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

                $out[] = [
                    'user_id' => (int) $sub->user_id,
                    'subscription_id' => (int) $sub->id,
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
                    'eggs_added' => $eggsAdded,
                    'eggs_missing' => $eggsMissing,
                    'flags' => $flags,
                    'ok' => $previouslyOk,
                ];
            }
        }

        return $out;
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
