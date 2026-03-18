@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">
                    About Erdgut
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    A transparent bridge between Berlin households and local farms.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    Erdgut was started to make weekly food shopping more local, traceable, and fair.
                    We work with small producers and deliver curated boxes that make regional food simple for everyday life.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-6 px-4 py-14 sm:px-6 md:grid-cols-2 lg:px-8">
        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Our project story</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                We saw that many families wanted better local food options but lacked time and visibility into
                where products came from. Erdgut was built to connect producers and households directly through
                one simple weekly box flow.
            </p>
        </article>

        <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Sourcing philosophy</h2>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                We prioritize seasonal produce, clear producer attribution, and fair compensation.
                Each item in the box is linked to its origin so quality and pricing stay transparent.
            </p>
        </article>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8">
            <h2 class="text-xl font-semibold text-emerald-900">FAQ teaser</h2>
            <p class="mt-3 text-sm leading-6 text-emerald-900/90">
                Curious about delivery areas, subscription flexibility, or producer selection?
                A full FAQ page is coming soon. For now, our About section outlines the core principles behind Erdgut.
            </p>
            <div class="mt-5">
                <a href="{{ route('public.home') }}" class="text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline">
                    Back to homepage
                </a>
            </div>
        </div>
    </section>
@endsection
