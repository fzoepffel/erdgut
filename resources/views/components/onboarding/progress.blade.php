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

<ol class="flex flex-wrap items-center gap-2 text-xs text-brand-ink/70" role="list" aria-label="{{ __('Onboarding progress') }}">
    @foreach ($sharedSteps as $i => $s)
        <li class="flex items-center gap-1">
            <span class="w-6 h-6 flex items-center justify-center rounded-full {{ $i < $currentIndex ? 'bg-category-produce text-white' : ($i === $currentIndex ? 'bg-brand-ink text-white' : 'bg-brand-ink/10') }}">{{ $i + 1 }}</span>
            <span class="{{ $i === $currentIndex ? 'font-semibold text-brand-ink' : '' }}">{{ $s['label'] }}</span>
            @if (! $loop->last)<span class="mx-2 text-brand-ink/30">›</span>@endif
        </li>
    @endforeach
</ol>
