@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Secure your trial box') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">
        {{ __('One-time charge of EUR :amount. No subscription, no stored card.', ['amount' => number_format((float) $order->amount, 2)]) }}
    </p>

    <div class="mt-4 border border-brand-ink/15 bg-white/60 p-4 text-sm space-y-1">
        <div class="flex justify-between"><span>{{ __('Order #') }}</span><span class="font-mono">{{ $order->id }}</span></div>
        <div class="flex justify-between"><span>{{ __('Box size') }}</span><span>{{ ucfirst($order->box_size) }}</span></div>
        <div class="flex justify-between"><span>{{ __('Amount') }}</span><span>EUR {{ number_format((float) $order->amount, 2) }}</span></div>
    </div>

    @if ($checkoutId)
        <div id="sumup-card" class="mt-6 border border-brand-ink/15 p-4 bg-white"></div>
        <script type="text/javascript" src="https://gateway.sumup.com/gateway/ecom/card/v2.0/sdk.js"></script>
        <script type="text/javascript">
            SumUpCard.mount({
                id: 'sumup-card',
                checkoutId: @json($checkoutId),
                onResponse: function (type, body) {
                    if (type === 'success' || type === 'sent') {
                        window.location.href = @json($returnUrl) + '?paid=1';
                    }
                }
            });
        </script>
    @else
        <p class="mt-6 text-sm text-red-700 bg-red-50 border border-red-200 p-3">
            {{ __('Payment is temporarily unavailable. Please try again shortly.') }}
        </p>
        <a href="{{ route('app.onboarding.step.commitment') }}" class="mt-4 inline-block text-sm underline">{{ __('Back') }}</a>
    @endif
@endsection
