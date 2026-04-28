<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DietaryTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxSetupApplyRequest;
use App\Models\Box;
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

        // Per-size customer roster for the "Pro Kunde" panel — surfaces each
        // subscriber with their preferences (eggs / allergies / note) so the
        // admin can see at a glance whose box differs from the standard size
        // template before saving.
        $subsBySize = $this->plannerService
            ->eligibleSubscriptionsForWeek($monday)
            ->groupBy(fn (Subscription $s): string => (string) $s->box_size);

        // Build the per-customer initial composition: size-template lines, with
        // allergen-conflicting items removed (and tracked) and the eggs add-on
        // appended for customers who flagged it. Frontend then lets the admin
        // adjust per-customer before saving; flags must be OK'd or resolved
        // before the form unlocks.
        $customers = $this->buildInitialCustomerState($data, $subsBySize, $monday);

        return view('admin.operations.boxes.setup', [
            'week' => $monday,
            'data' => $data,
            'subsBySize' => $subsBySize,
            'customers' => $customers,
        ]);
    }

    /**
     * Derive each customer's initial box composition from the size template
     * + auto rules. Returns a list of customer payloads consumable by Alpine.
     *
     * @param  array<string, mixed>  $data  Output of buildInitialDistribution
     * @param  Collection<string, \Illuminate\Database\Eloquent\Collection<int, Subscription>>  $subsBySize
     * @return list<array<string, mixed>>
     */
    private function buildInitialCustomerState(array $data, $subsBySize, Carbon $week): array
    {
        $sizeBlocks = collect($data['size_blocks'] ?? [])->keyBy('box_size');
        $eggsProduct = $this->setupService->resolveEggsAddOnProduct($week);
        $stockById = collect($data['stock'] ?? [])->keyBy('product_id');

        $allergenLookup = [];
        foreach ($stockById as $row) {
            $allergenLookup[(int) $row['product_id']] = array_map('strval', (array) ($row['allergens'] ?? []));
        }

        $out = [];
        foreach ($subsBySize as $size => $subs) {
            $block = $sizeBlocks->get($size);
            $templateLines = $block['lines'] ?? [];

            foreach ($subs as $sub) {
                $allergies = array_values((array) ($sub->allergies ?? []));
                $eggsFlag = (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                $vegOnly = (bool) data_get($sub->preferences, 'veg_only', false);
                $note = trim((string) ($sub->allergies_note ?? ''));

                $lines = [];
                $removedAllergenItems = [];

                foreach ($templateLines as $line) {
                    $pid = (int) ($line['product_id'] ?? 0);
                    $productAllergens = $allergenLookup[$pid] ?? [];
                    $conflictingAllergens = array_values(array_intersect($allergies, $productAllergens));

                    if ($conflictingAllergens !== []) {
                        // Auto-remove conflicting items but track them so the UI
                        // can flag the customer and ask the admin to substitute.
                        $removedAllergenItems[] = [
                            'product_id' => $pid,
                            'product_name' => (string) ($stockById->get($pid)['name'] ?? '—'),
                            'conflicts' => $conflictingAllergens,
                        ];

                        continue;
                    }

                    $lines[] = [
                        'product_id' => $pid,
                        'quantity' => (float) ($line['quantity'] ?? 0),
                    ];
                }

                $eggsAdded = false;
                $eggsMissing = false;
                if ($eggsFlag) {
                    $alreadyHasEggs = false;
                    foreach ($lines as $l) {
                        $tags = $stockById->get((int) $l['product_id'])['dietary_tags'] ?? [];
                        if (in_array(DietaryTag::ContainsEggs->value, array_map('strval', (array) $tags), true)) {
                            $alreadyHasEggs = true;
                            break;
                        }
                    }

                    if (! $alreadyHasEggs) {
                        if ($eggsProduct !== null) {
                            $lines[] = [
                                'product_id' => (int) $eggsProduct->id,
                                'quantity' => 1.0,
                            ];
                            $eggsAdded = true;
                        } else {
                            $eggsMissing = true;
                        }
                    }
                }

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
                    'lines' => $lines,
                    'removed_allergen_items' => $removedAllergenItems,
                    'eggs_added' => $eggsAdded,
                    'eggs_missing' => $eggsMissing,
                    'flags' => $flags,
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
        if (is_array($customers) && $customers !== []) {
            $updated = $this->setupService->applyPerCustomerDistribution($monday, $customers);
        } else {
            // Backwards-compat: pre-per-customer payload (size templates only).
            $sizes = (array) $request->validated('sizes');
            $updated = $this->setupService->applyDistribution($monday, $sizes);
        }

        // Per W2-31 follow-up: jump straight to the boxes overview for the
        // week so the admin sees the result of the save in context.
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
