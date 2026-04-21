@extends('layouts.onboarding', ['step' => 'preferences', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('What should go in your box?') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('Pick your size and dietary preferences — you can change this later.') }}</p>

    <form method="POST" action="{{ route('app.onboarding.step.preferences.store') }}" class="mt-6 space-y-6">
        @csrf

        <fieldset>
            <legend class="text-sm font-semibold">{{ __('Box size') }}</legend>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach(['small' => '~6 items (EUR 20 value)', 'medium' => '~11 items (EUR 35 value)', 'large' => '~15 items (EUR 42 value)'] as $size => $desc)
                    <label class="border border-brand-ink/30 p-4 flex items-start gap-2 cursor-pointer has-[:checked]:border-category-produce has-[:checked]:bg-category-produce/5">
                        <input type="radio" name="box_size" value="{{ $size }}" {{ ($data['box_size'] ?? 'medium') === $size ? 'checked' : '' }} class="mt-1">
                        <span>
                            <span class="block font-medium">{{ ucfirst($size) }}</span>
                            <span class="block text-xs text-brand-ink/70">{{ $desc }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('box_size')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset>
            <legend class="text-sm font-semibold">{{ __('Preferences') }}</legend>
            <div class="mt-3 space-y-2">
                @foreach([
                    'veg_only' => 'Vegetarian / vegan only',
                    'meat_add_on' => 'Add a meat portion each delivery',
                    'eggs_dairy_add_on' => 'Add eggs / dairy selection (+ EUR 4.50)'
                ] as $key => $label)
                    <label class="flex items-center gap-2">
                        <input type="hidden" name="preferences[{{ $key }}]" value="0">
                        <input type="checkbox" name="preferences[{{ $key }}]" value="1"
                               {{ !empty($data['preferences'][$key] ?? false) ? 'checked' : '' }}>
                        <span class="text-sm">{{ __($label) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="allergies" class="block text-sm font-semibold">{{ __('Allergies and exclusions') }}</label>
            <p class="text-xs text-brand-ink/70 mb-2">{{ __('Comma-separated. We will avoid these in every box.') }}</p>
            <input id="allergies" name="allergies" type="text"
                   value="{{ old('allergies', implode(', ', $data['allergies'] ?? [])) }}"
                   class="w-full border border-brand-ink/30 p-2">
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-category-produce text-white px-5 py-2 font-medium hover:bg-category-produce/90">{{ __('Continue') }}</button>
        </div>
    </form>
@endsection
