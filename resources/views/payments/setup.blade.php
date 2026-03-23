@php
    $intent = $intent ?? 'setup';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if ($intent === 'update')
                {{ __('Update payment method') }}
            @else
                {{ __('Set up payment method') }}
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600">
                        @if ($intent === 'update')
                            {{ __('Replace your stored payment details.') }}
                        @else
                            {{ __('Add your payment details to activate your subscription.') }}
                        @endif
                    </p>

                    <script
                        type="text/javascript"
                        src="https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js"
                    ></script>

                    <div
                        x-data="{ widgetError: '' }"
                        x-init="
                            if (!window.SumUpCard) {
                                widgetError = '{{ __('Unable to load payment widget. Please refresh and try again.') }}';
                            } else {
                                try {
                                    SumUpCard.mount({
                                        id: 'sumup-card',
                                        checkoutId: '{{ $checkoutId }}',
                                        email: '{{ $subscription->user->email }}',
                                        locale: 'de-DE',
                                        onResponse: function(type, body) {
                                            if (type === 'success' && body?.status !== 'FAILED') {
                                                const checkoutId = body?.id ?? '{{ $checkoutId }}';
                                                const target = new URL('{{ route('app.payments.setup.complete') }}', window.location.origin);
                                                target.searchParams.set('checkout', checkoutId);
                                                @if ($intent === 'update')
                                                target.searchParams.set('intent', 'update');
                                                @endif
                                                window.location.href = target.toString();
                                                return;
                                            }

                                            if (type === 'success' && body?.status === 'FAILED') {
                                                widgetError = '{{ __('Payment was declined. Please check your card details and try again.') }}';
                                                return;
                                            }

                                            if (type === 'error' || type === 'fail') {
                                                widgetError = body?.message ?? '{{ __('Payment setup failed. Please try again.') }}';
                                            }
                                        }
                                    });
                                } catch(e) {
                                    widgetError = e.message || 'Mount failed';
                                }
                            }
                        "
                    >
                        <div id="sumup-card" class="mt-6 rounded-lg border border-dashed border-gray-300 p-6">
                            <p class="text-sm text-gray-500">
                                {{ __('Loading payment widget...') }}
                            </p>
                        </div>

                        <p x-show="widgetError" x-text="widgetError" class="mt-3 text-sm text-red-600"></p>
                    </div>

                    <p class="mt-3 text-xs text-gray-500">
                        {{ __('Your card details are processed securely by SumUp.') }}
                    </p>

                    <a
                        href="{{ route('app.subscriptions.show') }}"
                        class="mt-6 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        {{ __('Back to subscription') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
