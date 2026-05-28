@php
    use App\Enums\Allergen;
    use App\Support\Dashboard\DashboardKind;

    $firstDeliveryCard = $deliveryCards->first();
    $weeklyIntro = \App\Support\EditorialContent::weeklyIntro();
    $founderNote = \App\Support\EditorialContent::founderNote();

    // ERD-353: producer pick — prefer a producer whose product appears in
    // this week's composed box (anchors the quote to a real item), otherwise
    // fall back to the ISO-week rotation. Mirrors the rule already in use
    // on resources/views/boxes/show.blade.php so the dashboard and the box
    // detail page surface the same producer for the same week.
    $producerOfWeekSlug = $currentBox?->items
        ?->pluck('product.producer.name')
        ?->filter()
        ?->map(fn ($name) => strtolower(explode(' ', (string) $name)[0] ?? ''))
        ?->first(fn ($slug) => in_array($slug, \App\Support\EditorialContent::PRODUCER_SLUGS, true));
    $producerOfWeek = is_string($producerOfWeekSlug) && $producerOfWeekSlug !== ''
        ? \App\Support\EditorialContent::producer($producerOfWeekSlug)
        : \App\Support\EditorialContent::producerOfTheWeek();

    // ERD-354: recipe pick + from-box overlap. Recipe rotates by ISO week
    // (editorial-only for v1; admin curation lands in ERD-358). Overlap
    // counts how many of the recipe's ingredient slugs appear as substrings
    // of this week's composed-box product names (lowercased), so the third
    // metric "Aus der Box X / Y" reflects how much of the recipe you can
    // actually cook from your current delivery. Hidden when no box is
    // composed yet.
    $recipeOfWeek = \App\Support\EditorialContent::recipeOfTheWeek();
    $recipeMatchedCount = null;
    $recipeTotalCount = null;
    if ($currentBox && $currentBox->items->isNotEmpty() && ! empty($recipeOfWeek['ingredient_slugs'])) {
        $boxNames = $currentBox->items
            ->pluck('product.name')
            ->filter()
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->all();
        $recipeTotalCount = count($recipeOfWeek['ingredient_slugs']);
        $recipeMatchedCount = collect($recipeOfWeek['ingredient_slugs'])
            ->filter(function (string $needle) use ($boxNames) {
                $needle = mb_strtolower($needle);
                foreach ($boxNames as $name) {
                    if ($needle !== '' && mb_strpos($name, $needle) !== false) {
                        return true;
                    }
                }
                return false;
            })
            ->count();
    }

    $kind = $dashboardKind ?? DashboardKind::NewVisitor;

    // ERD-351: the new editorial dashboard-hero takes over for the steady-state
    // subscriber path (active with or without composed box, plus paused).
    // Other states (new visitor / trial / resume-setup / cancelled) keep the
    // older marketing-style hero-band until later phases re-skin them.
    $usesNewHero = in_array($kind, [
        DashboardKind::SubscriptionActive,
        DashboardKind::SubscriptionFirstWeek,
        DashboardKind::SubscriptionPaused,
    ], true);

    // ERD-352: manifest section state. 'composed' renders this week's actual
    // box items with numbering + quantities; 'uncomposed' shows 6 portfolio
    // products stably picked per ISO week. Hidden entirely for paused or
    // out-of-scope kinds.
    $manifestState = null;
    $manifestItems = collect();
    if ($usesNewHero && $kind !== DashboardKind::SubscriptionPaused) {
        // Allergen value → translated label lookup, shared by both render paths.
        $allergenLabels = collect(Allergen::cases())
            ->mapWithKeys(fn ($case) => [$case->value => __('allergens.'.$case->value)])
            ->all();

        $normalizeAllergens = fn (?array $values) => collect($values ?? [])
            ->filter(fn ($v) => isset($allergenLabels[$v]))
            ->map(fn ($v) => $allergenLabels[$v])
            ->values()
            ->all();

        if ($currentBox && $currentBox->items->isNotEmpty()) {
            $manifestState = 'composed';
            $manifestItems = $currentBox->items->values()->map(function ($item, $i) use ($normalizeAllergens) {
                $product = $item->product;
                $qty = (float) $item->quantity;
                // Use the BoxItem's own quantity + unit so the manifest mirrors
                // what was saved at composition time, independent of
                // product-canonical-unit conversions.
                $quantityLabel = trim(number_format($qty, 2).' '.($item->unit ?? ''));
                return [
                    'number' => $i + 1,
                    'name' => $product?->name ?? __('Unknown product'),
                    'quantityLabel' => $quantityLabel,
                    'producerName' => $product?->producer?->name,
                    'producerRegion' => $product?->producer?->region,
                    'imageUrl' => $product?->images->first()?->url() ?? $product?->imageUrl(),
                    'description' => $product?->localizedTasteProfile(),
                    'allergens' => $normalizeAllergens($product?->allergens),
                    'isExtra' => (bool) $item->is_extra,
                ];
            });
        } else {
            $manifestState = 'uncomposed';
            // Stable per ISO week: walk the catalog ordered by id and offset
            // by (week × 6) so the set rotates but stays identical for the
            // whole week. Wraps around at the end of the catalog.
            $portfolioBase = \App\Models\Product::query()
                ->visibleInCatalog()
                ->where('is_egg_addon', false)
                ->orderBy('id');
            $portfolioCount = (clone $portfolioBase)->count();
            if ($portfolioCount > 0) {
                $offset = $portfolioCount > 0 ? (((int) now()->isoWeek() - 1) * 6) % $portfolioCount : 0;
                $portfolio = (clone $portfolioBase)
                    ->with(['producer.translations', 'images', 'translations'])
                    ->offset($offset)
                    ->limit(6)
                    ->get();
                if ($portfolio->count() < 6) {
                    $wrap = (clone $portfolioBase)
                        ->with(['producer.translations', 'images', 'translations'])
                        ->limit(6 - $portfolio->count())
                        ->get();
                    $portfolio = $portfolio->concat($wrap);
                }
                $manifestItems = $portfolio->take(6)->values()->map(fn ($product) => [
                    'number' => null,
                    'name' => $product->name,
                    'quantityLabel' => null,
                    'producerName' => $product->producer?->name,
                    'producerRegion' => $product->producer?->region,
                    'imageUrl' => $product->images->first()?->url() ?? $product->imageUrl(),
                    'description' => $product->localizedTasteProfile(),
                    'allergens' => [],
                    'isExtra' => false,
                ]);
            }
        }
    }

    // ERD-355: 4-row almanac payload. Walks the next 4 weeks starting from
    // this ISO week's Monday, derives status (UNTERWEGS / EINGEPLANT /
    // ÜBERSPRUNGEN) and a label (Box №id / seasonal tagline / In deiner
    // Auszeit / Zurück ab KW :n). Skip-week lookup is preloaded as a Set
    // so each row is a constant-time check.
    $almanacRows = collect();
    if ($usesNewHero && $primarySubscription) {
        $today = now()->startOfDay();
        $firstWeekStart = $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $horizonEnd = $firstWeekStart->copy()->addWeeks(4);

        $almanacBoxes = \App\Models\Box::query()
            ->where('user_id', $primarySubscription->user_id)
            ->whereNotNull('subscription_id')
            ->whereBetween('week_start_date', [$firstWeekStart->toDateString(), $horizonEnd->toDateString()])
            ->get()
            ->keyBy(fn ($b) => $b->week_start_date->toDateString());

        $skippedSet = $primarySubscription->skipWeeks()
            ->whereBetween('week_start_date', [$firstWeekStart->toDateString(), $horizonEnd->toDateString()])
            ->pluck('week_start_date')
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())
            ->flip();

        for ($i = 0; $i < 4; $i++) {
            $weekStart = $firstWeekStart->copy()->addWeeks($i);
            $weekKey = $weekStart->toDateString();
            $deliveryDate = $weekStart->copy()->addDays(2);
            $isoWeek = (int) $weekStart->isoWeek();
            $box = $almanacBoxes->get($weekKey);
            $isSkipped = $skippedSet->has($weekKey)
                || $primarySubscription->isWithinPauseWindow($weekStart);

            if ($isSkipped) {
                // Use "Zurück ab KW :n" when the very next week IS available,
                // otherwise just say "In deiner Auszeit" (no concrete return date).
                $resumeWeekStart = $weekStart->copy()->addWeek();
                $resumeKey = $resumeWeekStart->toDateString();
                $nextIsActive = ! $skippedSet->has($resumeKey)
                    && ! $primarySubscription->isWithinPauseWindow($resumeWeekStart);
                $label = $nextIsActive
                    ? __('customer.dashboard.almanac.label_back_in_week', ['n' => (int) $resumeWeekStart->isoWeek()])
                    : __('customer.dashboard.almanac.label_in_pause');
                $status = 'skipped';
            } elseif ($box) {
                $label = __('customer.dashboard.almanac.box_label', ['id' => str_pad((string) $box->id, 4, '0', STR_PAD_LEFT)]);
                // Mark the first composed-and-not-skipped row as UNTERWEGS;
                // everything else (composed or otherwise) is scheduled.
                $status = $i === 0 ? 'on_its_way' : 'scheduled';
            } else {
                $label = \App\Support\EditorialContent::weekTagline($weekStart);
                $status = 'scheduled';
            }

            $almanacRows->push([
                'iso_week' => $isoWeek,
                'delivery_date' => $deliveryDate,
                'label' => $label,
                'status' => $status,
            ]);
        }
    }
