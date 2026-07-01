<x-shop-layout :title="__('restaurant.register_title')" :narrow="true">
    <p class="eyebrow text-shop-primary">{{ __('restaurant.register_eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.register_title') }}</h1>
    <p class="mt-3 max-w-xl text-base leading-relaxed text-shop-muted">{{ __('restaurant.register_intro') }}</p>

    @if ($errors->any())
        <div class="mt-6 border border-chapter-meat/40 bg-chapter-meat/5 p-4 text-sm text-shop-foreground">
            {{ __('restaurant.register_error_intro') }}
        </div>
    @endif

    <form method="POST" action="{{ route('restaurant.register.store') }}" class="mt-8 space-y-5" data-testid="restaurant-register-form">
        @csrf

        @php
            $fields = [
                ['business_name', 'text', true],
                ['contact_name', 'text', true],
                ['email', 'email', true],
                ['phone', 'tel', true],
                ['handelsregisternummer', 'text', false],
                ['ust_id_nr', 'text', false],
                ['billing_street', 'text', true],
                ['billing_postal_code', 'text', true],
                ['billing_city', 'text', true],
            ];
        @endphp

        @foreach ($fields as [$name, $type, $required])
            <div>
                <label for="{{ $name }}" class="block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">
                    {{ __('restaurant.field_'.$name) }}@if (! $required) <span class="text-shop-muted/60">({{ __('restaurant.optional') }})</span>@endif
                </label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" @required($required)
                       class="mt-1 block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary">
                @error($name)<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
        @endforeach

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary">
                @error('password')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_password_confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                       class="mt-1 block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary">
            </div>
        </div>

        <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
            {{ __('restaurant.register_submit') }}
        </button>
        <p class="text-xs text-shop-muted">{{ __('restaurant.register_after_note') }}</p>
    </form>

    <p class="mt-8 border-t border-shop-border pt-6 text-sm text-shop-muted">
        {{ __('restaurant.register_have_account') }}
        <a href="{{ route('auth.login') }}" class="font-semibold text-shop-foreground underline-offset-4 hover:underline">{{ __('Log in') }}</a>
    </p>
</x-shop-layout>
