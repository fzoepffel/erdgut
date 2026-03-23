<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription overview') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('status') === 'subscription-updated')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            Subscription details saved.
                        </p>
                    @elseif (session('status') === 'subscription-pause-scheduled')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            Pause timeframe saved.
                        </p>
                    @elseif (session('status') === 'subscription-resumed')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            Subscription resumed immediately.
                        </p>
                    @elseif (session('status') === 'subscription-cancelled')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            Subscription cancelled.
                        </p>
                    @elseif (session('status') === 'subscription-created')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            {{ __('Subscription created. Set up your payment method to activate billing.') }}
                        </p>
                    @elseif (session('status') === 'subscription-missing')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            No subscription available to edit yet.
                        </p>
                    @elseif (session('status') === 'subscription-action-not-allowed')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            This subscription action is not allowed in the current state.
                        </p>
                    @elseif (session('status') === 'payment-already-setup')
                        <p class="mb-4 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-700">
                            Payment method already set up.
                        </p>
                    @elseif (session('status') === 'payment-updated')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            {{ __('Payment method updated successfully.') }}
                        </p>
                    @elseif (session('status') === 'payment-update-unavailable')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            {{ __('Update payment method is not available. Set up a payment method first.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-success')
                        <p class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                            {{ __('Payment method set up successfully.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-failed')
                        <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                            {{ __('Payment setup failed. Please try again.') }}
                        </p>
                    @elseif (session('status') === 'payment-setup-unavailable')
                        <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            {{ __('Payment setup is temporarily unavailable. Please try again later.') }}
                        </p>
                    @endif

                    @if ($subscription === null)
                        <p class="text-sm text-gray-600">{{ __('No active subscription found yet.') }}</p>
                        <a
                            href="{{ route('app.subscriptions.create') }}"
                            class="mt-4 inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500"
                        >
                            {{ __('Create subscription') }}
                        </a>
                    @else
                        <div class="mb-6 flex flex-wrap items-center gap-3">
                            <a
                                href="{{ route('app.subscriptions.edit') }}"
                                class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                            >
                                Edit subscription
                            </a>

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                                <x-secondary-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-pause')"
                                >
                                    Pause timeframe
                                </x-secondary-button>
                            @endif

                            @if ($subscription->status === \App\Enums\SubscriptionStatus::Paused || $subscription->hasPauseWindow())
                                <form method="post" action="{{ route('app.subscriptions.resume') }}">
                                    @csrf
                                    <x-primary-button>Resume now</x-primary-button>
                                </form>
                            @endif

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                                <x-danger-button
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-cancel')"
                                >
                                    Cancel subscription
                                </x-danger-button>
                            @endif

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && empty($subscription->sumup_token))
                                <a
                                    href="{{ route('app.payments.setup') }}"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-indigo-500"
                                >
                                    Set up payment method
                                </a>
                            @endif

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && ! empty($subscription->sumup_token))
                                <a
                                    href="{{ route('app.payments.update') }}"
                                    class="inline-flex items-center rounded-md border border-indigo-600 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-600 transition hover:bg-indigo-50"
                                >
                                    {{ __('Update payment method') }}
                                </a>
                            @endif

                            <a
                                href="{{ route('app.payments.history') }}"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50"
                            >
                                {{ __('Payment history') }}
                            </a>
                        </div>

                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Plan</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($subscription->plan->value) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Box size</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($subscription->box_size) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <x-status-badge :value="$subscription->status" type="subscription" />
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Preferences</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if (! empty($subscription->preferences))
                                        {{ json_encode($subscription->preferences, JSON_UNESCAPED_SLASHES) }}
                                    @else
                                        No preferences saved.
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Allergies</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if (! empty($subscription->allergies))
                                        {{ implode(', ', $subscription->allergies) }}
                                    @else
                                        No allergies listed.
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Upcoming billing date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $billingDateDisplay }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Pause timeframe</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if ($subscription->hasPauseWindow())
                                        {{ $subscription->pause_from?->format('Y-m-d') }} to {{ $subscription->pause_until?->format('Y-m-d') }}
                                    @else
                                        No pause timeframe scheduled.
                                    @endif
                                </dd>
                            </div>

                            @if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled && $subscription->cancellation_reason)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Cancellation reason</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ $subscription->cancellation_reason }}</dd>
                                </div>
                            @endif
                        </dl>

                        <x-modal
                            name="confirm-subscription-pause"
                            :show="$errors->has('pause_from') || $errors->has('pause_until')"
                            focusable
                        >
                            <form method="post" action="{{ route('app.subscriptions.pause') }}" class="p-6">
                                @csrf

                                <h2 class="text-lg font-medium text-gray-900">
                                    Pause subscription timeframe
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    Choose the period where billing should be paused.
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

                                <div class="mt-6 flex justify-end">
                                    <x-secondary-button x-on:click="$dispatch('close')">
                                        Cancel
                                    </x-secondary-button>

                                    <x-primary-button class="ms-3">
                                        Save timeframe
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
                                    Cancel subscription
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    We are sorry to see you go. If you want, share a short reason so we can improve.
                                </p>

                                <div class="mt-4">
                                    <x-input-label for="cancellation_reason" :value="__('Reason (optional)')" />
                                    <textarea
                                        id="cancellation_reason"
                                        name="cancellation_reason"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Optional feedback"
                                    >{{ old('cancellation_reason') }}</textarea>
                                    <x-input-error class="mt-2" :messages="$errors->get('cancellation_reason')" />
                                </div>

                                <div class="mt-6 flex justify-end">
                                    <x-secondary-button x-on:click="$dispatch('close')">
                                        Keep subscription
                                    </x-secondary-button>

                                    <x-danger-button class="ms-3">
                                        Confirm cancellation
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
