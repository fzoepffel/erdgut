@extends('layouts.onboarding', ['step' => 'preferences', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-eggdairy">{{ __('Your preferences') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Pick your box size') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Every Erdgut box is filled with seasonal vegetables and fruit from the region. You can change the size later.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.preferences.store') }}" class="mt-8 space-y-8">
        @csrf

        {{-- All Erdgut boxes are vegetable + fruit only for now (no meat, no dairy).
             We keep the underlying preference flags so the composition + admin
             surfaces don't need to change, but the customer never sees them. --}}
        <input type="hidden" name="preferences[veg_only]" value="1">
        <input type="hidden" name="preferences[meat_add_on]" value="0">

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Box size') }} <span class="text-chapter-meat" aria-hidden="true">*</span><span class="sr-only"> {{ __('required') }}</span></legend>
            @php
                // Three plant-life-stage illustrations mirror the chapter strip
                // on /home: a white-filtered PNG over a chapter-coloured panel.
                // Small = wheat seeds, medium = sunflower microgreens, large
                // = vine tomatoes — seed → seedling → fruit-bearing plant.
                $sizeCards = [
                    'small' => [
                        'image' => 'images/chapters/catalog-wheat-kernels-white-transparent.png',
                        'panel' => 'bg-chapter-bread',
                        'tagline' => __('box_size.tagline.small'),
                    ],
                    'medium' => [
                        'image' => 'images/chapters/catalog-sunflower-microgreens-white-transparent.png',
                        'panel' => 'bg-chapter-produce',
                        'tagline' => __('box_size.tagline.medium'),
                    ],
                    'large' => [
                        'image' => 'images/chapters/catalog-tomatoes-vine-white-transparent.png',
                        'panel' => 'bg-chapter-meat',
                        'tagline' => __('box_size.tagline.large'),
                    ],
                ];
            @endphp
            {{-- Stacked layout (image on top, text below): the wizard column
                 is ~600px wide, so each 3-up card sits at ~190px wide. The
                 homepage chapter strip's image-left-text-right pattern needs
                 ~320px per card to breathe — at 190px it crops the title and
                 wraps the price. The poster-style stack scales down without
                 truncating any content. --}}
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach($sizeCards as $size => $card)
                    <label
                        class="group relative flex cursor-pointer flex-col overflow-hidden text-brand-cream transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-20px_rgba(44,36,28,0.35)] has-[:checked]:ring-2 has-[:checked]:ring-brand-ink {{ $card['panel'] }}"
                        data-testid="onboarding-box-size-card-{{ $size }}"
                    >
                        <input
                            type="radio"
                            name="box_size"
                            value="{{ $size }}"
                            {{ ($data['box_size'] ?? null) === $size ? 'checked' : '' }}
                            class="sr-only"
                            required
                        >
                        <div class="relative h-36 overflow-hidden sm:h-40">
                            <img
                                src="{{ asset($card['image']) }}"
                                alt=""
                                aria-hidden="true"
                                class="absolute inset-0 h-full w-full select-none object-contain object-center p-3"
                                style="filter: brightness(0) invert(1);"
                            />
                        </div>
                        <div class="flex flex-1 flex-col gap-3 border-t border-brand-cream/20 p-5">
                            <p class="eyebrow text-brand-cream/80">
                                {{ sprintf('%02d', $loop->iteration) }} &nbsp;·&nbsp; {{ __('Box size') }}
                            </p>
                            <div>
                                <p class="font-display text-2xl leading-tight">{{ __(ucfirst($size)) }}</p>
                                <p class="mt-1.5 font-editorial text-sm italic leading-relaxed text-brand-cream/85">
                                    {{ $card['tagline'] }}
                                </p>
                            </div>
                            <p class="mt-auto inline-flex items-baseline gap-2 pt-2">
                                <span class="font-display text-xl font-semibold text-brand-cream">
                                    @money($sizePrices[$size] ?? 0) €
                                </span>
                                <span class="text-xs uppercase tracking-eyebrow text-brand-cream/75">
                                    {{ __('Per delivery') }}
                                </span>
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('box_size')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Extras') }}</legend>
            <div class="mt-4">
                <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/15 px-4 py-4 transition hover:border-brand-ink/30 has-[:checked]:border-chapter-eggdairy has-[:checked]:bg-chapter-eggdairy/5">
                    <input type="hidden" name="preferences[eggs_dairy_add_on]" value="0">
                    <input type="checkbox" name="preferences[eggs_dairy_add_on]" value="1"
                           class="mt-1 h-4 w-4 border-brand-ink/40 text-chapter-eggdairy focus:ring-chapter-eggdairy"
                           {{ !empty($data['preferences']['eggs_dairy_add_on'] ?? false) ? 'checked' : '' }}>
                    <span class="flex flex-col gap-1">
                        <span class="font-display text-base text-brand-ink">{{ __('Add eggs to every box') }}</span>
                        <span class="text-xs text-brand-ink/65">
                            {{ __('Six fresh free-range eggs from regional farms. + EUR :amount per delivery.', ['amount' => \App\Support\Money::format(config('box.eggs_dairy_add_on_eur', 4.5))]) }}
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Allergies and exclusions') }}</legend>
            <p class="mt-1 text-xs text-brand-ink/65">{{ __('allergens.pick_hint') }}</p>
            @php
                $selectedAllergens = old('allergies', $data['allergies'] ?? []);
                if (! is_array($selectedAllergens)) {
                    $selectedAllergens = [];
                }
            @endphp
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach(\App\Enums\Allergen::cases() as $allergen)
                    <label class="flex cursor-pointer items-center gap-2 border border-brand-ink/15 px-3 py-2 text-sm transition hover:border-brand-ink/30 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                        <input type="checkbox" name="allergies[]" value="{{ $allergen->value }}"
                               class="h-4 w-4 border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
                               {{ in_array($allergen->value, $selectedAllergens, true) ? 'checked' : '' }}>
                        <span class="text-brand-ink/85">{{ __('allergens.'.$allergen->value) }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('allergies')" class="mt-2" />
            <x-input-error :messages="$errors->get('allergies.*')" class="mt-1" />

            <div class="mt-5">
                <x-input-label for="allergies_note" :value="__('allergens.note_label')" optional />
                <p class="mt-1 text-xs text-brand-ink/65">{{ __('allergens.note_hint') }}</p>
                <textarea id="allergies_note" name="allergies_note" rows="2"
                          class="mt-2 block w-full border-brand-ink/20 bg-white px-3 py-2 text-sm shadow-none focus:border-chapter-produce focus:ring-chapter-produce"
                          maxlength="500">{{ old('allergies_note', $data['allergies_note'] ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('allergies_note')" class="mt-2" />
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            <a href="{{ route('app.onboarding.step.address') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => __('Address')]) }}
            </a>
            <x-primary-button>{{ __('Continue to :step', ['step' => __('Commitment')]) }}</x-primary-button>
        </div>
    </form>
@endsection
