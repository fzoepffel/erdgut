<x-shop-layout :title="__('shop_terms.page_title')" narrow>
    <p class="eyebrow text-brand-ink/60">{{ __('shop_terms.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('shop_terms.heading') }}
    </h1>
    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-brand-ink/75">
        {{ __('shop_terms.intro') }}
    </p>

    <section class="mt-8 max-w-2xl space-y-4 text-sm leading-relaxed text-brand-ink/85">
        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_scope_heading') }}</h2>
        <p>{{ __('shop_terms.section_scope_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_contract_heading') }}</h2>
        <p>{{ __('shop_terms.section_contract_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_prices_heading') }}</h2>
        <p>{{ __('shop_terms.section_prices_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_vouchers_heading') }}</h2>
        <p>{{ __('shop_terms.section_vouchers_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_delivery_heading') }}</h2>
        <p>{{ __('shop_terms.section_delivery_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_withdrawal_heading') }}</h2>
        <p>{{ __('shop_terms.section_withdrawal_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_warranty_heading') }}</h2>
        <p>{{ __('shop_terms.section_warranty_body') }}</p>

        <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('shop_terms.section_contact_heading') }}</h2>
        <p>{!! __('shop_terms.section_contact_body') !!}</p>
    </section>

    <div class="mt-12 text-sm">
        <a href="{{ route('public.shop.index') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('shop_terms.back') }}
        </a>
    </div>
</x-shop-layout>
