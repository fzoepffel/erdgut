<x-shop-layout :title="__('imprint.page_title')" narrow>
    <p class="eyebrow text-brand-ink/60">{{ __('imprint.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('imprint.heading') }}
    </h1>

    <section class="mt-8 max-w-2xl space-y-6 text-sm leading-relaxed text-brand-ink/85">
        <div>
            <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('imprint.section_provider') }}</h2>
            <p class="mt-1">
                Erdgut UG (haftungsbeschränkt)<br>
                Gotenstr. 19<br>
                10829 Berlin<br>
                Deutschland
            </p>
            <p class="mt-3">
                <span class="text-brand-ink/60">{{ __('imprint.label_represented_by') }}:</span><br>
                Arthur Hamcha
            </p>
        </div>

        <div>
            <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('imprint.section_register') }}</h2>
            <p class="mt-1">
                <span class="text-brand-ink/60">{{ __('imprint.label_register_number') }}:</span> HRB 286220 B<br>
                <span class="text-brand-ink/60">{{ __('imprint.label_register_court') }}:</span> Amtsgericht Charlottenburg (Berlin)
            </p>
        </div>

        <div>
            <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('imprint.section_contact') }}</h2>
            <p class="mt-1">
                <span class="text-brand-ink/60">{{ __('imprint.label_email') }}:</span>
                <a href="mailto:kontakt@erd-gut.de" class="underline-offset-4 hover:underline">kontakt@erd-gut.de</a>
            </p>
        </div>

        <div>
            <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('imprint.section_responsible') }}</h2>
            <p class="mt-1">
                Arthur Hamcha<br>
                <span class="text-brand-ink/60">{{ __('imprint.responsible_note') }}</span>
            </p>
        </div>

        <div>
            <h2 class="font-display text-lg font-semibold text-brand-ink">{{ __('imprint.section_dispute') }}</h2>
            <p class="mt-1">{{ __('imprint.dispute_body') }}</p>
        </div>
    </section>

    <div class="mt-12 text-sm">
        <a href="{{ route('public.shop.index') }}" class="text-brand-ink/65 underline-offset-4 hover:text-brand-ink hover:underline">
            ← {{ __('imprint.back') }}
        </a>
    </div>
</x-shop-layout>
