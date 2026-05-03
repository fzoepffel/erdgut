<x-guest-layout>
    <div class="mb-6">
        <p class="eyebrow text-chapter-produce">{{ __('Secure area') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Confirm your password') }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-brand-ink/75">
            {{ __('This is a protected area. Confirm your password to continue to your Erdgut account.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full"
                type="password"
                name="password"
                required autocomplete="current-password" />
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button class="w-full sm:w-auto">
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
