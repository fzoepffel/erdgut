<x-shop-layout :title="__('restaurant.review_title')" :narrow="true">
    <p class="eyebrow text-shop-primary">{{ __('restaurant.review_eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.review_title') }}</h1>

    <dl class="shop-card mt-8 divide-y divide-shop-border">
        @php
            $rows = [
                'field_business_name' => $profile->business_name,
                'field_contact_name' => $profile->contact_name,
                'field_email' => $profile->user?->email,
                'field_phone' => $profile->user?->phone,
                'field_handelsregisternummer' => $profile->handelsregisternummer,
                'field_ust_id_nr' => $profile->ust_id_nr,
                'field_billing_street' => $profile->billing_street,
                'field_billing_postal_code' => $profile->billing_postal_code,
                'field_billing_city' => $profile->billing_city,
            ];
        @endphp
        @foreach ($rows as $labelKey => $value)
            <div class="flex gap-4 px-5 py-3">
                <dt class="w-2/5 font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.'.$labelKey) }}</dt>
                <dd class="flex-1 text-sm text-shop-foreground">{{ $value ?: '—' }}</dd>
            </div>
        @endforeach
    </dl>

    @if (! $profile->isPending())
        <p class="mt-6 border border-shop-border bg-shop-background p-4 text-sm text-shop-muted">
            {{ __('restaurant.review_already_processed') }}
        </p>
    @else
        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <form method="POST" action="{{ $verifyUrl }}">
                @csrf
                <button type="submit" class="w-full inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                    {{ __('restaurant.review_verify') }}
                </button>
            </form>

            <form method="POST" action="{{ $rejectUrl }}" x-data="{ confirming: false }"
                  @submit="if (! confirming) { $event.preventDefault(); confirming = true; }">
                @csrf
                <label class="block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.review_reason_label') }}</label>
                <input type="text" name="reason" maxlength="500"
                       class="mt-1 mb-3 block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-chapter-meat focus:outline-none">
                <button type="submit" class="w-full inline-flex h-[3rem] items-center justify-center border border-chapter-meat px-6 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-meat transition hover:bg-chapter-meat hover:text-white">
                    <span x-show="! confirming">{{ __('restaurant.review_reject') }}</span>
                    <span x-show="confirming" x-cloak>{{ __('restaurant.review_reject_confirm') }}</span>
                </button>
            </form>
        </div>
    @endif
</x-shop-layout>
