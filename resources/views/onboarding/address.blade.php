@extends('layouts.onboarding', ['step' => 'address', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Where should we deliver?') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('Primary delivery address and gate / floor details so we can find you.') }}</p>

    <form method="POST" action="{{ route('app.onboarding.step.address.store') }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="recipient_name" class="block text-sm font-medium">{{ __('Recipient name') }}</label>
            <input id="recipient_name" name="recipient_name" type="text" required
                   value="{{ old('recipient_name', $address?->recipient_name ?? auth()->user()?->name) }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('recipient_name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="street_line_1" class="block text-sm font-medium">{{ __('Street and number') }}</label>
            <input id="street_line_1" name="street_line_1" type="text" required
                   value="{{ old('street_line_1', $address?->street_line_1) }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('street_line_1')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="street_line_2" class="block text-sm font-medium">{{ __('Address line 2 (optional)') }}</label>
            <input id="street_line_2" name="street_line_2" type="text"
                   value="{{ old('street_line_2', $address?->street_line_2) }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2">
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
                <label for="postal_code" class="block text-sm font-medium">{{ __('Postal code') }}</label>
                <input id="postal_code" name="postal_code" type="text" required
                       value="{{ old('postal_code', $address?->postal_code) }}"
                       class="mt-1 w-full border border-brand-ink/30 p-2">
                @error('postal_code')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="col-span-2">
                <label for="city" class="block text-sm font-medium">{{ __('City') }}</label>
                <input id="city" name="city" type="text" required
                       value="{{ old('city', $address?->city) }}"
                       class="mt-1 w-full border border-brand-ink/30 p-2">
                @error('city')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="country_code" class="block text-sm font-medium">{{ __('Country') }}</label>
            <input id="country_code" name="country_code" type="text" maxlength="2" required
                   value="{{ old('country_code', $address?->country_code ?? 'DE') }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2 uppercase">
            @error('country_code')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="building_code" class="block text-sm font-medium">{{ __('Building / gate code') }}</label>
                <input id="building_code" name="building_code" type="text"
                       value="{{ old('building_code', $address?->building_code) }}"
                       class="mt-1 w-full border border-brand-ink/30 p-2">
            </div>
            <div>
                <label for="floor" class="block text-sm font-medium">{{ __('Floor / unit') }}</label>
                <input id="floor" name="floor" type="text"
                       value="{{ old('floor', $address?->floor) }}"
                       class="mt-1 w-full border border-brand-ink/30 p-2">
            </div>
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium">{{ __('Phone (for delivery notifications)') }}</label>
            <input id="phone" name="phone" type="tel"
                   value="{{ old('phone', $address?->phone ?? auth()->user()?->phone) }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2">
        </div>
        <div>
            <label for="delivery_instructions" class="block text-sm font-medium">{{ __('Delivery notes') }}</label>
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3"
                      class="mt-1 w-full border border-brand-ink/30 p-2">{{ old('delivery_instructions', $address?->delivery_instructions) }}</textarea>
        </div>
        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-chapter-produce text-white px-5 py-2 font-medium hover:bg-chapter-produce/90">{{ __('Continue') }}</button>
        </div>
    </form>
@endsection
