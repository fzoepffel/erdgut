@extends('layouts.onboarding', ['step' => 'preferences', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-eggdairy">{{ __('Your preferences') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Pick your box size') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Every Erdgut box is filled with seasonal vegetables and fruit from the region. You can change the size later.') }}
    </p>

    @php
        // D3 (UX notes): the eggs add-on must be impossible to combine
        // with eggs allergy. Alpine reactively unticks + disables the
        // eggs checkbox the moment the customer ticks the eggs allergy
        // (and re-enables when they untick it). The server validates
        // the same rule via PreferencesStepRequest::withValidator so
        // anyone bypassing the JS still gets a 422.
        $initialAllergies = old('allergies', $data['allergies'] ?? []);
        if (! is_array($initialAllergies)) {
            $initialAllergies = [];
        }
        $initialEggsAllergic = in_array(\App\Enums\Allergen::Eggs->value, $initialAllergies, true);
    @endphp

    <form method="POST" action="{{ route('app.onboarding.step.preferences.store') }}" class="mt-8 space-y-8"
          x-data="{
              eggsAllergic: {{ $initialEggsAllergic ? 'true' : 'false' }},
          }"
          x-init="$watch('eggsAllergic', (v) => {
              if (v) { document.getElementById('preferences-eggs-addon').checked = false }
          })"
    >
        @csrf

        {{-- All Erdgut boxes are vegetable + fruit only for now (no meat, no dairy).
             We keep the underlying preference flags so the composition + admin
             surfaces don't need to change, but the customer never sees them. --}}
        <input type="hidden" name="preferences[veg_only]" value="1">
        <input type="hidden" name="preferences[meat_add_on]" value="0">

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Box size') }} <span class="text-chapter-meat" aria-hidden="true">*</span><span class="sr-only"> {{ __('required') }}</span></legend>
            @php
                // Pure typographic chapter cards — chapter-coloured panel,
                // cream text, oversized numeric badge as the visual anchor,
                // big price as the punchline. The colour ladder mirrors the
                // life-stage progression: bread (soil) → produce (sprout) →
                // meat (ripe). No imagery; the brand palette + display type
                // do the heavy lifting at the wizard's narrow ~190px column.
                $sizeCards = [
                    'small' => [
                        'panel' => 'bg-chapter-bread',
                        'tagline' => __('box_size.tagline.small'),
                    ],
                    'medium' => [
                        'panel' => 'bg-chapter-produce',
                        'tagline' => __('box_size.tagline.medium'),
                    ],
                    'large' => [
                        'panel' => 'bg-chapter-meat',
                        'tagline' => __('box_size.tagline.large'),
                    ],
                ];
            @endphp
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach($sizeCards as $size => $card)
                    <label
                        class="group relative flex cursor-pointer flex-col overflow-hidden text-brand-cream transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-20px_rgba(44,36,28,0.35)] has-[:checked]:ring-4 has-[:checked]:ring-brand-ink has-[:checked]:ring-offset-2 has-[:checked]:ring-offset-brand-cream {{ $card['panel'] }}"
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
                        {{-- Pure typography. The fieldset legend above already
                             reads "BOX-GRÖSSE *" — repeating it inside each
                             card was dead weight, so the only top-corner
                             ornament is the rank number, sized larger so it
                             reads as a chapter stamp instead of a column header. --}}
                        <div class="flex flex-1 flex-col p-6 sm:p-7">
                            <p aria-hidden="true" class="font-display text-3xl font-semibold leading-none text-brand-cream/55">
                                {{ sprintf('%02d', $loop->iteration) }}
                            </p>
                            <p class="mt-6 font-display text-4xl font-semibold leading-tight sm:text-5xl">
                                {{ __(ucfirst($size)) }}
                            </p>
                            <p class="mt-3 font-editorial text-sm italic leading-snug text-brand-cream/85">
                                {{ $card['tagline'] }}
                            </p>
                            <div class="mt-auto pt-6">
                                <p class="font-display text-3xl font-semibold tabular-nums">
                                    @money($sizePrices[$size] ?? 0) €
                                </p>
                                <p class="mt-1 text-xs uppercase tracking-eyebrow text-brand-cream/75">
                                    {{ ($isTrial ?? false) ? __('Once') : __('Per delivery') }}
                                </p>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('box_size')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Extras') }}</legend>
            <div class="mt-4">
                <label
                    class="flex cursor-pointer items-start gap-3 border border-brand-ink/15 px-4 py-4 transition hover:border-brand-ink/30 has-[:checked]:border-chapter-eggdairy has-[:checked]:bg-chapter-eggdairy/5"
                    x-bind:class="eggsAllergic ? 'opacity-60 cursor-not-allowed pointer-events-none' : ''"
                >
                    <input type="hidden" name="preferences[eggs_dairy_add_on]" value="0">
                    <input id="preferences-eggs-addon" type="checkbox" name="preferences[eggs_dairy_add_on]" value="1"
                           class="mt-1 h-4 w-4 border-brand-ink/40 text-chapter-eggdairy focus:ring-chapter-eggdairy"
                           {{ ! $initialEggsAllergic && ! empty($data['preferences']['eggs_dairy_add_on'] ?? false) ? 'checked' : '' }}
                           x-bind:disabled="eggsAllergic">
                    <span class="flex flex-col gap-1">
                        <span class="font-display text-base text-brand-ink">{{ __('Add eggs to every box') }}</span>
                        <span class="text-xs text-brand-ink/65">
                            {{ __('Six fresh free-range eggs from regional farms. + EUR :amount per delivery.', ['amount' => \App\Support\Money::format(config('box.eggs_dairy_add_on_eur', 4.5))]) }}
                        </span>
                    </span>
                </label>
                <p
                    x-show="eggsAllergic"
                    x-cloak
                    class="mt-2 text-xs text-chapter-meat"
                    role="status"
                    data-testid="preferences-eggs-allergic-hint"
                >
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
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach(\App\Enums\Allergen::cases() as $allergen)
                    <label class="flex cursor-pointer items-center gap-2 border border-brand-ink/15 px-3 py-2 text-sm transition hover:border-brand-ink/30 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                        <input type="checkbox" name="allergies[]" value="{{ $allergen->value }}"
                               class="h-4 w-4 border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
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
            {{-- B11 (UX notes): preferences / commitment / schedule /
                 delivery all live inside the same "Box" pill on the
                 progress bar. Rather than spelling out the inner-step
                 names (Schedule / Delivery / Commitment) in the button
                 — which made the bar and the button contradict — use a
                 generic "Continue" so the chrome stays consistent
                 regardless of which sub-step is up next. --}}
            <x-primary-button>{{ __('Continue') }}</x-primary-button>
        </div>
    </form>
@endsection
