@props([
    'intent' => 'trial', // trial | subscription
])
@php
    $steps = [
        [
            'number' => '1',
            'label' => __('Enter card details'),
            'detail' => __('30 seconds. Your card number never leaves SumUp.'),
            'state' => 'active',
        ],
        [
            'number' => '2',
            'label' => __('Confirm with your bank'),
            'detail' => __('Approve via SMS, app, or bank popup (3-D Secure).'),
            'state' => 'next',
        ],
        [
            'number' => '3',
            // C8 / D2 (UX notes): older copy promised a Monday ship +
            // "first delivery the following Monday", but the only
            // active delivery slot is Wed 12-16. Reframe to match
            // reality without hard-coding a date — operations confirm
            // the exact date out-of-band per delivery.
            'label' => $intent === 'trial'
                ? __('Your trial box is on the way')
                : __('Your subscription is live'),
            'detail' => $intent === 'trial'
                ? __('We confirm the exact Wednesday delivery and pack the box the day before.')
                : __('First delivery on the next eligible Wednesday.'),
            'state' => 'next',
        ],
    ];
@endphp

<aside
    class="mb-6 border border-brand-ink/15 bg-brand-paper/60 p-5"
    role="region"
    aria-labelledby="payment-expectations-heading"
>
    <h2 id="payment-expectations-heading" class="eyebrow text-chapter-produce">
        {{ __('What happens next') }}
    </h2>
    <ol class="mt-3 grid gap-3 sm:grid-cols-3" role="list">
        @foreach ($steps as $s)
            <li class="flex items-start gap-3" @if ($s['state'] === 'active') aria-current="step" @endif>
                <span
                    @class([
                        'flex h-6 w-6 shrink-0 items-center justify-center border font-display text-[0.7rem] font-semibold leading-none',
                        'border-brand-ink bg-brand-ink text-brand-cream' => $s['state'] === 'active',
                        'border-brand-ink/25 text-brand-ink/40' => $s['state'] !== 'active',
                    ])
                >{{ $s['number'] }}</span>
                <span class="flex flex-col text-sm leading-snug">
                    <span class="font-semibold text-brand-ink">{{ $s['label'] }}</span>
                    <span class="text-xs text-brand-ink/65">{{ $s['detail'] }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</aside>
