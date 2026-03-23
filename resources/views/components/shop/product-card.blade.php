@props(['product', 'currentBox' => null])

@php
    $origin = $product->metadata['origin'] ?? $product->producer?->region ?? '—';
    $unit = $product->metadata['unit'] ?? 'kg';
    $canAddToBox = $currentBox instanceof \App\Models\Box;
    $cardImage = $product->imageUrl() ?: ($product->producer?->primaryImageUrl() ?: null);
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm']) }}
    @if ($canAddToBox)
        x-data="{ loading: false, error: null }"
    @endif
>
    <div class="h-44 w-full bg-gray-100">
        @if (! empty($cardImage))
            <img
                src="{{ $cardImage }}"
                alt="{{ $product->name }}"
                class="h-44 w-full object-cover"
                loading="lazy"
                decoding="async"
                onerror="this.onerror=null; this.style.display='none'; var f=this.nextElementSibling; if(f) f.style.display='flex';"
            >
            <div class="flex h-44 w-full items-center justify-center" style="display: none">
                <span class="text-sm text-gray-400">{{ __('No image') }}</span>
            </div>
        @else
            <div class="flex h-44 w-full items-center justify-center">
                <span class="text-sm text-gray-400">{{ __('No image') }}</span>
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col space-y-2 p-5">
        <h3 class="font-semibold text-gray-900">
            <a href="{{ route('app.shop.show', $product) }}" class="hover:text-emerald-600 hover:underline">{{ $product->name }}</a>
        </h3>
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
        <div class="mt-auto flex flex-col gap-2">
            <p class="font-medium text-emerald-700">
                EUR {{ number_format((float) $product->unit_price, 2) }}
            </p>
            @if ($canAddToBox)
                <form
                    method="POST"
                    action="{{ route('app.boxes.items.add', $currentBox) }}"
                    class="add-to-box-form"
                    x-ref="form"
                    @submit.prevent="
                        loading = true;
                        error = null;
                        fetch($refs.form.action, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            },
                            body: JSON.stringify({ product_id: {{ $product->id }}, quantity: 1 }),
                        })
                        .then(r => r.json())
                        .then(data => { if (data.estimated_value !== undefined) window.location.reload(); })
                        .catch(e => { error = e.message; loading = false; });
                    "
                >
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                        :disabled="loading"
                    >
                        <span x-show="!loading">{{ __('Add to box') }}</span>
                        <span x-show="loading" x-cloak>{{ __('Adding…') }}</span>
                    </button>
                </form>
                <p x-show="error" x-cloak x-text="error" class="text-sm text-red-600"></p>
            @endif
        </div>
    </div>
</article>
