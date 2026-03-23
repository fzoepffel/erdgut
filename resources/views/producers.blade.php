@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">
                    Local producer network
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Meet the producers behind your weekly Erdgut box.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    We partner with farms and small food makers focused on quality, transparency, and fair pricing.
                    Every producer card below includes region and background details.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        @if ($producers->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                <h2 class="text-lg font-semibold text-gray-900">No producers available yet</h2>
                <p class="mt-2 text-sm text-gray-600">
                    We are currently preparing producer profiles. Please check back soon.
                </p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($producers as $producer)
                    <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        @if ($producer->primaryImageUrl())
                            <img
                                src="{{ $producer->primaryImageUrl() }}"
                                alt="{{ $producer->name }}"
                                class="h-44 w-full object-cover"
                                loading="lazy"
                            >
                        @endif
                        <div class="space-y-3 p-5">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $producer->name }}</h2>
                            <p class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                Region: {{ $producer->region }}
                            </p>
                            <p class="text-sm leading-6 text-gray-600">{{ $producer->description }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
