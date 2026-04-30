<x-spargel-layout :title="__('spargel.pay_page_title')" :allow-sumup="true">
    @php
        $deliveryDateLabel = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM')
            : null;
    @endphp

    <p class="eyebrow text-chapter-meat">{{ __('spargel.eyebrow_pay') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('spargel.pay_heading') }}
    </h1>
    <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('spargel.pay_intro') }}
    </p>

    {{-- Order summary card --}}
    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_product') }}</dt>
            <dd class="text-brand-ink">{{ $order->line_description }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_subtotal') }}</dt>
            <dd class="tabular-nums text-brand-ink">{{ number_format(((int) $order->quantity_grams / 1000) * (float) $order->unit_price_eur, 2, ',', '.') }} €</dd>
        </div>
        @if ($order->fulfillment_mode === 'delivery')
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_delivery') }}</dt>
                <dd class="tabular-nums text-brand-ink">{{ number_format((float) $order->delivery_fee_eur, 2, ',', '.') }} €</dd>
            </div>
        @else
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_fulfillment') }}</dt>
                <dd class="text-brand-ink">{{ __('spargel.fulfillment_pickup') }}</dd>
            </div>
        @endif
        @if ($deliveryDateLabel)
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_when') }}</dt>
                <dd class="text-brand-ink">{{ $deliveryDateLabel }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_total') }}</dt>
            <dd class="font-display text-lg tabular-nums text-brand-ink">{{ number_format((float) $order->total_amount_eur, 2, ',', '.') }} €</dd>
        </div>
        <p class="mt-1 text-xs text-brand-ink/60">
            {{ __('spargel.summary_vat_inclusive', ['rate' => $vatRatePercent]) }}
        </p>
    </dl>

    @if ($checkoutId)
        <div class="mt-8">
            <h2 id="payment-widget-heading" class="sr-only">{{ __('Card payment form') }}</h2>
            <x-payment.sumup-widget
                :checkout-id="$checkoutId"
                :return-url="$returnUrl"
                :email="$order->email"
                intent="one_time"
            />
            <p class="mt-4 text-xs text-brand-ink/55">
                {{ __('spargel.pay_secure_hint') }}
            </p>
            <div class="mt-6">
                <a href="{{ route('public.spargel.show') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                    ← {{ __('spargel.pay_back') }}
                </a>
            </div>
        </div>
    @else
        <div class="mt-6 border border-chapter-meat/40 bg-chapter-meat/5 p-5 text-sm text-brand-ink" role="alert">
            <p class="eyebrow text-chapter-meat">{{ __('spargel.pay_unavailable_title') }}</p>
            <p class="mt-2 leading-relaxed">{{ __('spargel.pay_unavailable_body') }}</p>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-4 text-sm">
            <a
                href="{{ route('public.spargel.pay', ['spargelOrder' => $order->id]) }}"
                class="btn-solid"
                data-testid="spargel-pay-retry"
            >
                {{ __('Try again') }}
            </a>
            <a href="{{ route('public.spargel.show') }}" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('spargel.pay_back') }}
            </a>
        </div>
    @endif
</x-spargel-layout>