@endphp

<x-app-layout :hide-app-footer="$usesNewHero">
    {{-- Spec-aligned layout: max content width 1400px, horizontal padding
         24px mobile → 48px desktop, no global space-y-* (each section
         carries its own py-* + hairline divider so the page reads as a
         printed almanac with chapters). --}}
    <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
        {{-- Hero — py-16 lg:py-24, hairline bottom border. --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24">
            @if ($usesNewHero)
                <x-customer.dashboard-hero
                    :user="Auth::user()"
                    :subscription="$primarySubscription"
                    :currentBox="$currentBox"
                />
            @else
                <x-customer.hero-band
                    chapter="produce"
                    density="compact"
                    :image="asset('images/mock/hero/hero-farm.jpg')"
                    :eyebrow="__('Dashboard')"
                    :title="__('customer.story.dashboard.greeting', ['name' => Auth::user()->name])"
                    :lead="$weeklyIntro"
                    :caption="now()->locale(app()->getLocale())->isoFormat('MMMM YYYY')"
                />
            @endif
        </section>

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

            {{-- ERD-351: the new hero conveys the pause state in its headline + action row,
                 so the separate active-pause banner becomes redundant. Keep it only for
                 paths still on the old hero (i.e. shouldn't fire anyway, but explicit). --}}
            @if (! $usesNewHero && $primarySubscription && $primarySubscription->isWithinPauseWindow(now()))
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
                    class="py-12 lg:py-16"
                    aria-labelledby="dashboard-resume-setup-heading"
                    data-testid="dashboard-resume-subscription-setup"
                >
                    <div class="grid gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-16">

                        {{-- Left: eyebrow + headline + lead + actions --}}
                        <div class="flex flex-col justify-between">
                            <div>
                                <p class="eyebrow text-chapter-produce">{{ __('status.pending_payment') }}</p>
                                <h2 id="dashboard-resume-setup-heading" class="mt-4 font-display text-4xl leading-[1.05] tracking-tight text-balance text-brand-ink md:text-5xl">
                                    {{ __('customer.dashboard.pending.h2_line1') }}<br>
                                    <em class="font-normal italic text-chapter-produce">{{ __('customer.dashboard.pending.h2_line2') }}</em>
                                </h2>
                                <p class="mt-6 max-w-md text-base leading-relaxed text-brand-ink/60">
                                    {{ __('You picked your box and preferences but have not yet completed the payment step. No charge has been made — your first box will ship once payment is set up.') }}
                                </p>
                            </div>
                            <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                                <a href="{{ route('app.subscriptions.onboard.payment') }}"
                                   class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90"
                                   data-track-section="resume_setup.payment"
                                   data-track-destination="app.subscriptions.onboard.payment">
                                    {{ __('Resume payment setup') }}
                                </a>
                                <a href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                                   class="border-b border-brand-ink/30 pb-0.5 text-brand-ink/70 hover:text-brand-ink"
                                   data-track-section="resume_setup.restart"
                                   data-track-destination="app.onboarding.start">
                                    {{ __('Or restart and change your selections') }}
                                </a>
                            </div>
                        </div>

                        {{-- Right: At a glance panel --}}
                        @if ($sub)
                            <div class="bg-brand-paper p-8 lg:p-10">
                                <div class="flex items-start justify-between">
                                    <span class="eyebrow text-chapter-produce">{{ __('At a glance') }}</span>
                                    <span class="eyebrow text-brand-ink/40">{{ __('Draft') }}</span>
                                </div>
                                <dl class="mt-8 grid grid-cols-2 gap-x-8 gap-y-8">
                                    <div>
                                        <dt class="eyebrow text-brand-ink/55">{{ __('Box size') }}</dt>
                                        <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $sizeLabel ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="eyebrow text-brand-ink/55">{{ __('Cadence') }}</dt>
                                        <dd class="mt-2 font-display text-2xl text-brand-ink">
                                            {{ $sub->plan === \App\Enums\SubscriptionPlan::Weekly ? __('Every week') : __('Every two weeks') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="eyebrow text-brand-ink/55">{{ __('Eggs add-on') }}</dt>
                                        <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $hasEggsAddon ? __('Yes') : __('No') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="eyebrow text-brand-ink/55">{{ __('Status') }}</dt>
                                        <dd class="mt-2">
                                            <x-status-badge :value="\App\Enums\SubscriptionStatus::PendingPayment" type="subscription" />
                                        </dd>
                                    </div>
                                </dl>
                                <div class="mt-8 flex items-baseline justify-between border-t border-brand-ink/10 pt-6">
                                    <span class="eyebrow text-chapter-produce">{{ __('Per delivery') }}</span>
                                    <span class="font-display text-4xl text-brand-ink tabular-nums">
                                        EUR {{ number_format($sub->expectedChargeAmount(), 2, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endif

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
            {{-- ERD-351: the SubscriptionFirstWeek welcome card duplicates what the
                 new dashboard-hero already says (greeting + planned/composed delivery
                 line + lead). Suppressed when the new hero is in use. --}}
            @if ($kind === DashboardKind::SubscriptionFirstWeek && ! $usesNewHero)
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

        {{-- Manifest — py-20 lg:py-28, hairline divider. --}}
        @if ($manifestState !== null && $manifestItems->isNotEmpty())
            <section class="border-b border-brand-ink/10 py-20 lg:py-28">
                <x-customer.manifest-grid
                    :eyebrow="$manifestState === 'composed'
                        ? __('customer.dashboard.manifest.eyebrow_composed')
                        : __('customer.dashboard.manifest.eyebrow_uncomposed')"
                    :title="__('customer.dashboard.manifest.title')"
                    :subtitle="$manifestState === 'composed'
                        ? __('customer.dashboard.manifest.subtitle_composed')
                        : __('customer.dashboard.manifest.subtitle_uncomposed')"
                >
                    @foreach ($manifestItems as $item)
                        <x-customer.item-card
                            :number="$item['number']"
                            :name="$item['name']"
                            :quantity-label="$item['quantityLabel']"
                            :producer-name="$item['producerName']"
                            :producer-region="$item['producerRegion']"
                            :image-url="$item['imageUrl']"
                            :description="$item['description']"
                            :allergens="$item['allergens']"
                            :is-extra="$item['isExtra']"
                        />
                    @endforeach
                </x-customer.manifest-grid>
            </section>
        @endif

        {{-- Producer feature — full-bleed paper-tinted band per spec.
             Breaks out of the max-w-[1400px] wrapper via w-screen +
             left-1/2 -translate-x-1/2 so the darker cream colour
             reaches the viewport edges, then re-applies the same
             max-width container internally so the feature itself
             stays aligned with the rest of the page. --}}
        @if ($usesNewHero && $kind !== DashboardKind::SubscriptionPaused)
            <section class="relative left-1/2 w-screen -translate-x-1/2 border-y border-brand-ink/10 bg-brand-paper py-20 lg:py-28">
                <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                    <x-customer.producer-feature :producer="$producerOfWeek" />
                </div>
            </section>
        @endif

        {{-- Recipe — py-20 lg:py-28, hairline divider. --}}
        @if ($usesNewHero && $kind !== DashboardKind::SubscriptionPaused && ! empty($recipeOfWeek['slug']))
            <section class="border-b border-brand-ink/10 py-20 lg:py-28">
                <x-customer.recipe-feature
                    :recipe="$recipeOfWeek"
                    :matched-count="$recipeMatchedCount"
                    :total-count="$recipeTotalCount"
                />
            </section>
        @endif

        {{-- Almanac — py-20 lg:py-24, hairline divider. --}}
        @if ($usesNewHero && $almanacRows->isNotEmpty())
            <section class="border-b border-brand-ink/10 py-20 lg:py-24">
                <x-customer.almanac-rows :rows="$almanacRows->all()" />
            </section>
        @endif

        {{-- Story block — only for non-new-hero paths (the spec doesn't
             include a story block; the steady-state dashboard's editorial
             tone is carried by the producer-feature and recipe-feature). --}}
        @unless ($usesNewHero)
            <x-customer.story-block
                chapter="bread"
                :image="asset('images/mock/stalls/produce/scene.jpg')"
                :eyebrow="__('customer.story.weekly_story_eyebrow')"
                :quote="$founderNote"
                :signature="__('customer.story.dashboard.founder_signature')"
            />
        @endunless

            {{-- ─── Bottom rail removed in the dashboard rebuild ───────────
                 The legacy 3-column rail (Next deliveries / Current week box /
                 Almanach) only ever rendered for states whose
                 `showsSubscriptionBoxes()` was true — exactly the steady-state
                 path the new editorial dashboard fully covers:
                   - "Next deliveries" timeline → hero next-delivery line +
                     almanac's 4-week table with "Vollen Kalender öffnen →".
                   - "Current week box" item list → manifest grid (richer per
                     item: photo, producer, descriptor).
                   - "Almanach" upcoming boxes card → <x-customer.almanac-rows>.
                 Trial / new-visitor / resume-setup / cancelled paths never
                 satisfied `showsSubscriptionBoxes()`, so removing the rail
                 changes nothing for them either. --}}

            {{-- ERD-356: editorial green footer band. Renders for the same
                 steady-state subscriber path as the rest of §1-5 so the new
                 dashboard reads as one cohesive editorial spread. The
                 "Pausieren" link dispatches the confirm-subscription-pause
                 modal already hosted by <x-customer.dashboard-hero>, so we
                 only expose it when modifying the pause is possible (active
                 + non-cancelled subscriptions). --}}
        @if ($usesNewHero)
            <x-customer.editorial-footer-band />
        @endif
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
