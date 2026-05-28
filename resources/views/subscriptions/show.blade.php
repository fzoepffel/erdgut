@php
    use App\Enums\FulfillmentMode;
    use App\Enums\SubscriptionPlan;
    use App\Enums\SubscriptionStatus;

    $eggsDairyAddOn = (bool) data_get($subscription?->preferences, 'eggs_dairy_add_on', false);
    $meatAddOn      = (bool) data_get($subscription?->preferences, 'meat_add_on', false);

    $allergenList = array_values(array_filter((array) ($subscription?->allergies ?? [])));

    $boxGrowthAsset = match ($subscription?->box_size) {
        'small'  => 'images/box-growth/box-plant-stage-small-transparent.png',
        'medium' => 'images/box-growth/box-plant-stage-medium-transparent.png',
        'large'  => 'images/box-growth/box-plant-stage-large-transparent.png',
        default  => null,
    };

    $isCancelled  = $subscription?->status === SubscriptionStatus::Cancelled;
    $isPaused     = $subscription !== null && (
        $subscription->status === SubscriptionStatus::Paused || $subscription->hasPauseWindow()
    );
    $isActive     = $subscription?->status === SubscriptionStatus::Active;
    $isPending    = $subscription?->status === SubscriptionStatus::PendingPayment;

    $statusLabel = match ($subscription?->status) {
        SubscriptionStatus::Active         => __('Active'),
        SubscriptionStatus::Paused         => __('Paused'),
        SubscriptionStatus::Cancelled      => __('Cancelled'),
        SubscriptionStatus::PendingPayment => __('Pending setup'),
        default                            => '—',
    };

    $planLabel = match ($subscription?->plan) {
        SubscriptionPlan::Weekly   => __('Every week'),
        SubscriptionPlan::Biweekly => __('Every two weeks'),
        default                    => '—',
    };

    $boxSizeLabel = match ($subscription?->box_size) {
        'small'  => __('Small'),
        'medium' => __('Medium'),
        'large'  => __('Large'),
        default  => '—',
    };

    $fulfillmentLabel = ($subscription?->fulfillment_mode ?? FulfillmentMode::Delivery) === FulfillmentMode::Pickup
        ? __('Pickup')
        : __('Home delivery');

    $subNumber = str_pad((string) ($subscription?->id ?? 0), 4, '0', STR_PAD_LEFT);

    $monthStamp = mb_strtoupper(now()->locale(app()->getLocale())->translatedFormat('M')) . ' · ' . now()->format('Y');
    $stampStatus = $isActive ? __('Active') : ($isPaused ? __('Paused') : ($isCancelled ? __('Cancelled') : __('Pending')));

    // Skip picker: weeks as [{iso, label, sub, locked}]
    $weekCells = [];
    foreach ($skipPickerWeeks as $weekMonday) {
        $iso        = $weekMonday->toDateString();
        $isLocked   = in_array($iso, $pauseLockedWeeks, true);
        $weekSunday = $weekMonday->copy()->addDays(6);
        $start      = $weekMonday->locale(app()->getLocale());
        $end        = $weekSunday->locale(app()->getLocale());
        $sameMonth  = $weekMonday->month === $weekSunday->month;
        $rangeLabel = $sameMonth
            ? $start->isoFormat('D.') . '–' . $end->isoFormat('D. MMM')
            : $start->isoFormat('D. MMM') . '–' . $end->isoFormat('D. MMM');
        $weekCode   = 'KW ' . $weekMonday->isoWeek();
        $weekCells[] = [
            'iso'     => $iso,
            'label'   => $rangeLabel,
            'sub'     => $isLocked ? __('Locked by pause') : $weekCode,
            'locked'  => $isLocked,
        ];
    }
@endphp

