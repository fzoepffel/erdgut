@extends('layouts.onboarding', ['step' => 'account', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Create your account') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('We will save your details so you can manage deliveries later.') }}</p>

    <form method="POST" action="{{ route('app.onboarding.step.account.store') }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium">{{ __('Full name') }}</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('name')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('email')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium">{{ __('Phone (for delivery updates)') }}</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('phone')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required class="mt-1 w-full border border-brand-ink/30 p-2">
            @error('password')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-1 w-full border border-brand-ink/30 p-2">
        </div>
        <div class="flex justify-between items-center pt-2">
            <a href="{{ route('login') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:underline">{{ __('Already have an account? Log in') }}</a>
            <button type="submit" class="bg-chapter-produce text-white px-5 py-2 font-medium hover:bg-chapter-produce/90">{{ __('Continue') }}</button>
        </div>
    </form>
@endsection
