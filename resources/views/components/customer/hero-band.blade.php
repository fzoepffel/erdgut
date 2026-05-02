@props([
    // One of: produce|meat|fish|eggdairy|bread|producers|almanach. Drives the
    // gradient overlay tint and the eyebrow accent colour.
    'chapter' => 'produce',

    // Background image asset URL. Pass via asset('images/...') from the caller.
    'image' => null,

    // Eyebrow line above the title. Short, uppercase by CSS.
    'eyebrow' => null,

    // Display title (rendered as h1).
    'title' => '',

    // Italic editorial lead under the title. Optional.
    'lead' => null,

    // Optional small ornament line under the lead (e.g. month label).
    'caption' => null,
])

@php
    // Literal class maps so Tailwind JIT discovers the chapter utilities.
    $eyebrowText = [
        'produce' => 'text-chapter-produce',
        'meat' => 'text-chapter-meat',
        'fish' => 'text-chapter-fish',
        'eggdairy' => 'text-chapter-eggdairy',
        'bread' => 'text-chapter-bread',
        'producers' => 'text-chapter-producers',
        'almanach' => 'text-chapter-almanach',
    ][$chapter] ?? 'text-chapter-produce';

    // Gradient swatches use the chapter's DEFAULT (deep) tone for legibility
    // over the photo. Each pair is `from-[hex]/80 via-[hex]/40 to-transparent`.
    $gradientHex = [
        'produce' => '#21341d',
        'meat' => '#ac273b',
        'fish' => '#1d4485',
        'eggdairy' => '#e46f25',
        'bread' => '#944e22',
        'producers' => '#a37a2c',
        'almanach' => '#703b46',
    ][$chapter] ?? '#21341d';
@endphp

<section
    {{ $attributes->class(['relative overflow-hidden border border-brand-ink/10']) }}
    data-testid="customer-hero-band"
    aria-label="{{ $title }}"
>
    @if ($image)
        <img
            src="{{ $image }}"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            loading="eager"
            decoding="async"
        />
    @else
        <div class="absolute inset-0" style="background-color: {{ $gradientHex }};"></div>
    @endif

    {{-- Gradient overlay: dark chapter tone on the left bleeds to transparent on the right
         so the photo carries the right side and the text reads cleanly on the left. --}}
    <div
        class="absolute inset-0"
        style="background: linear-gradient(105deg, {{ $gradientHex }}EE 0%, {{ $gradientHex }}AA 35%, {{ $gradientHex }}55 65%, transparent 100%);"
        aria-hidden="true"
    ></div>

    <div class="relative px-6 py-12 sm:px-10 sm:py-16 lg:py-20">
        <div class="max-w-2xl">
            <x-reveal variant="fade-up">
                @if ($eyebrow)
                    <p class="eyebrow text-brand-cream/85">{{ $eyebrow }}</p>
                @endif
                <h1 class="mt-3 font-display text-3xl font-semibold leading-tight text-brand-cream sm:text-5xl">
                    {{ $title }}
                </h1>
                @if ($lead)
                    <p class="mt-5 max-w-xl font-editorial text-base italic leading-relaxed text-brand-cream/90 sm:text-lg">
                        {{ $lead }}
                    </p>
                @endif
                @if ($caption)
                    <p class="mt-4 text-xs uppercase tracking-eyebrow text-brand-cream/70">{{ $caption }}</p>
                @endif
                @isset($actions)
                    <div class="mt-6 flex flex-wrap gap-3">
                        {{ $actions }}
                    </div>
                @endisset
            </x-reveal>
        </div>
    </div>
</section>
