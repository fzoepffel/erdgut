<x-guest-layout>
    <div class="mb-8">
        <p class="eyebrow text-chapter-produce">{{ __('Join Erdgut') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Create your account') }}</h1>
    </div>

    <form method="POST" action="{{ route('auth.register.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="mt-2 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full"
                type="password"
                name="password"
                required autocomplete="new-password"
                minlength="8" />
            <p class="mt-2 text-xs text-brand-ink/60">
                {{ __('Use at least 8 characters. A mix of letters and numbers is recommended.') }}
            </p>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-2 block w-full"
                type="password"
                name="password_confirmation" required autocomplete="new-password"
                minlength="8" />
            <x-input-error id="password_confirmation-error" :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-end">
            <a class="text-center text-sm font-medium text-brand-ink underline-offset-4 hover:underline sm:me-4 sm:text-right" href="{{ route('auth.login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="w-full sm:w-auto">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
