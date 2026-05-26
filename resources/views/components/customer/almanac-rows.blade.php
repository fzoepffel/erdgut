@props([
    // Pre-computed row payloads — call site does the model lookups so the
    // component stays presentational. Each row shape:
    //   [
    //       'iso_week' => int,            // ISO week number for the WK column
    //       'delivery_date' => Carbon,    // the canonical Wednesday for the week
    //       'label' => string,            // "Box №0214" | seasonal tagline | "In deiner Auszeit" | …
    //       'status' => 'on_its_way'|'scheduled'|'skipped',
    //   ]
    'rows' => [],

    // Optional override for the "Open full calendar" link target. Defaults
    // to the deliveries index.
    'fullCalendarUrl' => null,
])

@php
    $fullCalendarUrl = $fullCalendarUrl ?? route('app.deliveries.index');

    // Status → presentation map. Kept literal so Tailwind JIT discovers
    // every utility class at build time.
    $statusStyles = [
        'on_its_way' => ['label_key' => 'customer.dashboard.almanac.status_on_its_way', 'class' => 'text-chapter-meat'],
        'scheduled' => ['label_key' => 'customer.dashboard.almanac.status_scheduled', 'class' => 'text-brand-ink'],
        'skipped' => ['label_key' => 'customer.dashboard.almanac.status_skipped', 'class' => 'text-brand-ink/50 line-through'],
    ];
@endphp

<section
    {{ $attributes->class([]) }}
    data-testid="customer-almanac-rows"
    aria-labelledby="customer-almanac-heading"
>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow text-chapter-almanach">{{ __('customer.dashboard.almanac.eyebrow') }}</p>
            <h2 id="customer-almanac-heading" class="mt-2 font-display text-3xl text-brand-ink sm:text-4xl">
                {{ __('customer.dashboard.almanac.title') }}
            </h2>
        </div>
        <a
            href="{{ $fullCalendarUrl }}"
            class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-almanach"
            data-testid="customer-almanac-full-calendar"
            data-track-section="almanac.full_calendar"
            data-track-destination="app.deliveries.index"
        >
            {{ __('customer.dashboard.almanac.cta_full_calendar') }}
            <span aria-hidden="true">&rarr;</span>
        </a>
    </header>

    @if (! empty($rows))
        <ul class="mt-8 divide-y divide-brand-ink/15 border-t border-brand-ink/15">
            @foreach ($rows as $row)
                @php
                    $statusKey = $row['status'] ?? 'scheduled';
                    $style = $statusStyles[$statusKey] ?? $statusStyles['scheduled'];
                    $weekday = $row['delivery_date']?->locale(app()->getLocale())->translatedFormat('D, d. M') ?? '';
                @endphp
                <li class="grid grid-cols-12 items-baseline gap-3 py-5 sm:gap-6">
                    <div class="col-span-2 sm:col-span-1">
                        <p class="eyebrow text-brand-ink/65">{{ __('customer.dashboard.almanac.iso_week', ['n' => $row['iso_week'] ?? '']) }}</p>
                    </div>
                    <div class="col-span-4 sm:col-span-3">
                        <p class="font-display text-base text-brand-ink sm:text-lg">{{ $weekday }}</p>
                    </div>
                    <div class="col-span-12 sm:col-span-5">
                        <p class="text-sm text-brand-ink/80 sm:text-base">{{ $row['label'] ?? '' }}</p>
                    </div>
                    <div class="col-span-12 sm:col-span-3 sm:text-right">
                        <p class="eyebrow {{ $style['class'] }}">{{ __($style['label_key']) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
