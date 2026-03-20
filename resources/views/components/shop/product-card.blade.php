@props(['product'])

@php
    $origin = $product->metadata['origin'] ?? $product->producer?->region ?? '—';
    $unit = $product->metadata['unit'] ?? 'kg';
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if (! empty($product->producer?->image_url))
        <img
            src="{{ $product->producer->image_url }}"
            alt="{{ $product->name }}"
            class="h-44 w-full object-cover"
            loading="lazy"
        >
    @else
        <div class="flex h-44 w-full items-center justify-center bg-gray-100">
            <span class="text-sm text-gray-400">No image</span>
        </div>
    @endif

    <div class="flex flex-1 flex-col space-y-2 p-5">
        <h3 class="font-semibold text-gray-900">{{ $product->name }}</h3>
        <p class="text-sm text-gray-600">by {{ $product->producer?->name ?? 'Unknown' }}</p>
        <p class="inline-flex w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
            {{ $origin }}
        </p>
        <div class="flex flex-wrap gap-1.5">
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                {{ ucfirst($product->category) }}
            </span>
            @if ($product->is_seasonal)
                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                    Seasonal
                </span>
            @endif
            <span class="text-xs text-gray-500">per {{ $unit }}</span>
        </div>
        <p class="mt-auto font-medium text-emerald-700">
            EUR {{ number_format((float) $product->unit_price, 2) }}
        </p>
    </div>
</article>
