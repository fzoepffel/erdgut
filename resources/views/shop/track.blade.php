<x-shop-layout :title="__('shop.track_page_title')">
    @php
        $locale = app()->getLocale();
        $isDelivery = $order->fulfillment_mode === 'delivery';

        $deliveryDate = $order->expected_delivery_date;
        $deliveryDateLabel = $deliveryDate
            ? $deliveryDate->locale($locale)->isoFormat('dddd, D. MMMM')
            : null;
        $weekNumber = $deliveryDate?->isoWeek();
        $year = $deliveryDate?->year;

        $hasWindow = $order->hasDeliveryWindow();
        $windowLabel = $order->deliveryWindowLabel();

        $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
        $pickupStreetName = $pickup
            ? trim(preg_replace('/\s+\d+.*$/u', '', $pickup['street']))
            : null;

        $bandLocation = $isDelivery ? $order->delivery_street : ($pickup['street'] ?? null);

        // Per-rate VAT breakdown (VAT contained in the gross totals).
        $vatByRate = $order->items
            ->groupBy('vat_rate_snapshot_percent')
            ->map(fn ($group) => round($group->sum(fn ($i) => (float) $i->vat_amount_eur), 2));
        if ((float) $order->delivery_fee_vat_amount_eur > 0) {
            $r = (int) $order->delivery_fee_vat_rate_percent;
            $vatByRate[$r] = round(($vatByRate[$r] ?? 0) + (float) $order->delivery_fee_vat_amount_eur, 2);
        }
        $vatByRate = $vatByRate->sortKeys();
        $itemCount = $order->items->count();
        $discount = (float) $order->discount_eur;
        $deliveryFee = (float) $order->delivery_fee_eur;

        $producers = $order->items
            ->map(fn ($item) => $item->product?->producer)
            ->filter()
            ->unique('id')
            ->values();

        $orderRef = $order->created_at
            ? $order->created_at->locale($locale)->isoFormat('DD MMM HH:mm')
            : null;

        $contactPhone = \App\Services\Shop\ShopOrderService::contactPhone($order);
        $contactPhoneHref = 'tel:'.preg_replace('/[^+0-9]/', '', $contactPhone);
        $contactEmail = __('shop.contact_email');
    @endphp

    {{-- ───────────────────── LiveStatus band ───────────────────── --}}
    <section class="border-b border-shop-border bg-shop-primary text-shop-primary-foreground">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    @if (! $hasWindow)
                        <p class="font-mono text-[0.65rem] uppercase tracking-eyebrow opacity-90">
                            {{ $isDelivery ? __('shop.track_pending_eyebrow_delivery') : __('shop.track_pending_eyebrow_pickup') }}
                        </p>
                        <p class="mt-4 font-display text-5xl leading-[1.05] tracking-tight md:text-6xl">
                            {{ $deliveryDateLabel ?? __('shop.track_window_pending_headline') }}
                        </p>
                        @if ($weekNumber)
                            <p class="mt-3 font-mono text-xs uppercase tracking-eyebrow opacity-80">
                                {{ __('shop.track_subline_kw_follows', ['week' => $weekNumber]) }}
                            </p>
                        @endif
                    @else
                        <p class="font-mono text-[0.65rem] uppercase tracking-eyebrow opacity-90">
                            {{ $isDelivery ? __('shop.track_set_eyebrow_delivery') : __('shop.track_set_eyebrow_pickup') }}
                        </p>
                        <p class="mt-4 font-display text-5xl leading-[1.05] tracking-tight md:text-6xl">
                            {{ __('shop.track_band_window', ['window' => $windowLabel]) }}
                        </p>
                        <p class="mt-3 font-mono text-xs uppercase tracking-eyebrow opacity-80">
                            {{ $deliveryDateLabel }}@if ($bandLocation) · {{ $bandLocation }}@endif
                        </p>
                    @endif
                </div>
                <p class="max-w-xs text-sm leading-relaxed opacity-90">
                    @if (! $hasWindow)
                        {{ $isDelivery ? __('shop.track_pending_helper_delivery') : __('shop.track_pending_helper_pickup') }}
                    @elseif ($isDelivery)
                        {{ __('shop.track_set_helper_delivery') }}
                    @elseif ($pickupStreetName)
                        {{ __('shop.track_set_helper_pickup', ['street' => $pickupStreetName]) }}
                    @else
                        {{ __('shop.track_set_helper_pickup_generic') }}
                    @endif
                </p>
            </div>

            {{-- Inline contact line --}}
            <div class="mt-8 flex flex-wrap gap-x-8 gap-y-3 border-t border-current/15 pt-6 text-sm opacity-85">
                <span>{{ __('shop.contact_question') }} <a href="{{ $contactPhoneHref }}" class="underline underline-offset-4">{{ $contactPhone }}</a></span>
                <span class="hidden opacity-40 sm:inline">·</span>
                <span><a href="mailto:{{ $contactEmail }}" class="underline underline-offset-4">{{ $contactEmail }}</a></span>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Hero ───────────────────────── --}}
    <x-reveal as="section" class="relative border-b border-shop-border">
        @if ($orderRef)
            <div class="absolute right-4 top-6 hidden font-mono text-xs uppercase tracking-eyebrow text-chapter-produce sm:right-6 lg:block lg:px-6">
                {{ __('shop.return_order_label') }} · #{{ $order->id }} · {{ $orderRef }}
            </div>
        @endif
        <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
            <p class="eyebrow text-chapter-produce">
                {{ __('shop.return_hero_kicker', ['week' => $weekNumber, 'year' => $year]) }}
            </p>
            <h1 class="mt-6 max-w-3xl text-balance font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-7xl">
                @if ($isDelivery)
                    {{ __('shop.return_hero_delivery_prefix') }}
                    <em class="font-normal italic text-chapter-produce">{{ __('shop.return_hero_delivery_em') }}</em>
                @else
                    {{ __('shop.return_hero_pickup_prefix') }}
                    <em class="font-normal italic text-chapter-produce">{{ __('shop.return_hero_pickup_em') }}</em>
                @endif
            </h1>
            <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/65">
                {{ $isDelivery ? __('shop.track_hero_body_delivery') : __('shop.track_hero_body_pickup') }}
            </p>
        </div>
    </x-reveal>

    {{-- ──────────────────── Fulfillment block ──────────────────── --}}
    @if ($isDelivery)
        <x-reveal as="section" class="border-b border-shop-border">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_1.6fr] lg:gap-16 lg:px-8 lg:py-16">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('shop.fulfillment_delivery_eyebrow') }}</p>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-brand-ink/65">
                        {{ __('shop.return_delivery_desc') }}
                    </p>
                </div>
                <dl class="grid grid-cols-1 gap-x-12 gap-y-10 sm:grid-cols-2">
                    @if ($deliveryDateLabel)
                        <div>
                            <dt class="eyebrow text-chapter-produce">{{ __('shop.return_label_date') }}</dt>
                            <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">{{ $deliveryDateLabel }}</dd>
                            <div class="mt-4 h-px bg-shop-border"></div>
                            <p class="mt-3 text-sm text-brand-ink/60">KW {{ $weekNumber }}</p>
                        </div>
                    @endif
                    <div>
                        <dt class="eyebrow text-chapter-produce">{{ __('shop.return_label_address') }}</dt>
                        <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">{{ $order->name }}</dd>
                        <div class="mt-4 h-px bg-shop-border"></div>
                        <p class="mt-3 text-sm text-brand-ink/60">
                            {{ $order->delivery_street }} · {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                        </p>
                    </div>
                </dl>
            </div>
        </x-reveal>
    @else
        <x-reveal as="section" class="border-b border-shop-border bg-brand-paper">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_1.4fr] lg:gap-16 lg:px-8 lg:py-16">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('shop.return_pickup_eyebrow') }}</p>
                    @if ($pickupStreetName)
                        <p class="mt-4 max-w-sm text-sm leading-relaxed text-brand-ink/65">
                            {{ __('shop.return_pickup_desc', ['street' => $pickupStreetName]) }}
                        </p>
                    @endif
                </div>
                @if ($pickup)
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('shop.return_label_address') }}</p>
                        <p class="mt-4 font-display text-5xl leading-[1.05] tracking-tight text-brand-ink md:text-6xl">{{ $pickup['street'] }}</p>
                        <p class="mt-3 font-display text-2xl text-brand-ink/60 md:text-3xl">{{ $pickup['postal_code'] }} {{ $pickup['city'] }}</p>
                        <div class="mt-10 grid grid-cols-1 gap-x-12 gap-y-8 border-t border-shop-border pt-8 sm:grid-cols-2">
                            <div>
                                <dt class="eyebrow text-chapter-produce">{{ __('shop.return_label_doorbell') }}</dt>
                                <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">{{ $pickup['name'] }}</dd>
                                <p class="mt-3 text-sm text-brand-ink/60">{{ __('shop.return_doorbell_hint') }}</p>
                            </div>
                            @if ($deliveryDateLabel)
                                <div>
                                    <dt class="eyebrow text-chapter-produce">{{ __('shop.return_label_date') }}</dt>
                                    <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">{{ $deliveryDateLabel }}</dd>
                                    <p class="mt-3 text-sm text-brand-ink/60">KW {{ $weekNumber }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-reveal>
    @endif

    {{-- ─────────────────────── Order ─────────────────────── --}}
    <x-reveal as="section" class="border-b border-shop-border bg-brand-paper">
        <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <div class="mb-12 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('shop.return_basket_eyebrow') }}</p>
                    <h2 class="mt-4 text-balance font-display text-4xl text-brand-ink md:text-5xl">{{ __('shop.return_basket_heading') }}</h2>
                </div>
                <span class="font-mono text-xs uppercase tracking-eyebrow text-brand-ink/55">
                    {{ __('shop.return_basket_count', ['count' => $itemCount, 'week' => $weekNumber]) }}
                </span>
            </div>

            <ul class="divide-y divide-shop-border border-y border-shop-border">
                @foreach ($order->items as $item)
                    <li class="grid grid-cols-12 items-baseline gap-x-4 gap-y-2 py-7">
                        <span class="col-span-3 font-mono text-xs uppercase tracking-eyebrow text-chapter-produce md:col-span-1">
                            {{ $item->product?->displayQuantity((float) $item->quantity) }}
                        </span>
                        <span class="col-span-9 font-display text-2xl text-brand-ink md:col-span-5">{{ $item->product?->name }}</span>
                        <span class="col-span-6 text-sm text-brand-ink/60 md:col-span-3">{{ $item->product?->producer?->name }}</span>
                        <span class="col-span-6 justify-self-end font-display text-xl tabular-nums text-brand-ink md:col-span-3">@eur($item->line_total_eur)</span>
                    </li>
                @endforeach

                @if ($discount > 0 || $deliveryFee > 0)
                    <li class="grid grid-cols-12 items-baseline gap-x-4 py-5">
                        <span class="col-span-6 font-mono text-xs uppercase tracking-eyebrow text-brand-ink/55 md:col-start-7 md:col-span-3">{{ __('shop.summary_line_items') }}</span>
                        <span class="col-span-6 justify-self-end font-mono text-sm tabular-nums text-brand-ink/55 md:col-span-3">@eur($order->items_total_eur)</span>
                    </li>
                @endif

                @if ($discount > 0)
                    <li class="grid grid-cols-12 items-baseline gap-x-4 py-5">
                        <span class="col-span-8 font-mono text-xs uppercase tracking-eyebrow text-chapter-meat md:col-start-7 md:col-span-3">
                            {{ __('coupon.discount_line') }}@if ($order->coupon_code) · {{ $order->coupon_code }}@endif
                        </span>
                        <span class="col-span-4 justify-self-end font-mono text-sm italic tabular-nums text-chapter-meat md:col-span-3">− @eur($order->discount_eur)</span>
                    </li>
                @endif

                @if ($deliveryFee > 0)
                    <li class="grid grid-cols-12 items-baseline gap-x-4 py-5">
                        <span class="col-span-6 font-mono text-xs uppercase tracking-eyebrow text-brand-ink/55 md:col-start-7 md:col-span-3">{{ __('shop.return_line_delivery') }}</span>
                        <span class="col-span-6 justify-self-end font-mono text-sm tabular-nums text-brand-ink/55 md:col-span-3">@eur($order->delivery_fee_eur)</span>
                    </li>
                @endif
            </ul>

            <div class="mt-6 grid grid-cols-12 items-baseline gap-x-4">
                <div class="col-span-12 md:col-start-7 md:col-span-6">
                    <div class="flex items-baseline justify-between">
                        <span class="font-mono text-xs uppercase tracking-eyebrow text-chapter-produce">{{ __('shop.summary_line_total') }}</span>
                        <span class="font-display text-4xl tabular-nums text-chapter-produce">@eur($order->total_amount_eur)</span>
                    </div>
                    @foreach ($vatByRate as $rate => $amount)
                        <p class="mt-2 text-right font-mono text-[0.65rem] uppercase tracking-eyebrow text-brand-ink/55">
                            {{ __('shop.summary_vat_detail', ['rate' => $rate, 'amount' => \App\Support\Money::formatEur($amount)]) }}
                        </p>
                    @endforeach
                </div>
            </div>
        </div>
    </x-reveal>

    {{-- ─────────────────────── Producers ─────────────────────── --}}
    @if ($producers->isNotEmpty())
        <x-reveal as="section" class="border-b border-shop-border">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_1.4fr] lg:gap-16 lg:px-8 lg:py-20">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('shop.return_producers_eyebrow') }}</p>
                    <h2 class="mt-4 text-balance font-display text-4xl leading-tight text-brand-ink md:text-5xl">
                        {{ __('shop.return_producers_heading_1') }}
                        <em class="font-normal italic text-chapter-produce">{{ __('shop.return_producers_heading_em') }}</em>
                        {{ __('shop.return_producers_heading_2') }}
                    </h2>
                    <p class="mt-6 max-w-md text-sm leading-relaxed text-brand-ink/65">
                        {{ __('shop.return_producers_desc') }}
                    </p>
                </div>
                <ul class="divide-y divide-shop-border border-y border-shop-border">
                    @foreach ($producers as $producer)
                        <li class="grid grid-cols-12 items-center gap-x-4 py-5">
                            <span class="col-span-12 font-display text-2xl text-brand-ink md:col-span-4">{{ $producer->name }}</span>
                            <span class="col-span-9 text-sm text-brand-ink/60 md:col-span-6">{{ $producer->region }}</span>
                            <a href="{{ route('public.shop.producers.show', ['producer' => $producer->slug]) }}"
                               class="col-span-3 justify-self-end font-mono text-xs uppercase tracking-eyebrow text-chapter-produce underline underline-offset-4">
                                {{ __('shop.return_producers_visit') }} →
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-reveal>
    @endif

    <div class="mx-auto max-w-6xl px-4 py-10 text-sm sm:px-6 lg:px-8">
        <a href="{{ route('public.shop.index') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('shop.pay_back') }}
        </a>
    </div>
</x-shop-layout>
