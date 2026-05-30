@props(['step' => 'address', 'intent' => 'subscription'])

@php
    // Customer-facing stepper rail. Konto is deliberately not shown — the
    // account step belonged to a separate (pre-)flow; the wizard the
    // customer walks here is four steps: Adresse · Box · Zeitplan · Zahlung.
    // The trial path swaps "Zeitplan" for "Lieferung" on step 3.
    //
    // Each segment lists the internal step IDs that map to it; the segment is
    // "current" when $step is in its match set, "done" for earlier segments,
    // "pending" for later ones. The account step highlights nothing (it sits
    // before segment one).
    $isTrial = $intent === \App\Services\Onboarding\OnboardingDraftManager::INTENT_TRIAL;

    $segments = [
        [
            'label' => __('onboarding.step.address'),
            'match' => ['address'],
        ],
        [
            'label' => __('onboarding.step.box'),
            'match' => ['preferences', 'commitment'],
        ],
        [
            'label' => $isTrial ? __('onboarding.step.delivery') : __('onboarding.step.schedule'),
            'match' => ['subscription_schedule', 'trial_delivery'],
        ],
        [
            'label' => __('onboarding.step.payment'),
            'match' => ['subscription_payment', 'trial_payment'],
        ],
    ];

    $currentIndex = -1;
    foreach ($segments as $i => $seg) {
        if (in_array($step, $seg['match'], true)) {
            $currentIndex = $i;
            break;
        }
    }
@endphp

<ol class="grid grid-cols-2 sm:grid-cols-4" role="list" aria-label="{{ __('Onboarding progress') }}" data-testid="onboarding-progress">
    @foreach ($segments as $i => $seg)
        @php
            $state = $currentIndex === -1 ? 'pending' : ($i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'pending'));
        @endphp
        <li
            @class([
                'border-t-2 py-5 pr-6',
                'border-brand-ink' => $state === 'current',
                'border-chapter-produce' => $state === 'done',
                'border-brand-ink/15' => $state === 'pending',
            ])
            @if ($state === 'current') aria-current="step" @endif
        >
            <span class="font-mono text-[0.65rem] uppercase tracking-widest {{ $state === 'pending' ? 'text-brand-ink/35' : 'text-chapter-produce' }}">
                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
            </span>
            <p class="mt-2 font-display text-lg {{ $state === 'current' ? 'text-brand-ink' : 'text-brand-ink/55' }}">
                {{ $seg['label'] }}
                @if ($state === 'done')
                    <span class="font-mono text-[0.7rem] uppercase tracking-widest text-chapter-produce">· {{ __('onboarding.stepper.done') }}</span>
                @elseif ($state === 'current')
                    <span class="font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/45">· {{ __('onboarding.stepper.now') }}</span>
                @endif
            </p>
        </li>
    @endforeach
</ol>
