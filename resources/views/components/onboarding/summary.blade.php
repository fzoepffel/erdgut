{{-- W2-17: progressive draft summary that replaces the empty "DEINE BOX"
     placeholder. As the customer fills each wizard step, the matching block
     appears here so they can self-review before handing over their card.
     The price line is the same number the calculator returns everywhere
     (W2-16). Each block carries a small Edit link back to its step. --}}
@props([
    'draft' => null,
    'user' => null,
])

@php
    use App\Services\Billing\BoxPriceCalculator;
    use App\Services\Onboarding\OnboardingDraftManager;

    /** @var \App\Models\OnboardingDraft|null $draft */
    /** @var \App\Models\User|null $user */
    $data = $draft?->data ?? [];
    $resolvedUser = $user ?? $draft?->user;
    $address = isset($data['address_id'])
        ? \App\Models\Address::find($data['address_id'])
        : null;

    $boxSize = is_string($data['box_size'] ?? null) ? (string) $data['box_size'] : null;
    $eggsAddOn = (bool) data_get($data, 'preferences.eggs_dairy_add_on', false);
    $allergies = (array) ($data['allergies'] ?? []);

    $isTrial = $draft?->intent === OnboardingDraftManager::INTENT_TRIAL;

    $perDelivery = $boxSize !== null
        ? ($isTrial
            ? app(BoxPriceCalculator::class)->trialOneOff($boxSize, $eggsAddOn)
            : app(BoxPriceCalculator::class)->subscriptionPerDelivery($boxSize, $eggsAddOn))
        : null;

    $sizeApproxCount = match ($boxSize) {
        'small' => 6,
        'medium' => 11,
        'large' => 15,
        default => null,
    };

    $plan = is_string($data['plan'] ?? null) ? (string) $data['plan'] : null;
    $deliveryWeekday = $data['delivery_weekday'] ?? null;
    $preferredTimeslot = is_string($data['preferred_timeslot'] ?? null)
        ? (string) $data['preferred_timeslot']
        : null;
    $fulfillmentMode = is_string($data['fulfillment_mode'] ?? null)
        ? (string) $data['fulfillment_mode']
        : null;

    $weekdayLabels = [
        1 => __('Monday'),
        2 => __('Tuesday'),
        3 => __('Wednesday'),
        4 => __('Thursday'),
        5 => __('Friday'),
        6 => __('Saturday'),
        7 => __('Sunday'),
    ];

    $accountFilled = ($data['account_completed'] ?? false) && $resolvedUser !== null;
    $addressFilled = ! empty($data['address_completed']) || $address !== null;
    $boxFilled = ! empty($data['preferences_completed']) || $boxSize !== null;
    $scheduleFilled = $plan !== null || $preferredTimeslot !== null;

    $anyFilled = $accountFilled || $addressFilled || $boxFilled || $scheduleFilled;
@endphp

