<x-guest-layout>
    <div class="mb-4 text-sm leading-6 text-gray-600">
        {{ __('Forgot your password? Enter your email and we’ll send a link to choose a new one.') }}
    </div>

    <p class="mb-6 text-sm text-gray-500">
        {{ __('Next step: open the email and follow the link — it may take a minute to arrive.') }}
    </p>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-center text-sm font-medium text-emerald-800 underline-offset-4 hover:underline sm:text-left" href="{{ route('auth.login') }}">
                {{ __('Back to log in') }}
            </a>

            <x-primary-button class="w-full sm:w-auto">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
