@props([
    // Producer payload — typically the array returned by EditorialContent::producerOfTheWeek().
    // Shape: ['slug' => ..., 'name' => ..., 'role' => ..., 'bio' => ..., 'photo' => ...]
    'producer' => [],

    // Optional override link (defaults to the public producers page).
    'link' => null,

    // Optional eyebrow override (defaults to the lang key).
    'eyebrow' => null,
])

@php
    $href = $link ?? route('public.producers');
    $eyebrowText = $eyebrow ?? __('customer.story.producer_spotlight_eyebrow');
    $name = $producer['name'] ?? '';
    $role = $producer['role'] ?? '';
    $bio = $producer['bio'] ?? '';
    $photo = $producer['photo'] ?? null;
@endphp

<aside
    {{ $attributes->class(['border border-chapter-producers/30 bg-chapter-producers/[0.06] p-5 sm:p-6']) }}
    data-testid="customer-producer-spotlight"
    aria-labelledby="producer-spotlight-name-{{ $producer['slug'] ?? 'x' }}"
>
    <p class="eyebrow text-chapter-producers">{{ $eyebrowText }}</p>
    <div class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-start">
        @if ($photo)
            <div class="h-24 w-24 shrink-0 overflow-hidden bg-brand-ink/5 sm:h-28 sm:w-28">
                <img
                    src="{{ $photo }}"
                    alt="{{ $name }}"
                    class="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <p
                id="producer-spotlight-name-{{ $producer['slug'] ?? 'x' }}"
                class="font-display text-xl text-brand-ink"
            >
                {{ $name }}
            </p>
            @if ($role)
                <p class="mt-1 font-editorial text-sm italic text-brand-ink/65">{{ $role }}</p>
            @endif
            @if ($bio)
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/80">{{ $bio }}</p>
            @endif

            <a
                href="{{ $href }}"
                class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
            >
                {{ __('customer.story.box.read_more_producers') }}
            </a>
        </div>
    </div>
</aside>
