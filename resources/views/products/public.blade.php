@extends('layouts.marketing')

@section('content')
    <section class="border-b border-brand-ink/10 bg-brand-paper">
        <div class="mx-auto max-w-7xl px-4 pb-10 pt-12 sm:px-6 sm:pb-12 sm:pt-16 lg:px-8">
            <p class="eyebrow text-chapter-produce">{{ __('products.index_title') }}</p>
            <h1 class="mt-3 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Five stalls, curated weekly.') }}
            </h1>
            <p class="mt-4 max-w-2xl font-editorial text-base leading-relaxed text-brand-ink/75 sm:text-lg">
                {{ __('products.index_intro') }}
            </p>
        </div>
    </section>

    @include('products._partials.listing', [
        'products' => $products,
        'activeCategory' => $activeCategory,
        'categories' => $categories,
        'listRouteName' => 'public.products.index',
        'detailRouteName' => 'public.products.show',
    ])
@endsection
