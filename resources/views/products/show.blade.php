<x-app-layout>
    @php
        $catEnum = \App\Enums\ProductCategory::tryFrom($product->category);
        $categoryPlaceholders = [
            'vegetables' => asset('images/mock/products/vegetables.jpg'),
            'fruit' => asset('images/mock/products/fruit.jpg'),
            'dairy' => asset('images/mock/products/dairy.jpg'),
            'bakery' => asset('images/mock/products/bakery.jpg'),
            'fish' => asset('images/mock/products/fish.jpg'),
            'meat' => asset('images/mock/products/meat.jpg'),
        ];
        $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['vegetables'];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3">
            <a href="{{ route('app.products.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                <span aria-hidden="true">&larr;</span> {{ __('products.back_to_list') }}
            </a>
            @if ($catEnum)
                <x-category-badge :category="$catEnum" class="w-fit" />
            @endif
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ $product->name }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">
                <x-reveal variant="scale" class="lg:col-span-7">
                    <div class="aspect-[4/5] overflow-hidden bg-brand-ink/5">
                        <img
                            src="{{ $product->imageUrl() ?? $placeholder }}"
                            alt="{{ $product->name }}"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150" class="lg:col-span-5">
                    <div class="space-y-6 lg:pt-8">
                        <div>
                            <p class="eyebrow text-brand-ink/60">{{ __('products.producer_label') }}</p>
                            <p class="mt-2 font-display text-2xl italic text-brand-ink">{{ $product->producer?->name ?? __('products.producer_unknown') }}</p>
                        </div>

                        <div class="border-y border-brand-ink/15 py-6">
                            <p class="eyebrow text-brand-ink/60">{{ __('Price') }}</p>
                            <p class="mt-2 font-display text-4xl font-semibold text-brand-ink">
                                {{ __('EUR :amount', ['amount' => number_format((float) $product->unit_price, 2)]) }}
                            </p>
                        </div>

                        @if (! empty($product->description))
                            <div class="space-y-3">
                                <p class="eyebrow text-brand-ink/60">{{ __('About') }}</p>
                                <p class="text-base leading-relaxed text-brand-ink/80">{{ $product->description }}</p>
                            </div>
                        @endif
                    </div>
                </x-reveal>
            </div>
        </div>
    </div>
</x-app-layout>