<div class="sticky top-6 space-y-5 bg-brand-paper p-6 text-sm text-brand-ink/80">
    <p class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce">{{ __('onboarding.summary.kicker') }}</p>
    <p class="-mt-3 font-display text-2xl text-brand-ink">{{ __('Your details') }}</p>

    @if (! $anyFilled)
        <p class="text-xs text-brand-ink/60">{{ __('We will keep a running summary of everything you tell us as you go.') }}</p>
    @endif

    @if ($accountFilled)
        <section data-testid="onboarding-summary-account">
            <div class="flex items-baseline justify-between gap-3">
                <p class="eyebrow text-brand-ink/60">{{ __('Your account') }}</p>
                <a href="{{ route('app.onboarding.step.account') }}" class="text-xs text-brand-ink/60 underline-offset-4 hover:text-brand-ink hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>
            <p class="mt-1 font-medium text-brand-ink">{{ $resolvedUser->name ?: '—' }}</p>
            <p class="text-xs text-brand-ink/65">{{ $resolvedUser->email }}</p>
        </section>
    @endif

    @if ($addressFilled && $address !== null && $fulfillmentMode !== 'pickup')
        <section data-testid="onboarding-summary-address">
            <div class="flex items-baseline justify-between gap-3">
                <p class="eyebrow text-brand-ink/60">{{ __('Your delivery address') }}</p>
                <a href="{{ route('app.onboarding.step.address') }}" class="text-xs text-brand-ink/60 underline-offset-4 hover:text-brand-ink hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>
            <p class="mt-1 text-brand-ink">
                {{ $address->street_line_1 }}@if ($address->street_line_2), {{ $address->street_line_2 }}@endif
            </p>
            <p class="text-xs text-brand-ink/65">{{ $address->postal_code }} {{ $address->city }}</p>
        </section>
    @endif

    @if ($fulfillmentMode === 'pickup')
        @php
            $pickup = config('box.pickup_location');
        @endphp
        <section data-testid="onboarding-summary-pickup">
            <div class="flex items-baseline justify-between gap-3">
                <p class="eyebrow text-chapter-bread">{{ __('Pickup location') }}</p>
                <a href="{{ route('app.onboarding.step.subscription_schedule') }}" class="text-xs text-brand-ink/60 underline-offset-4 hover:text-brand-ink hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>
            <p class="mt-1 font-medium text-brand-ink">{{ $pickup['name'] }}</p>
            <p class="text-xs text-brand-ink/65">{{ $pickup['street'] }} · {{ $pickup['postal_code'] }} {{ $pickup['city'] }}</p>
            <p class="text-xs italic text-brand-ink/65">{{ $pickup['window'] }}</p>
        </section>
    @endif

    @if ($boxFilled && $boxSize !== null)
        <section data-testid="onboarding-summary-box">
            <div class="flex items-baseline justify-between gap-3">
                <p class="eyebrow text-brand-ink/60">{{ __('Your box') }}</p>
                <a href="{{ route('app.onboarding.step.preferences') }}" class="text-xs text-brand-ink/60 underline-offset-4 hover:text-brand-ink hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>
            <p class="mt-1 font-medium text-brand-ink">
                {{ __(ucfirst($boxSize)) }}
                @if ($sizeApproxCount !== null)
                    <span class="text-xs text-brand-ink/65">· ~{{ $sizeApproxCount }} {{ __('items') }}</span>
                @endif
            </p>
            @if ($perDelivery !== null)
                <p class="text-xs text-brand-ink/65">
                    {{ $isTrial
                        ? __('EUR :amount once', ['amount' => \App\Support\Money::format($perDelivery)])
                        : __('EUR :amount / delivery', ['amount' => \App\Support\Money::format($perDelivery)]) }}
                </p>
            @endif
            @if ($eggsAddOn)
                <span class="mt-2 inline-block bg-chapter-eggdairy/10 px-2 py-0.5 text-[11px] font-medium text-chapter-eggdairy">
                    {{ __('Eggs add-on') }}
                </span>
            @endif
            @if ($allergies !== [])
                <p class="mt-2 text-[11px] text-brand-ink/65">
                    {{ __('Allergies') }}: {{ implode(', ', array_map(fn ($a) => __('allergens.'.$a), $allergies)) }}
                </p>
            @endif
        </section>
    @endif

    @if ($scheduleFilled)
        <section data-testid="onboarding-summary-schedule">
            <div class="flex items-baseline justify-between gap-3">
                <p class="eyebrow text-brand-ink/60">{{ __('Your schedule') }}</p>
                <a href="{{ route('app.onboarding.step.subscription_schedule') }}" class="text-xs text-brand-ink/60 underline-offset-4 hover:text-brand-ink hover:underline">
                    {{ __('Edit') }}
                </a>
            </div>
            @if ($plan !== null)
                <p class="mt-1 font-medium text-brand-ink">
                    {{ $plan === 'weekly' ? __('Every week') : __('Every two weeks') }}
                </p>
            @endif
            @if ($fulfillmentMode === 'pickup')
                {{-- Pickup mode: weekday/timeslot don't apply (the pickup
                     location's open window above already says when). --}}
                <p class="text-xs text-brand-ink/65">{{ __('Pickup') }}</p>
            @else
                @if ($deliveryWeekday !== null && isset($weekdayLabels[(int) $deliveryWeekday]))
                    <p class="text-xs text-brand-ink/65">{{ $weekdayLabels[(int) $deliveryWeekday] }}</p>
                @endif
                @if ($preferredTimeslot !== null)
                    <p class="text-xs text-brand-ink/65">{{ $preferredTimeslot }}</p>
                @endif
                @if ($fulfillmentMode !== null)
                    <p class="text-xs text-brand-ink/65">{{ __('Home delivery') }}</p>
                @endif
            @endif
        </section>
    @endif
</div>
