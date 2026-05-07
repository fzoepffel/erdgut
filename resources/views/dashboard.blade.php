@php
    use App\Support\Dashboard\DashboardKind;

    $firstDeliveryCard = $deliveryCards->first();
    $producerOfWeek = \App\Support\EditorialContent::producerOfTheWeek();
    $weeklyIntro = \App\Support\EditorialContent::weeklyIntro();
    $founderNote = \App\Support\EditorialContent::founderNote();

    $kind = $dashboardKind ?? DashboardKind::NewVisitor;
@endphp

<x-app-layout>
    {{-- D9 (UX notes): tighter overall vertical rhythm. The previous
         space-y-8 / pb-16 / pt-10 wasted multiple viewport heights of
         empty cream between cards. Trimmed to space-y-6 / pb-12 / pt-8
         so action items reach the fold sooner. --}}
    <div class="pb-8 sm:pb-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 pt-6 sm:px-6 sm:pt-8 lg:px-8">
            <x-customer.hero-band
                chapter="produce"
                density="compact"
                :image="asset('images/mock/hero/hero-farm.jpg')"
                :eyebrow="__('Dashboard')"
                :title="__('customer.story.dashboard.greeting', ['name' => Auth::user()->name])"
                :lead="$weeklyIntro"
                :caption="now()->locale(app()->getLocale())->isoFormat('MMMM YYYY')"
            />

            {{-- Status flash banners (same regardless of state) --}}
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

            {{-- ─── State-aware top block ─────────────────────────────────────
                 Resume-setup CTAs come first because they're the most urgent
                 thing for that user; trial blocks come next; the steady-state
                 subscription "first box arrives" hero is last. Exactly one of
                 these renders for any given state. --}}

            @if ($kind->showsResumeSubscriptionSetup())
                @php
                    $sub = $primarySubscription;
                    $sizeLabel = $sub ? __(ucfirst($sub->box_size)) : null;
                    $hasEggsAddon = $sub && (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                @endphp
                <section
                    class="border border-chapter-meat/40 bg-chapter-meat/[0.04] p-6 sm:p-8"
                    aria-labelledby="dashboard-resume-setup-heading"
                    data-testid="dashboard-resume-subscription-setup"
                >
                    <p class="eyebrow text-chapter-meat">{{ __('status.pending_payment') }}</p>
                    <h2 id="dashboard-resume-setup-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl">
                        {{ __('Finish setting up your subscription') }}
                    </h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/75">
                        {{ __('You picked your box and preferences but did not complete the payment step. Your subscription is not active yet — no charge has been made and no box will be delivered until payment is set up.') }}
                    </p>

                    @if ($sub)
                        <dl class="mt-6 grid gap-4 border-t border-chapter-meat/20 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
                                <dd class="mt-2 font-display text-base text-brand-ink">{{ $sizeLabel }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Cadence') }}</dt>
                                <dd class="mt-2 font-display text-base text-brand-ink">
                                    {{ $sub->plan === \App\Enums\SubscriptionPlan::Weekly ? __('Every week') : __('Every two weeks') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Eggs add-on') }}</dt>
                                <dd class="mt-2 font-display text-base text-brand-ink">{{ $hasEggsAddon ? __('Yes') : __('No') }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Total per delivery') }}</dt>
                                <dd class="mt-2 font-display text-base text-brand-ink tabular-nums">
                                    @money($sub->expectedChargeAmount()) €
                                </dd>
                            </div>
                        </dl>
                    @endif

                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <x-solid-link :href="route('app.subscriptions.onboard.payment')"
                                      data-track-section="resume_setup.payment"
                                      data-track-destination="app.subscriptions.onboard.payment">
                            {{ __('Resume payment setup') }}
                        </x-solid-link>
                        <a href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                           class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                           data-track-section="resume_setup.restart"
                           data-track-destination="app.onboarding.start">
                            {{ __('Or restart and change your selections') }}
                        </a>
                    </div>
                </section>
            @endif

            @if ($kind->showsTrialBlock() && $openTrialOrder)
                <section
                    class="border border-chapter-eggdairy/30 bg-chapter-eggdairy/[0.05] p-6 sm:p-8"
                    aria-labelledby="dashboard-trial-block-heading"
                    data-testid="dashboard-trial-block"
                >
                    <p class="eyebrow text-chapter-eggdairy">{{ __('Trial box') }}</p>
                    <h2 id="dashboard-trial-block-heading" class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                        @if ($kind === DashboardKind::TrialDelivered)
                            {{ __('Your trial box was delivered') }}
                        @elseif ($openTrialOrder->scheduled_for_date)
                            {{ __('Your trial box arrives :date', ['date' => $openTrialOrder->scheduled_for_date->locale(app()->getLocale())->isoFormat('dd, D. MMM')]) }}
                        @else
                            {{ __('Your trial box is on the way') }}
                        @endif
                    </h2>

                    <dl class="mt-5 grid gap-4 border-t border-chapter-eggdairy/20 pt-5 sm:grid-cols-3">
                        <div>
                            <dt class="eyebrow text-brand-ink/60">{{ __('Order') }}</dt>
                            <dd class="mt-2 text-base text-brand-ink">#{{ $openTrialOrder->id }}</dd>
                        </div>
                        <div>
                            <dt class="eyebrow text-brand-ink/60">{{ __('Status') }}</dt>
                            <dd class="mt-2 text-base text-brand-ink">{{ $openTrialOrder->status->label() }}</dd>
                        </div>
                        <div>
                            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
                            <dd class="mt-2 text-base text-brand-ink">{{ __(ucfirst($openTrialOrder->box_size)) }}</dd>
                        </div>
                    </dl>

                    @if ($trialBox)
                        <div class="mt-6 border-t border-chapter-eggdairy/20 pt-5">
                            <p class="eyebrow text-chapter-eggdairy">{{ __('In the trial box') }}</p>
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                @forelse ($trialBox->items as $item)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-brand-ink">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                        <span class="tabular-nums text-brand-ink/60">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                    </li>
                                @empty
                                    <li class="text-sm text-brand-ink/65">{{ __('Composed shortly before delivery — items will appear here once finalised.') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    @endif

                    {{-- Conversion CTA: only when the user doesn't already have a sub being set up --}}
                    @if ($kind === DashboardKind::TrialOnTheWay || $kind === DashboardKind::TrialDelivered)
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a href="{{ route('app.onboarding.start', ['intent' => 'subscription', 'from_order' => $openTrialOrder->id]) }}"
                               class="inline-flex items-center border border-chapter-produce bg-chapter-produce px-4 py-2 text-xs font-semibold uppercase tracking-eyebrow text-white transition hover:bg-chapter-produce/90"
                               data-testid="dashboard-trial-convert-cta"
                               data-track-section="trial_block.convert"
                               data-track-destination="app.onboarding.start">
                                {{ __('Turn your trial into a weekly box') }}
                            </a>
                            <p class="font-editorial text-sm italic text-brand-ink/65">
                                {{ __('We keep your address and preferences — you only need to set up your payment card.') }}
                            </p>
                        </div>
                    @endif
                </section>
            @endif

            {{-- W2-24: brand-new-subscriber hero. Only fires for the "Active +
                 token + no box yet" state — DashboardKind::SubscriptionFirstWeek.
                 K8/K17 (UX notes): prefer the next *actual* scheduled delivery
                 over the cadence-based calculation. The calculation gives a
                 forward-looking estimate that is correct on day one of a fresh
                 subscription but quickly drifts as soon as ops creates a real
                 Box / Delivery for a different week or the customer adjusts
                 their schedule.
                 D1 (UX notes): the older fallback to the cadence-only
                 estimate has been removed. The hero only promises a date
                 when a real Delivery row exists — otherwise it shows the
                 softer "we are planning" copy so the customer is not
                 told a date that has no record behind it. --}}
            @if ($kind === DashboardKind::SubscriptionFirstWeek)
                @php
                    $actualNextDelivery = $firstDeliveryCard['delivery']?->scheduled_for_date ?? null;
                @endphp
                <section
                    class="border border-chapter-produce/30 bg-chapter-produce/5"
                    aria-labelledby="dashboard-new-subscriber-hero-heading"
                    data-testid="dashboard-new-subscriber-hero"
                >
                    <div class="px-6 py-6">
                        <p class="eyebrow text-chapter-produce">{{ __('Welcome to Erdgut') }}</p>
                        @if ($actualNextDelivery)
                            <h2 id="dashboard-new-subscriber-hero-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl">
                                {{ __('Your first box arrives :date', ['date' => $actualNextDelivery->locale(app()->getLocale())->isoFormat('dd, D. MMM')]) }}
                            </h2>
                            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/70">
                                {{ __('We compose your box from the season — exactly what is harvested that week. You will see the products listed here as soon as your box is composed (Thursdays, the day before delivery).') }}
                            </p>
                        @else
                            <h2 id="dashboard-new-subscriber-hero-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl" data-testid="dashboard-first-box-pending">
                                {{ __('We are planning your first delivery.') }}
                            </h2>
                            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/70">
                                {{ __('We will confirm the date and timeslot here as soon as your delivery slot is reserved.') }}
                            </p>
                        @endif
                    </div>
                </section>
            @endif

            {{-- This week at a glance — adapts per state.
                 D9 (UX notes): card-header padding tightened from
                 py-6 to py-5 and inner grid from gap-8/p-6 to
                 gap-6/p-5 so the three glance cards land in one
                 viewport on desktop. --}}
            <section class="border border-brand-ink/15" aria-labelledby="dashboard-this-week-heading">
                <div class="border-b border-brand-ink/15 px-6 py-5">
                    <p class="eyebrow text-chapter-produce">{{ __('At a glance') }}</p>
                    <h2 id="dashboard-this-week-heading" class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                        {{ __('This week at a glance') }}
                    </h2>
                    <p class="mt-1 text-sm text-brand-ink/70">
                        {{ __('Next delivery, box deadlines, and your subscription status.') }}
                    </p>
                </div>
                <div class="grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
                    {{-- Card 1: Next delivery (only meaningful for active/paused subs) --}}
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Next delivery') }}</p>
                        @if ($firstDeliveryCard)
                            @php
                                // Use the full block form rather than the
                                // single-line shorthand: the latter breaks
                                // subsequent block compilation. See
                                // docs/feedback_blade_php_shorthand.md.
                                $delivery = $firstDeliveryCard['delivery'];
                            @endphp
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
                        @elseif ($kind === DashboardKind::SubscriptionFirstWeek)
                            {{-- D1 (UX notes): the cadence-only estimate
                                 used to render here even when no Delivery
                                 row existed — fooling the customer into
                                 expecting a date that wasn't backed by a
                                 record. Soft-pending message instead. --}}
                            <p class="mt-3 text-sm text-brand-ink/70" data-testid="dashboard-glance-first-delivery-pending">
                                {{ __('We are planning your first delivery — we will confirm here as soon as it is scheduled.') }}
                            </p>
                            <a href="{{ route('app.deliveries.preferences.edit') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.next_delivery"
                               data-track-destination="app.deliveries.preferences.edit">
                                {{ __('Set delivery preferences') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($kind === DashboardKind::SubscriptionPaused)
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Your subscription is currently paused — deliveries resume after the pause window.') }}</p>
                            <a href="{{ route('app.subscriptions.show') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.next_delivery"
                               data-track-destination="app.subscriptions.show">
                                {{ __('Manage subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($kind->showsTrialBlock())
                            <p class="mt-3 text-sm text-brand-ink/70">
                                {{ __('Your trial box is a one-off — no recurring deliveries are scheduled yet.') }}
                            </p>
                        @elseif ($kind->showsResumeSubscriptionSetup())
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No deliveries scheduled — finish payment setup to schedule your first box.') }}</p>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet — we will plan deliveries once you subscribe.') }}</p>
                        @endif
                    </div>

                    {{-- Card 2: Your box (subscription box only — trial box has its own block above) --}}
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
                        @elseif ($kind === DashboardKind::SubscriptionFirstWeek)
                            {{-- D2 (UX notes): mention reality of Wed-only
                                 delivery, not the older Mon copy. --}}
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('We compose your box before each Wednesday delivery — your first box will appear here once it is composed.') }}</p>
                        @elseif ($kind->showsTrialBlock())
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('See the trial-box block above for this week\'s details.') }}</p>
                        @elseif ($kind->showsResumeSubscriptionSetup())
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Your box will appear here once your subscription is fully set up.') }}</p>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Start a subscription to receive a weekly box.') }}</p>
                        @endif
                    </div>

                    {{-- Card 3: Subscription status --}}
                    <div class="sm:col-span-2 lg:col-span-1">
                        <p class="eyebrow text-chapter-meat">{{ __('Subscription') }}</p>
                        @if ($primarySubscription)
                            @php
                                /* K16: Subscription.status stays Active until "today" enters
                                   the pause window. Expose pause_from explicitly so the card
                                   can flag a pending pause while the sub is still nominally
                                   Active, and a back-on-date once the pause is running. */
                                $pauseFrom = $primarySubscription->pause_from;
                                $pauseUntil = $primarySubscription->pause_until;
                                $todayDate = now()->toDateString();
                                $isCurrentlyPaused = $pauseFrom !== null
                                    && $pauseUntil !== null
                                    && $primarySubscription->isWithinPauseWindow();
                                $isPausePending = $pauseFrom !== null
                                    && $pauseFrom->toDateString() > $todayDate;
                                $resumeDate = $pauseUntil?->copy()->addDay();
                            @endphp
                            <p class="mt-3 font-display text-2xl text-brand-ink">
                                {{ __('status.'.$primarySubscription->status->value) }}
                            </p>
                            @if ($isCurrentlyPaused && $resumeDate)
                                <p class="mt-1 text-sm text-brand-ink/70" data-testid="dashboard-subscription-pause-active">
                                    {{ __('Back on :date', ['date' => $resumeDate->translatedFormat('d.m.Y')]) }}
                                </p>
                            @elseif ($isPausePending)
                                <p class="mt-1 text-sm text-brand-ink/70" data-testid="dashboard-subscription-pause-pending">
                                    {{ __('Pause from :date', ['date' => $pauseFrom->translatedFormat('d.m.Y')]) }}
                                </p>
                            @endif
                            {{-- Only show the recurring price for real subscriptions, not for
                                 the half-finished setup that hasn't committed to billing yet. --}}
                            @if (! $kind->showsResumeSubscriptionSetup())
                                <p class="mt-1 text-sm text-brand-ink/70">
                                    {{ __('EUR :amount / delivery', ['amount' => number_format($primarySubscription->expectedChargeAmount(), 2, ',', '.')]) }}
                                </p>
                            @endif
                            <a href="{{ route('app.subscriptions.show') }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.subscription"
                               data-track-destination="app.subscriptions.show">
                                {{ __('Manage subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet') }}</p>
                            <a href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                               class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                               data-track-section="glance.subscription"
                               data-track-destination="app.onboarding.start">
                                {{ __('Start a subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="border-t border-brand-ink/15 px-6 py-5">
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

            {{-- ─── Bottom rail: subscription boxes + deliveries ───────────
                 Only renders for states where the customer actually has a
                 real subscription whose boxes belong to them. Trial-only
                 customers see their trial info above — they shouldn't see
                 a "current week box" panel that pretends a subscription
                 exists. --}}
            @if ($kind->showsSubscriptionBoxes())
                {{-- D9 (UX notes): grid gap tightened from 8 to 6 and
                     inner card padding from p-6 to p-5 so the three
                     bottom-rail panels share more horizontal real estate
                     and less vertical air. --}}
                <div class="grid gap-6 lg:grid-cols-3">
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
                        <div class="p-5 sm:p-6">
                            <x-order-timeline
                                :cards="$deliveryCards"
                                :empty-description="__('Your delivery days will show up here once we compose your first box.')"
                            >
                                <x-slot name="emptyAction">
                                    <a href="{{ route('app.deliveries.preferences.edit') }}"
                                       class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                       data-track-section="feed.deliveries.empty"
                                       data-track-destination="app.deliveries.preferences.edit">
                                        {{ __('Review delivery preferences') }}
                                    </a>
                                </x-slot>
                            </x-order-timeline>
                        </div>
                    </section>

                    <section class="border border-brand-ink/15 lg:order-2">
                        <div class="border-b border-brand-ink/15 px-6 py-5">
                            <p class="eyebrow text-chapter-fish">{{ __('This week') }}</p>
                            <h3 class="mt-1 font-display text-xl text-brand-ink">{{ __('Current week box') }}</h3>
                        </div>
                        <div class="p-5 sm:p-6">
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
                        <div class="p-5 sm:p-6">
                            @if ($upcomingBoxes->isEmpty())
                                <x-empty-state
                                    :title="__('No boxes scheduled')"
                                    :description="__('Upcoming box weeks will be listed here as your plan rolls forward.')"
                                    variant="inset"
                                />
                            @else
                                <ul class="space-y-3">
                                    @foreach ($upcomingBoxes as $box)
                                        <li>
                                            <a href="{{ route('app.boxes.show', $box) }}"
                                               class="block border border-brand-ink/15 p-4 transition hover:border-brand-ink/40 hover:bg-brand-ink/[0.02] focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                                               data-track-section="feed.almanach.box_row"
                                               data-track-destination="app.boxes.show">
                                                <p class="font-display text-lg text-brand-ink">{{ __('Week of :date', ['date' => $box->week_start_date->locale(app()->getLocale())->isoFormat('D. MMM YYYY')]) }}</p>
                                                @php
                                                    $customerStatusKey = 'box_status_customer.'.$box->status->value;
                                                    $customerStatusLabel = __($customerStatusKey);
                                                @endphp
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
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const url = @js(route('app.dashboard.click'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            function track(section, destination) {
                const payload = JSON.stringify({ section, destination });
                try {
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
                    }).catch(() => {});
                } catch (_e) {}
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
