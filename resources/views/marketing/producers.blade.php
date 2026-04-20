@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white" aria-labelledby="producers-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                    Local producer network
                </p>
                <h1 id="producers-hero-heading" class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Meet the producers behind your weekly Erdgut box.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    We partner with farms and small food makers focused on quality, transparency, and fair pricing.
                    Filter by region or search by name—every profile shows where they work and what they stand for.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8" aria-labelledby="producers-directory-heading">
        <h2 id="producers-directory-heading" class="sr-only">Producer directory</h2>

        @if ($hasAnyProducers)
            <form
                method="get"
                action="{{ route('public.producers') }}"
                class="mb-10 flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end"
                data-testid="producers-toolbar"
            >
                <div class="w-full min-w-0 flex-1 sm:max-w-xs">
                    <label for="filter-region" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Region</label>
                    <select
                        id="filter-region"
                        name="region"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                    >
                        <option value="">All regions</option>
                        @foreach ($regions as $regionOption)
                            <option value="{{ $regionOption }}" @selected($filters['region'] === $regionOption)>{{ $regionOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full min-w-0 flex-1 sm:max-w-md">
                    <label for="filter-q" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</label>
                    <input
                        type="search"
                        id="filter-q"
                        name="q"
                        value="{{ $filters['q'] }}"
                        maxlength="100"
                        placeholder="Producer name or keywords…"
                        class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                    >
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <button
                        type="submit"
                        class="inline-flex justify-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800"
                    >
                        Apply
                    </button>
                    @if ($filtersActive)
                        <a
                            href="{{ route('public.producers') }}"
                            class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-semibold text-gray-700 transition hover:border-emerald-600 hover:text-emerald-800"
                        >
                            Clear filters
                        </a>
                    @endif
                </div>
            </form>
        @endif

        @if (! $hasAnyProducers)
            <x-empty-state
                :title="__('No producers available yet')"
                :description="__('We are currently preparing producer profiles. Please check back soon.')"
                variant="card"
            />
        @elseif ($producers->isEmpty())
            <x-empty-state
                variant="card"
                tone="amber"
                :title="__('No producers match your filters')"
                :description="__('Try another region or search term, or clear filters to see everyone.')"
            >
                <x-slot name="action">
                    <a
                        href="{{ route('public.producers') }}"
                        class="inline-block text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline"
                    >
                        {{ __('Clear filters') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-testid="producers-grid">
                @foreach ($producers as $producer)
                    <article
                        class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                        x-data="{ expanded: false }"
                        data-testid="producer-card"
                        data-producer-id="{{ $producer->id }}"
                    >
                        <div class="relative h-44 shrink-0 bg-gradient-to-br from-emerald-100 to-emerald-50">
                            @if ($producer->primaryImageUrl())
                                <img
                                    src="{{ $producer->primaryImageUrl() }}"
                                    alt=""
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                >
                                <span class="sr-only">{{ $producer->name }} — photo</span>
                            @else
                                <div class="flex h-full w-full items-center justify-center text-emerald-700/35" aria-hidden="true">
                                    <svg class="h-20 w-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col space-y-3 p-5">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $producer->name }}</h3>
                            <p class="inline-flex w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                {{ $producer->region }}
                            </p>
                            @php
                                $desc = $producer->description ?? '';
                                $descLong = mb_strlen($desc) > 180;
                            @endphp
                            <div class="flex-1 text-sm leading-6 text-gray-600">
                                @if ($desc === '')
                                    <p class="text-gray-500">No description yet.</p>
                                @else
                                    <p :class="expanded ? '' : 'line-clamp-3'">{{ $desc }}</p>
                                    @if ($descLong)
                                        <button
                                            type="button"
                                            class="mt-2 text-sm font-semibold text-emerald-800 underline-offset-2 hover:underline"
                                            @click="expanded = !expanded"
                                            :aria-expanded="expanded"
                                            x-text="expanded ? 'Show less' : 'Read more'"
                                        ></button>
                                    @endif
                                @endif
                            </div>
                            <div class="border-t border-gray-100 pt-4">
                                @auth
                                    <a
                                        href="{{ route('app.products.index') }}"
                                        class="inline-flex w-full justify-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800"
                                        data-testid="producer-card-products-link"
                                    >
                                        Browse products
                                    </a>
                                @else
                                    <a
                                        href="{{ route('auth.register') }}"
                                        class="inline-flex w-full justify-center rounded-md border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50"
                                        data-testid="producer-card-signup-link"
                                    >
                                        Sign up to browse products
                                    </a>
                                    <p class="mt-2 text-center text-xs text-gray-500">
                                        Already a customer?
                                        <a href="{{ route('auth.login') }}" class="font-medium text-emerald-800 underline-offset-2 hover:underline">Log in</a>
                                    </p>
                                @endauth
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
