<x-shop-layout :title="__('shop.checkout.details_heading')" nav-active="shop">
    @include('shop._partials.checkout-progress', ['active' => 2])

    <div class="bg-shop-background text-shop-foreground"
         x-data="{ mode: '{{ $draft['fulfillment_mode'] ?? 'pickup' }}', payment: '{{ $draft['payment_method'] ?? 'online' }}' }">
        <section class="mx-auto max-w-[1100px] px-6 pt-10 lg:px-12 lg:pt-14">
            <p class="eyebrow text-shop-primary">{{ __('shop.checkout.details_eyebrow') }}</p>
            <h1 class="mt-3 font-display text-3xl leading-tight sm:text-4xl">{{ __('shop.checkout.details_heading') }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-shop-muted">{{ __('shop.checkout.details_intro') }}</p>
        </section>

        @if (session('error'))
            <div class="mx-auto max-w-[1100px] px-6 pt-6 lg:px-12">
                <div class="border border-shop-border bg-shop-paper p-4 text-sm leading-relaxed text-shop-foreground" role="alert">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <div class="mx-auto grid max-w-[1100px] gap-10 px-6 py-10 lg:grid-cols-[1fr_22rem] lg:px-12 lg:py-14">
            <form
                method="POST"
                action="{{ route('public.shop.checkout.details.store') }}"
                data-testid="shop-checkout-details-form"
                class="space-y-12"
            >
                @csrf
                @php
                    $eyebrow = 'font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary';
                    $fieldLabel = 'font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted block';
                    $inputClass = 'mt-2 block w-full border border-shop-border bg-shop-background px-4 py-3 text-base text-shop-foreground placeholder:text-shop-muted/60 focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary';
                @endphp

                {{-- ── Fulfillment ───────────────────────────────────── --}}
                <section>
                    <header class="mb-5">
                        <p class="{{ $eyebrow }}">{{ __('shop.section_fulfillment') }}</p>
                    </header>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                               :class="mode === 'pickup' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'">
                            <input type="radio" name="fulfillment_mode" value="pickup" x-model="mode" class="sr-only">
                            <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.fulfillment_pickup_eyebrow') }}</span>
                            <span class="font-display text-2xl leading-tight">{{ __('shop.fulfillment_pickup') }}</span>
                            <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.fulfillment_pickup_hint') }}</span>
                        </label>
                        <label class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                               :class="mode === 'delivery' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'">
                            <input type="radio" name="fulfillment_mode" value="delivery" x-model="mode" class="sr-only">
                            <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.fulfillment_delivery_eyebrow') }}</span>
                            <span class="font-display text-2xl leading-tight">{{ __('shop.fulfillment_delivery') }}</span>
                            <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.fulfillment_delivery_hint', ['fee' => \App\Support\Money::format($deliveryFeeEur)]) }}</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-4" />
                </section>

                {{-- ── Pickup location (pickup) ──────────────────────── --}}
                <section x-show="mode === 'pickup'" x-cloak>
                    <header class="mb-5 flex flex-wrap items-end justify-between gap-4">
                        <p class="{{ $eyebrow }}">{{ __('shop.pickup_location_section') }}</p>
                        <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.pickup_location_hint') }}</p>
                    </header>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($pickupLocations as $key => $loc)
                            <label class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition border-shop-border hover:border-shop-primary">
                                <input type="radio" name="pickup_location" value="{{ $key }}" class="sr-only" @checked(old('pickup_location', $draft['pickup_location'] ?? '') === $key)>
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.pickup_location_eyebrow', ['index' => $loop->iteration]) }}</span>
                                <span class="font-display text-2xl leading-snug">{{ $loc['street'] }}</span>
                                <span class="text-sm leading-relaxed text-shop-muted">{{ $loc['postal_code'] }} {{ $loc['city'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('pickup_location')" class="mt-4" />
                </section>

                {{-- ── Payment (pickup only) ────────────────────────── --}}
                <section x-show="mode === 'pickup'" x-cloak>
                    <header class="mb-5">
                        <p class="{{ $eyebrow }}">{{ __('shop.section_payment') }}</p>
                    </header>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                               :class="payment === 'online' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'">
                            <input type="radio" name="payment_method" value="online" x-model="payment" class="sr-only">
                            <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.payment_online_eyebrow') }}</span>
                            <span class="font-display text-2xl leading-tight">{{ __('shop.payment_online') }}</span>
                            <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.payment_online_hint') }}</span>
                        </label>
                        <label class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                               :class="payment === 'in_person' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'">
                            <input type="radio" name="payment_method" value="in_person" x-model="payment" class="sr-only">
                            <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.payment_in_person_eyebrow') }}</span>
                            <span class="font-display text-2xl leading-tight">{{ __('shop.payment_in_person') }}</span>
                            <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.payment_in_person_hint') }}</span>
                        </label>
                    </div>
                    <template x-if="mode === 'delivery'">
                        <input type="hidden" name="payment_method" value="online">
                    </template>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-4" />
                </section>

                {{-- ── Contact ───────────────────────────────────────── --}}
                <section>
                    <header class="mb-5">
                        <p class="{{ $eyebrow }}">{{ __('shop.section_contact') }}</p>
                    </header>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="{{ $fieldLabel }}">{{ __('Name') }}</span>
                            <input type="text" name="name" value="{{ old('name', $draft['name'] ?? '') }}" required autocomplete="name" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </label>
                        <label class="block">
                            <span class="{{ $fieldLabel }}">{{ __('Email') }}</span>
                            <input type="email" name="email" value="{{ old('email', $draft['email'] ?? '') }}" required autocomplete="email" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </label>
                        <label class="block">
                            <span class="{{ $fieldLabel }}">
                                {{ __('Phone') }}<span class="ml-2 text-sm font-normal text-shop-muted" x-cloak><span x-show="mode === 'delivery'">{{ __('required') }}</span><span x-show="mode !== 'delivery'">({{ __('optional') }})</span></span>
                            </span>
                            <input type="tel" name="phone" value="{{ old('phone', $draft['phone'] ?? '') }}" autocomplete="tel" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </label>
                    </div>
                </section>

                {{-- ── Delivery address (delivery only) ──────────────── --}}
                <section x-show="mode === 'delivery'" x-cloak>
                    <header class="mb-5">
                        <p class="{{ $eyebrow }}">{{ __('shop.section_address') }}</p>
                    </header>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="{{ $fieldLabel }}">{{ __('shop.delivery_street') }}</span>
                            <input type="text" name="delivery_street" value="{{ old('delivery_street', $draft['delivery_street'] ?? '') }}" autocomplete="street-address" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
                        </label>
                        <label class="block">
                            <span class="{{ $fieldLabel }}">{{ __('shop.delivery_postal_code') }}</span>
                            <input type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code', $draft['delivery_postal_code'] ?? '') }}" autocomplete="postal-code" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                        </label>
                        <label class="block">
                            <span class="{{ $fieldLabel }}">{{ __('shop.delivery_city') }}</span>
                            <input type="text" name="delivery_city" value="{{ old('delivery_city', $draft['delivery_city'] ?? '') }}" autocomplete="address-level2" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('delivery_city')" class="mt-2" />
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="{{ $fieldLabel }}">{{ __('shop.delivery_notes') }} <span class="font-normal text-shop-muted">({{ __('optional') }})</span></span>
                            <textarea name="delivery_notes" rows="2" class="{{ $inputClass }}">{{ old('delivery_notes', $draft['delivery_notes'] ?? '') }}</textarea>
                            <x-input-error :messages="$errors->get('delivery_notes')" class="mt-2" />
                        </label>
                    </div>
                </section>

                {{-- ── Coupon code ────────────────────────────────────── --}}
                <section>
                    <header class="mb-5">
                        <p class="{{ $eyebrow }}">{{ __('coupon.field_label') }}</p>
                    </header>
                    <input
                        type="text"
                        name="coupon_code"
                        value="{{ old('coupon_code', $draft['coupon_code'] ?? '') }}"
                        placeholder="{{ __('coupon.field_placeholder') }}"
                        autocapitalize="characters"
                        class="{{ $inputClass }}"
                        data-testid="shop-checkout-coupon"
                    />
                    <x-input-error :messages="$errors->get('coupon_code')" class="mt-2" />
                </section>

                <div class="flex flex-wrap items-center gap-5 border-t border-shop-border pt-10">
                    <a href="{{ route('public.shop.index') }}"
                       class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-foreground hover:underline">
                        {{ __('shop.checkout.details_back') }}
                    </a>
                    <button
                        type="submit"
                        class="rounded-sm bg-shop-primary px-7 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover"
                        data-testid="shop-checkout-details-continue"
                    >{{ __('shop.checkout.details_continue') }}</button>
                </div>
            </form>

            <aside class="lg:sticky lg:top-10 lg:self-start">
                @include('shop._partials.order-summary', [
                    'lines' => $lines,
                    'editHref' => route('public.shop.index'),
                ])
            </aside>
        </div>
    </div>
</x-shop-layout>
