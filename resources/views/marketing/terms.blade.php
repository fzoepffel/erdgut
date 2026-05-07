<x-spargel-layout :title="__('terms.page_title')">
    <p class="eyebrow text-brand-ink/60">{{ __('terms.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('terms.heading') }}
    </h1>
    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/75">
        {{ __('terms.intro') }}
    </p>

    <section class="mt-8 max-w-2xl space-y-4 text-sm leading-relaxed text-brand-ink/85">
        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_scope_heading') }}</h2>
        <p>{{ __('terms.section_scope_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_subscription_heading') }}</h2>
        <p>{{ __('terms.section_subscription_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_billing_heading') }}</h2>
        <p>{{ __('terms.section_billing_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_cancellation_heading') }}</h2>
        <p>{{ __('terms.section_cancellation_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_delivery_heading') }}</h2>
        <p>{{ __('terms.section_delivery_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('terms.section_contact_heading') }}</h2>
        <p>{!! __('terms.section_contact_body') !!}</p>
    </section>

    <div class="mt-12 text-sm">
        <a href="{{ url()->previous() === url()->current() ? route('public.home') : url()->previous() }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('terms.back') }}
        </a>
    </div>
</x-spargel-layout>
