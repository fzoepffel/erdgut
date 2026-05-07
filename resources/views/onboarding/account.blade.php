@extends('layouts.onboarding', ['step' => 'account', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-produce">{{ __('Get started') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Create your account') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('We will save your details so you can manage deliveries later.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.account.store') }}" class="mt-8 space-y-6">
        @csrf
        <div>
            <x-input-label for="name" :value="__('Full name')" required />
            <x-text-input id="name" name="name" type="text" :value="old('name')" required autocomplete="name" class="mt-2" />
            <x-input-error id="name-error" :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" :value="__('Email')" required />
            <x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="email" class="mt-2" />
            <x-input-error id="email-error" :messages="$errors->get('email')" class="mt-2" />

            @if (session('account_step_email_already_registered'))
                {{-- B6 (UX notes): email is already registered. Surface a
                     one-click recovery rather than letting the user stare
                     at "already taken" with no exit. The login link
                     pre-fills email via the standard login route. --}}
                <div class="mt-2 border border-chapter-meat/30 bg-chapter-meat/5 px-3 py-2 text-sm text-brand-ink/85"
                     role="alert"
                     data-testid="account-step-email-recovery">
                    <p>{{ __('This email is already registered.') }}</p>
                    <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                        <a href="{{ route('auth.login') }}" class="font-semibold text-chapter-meat underline-offset-4 hover:underline" data-testid="account-step-email-recovery-login">
                            {{ __('Log in') }}
                        </a>
                        <span aria-hidden="true">·</span>
                        <a href="{{ route('password.request') }}" class="font-semibold text-chapter-meat underline-offset-4 hover:underline" data-testid="account-step-email-recovery-reset">
                            {{ __('Reset password') }}
                        </a>
                    </p>
                </div>
            @endif
        </div>
        <div>
            <x-input-label for="phone" :value="__('Phone (for delivery updates)')" optional />
            <x-text-input id="phone" name="phone" type="tel" :value="old('phone')" autocomplete="tel" class="mt-2" />
            <x-input-error id="phone-error" :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" :value="__('Password')" required />
            <x-text-input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2" />
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm password')" required />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2" />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            <a href="{{ route('auth.login') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                {{ __('Already have an account? Log in') }}
            </a>
            <x-primary-button>{{ __('Continue to :step', ['step' => __('Address')]) }}</x-primary-button>
        </div>
    </form>
@endsection