<x-app-layout :hideAppFooter="true">

    {{-- ───────────────────────────────────────────────────────────────── --}}
    {{-- Flash messages                                                    --}}
    {{-- ───────────────────────────────────────────────────────────────── --}}
    @if (session('status'))
        <div class="border-b border-brand-ink/10 bg-brand-cream">
            <div class="mx-auto max-w-[1400px] px-6 py-4 lg:px-12">
                @if (session('status') === 'subscription-updated')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Subscription details saved.') }}</p>
                @elseif (session('status') === 'subscription-pause-scheduled')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Pause timeframe saved.') }}</p>
                @elseif (session('status') === 'subscription-next-week-skipped')
                    @php $skippedWeek = session('subscription_skipped_week'); @endphp
                    <p class="text-sm text-brand-ink" role="status" data-testid="subscription-skip-confirmation">
                        @if ($skippedWeek)
                            {{ __('Week of :date is paused — no delivery, no charge.', ['date' => \Carbon\Carbon::parse($skippedWeek)->translatedFormat('d.m.Y')]) }}
                        @else
                            {{ __('Next week is paused — no delivery, no charge.') }}
                        @endif
                    </p>
                @elseif (session('status') === 'subscription-resumed')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Subscription resumed immediately.') }}</p>
                @elseif (session('status') === 'subscription-cancelled')
                    <p class="text-sm text-brand-ink/70" role="status">{{ __('Subscription cancelled.') }}</p>
                @elseif (session('status') === 'subscription-skip-weeks-updated')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Skipped weeks updated.') }}</p>
                @elseif (session('status') === 'subscription-action-not-allowed')
                    <p class="text-sm text-chapter-meat" role="status">{{ __('This subscription action is not allowed in the current state.') }}</p>
                @elseif (in_array(session('status'), ['payment-setup-success', 'payment-updated', 'payment-already-setup', 'payment-setup-failed', 'payment-setup-unavailable', 'payment-update-unavailable'], true))
                    @include('payments.partials.session-status')
                @endif
            </div>
        </div>
    @endif

    @if ($subscription === null)
        {{-- ───────────────────────────────────────────────────────────── --}}
        {{-- Empty state                                                   --}}
        {{-- ───────────────────────────────────────────────────────────── --}}
        <section class="py-20 lg:py-28">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <x-empty-state
                    :title="__('No subscription yet')"
                    :description="__('Start a subscription to choose your box rhythm, size, and dietary preferences. You can set up payment right after.')"
                    variant="card"
                >
                    <x-slot name="action">
                        <x-solid-link :href="route('app.subscriptions.onboard')">
                            {{ __('Create subscription') }}
                        </x-solid-link>
                    </x-slot>
                </x-empty-state>
            </div>
        </section>

        <x-customer.editorial-footer-band class="mt-auto" />

    @else

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 1 · HERO                                                        --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <section class="relative border-b border-brand-ink/10 py-16 lg:py-24" aria-label="{{ __('Subscription overview') }}">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                {{-- Floating mono stamp --}}
                <p class="absolute right-6 top-6 hidden font-mono text-xs uppercase tracking-widest text-chapter-produce lg:block">
                    {{ $monthStamp }} · {{ mb_strtoupper($stampStatus) }}
                </p>

                <div class="grid gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-16">

                    {{-- Left: eyebrow + H1 + lead + actions --}}
                    <div class="flex flex-col justify-between">
                        <div>
                            <p class="eyebrow text-chapter-produce">{{ __('Subscription') }} · {{ __('Abo') }} {{ $subNumber }}</p>

                            <h1 class="mt-6 font-display text-5xl leading-[1.02] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-[5rem]">
                                {{ __('customer.subscription.hero_h1_line1') }}<br>
                                <em class="font-normal italic text-chapter-produce">{{ __('customer.subscription.hero_h1_line2') }}</em>
                            </h1>

                            <p class="mt-8 max-w-md text-base leading-relaxed text-brand-ink/60">
                                {{ __('customer.subscription.hero_lead') }}
                            </p>
                        </div>

                        {{-- Action row --}}
                        <div class="mt-12 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                            <a href="{{ route('app.subscriptions.edit') }}"
                               class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Edit subscription') }}
                            </a>

                            @if (! $isCancelled)
                                <form method="POST" action="{{ route('app.subscriptions.skip.next') }}" class="inline">
                                    @csrf
                                    <button type="submit" class="border-b border-brand-ink/30 pb-0.5 text-brand-ink/70 hover:text-brand-ink">
                                        {{ __('subscriptions.skip_next_week_cta') }}
                                    </button>
                                </form>

                                <button
                                    type="button"
                                    class="border-b border-brand-ink/30 pb-0.5 text-brand-ink/70 hover:text-brand-ink"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'confirm-subscription-pause')"
                                >
                                    {{ __('subscriptions.skip_range_cta') }}
                                </button>
                            @endif

                            @if ($isPaused)
                                <button
                                    type="button"
                                    class="border-b border-brand-ink/30 pb-0.5 text-brand-ink/70 hover:text-brand-ink"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'confirm-subscription-resume')"
                                >
                                    {{ __('subscriptions.end_skip_cta') }}
                                </button>
                            @endif

                            @if (! $isCancelled)
                                <button
                                    type="button"
                                    class="border-b border-chapter-meat/50 pb-0.5 text-chapter-meat hover:border-chapter-meat"
                                    x-data=""
                                    x-on:click="$dispatch('open-modal', 'confirm-subscription-cancel')"
                                >
                                    {{ __('Cancel subscription') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Right: At a glance panel --}}
                    <div class="bg-brand-paper p-8 lg:p-10">
                        <div class="flex items-start justify-between">
                            <span class="eyebrow text-chapter-produce">{{ __('At a glance') }}</span>
                            <span class="eyebrow text-chapter-produce">{{ __('Abo') }} № {{ $subNumber }}</span>
                        </div>

                        <dl class="mt-10 grid grid-cols-2 gap-x-8 gap-y-8">
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Cadence') }}</dt>
                                <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $planLabel }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Fulfillment') }}</dt>
                                <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $fulfillmentLabel }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Box size') }}</dt>
                                <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $boxSizeLabel }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Next charge') }}</dt>
                                <dd class="mt-2 font-display text-2xl text-brand-ink">
                                    {{ $subscription->next_charge_date?->translatedFormat('d. M') ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-10 flex items-baseline justify-between border-t border-brand-ink/10 pt-6">
                            <span class="eyebrow text-chapter-produce">{{ __('Per delivery') }}</span>
                            <span class="font-display text-4xl text-brand-ink tabular-nums">
                                EUR {{ number_format($subscription->expectedChargeAmount(), 2, ',', '.') }}
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 2 · MANIFESTO                                                   --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 bg-brand-paper py-20 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="mx-auto max-w-3xl text-center">
                    <blockquote>
                        <p class="font-display text-3xl italic leading-snug text-balance text-brand-ink md:text-4xl">
                            <span class="align-[-0.15em] font-display text-6xl leading-none text-chapter-produce" aria-hidden="true">"</span>{{ __('customer.story.subscription.rhythm') }}"
                        </p>
                        <footer class="mt-8 flex items-center justify-center gap-4">
                            <span class="h-px w-10 bg-chapter-produce"></span>
                            <cite class="eyebrow not-italic text-chapter-produce">{{ __('customer.story.dashboard.founder_signature') }}</cite>
                            <span class="h-px w-10 bg-chapter-produce"></span>
                        </footer>
                    </blockquote>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 3 · PLAN & BOX                                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <section id="sub-plan" class="scroll-mt-20 border-b border-brand-ink/10 py-20 lg:py-24" aria-labelledby="sub-section-plan">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                {{-- Header row --}}
                <div class="mb-14 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Tariff') }}</p>
                        <h2 id="sub-section-plan" class="mt-2 font-display text-4xl text-balance text-brand-ink md:text-5xl">{{ __('Plan & box.') }}</h2>
                    </div>
                    <div class="flex items-center gap-2 border border-chapter-produce px-3 py-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-chapter-produce"></span>
                        <span class="eyebrow text-chapter-produce">{{ $statusLabel }}</span>
                    </div>
                </div>

                {{-- 4-column metric grid --}}
                <div class="grid gap-x-12 gap-y-10 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Cadence') }}</p>
                        <p class="mt-3 font-display text-3xl text-brand-ink">{{ $planLabel }}</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/60">
                            {{ $subscription->plan === SubscriptionPlan::Weekly ? __('Every week, year-round') : __('Every other week') }}
                        </p>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Fulfillment') }}</p>
                        <p class="mt-3 font-display text-3xl text-brand-ink">{{ $fulfillmentLabel }}</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/60">
                            {{ ($subscription->fulfillment_mode ?? FulfillmentMode::Delivery) === FulfillmentMode::Pickup ? __('You collect from the farm') : __('Direct to your door') }}
                        </p>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Box size') }}</p>
                        <p class="mt-3 font-display text-3xl text-brand-ink">{{ $boxSizeLabel }}</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/60">
                            @if ($boxGrowthAsset)
                                <img src="{{ asset($boxGrowthAsset) }}" alt="" class="h-8 w-8 object-contain opacity-80" loading="lazy">
                            @else
                                {{ __('Selected box size') }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Total per delivery') }}</p>
                        <p class="mt-3 font-display text-3xl text-brand-ink tabular-nums">EUR {{ number_format($subscription->expectedChargeAmount(), 2, ',', '.') }}</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/60">{{ __('Charged on delivery week') }}</p>
                    </div>
                </div>

            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 4 · PREFERENCES                                                 --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <section id="sub-diet" class="scroll-mt-20 border-b border-brand-ink/10 py-20 lg:py-24" aria-labelledby="sub-section-diet">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">

                    {{-- Left: intro --}}
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Preferences') }}</p>
                        <h2 id="sub-section-diet" class="mt-2 font-display text-4xl text-balance text-brand-ink md:text-5xl">{{ __('Diet & allergies.') }}</h2>
                        <p class="mt-8 max-w-sm text-base leading-relaxed text-brand-ink/60">
                            {{ __('Tell us about dietary restrictions so we can tailor your box. Changes take effect from the next open delivery cycle.') }}
                        </p>
                        <a href="{{ route('app.subscriptions.edit') }}"
                           class="mt-6 inline-block border-b border-chapter-produce pb-1 text-sm text-chapter-produce hover:text-chapter-produce/80">
                            {{ __('Edit preferences') }} →
                        </a>
                    </div>

                    {{-- Right: dl --}}
                    <dl class="grid gap-x-12 gap-y-10 self-start sm:grid-cols-2">
                        <div>
                            <dt class="eyebrow text-brand-ink/55">{{ __('Eggs add-on') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">{{ $eggsDairyAddOn ? __('Yes') : __('No') }}</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                        <div>
                            <dt class="eyebrow text-brand-ink/55">{{ __('Meat add-on') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">{{ $meatAddOn ? __('Yes') : __('No') }}</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="eyebrow text-brand-ink/55">{{ __('Allergies') }}</dt>
                            <dd class="mt-3 text-brand-ink">
                                @if ($allergenList !== [])
                                    <ul class="flex flex-wrap gap-1.5">
                                        @foreach ($allergenList as $allergenValue)
                                            @php
                                                $case = \App\Enums\Allergen::tryFrom((string) $allergenValue);
                                                $allergenKey = 'allergens.' . ($case?->value ?? '');
                                            @endphp
                                            @if ($case !== null)
                                                <li class="inline-flex items-center border border-brand-ink/20 bg-brand-ink/[0.04] px-2 py-0.5 font-sans text-xs">
                                                    {{ __($allergenKey) }}
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                    @if (! empty($subscription->allergies_note))
                                        <p class="mt-2 text-sm italic text-brand-ink/60">{{ $subscription->allergies_note }}</p>
                                    @endif
                                @else
                                    <span class="font-display text-2xl">{{ __('None listed') }}</span>
                                @endif
                            </dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 5 · PAUSE WINDOW (quiet weeks)                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if (! $isCancelled && ! empty($skipPickerWeeks))
            <section id="sub-skip" class="scroll-mt-20 border-b border-brand-ink/10 bg-brand-paper py-20 lg:py-24" aria-labelledby="sub-section-pause">
                @once
                <script>
                    window.__skipPickerData = {
                        skipped: @json(array_values($userSkippedWeeks)),
                        weeks:   @json($weekCells)
                    };
                </script>
                @endonce
                <div class="mx-auto max-w-[1400px] px-6 lg:px-12"
                     x-data="skipPicker()"
                     x-init="init(window.__skipPickerData)"
                >

                    {{-- Header --}}
                    <div class="mb-12 grid gap-10 lg:grid-cols-[1fr_1.6fr]">
                        <div>
                            <p class="eyebrow text-chapter-produce">{{ __('Quiet weeks') }}</p>
                            <h2 id="sub-section-pause" class="mt-2 font-display text-4xl text-balance text-brand-ink md:text-5xl">{{ __('A pause, when you need one.') }}</h2>
                        </div>
                        <p class="self-end text-base leading-relaxed text-brand-ink/60">
                            {{ __('Tick any weeks in the next twelve you want to skip. Uncheck to bring a week back. No delivery, no charge.') }}
                        </p>
                    </div>

                    {{-- Current pause window note --}}
                    @if ($subscription->hasPauseWindow())
                        <p class="mb-6 text-sm text-brand-ink/70">
                            {{ __('subscriptions.skip_window_text', ['from' => $subscription->pause_from?->format('d.m.Y'), 'until' => $subscription->pause_until?->format('d.m.Y')]) }}
                        </p>
                    @endif

                    {{-- Calendar grid --}}
                    <form method="POST" action="{{ route('app.subscriptions.skip.weeks') }}">
                        @csrf
                        <ul class="grid grid-cols-2 gap-px border border-brand-ink/15 bg-brand-ink/15 sm:grid-cols-3 lg:grid-cols-4"
                            role="group" aria-label="{{ __('Pick weeks to skip') }}">
                            @foreach ($weekCells as $cell)
                                <li class="bg-brand-cream">
                                    <button
                                        type="button"
                                        class="w-full bg-brand-cream px-5 py-5 text-left transition hover:bg-brand-paper"
                                        :class="{ 'bg-brand-paper': isSkipped('{{ $cell['iso'] }}') }"
                                        @if (! $cell['locked'])
                                            @click="toggle('{{ $cell['iso'] }}')"
                                        @else
                                            disabled aria-disabled="true"
                                        @endif
                                        :aria-pressed="isSkipped('{{ $cell['iso'] }}')"
                                    >
                                        <span class="flex items-center justify-between gap-4">
                                            <span>
                                                <span
                                                    class="block font-display text-xl text-brand-ink"
                                                    :class="{ 'line-through text-brand-ink/50': isSkipped('{{ $cell['iso'] }}') }"
                                                    @if ($cell['locked']) style="text-decoration: line-through; opacity: 0.5;" @endif
                                                >{{ $cell['label'] }}</span>
                                                <span class="mt-1 block eyebrow text-chapter-produce">
                                                    @if ($cell['locked']) {{ __('Locked by pause') }} @else {{ $cell['sub'] }} @endif
                                                </span>
                                            </span>
                                            <span
                                                class="grid h-6 w-6 shrink-0 place-items-center border transition"
                                                :class="isSkipped('{{ $cell['iso'] }}') ? 'border-chapter-produce bg-chapter-produce text-brand-cream' : 'border-brand-ink/25 bg-brand-cream'"
                                                @if ($cell['locked']) style="border-color: rgba(44,36,28,0.25); background: rgba(44,36,28,0.04);" @endif
                                            >
                                                <template x-if="isSkipped('{{ $cell['iso'] }}')"><span class="text-xs leading-none">✕</span></template>
                                                @if ($cell['locked'])<span class="text-xs leading-none text-brand-ink/40">✕</span>@endif
                                            </span>
                                        </span>
                                    </button>
                                    {{-- Hidden checkbox drives the actual form submission --}}
                                    @if (! $cell['locked'])
                                        <input type="checkbox" name="weeks[]" value="{{ $cell['iso'] }}" class="sr-only"
                                               :checked="isSkipped('{{ $cell['iso'] }}')" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <x-input-error class="mt-2" :messages="$errors->get('weeks')" />
                        <x-input-error class="mt-2" :messages="$errors->get('weeks.*')" />

                        {{-- Footer: save + live status --}}
                        <div class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-4">
                            <button type="submit"
                                    class="rounded-sm bg-chapter-produce px-7 py-3 text-sm text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Save quiet weeks') }}
                            </button>
                            <p class="eyebrow text-brand-ink/55">
                                <span x-show="countPaused() === 0">{{ __('No weeks paused') }}</span>
                                <span x-show="countPaused() > 0"><span x-text="countPaused()"></span> {{ __('week(s) paused') }}</span>
                            </p>
                        </div>
                    </form>

                </div>
            </section>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 6 · PAYMENTS & RECEIPTS                                         --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <section id="sub-billing" class="scroll-mt-20 py-20 lg:py-24" aria-labelledby="sub-section-billing">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.2fr] lg:gap-16">

                    {{-- Left: intro --}}
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Billing') }}</p>
                        <h2 id="sub-section-billing" class="mt-2 font-display text-4xl text-balance text-brand-ink md:text-5xl">{{ __('Payments & receipts.') }}</h2>
                        <p class="mt-8 max-w-sm text-base leading-relaxed text-brand-ink/60">
                            {{ __('Manage your payment method and review past charges for your subscription deliveries.') }}
                        </p>
                    </div>

                    {{-- Right: billing dl + actions --}}
                    <div class="self-start">
                        <dl class="grid gap-x-12 gap-y-8 sm:grid-cols-2">
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Next charge date') }}</dt>
                                <dd class="mt-3 font-display text-2xl text-brand-ink">{{ $billingDateDisplay }}</dd>
                                <div class="mt-3 h-px bg-brand-ink/10"></div>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Amount') }}</dt>
                                <dd class="mt-3 font-display text-2xl text-brand-ink tabular-nums">EUR {{ number_format($subscription->expectedChargeAmount(), 2, ',', '.') }}</dd>
                                <div class="mt-3 h-px bg-brand-ink/10"></div>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Payment method') }}</dt>
                                <dd class="mt-3 font-display text-2xl text-brand-ink">
                                    @if (! empty($subscription->sumup_token))
                                        {{ __('Card on file') }}
                                    @else
                                        <span class="text-chapter-meat">{{ __('Not set up') }}</span>
                                    @endif
                                </dd>
                                <div class="mt-3 h-px bg-brand-ink/10"></div>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Status') }}</dt>
                                <dd class="mt-3">
                                    <x-status-badge :value="$subscription->status" type="subscription" />
                                </dd>
                                <div class="mt-3 h-px bg-brand-ink/10"></div>
                            </div>
                        </dl>

                        <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                            @if (! $isCancelled && empty($subscription->sumup_token))
                                <a href="{{ route('app.subscriptions.onboard.payment') }}"
                                   class="rounded-sm bg-chapter-produce px-7 py-3 text-brand-cream transition hover:bg-chapter-produce/90">
                                    {{ __('Set up payment method') }}
                                </a>
                            @endif
                            @if (! $isCancelled && ! empty($subscription->sumup_token))
                                <a href="{{ route('app.payments.update') }}"
                                   class="rounded-sm border border-chapter-produce px-6 py-3 text-chapter-produce transition hover:bg-chapter-produce hover:text-brand-cream">
                                    {{ __('Update payment method') }}
                                </a>
                            @endif
                            <a href="{{ route('app.payments.history') }}"
                               class="self-center border-b border-brand-ink/30 pb-0.5 text-brand-ink/70 hover:text-brand-ink">
                                {{ __('View all invoices') }} →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 7 · EDITORIAL FOOTER BAND                                       --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <x-customer.editorial-footer-band />

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- MODALS                                                           --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <x-modal
            name="confirm-subscription-pause"
            :show="$errors->has('pause_from') || $errors->has('pause_until')"
            focusable
        >
            <form method="post" action="{{ route('app.subscriptions.pause') }}" class="bg-brand-cream p-6">
                @csrf
                <p class="eyebrow text-chapter-produce">{{ __('Skip') }}</p>
                <h2 class="mt-2 font-display text-2xl text-brand-ink">{{ __('subscriptions.skip_range_modal_title') }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">{{ __('subscriptions.skip_range_modal_intro') }}</p>
                <div class="mt-5 space-y-5">
                    <div>
                        <x-input-label for="pause_from" :value="__('subscriptions.skip_from_label')" />
                        <x-text-input id="pause_from" name="pause_from" type="date" class="mt-2 block w-full"
                            :value="old('pause_from', $subscription->pause_from?->format('Y-m-d'))" required />
                        <x-input-error class="mt-2" :messages="$errors->get('pause_from')" />
                    </div>
                    <div>
                        <x-input-label for="pause_until" :value="__('subscriptions.skip_until_label')" />
                        <x-text-input id="pause_until" name="pause_until" type="date" class="mt-2 block w-full"
                            :value="old('pause_until', $subscription->pause_until?->format('Y-m-d'))" required />
                        <x-input-error class="mt-2" :messages="$errors->get('pause_until')" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Close') }}</x-secondary-button>
                    <x-primary-button>{{ __('subscriptions.skip_range_save') }}</x-primary-button>
                </div>
            </form>
        </x-modal>

        <x-modal name="confirm-subscription-resume" focusable>
            <form method="post" action="{{ route('app.subscriptions.resume') }}" class="bg-brand-cream p-6">
                @csrf
                <p class="eyebrow text-chapter-produce">{{ __('Resume') }}</p>
                <h2 class="mt-2 font-display text-2xl text-brand-ink">{{ __('subscriptions.end_skip_modal_title') }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">{{ __('subscriptions.end_skip_modal_intro') }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Not now') }}</x-secondary-button>
                    <x-primary-button>{{ __('subscriptions.end_skip_confirm') }}</x-primary-button>
                </div>
            </form>
        </x-modal>

        <x-modal
            name="confirm-subscription-cancel"
            :show="$errors->has('cancellation_reason')"
            focusable
        >
            <form method="post" action="{{ route('app.subscriptions.cancel') }}" class="bg-brand-cream p-6">
                @csrf
                <h2 class="font-display text-2xl text-brand-ink">{{ __('Cancel subscription') }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                    {{ __('This ends your subscription at the end of the current billing cycle — pending deliveries within that cycle still ship; we will not charge you for future cycles.') }}
                </p>
                <ul class="mt-3 list-inside list-disc text-sm text-brand-ink/75">
                    <li>{{ __('Your plan and payment method will no longer be used for new charges.') }}</li>
                    <li>{{ __('You can subscribe again later with a new signup if we offer it.') }}</li>
                </ul>
                <div class="mt-5">
                    <x-input-label for="cancellation_reason" :value="__('Reason')" optional />
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3"
                        class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream px-4 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0"
                        placeholder="{{ __('Optional feedback') }}">{{ old('cancellation_reason') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('cancellation_reason')" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" data-default-focus x-on:click="$dispatch('close')">{{ __('Keep subscription') }}</x-secondary-button>
                    <x-danger-button>{{ __('Confirm cancellation') }}</x-danger-button>
                </div>
            </form>
        </x-modal>

    @endif

</x-app-layout>

@push('scripts')
<script>
    function skipPicker() {
        return {
            skipped: new Set(),
            weeks:   [],
            init(data) {
                this.skipped = new Set(data.skipped);
                this.weeks   = data.weeks;
            },
            toggle(iso) {
                const s = new Set(this.skipped);
                s.has(iso) ? s.delete(iso) : s.add(iso);
                this.skipped = s;
            },
            isSkipped(iso) { return this.skipped.has(iso); },
            countPaused()  { return this.skipped.size; }
        };
    }
</script>
@endpush
