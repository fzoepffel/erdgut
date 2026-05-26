@props([
    // Producer payload — same shape as EditorialContent::producer().
    // Required keys: slug, name, role, bio, quote, photo.
    'producer' => [],

    // Optional override for the "Read her chapter" link target. Defaults
    // to the public producers index until per-producer detail pages exist.
    'link' => null,

    // Optional eyebrow override; defaults to the dashboard producer-of-week label.
    'eyebrow' => null,
])

@php
    $slug = $producer['slug'] ?? 'x';
    $name = $producer['name'] ?? '';
    $role = $producer['role'] ?? '';
    $bio = $producer['bio'] ?? '';
    $quote = $producer['quote'] ?? $bio;
    $photo = $producer['photo'] ?? null;
    $href = $link ?? route('public.producers');
    $eyebrowText = $eyebrow ?? __('customer.dashboard.producer_feature.eyebrow');

    // Heading id ties the section's aria-labelledby to the producer name
    // for screen-reader navigation; the visible heading is the quote.
    $headingId = 'producer-feature-name-'.$slug;
@endphp

<section
    {{ $attributes->class(['relative']) }}
    data-testid="customer-producer-feature"
    aria-labelledby="{{ $headingId }}"
>
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
        {{-- Portrait — lg+ takes the leading 7 cols (≈ 60%), small screens stack first. --}}
        <div class="lg:col-span-7">
            @if ($photo)
                <div class="aspect-[5/6] w-full overflow-hidden bg-brand-ink/5 lg:aspect-[4/5]">
                    <img
                        src="{{ $photo }}"
                        alt="{{ $name }}"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            @endif
        </div>

        {{-- Text column --}}
        <div class="flex flex-col justify-center lg:col-span-5">
            <p class="eyebrow text-chapter-produce">{{ $eyebrowText }}</p>

            <blockquote class="mt-6 font-display text-3xl italic leading-[1.15] text-chapter-produce sm:text-4xl lg:text-[2.5rem]">
                <span aria-hidden="true">“</span>{{ $quote }}<span aria-hidden="true">”</span>
            </blockquote>

            <div class="mt-8 flex items-center gap-3">
                <span class="block h-px w-10 shrink-0 bg-brand-ink/40" aria-hidden="true"></span>
                <p id="{{ $headingId }}" class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/80">
                    {{ $name }}@if ($role)<span class="font-normal text-brand-ink/60"> · {{ $role }}</span>@endif
                </p>
            </div>

            @if ($bio)
                <p class="mt-6 max-w-md text-sm leading-relaxed text-brand-ink/75">
                    {{ $bio }}
                </p>
            @endif

            <a
                href="{{ $href }}"
                class="mt-6 inline-flex items-center gap-2 self-start border-b border-chapter-produce pb-0.5 eyebrow text-[0.7rem] text-chapter-produce hover:text-chapter-produce/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                data-testid="customer-producer-feature-link"
                data-track-section="producer_feature.read_more"
                data-track-destination="public.producers"
            >
                {{ __('customer.dashboard.producer_feature.cta', ['name' => $name]) }}
                <span aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</section>
