@props(['box', 'budgetRemainder' => null])

@php
    $itemCount = $box->items->sum(fn ($item) => (int) $item->quantity);
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <h3 class="text-sm font-semibold text-gray-900">{{ __('My box') }}</h3>
    <dl class="mt-3 space-y-1 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-600">{{ __('Items') }}</dt>
            <dd class="font-medium text-gray-900">{{ $itemCount }}</dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-600">{{ __('Estimated value') }}</dt>
            <dd class="font-medium text-gray-900">EUR {{ number_format((float) $box->estimated_value, 2) }}</dd>
        </div>
        @if ($budgetRemainder !== null)
            <div class="flex justify-between">
                <dt class="text-gray-600">{{ __('Remaining budget') }}</dt>
                <dd class="font-medium {{ $budgetRemainder >= 0 ? 'text-emerald-700' : 'text-amber-600' }}">
                    @if ($budgetRemainder >= 0)
                        EUR {{ number_format($budgetRemainder, 2) }}
                    @else
                        {{ number_format(abs($budgetRemainder), 2) }} {{ __('over') }}
                    @endif
                </dd>
            </div>
        @endif
    </dl>
    <a
        href="{{ route('app.boxes.show', $box) }}"
        class="mt-4 flex w-full justify-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
    >
        {{ __('View my box') }}
    </a>
</div>
