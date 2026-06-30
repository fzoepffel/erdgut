<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopClosureRule;
use App\Services\Shop\ShopClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminShopClosureController extends Controller
{
    public function __construct(private readonly ShopClosureService $closures) {}

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $this->closures->purgeExpired();

        $month = $this->resolveMonth((string) $request->query('month', ''));

        return view('admin.shop-closures.index', [
            'calendar' => $this->calendarPayload($month),
            'rules' => $this->rulesPayload(),
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $month = $this->resolveMonth((string) $request->query('month', ''));

        return response()->json($this->calendarPayload($month));
    }

    public function toggle(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $this->assertManageable($date);

        $closed = $this->closures->toggleDay($date, (int) $request->user()->id);

        $month = $this->resolveMonth((string) ($validated['month'] ?? $date->format('Y-m')));

        return $this->payloadResponse($month, ['date' => $date->toDateString(), 'closed' => $closed]);
    }

    /**
     * Set a single day's state: a one-off closing time, an immediate close,
     * keep-open, or reset to the rule. Manual choices override any rule.
     */
    public function setDay(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'action' => ['required', 'in:closes_at,close_now,keep_open,reset'],
            'closes_at' => ['nullable', 'required_if:action,closes_at', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $this->assertManageable($date);
        $userId = (int) $request->user()->id;

        match ($validated['action']) {
            'closes_at' => $this->closures->setClosingTime($date, Carbon::parse($validated['closes_at']), $userId),
            'close_now' => $this->closures->closeNow($date, $userId),
            'keep_open' => $this->closures->keepOpen($date, $userId),
            'reset' => $this->closures->clearOverride($date),
        };

        $month = $this->resolveMonth((string) ($validated['month'] ?? $date->format('Y-m')));

        return $this->payloadResponse($month);
    }

    /**
     * Drop any manual override on a day so it follows its rule again.
     */
    public function reset(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $this->assertManageable($date);

        $this->closures->clearOverride($date);

        $month = $this->resolveMonth((string) ($validated['month'] ?? $date->format('Y-m')));

        return $this->payloadResponse($month);
    }

    /**
     * Create or update the recurring rule for a weekday (one rule per weekday).
     */
    public function rulesStore(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'lead_days' => ['required', 'integer', 'between:0,30'],
            'cutoff_time' => ['required', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        ShopClosureRule::query()->updateOrCreate(
            ['weekday' => (int) $validated['weekday']],
            [
                'lead_days' => (int) $validated['lead_days'],
                'cutoff_time' => $validated['cutoff_time'].':00',
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by_user_id' => (int) $request->user()->id,
            ],
        );
        $this->closures->flushRuleCache();

        return $this->payloadResponse($this->resolveMonth((string) ($validated['month'] ?? '')));
    }

    public function rulesDestroy(Request $request, ShopClosureRule $rule): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $rule->delete();
        $this->closures->flushRuleCache();

        return $this->payloadResponse($this->resolveMonth((string) ($validated['month'] ?? '')));
    }

    private function assertManageable(Carbon $date): void
    {
        if (! $this->closures->isManageableDate($date)) {
            throw ValidationException::withMessages([
                'date' => [__('admin.shop_closures.validation_past_date')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function payloadResponse(Carbon $month, array $extra = []): JsonResponse
    {
        return response()->json(array_merge($extra, [
            'calendar' => $this->calendarPayload($month),
            'rules' => $this->rulesPayload(),
        ]));
    }

    /**
     * @return list<array{id: int, weekday: int, weekday_label: string, lead_days: int, cutoff_time: string, is_active: bool}>
     */
    private function rulesPayload(): array
    {
        return ShopClosureRule::query()
            ->orderBy('weekday')
            ->get()
            ->map(fn (ShopClosureRule $rule): array => [
                'id' => (int) $rule->id,
                'weekday' => (int) $rule->weekday,
                'weekday_label' => $this->weekdayLabel((int) $rule->weekday),
                'lead_days' => (int) $rule->lead_days,
                'cutoff_time' => Carbon::parse((string) $rule->cutoff_time)->format('H:i'),
                'is_active' => (bool) $rule->is_active,
            ])
            ->all();
    }

    private function weekdayLabel(int $weekday): string
    {
        $keys = [
            0 => 'admin.shop_closures.weekday_sun',
            1 => 'admin.shop_closures.weekday_mon',
            2 => 'admin.shop_closures.weekday_tue',
            3 => 'admin.shop_closures.weekday_wed',
            4 => 'admin.shop_closures.weekday_thu',
            5 => 'admin.shop_closures.weekday_fri',
            6 => 'admin.shop_closures.weekday_sat',
        ];

        return __($keys[$weekday] ?? 'admin.shop_closures.weekday_mon');
    }

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     prev_month: string,
     *     next_month: string,
     *     weekdays: list<string>,
     *     weeks: list<list<array{iso: string, day: int, in_month: bool, closed: bool, state: string, has_rule: bool, has_override: bool, cutoff_label: ?string, is_today: bool, is_disabled: bool}>>
     * }
     */
    private function calendarPayload(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = Carbon::today()->toDateString();
        $earliestIso = $this->closures->earliestManageableDate()->toDateString();

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $states = $this->closures->statesForRange($gridStart, $gridEnd);

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $iso = $cursor->toDateString();
                $isDisabled = $iso < $earliestIso;
                $st = $states[$iso] ?? null;
                $week[] = [
                    'iso' => $iso,
                    'day' => (int) $cursor->day,
                    'in_month' => $cursor->month === $month->month,
                    'closed' => ! $isDisabled && (bool) ($st['closed'] ?? false),
                    'state' => $isDisabled ? 'disabled' : (string) ($st['state'] ?? 'open'),
                    'has_rule' => (bool) ($st['has_rule'] ?? false),
                    'has_override' => ! $isDisabled && (bool) ($st['has_override'] ?? false),
                    'cutoff_label' => $st['cutoff_label'] ?? null,
                    'closes_at' => $st['closes_at'] ?? null,
                    'rule_cutoff' => $st['rule_cutoff'] ?? null,
                    'is_today' => $iso === $today,
                    'is_disabled' => $isDisabled,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->translatedFormat('F Y'),
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'weekdays' => [
                __('admin.shop_closures.weekday_mon'),
                __('admin.shop_closures.weekday_tue'),
                __('admin.shop_closures.weekday_wed'),
                __('admin.shop_closures.weekday_thu'),
                __('admin.shop_closures.weekday_fri'),
                __('admin.shop_closures.weekday_sat'),
                __('admin.shop_closures.weekday_sun'),
            ],
            'weeks' => $weeks,
        ];
    }

    private function resolveMonth(string $month): Carbon
    {
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }
}
