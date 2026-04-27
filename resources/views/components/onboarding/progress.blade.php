@props(['step' => 'account', 'intent' => 'undecided'])

@php
    // Each onboarding step is a chapter page — the dots cycle through the
    // catalogue palette so the wizard feels like turning pages rather than
    // filling a form.
    $sharedSteps = [
        ['id' => 'account', 'label' => __('Account'), 'chapter' => 'produce'],
        ['id' => 'address', 'label' => __('Address'), 'chapter' => 'producers'],
        ['id' => 'preferences', 'label' => __('Preferences'), 'chapter' => 'eggdairy'],
        ['id' => 'commitment', 'label' => __('Commitment'), 'chapter' => 'almanach'],
    ];

    if ($intent === 'trial') {
        $sharedSteps[] = ['id' => 'trial_delivery', 'label' => __('Delivery'), 'chapter' => 'fish'];
        $sharedSteps[] = ['id' => 'trial_payment', 'label' => __('Payment'), 'chapter' => 'meat'];
    } elseif ($intent === 'subscription') {
        $sharedSteps[] = ['id' => 'subscription_schedule', 'label' => __('Schedule'), 'chapter' => 'fish'];
        $sharedSteps[] = ['id' => 'subscription_payment', 'label' => __('Payment'), 'chapter' => 'meat'];
    } else {
        // Intent not yet chosen — show generic last two steps so the user
        // knows the journey has 6 steps total, not 4.
        $sharedSteps[] = ['id' => '__delivery', 'label' => __('Delivery'), 'chapter' => 'fish'];
        $sharedSteps[] = ['id' => '__payment', 'label' => __('Payment'), 'chapter' => 'meat'];
    }

    $currentIndex = array_search($step, array_column($sharedSteps, 'id'), true);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }

    // Per-chapter tailwind class maps — literal classes so Tailwind's JIT
    // picks them up at build time.
    $completedClasses = [
        'produce' => 'border-chapter-produce bg-chapter-produce text-white',
        'producers' => 'border-chapter-producers bg-chapter-producers text-white',
        'eggdairy' => 'border-chapter-eggdairy bg-chapter-eggdairy text-white',
        'almanach' => 'border-chapter-almanach bg-chapter-almanach text-white',
        'fish' => 'border-chapter-fish bg-chapter-fish text-white',
        'meat' => 'border-chapter-meat bg-chapter-meat text-white',
    ];
@endphp

<ol class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs uppercase tracking-eyebrow text-brand-ink/55" role="list" aria-label="{{ __('Onboarding progress') }}">
    @foreach ($sharedSteps as $i => $s)
        <li class="flex items-center gap-2">
            <span
                @class([
                    'flex h-6 w-6 items-center justify-center border font-display text-[0.7rem] font-semibold leading-none',
                    $completedClasses[$s['chapter']] ?? $completedClasses['produce'] => $i < $currentIndex,
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
