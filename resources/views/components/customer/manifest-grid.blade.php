@props([
    // Eyebrow caps — switches by state at the call site
    // (e.g. "WAS IN DER BOX IST" vs "WAS DIESE WOCHE IM SORTIMENT IST").
    'eyebrow' => null,

    // Section title (Fraunces).
    'title' => null,

    // Optional subtitle / lead paragraph rendered to the right of the
    // title on wider viewports.
    'subtitle' => null,
])

<section
    {{ $attributes->class([]) }}
    data-testid="customer-manifest-grid"
    aria-labelledby="customer-manifest-heading"
>
    <header class="grid gap-4 sm:grid-cols-12 sm:items-end">
        <div class="sm:col-span-8">
            @if ($eyebrow)
                <p class="eyebrow text-chapter-produce">{{ $eyebrow }}</p>
            @endif
            @if ($title)
                <h2 id="customer-manifest-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl">
                    {{ $title }}
                </h2>
            @endif
        </div>
        @if ($subtitle)
            <p class="font-editorial text-sm italic leading-relaxed text-brand-ink/70 sm:col-span-4 sm:text-right">
                {{ $subtitle }}
            </p>
        @endif
    </header>

    <div class="mt-8 grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
        {{ $slot }}
    </div>
</section>
