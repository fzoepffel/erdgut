{{-- Shared public producer-detail body. Rendered inside two different
     isolated chromes:
       - marketing shell (marketing/producer-show.blade.php) — links to the
         public catalogue + product pages;
       - preview-shop shell (shop/producer.blade.php) — links stay inside
         the hidden /shop surface.
     The wrapping layout and the internal link route names are the only
     differences, passed in as params.

     Required params:
       $producer          — App\Models\Producer
       $products          — Collection<App\Models\Product> (visible only)
       $indexUrl          — href for the breadcrumb middle crumb
       $indexLabel        — label for that crumb (e.g. "Producers" / "Shop")
       $productRouteName  — route name for product-card links --}}
@php
    /** @var \App\Models\Producer $producer */
    // Admins enter a single name field, so render it whole (no first-word split).
    $name = $producer->name;
    $region = $producer->region;
    $description = $producer->description;
    $image = $producer->primaryImageUrl();
@endphp

{{-- Breadcrumb: mono uppercase, hairline top & bottom --}}
<nav aria-label="{{ __('Breadcrumb') }}" class="border-y border-brand-ink/15">
    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
        <ol class="flex flex-wrap items-center gap-2 text-xs uppercase tracking-eyebrow text-brand-ink/55">
            <li>{{ config('app.name', 'Erdgut') }}</li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ $indexUrl }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ $indexLabel }}</a></li>
            <li aria-hidden="true">/</li>
            <li class="text-brand-ink/80">{{ $name }}</li>
        </ol>
    </div>
</nav>

{{-- Hero --}}
<x-reveal as="section" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8" aria-labelledby="producer-hero-heading">
    <div class="grid items-start gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
        {{-- Left: copy --}}
        <div>
            <p class="eyebrow text-brand-moss">{{ __('producer.detail.eyebrow') }}</p>
            <h1 id="producer-hero-heading" class="mt-6 font-display text-5xl font-semibold leading-[1.04] tracking-tight text-brand-ink sm:text-7xl lg:text-[5.25rem]">
                {{ $name }}
            </h1>
            @if ($region)
                <p class="mt-6 text-sm uppercase tracking-eyebrow text-brand-ink/55">{{ $region }}</p>
            @endif
            <div class="mt-8 max-w-xl font-editorial text-base leading-relaxed text-brand-ink/80">
                @if ($description)
                    <p>{{ $description }}</p>
                @else
                    <p class="italic text-brand-ink/50">{{ __('producer.detail.description_missing') }}</p>
                @endif
            </div>
        </div>

        {{-- Right: full-bleed image with floating caption --}}
        <div class="relative">
            <div class="relative aspect-[4/5] overflow-hidden bg-chapter-producers-soft/40">
                @if ($image)
                    <img src="{{ $image }}" alt="{{ $name }}" class="h-full w-full object-cover" fetchpriority="high" decoding="async" />
                @else
                    <span class="flex h-full w-full items-center justify-center text-brand-ink/25" aria-hidden="true">
                        <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="0.75" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                    </span>
                @endif
                @if ($region)
                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-4 bg-brand-cream/70 px-4 py-3 text-xs uppercase tracking-eyebrow text-brand-ink/80 backdrop-blur">
                        <span class="truncate">{{ $name }}</span>
                        <span class="shrink-0 text-brand-ink/55">{{ $region }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-reveal>

{{-- Products: rendered as full cards (image, name, price, note), like the catalogue --}}
@if ($products->isNotEmpty())
    @php
        // Category placeholder images, matching the public catalogue fallbacks.
        $categoryPlaceholders = [
            'produce' => asset('images/mock/products/vegetables.jpg'),
            'eggs_and_dairy' => asset('images/mock/products/dairy.jpg'),
            'bread' => asset('images/mock/products/bakery.jpg'),
            'fish' => asset('images/mock/products/fish.jpg'),
            'meat' => asset('images/mock/products/meat.jpg'),
        ];
    @endphp
    <x-reveal as="section" class="border-t border-brand-ink/15" aria-labelledby="producer-products-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            {{-- Section header --}}
            <div class="max-w-2xl">
                <p class="eyebrow text-brand-moss">{{ __('producer.detail.in_the_box_eyebrow') }}</p>
                <h2 id="producer-products-heading" class="mt-6 font-display text-3xl font-semibold leading-tight text-brand-ink sm:text-4xl">
                    {{ __('producer.detail.in_the_box_heading', ['name' => $name]) }}
                </h2>
                <p class="mt-6 font-editorial text-base leading-relaxed text-brand-ink/70">
                    {{ __('producer.detail.in_the_box_sub') }}
                </p>
            </div>

            {{-- Product grid --}}
            <ul class="mt-14 grid grid-cols-1 gap-x-10 gap-y-14 sm:grid-cols-2 lg:grid-cols-3" data-testid="producer-products-grid">
                @foreach ($products as $index => $product)
                    @php
                        $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['produce'];
                        $note = $product->localizedTasteProfile();
                    @endphp
                    <li class="list-none">
                        <a href="{{ route($productRouteName, $product) }}" class="group flex flex-col" data-testid="producer-product-card">
                            {{-- Image well --}}
                            <div class="relative aspect-square overflow-hidden bg-chapter-producers-soft/40">
                                <img
                                    src="{{ $product->imageUrl() ?? $placeholder }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                                    loading="lazy"
                                />
                                <span class="absolute left-3 top-3 bg-chapter-producers px-2 py-1 font-mono text-[0.65rem] uppercase tracking-widest text-brand-cream">
                                    № {{ sprintf('%02d', $index + 1) }}
                                </span>
                            </div>

                            {{-- Title row --}}
                            <div class="mt-5 flex items-baseline justify-between gap-4">
                                <h3 class="font-display text-2xl text-brand-ink">{{ $product->name }}</h3>
                                <span class="shrink-0 font-mono text-xs tabular-nums text-brand-ink/60">{{ $product->displayPrice() }}</span>
                            </div>

                            {{-- Hairline --}}
                            <div class="mt-2 h-px bg-brand-ink/10"></div>

                            {{-- Note --}}
                            @if ($note)
                                <p class="mt-3 text-sm leading-relaxed text-brand-ink/60">{{ $note }}</p>
                            @endif

                            {{-- CTA --}}
                            <span class="mt-5 inline-flex items-center gap-2 self-start border-b border-chapter-producers pb-0.5 text-xs text-chapter-producers">
                                {{ __('Read the entry') }} <span aria-hidden="true">&rarr;</span>
                            </span>
                        </a>

                        {{-- Shop availability CTA: only on the shop-shell page ($shopCtas passed). --}}
                        @isset($shopCtas)
                            <div class="mt-4">
                                @include('shop._partials.availability-cta', ['cta' => $shopCtas[$product->id], 'block' => true])
                            </div>
                        @endisset
                    </li>
                @endforeach
            </ul>
        </div>
    </x-reveal>
@endif
