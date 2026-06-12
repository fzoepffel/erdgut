<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopDeliveryClosure;
use App\Services\Shop\ShopClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminShopClosureController extends Controller
{
    public function __construct(private readonly ShopClosureService $closures) {}

    public function index(): View
    {
        $this->authorize('admin');

        $today = Carbon::today()->toDateString();

        $upcoming = ShopDeliveryClosure::query()
            ->with('createdBy')
            ->whereDate('ends_on', '>=', $today)
            ->orderBy('starts_on')
            ->get();

        $past = ShopDeliveryClosure::query()
            ->with('createdBy')
            ->whereDate('ends_on', '<', $today)
            ->orderByDesc('starts_on')
            ->limit(50)
            ->get();

        return view('admin.shop-closures.index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'tomorrow' => Carbon::tomorrow()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
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

        return redirect()
            ->route('app.admin.shop-closures.index')
            ->with('status', __('admin.shop_closures.created'));
    }

    public function destroy(ShopDeliveryClosure $closure): RedirectResponse
    {
        $this->authorize('admin');

        $closure->delete();

        return redirect()
            ->route('app.admin.shop-closures.index')
            ->with('status', __('admin.shop_closures.deleted'));
    }
}
