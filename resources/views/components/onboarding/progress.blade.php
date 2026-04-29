@props(['step' => 'account', 'intent' => 'undecided'])

@php
    // W2-08: the progress indicator collapses to four visible segments —
    // Konto · Adresse · Box · Zahlung — to match the homepage's "4 steps"
    // promise. Internally the wizard still has up to six actual steps (the
    // 'Box' segment encompasses preferences, commitment, schedule/delivery);
    // we just don't expose that to the customer's eye.
    //
    // Each visible segment lists the actual step IDs that map to it — the
    // segment is highlighted whenever the current $step is in its match set.
    // Order matters: the first segment whose match set contains $step is
    // treated as the current one (everything before is 'completed', after is
    // 'pending').
    $visibleSegments = [
        [
            'label' => __('Account'),
            'chapter' => 'produce',
            'match' => ['account'],
        ],
        [
            'label' => __('Address'),
            'chapter' => 'producers',
            'match' => ['address'],
        ],
        [
            'label' => __('Box'),
            'chapter' => 'eggdairy',
            'match' => ['preferences', 'commitment', 'subscription_schedule', 'trial_delivery'],
        ],
        [
            'label' => __('Payment'),
            'chapter' => 'meat',
            'match' => ['subscription_payment', 'trial_payment'],
        ],
    ];

    $currentIndex = 0;
    foreach ($visibleSegments as $i => $seg) {
        if (in_array($step, $seg['match'], true)) {
            $currentIndex = $i;
            break;
        }
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

<ol class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs uppercase tracking-eyebrow text-brand-ink/55" role="list" aria-label="{{ __('Onboarding progress') }}" data-testid="onboarding-progress">
    @foreach ($visibleSegments as $i => $seg)
        <li class="flex items-center gap-2">
            <span
                @class([
                    'flex h-6 w-6 items-center justify-center border font-display text-[0.7rem] font-semibold leading-none',
                    $completedClasses[$seg['chapter']] ?? $completedClasses['produce'] => $i < $currentIndex,
                    'border-brand-ink bg-brand-ink text-brand-cream' => $i === $currentIndex,
                    'border-brand-ink/25 text-brand-ink/40' => $i > $currentIndex,
                ])
            >
                {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
            </span>
            <span class="{{ $i === $currentIndex ? 'font-semibold text-brand-ink' : '' }}">{{ $seg['label'] }}</span>
            @if (! $loop->last)<span class="text-brand-ink/25" aria-hidden="true">·</span>@endif
        </li>
    @endforeach
</ol>
