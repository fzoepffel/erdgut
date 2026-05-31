<x-shop-layout :title="__('shop.return_page_title')" :narrow="true">
    @php
        $deliveryDateLabel = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM')
            : null;
        $payInPersonPending = $order->payment_method === 'in_person'
            && $order->status === \App\Enums\ShopOrderStatus::PendingPayment;
        $isHappyState = $payInPersonPending
            || $order->status === \App\Enums\ShopOrderStatus::Paid;
    @endphp

    @if ($isHappyState)
        @if ($payInPersonPending)
            <p class="eyebrow text-chapter-produce">{{ __('shop.return_pay_in_person_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                {{ __('shop.return_pay_in_person_heading') }}
            </h1>
            <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('shop.return_pay_in_person_body', ['email' => $order->email, 'amount' => \App\Support\Money::format($order->total_amount_eur)]) }}
            </p>
        @else
            <p class="eyebrow text-chapter-produce">{{ __('shop.return_paid_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                {{ __('shop.return_paid_heading') }}
            </h1>
            <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('shop.return_paid_body', ['email' => $order->email]) }}
            </p>
        @endif

        @if ($deliveryDateLabel)
            <div class="mt-6 max-w-xl border border-chapter-produce/30 bg-chapter-produce/5 p-4 text-sm leading-relaxed text-brand-ink">
                <p class="eyebrow text-chapter-produce">
                    @if ($order->fulfillment_mode === 'delivery')
                        {{ __('shop.return_paid_delivery_eyebrow') }}
                    @else
                        {{ __('shop.return_paid_pickup_eyebrow') }}
                    @endif
                </p>
                <p class="mt-1 font-display text-base text-brand-ink">
                    {{ __('shop.delivery_window_main', ['date' => $deliveryDateLabel]) }}
                </p>
            </div>
        @endif

        <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
            @foreach ($order->items as $item)
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                    <dt class="eyebrow text-brand-ink/60">
                        {{ $item->product?->displayQuantity((float) $item->quantity) }} · {{ $item->product?->name }}
                    </dt>
                    <dd class="tabular-nums text-brand-ink">@eur($item->line_total_eur)</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('shop.summary_line_fulfillment') }}</dt>
                <dd class="text-brand-ink">
                    {{ $order->fulfillment_mode === 'delivery' ? __('shop.fulfillment_delivery') : __('shop.fulfillment_pickup') }}
                </dd>
            </div>
            @if ($order->fulfillment_mode === 'delivery')
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                    <dt class="eyebrow text-brand-ink/60">{{ __('shop.summary_line_delivery_to') }}</dt>
                    <dd class="text-right text-brand-ink">
                        {{ $order->delivery_street }}<br>
                        {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                    </dd>
                </div>
            @else
                @php $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location); @endphp
                @if ($pickup)
                    <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                        <dt class="eyebrow text-brand-ink/60">{{ __('shop.summary_line_pickup_at') }}</dt>
                        <dd class="text-right text-brand-ink">
                            {{ $pickup['name'] }}<br>
                            {{ $pickup['street'] }}<br>
                            {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                        </dd>
                    </div>
                @endif
            @endif
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('shop.summary_line_payment_method') }}</dt>
                <dd class="text-brand-ink">
                    {{ $order->payment_method === 'in_person' ? __('shop.payment_in_person') : __('shop.payment_online') }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('shop.summary_line_total') }}</dt>
                <dd class="font-display text-lg tabular-nums text-brand-ink">@eur($order->total_amount_eur)</dd>
            </div>
        </dl>

        <p class="mt-6 text-sm text-brand-ink/70">
            {{ __('shop.return_paid_followup') }}
        </p>
    @elseif ($order->status === \App\Enums\ShopOrderStatus::Failed)
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

    {{-- Stay inside the isolated shop surface: link back to the storefront,
         never to the (hidden) marketing home. --}}
    <div class="mt-12 text-sm">
        <a href="{{ route('public.shop.index') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('shop.pay_back') }}
        </a>
    </div>
</x-shop-layout>
