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

    @include('products._partials.listing', [
        'products' => $products,
        'activeCategory' => $activeCategory,
        'categories' => $categories,
        'listRouteName' => 'app.products.index',
        'detailRouteName' => 'app.products.show',
    ])
</x-app-layout>
