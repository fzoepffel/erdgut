<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        if (! $this->closures->isManageableDate($date)) {
            throw ValidationException::withMessages([
                'date' => [__('admin.shop_closures.validation_past_date')],
            ]);
        }

        $closed = $this->closures->toggleDay($date, (int) $request->user()->id);

        $month = $this->resolveMonth((string) ($validated['month'] ?? $date->format('Y-m')));

        return response()->json([
            'date' => $date->toDateString(),
            'closed' => $closed,
            'calendar' => $this->calendarPayload($month),
        ]);
    }

    /**
     * @return array{
     *     month: string,
     *     label: string,
     *     prev_month: string,
     *     next_month: string,
     *     weekdays: list<string>,
     *     weeks: list<list<array{iso: string, day: int, in_month: bool, closed: bool, is_today: bool, is_disabled: bool}>>
     * }
     */
    private function calendarPayload(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $closedSet = array_fill_keys($this->closures->closedIsoDatesBetween($start, $end), true);
        $today = Carbon::today()->toDateString();
        $earliestIso = $this->closures->earliestManageableDate()->toDateString();

        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $iso = $cursor->toDateString();
                $isDisabled = $iso < $earliestIso;
                $week[] = [
                    'iso' => $iso,
                    'day' => (int) $cursor->day,
                    'in_month' => $cursor->month === $month->month,
                    'closed' => ! $isDisabled && isset($closedSet[$iso]),
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
