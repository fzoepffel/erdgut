<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopDeliveryClosure;
use App\Services\Shop\ShopClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        $month = $this->resolveMonth((string) $request->query('month', ''));
        $calendar = $this->calendarPayload($month);

        return view('admin.shop-closures.index', [
            'month' => $month,
            'calendar' => $calendar,
            'activeRanges' => $this->closures->activeRanges(),
            'tomorrow' => Carbon::tomorrow()->toDateString(),
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
        $closed = $this->closures->toggleDay($date, (int) $request->user()->id);

        $month = $this->resolveMonth((string) ($validated['month'] ?? $date->format('Y-m')));

        return response()->json([
            'date' => $date->toDateString(),
            'closed' => $closed,
            'calendar' => $this->calendarPayload($month),
            'active_ranges' => $this->closures->activeRanges(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $startsOn = Carbon::parse($validated['starts_on'])->startOfDay();
        $endsOn = isset($validated['ends_on'])
            ? Carbon::parse($validated['ends_on'])->startOfDay()
            : $startsOn->copy();

        if ($this->closures->rangesOverlap($startsOn, $endsOn)) {
            throw ValidationException::withMessages([
                'starts_on' => [__('admin.shop_closures.validation_overlap')],
            ]);
        }

        ShopDeliveryClosure::query()->create([
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'reason' => $validated['reason'] ?? null,
            'created_by_user_id' => (int) $request->user()->id,
        ]);

        $month = $this->resolveMonth((string) ($validated['month'] ?? $startsOn->format('Y-m')));

        return redirect()
            ->route('app.admin.shop-closures.index', ['month' => $month->format('Y-m')])
            ->with('status', __('admin.shop_closures.created'));
    }

    public function destroy(Request $request, ShopDeliveryClosure $closure): RedirectResponse
    {
        $this->authorize('admin');

        $month = $this->resolveMonth((string) $request->query('month', $closure->starts_on->format('Y-m')));

        $closure->delete();

        return redirect()
            ->route('app.admin.shop-closures.index', ['month' => $month->format('Y-m')])
            ->with('status', __('admin.shop_closures.deleted'));
    }

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     prev_month: string,
     *     next_month: string,
     *     weekdays: list<string>,
     *     weeks: list<list<array{iso: string, day: int, in_month: bool, closed: bool, is_today: bool, is_past: bool}>>
     * }
     */
    private function calendarPayload(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $closedSet = array_fill_keys($this->closures->closedIsoDatesBetween($start, $end), true);
        $today = Carbon::today()->toDateString();

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $iso = $cursor->toDateString();
                $week[] = [
                    'iso' => $iso,
                    'day' => (int) $cursor->day,
                    'in_month' => $cursor->month === $month->month,
                    'closed' => isset($closedSet[$iso]),
                    'is_today' => $iso === $today,
                    'is_past' => $iso < $today,
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
