@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">
                    Farm-to-consumer in Berlin
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Fresh weekly boxes from local farms, delivered transparently.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    Erdgut connects Berlin households with small-scale producers through weekly subscription boxes.
                    You always see where your food comes from, and farmers receive fair pricing.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('auth.register') }}" class="rounded-md bg-emerald-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                        Start your subscription
                    </a>
                    <a href="{{ route('auth.login') }}" class="rounded-md border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 transition hover:border-emerald-700 hover:text-emerald-700">
                        I already have an account
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-6 px-4 py-14 sm:px-6 md:grid-cols-3 lg:px-8">
        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Transparent sourcing</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                Each product is linked to its producer and region so customers understand quality and origin.
            </p>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Flexible weekly deliveries</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                Customers can manage delivery preferences and adjust upcoming boxes based on their needs.
            </p>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Fair pricing for farmers</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                Erdgut emphasizes a direct relationship between producer and household, with clear value distribution.
            </p>
        </article>
    </section>
@endsection
