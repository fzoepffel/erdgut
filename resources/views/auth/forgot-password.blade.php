<x-guest-layout>
    <div class="mb-8">
        <p class="eyebrow text-category-produce">{{ __('Password reset') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Forgot your password?') }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-brand-ink/70">
            {{ __('Enter your email and we’ll send a link to choose a new one.') }}
        </p>
        <p class="mt-2 text-sm text-brand-ink/55">
            {{ __('Next step: open the email and follow the link — it may take a minute to arrive.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-center text-sm font-medium text-brand-ink underline-offset-4 hover:underline sm:text-left" href="{{ route('auth.login') }}">
                {{ __('Back to log in') }}
            </a>

            <x-primary-button class="w-full sm:w-auto">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
