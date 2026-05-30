@props([
    'eyebrow' => null,
    'title' => null,
    'note' => null,
])

<div>
    @if ($eyebrow)
        <p class="eyebrow text-chapter-produce">{{ $eyebrow }}</p>
    @endif
    @if ($title)
        <h2 class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">{{ $title }}</h2>
    @endif
    @if ($note)
        <p class="mt-3 border-l-2 border-chapter-produce bg-brand-paper px-4 py-3 text-sm leading-relaxed text-brand-ink/70">
            {{ $note }}
        </p>
    @endif
    {{ $slot }}
</div>
