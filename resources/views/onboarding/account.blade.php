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
