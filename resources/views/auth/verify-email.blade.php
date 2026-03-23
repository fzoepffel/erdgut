<x-guest-layout>
    <div class="mb-6 text-sm leading-6 text-gray-600">
        {{ __('Thanks for signing up! Please verify your email using the link we sent. If it’s missing, check spam — then you can resend below.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button class="w-full">
                    {{ __('Resend verification email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf

            <button type="submit" class="w-full rounded-md border border-gray-200 bg-white py-2.5 text-sm font-medium text-gray-700 transition hover:border-emerald-300 hover:text-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
                {{ __('Log out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
