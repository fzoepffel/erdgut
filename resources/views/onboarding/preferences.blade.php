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
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach(['small', 'medium', 'large'] as $size)
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-5 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="box_size" value="{{ $size }}" {{ ($data['box_size'] ?? null) === $size ? 'checked' : '' }} class="mt-1 sr-only" required>
                        <span class="flex flex-col gap-1">
                            <span class="font-display text-lg font-semibold text-brand-ink">{{ __(ucfirst($size)) }}</span>
                            <span class="text-xs leading-relaxed text-brand-ink/70">{{ __('box_size.description.' . $size) }}</span>
                            <span class="mt-1 font-display text-sm text-brand-ink">
                                {{ __('box_size.price_per_delivery', ['amount' => \App\Support\Money::format($sizePrices[$size] ?? 0)]) }}
                            </span>
                        </span>
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
