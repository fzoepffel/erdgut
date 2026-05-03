<x-guest-layout>
    @php
        // W2-20: by the time the customer lands here their OnboardingDraft is
        // already cleared (PaymentStepController::storeComplete on success).
        // Load the freshly-created subscription + delivery context directly
        // from the User so the page can confirm what was just set up instead
        // of leaving the customer wondering whether anything stuck.
        /** @var \App\Models\User|null $authedUser */
        $authedUser = auth()->user();
        $subscription = $authedUser?->subscriptions()->latest('id')->first();
        $eggsAddOn = (bool) data_get($subscription?->preferences, 'eggs_dairy_add_on', false);
        $fulfillmentMode = $subscription?->fulfillment_mode?->value;
        $isPickup = $fulfillmentMode === \App\Enums\FulfillmentMode::Pickup->value;
        $address = $isPickup ? null : $authedUser?->primaryAddress;
        $pickup = config('box.pickup_location');
        $firstDelivery = $subscription?->firstExpectedDeliveryDate();
    @endphp

    <div class="mb-6">
        <p class="eyebrow text-chapter-produce">{{ __('Verify email') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink">{{ __('Almost there.') }}</h1>
        <p class="mt-4 text-sm leading-relaxed text-brand-ink/75">
            {{ __('Thanks for signing up! Please verify your email using the link we sent. If it’s missing, check spam — then you can resend below.') }}
        </p>
    </div>

    @if ($subscription !== null)
        {{-- W2-20: confirmation block. The customer just handed over a card —
             show them what they bought (subscription details, address /
             pickup location, first delivery date) so the verify-email gate
             feels like the last optional step before food shows up, not a
             roadblock that erased everything they did. --}}
        <section class="mb-6 border border-chapter-produce/30 bg-chapter-produce/5 p-5 space-y-4 text-sm" data-testid="verify-email-confirmation">
            <p class="eyebrow text-chapter-produce">{{ __('What we just set up') }}</p>

            <div>
                <p class="eyebrow text-brand-ink/60">{{ __('Your box') }}</p>
                <p class="mt-1 font-medium text-brand-ink">
                    {{ __(ucfirst($subscription->box_size)) }} ·
                    {{ $subscription->plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                </p>
                <p class="text-xs text-brand-ink/65">
                    {{ __('EUR :amount / delivery', ['amount' => \App\Support\Money::format($subscription->expectedChargeAmount())]) }}
                    @if ($eggsAddOn)
                        · {{ __('Eggs add-on') }}
                    @endif
                </p>
            </div>

            @if ($isPickup)
                <div>
                    <p class="eyebrow text-chapter-bread">{{ __('Pickup location') }}</p>
                    <p class="mt-1 font-medium text-brand-ink">{{ $pickup['name'] }}</p>
                    <p class="text-xs text-brand-ink/65">{{ $pickup['street'] }} · {{ $pickup['postal_code'] }} {{ $pickup['city'] }}</p>
                    <p class="text-xs italic text-brand-ink/65">{{ $pickup['window'] }}</p>
                </div>
            @elseif ($address !== null)
                <div>
                    <p class="eyebrow text-brand-ink/60">{{ __('Your delivery address') }}</p>
                    <p class="mt-1 text-brand-ink">
                        {{ $address->street_line_1 }}@if ($address->street_line_2), {{ $address->street_line_2 }}@endif
                    </p>
                    <p class="text-xs text-brand-ink/65">{{ $address->postal_code }} {{ $address->city }}</p>
                </div>
            @endif

            @if ($firstDelivery !== null)
                <div>
                    <p class="eyebrow text-brand-ink/60">{{ __('First delivery') }}</p>
                    <p class="mt-1 font-medium text-brand-ink">
                        {{ $firstDelivery->locale(app()->getLocale())->translatedFormat('l, j. F Y') }}
                    </p>
                </div>
            @endif
        </section>
    @endif

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
