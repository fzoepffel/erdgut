@props([
    // The logged-in customer. Drives the italicised first name on the greeting line.
    'user',

    // Primary subscription. Nullable — when null, the steady-state hero shouldn't
    // render at all (caller filters by DashboardKind first). When the subscription
    // is Paused / inside a pause window, the headline and lead switch to the
    // paused variant and the "End break" action surfaces.
    'subscription' => null,

    // Currently-composed Box for this week, if any. When present, drives the
    // BOX № corner label, the n_items/n_farms lead, and the "View this week's
    // box" CTA. Absent → planned-state copy and the right column shows the
    // editorial illustration with size+people only.
    'currentBox' => null,
])

@php
    use App\Enums\SubscriptionStatus;
    use App\Support\EditorialContent;

    // ─── State derivation ─────────────────────────────────────────────────
    $isCancelled = $subscription?->status === SubscriptionStatus::Cancelled;
    $isPaused = $subscription !== null && (
        $subscription->status === SubscriptionStatus::Paused
        || $subscription->isWithinPauseWindow()
    );
    $isComposed = $currentBox !== null;

    // ─── Greeting ─────────────────────────────────────────────────────────
    // Time-of-day-aware greeting (morning 5-10 / day 10-18 / evening 18-5).
    // Rendered as: "Guten Morgen," / italic name / delivery line — three
    // visual lines so the name reads as a personal beat (matches the
    // prototype's typographic emphasis).
    $hour = now()->hour;
    $greetingKey = match (true) {
        $hour >= 5 && $hour < 10 => 'customer.dashboard.hero.greeting_morning',
        $hour >= 10 && $hour < 18 => 'customer.dashboard.hero.greeting_day',
        default => 'customer.dashboard.hero.greeting_evening',
    };
    $greeting = __($greetingKey);
    $firstName = trim(explode(' ', (string) ($user->name ?? ''))[0] ?? '');

    // ─── Delivery line + weekday ──────────────────────────────────────────
    // Prefer the box's canonical ship day, then the subscription's projected
    // first delivery, then the config zone's first allowed weekday. The
    // config fallback exists for the rare case where a subscription is Active
    // without next_charge_date set yet.
    $deliveryDate = null;
    if ($isComposed && $currentBox->week_start_date instanceof \Carbon\CarbonInterface) {
        $deliveryDate = $currentBox->week_start_date->copy()->addDays(2); // Wed
    } elseif ($subscription) {
        $deliveryDate = $subscription->firstExpectedDeliveryDate();
    }
    if ($deliveryDate === null) {
        $zone = config('delivery.zones.' . config('delivery.default_zone'));
        $allowed = $zone['allowed_weekdays'] ?? [3];
        $iso = $allowed[0] ?? 3;
        $deliveryDate = now()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays($iso - 1);
        if ($deliveryDate->isPast()) {
            $deliveryDate->addWeek();
        }
    }
    $deliveryWeekday = $deliveryDate->locale(app()->getLocale())->translatedFormat('l');
    // Day BEFORE delivery — used by the "Foto folgt am DI" caption to signal
    // when this week's box photo replaces the placeholder illustration.
    $photoDayShort = $deliveryDate->copy()->subDay()->locale(app()->getLocale())->translatedFormat('D');

    if ($isPaused) {
        $deliveryLine = __('customer.dashboard.hero.delivery_line.paused', [
            'date' => $subscription->pause_until?->locale(app()->getLocale())->translatedFormat('d.m.Y') ?? '',
        ]);
    } elseif ($isComposed) {
        $deliveryLine = __('customer.dashboard.hero.delivery_line.composed', [
            'weekday' => $deliveryWeekday,
        ]);
    } else {
        $deliveryLine = __('customer.dashboard.hero.delivery_line.planned');
    }

    // ─── Lead paragraph ───────────────────────────────────────────────────
    if ($isPaused) {
        $lead = __('customer.dashboard.hero.lead.paused');
    } elseif ($isComposed) {
        $itemCount = $currentBox->items->count();
        $farmCount = $currentBox->items
            ->pluck('product.producer_id')
            ->filter()
            ->unique()
            ->count();
        $lead = __('customer.dashboard.hero.lead.composed', [
            'n_items' => $itemCount,
            'n_farms' => max(1, $farmCount),
        ]);
    } else {
        $lead = __('customer.dashboard.hero.lead.uncomposed');
    }

    // ─── Right-column meta ────────────────────────────────────────────────
    $boxLabel = $isComposed
        ? __('customer.dashboard.hero.meta.box_label') . ' ' . str_pad((string) $currentBox->id, 4, '0', STR_PAD_LEFT)
        : null;

    $sizeLabel = $subscription?->box_size ? __(ucfirst($subscription->box_size)) : null;
    $peopleCount = match ($subscription?->box_size) {
        'small' => 1,
        'medium' => 2,
        'large' => 4,
        default => null,
    };
    $peopleLabel = $peopleCount === 1
        ? __('customer.dashboard.hero.meta.people_singular')
        : ($peopleCount !== null
            ? __('customer.dashboard.hero.meta.people_plural', ['count' => $peopleCount])
            : null);

    // Chapter eyebrow ties the dashboard to the editorial chapter vocabulary —
    // the ISO week stands in for a "chapter number" of the year.
    $chapterEyebrow = __('customer.dashboard.hero.meta.chapter_label', [
        'week' => now()->isoWeek(),
    ]);

    // ─── Action visibility ────────────────────────────────────────────────
    // "View current box" only when a real box exists; pause/resume modals
    // require an editable (non-cancelled) subscription; "End break" only
    // when actually in or about to enter a pause.
    $canViewBox = $isComposed;
    $canModifyPause = $subscription !== null && ! $isCancelled;
    $canEndBreak = $canModifyPause && ($isPaused || $subscription->hasPauseWindow());

    $illustrationUrl = asset('images/box-growth/box-contents-illustration.png');

    // Tiny date stamp pinned to the top-right of the hero on lg+ — the
    // printed-almanac voice from the design spec. Month abbreviation +
    // year + ISO week, separated by middle dots. Locale-aware so DE shows
    // "MAI" while EN shows "MAY".
    $monthAbbrev = now()->locale(app()->getLocale())->translatedFormat('M');
    $heroDateStamp = mb_strtoupper($monthAbbrev).' · '.now()->format('Y').' · KW '.now()->isoWeek();
