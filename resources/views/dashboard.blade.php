@php
    $firstDeliveryCard = $deliveryCards->first();
    $producerOfWeek = \App\Support\EditorialContent::producerOfTheWeek();
    $weeklyIntro = \App\Support\EditorialContent::weeklyIntro();
    $founderNote = \App\Support\EditorialContent::founderNote();
@endphp

<x-app-layout>
    <div class="pb-10 sm:pb-16">
        <div class="mx-auto max-w-6xl space-y-8 px-4 pt-8 sm:px-6 sm:pt-10 lg:px-8">
            <x-customer.hero-band
                chapter="produce"
                :image="asset('images/mock/hero/hero-farm.jpg')"
                :eyebrow="__('Dashboard')"
                :title="__('customer.story.dashboard.greeting', ['name' => Auth::user()->name])"
                :lead="$weeklyIntro"
                :caption="now()->locale(app()->getLocale())->isoFormat('MMMM YYYY')"
            />
            @if (session('status') === 'current-box-missing')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('No current week box is available yet.') }}
                </p>
            @endif

            @if (session('status') === 'subscriber-area-requires-setup')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Complete your subscription and payment setup to access deliveries and billing.') }}
                </p>
            @endif

            @if ($primarySubscription && $primarySubscription->isWithinPauseWindow(now()))
                <div class="flex flex-col gap-3 border border-chapter-produce/40 bg-chapter-produce/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p class="text-sm text-brand-ink">
                        <span class="font-semibold">{{ __('subscriptions.active_skip_banner') }}</span>
                        — {{ __('subscriptions.skip_window_text', ['from' => $primarySubscription->pause_from?->format('d.m.Y'), 'until' => $primarySubscription->pause_until?->format('d.m.Y')]) }}
                    </p>
                    <form method="POST" action="{{ route('app.subscriptions.resume') }}">
                        @csrf
                        <x-primary-button type="submit">
                            {{ __('subscriptions.end_skip_cta') }}
                        </x-primary-button>
                    </form>
                </div>
            @endif

            @if (session('status') === 'subscription-onboarded')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Your subscription is active. Deliveries and the full app are now available.') }}
                </p>
            @endif

            @isset($openTrialOrder)
                @if ($openTrialOrder)
                    <div class="flex flex-col gap-3 border border-chapter-produce/40 bg-chapter-produce/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" data-testid="dashboard-trial-strip">
                        <p class="text-sm text-brand-ink">
                            <span class="font-semibold">{{ __('Trial box') }}</span>
                            — {{ __('Order #:id · :status', ['id' => $openTrialOrder->id, 'status' => $openTrialOrder->status->label()]) }}
                            @if ($openTrialOrder->scheduled_for_date)
                                · {{ $openTrialOrder->scheduled_for_date->format('D d M') }}
                            @endif
                        </p>
                        @if (! $primarySubscription)
                            <a href="{{ route('app.onboarding.start', ['intent' => 'subscription', 'from_order' => $openTrialOrder->id]) }}"
                               class="inline-flex items-center border border-chapter-produce bg-chapter-produce text-white px-4 py-2 text-xs font-semibold uppercase tracking-eyebrow hover:bg-chapter-produce/90"
                               data-testid="dashboard-trial-convert-cta"
                               data-track-section="header_alert.trial_conversion"
                               data-track-destination="app.onboarding.start">
                                {{ __('Turn your trial into a weekly box') }}
                            </a>
                        @endif
                    </div>
                @endif
            @endisset

            @if ($needsPaymentSetup)
                <div class="flex flex-col gap-3 border border-chapter-meat/40 bg-chapter-meat/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-brand-ink">
                        <span class="font-semibold">{{ __('Add a payment method') }}</span>
                        — {{ __('Complete setup so we can charge your subscription on schedule.') }}
                    </p>
                    <x-solid-link :href="route('app.subscriptions.onboard.payment')"
                                  data-track-section="header_alert.payment_setup"
                                  data-track-destination="app.subscriptions.onboard.payment">
                        {{ __('Set up payments') }}
                    </x-solid-link>
                </div>
            @endif

            {{-- W2-24: brand-new-subscriber hero. When a subscriber has a
                 confirmed first delivery date but no composed box yet, the
                 dashboard used to be six near-empty cards. Now it leads with
                 a warm welcome + the concrete first-delivery date so the
                 customer's first impression isn't blanks. --}}
            @php
                $isNewSubscriber = $primarySubscription
                    && $primarySubscription->status === \App\Enums\SubscriptionStatus::Active
                    && $currentBox === null
                    && ! $firstDeliveryCard;
                $heroFirstDelivery = $isNewSubscriber
                    ? $primarySubscription->firstExpectedDeliveryDate()
                    : null;
            @endphp
            @if ($isNewSubscriber && $heroFirstDelivery)
                <section
                    class="border border-chapter-produce/30 bg-chapter-produce/5"
                    aria-labelledby="dashboard-new-subscriber-hero-heading"
                    data-testid="dashboard-new-subscriber-hero"
                >
                    <div class="px-6 py-6">
                        <p class="eyebrow text-chapter-produce">{{ __('Welcome to Erdgut') }}</p>
                        <h2 id="dashboard-new-subscriber-hero-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl">
                            {{ __('Your first box arrives :date', ['date' => $heroFirstDelivery->locale(app()->getLocale())->isoFormat('dd, D. MMM')]) }}
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/70">
                            {{ __('We compose your box from the season — exactly what is harvested that week. You will see the products listed here as soon as your box is composed (Thursdays, the day before delivery).') }}
                        </p>
                    </div>
                </section>
            @endif

            {{-- This week at a glance --}}
            <section class="border border-brand-ink/15" aria-labelledby="dashboard-this-week-heading">
                <div class="border-b border-brand-ink/15 px-6 py-6">
                    <p class="eyebrow text-chapter-produce">{{ __('At a glance') }}</p>
                    <h2 id="dashboard-this-week-heading" class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                        {{ __('This week at a glance') }}
                    </h2>
                    <p class="mt-2 text-sm text-brand-ink/70">
                        {{ __('Next delivery, box deadlines, and your subscription status.') }}
                    </p>
                </div>
                <div class="grid gap-8 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Next delivery') }}</p>
                        @if ($firstDeliveryCard)
                            @php($delivery = $firstDeliveryCard['delivery'])
                            <p class="mt-3 font-display text-2xl text-brand-ink">{{ $delivery->scheduled_for_date->locale(app()->getLocale())->isoFormat('dd, D. MMM YYYY') }}</p>
                            <p class="mt-1 text-sm text-brand-ink/70">{{ $delivery->timeslot }}</p>
                            <p class="text-sm text-brand-ink/70">
                                {{ $delivery->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                            </p>
                            <a href="{{ route('app.deliveries.index') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.next_delivery"
                               data-track-destination="app.deliveries.index">
                                {{ __('Manage deliveries') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($primarySubscription)
                            {{-- W2-22: was reading next_charge_date as the next delivery date,
                                 which could be in the past for new subs (next_charge_date is a
                                 BILLING date, not a ship date). The subscription model now
                                 returns the first cadence-matching, non-paused Wednesday from
                                 today onward. --}}
                            @php($firstDelivery = $primarySubscription->firstExpectedDeliveryDate())
                            @if ($firstDelivery)
                                <p class="mt-3 font-display text-2xl text-brand-ink">{{ $firstDelivery->locale(app()->getLocale())->isoFormat('dd, D. MMM') }}</p>
                                <p class="mt-1 text-sm text-brand-ink/70">{{ __('First delivery — we will confirm day and timeslot once your box is composed.') }}</p>
                            @else
                                <p class="mt-3 text-sm text-brand-ink/70">{{ __('Your subscription is currently paused — deliveries resume after the pause window.') }}</p>
                            @endif
                            <a href="{{ route('app.deliveries.preferences.edit') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.next_delivery"
                               data-track-destination="app.deliveries.preferences.edit">
                                {{ __('Set delivery preferences') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet — we will plan deliveries once you subscribe.') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.next_delivery"
                               data-track-destination="app.subscriptions.create">
                                {{ __('Start a subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-fish">{{ __('Your box') }}</p>
                        @if ($currentBox)
                            <p class="mt-3 font-display text-2xl text-brand-ink">
                                {{ __('Week of :date', ['date' => $currentBox->week_start_date->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}
                            </p>
                            <a href="{{ route('app.boxes.current') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.current_box"
                               data-track-destination="app.boxes.current">
                                {{ __('View current box') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($primarySubscription)
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Box composition runs each Thursday for the following Monday delivery — your first box will appear here once it is composed.') }}</p>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Start a subscription to receive a weekly box.') }}</p>
                        @endif
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <p class="eyebrow text-chapter-meat">{{ __('Subscription') }}</p>
                        @if ($primarySubscription)
                            <p class="mt-3 font-display text-2xl text-brand-ink">
                                {{ __('status.'.$primarySubscription->status->value) }}
                            </p>
                            {{-- W2-16 item 3: surface the recurring price so the customer
                                 can spot-check it against the wizard's payment step + the
                                 actual SumUp charge they'll see on the card statement. --}}
                            <p class="mt-1 text-sm text-brand-ink/70">
                                {{ __('EUR :amount / delivery', ['amount' => number_format($primarySubscription->expectedChargeAmount(), 2, ',', '.')]) }}
                            </p>
                            <a href="{{ route('app.subscriptions.show') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.subscription"
                               data-track-destination="app.subscriptions.show">
                                {{ __('Manage subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.subscription"
                               data-track-destination="app.subscriptions.create">
                                {{ __('Start a subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Replaces the old "Quick actions" row (Address & timeslot,
                     Payments, Producers). Address/Payments are reachable via
                     the user dropdown; the producer spotlight covers the third.
                     Bringing the homepage's producer voice onto the dashboard. --}}
                <div class="border-t border-brand-ink/15 px-6 py-6">
                    <x-customer.producer-spotlight :producer="$producerOfWeek" />
                </div>
            </section>

            <x-customer.story-block
                chapter="bread"
                :image="asset('images/mock/stalls/produce/scene.jpg')"
                :eyebrow="__('customer.story.weekly_story_eyebrow')"
                :quote="$founderNote"
                :signature="__('customer.story.dashboard.founder_signature')"
            />

            <div class="grid gap-8 lg:grid-cols-3">
                <section class="border border-brand-ink/15 lg:order-1">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex flex-col gap-1">
                                <p class="eyebrow text-chapter-produce">{{ __('Deliveries') }}</p>
                                <h3 class="font-display text-xl text-brand-ink">{{ __('Next deliveries') }}</h3>
                            </div>
                            <a href="{{ route('app.deliveries.index') }}"
                               class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="feed.deliveries"
                               data-track-destination="app.deliveries.index">
                                {{ __('Manage all') }}
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <x-order-timeline
                            :cards="$deliveryCards"
                            :empty-description="$primarySubscription
                                ? __('Your delivery days will show up here once we compose your first box.')
                                : __('When your subscription is active, your delivery days will show up here.')"
                        >
                            <x-slot name="emptyAction">
                                @if ($primarySubscription)
                                    <a href="{{ route('app.deliveries.preferences.edit') }}"
                                       class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                       data-track-section="feed.deliveries.empty"
                                       data-track-destination="app.deliveries.preferences.edit">
                                        {{ __('Review delivery preferences') }}
                                    </a>
                                @else
                                    <a href="{{ route('app.subscriptions.create') }}"
                                       class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                       data-track-section="feed.deliveries.empty"
                                       data-track-destination="app.subscriptions.create">
                                        {{ __('Start a subscription') }}
                                    </a>
                                @endif
                            </x-slot>
                        </x-order-timeline>
                    </div>
                </section>

                <section class="border border-brand-ink/15 lg:order-2">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <p class="eyebrow text-chapter-fish">{{ __('This week') }}</p>
                        <h3 class="mt-1 font-display text-xl text-brand-ink">{{ __('Current week box') }}</h3>
                    </div>
                    <div class="p-6">
                        @if ($currentBox)
                            <p class="text-sm text-brand-ink/70">
                                {{ __('Week of :date', ['date' => $currentBox->week_start_date->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}
                            </p>
                            <p class="mt-2">
                                <a href="{{ route('app.boxes.current') }}"
                                   class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                   data-track-section="feed.current_box"
                                   data-track-destination="app.boxes.current">
                                    {{ __('View current box') }}
                                </a>
                            </p>
                            <ul class="mt-5 space-y-2 border-t border-brand-ink/10 pt-4">
                                @forelse ($currentBox->items as $item)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-brand-ink">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                        <span class="text-brand-ink/60">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                    </li>
                                @empty
                                    <li class="text-sm text-brand-ink/60">{{ __('No items in the current box yet.') }}</li>
                                @endforelse
                            </ul>
                            <p class="mt-5 border-t border-brand-ink/10 pt-4 text-sm font-semibold text-brand-ink">
                                {{ __('Estimated value: EUR :amount', ['amount' => \App\Support\Money::format($currentBox->estimated_value)]) }}
                            </p>
                        @else
                            <x-empty-state
                                :title="__('No box for this week')"
                                :description="__('Your weekly box will appear here once it is generated for your delivery week.')"
                                variant="inset"
                            >
                                <x-slot name="action">
                                    <a href="{{ route('app.subscriptions.show') }}"
                                       class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                       data-track-section="feed.current_box.empty"
                                       data-track-destination="app.subscriptions.show">
                                        {{ __('Check subscription') }}
                                    </a>
                                </x-slot>
                            </x-empty-state>
                        @endif
                    </div>
                </section>

                <section class="border border-brand-ink/15 lg:order-3">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <p class="eyebrow text-chapter-almanach">{{ __('Almanach') }}</p>
                        <h3 class="mt-1 font-display text-xl text-brand-ink">{{ __('Current and upcoming boxes') }}</h3>
                    </div>
                    <div class="p-6">
                        @if ($upcomingBoxes->isEmpty())
                            <x-empty-state
                                :title="__('No boxes scheduled')"
                                :description="__('Upcoming box weeks will be listed here as your plan rolls forward.')"
                                variant="inset"
                            >
                                @if (! $primarySubscription)
                                    <x-slot name="action">
                                        <a href="{{ route('app.subscriptions.create') }}"
                                           class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                           data-track-section="feed.almanach.empty"
                                           data-track-destination="app.subscriptions.create">
                                            {{ __('Start a subscription') }}
                                        </a>
                                    </x-slot>
                                @endif
                            </x-empty-state>
                        @else
                            <ul class="space-y-3">
                                @foreach ($upcomingBoxes as $box)
                                    <li>
                                        <a href="{{ route('app.boxes.show', $box) }}"
                                           class="block border border-brand-ink/15 p-4 transition hover:border-brand-ink/40 hover:bg-brand-ink/[0.02] focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                                           data-track-section="feed.almanach.box_row"
                                           data-track-destination="app.boxes.show">
                                            <p class="font-display text-lg text-brand-ink">{{ __('Week of :date', ['date' => $box->week_start_date->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}</p>
                                            {{-- Customer-friendly box status labels (E in followups). The
                                                 admin-facing `status.editable` → "Bearbeitbar" etc. would leak
                                                 internal lifecycle terms; `box_status_customer.*` is the
                                                 customer-side mapping ("In Vorbereitung", "Wird zugestellt",
                                                 "Geliefert"), available in de/en/fr. --}}
                                            @php($customerStatusKey = 'box_status_customer.'.$box->status->value)
                                            @php($customerStatusLabel = __($customerStatusKey))
                                            @if ($customerStatusLabel !== $customerStatusKey)
                                                <p class="mt-1 text-sm text-brand-ink/70">{{ $customerStatusLabel }}</p>
                                            @endif
                                            <p class="text-sm text-brand-ink/70">
                                                {{ __('Estimated value: EUR :amount', ['amount' => \App\Support\Money::format($box->estimated_value)]) }}
                                            </p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>

    {{-- Click-destination instrumentation. We collect anonymous click counts
         per dashboard region so the redesign decision (single feed vs.
         two-column vs. status-conditional, see customer-ux-walkthrough-followups)
         can be informed by what users actually use. Beacon-style send so we
         don't block navigation; on failure we just lose the row. --}}
    @push('scripts')
    <script>
        (function () {
            const url = @js(route('app.dashboard.click'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            function track(section, destination) {
                const payload = JSON.stringify({ section, destination });
                try {
                    if (navigator.sendBeacon) {
                        const blob = new Blob([payload], { type: 'application/json' });
                        // sendBeacon can't set custom headers, so we proxy through fetch+keepalive
                        // when CSRF protection requires the X-CSRF-TOKEN header.
                    }
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        keepalive: true,
                        body: payload,
                    }).catch(() => { /* no-op: instrumentation must never break the click */ });
                } catch (_e) {
                    /* no-op */
                }
            }

            document.addEventListener('click', function (e) {
                const el = e.target.closest('[data-track-section][data-track-destination]');
                if (!el) return;
                track(el.dataset.trackSection, el.dataset.trackDestination);
            }, { capture: true });
        })();
    </script>
    @endpush
</x-app-layout>
