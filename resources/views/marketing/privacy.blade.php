<x-spargel-layout :title="__('privacy.page_title')">
    <p class="eyebrow text-brand-ink/60">{{ __('privacy.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('privacy.heading') }}
    </h1>
    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/75">
        {{ __('privacy.intro') }}
    </p>

    <section class="mt-8 max-w-2xl space-y-4 text-sm leading-relaxed text-brand-ink/85">
        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_data_heading') }}</h2>
        <p>{{ __('privacy.section_data_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_purpose_heading') }}</h2>
        <p>{{ __('privacy.section_purpose_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_third_parties_heading') }}</h2>
        <p>{{ __('privacy.section_third_parties_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_cookies_heading') }}</h2>
        <p>{{ __('privacy.section_cookies_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_retention_heading') }}</h2>
        <p>{{ __('privacy.section_retention_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('privacy.section_rights_heading') }}</h2>
        <p>{!! __('privacy.section_rights_body') !!}</p>
    </section>

    <div class="mt-12 text-sm">
        <a href="{{ url()->previous() === url()->current() ? route('public.home') : url()->previous() }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('privacy.back') }}
        </a>
    </div>
</x-spargel-layout>
