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
            @php
                // Literal class maps so Tailwind JIT keeps the chapter filter utilities.
                $chipActiveByChapter = [
                    'produce' => 'bg-chapter-produce text-brand-cream border-chapter-produce',
                    'meat' => 'bg-chapter-meat text-brand-cream border-chapter-meat',
                    'fish' => 'bg-chapter-fish text-brand-cream border-chapter-fish',
                    'eggdairy' => 'bg-chapter-eggdairy text-brand-cream border-chapter-eggdairy',
                    'bread' => 'bg-chapter-bread text-brand-cream border-chapter-bread',
                ];
                $chipRestByChapter = [
                    'produce' => 'border-chapter-produce/40 text-chapter-produce hover:bg-chapter-produce/10',
                    'meat' => 'border-chapter-meat/40 text-chapter-meat hover:bg-chapter-meat/10',
                    'fish' => 'border-chapter-fish/40 text-chapter-fish hover:bg-chapter-fish/10',
                    'eggdairy' => 'border-chapter-eggdairy/40 text-chapter-eggdairy hover:bg-chapter-eggdairy/10',
                    'bread' => 'border-chapter-bread/40 text-chapter-bread hover:bg-chapter-bread/10',
                ];

                $categoryPlaceholders = [
                    'vegetables' => asset('images/mock/products/vegetables.jpg'),
                    'fruit' => asset('images/mock/products/fruit.jpg'),
                    'dairy' => asset('images/mock/products/dairy.jpg'),
                    'bakery' => asset('images/mock/products/bakery.jpg'),
                    'fish' => asset('images/mock/products/fish.jpg'),
                    'meat' => asset('images/mock/products/meat.jpg'),
                ];
            @endphp

            {{-- Chapter filter chips --}}
            <nav aria-label="{{ __('products.category_filter_label') }}" class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('app.products.index') }}"
                    @class([
                        'inline-flex items-center border px-4 py-2 text-xs font-semibold uppercase tracking-eyebrow transition',
                        'bg-brand-ink text-brand-cream border-brand-ink' => ! $activeCategory,
                        'border-brand-ink/30 text-brand-ink/70 hover:bg-brand-ink/5' => $activeCategory,
                    ])
                >
                    {{ __('All') }}
                </a>
                @foreach ($categories as $cat)
                    @php
                        $style = \App\Support\ProductCategoryStyle::for($cat);
                        $chapter = $style['chapter'];
                        $isActive = $activeCategory && $activeCategory->value === $cat->value;
                        $activeCls = $chipActiveByChapter[$chapter] ?? 'bg-brand-ink text-brand-cream border-brand-ink';
                        $restCls = $chipRestByChapter[$chapter] ?? 'border-brand-ink/30 text-brand-ink/70 hover:bg-brand-ink/5';
                    @endphp
                    <a
                        href="{{ route('app.products.index', ['category' => $cat->value]) }}"
                        @class([
                            'inline-flex items-center gap-2 border px-4 py-2 text-xs font-semibold uppercase tracking-eyebrow transition',
                            $activeCls => $isActive,
                            $restCls => ! $isActive,
                        ])
                        aria-current="{{ $isActive ? 'true' : 'false' }}"
                    >
                        <x-chapter-mark :chapter="$chapter" :variant="$isActive ? 'white' : 'color'" size="xs" class="shrink-0 opacity-90" />
                        <span>{{ __('admin.category.' . $cat->value) }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($products->isEmpty())
                <x-empty-state
                    :title="__('products.empty')"
                    :description="__('Check back soon — new products are added each week.')"
                    variant="card"
                />
            @else
                <ul class="grid grid-cols-1 gap-x-6 gap-y-14 reveal-stagger sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-testid="products-grid">
                    @foreach ($products as $i => $product)
                        @php
                            $catEnum = \App\Enums\ProductCategory::tryFrom($product->category);
                            $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['vegetables'];
                            $style = $catEnum ? \App\Support\ProductCategoryStyle::for($catEnum) : null;
                            $chapter = $style['chapter'] ?? null;
                            // Running index across the paginated list, 1-based.
                            $cardNumber = ($products->firstItem() ?? 1) + $i;
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
                                    <span class="absolute left-0 top-0 bg-brand-ink px-3 py-2 font-display text-[11px] font-bold uppercase tracking-eyebrow text-brand-cream">
                                        {{ str_pad((string) $cardNumber, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    @if ($chapter)
                                        <span class="absolute right-2 top-2 flex h-12 w-12 items-center justify-center bg-brand-cream/85 backdrop-blur-sm">
                                            <x-chapter-mark :chapter="$chapter" size="xs" class="opacity-90" />
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col gap-2">
                                    @if ($catEnum)
                                        <x-chapter-badge :category="$catEnum" />
                                    @else
                                        <span class="eyebrow text-brand-ink/60">{{ $product->category }}</span>
                                    @endif
                                    <h3 class="font-display text-lg leading-snug text-brand-ink">{{ $product->name }}</h3>
                                    <p class="font-editorial text-xs italic text-brand-ink/60">{{ $product->producer?->name ?? __('products.producer_unknown') }}</p>
                                    <p class="mt-auto flex items-end justify-between gap-3 pt-2">
                                        <span class="font-display text-base text-brand-ink">
                                            {{ __('EUR :amount', ['amount' => \App\Support\Money::format($product->unit_price)]) }}
                                        </span>
                                        <span class="font-editorial text-[11px] italic text-brand-ink/55 group-hover:text-brand-ink">
                                            {{ __('Read') }} <span aria-hidden="true">&rarr;</span>
                                        </span>
                                    </p>
                                </div>
                            </a>
                        </x-reveal>
                    @endforeach
                </ul>

                <div class="pt-4">{{ $products->links() }}</div>

                <div class="flex flex-col items-center gap-3 pt-8">
                    <x-triple-star class="opacity-70" />
                    <p class="font-editorial text-sm italic text-brand-ink/60">{{ __('Fresh entries every week, in the rhythm of the harvest.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
