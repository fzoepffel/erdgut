<x-shop-layout :title="__('restaurant.account_title')" :narrow="true" restaurant-nav="account">
    @if (session('status'))
        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('status') }}</div>
    @endif

    <p class="eyebrow text-shop-primary">{{ __('restaurant.orders_eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.account_title') }}</h1>

    {{-- ── Business + delivery address ────────────────────────────────── --}}
    <form method="POST" action="{{ route('app.restaurant.account.update') }}" class="shop-card mt-8 space-y-4 p-6">
        @csrf
        @method('PUT')
        <h2 class="font-display text-xl text-shop-foreground">{{ __('restaurant.account_profile_title') }}</h2>
        <p class="text-xs text-shop-muted">{{ __('restaurant.account_profile_hint') }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="business_name" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_business_name') }}</label>
                <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $profile->business_name) }}" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('business_name')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact_name" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_contact_name') }}</label>
                <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $profile->contact_name) }}"
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('contact_name')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_phone') }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('phone')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="billing_street" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_billing_street') }}</label>
                <input type="text" id="billing_street" name="billing_street" value="{{ old('billing_street', $profile->billing_street) }}" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('billing_street')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="billing_postal_code" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_billing_postal_code') }}</label>
                <input type="text" id="billing_postal_code" name="billing_postal_code" value="{{ old('billing_postal_code', $profile->billing_postal_code) }}" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('billing_postal_code')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="billing_city" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_billing_city') }}</label>
                <input type="text" id="billing_city" name="billing_city" value="{{ old('billing_city', $profile->billing_city) }}" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('billing_city')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
        </div>

        <p class="text-xs text-shop-muted">{{ __('restaurant.account_email_note', ['email' => $user->email]) }}</p>

        <button type="submit" data-testid="restaurant-account-save"
                class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
            {{ __('restaurant.account_save') }}
        </button>
    </form>

    {{-- ── Password ───────────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('app.restaurant.account.password') }}" class="shop-card mt-6 space-y-4 p-6">
        @csrf
        @method('PUT')
        <h2 class="font-display text-xl text-shop-foreground">{{ __('restaurant.account_password_title') }}</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="current_password" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_current_password') }}</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('current_password')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_new_password') }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                @error('password')<p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.field_new_password_confirm') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required
                       class="w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
            </div>
        </div>
        <button type="submit" data-testid="restaurant-password-save"
                class="inline-flex h-[3rem] items-center justify-center border border-shop-border px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
            {{ __('restaurant.password_save') }}
        </button>
    </form>

    {{-- ── Invoices shortcut ──────────────────────────────────────────── --}}
    <a href="{{ route('app.restaurant.invoices.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">
        {{ __('restaurant.invoices_title') }} &rarr;
    </a>
</x-shop-layout>
