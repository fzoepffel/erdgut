@props(['step' => 'account', 'intent' => 'undecided'])

@php
    $sharedSteps = [
        ['id' => 'account', 'label' => __('Account')],
        ['id' => 'address', 'label' => __('Address')],
        ['id' => 'preferences', 'label' => __('Preferences')],
        ['id' => 'commitment', 'label' => __('Commitment')],
    ];

    if ($intent === 'trial') {
        $sharedSteps[] = ['id' => 'trial_delivery', 'label' => __('Delivery')];
        $sharedSteps[] = ['id' => 'trial_payment', 'label' => __('Payment')];
    } elseif ($intent === 'subscription') {
        $sharedSteps[] = ['id' => 'subscription_schedule', 'label' => __('Schedule')];
        $sharedSteps[] = ['id' => 'subscription_payment', 'label' => __('Payment')];
    }

    $currentIndex = array_search($step, array_column($sharedSteps, 'id'), true);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }
@endphp

<ol class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs uppercase tracking-eyebrow text-brand-ink/55" role="list" aria-label="{{ __('Onboarding progress') }}">
    @foreach ($sharedSteps as $i => $s)
        <li class="flex items-center gap-2">
            <span
                @class([
                    'flex h-6 w-6 items-center justify-center border font-display text-[0.7rem] font-semibold leading-none',
                    'border-chapter-produce bg-chapter-produce text-white' => $i < $currentIndex,
                    'border-brand-ink bg-brand-ink text-brand-cream' => $i === $currentIndex,
                    'border-brand-ink/25 text-brand-ink/40' => $i > $currentIndex,
                ])
            >
                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
            </span>
            <span class="{{ $i === $currentIndex ? 'font-semibold text-brand-ink' : '' }}">{{ $s['label'] }}</span>
            @if (! $loop->last)<span class="text-brand-ink/25" aria-hidden="true">·</span>@endif
        </li>
    @endforeach
</ol>
