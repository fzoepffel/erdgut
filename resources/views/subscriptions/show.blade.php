@php
    $vegOnly = (bool) data_get($subscription?->preferences, 'veg_only', false);
    $meatAddOn = (bool) data_get($subscription?->preferences, 'meat_add_on', false);
    $eggsDairyAddOn = (bool) data_get($subscription?->preferences, 'eggs_dairy_add_on', false);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Subscription') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Subscription overview') }}
            </h1>
            <p class="mt-3 max-w-2xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('Your weekly rhythm — tuned to your table.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'subscription-updated')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Subscription details saved.') }}
                </p>
            @elseif (session('status') === 'subscription-pause-scheduled')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Pause timeframe saved.') }}
                </p>
            @elseif (session('status') === 'subscription-resumed')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Subscription resumed immediately.') }}
                </p>
            @elseif (session('status') === 'subscription-cancelled')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('Subscription cancelled.') }}
                </p>
            @elseif (session('status') === 'subscription-created')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Subscription created. Set up your payment method to activate billing.') }}
                </p>
                <div class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    <span class="font-semibold">{{ __('Step 2 of 2: Payment') }}</span>
                    — {{ __('Use "Set up payment method" below to complete your subscription.') }}
                </div>
            @elseif (session('status') === 'subscription-missing')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('No subscription available to edit yet.') }}
                </p>
            @elseif (session('status') === 'subscription-action-not-allowed')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('This subscription action is not allowed in the current state.') }}
                </p>
            @elseif (in_array(session('status'), [
                'payment-already-setup',
                'payment-updated',
                'payment-update-unavailable',
                'payment-setup-success',
                'payment-setup-failed',
                'payment-setup-unavailable',
            ], true))
                @include('payments.partials.session-status')
            @endif

            @if ($subscription === null)
                <x-empty-state
                    :title="__('No subscription yet')"
                    :description="__('Start a subscription to choose your box rhythm, size, and dietary preferences. You can set up payment right after.')"
                    variant="card"
                >
                    <x-slot name="action">
                        <x-solid-link :href="route('app.subscriptions.onboard')">
                            {{ __('Create subscription') }}
                        </x-solid-link>
                    </x-slot>
                </x-empty-state>
            @else
                @php
                    $subscriptionTabs = [
                        ['id' => 'sub-plan', 'label' => __('Plan'), 'chapter' => 'produce'],
                        ['id' => 'sub-diet', 'label' => __('Diet'), 'chapter' => 'eggdairy'],
                        ['id' => 'sub-skip', 'label' => __('Skip'), 'chapter' => 'bread'],
                        ['id' => 'sub-billing', 'label' => __('Billing'), 'chapter' => 'fish'],
                    ];
                    if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled && $subscription->cancellation_reason) {
                        $subscriptionTabs[] = ['id' => 'sub-cancel', 'label' => __('Cancelled'), 'chapter' => 'meat'];
                    }
                @endphp

                <x-chapter-tabs :items="$subscriptionTabs" sticky :label="__('Subscription sections')" class="-mx-4 sm:-mx-6 lg:-mx-8" />

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('app.subscriptions.edit') }}" class="btn-solid text-xs">
                        {{ __('Edit subscription') }}
                    </a>

                    @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                        <form method="POST" action="{{ route('app.subscriptions.skip.next') }}" class="inline">
                            @csrf
                            <x-secondary-button type="submit">
                                {{ __('subscriptions.skip_next_week_cta') }}
                            </x-secondary-button>
                        </form>

                        <x-secondary-button
                            x-data=""
                            x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-pause')"
                        >
                            {{ __('subscriptions.skip_range_cta') }}
                        </x-secondary-button>
                    @endif

                    @if ($subscription->status === \App\Enums\SubscriptionStatus::Paused || $subscription->hasPauseWindow())
                        <x-secondary-button
                            x-data=""
                            x-on:click.prevent="$dispatch('open-modal', 'confirm-subscription-resume')"
                        >
                            {{ __('subscriptions.end_skip_cta') }}
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
                    <section id="sub-plan" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="sub-section-plan">
                        <p class="eyebrow text-chapter-produce">{{ __('Plan') }}</p>
                        <h3 id="sub-section-plan" class="mt-2 font-display text-2xl text-brand-ink">{{ __('Subscription and box') }}</h3>
                        <dl class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Delivery rhythm') }}</dt>
                                <dd class="mt-2 font-display text-lg text-brand-ink">
                                    {{ $subscription->plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Fulfillment') }}</dt>
                                <dd class="mt-2 font-display text-lg text-brand-ink">
                                    {{ ($subscription->fulfillment_mode ?? \App\Enums\FulfillmentMode::Delivery) === \App\Enums\FulfillmentMode::Pickup ? __('Pickup') : __('Home delivery') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
                                <dd class="mt-2 font-display text-lg text-brand-ink">{{ __(ucfirst($subscription->box_size)) }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Status') }}</dt>
                                <dd class="mt-2">
                                    <x-status-badge :value="$subscription->status" type="subscription" />
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section id="sub-diet" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="sub-section-diet">
                        <p class="eyebrow text-chapter-eggdairy">{{ __('Preferences') }}</p>
                        <h3 id="sub-section-diet" class="mt-2 font-display text-2xl text-brand-ink">{{ __('Diet and allergies') }}</h3>
                        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Veg option only') }}</dt>
                                <dd class="mt-2 text-base text-brand-ink">{{ $vegOnly ? __('Yes') : __('No') }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Meat add-on') }}</dt>
                                <dd class="mt-2 text-base text-brand-ink">{{ $meatAddOn ? __('Yes') : __('No') }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Eggs or dairy add-on') }}</dt>
                                <dd class="mt-2 text-base text-brand-ink">{{ $eggsDairyAddOn ? __('Yes') : __('No') }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/60">{{ __('Allergies') }}</dt>
                                <dd class="mt-2 text-base text-brand-ink">
                                    @php
                                        $allergenList = array_values(array_filter((array) ($subscription->allergies ?? [])));
                                    @endphp
                                    @if ($allergenList !== [])
                                        <ul class="flex flex-wrap gap-1.5">
                                            @foreach ($allergenList as $allergenValue)
                                                @php
                                                    $case = \App\Enums\Allergen::tryFrom((string) $allergenValue);
                                                    $allergenKey = 'allergens.'.($case?->value ?? '');
                                                @endphp
                                                @if ($case !== null)
                                                    <li class="inline-flex items-center border border-brand-ink/20 bg-brand-ink/[0.04] px-2 py-0.5 text-xs">
                                                        {{ __($allergenKey) }}
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    @else
                                        {{ __('None listed') }}
                                    @endif
                                    @if (! empty($subscription->allergies_note))
                                        <p class="mt-2 text-sm italic text-brand-ink/70">{{ $subscription->allergies_note }}</p>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section id="sub-skip" @class([
                        'scroll-mt-24 border p-6 sm:p-8',
                        'border-chapter-bread/40 bg-chapter-bread/5' => $subscription->hasPauseWindow(),
                        'border-brand-ink/15' => ! $subscription->hasPauseWindow(),
                    ]) aria-labelledby="sub-section-pause">
                        <p class="eyebrow text-chapter-bread">{{ __('Skip window') }}</p>
                        <h3 id="sub-section-pause" class="mt-2 font-display text-2xl text-brand-ink">{{ __('subscriptions.skip_section_title') }}</h3>
                        <p class="mt-4 text-base leading-relaxed text-brand-ink/80">
                            @if ($subscription->hasPauseWindow())
                                {{ __('subscriptions.skip_window_text', ['from' => $subscription->pause_from?->format('d.m.Y'), 'until' => $subscription->pause_until?->format('d.m.Y')]) }}
                            @else
                                {{ __('subscriptions.no_skip_window_text') }}
                            @endif
                        </p>

                        @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && ! empty($skipPickerWeeks))
                            <div class="mt-6 border-t border-brand-ink/10 pt-6">
                                <p class="font-display text-lg text-brand-ink">{{ __('Pick weeks to skip') }}</p>
                                <p class="mt-2 text-sm text-brand-ink/70">{{ __('Tick any weeks in the next twelve you want to skip. Uncheck to bring a week back.') }}</p>
                                <form method="POST" action="{{ route('app.subscriptions.skip.weeks') }}" class="mt-4">
                                    @csrf
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($skipPickerWeeks as $weekMonday)
                                            @php
                                                $iso = $weekMonday->toDateString();
                                                $isUserSkipped = in_array($iso, $userSkippedWeeks, true);
                                                $isLocked = in_array($iso, $pauseLockedWeeks, true);
                                            @endphp
                                            <label class="flex cursor-pointer items-start gap-2 border border-brand-ink/20 p-3 text-sm transition hover:border-brand-ink/40 has-[:checked]:border-chapter-bread has-[:checked]:bg-chapter-bread/10 @if($isLocked) cursor-not-allowed opacity-60 @endif">
                                                <input type="checkbox" name="weeks[]" value="{{ $iso }}" @checked($isUserSkipped || $isLocked) @disabled($isLocked) class="mt-0.5 border-brand-ink/30 text-chapter-bread focus:ring-chapter-bread" />
                                                <span>
                                                    <span class="block font-medium text-brand-ink">{{ $weekMonday->isoFormat('D. MMM') }}</span>
                                                    <span class="block text-xs text-brand-ink/60">
                                                        @if ($isLocked){{ __('Locked by pause') }}@else{{ __('Week of :date', ['date' => $weekMonday->format('d.m.Y')]) }}@endif
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <x-input-error class="mt-2" :messages="$errors->get('weeks')" />
                                    <x-input-error class="mt-2" :messages="$errors->get('weeks.*')" />
                                    <div class="mt-4">
                                        <x-primary-button>{{ __('Save skipped weeks') }}</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </section>

                    <section id="sub-billing" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="sub-section-billing">
                        <p class="eyebrow text-chapter-fish">{{ __('Billing') }}</p>
                        <h3 id="sub-section-billing" class="mt-2 font-display text-2xl text-brand-ink">{{ __('Billing and payments') }}</h3>
                        <p class="mt-4 text-base text-brand-ink/80">
                            <span class="font-semibold text-brand-ink">{{ __('Next charge date') }}:</span>
                            {{ $billingDateDisplay }}
                        </p>
                        <div class="mt-6 flex flex-wrap gap-2">
                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && empty($subscription->sumup_token))
                                <a href="{{ route('app.subscriptions.onboard.payment') }}" class="btn-solid text-xs">
                                    {{ __('Set up payment method') }}
                                </a>
                            @endif

                            @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled && ! empty($subscription->sumup_token))
                                <a href="{{ route('app.payments.update') }}" class="btn-ghost text-xs">
                                    {{ __('Update payment method') }}
                                </a>
                            @endif

                            <a href="{{ route('app.payments.history') }}" class="btn-ghost text-xs">
                                {{ __('Payments and billing') }}
                            </a>
                        </div>
                    </section>

                    @if ($subscription->status === \App\Enums\SubscriptionStatus::Cancelled && $subscription->cancellation_reason)
                        <section id="sub-cancel" class="scroll-mt-24 border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="sub-section-cancel-reason">
                            <p class="eyebrow text-chapter-meat">{{ __('Cancelled') }}</p>
                            <h3 id="sub-section-cancel-reason" class="mt-2 font-display text-2xl text-brand-ink">{{ __('Cancellation reason') }}</h3>
                            <p class="mt-4 text-base leading-relaxed text-brand-ink/80">{{ $subscription->cancellation_reason }}</p>
                        </section>
                    @endif
                </div>

                <x-modal
                    name="confirm-subscription-pause"
                    :show="$errors->has('pause_from') || $errors->has('pause_until')"
                    focusable
                >
                    <form method="post" action="{{ route('app.subscriptions.pause') }}" class="bg-brand-cream p-6">
                        @csrf

                        <p class="eyebrow text-chapter-produce">{{ __('Skip') }}</p>
                        <h2 class="mt-2 font-display text-2xl text-brand-ink">
                            {{ __('subscriptions.skip_range_modal_title') }}
                        </h2>

                        <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                            {{ __('subscriptions.skip_range_modal_intro') }}
                        </p>

                        <div class="mt-5 space-y-5">
                            <div>
                                <x-input-label for="pause_from" :value="__('subscriptions.skip_from_label')" />
                                <x-text-input id="pause_from" name="pause_from" type="date" class="mt-2 block w-full"
                                    :value="old('pause_from', $subscription->pause_from?->format('Y-m-d'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('pause_from')" />
                            </div>

                            <div>
                                <x-input-label for="pause_until" :value="__('subscriptions.skip_until_label')" />
                                <x-text-input id="pause_until" name="pause_until" type="date" class="mt-2 block w-full"
                                    :value="old('pause_until', $subscription->pause_until?->format('Y-m-d'))" required />
                                <x-input-error class="mt-2" :messages="$errors->get('pause_until')" />
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Close') }}
                            </x-secondary-button>

                            <x-primary-button>
                                {{ __('subscriptions.skip_range_save') }}
                            </x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <x-modal name="confirm-subscription-resume" focusable>
                    <form method="post" action="{{ route('app.subscriptions.resume') }}" class="bg-brand-cream p-6">
                        @csrf

                        <p class="eyebrow text-chapter-produce">{{ __('Resume') }}</p>
                        <h2 class="mt-2 font-display text-2xl text-brand-ink">
                            {{ __('subscriptions.end_skip_modal_title') }}
                        </h2>

                        <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                            {{ __('subscriptions.end_skip_modal_intro') }}
                        </p>

                        <div class="mt-6 flex justify-end gap-2">
                            <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                {{ __('Not now') }}
                            </x-secondary-button>

                            <x-primary-button>
                                {{ __('subscriptions.end_skip_confirm') }}
                            </x-primary-button>
                        </div>
                    </form>
                </x-modal>

                <x-modal
                    name="confirm-subscription-cancel"
                    :show="$errors->has('cancellation_reason')"
                    focusable
                >
                    <form method="post" action="{{ route('app.subscriptions.cancel') }}" class="bg-brand-cream p-6">
                        @csrf

                        <p class="eyebrow text-chapter-meat">{{ __('Cancel') }}</p>
                        <h2 class="mt-2 font-display text-2xl text-brand-ink">
                            {{ __('Cancel subscription') }}
                        </h2>

                        <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                            {{ __('This ends your subscription. Outstanding deliveries may still apply depending on your billing cycle; you will not be charged for future cycles after cancellation.') }}
                        </p>

                        <ul class="mt-3 list-inside list-disc text-sm text-brand-ink/75">
                            <li>{{ __('Your plan and payment method will no longer be used for new charges.') }}</li>
                            <li>{{ __('You can subscribe again later with a new signup if we offer it.') }}</li>
                        </ul>

                        <div class="mt-5">
                            <x-input-label for="cancellation_reason" :value="__('Reason (optional)')" />
                            <textarea
                                id="cancellation_reason"
                                name="cancellation_reason"
                                rows="3"
                                class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream px-4 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0"
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

                <div class="flex flex-col items-center gap-3 pt-8">
                    <x-triple-star class="opacity-70" />
                    <p class="font-editorial text-sm italic text-brand-ink/60">
                        {{ __('In the rhythm of the harvest, week by week.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
