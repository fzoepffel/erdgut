<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-category-produce">{{ __('Welcome') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Welcome to Erdgut') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'subscriber-area-requires-setup')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Complete your subscription and payment setup to access deliveries, shop extras, and billing.') }}
                </p>
            @endif

            <section class="border border-brand-ink/15">
                <div class="border-b border-brand-ink/15 px-8 py-6">
                    <p class="eyebrow text-category-produce">{{ __('Two simple steps') }}</p>
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
                    <div class="pt-2">
                        <x-solid-link :href="route('app.subscriptions.onboard')">
                            {{ __('Start subscription') }}
                        </x-solid-link>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
