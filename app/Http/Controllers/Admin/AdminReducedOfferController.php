<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopReducedOfferRule;
use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin controls for reduced-offer mode, on its own tab: current status and
 * waitlist, a recurring weekly window, one-off manual periods, and an "open
 * now" that ends the current window early and notifies the waitlist.
 */
class AdminReducedOfferController extends Controller
{
    public function __construct(private readonly ShopReducedOfferService $reducedOffer) {}

    public function index(): View
    {
        $this->authorize('admin');

        return view('admin.reduced-offer.index', [
            'reducedOffer' => $this->reducedOfferPayload(),
            'weekdayOptions' => $this->weekdayOptions(),
        ]);
    }

    /**
     * Current reduced-offer state for the admin panel: whether the shop is
     * reduced now, the reopen target, subscriber count, and the recurring rules.
     *
     * @return array{is_reduced: bool, reopens_at: ?string, reopens_label: ?string, source: ?string, subscriber_count: int, subscribers: list<array{email: string, since: ?string}>, rules: list<array{id: int, starts_weekday: int, starts_time: string, reopens_weekday: int, reopens_time: string, is_active: bool, summary: string}>}
     */
    private function reducedOfferPayload(): array
    {
        $window = $this->reducedOffer->currentWindow();

        $rules = ShopReducedOfferRule::query()
            ->orderBy('starts_weekday')
            ->get()
            ->map(fn (ShopReducedOfferRule $rule): array => [
                'id' => (int) $rule->id,
                'starts_weekday' => (int) $rule->starts_weekday,
                'starts_time' => Carbon::parse((string) $rule->starts_time)->format('H:i'),
                'reopens_weekday' => (int) $rule->reopens_weekday,
                'reopens_time' => Carbon::parse((string) $rule->reopens_time)->format('H:i'),
                'is_active' => (bool) $rule->is_active,
                'summary' => __('admin.reduced_offer.rule_summary', [
                    'start_day' => $this->weekdayLabel((int) $rule->starts_weekday),
                    'start_time' => Carbon::parse((string) $rule->starts_time)->format('H:i'),
                    'reopen_day' => $this->weekdayLabel((int) $rule->reopens_weekday),
                    'reopen_time' => Carbon::parse((string) $rule->reopens_time)->format('H:i'),
                ]),
            ])
            ->all();

        return [
            'is_reduced' => $window !== null,
            'reopens_at' => $window ? $window['reopens_at']->format('Y-m-d\TH:i') : null,
            'reopens_label' => $window ? $window['reopens_at']->translatedFormat('l, j. F H:i') : null,
            'source' => $window['source'] ?? null,
            'subscriber_count' => $this->reducedOffer->subscriberCount(),
            'subscribers' => $this->reducedOffer->subscriberList(),
            'rules' => $rules,
        ];
    }

    /**
     * @return list<array{v: int, l: string}>
     */
    private function weekdayOptions(): array
    {
        return [
            ['v' => 1, 'l' => $this->weekdayLabel(1)],
            ['v' => 2, 'l' => $this->weekdayLabel(2)],
            ['v' => 3, 'l' => $this->weekdayLabel(3)],
            ['v' => 4, 'l' => $this->weekdayLabel(4)],
            ['v' => 5, 'l' => $this->weekdayLabel(5)],
            ['v' => 6, 'l' => $this->weekdayLabel(6)],
            ['v' => 0, 'l' => $this->weekdayLabel(0)],
        ];
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
