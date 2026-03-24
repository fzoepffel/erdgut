@php
    $vegOnly = (bool) data_get($subscription?->preferences, 'veg_only', false);
    $meatAddOn = (bool) data_get($subscription?->preferences, 'meat_add_on', false);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription overview') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-6 text-gray-900">
                    @if (session('status') === 'subscription-updated')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Subscription details saved.') }}
                        </p>
                    @elseif (session('status') === 'subscription-pause-scheduled')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Pause timeframe saved.') }}
                        </p>
                    @elseif (session('status') === 'subscription-resumed')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Subscription resumed immediately.') }}
                        </p>
                    @elseif (session('status') === 'subscription-cancelled')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Subscription cancelled.') }}
                        </p>
                    @elseif (session('status') === 'subscription-created')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Subscription created. Set up your payment method to activate billing.') }}
                        </p>
                        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-sm text-emerald-900">
                            <span class="font-semibold">{{ __('Step 2 of 2: Payment') }}</span>
                            — {{ __('Use “Set up payment method” below to complete your subscription.') }}
                        </div>
                    @elseif (session('status') === 'subscription-missing')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ __('No subscription available to edit yet.') }}
                        </p>
                    @elseif (session('status') === 'subscription-action-not-allowed')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ __('This subscription action is not allowed in the current state.') }}
                        </p>
                    @elseif (session('status') === 'payment-already-setup')
                        <p class="mb-4 rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-800">
                            {{ __('Payment method already set up.') }}
                        </p>
                    @elseif (session('status') === 'payment-updated')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Payment method updated successfully.') }}
                        </p>
                    @elseif (session('status') === 'payment-update-unavailable')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ __('Update payment method is not available. Set up a payment method first.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-success')
                        <p class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            {{ __('Payment method set up successfully.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-failed')
                        <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">
                            {{ __('Payment setup failed. Please try again.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-unavailable')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            {{ __('Payment setup is temporarily unavailable. Please try again later.') }}
                        </p>
                    @endif

                    @if ($subscription === null)
                        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-6">
                            <h3 class="text-base font-semibold text-gray-900">{{ __('No subscription yet') }}</h3>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ __('Start a subscription to choose your box rhythm, size, and dietary preferences. You can set up payment right after.') }}
                            </p>
                            <a
                                href="{{ route('app.subscriptions.create') }}"
                                class="mt-4 inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                            >
                                {{ __('Create subscription') }}
                            </a>
                        </div>
                    @else
                        <div class="mb-8 flex flex-wrap gap-2">
                            <a
                                href="{{ route('app.subscriptions.edit') }}"
                                class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-800"
                            >
                                {{ __('Edit subscription') }}
                            </a>

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                                <x-secondary-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-pause')"
                                >
                                    {{ __('Pause delivery window') }}
                                </x-secondary-button>
                            @endif

                            @if ($subscription->status === \App\Enums\SubscriptionStatus::Paused || $subscription->hasPauseWindow())
                                <x-secondary-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-resume')"
                                >
                                    {{ __('Resume subscription') }}
                                </x-secondary-button>
                            @endif

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                                <x-danger-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-cancel')"
                                >
                                    {{ __('Cancel subscription') }}
                                </x-danger-button>
                            @endif
                        </div>

                        <div class="space-y-8">
                            <section aria-labelledby="sub-section-plan">
                                <h3 id="sub-section-plan" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Subscription and box') }}</h3>
                                <dl class="mt-3 space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Delivery rhythm') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            {{ $subscription->plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Box size') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ __(ucfirst($subscription->box_size)) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Status') }}</dt>
                                        <dd class="mt-1">
                                            <x-status-badge :value="$subscription->status" type="subscription" />
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section aria-labelledby="sub-section-diet">
                                <h3 id="sub-section-diet" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Diet and allergies') }}</h3>
                                <dl class="mt-3 space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Veg option only') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $vegOnly ? __('Yes') : __('No') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Meat add-on') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $meatAddOn ? __('Yes') : __('No') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ __('Allergies') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            @if (! empty($subscription->allergies))
                                                {{ implode(', ', $subscription->allergies) }}
                                            @else
                                                {{ __('None listed') }}
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section aria-labelledby="sub-section-pause">
                                <h3 id="sub-section-pause" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Delivery pause') }}</h3>
                                <p class="mt-2 text-sm text-gray-600">
                                    @if ($subscription->hasPauseWindow())
                                        {{ __('Paused from :from until :until.', ['from' => $subscription->pause_from?->format('d.m.Y'), 'until' => $subscription->pause_until?->format('d.m.Y')]) }}
                                    @else
                                        {{ __('No delivery pause scheduled. Use “Pause delivery window” to skip billing and deliveries for a date range.') }}
                                    @endif
                                </p>
                            </section>

                            <section aria-labelledby="sub-section-billing">
                                <h3 id="sub-section-billing" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Billing and payments') }}</h3>
                                <p class="mt-2 text-sm text-gray-600">
                                    <span class="font-medium text-gray-800">{{ __('Next charge date') }}:</span>
                                    {{ $billingDateDisplay }}
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && empty($subscription->sumup_token))
                                        <a
                                            href="{{ route('app.payments.setup') }}"
                                            class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-emerald-700"
                                        >
                                            {{ __('Set up payment method') }}
                                        </a>
                                    @endif

                                    @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && ! empty($subscription->sumup_token))
                                        <a
                                            href="{{ route('app.payments.update') }}"
                                            class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-emerald-800 shadow-sm transition hover:bg-emerald-50"
                                        >
                                            {{ __('Update payment method') }}
                                        </a>
                                    @endif

                                    <a
                                        href="{{ route('app.payments.history') }}"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                                    >
                                        {{ __('Payment history') }}
                                    </a>
                                </div>
                            </section>

                            @if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled && $subscription->cancellation_reason)
                                <section aria-labelledby="sub-section-cancel-reason">
                                    <h3 id="sub-section-cancel-reason" class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('Cancellation reason') }}</h3>
                                    <p class="mt-2 text-sm text-gray-900">{{ $subscription->cancellation_reason }}</p>
                                </section>
                            @endif
                        </div>

                        <x-modal
                            name="confirm-subscription-pause"
                            :show="$errors->has('pause_from') || $errors->has('pause_until')"
                            focusable
                        >
                            <form method="post" action="{{ route('app.subscriptions.pause') }}" class="p-6">
                                @csrf

                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('Pause delivery window') }}
                                </h2>

                                <p class="mt-2 text-sm text-gray-600">
                                    {{ __('Choose the start and end dates for your pause. During this period we pause billing and deliveries according to your plan. You can resume earlier with “Resume subscription” if needed.') }}
                                </p>

                                <div class="mt-4 space-y-4">
                                    <div>
                                        <x-input-label for="pause_from" :value="__('Pause from')" />
                                        <x-text-input
                                            id="pause_from"
                                            name="pause_from"
                                            type="date"
                                            class="mt-1 block w-full"
                                            :value="old('pause_from', $subscription->pause_from?->format('Y-m-d'))"
                                            required
                                        />
                                        <x-input-error class="mt-2" :messages="$errors->get('pause_from')" />
                                    </div>

                                    <div>
                                        <x-input-label for="pause_until" :value="__('Pause until')" />
                                        <x-text-input
                                            id="pause_until"
                                            name="pause_until"
                                            type="date"
                                            class="mt-1 block w-full"
                                            :value="old('pause_until', $subscription->pause_until?->format('Y-m-d'))"
                                            required
                                        />
                                        <x-input-error class="mt-2" :messages="$errors->get('pause_until')" />
                                    </div>
                                </div>

                                <div class="mt-6 flex justify-end gap-2">
                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                        {{ __('Close') }}
                                    </x-secondary-button>

                                    <x-primary-button>
                                        {{ __('Save pause timeframe') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        </x-modal>

                        <x-modal name="confirm-subscription-resume" focusable>
                            <form method="post" action="{{ route('app.subscriptions.resume') }}" class="p-6">
                                @csrf

                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('Resume subscription now?') }}
                                </h2>

                                <p class="mt-2 text-sm text-gray-600">
                                    {{ __('Your subscription becomes active again immediately. The next charge date is recalculated from today. Scheduled deliveries follow your usual rhythm after that.') }}
                                </p>

                                <div class="mt-6 flex justify-end gap-2">
                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                        {{ __('Not now') }}
                                    </x-secondary-button>

                                    <x-primary-button>
                                        {{ __('Yes, resume now') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        </x-modal>

                        <x-modal
                            name="confirm-subscription-cancel"
                            :show="$errors->has('cancellation_reason')"
                            focusable
                        >
                            <form method="post" action="{{ route('app.subscriptions.cancel') }}" class="p-6">
                                @csrf

                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('Cancel subscription') }}
                                </h2>

                                <p class="mt-2 text-sm text-gray-600">
                                    {{ __('This ends your subscription. Outstanding deliveries may still apply depending on your billing cycle; you will not be charged for future cycles after cancellation.') }}
                                </p>

                                <ul class="mt-3 list-inside list-disc text-sm text-gray-600">
                                    <li>{{ __('Your plan and payment method will no longer be used for new charges.') }}</li>
                                    <li>{{ __('You can subscribe again later with a new signup if we offer it.') }}</li>
                                </ul>

                                <div class="mt-4">
                                    <x-input-label for="cancellation_reason" :value="__('Reason (optional)')" />
                                    <textarea
                                        id="cancellation_reason"
                                        name="cancellation_reason"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                        placeholder="{{ __('Optional feedback') }}"
                                    >{{ old('cancellation_reason') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('cancellation_reason')" />
                                </div>

                                <div class="mt-6 flex justify-end gap-2">
                                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                        {{ __('Keep subscription') }}
                                    </x-secondary-button>

                                    <x-danger-button>
                                        {{ __('Confirm cancellation') }}
                                    </x-danger-button>
                                </div>
                            </form>
                        </x-modal>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
