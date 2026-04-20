<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminWeeklyCatalogSaveRequest;
use App\Models\Producer;
use App\Models\Product;
use App\Services\Admin\WeeklyBoxPlannerService;
use App\Services\Admin\WeeklyCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminWeeklyCatalogController extends Controller
{
    public function __construct(
        private readonly WeeklyCatalogService $weeklyCatalog,
    ) {}

    public function show(?string $week = null): View
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);

        $rows = $this->weeklyCatalog->rowsForWeek($monday);

        $catalogProducts = Product::query()
            ->visibleInCatalog()
            ->orderBy('name')
            ->get(['id', 'name']);

        $producers = Producer::query()->orderBy('name')->get(['id', 'name']);

        $overCount = $rows->filter(fn (array $r): bool => (bool) ($r['over'] ?? false))->count();

        return view('admin.products.weekly', [
            'week' => $monday,
            'previousWeek' => $monday->copy()->subWeek(),
            'nextWeek' => $monday->copy()->addWeek(),
            'rows' => $rows,
            'catalogProducts' => $catalogProducts,
            'producers' => $producers,
            'categories' => ProductCategory::cases(),
            'overCount' => $overCount,
        ]);
    }

    public function save(AdminWeeklyCatalogSaveRequest $request, string $week): RedirectResponse
    {
        $this->authorize('admin');

        $monday = $this->resolveMonday($week);
        $rows = (array) $request->validated('rows', []);

        $this->weeklyCatalog->saveForWeek($monday, $rows);

        return redirect()
            ->route('app.admin.products.weekly.show', ['week' => $monday->toDateString()])
            ->with('status', __('admin.weekly_catalog.flash_saved'));
    }

    private function resolveMonday(?string $week): Carbon
    {
        if ($week === null || $week === '') {
            return WeeklyBoxPlannerService::defaultUpcomingWeekMonday();
        }

        $parsed = Carbon::parse($week)->startOfDay();
        if (! $parsed->isMonday()) {
            $parsed = $parsed->previous(Carbon::MONDAY)->startOfDay();
        }

        return $parsed;
    }
}
