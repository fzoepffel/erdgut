@php
    /**
     * Shared allergy selector (ERD-346).
     * Expects variables: $selectedAllergens (array<string>), $allergiesNoteValue (string).
     */
    $selectedAllergens = is_array($selectedAllergens ?? null) ? $selectedAllergens : [];
    $allergiesNoteValue = $allergiesNoteValue ?? '';
@endphp
<fieldset class="space-y-3">
    <legend class="font-display text-lg text-brand-ink">{{ __('Allergies and exclusions') }}</legend>
    <p class="text-xs text-brand-ink/65">{{ __('allergens.pick_hint') }}</p>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach(\App\Enums\Allergen::cases() as $allergen)
            <label class="flex cursor-pointer items-center gap-2 border border-brand-ink/15 px-3 py-2 text-sm transition hover:border-brand-ink/30 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                <input type="checkbox" name="allergies[]" value="{{ $allergen->value }}"
                       class="h-4 w-4 border-brand-ink/40 text-chapter-produce focus:ring-chapter-produce"
                       {{ in_array($allergen->value, $selectedAllergens, true) ? 'checked' : '' }}
                       @if ($allergen === \App\Enums\Allergen::Eggs)
                           {{-- D3 (UX notes): emit a window-level event so any
                                surrounding Alpine form listening for it can
                                disable the eggs add-on. Pages that don't
                                listen are unaffected. --}}
                           x-on:change="window.dispatchEvent(new CustomEvent('eggs-allergic-changed', { detail: { allergic: $event.target.checked } }))"
                           data-testid="allergy-eggs-checkbox"
                       @endif
                >
                <span class="text-brand-ink/85">{{ __('allergens.'.$allergen->value) }}</span>
            </label>
        @endforeach
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('allergies')" />
    <x-input-error class="mt-1" :messages="$errors->get('allergies.*')" />

    <div class="pt-2">
        <x-input-label for="allergies_note" :value="__('allergens.note_label')" />
        <p class="mt-1 text-xs text-brand-ink/65">{{ __('allergens.note_hint') }}</p>
        <textarea id="allergies_note" name="allergies_note" rows="2"
                  class="mt-2 block w-full border-brand-ink/20 bg-white px-3 py-2 text-sm shadow-none focus:border-chapter-produce focus:ring-chapter-produce"
                  maxlength="500">{{ $allergiesNoteValue }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('allergies_note')" />
    </div>
</fieldset>
