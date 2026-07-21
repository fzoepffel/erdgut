<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopReducedOfferRule;
use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Admin controls for reduced-offer mode, surfaced on the shop-closures page:
 * a recurring weekly window, one-off manual periods, and an "open now" that
 * ends the current window early and notifies the waitlist.
 */
class AdminReducedOfferController extends Controller
{
    public function __construct(private readonly ShopReducedOfferService $reducedOffer) {}

    /**
     * Start (or schedule) a one-off reduced period with an explicit reopen
     * datetime. The countdown ticks to that datetime; the waitlist is emailed
     * when it reopens (auto or via "open now").
     */
    public function start(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'starts_at' => ['nullable', 'date'],
            'reopens_at' => ['required', 'date'],
        ]);

        $startsAt = isset($validated['starts_at']) && $validated['starts_at'] !== null
            ? Carbon::parse($validated['starts_at'])
            : Carbon::now();
        $reopensAt = Carbon::parse($validated['reopens_at']);

        if ($reopensAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'reopens_at' => [__('admin.reduced_offer.validation_reopen_after_start')],
            ]);
        }

        $this->reducedOffer->startManual($startsAt, $reopensAt, (int) $request->user()->id);

        return back()->with('status', __('admin.reduced_offer.flash_started'));
    }

    /**
     * Reopen the shop now, ending the active reduced window early and emailing
     * every waitlist subscriber that the full offer is back.
     */
    public function openNow(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $period = $this->reducedOffer->openNow((int) $request->user()->id);

        if ($period === null) {
            return back()->with('status', __('admin.reduced_offer.flash_not_reduced'));
        }

        $sent = $this->reducedOffer->notifySubscribers($period);

        return back()->with('status', __('admin.reduced_offer.flash_opened', ['count' => $sent]));
    }

    /**
     * Create or update the recurring weekly reduced-offer window.
     */
    public function rulesStore(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'rule_id' => ['nullable', 'integer', 'exists:shop_reduced_offer_rules,id'],
            'starts_weekday' => ['required', 'integer', 'between:0,6'],
            'starts_time' => ['required', 'date_format:H:i'],
            'reopens_weekday' => ['required', 'integer', 'between:0,6'],
            'reopens_time' => ['required', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        ShopReducedOfferRule::query()->updateOrCreate(
            ['id' => $validated['rule_id'] ?? null],
            [
                'starts_weekday' => (int) $validated['starts_weekday'],
                'starts_time' => $validated['starts_time'].':00',
                'reopens_weekday' => (int) $validated['reopens_weekday'],
                'reopens_time' => $validated['reopens_time'].':00',
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'created_by_user_id' => (int) $request->user()->id,
            ],
        );
        $this->reducedOffer->flushRuleCache();

        return back()->with('status', __('admin.reduced_offer.flash_rule_saved'));
    }

    public function rulesDestroy(Request $request, ShopReducedOfferRule $rule): RedirectResponse
    {
        $this->authorize('admin');

        $rule->delete();
        $this->reducedOffer->flushRuleCache();

        return back()->with('status', __('admin.reduced_offer.flash_rule_deleted'));
    }
}
