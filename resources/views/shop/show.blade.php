@php
    $origin = $product->metadata['origin'] ?? $product->producer?->region ?? '—';
    $unit = $product->metadata['unit'] ?? 'kg';
    $variety = $product->metadata['variety'] ?? null;
    $tasteProfile = $product->metadata['taste_profile'] ?? null;
    $characteristics = $product->metadata['characteristics'] ?? null;
    if (! is_array($characteristics)) {
        $characteristics = null;
    } else {
        $characteristics = array_values(array_filter(array_map('strval', $characteristics), static fn ($v) => $v !== ''));
        $characteristics = $characteristics === [] ? null : $characteristics;
    }
    $dietaryNotes = $product->metadata['dietary_notes'] ?? null;
    $dietaryNotes = is_string($dietaryNotes) && trim($dietaryNotes) !== '' ? trim($dietaryNotes) : null;
    $canAddToBox = $currentBox instanceof \App\Models\Box;
    $heroImage = $product->imageUrl() ?: ($product->producer?->primaryImageUrl() ?: null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('app.shop.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $product->name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-[1fr_280px] lg:gap-8">
                <div class="lg:col-span-1">
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        {{-- Image --}}
                        @if (! empty($heroImage))
                            <img
                                src="{{ $heroImage }}"
                                alt="{{ $product->name }}"
                                class="h-64 w-full object-cover lg:h-80"
                                loading="lazy"
                                decoding="async"
                                onerror="this.onerror=null; this.style.display='none'; var f=this.nextElementSibling; if(f) f.style.display='flex';"
                            >
                            <div class="flex h-64 w-full items-center justify-center bg-gray-100 lg:h-80" style="display: none">
                                <span class="text-sm text-gray-400">{{ __('No image') }}</span>
                            </div>
                        @else
                            <div class="flex h-64 w-full items-center justify-center bg-gray-100 lg:h-80">
                                <span class="text-sm text-gray-400">{{ __('No image') }}</span>
                            </div>
                        @endif

                        <div class="space-y-6 p-6">
                            {{-- Overview --}}
                            <section>
                                <h3 class="sr-only">{{ __('Overview') }}</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                        {{ __(\Illuminate\Support\Str::title($product->category)) }}
                                    </span>
                                    @if ($product->is_seasonal)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                            {{ __('Seasonal') }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-500">{{ __('per') }} {{ $unit }}</span>
                                </div>
                                <p class="mt-3 text-2xl font-semibold text-emerald-700">
                                    EUR {{ number_format((float) $product->unit_price, 2) }}
                                </p>
                            </section>

                            {{-- Origin / Region --}}
                            <section>
                                <h3 class="text-sm font-semibold text-gray-900">{{ __('Origin') }}</h3>
                                <p class="mt-1 text-gray-600">{{ $origin }}</p>
                            </section>

                            {{-- Variety --}}
                            @if ($variety)
                                <section>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Variety') }}</h3>
                                    <p class="mt-1 text-gray-600">{{ $variety }}</p>
                                </section>
                            @endif

                            @if ($characteristics)
                                <section>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Characteristics') }}</h3>
                                    <ul class="mt-2 list-inside list-disc text-sm text-gray-600">
                                        @foreach ($characteristics as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif

                            @if ($dietaryNotes)
                                <section>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Dietary notes') }}</h3>
                                    <p class="mt-1 text-sm text-gray-600">{{ $dietaryNotes }}</p>
                                </section>
                            @endif

                            {{-- Producer story --}}
                            @if ($product->producer)
                                <section>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Producer story') }}</h3>
                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ $product->producer->name }}
                                        @if ($product->producer->region)
                                            · {{ $product->producer->region }}
                                        @endif
                                    </p>
                                    @if (! empty($product->producer->description))
                                        <p class="mt-2 text-gray-600">{{ $product->producer->description }}</p>
                                    @endif
                                </section>
                            @endif

                            {{-- Taste profile --}}
                            @if ($tasteProfile)
                                <section>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Taste profile') }}</h3>
                                    <p class="mt-1 text-gray-600">{{ $tasteProfile }}</p>
                                </section>
                            @endif

                            @if (! $canAddToBox)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <p class="text-sm font-semibold text-gray-900">{{ __('Extras need an editable box') }}</p>
                                    <p class="mt-2 text-sm text-gray-600">
                                        {{ __('You can add shop extras only when your box for this week is open for editing and before the modification deadline.') }}
                                    </p>
                                    <div class="mt-4 flex flex-wrap gap-3">
                                        <a
                                            href="{{ route('app.boxes.current') }}"
                                            class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                                        >
                                            {{ __('Open current week box') }}
                                        </a>
                                        <a
                                            href="{{ route('app.dashboard') }}"
                                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                        >
                                            {{ __('Dashboard') }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            {{-- Add to box --}}
                            @if ($canAddToBox)
                                <div
                                    x-data="{ loading: false, error: null }"
                                    class="pt-4"
                                >
                                    <form
                                        method="POST"
                                        action="{{ route('app.boxes.items.add', $currentBox) }}"
                                        x-ref="form"
                                        @submit.prevent="
                                            loading = true;
                                            error = null;
                                            fetch($refs.form.action, {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                },
                                                body: JSON.stringify({ product_id: {{ $product->id }}, quantity: 1 }),
                                            })
                                            .then(r => r.json())
                                            .then(data => { if (data.estimated_value !== undefined) window.location.reload(); })
                                            .catch(e => { error = e.message; loading = false; });
                                        "
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="w-full rounded-md bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                                            :disabled="loading"
                                        >
                                            <span x-show="!loading">{{ __('Add to box') }}</span>
                                            <span x-show="loading" x-cloak>{{ __('Adding…') }}</span>
                                        </button>
                                    </form>
                                    <p
                                        x-show="error"
                                        x-cloak
                                        x-text="error"
                                        class="mt-2 text-sm text-red-600"
                                        role="alert"
                                        aria-live="polite"
                                    ></p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @if ($currentBox)
                    <aside class="mt-8 lg:col-span-1 lg:mt-0">
                        <div class="sticky top-4">
                            <x-shop.box-summary :box="$currentBox" :budgetRemainder="$budgetRemainder" />
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
