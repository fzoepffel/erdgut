@extends('layouts.onboarding', ['step' => 'preferences', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-eggdairy">{{ __('Your preferences') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('What should go in your box?') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Pick your size and dietary preferences — you can change this later.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.preferences.store') }}" class="mt-8 space-y-8">
        @csrf

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Box size') }}</legend>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach(['small', 'medium', 'large'] as $size)
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-5 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="box_size" value="{{ $size }}" {{ ($data['box_size'] ?? 'medium') === $size ? 'checked' : '' }} class="mt-1 sr-only">
                        <span class="flex flex-col gap-1">
                            <span class="font-display text-lg font-semibold text-brand-ink">{{ __(ucfirst($size)) }}</span>
                            <span class="text-xs leading-relaxed text-brand-ink/70">{{ __('box_size.description.' . $size) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('box_size')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Preferences') }}</legend>
            <div class="mt-4 space-y-3">
                @foreach([
                    'veg_only' => 'Vegetarian / vegan only',
                    'meat_add_on' => 'Add a meat portion each delivery',
                    'eggs_dairy_add_on' => 'Add eggs / dairy selection (+ EUR 4.50)'
                ] as $key => $label)
                    <label class="flex cursor-pointer items-center gap-3 border border-brand-ink/15 px-4 py-3 transition hover:border-brand-ink/30 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                        <input type="hidden" name="preferences[{{ $key }}]" value="0">
                        <input type="checkbox" name="preferences[{{ $key }}]" value="1"
                               class="h-4 w-4 border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
                               {{ !empty($data['preferences'][$key] ?? false) ? 'checked' : '' }}>
                        <span class="text-sm text-brand-ink/80">{{ __($label) }}</span>
                    </label>
                @endforeach
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
                <x-input-label for="allergies_note" :value="__('allergens.note_label')" />
                <p class="mt-1 text-xs text-brand-ink/65">{{ __('allergens.note_hint') }}</p>
                <textarea id="allergies_note" name="allergies_note" rows="2"
                          class="mt-2 block w-full border-brand-ink/20 bg-white px-3 py-2 text-sm shadow-none focus:border-chapter-produce focus:ring-chapter-produce"
                          maxlength="500">{{ old('allergies_note', $data['allergies_note'] ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('allergies_note')" class="mt-2" />
            </div>
        </fieldset>

        <div class="flex justify-end pt-4">
            <x-primary-button>{{ __('Continue') }}</x-primary-button>
        </div>
    </form>
@endsection
