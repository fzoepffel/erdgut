@props([
    'box',
    'budgetDetails' => null,
    'bindEstimate' => false,
])

@php
    $itemCount = $box->items->sum(fn ($item) => (int) $item->quantity);
@endphp

<section
    class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm"
    aria-labelledby="box-budget-heading"
>
    <h3 id="box-budget-heading" class="text-sm font-semibold text-emerald-900">
        {{ __('Value & budget') }}
    </h3>
    <dl class="mt-3 space-y-2 text-sm">
        <div class="flex justify-between gap-3">
            <dt class="text-gray-600">{{ __('Items') }}</dt>
            <dd class="font-medium text-gray-900">{{ $itemCount }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-gray-600">{{ __('Estimated value') }}</dt>
            <dd class="font-medium text-gray-900">
                @if ($bindEstimate)
                    <span x-text="'EUR ' + estimatedValue.toFixed(2)"></span>
                @else
                    EUR {{ number_format((float) $box->estimated_value, 2) }}
                @endif
            </dd>
        </div>
        @if ($budgetDetails !== null)
            <div class="flex justify-between gap-3">
                <dt class="text-gray-600">{{ __('Included in subscription') }}</dt>
                <dd class="font-medium text-gray-900">
                    EUR {{ number_format($budgetDetails['included_value'], 2) }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-600">{{ __('Remaining budget') }}</dt>
                @if ($bindEstimate)
                    <dd
                        class="font-medium"
                        :class="includedValue !== null && (includedValue - estimatedValue) >= 0 ? 'text-emerald-700' : 'text-amber-600'"
                        x-text="formatRemainder()"
                    ></dd>
                @else
                    <dd
                        @class([
                            'font-medium',
                            'text-emerald-700' => $budgetDetails['remainder'] >= 0,
                            'text-amber-600' => $budgetDetails['remainder'] < 0,
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