@endphp

<section
    {{ $attributes->class(['relative']) }}
    data-testid="customer-dashboard-hero"
    aria-labelledby="dashboard-hero-headline"
>
    {{-- Date stamp pinned top-right of the hero on lg+. Hidden on mobile
         where it would collide with the illustration panel's BOX № label. --}}
    <p class="absolute right-0 top-0 hidden text-[0.65rem] eyebrow text-chapter-produce lg:block" data-testid="dashboard-hero-date-stamp">
        {{ $heroDateStamp }}
    </p>

    <div class="grid grid-cols-1 gap-10 lg:grid-cols-2 lg:gap-16">
        {{-- ─── Left column: greeting + lead + actions ─────────────────── --}}
        {{-- On <lg the greeting reads first (natural document order); the
             illustration follows. On lg+ the two columns sit side-by-side. --}}
        <div>
            <p class="eyebrow text-chapter-produce">{{ $chapterEyebrow }}</p>

            {{-- Spec H1: text-5xl → md:text-7xl → lg:text-[5.5rem] (~88px),
                 leading 1.02, never bold. Instrument Serif at weight 400 + italic
                 emphasis on the name. text-balance keeps line breaks editorial. --}}
            <h1 id="dashboard-hero-headline" class="mt-6 font-display font-normal text-balance leading-[1.02] text-brand-ink text-5xl md:text-7xl lg:text-[5.5rem]">
                <span class="block">{{ $greeting }},</span>
                @if ($firstName !== '')
                    <em class="block font-display italic text-chapter-produce">{{ $firstName }}.</em>
                @endif
                <span class="block">{{ $deliveryLine }}</span>
            </h1>

            <p class="mt-8 max-w-md text-base leading-relaxed text-brand-ink/65">
                {{ $lead }}
            </p>

            <div class="mt-12 flex flex-wrap items-center gap-x-6 gap-y-4">
                @if ($canViewBox)
                    <a
                        href="{{ route('app.boxes.current') }}"
                        class="btn-solid"
                        data-testid="dashboard-hero-action-view-box"
                        data-track-section="dashboard_hero.view_box"
                        data-track-destination="app.boxes.current"
                    >
                        {{ __('customer.dashboard.hero.action.view_box') }}
                    </a>
                @endif

                @if ($canModifyPause && ! $isPaused)
                    <form method="POST" action="{{ route('app.subscriptions.skip.next') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="text-sm font-semibold text-brand-ink underline underline-offset-4 hover:text-chapter-produce focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                            data-testid="dashboard-hero-action-skip-next"
                            data-track-section="dashboard_hero.skip_next"
                            data-track-destination="app.subscriptions.skip.next"
                        >
                            {{ __('subscriptions.skip_next_week_cta') }}
                        </button>
                    </form>
                @endif

                @if ($canModifyPause)
                    <button
                        type="button"
                        class="text-sm font-semibold text-brand-ink underline underline-offset-4 hover:text-chapter-produce focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-pause')"
                        data-testid="dashboard-hero-action-skip-range"
                        data-track-section="dashboard_hero.skip_range"
                    >
                        {{ __('subscriptions.skip_range_cta') }}
                    </button>
                @endif

                @if ($canEndBreak)
                    <button
                        type="button"
                        class="text-sm font-semibold text-brand-ink underline underline-offset-4 hover:text-chapter-produce focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-resume')"
                        data-testid="dashboard-hero-action-end-break"
                        data-track-section="dashboard_hero.end_break"
                    >
                        {{ __('subscriptions.end_skip_cta') }}
                    </button>
                @endif

                @if ($subscription)
                    <a
                        href="{{ route('app.subscriptions.show') }}"
                        class="text-sm font-semibold text-brand-ink underline underline-offset-4 hover:text-chapter-produce focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                        data-testid="dashboard-hero-action-manage-subscription"
                        data-track-section="dashboard_hero.manage_subscription"
                        data-track-destination="app.subscriptions.show"
                    >
                        {{ __('customer.dashboard.hero.action.manage_subscription') }}
                    </a>
                @endif
            </div>
        </div>

        {{-- ─── Right column: meta-framed illustration ──────────────────── --}}
        <div>
            {{-- aspect-[4/3] on small screens keeps the illustration block
                 from eating an entire mobile viewport; lg+ goes back to the
                 prototype's portrait 4:5 framing. --}}
            <div class="relative aspect-[4/3] w-full overflow-hidden bg-brand-paper lg:aspect-[4/5]">
                {{-- Top corners — primary green per spec, mono via .eyebrow. --}}
                <div class="absolute inset-x-4 top-4 flex items-start justify-between gap-4 sm:inset-x-6 sm:top-6">
                    @if ($boxLabel)
                        <p class="eyebrow text-[0.65rem] text-chapter-produce">{{ $boxLabel }}</p>
                    @else
                        <span aria-hidden="true"></span>
                    @endif
                    @if ($sizeLabel && $peopleLabel)
                        <p class="eyebrow text-right text-[0.65rem] text-chapter-produce">{{ $sizeLabel }} · {{ $peopleLabel }}</p>
                    @endif
                </div>

                {{-- Centerpiece illustration --}}
                <img
                    src="{{ $illustrationUrl }}"
                    alt=""
                    role="presentation"
                    class="absolute inset-x-0 top-1/2 -translate-y-1/2 mx-auto h-[75%] w-auto max-w-[80%] object-contain"
                    loading="eager"
                    decoding="async"
                />

                {{-- Bottom corners — same green at 70% opacity per spec. --}}
                <div class="absolute inset-x-4 bottom-4 flex items-end justify-between gap-4 sm:inset-x-6 sm:bottom-6">
                    <p class="eyebrow text-[0.65rem] text-chapter-produce/70">{{ __('customer.dashboard.hero.meta.harvest_caption') }}</p>
                    @unless ($isPaused)
                        <p class="eyebrow text-right text-[0.65rem] text-chapter-produce/70">{{ __('customer.dashboard.hero.meta.photo_pending', ['weekday' => $photoDayShort]) }}</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Action modals (hosted on the dashboard so the action row works) ───
         These are visual copies of the modals on subscriptions/show.blade.php.
         Both surfaces dispatching the same modal names is intentional —
         Alpine's open-modal event is page-scoped, so each page that wants
         to trigger a modal must host one. --}}
    @if ($canModifyPause)
        <x-modal
            name="confirm-subscription-pause"
            :show="$errors->has('pause_from') || $errors->has('pause_until')"
            focusable
        >
            <form method="post" action="{{ route('app.subscriptions.pause') }}" class="bg-brand-cream p-6">
                @csrf
                <p class="eyebrow text-chapter-produce">{{ __('Skip') }}</p>
                <h2 class="mt-2 font-display text-2xl text-brand-ink">
                    {{ __('subscriptions.skip_range_modal_title') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                    {{ __('subscriptions.skip_range_modal_intro') }}
                </p>
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
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Close') }}
                    </x-secondary-button>
                    <x-primary-button>
                        {{ __('subscriptions.skip_range_save') }}
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($canEndBreak)
        <x-modal name="confirm-subscription-resume" focusable>
            <form method="post" action="{{ route('app.subscriptions.resume') }}" class="bg-brand-cream p-6">
                @csrf
                <p class="eyebrow text-chapter-produce">{{ __('Resume') }}</p>
                <h2 class="mt-2 font-display text-2xl text-brand-ink">
                    {{ __('subscriptions.end_skip_modal_title') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                    {{ __('subscriptions.end_skip_modal_intro') }}
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Not now') }}
                    </x-secondary-button>
                    <x-primary-button>
                        {{ __('subscriptions.end_skip_confirm') }}
                    </x-primary-button>
                </div>
            </form>
        </x-modal>
    @endif
</section>
