<x-shop-layout :title="__('restaurant.contact_title')" :narrow="true" restaurant-nav="contact">
    <div>
        <p class="eyebrow text-shop-primary">{{ __('restaurant.nav_contact') }}</p>
        <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.contact_title') }}</h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-shop-muted">{{ __('restaurant.contact_intro') }}</p>
    </div>

    @if (session('status') === 'restaurant-contact-submitted')
        <div class="mt-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900" data-testid="restaurant-contact-flash">
            {{ __('restaurant.contact_flash') }}
        </div>
    @endif

    <form method="POST" action="{{ route('app.restaurant.contact.store') }}" class="shop-card mt-8 space-y-5 p-6">
        @csrf

        {{-- The sender comes from the account, nothing to type or get wrong. --}}
        <div class="border border-shop-border bg-shop-card px-4 py-3 text-sm">
            <p class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.contact_from_label') }}</p>
            <p class="mt-1 text-shop-foreground">{{ $profile?->business_name }}</p>
            <p class="text-xs text-shop-muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
        </div>

        <div>
            <label for="subject" class="mb-1 block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.contact_subject_label') }}</label>
            <input type="text" id="subject" name="subject" value="{{ old('subject') }}" maxlength="255"
                   class="block w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
            <x-input-error :messages="$errors->get('subject')" class="mt-1" />
        </div>

        <div>
            <label for="message" class="mb-1 block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.contact_message_label') }}</label>
            <textarea id="message" name="message" rows="7" required maxlength="5000" data-testid="restaurant-contact-message"
                      class="block w-full border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">{{ old('message') }}</textarea>
            <x-input-error :messages="$errors->get('message')" class="mt-1" />
        </div>

        <div class="flex justify-end">
            <button type="submit" data-testid="restaurant-contact-submit"
                    class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.contact_submit') }}
            </button>
        </div>
    </form>
</x-shop-layout>
