<x-shop-layout :title="__('shop.checkout.review_heading')" nav-active="shop">
    @include('shop._partials.checkout-progress', ['active' => 3])

    @php
        $deliveryDateLabel = isset($draft['expected_delivery_date'])
            ? \Illuminate\Support\Carbon::parse((string) $draft['expected_delivery_date'])
                ->locale(app()->getLocale())
                ->isoFormat('dddd, D. MMMM YYYY')
            : null;
        $mode = (string) ($draft['fulfillment_mode'] ?? 'pickup');
        $payment = (string) ($draft['payment_method'] ?? 'online');
        $pickupKey = $draft['pickup_location'] ?? null;
        $pickup = $pickupKey ? ($pickupLocations[$pickupKey] ?? null) : null;
        $eyebrow = 'font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary';
    @endphp

    <div class="bg-shop-background text-shop-foreground">
        <section class="mx-auto max-w-[1100px] px-6 pt-10 lg:px-12 lg:pt-14">
            <p class="eyebrow text-shop-primary">{{ __('shop.checkout.review_eyebrow') }}</p>
            <h1 class="mt-3 font-display text-3xl leading-tight sm:text-4xl">{{ __('shop.checkout.review_heading') }}</h1>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-shop-muted">{{ __('shop.checkout.review_intro') }}</p>
        </section>

        @if (session('error'))
            <div class="mx-auto max-w-[1100px] px-6 pt-6 lg:px-12">
                <div class="border border-shop-border bg-shop-paper p-4 text-sm leading-relaxed text-shop-foreground" role="alert">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        <div class="mx-auto grid max-w-[1100px] gap-10 px-6 py-10 lg:grid-cols-[1fr_22rem] lg:px-12 lg:py-14">
            <div class="space-y-10">
                {{-- ── Details review ────────────────────────────────── --}}
                <section class="border border-shop-border bg-shop-paper p-5">
                    <header class="flex items-center justify-between gap-3">
                        <p class="{{ $eyebrow }}">{{ __('shop.checkout.details_heading') }}</p>
                        <a href="{{ route('public.shop.checkout.details') }}"
                           class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline"
                           data-testid="shop-checkout-review-edit-details">
                            {{ __('shop.checkout.review_edit_details') }}
                        </a>
                    </header>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        @if ($deliveryDateLabel)
                            <div>
                                <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_delivery_date') }}</dt>
                                <dd class="mt-1 text-shop-foreground">{{ $deliveryDateLabel }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_fulfillment') }}</dt>
                            <dd class="mt-1 text-shop-foreground">
                                {{ $mode === 'delivery' ? __('shop.fulfillment_delivery') : __('shop.fulfillment_pickup') }}
                            </dd>
                        </div>
                        @if ($mode === 'pickup' && $pickup)
                            <div class="sm:col-span-2">
                                <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_pickup') }}</dt>
                                <dd class="mt-1 text-shop-foreground">
                                    {{ $pickup['name'] ?? '' }}<br>
                                    <span class="text-shop-muted">{{ $pickup['street'] ?? '' }}, {{ $pickup['postal_code'] ?? '' }} {{ $pickup['city'] ?? '' }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($mode === 'delivery')
                            <div class="sm:col-span-2">
                                <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_address') }}</dt>
                                <dd class="mt-1 text-shop-foreground">
                                    {{ $draft['delivery_street'] ?? '' }}<br>
                                    <span class="text-shop-muted">{{ $draft['delivery_postal_code'] ?? '' }} {{ $draft['delivery_city'] ?? '' }}</span>
                                </dd>
                            </div>
                            @if (! empty($draft['delivery_notes']))
                                <div class="sm:col-span-2">
                                    <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_notes') }}</dt>
                                    <dd class="mt-1 whitespace-pre-line text-shop-foreground">{{ $draft['delivery_notes'] }}</dd>
                                </div>
                            @endif
                        @endif
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_payment') }}</dt>
                            <dd class="mt-1 text-shop-foreground">
                                {{ $payment === 'in_person' ? __('shop.payment_in_person') : __('shop.payment_online') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.checkout.review_section_contact') }}</dt>
                            <dd class="mt-1 text-shop-foreground">
                                {{ $draft['name'] ?? '' }}<br>
                                <span class="text-shop-muted">{{ $draft['email'] ?? '' }}</span>
                                @if (! empty($draft['phone']))
                                    <br><span class="text-shop-muted">{{ $draft['phone'] }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>

                {{-- ── Consent + submit ──────────────────────────────── --}}
                <form
                    method="POST"
                    action="{{ route('public.shop.checkout.place') }}"
                    data-testid="shop-checkout-review-form"
                    class="border border-shop-border bg-shop-paper p-5"
                >
                    @csrf
                    <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-shop-muted">
                        <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 accent-shop-primary" data-testid="shop-checkout-consent">
                        <span>
                            {!! __('shop.consent_text', ['privacy' => '<a href="'.route('public.privacy').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_privacy_link')).'</a>', 'terms' => '<a href="'.route('public.shop.terms').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_terms_link')).'</a>']) !!}
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('consent')" class="mt-2" />

                    <div class="mt-8 flex flex-wrap items-center gap-5">
                        <a href="{{ route('public.shop.checkout.details') }}"
                           class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-foreground hover:underline">
                            {{ __('shop.checkout.review_back') }}
                        </a>
                        <button
                            type="submit"
                            class="rounded-sm bg-shop-primary px-7 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover"
                            data-testid="shop-checkout-place"
                        >
                            {{ $payment === 'in_person' ? __('shop.checkout.review_submit_in_person') : __('shop.checkout.review_submit_online') }}
                        </button>
                    </div>
                </form>
            </div>

            <aside class="lg:sticky lg:top-10 lg:self-start">
                @include('shop._partials.order-summary', [
                    'lines' => $lines,
                    'editHref' => route('public.shop.index'),
                ])
            </aside>
        </div>
    </div>
</x-shop-layout>
