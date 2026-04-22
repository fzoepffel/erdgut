@props(['locale'])

@php
    $locale = strtolower((string) $locale);
    $titles = [
        'de' => 'Deutschland',
        'en' => 'United Kingdom',
        'fr' => 'France',
    ];
    $title = $titles[$locale] ?? strtoupper($locale);
@endphp

<span {{ $attributes->merge(['class' => 'inline-block']) }} role="img" aria-label="{{ $title }}" title="{{ $title }}">
    @switch($locale)
        @case('de')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3" preserveAspectRatio="none" class="block h-full w-full">
                <rect width="5" height="1" y="0" fill="#000000" />
                <rect width="5" height="1" y="1" fill="#DD0000" />
                <rect width="5" height="1" y="2" fill="#FFCE00" />
            </svg>
            @break

        @case('fr')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2" preserveAspectRatio="none" class="block h-full w-full">
                <rect width="1" height="2" x="0" fill="#0055A4" />
                <rect width="1" height="2" x="1" fill="#FFFFFF" />
                <rect width="1" height="2" x="2" fill="#EF4135" />
            </svg>
            @break

        @case('en')
            {{-- Union Jack, simplified but faithful --}}
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30" preserveAspectRatio="none" class="block h-full w-full">
                <clipPath id="uk-clip">
                    <rect width="60" height="30" />
                </clipPath>
                <g clip-path="url(#uk-clip)">
                    <rect width="60" height="30" fill="#012169" />
                    <path d="M0,0 L60,30 M60,0 L0,30" stroke="#FFFFFF" stroke-width="6" />
                    <path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4" clip-path="polygon(0 0, 30 15, 60 30, 60 30, 30 15, 0 30)" />
                    <path d="M30,0 V30 M0,15 H60" stroke="#FFFFFF" stroke-width="10" />
                    <path d="M30,0 V30 M0,15 H60" stroke="#C8102E" stroke-width="6" />
                </g>
            </svg>
            @break

        @default
            <span class="block h-full w-full bg-brand-ink/10 text-[0.6rem] font-bold uppercase text-brand-ink/60 text-center">{{ strtoupper($locale) }}</span>
    @endswitch
</span>
