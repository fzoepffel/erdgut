<x-app-layout>
    @php($trialEnabled = (bool) config('features.trial_orders_enabled', true))
    @php($trialPrice = \App\Support\Money::format(config('box.trial_price_oneoff_ttc', 35), 0))

    <div class="pb-10 sm:pb-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 pt-8 sm:px-6 sm:pt-10 lg:px-8">
            <x-customer.hero-band
                chapter="produce"
                :image="asset('images/mock/hero/hero-field.jpg')"
                :eyebrow="__('Welcome')"
                :title="__('Welcome to Erdgut')"
                :lead="\App\Support\EditorialContent::weeklyIntro()"
            />
        </div>
        <div class="mx-auto max-w-3xl space-y-8 px-4 pt-8 sm:px-6 lg:px-8">
            @if (session('status') === 'subscriber-area-requires-setup')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Complete your subscription and payment setup to access deliveries, shop extras, and billing.') }}
                </p>
            @endif

            <section class="border border-brand-ink/15" data-testid="welcome-subscription-card">
                <div class="border-b border-brand-ink/15 px-8 py-6">
                    <p class="eyebrow text-chapter-produce">{{ __('Two simple steps') }}</p>
                    <h2 class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                        {{ __('Start your subscription') }}
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                        {{ __('Start your subscription in two steps: choose your box and preferences, then add a payment method. After that, deliveries, your box, and billing will unlock in the app.') }}
                    </p>
                </div>
                <div class="space-y-6 px-8 py-8">
                    <div class="flex gap-5">
                        <span class="font-display text-4xl font-semibold text-brand-ink/20">01</span>
                        <div>
                            <p class="font-display text-lg text-brand-ink">{{ __('Plan and preferences') }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-brand-ink/70">
                                {{ __('Rhythm, box size, dietary preferences, and allergies.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-5">
                        <span class="font-display text-4xl font-semibold text-brand-ink/20">02</span>
                        <div>
                            <p class="font-display text-lg text-brand-ink">{{ __('Payment method') }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-brand-ink/70">
                                {{ __('Secure card setup via SumUp; we only store a payment token.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center">
                        <x-solid-link
                            :href="route('app.onboarding.start', ['intent' => 'subscription'])"
                            data-testid="welcome-cta-subscribe"
                        >
                            {{ __('Start subscription') }}
                        </x-solid-link>
                    </div>
                </div>
            </section>

            @if ($trialEnabled)
                <section
                    class="border border-brand-ink/15 bg-white/50"
                    data-testid="welcome-trial-card"
                    aria-labelledby="welcome-trial-heading"
                >
                    <div class="px-8 py-6">
                        <p class="eyebrow text-chapter-produce">{{ __('No commitment') }}</p>
                        <h2
                            id="welcome-trial-heading"
                            class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl"
                        >
                            {{ __('Try a box first') }}
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">
                            {{ __('EUR :amount for a single Erdgut box — one delivery, no subscription, no stored card. You pick the date; we handle the rest.', ['amount' => $trialPrice]) }}
                        </p>
                        <div class="mt-5">
                            <a
                                href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                                class="inline-flex items-center justify-center border border-brand-ink px-6 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-brand-ink hover:text-white"
                                data-testid="welcome-cta-try-box"
                            >
                                {{ __('Try a box') }}
                            </a>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
