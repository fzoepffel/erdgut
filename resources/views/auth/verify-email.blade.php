<x-guest-layout>
    <div class="mb-6">
        <p class="eyebrow text-chapter-produce">{{ __('Verify email') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Almost there.') }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-brand-ink/75">
            {{ __('Thanks for signing up! Please verify your email using the link we sent. If it’s missing, check spam — then you can resend below.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-6 border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm font-medium text-brand-ink" role="status">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex flex-col gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button class="w-full justify-center">
                {{ __('Resend verification email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <x-secondary-button class="w-full justify-center">
                {{ __('Log out') }}
            </x-secondary-button>
        </form>
    </div>
</x-guest-layout>
