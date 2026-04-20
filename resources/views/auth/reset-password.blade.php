<x-guest-layout>
    <div class="mb-8">
        <p class="eyebrow text-category-produce">{{ __('New password') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Reset your password') }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-brand-ink/70">
            {{ __('Choose a new password for your account.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" minlength="8" />
            <p class="mt-2 text-xs text-brand-ink/60">
                {{ __('Use at least 8 characters. A mix of letters and numbers is recommended.') }}
            </p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-2 block w-full"
                type="password"
                name="password_confirmation" required autocomplete="new-password"
                minlength="8" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button class="w-full sm:w-auto">
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
