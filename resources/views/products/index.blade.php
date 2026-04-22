<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('products.index_title') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Five stalls, curated weekly.') }}
            </h1>
            <p class="mt-3 max-w-2xl text-base leading-relaxed text-brand-ink/75">
                {{ __('products.index_intro') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
            @if ($products->isEmpty())
                <x-empty-state
                    :title="__('products.empty')"
                    :description="__('Check back soon — new products are added each week.')"
                    variant="card"
                />
            @else
                @php
                    $categoryPlaceholders = [
                        'vegetables' => asset('images/mock/products/vegetables.jpg'),
                        'fruit' => asset('images/mock/products/fruit.jpg'),
                        'dairy' => asset('images/mock/products/dairy.jpg'),
                        'bakery' => asset('images/mock/products/bakery.jpg'),
                        'fish' => asset('images/mock/products/fish.jpg'),
                        'meat' => asset('images/mock/products/meat.jpg'),
                    ];
                @endphp
                <ul class="grid grid-cols-1 gap-x-6 gap-y-12 reveal-stagger sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $i => $product)
                        @php
                            $catEnum = \App\Enums\ProductCategory::tryFrom($product->category);
                            $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['vegetables'];
                        @endphp
                        <x-reveal as="li" variant="fade-up" style="--i:{{ $i % 6 }}">
                            <a
                                href="{{ route('app.products.show', $product) }}"
                                class="group flex h-full flex-col gap-4"
                            >
                                <div class="relative aspect-square w-full overflow-hidden bg-brand-ink/5">
                                    <img
                                        src="{{ $product->imageUrl() ?? $placeholder }}"
                                        alt="{{ $product->name }}"
                                        class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]"
                                        loading="lazy"
                                    />
                                </div>
                                <div class="flex flex-1 flex-col gap-2">
                                    @if ($catEnum)
                                        <x-chapter-badge :category="$catEnum" />
                                    @else
                                        <span class="eyebrow text-brand-ink/60">{{ $product->category }}</span>
                                    @endif
                                    <h3 class="font-display text-lg leading-snug text-brand-ink">{{ $product->name }}</h3>
                                    <p class="text-xs italic text-brand-ink/60">{{ $product->producer?->name ?? __('products.producer_unknown') }}</p>
                                    <p class="mt-auto pt-2 font-display text-base text-brand-ink">
                                        {{ __('EUR :amount', ['amount' => number_format((float) $product->unit_price, 2)]) }}
                                    </p>
                                </div>
                            </a>
                        </x-reveal>
                    @endforeach
                </ul>

                <div class="pt-4">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
