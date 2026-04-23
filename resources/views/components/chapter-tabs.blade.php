@props([
    // Tabs to render. Each item is: ['id' => 'plan', 'label' => 'Plan', 'chapter' => 'produce'].
    // 'id' maps to an anchor on the page (e.g. <section id="plan">).
    // 'chapter' picks the accent color from the palette.
    'items' => [],

    // Keep the strip pinned to the top of the viewport as the user scrolls.
    'sticky' => false,

    // aria-label for the <nav> element.
    'label' => null,
])

@php
    // Literal class maps — keep these so Tailwind JIT can discover all chapter utilities.
    $textByChapter = [
        'produce' => 'text-chapter-produce',
        'meat' => 'text-chapter-meat',
        'fish' => 'text-chapter-fish',
        'eggdairy' => 'text-chapter-eggdairy',
        'bread' => 'text-chapter-bread',
        'producers' => 'text-chapter-producers',
        'almanach' => 'text-chapter-almanach',
    ];
    $borderByChapter = [
        'produce' => 'border-chapter-produce',
        'meat' => 'border-chapter-meat',
        'fish' => 'border-chapter-fish',
        'eggdairy' => 'border-chapter-eggdairy',
        'bread' => 'border-chapter-bread',
        'producers' => 'border-chapter-producers',
        'almanach' => 'border-chapter-almanach',
    ];

    $firstId = $items[0]['id'] ?? '';
    $itemsJson = collect($items)->pluck('id')->values()->toJson();
@endphp

@if (! empty($items))
    <nav
        aria-label="{{ $label ?? __('Section navigation') }}"
        x-data="{
            active: @js($firstId),
            ids: {{ $itemsJson }},
            observer: null,
            init() {
                if (typeof IntersectionObserver === 'undefined') return;
                this.observer = new IntersectionObserver((entries) => {
                    const visible = entries
                        .filter((e) => e.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                    if (visible[0]) {
                        this.active = visible[0].target.id;
                    }
                }, { rootMargin: '-30% 0px -50% 0px', threshold: [0, 0.25, 0.5, 0.75] });
                this.ids.forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) this.observer.observe(el);
                });
            },
            destroy() { this.observer?.disconnect(); }
        }"
        {{ $attributes->class([
            'border-b border-brand-ink/10 bg-brand-cream/95 backdrop-blur',
            'sticky top-0 z-30' => $sticky,
        ]) }}
    >
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <ul class="-mx-1 flex gap-1 overflow-x-auto sm:gap-2">
                @foreach ($items as $item)
                    @php
                        $id = $item['id'] ?? '';
                        $label = $item['label'] ?? '';
                        $chapter = $item['chapter'] ?? 'produce';
                        $textClass = $textByChapter[$chapter] ?? 'text-brand-ink';
                        $borderClass = $borderByChapter[$chapter] ?? 'border-brand-ink';
                    @endphp
                    <li class="flex-none">
                        <a
                            href="#{{ $id }}"
                            @click="active = @js($id)"
                            :class="active === @js($id)
                                ? '{{ $textClass }} {{ $borderClass }}'
                                : 'border-transparent text-brand-ink/55 hover:text-brand-ink'"
                            class="inline-flex items-center whitespace-nowrap border-b-2 px-3 py-3 text-xs font-semibold uppercase tracking-eyebrow transition-colors"
                        >
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
@endif
