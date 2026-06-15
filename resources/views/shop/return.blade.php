<x-shop-layout :title="__('shop.return_page_title')">
    @php
        $locale = app()->getLocale();
        $isDelivery = $order->fulfillment_mode === 'delivery';

        $deliveryDate = $order->expected_delivery_date;
        $deliveryDateLabel = $deliveryDate
            ? $deliveryDate->locale($locale)->isoFormat('dddd, D. MMMM')
            : null;
        $weekNumber = $deliveryDate?->isoWeek();
        $year = $deliveryDate?->year;

        $payInPersonPending = $order->payment_method === 'in_person'
            && $order->status === \App\Enums\ShopOrderStatus::PendingPayment;
        $isHappyState = $payInPersonPending
            || $order->status === \App\Enums\ShopOrderStatus::Paid;

        $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
        $pickupStreetName = $pickup
            ? trim(preg_replace('/\s+\d+.*$/u', '', $pickup['street']))
            : null;

        $vatByRate = \App\Services\Shop\ShopOrderService::vatByRate($order);
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
    @endphp

    @if ($isHappyState)
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
                    @if ($payInPersonPending)
                        {{ __('shop.return_pay_in_person_body', ['email' => $order->email, 'amount' => \App\Support\Money::formatEur($order->total_amount_eur)]) }}
                    @elseif ($isDelivery)
                        {{ __('shop.return_hero_body_delivery', ['email' => $order->email]) }}
                    @else
                        {{ __('shop.return_hero_body_pickup', ['email' => $order->email]) }}
                    @endif
                </p>
            </div>
        </x-reveal>

        {{-- ──────────────────── Fulfillment block ──────────────────── --}}
        @if ($isDelivery)
            <x-reveal as="section" class="border-b border-shop-border bg-brand-paper">
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

        {{-- Receipt download + back link ──────────────────────────────── --}}
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 flex flex-wrap items-center gap-6">
            <a
                href="{{ \App\Services\Shop\ShopOrderService::receiptUrl($order) }}"
                class="inline-flex items-center gap-2 rounded-sm border border-brand-ink/25 px-4 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/70 transition hover:border-brand-ink/50 hover:text-brand-ink"
                data-testid="shop-receipt-download"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                {{ __('shop.receipt_download_button') }}
            </a>
            <a href="{{ route('public.shop.index') }}" class="text-sm text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('shop.pay_back') }}
            </a>
        </div>
    @else
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            @if ($order->status === \App\Enums\ShopOrderStatus::Failed)
                <p class="eyebrow text-chapter-meat">{{ __('shop.return_failed_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                    {{ __('shop.return_failed_heading') }}
                </h1>
                <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                    {{ __('shop.return_failed_body') }}
                </p>
                <div class="mt-6 border border-chapter-meat/40 bg-chapter-meat/5 p-4 text-sm leading-relaxed text-brand-ink">
                    <p>{{ __('shop.return_failed_what_now') }}</p>
                </div>
                <div class="mt-6 flex flex-wrap items-center gap-4 text-sm">
                    <a href="{{ route('public.shop.pay', ['shopOrder' => $order->id]) }}" class="btn-solid" data-testid="shop-failed-retry">
                        {{ __('shop.return_failed_retry') }}
                    </a>
                    <a href="mailto:kontakt@erd-gut.de" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                        kontakt@erd-gut.de
                    </a>
                </div>
            @else
                <p class="eyebrow text-chapter-meat">{{ __('shop.return_pending_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                    {{ __('shop.return_pending_heading') }}
                </h1>
                <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                    {{ __('shop.return_pending_body') }}
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-4 text-sm">
                    <a href="{{ route('public.shop.pay', ['shopOrder' => $order->id]) }}" class="btn-solid">
                        {{ __('Try again') }}
                    </a>
                    <a href="mailto:kontakt@erd-gut.de" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                        kontakt@erd-gut.de
                    </a>
                </div>
            @endif

            <div class="mt-12 text-sm">
                <a href="{{ route('public.shop.index') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
                    ← {{ __('shop.pay_back') }}
                </a>
            </div>
        </div>
    @endif
</x-shop-layout>
