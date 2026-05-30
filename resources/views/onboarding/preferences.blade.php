@extends('layouts.onboarding', ['step' => 'preferences', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    @php
        // The eggs add-on must be impossible to combine with an eggs allergy.
        // Alpine reactively unticks + disables the eggs checkbox the moment the
        // customer ticks the eggs allergy; the server validates the same rule
        // via PreferencesStepRequest::withValidator so a JS bypass still 422s.
        $initialAllergies = old('allergies', $data['allergies'] ?? []);
        if (! is_array($initialAllergies)) {
            $initialAllergies = [];
        }
        $initialEggsAllergic = in_array(\App\Enums\Allergen::Eggs->value, $initialAllergies, true);

        $sizeTaglines = [
            'small' => __('box_size.tagline.small'),
            'medium' => __('box_size.tagline.medium'),
            'large' => __('box_size.tagline.large'),
        ];

        $nextCta = $draft->intent === \App\Services\Onboarding\OnboardingDraftManager::INTENT_TRIAL
            ? __('onboarding.cta.to_delivery')
            : __('onboarding.cta.to_schedule');
    @endphp

    <form method="POST" action="{{ route('app.onboarding.step.preferences.store') }}" class="space-y-12"
          x-data="{ eggsAllergic: {{ $initialEggsAllergic ? 'true' : 'false' }} }"
          x-init="$watch('eggsAllergic', (v) => { if (v) { document.getElementById('preferences-eggs-addon').checked = false } })"
    >
        @csrf

        {{-- All Erdgut boxes are vegetable + fruit only for now. We keep the
             underlying preference flags so composition/admin surfaces don't
             change, but the customer never sees them. --}}
        <input type="hidden" name="preferences[veg_only]" value="1">
        <input type="hidden" name="preferences[meat_add_on]" value="0">

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Box size') }} <span class="text-chapter-meat" aria-hidden="true">*</span><span class="sr-only"> {{ __('required') }}</span></legend>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach(['small', 'medium', 'large'] as $i => $size)
                    <label
                        class="group relative flex cursor-pointer flex-col border border-brand-ink/15 bg-brand-cream p-6 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-brand-paper"
                        data-testid="onboarding-box-size-card-{{ $size }}"
                    >
                        <input type="radio" name="box_size" value="{{ $size }}" class="sr-only" required
                               {{ ($data['box_size'] ?? null) === $size ? 'checked' : '' }}>
                        <span aria-hidden="true" class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/45">
                            {{ sprintf('%02d', $i + 1) }}
                        </span>
                        <span class="mt-4 font-display text-2xl text-brand-ink">{{ __(ucfirst($size)) }}</span>
                        <span class="mt-2 font-editorial text-sm italic leading-snug text-brand-ink/60">{{ $sizeTaglines[$size] }}</span>
                        <span class="mt-6 font-display text-3xl tabular-nums text-brand-ink">@money($sizePrices[$size] ?? 0) €</span>
                        <span class="mt-1 font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">
                            {{ ($isTrial ?? false) ? __('Once') : __('Per delivery') }}
                        </span>
                        <span class="mt-4 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-produce opacity-0 transition group-has-[:checked]:opacity-100">
                            · {{ __('onboarding.chosen') }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('box_size')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Extras') }}</legend>
            <div class="mt-4">
                <label
                    class="group flex cursor-pointer items-start gap-4 border border-brand-ink/15 bg-brand-cream px-5 py-5 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-brand-paper"
                    x-bind:class="eggsAllergic ? 'opacity-60 cursor-not-allowed pointer-events-none' : ''"
                >
                    <input type="hidden" name="preferences[eggs_dairy_add_on]" value="0">
                    <input id="preferences-eggs-addon" type="checkbox" name="preferences[eggs_dairy_add_on]" value="1"
                           class="mt-1 h-4 w-4 rounded-none border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
                           {{ ! $initialEggsAllergic && ! empty($data['preferences']['eggs_dairy_add_on'] ?? false) ? 'checked' : '' }}
                           x-bind:disabled="eggsAllergic">
                    <span class="flex flex-col gap-1">
                        <span class="font-display text-lg text-brand-ink">{{ __('Add eggs to every box') }}</span>
                        <span class="text-sm text-brand-ink/60">
                            {{ __('Six fresh free-range eggs from regional farms. + EUR :amount per delivery.', ['amount' => \App\Support\Money::format(config('box.eggs_dairy_add_on_eur', 4.5))]) }}
                        </span>
                    </span>
                </label>
                <p x-show="eggsAllergic" x-cloak class="mt-2 text-xs text-chapter-meat" role="status" data-testid="preferences-eggs-allergic-hint">
                    {{ __('Eggs add-on is disabled because eggs is in your allergies.') }}
                </p>
                <x-input-error :messages="$errors->get('preferences.eggs_dairy_add_on')" class="mt-2" />
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
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach(\App\Enums\Allergen::cases() as $allergen)
                    <label class="flex cursor-pointer items-center gap-2 border border-brand-ink/15 bg-brand-cream px-3 py-2.5 text-sm transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-brand-paper">
                        <input type="checkbox" name="allergies[]" value="{{ $allergen->value }}"
                               class="h-4 w-4 rounded-none border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
                               {{ in_array($allergen->value, $selectedAllergens, true) ? 'checked' : '' }}
                               @if ($allergen === \App\Enums\Allergen::Eggs)
                                   x-on:change="eggsAllergic = $event.target.checked"
                                   data-testid="allergy-eggs-checkbox"
                               @endif
                        >
                        <span class="text-brand-ink/85">{{ __('allergens.'.$allergen->value) }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('allergies')" class="mt-2" />
            <x-input-error :messages="$errors->get('allergies.*')" class="mt-1" />

            <div class="mt-6">
                <x-input-label for="allergies_note" :value="__('allergens.note_label')" optional />
                <p class="mt-1 text-xs text-brand-ink/65">{{ __('allergens.note_hint') }}</p>
                <textarea id="allergies_note" name="allergies_note" rows="2"
                          class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-sm text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0"
                          maxlength="500">{{ old('allergies_note', $data['allergies_note'] ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('allergies_note')" class="mt-2" />
            </div>
        </fieldset>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
            <a href="{{ route('app.onboarding.step.address') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('onboarding.cta.back') }}
            </a>
            <x-primary-button>{{ $nextCta }}</x-primary-button>
        </div>
    </form>
@endsection
