<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $product->name }}
        </h2>
    </x-slot>

    @php $catEnum = \App\Enums\ProductCategory::tryFrom($product->category); @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="grid gap-6 p-6 sm:grid-cols-2">
                    <div class="aspect-square overflow-hidden rounded-md bg-gray-100">
                        @if ($product->imageUrl())
                            <img
                                src="{{ $product->imageUrl() }}"
                                alt="{{ $product->name }}"
                                class="h-full w-full object-cover"
                            />
                        @else
                            <div class="flex h-full w-full items-center justify-center text-sm text-gray-400">
                                {{ __('products.no_image') }}
                            </div>
                        @endif
                    </div>
                    <div class="space-y-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">
                            {{ $catEnum ? __('admin.category.'.$catEnum->value) : $product->category }}
                        </p>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ $product->name }}</h1>
                        <p class="text-sm text-gray-600">
                            {{ __('products.producer_label') }}:
                            <span class="font-medium text-gray-900">{{ $product->producer?->name ?? __('products.producer_unknown') }}</span>
                        </p>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ __('EUR :amount', ['amount' => number_format((float) $product->unit_price, 2)]) }}
                        </p>
                    </div>
                </div>
            </div>

            <div>
                <a href="{{ route('app.products.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    {{ __('products.back_to_list') }} &larr;
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
