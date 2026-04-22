@props([
    'box',
    'budgetDetails' => null,
    'bindEstimate' => false,
])

@php
    $itemCount = $box->items->sum(fn ($item) => (int) $item->quantity);
@endphp

<section
    class="border border-brand-ink/15 bg-brand-ink/[0.02] p-6"
    aria-labelledby="box-budget-heading"
>
    <p class="eyebrow text-chapter-produce">{{ __('Value & budget') }}</p>
    <h3 id="box-budget-heading" class="mt-2 font-display text-xl text-brand-ink">
        {{ __('Weekly totals') }}
    </h3>
    <dl class="mt-5 space-y-3 border-t border-brand-ink/15 pt-4 text-sm">
        <div class="flex justify-between gap-3">
            <dt class="text-brand-ink/70">{{ __('Items') }}</dt>
            <dd class="font-medium text-brand-ink">{{ $itemCount }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-brand-ink/70">{{ __('Estimated value') }}</dt>
            <dd class="font-medium text-brand-ink">
                @if ($bindEstimate)
                    <span x-text="'EUR ' + estimatedValue.toFixed(2)"></span>
                @else
                    EUR {{ number_format((float) $box->estimated_value, 2) }}
                @endif
            </dd>
        </div>
        @if ($budgetDetails !== null)
            <div class="flex justify-between gap-3">
                <dt class="text-brand-ink/70">{{ __('Included in subscription') }}</dt>
                <dd class="font-medium text-brand-ink">
                    EUR {{ number_format($budgetDetails['included_value'], 2) }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-brand-ink/70">{{ __('Remaining budget') }}</dt>
                @if ($bindEstimate)
                    <dd
                        class="font-medium"
                        :class="includedValue !== null && (includedValue - estimatedValue) >= 0 ? 'text-chapter-produce' : 'text-chapter-meat'"
                        x-text="formatRemainder()"
                    ></dd>
                @else
                    <dd
                        @class([
                            'font-medium',
                            'text-chapter-produce' => $budgetDetails['remainder'] >= 0,
                            'text-chapter-meat' => $budgetDetails['remainder'] < 0,
                        ])
                    >
                        @if ($budgetDetails['remainder'] >= 0)
                            EUR {{ number_format($budgetDetails['remainder'], 2) }}
                        @else
                            {{ number_format(abs($budgetDetails['remainder']), 2) }} {{ __('over') }}
                        @endif
                    </dd>
                @endif
            </div>
        @endif
    </dl>
</section>
