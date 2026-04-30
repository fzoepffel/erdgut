<x-spargel-layout :title="__('spargel.return_page_title')">
    @php
        $deliveryDateLabel = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM')
            : null;
        // Two "happy" surfaces share the same chrome (delivery banner +
        // summary): online-paid and pay-in-person-pending. The latter
        // hasn't actually been paid yet but the customer's done their
        // part — admin will collect cash at pickup.
        $payInPersonPending = $order->payment_method === 'in_person'
            && $order->status === \App\Enums\SpargelOrderStatus::PendingPayment;
        $isHappyState = $payInPersonPending
            || $order->status === \App\Enums\SpargelOrderStatus::Paid;
    @endphp

    @if ($isHappyState)
        @if ($payInPersonPending)
            <p class="eyebrow text-chapter-produce">{{ __('spargel.return_pay_in_person_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                {{ __('spargel.return_pay_in_person_heading') }}
            </h1>
            <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('spargel.return_pay_in_person_body', ['email' => $order->email, 'amount' => number_format((float) $order->total_amount_eur, 2, ',', '.')]) }}
            </p>
        @else
            <p class="eyebrow text-chapter-produce">{{ __('spargel.return_paid_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                {{ __('spargel.return_paid_heading') }}
            </h1>
            <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('spargel.return_paid_body', ['email' => $order->email]) }}
            </p>
        @endif

        @if ($deliveryDateLabel)
            <div class="mt-6 max-w-xl border border-chapter-produce/30 bg-chapter-produce/5 p-4 text-sm leading-relaxed text-brand-ink">
                <p class="eyebrow text-chapter-produce">
                    @if ($order->fulfillment_mode === 'delivery')
                        {{ __('spargel.return_paid_delivery_eyebrow') }}
                    @else
                        {{ __('spargel.return_paid_pickup_eyebrow') }}
                    @endif
                </p>
                <p class="mt-1 font-display text-base text-brand-ink">
                    {{ __('spargel.delivery_window_main', ['date' => $deliveryDateLabel]) }}
                </p>
            </div>
        @endif

        <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
            @if ((int) $order->quantity_green_grams > 0)
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                    <dt class="eyebrow text-brand-ink/60">{{ (int) $order->quantity_green_grams }} g · {{ __('spargel.type_green') }}</dt>
                    <dd class="tabular-nums text-brand-ink">{{ number_format(((int) $order->quantity_green_grams / 1000) * (float) $order->unit_price_eur, 2, ',', '.') }} €</dd>
                </div>
            @endif
            @if ((int) $order->quantity_white_grams > 0)
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                    <dt class="eyebrow text-brand-ink/60">{{ (int) $order->quantity_white_grams }} g · {{ __('spargel.type_white') }}</dt>
                    <dd class="tabular-nums text-brand-ink">{{ number_format(((int) $order->quantity_white_grams / 1000) * (float) $order->unit_price_eur, 2, ',', '.') }} €</dd>
                </div>
            @endif
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_fulfillment') }}</dt>
                <dd class="text-brand-ink">
                    {{ $order->fulfillment_mode === 'delivery' ? __('spargel.fulfillment_delivery') : __('spargel.fulfillment_pickup') }}
                </dd>
            </div>
            @if ($order->fulfillment_mode === 'delivery')
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                    <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_delivery_to') }}</dt>
                    <dd class="text-right text-brand-ink">
                        {{ $order->delivery_street }}<br>
                        {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                    </dd>
                </div>
            @endif
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_payment_method') }}</dt>
                <dd class="text-brand-ink">
                    {{ $order->payment_method === 'in_person' ? __('spargel.payment_in_person') : __('spargel.payment_online') }}
                </dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('spargel.summary_line_total') }}</dt>
                <dd class="font-display text-lg tabular-nums text-brand-ink">
                    {{ number_format((float) $order->total_amount_eur, 2, ',', '.') }} €
                </dd>
            </div>
        </dl>

        <p class="mt-6 text-sm text-brand-ink/70">
            {{ __('spargel.return_paid_followup') }}
        </p>
    @else
        <p class="eyebrow text-chapter-meat">{{ __('spargel.return_pending_eyebrow') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
            {{ __('spargel.return_pending_heading') }}
        </h1>
        <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
            {{ __('spargel.return_pending_body') }}
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-4 text-sm">
            <a href="{{ route('public.spargel.pay', ['spargelOrder' => $order->id]) }}" class="btn-solid">
                {{ __('Try again') }}
            </a>
            <a href="mailto:kontakt@erd-gut.de" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                kontakt@erd-gut.de
            </a>
        </div>
    @endif

    <div class="mt-12 text-sm">
        <a href="{{ route('public.home') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('spargel.return_back_home') }}
        </a>
    </div>
</x-spargel-layout>
