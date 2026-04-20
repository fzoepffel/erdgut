<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('products.index_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('products.index_intro') }}
            </p>

            @if ($products->isEmpty())
                <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-6 text-center text-sm text-gray-600">
                    {{ __('products.empty') }}
                </div>
            @else
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $product)
                        <li>
                            <a
                                href="{{ route('app.products.show', $product) }}"
                                class="flex h-full flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-emerald-400 hover:shadow"
                            >
                                <div class="aspect-square w-full overflow-hidden bg-gray-100">
                                    @if ($product->imageUrl())
                                        <img
                                            src="{{ $product->imageUrl() }}"
                                            alt="{{ $product->name }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                                            {{ __('products.no_image') }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col gap-1 p-4">
                                    @php $catEnum = \App\Enums\ProductCategory::tryFrom($product->category); @endphp
                                    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">
                                        {{ $catEnum ? __('admin.category.'.$catEnum->value) : $product->category }}
                                    </p>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $product->name }}</h3>
                                    <p class="text-xs text-gray-500">{{ $product->producer?->name ?? __('products.producer_unknown') }}</p>
                                    <p class="mt-auto text-sm font-medium text-gray-800">
                                        {{ __('EUR :amount', ['amount' => number_format((float) $product->unit_price, 2)]) }}
                                    </p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="pt-2">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
