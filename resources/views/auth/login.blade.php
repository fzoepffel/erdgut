<x-guest-layout>
    <div class="mb-8">
        <p class="eyebrow text-chapter-produce">{{ __('Welcome back') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Log in to your account') }}</h1>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('auth.login.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-brand-ink/30 bg-transparent text-brand-ink focus:ring-chapter-produce" name="remember">
                <span class="ms-2 text-sm text-brand-ink/70">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="text-center text-sm font-medium text-brand-ink underline-offset-4 hover:underline sm:text-left" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="w-full sm:w-auto sm:shrink-0">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    <p class="mt-8 border-t border-brand-ink/10 pt-6 text-center text-sm text-brand-ink/70">
        {{ __('New to Erdgut?') }}
        <a href="{{ route('auth.register') }}" class="font-semibold text-brand-ink underline-offset-4 hover:underline">{{ __('Create an account') }}</a>
    </p>

    <p class="mt-3 text-center text-sm text-brand-ink/70">
        {{ __('restaurant.login_prompt') }}
        <a href="{{ route('restaurant.register') }}" class="font-semibold text-brand-ink underline-offset-4 hover:underline">{{ __('restaurant.login_prompt_link') }}</a>
    </p>
</x-guest-layout>
