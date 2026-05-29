@php
    $nextRow       = $weeklyDeliveries->first();
    $nextBase      = $nextRow ? $nextRow['base']      : null;
    $nextEffective = $nextRow ? $nextRow['effective']  : null;
    $primary       = $addresses->firstWhere('is_primary', true);

    // Localized delivery day name, derived from the next scheduled date
    $deliveryDayName = $nextBase
        ? $nextBase->scheduled_for_date->locale(app()->getLocale())->dayName
        : null;

    // Plural form: DE appends 'e' (Donnerstag→Donnerstage), EN/FR append 's'
    $deliveryDayPlural = match(app()->getLocale()) {
        'de'    => $deliveryDayName ? $deliveryDayName . 'e'  : null,
        default => $deliveryDayName ? $deliveryDayName . 's' : null,
    };

    // Addresses heading varies by count
    $addrCount  = $addresses->count();
    $addressesH2 = match(true) {
        $addrCount === 0 => __('customer.deliveries.addresses_h2_zero'),
        $addrCount === 1 => __('customer.deliveries.addresses_h2_one'),
        $addrCount === 2 => __('customer.deliveries.addresses_h2_two'),
        default          => __('customer.deliveries.addresses_h2_many'),
    };

    $chapterRotation = ['produce', 'eggdairy', 'bread', 'fish', 'meat', 'producers', 'almanach'];
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="border-b border-brand-ink/10 bg-brand-cream">
            <div class="mx-auto max-w-[1400px] px-6 py-4 lg:px-12">
                @if (session('status') === 'delivery-gifted')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Gift delivery scheduled. Your original week was skipped; the gift ships on the date you chose.') }}</p>
                @elseif (session('status') === 'delivery-gift-already-delivered')
                    <p class="text-sm text-brand-ink" role="status">{{ __('This delivery was already completed; gifting is no longer available.') }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- § 1 — Hero --}}
    <section class="border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">

                {{-- Left --}}
                <div class="flex flex-col justify-between gap-10">
                    <div>
                        <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.eyebrow') }}</p></x-reveal>
                        <x-reveal variant="fade-up" delay="100"><h1 class="mt-5 font-display text-5xl leading-[1.05] tracking-tight text-brand-ink md:text-6xl lg:text-7xl">
                            {{ __('customer.deliveries.hero_h1_line1') }}<br>
                            <em class="font-normal italic text-chapter-produce">{{ __('customer.deliveries.hero_h1_line2') }}</em><br>
                            {{ __('customer.deliveries.hero_h1_line3', ['day' => $deliveryDayName ?? __('Thursday')]) }}
                        </h1></x-reveal>
                        <x-reveal variant="fade-up" delay="200"><p class="mt-6 max-w-sm text-sm leading-relaxed text-brand-ink/55">
                            {{ __('customer.story.deliveries.opening') }}
                        </p></x-reveal>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-7 gap-y-3">
                        <a href="{{ route('app.deliveries.preferences.edit') }}"
                           class="rounded-sm bg-chapter-produce px-7 py-3 text-sm font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                            {{ __('Edit default delivery') }}
                        </a>
                        <a href="{{ route('app.deliveries.preferences.edit') }}"
                           class="text-sm text-brand-ink/60 underline-offset-2 hover:text-brand-ink transition">
                            {{ __('Add a one-time address') }}
                        </a>
                        <a href="{{ route('app.subscriptions.show') }}"
                           class="text-sm text-brand-ink/60 underline-offset-2 hover:text-brand-ink transition">
                            {{ __('Pause deliveries') }}
                        </a>
                    </div>
                </div>

                {{-- Right: at a glance --}}
                <x-reveal variant="fade-up" delay="150"><div class="bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-chapter-produce">{{ __('At a glance') }}</p>

                    @if ($nextBase)
                        <p class="mt-5 font-display text-3xl leading-tight text-brand-ink">
                            {{ $nextBase->scheduled_for_date->locale(app()->getLocale())->isoFormat('ddd, D. MMM') }}
                            @if ($nextEffective?->timeslot)
                                <span class="text-brand-ink/40 text-xl"> · {{ $nextEffective->timeslot }}</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-5 font-display text-2xl text-brand-ink/35">{{ __('No upcoming deliveries') }}</p>
                    @endif

                    <dl class="mt-6 space-y-4 border-t border-brand-ink/10 pt-5 text-sm">
                        <div class="grid grid-cols-[7rem_1fr] gap-2">
                            <dt class="eyebrow text-brand-ink/45 pt-px">{{ __('Next delivery') }}</dt>
                            <dd class="text-brand-ink/80">
                                @if ($nextBase)
                                    {{ $nextBase->scheduled_for_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="grid grid-cols-[7rem_1fr] gap-2">
                            <dt class="eyebrow text-brand-ink/45 pt-px">{{ __('Status') }}</dt>
                            <dd>
                                @if ($nextEffective)
                                    <x-status-badge :value="$nextEffective->status" type="delivery" />
                                @else
                                    <span class="text-brand-ink/35">—</span>
                                @endif
                            </dd>
                        </div>
                        <div class="grid grid-cols-[7rem_1fr] gap-2">
                            <dt class="eyebrow text-brand-ink/45 pt-px">{{ __('Address') }}</dt>
                            <dd class="text-brand-ink/80">
                                @php $glanceAddr = $nextEffective?->address ?? $primary; @endphp
                                @if ($glanceAddr)
                                    {{ $glanceAddr->recipient_name }}<br>
                                    <span class="text-brand-ink/50">{{ $glanceAddr->street_line_1 }}, {{ $glanceAddr->city }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="grid grid-cols-[7rem_1fr] gap-2">
                            <dt class="eyebrow text-brand-ink/45 pt-px">{{ __('Doorbell') }}</dt>
                            <dd class="text-brand-ink/80">
                                @php $instrAddr = $nextEffective?->address ?? $primary; @endphp
                                {{ $instrAddr?->delivery_instructions ?: ($nextEffective?->special_instructions ?: '—') }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-[7rem_1fr] gap-2">
                            <dt class="eyebrow text-brand-ink/45 pt-px">{{ __('Upcoming') }}</dt>
                            <dd class="font-mono text-xs tabular-nums text-brand-ink/70">
                                {{ str_pad((string) $weeklyDeliveries->count(), 2, '0', STR_PAD_LEFT) }} {{ __('weeks') }}
                            </dd>
                        </div>
                    </dl>
                </div></x-reveal>

            </div>
        </div>
    </section>

    {{-- § 2 — Defaults ("The Standing Order") --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            <div class="grid gap-6 lg:grid-cols-[1fr_auto]">
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.defaults_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl leading-[1.05] text-brand-ink lg:text-5xl">
                        {{ __('Default delivery settings.') }}
                    </h2></x-reveal>
                </div>
                <x-reveal variant="fade-up" delay="200"><p class="max-w-xs self-end text-sm leading-relaxed text-brand-ink/50 lg:text-right">
                    {{ __('customer.deliveries.defaults_helper') }}
                </p></x-reveal>
            </div>

            <dl class="mt-10 grid gap-8 border-t border-brand-ink/10 pt-8 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="eyebrow text-brand-ink/45">{{ __('Timeslot') }}</dt>
                    <dd class="mt-2 font-display text-xl text-brand-ink">{{ $preferences?->preferred_timeslot ?: '—' }}</dd>
                    <p class="mt-1 text-xs text-brand-ink/40">{{ __('weekly') }}</p>
                </div>
                <div>
                    <dt class="eyebrow text-brand-ink/45">{{ __('Phone') }}</dt>
                    <dd class="mt-2 font-display text-xl text-brand-ink">{{ $preferences?->phone ?: '—' }}</dd>
                    <p class="mt-1 text-xs text-brand-ink/40">{{ __('for the driver') }}</p>
                </div>
                <div>
                    <dt class="eyebrow text-brand-ink/45">{{ __('Address') }}</dt>
                    <dd class="mt-2 font-display text-xl text-brand-ink">{{ $primary?->street_line_1 ?: '—' }}</dd>
                    <p class="mt-1 text-xs text-brand-ink/40">{{ $primary ? ($primary->postal_code.' '.$primary->city) : '—' }}</p>
                </div>
                <div>
                    <dt class="eyebrow text-brand-ink/45">{{ __('At the door') }}</dt>
                    <dd class="mt-2 font-display text-xl leading-tight text-brand-ink">{{ $preferences?->instructions ?: ($primary?->delivery_instructions ?: '—') }}</dd>
                    <p class="mt-1 text-xs text-brand-ink/40">{{ __('delivery note') }}</p>
                </div>
            </dl>

            <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3">
                <a href="{{ route('app.deliveries.preferences.edit') }}"
                   class="rounded-sm bg-chapter-produce px-7 py-3 text-sm font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                    {{ __('Edit default delivery') }}
                </a>
            </div>

        </div>
    </section>

    {{-- § 3 — Timeline ("The Route Ahead") --}}
    @if ($weeklyDeliveries->isEmpty())
        <section class="py-20 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.timeline_eyebrow') }}</p>
                <h2 class="mt-4 font-display text-4xl leading-[1.05] text-brand-ink lg:text-5xl">
                    {{ __('customer.deliveries.timeline_h2', ['days' => $deliveryDayPlural ?? __('Thursdays')]) }}
                </h2>
                <div class="mt-12">
                    <x-empty-state
                        :title="__('No upcoming deliveries')"
                        :description="__('Your delivery weeks will appear here as soon as we plan them. You can still set defaults for the next cycle.')"
                        variant="card"
                    >
                        <x-slot name="action">
                            <div class="flex flex-wrap gap-3">
                                <x-solid-link :href="route('app.subscriptions.show')">
                                    {{ __('View subscription') }}
                                </x-solid-link>
                                <x-ghost-link :href="route('app.deliveries.preferences.edit')">
                                    {{ __('Open default delivery settings') }}
                                </x-ghost-link>
                            </div>
                        </x-slot>
                    </x-empty-state>
                </div>
            </div>
        </section>
    @else
        <section class="border-b border-brand-ink/10 py-16 lg:py-24" data-testid="deliveries-timeline">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.timeline_eyebrow') }}</p></x-reveal>
                        <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl leading-[1.05] text-brand-ink lg:text-5xl">
                            {{ __('customer.deliveries.timeline_h2', ['days' => $deliveryDayPlural ?? __('Thursdays')]) }}
                        </h2></x-reveal>
                    </div>
                </div>

                <div class="mt-10 overflow-x-auto">
                    <table class="w-full min-w-[640px] border-collapse">
                        <thead>
                            <tr class="border-b border-brand-ink/10">
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Wk') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Date') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Timeslot') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40 lg:table-cell hidden">{{ __('Address') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40 lg:table-cell hidden">{{ __('Note') }}</th>
                                <th class="pb-3 text-right font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($weeklyDeliveries as $row)
                                @php
                                    $base      = $row['base'];
                                    $effective = $row['effective'];
                                    $isFocused = $focusedDeliveryId === $base->id;
                                    $rowChapter = $chapterRotation[($loop->index) % count($chapterRotation)];
                                    $eyebrowClass = [
                                        'produce'   => 'text-chapter-produce',
                                        'eggdairy'  => 'text-chapter-eggdairy',
                                        'bread'     => 'text-chapter-bread',
                                        'fish'      => 'text-chapter-fish',
                                        'meat'      => 'text-chapter-meat',
                                        'producers' => 'text-chapter-producers',
                                        'almanach'  => 'text-chapter-almanach',
                                    ][$rowChapter];
                                @endphp
                                <tr id="delivery-{{ $base->id }}"
                                    @class([
                                        'border-b border-brand-ink/8 transition',
                                        'bg-brand-cream/50' => $isFocused,
                                    ])>
                                    <td class="py-4 pr-6">
                                        <span class="font-mono text-xs tabular-nums {{ $eyebrowClass }}">
                                            W{{ $base->scheduled_for_date->weekOfYear }}
                                        </span>
                                    </td>
                                    <td class="py-4 pr-6">
                                        <span class="font-display text-base text-brand-ink">
                                            {{ $base->scheduled_for_date->locale(app()->getLocale())->isoFormat('ddd, D. MMM') }}
                                        </span>
                                    </td>
                                    <td class="py-4 pr-6 text-sm text-brand-ink/60">
                                        {{ $effective->timeslot ?: '—' }}
                                    </td>
                                    <td class="py-4 pr-6 text-sm text-brand-ink/60 lg:table-cell hidden">
                                        @if ($effective->address)
                                            {{ $effective->address->street_line_1 }}, {{ $effective->address->city }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-4 pr-6 text-sm text-brand-ink/45 lg:table-cell hidden" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $effective->special_instructions ?: '—' }}
                                    </td>
                                    <td class="py-4 text-right">
                                        <x-status-badge :value="$effective->status" type="delivery" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </section>
    @endif

    {{-- § 4 — Addresses ("The Address Book") --}}
    <section class="border-b border-brand-ink/10 py-16 lg:py-24"
             x-data="{ primaryId: {{ $addresses->firstWhere('is_primary', true)?->id ?? 'null' }} }">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            <div class="grid gap-10 lg:grid-cols-[1fr_2fr] lg:gap-16">

                {{-- Left: intro + CTA --}}
                <div class="flex flex-col justify-between gap-8">
                    <div>
                        <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.addresses_eyebrow') }}</p></x-reveal>
                        <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl leading-[1.05] text-brand-ink lg:text-5xl">
                            {{ $addressesH2 }}
                        </h2></x-reveal>
                        <x-reveal variant="fade-up" delay="200"><p class="mt-5 text-sm leading-relaxed text-brand-ink/55">
                            {{ __('customer.deliveries.addresses_body') }}
                        </p></x-reveal>
                    </div>
                    <div>
                        <a href="{{ route('app.deliveries.preferences.edit') }}"
                           class="inline-block rounded-sm border border-brand-ink/20 px-6 py-3 text-sm text-brand-ink/70 transition hover:border-brand-ink/40 hover:text-brand-ink">
                            {{ __('Add another address') }} →
                        </a>
                    </div>
                </div>

                {{-- Right: address cards --}}
                <div class="grid gap-5 sm:grid-cols-2">
                    @forelse ($addresses as $address)
                        <div
                            class="relative border p-6 transition"
                            :class="primaryId === {{ $address->id }}
                                ? 'border-chapter-produce bg-brand-paper'
                                : 'border-brand-ink/15'"
                        >
                            {{-- labels row --}}
                            <div class="flex items-center gap-2 font-mono text-[0.6rem] uppercase tracking-[0.2em] text-brand-ink/40">
                                @if ($address->label)
                                    <span>{{ $address->label }}</span>
                                    <span>·</span>
                                @endif
                                <span x-show="primaryId !== {{ $address->id }}">{{ __('Default') }}</span>
                                <span
                                    x-show="primaryId === {{ $address->id }}"
                                    class="text-chapter-produce"
                                >● {{ __('Primary') }}</span>
                            </div>

                            {{-- name --}}
                            <p class="mt-3 font-display text-xl text-brand-ink">{{ $address->recipient_name }}</p>

                            {{-- address block --}}
                            <address class="mt-3 not-italic text-sm leading-relaxed text-brand-ink/65">
                                {{ $address->street_line_1 }}@if ($address->street_line_2), {{ $address->street_line_2 }}@endif<br>
                                {{ $address->postal_code }} {{ $address->city }}@if ($address->country_code), {{ strtoupper($address->country_code) }}@endif
                            </address>

                            @if ($address->delivery_instructions)
                                <p class="mt-3 text-xs italic text-brand-ink/45">{{ $address->delivery_instructions }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="border border-dashed border-brand-ink/20 p-6">
                            <p class="text-sm text-brand-ink/40">{{ __('No addresses saved yet.') }}</p>
                        </div>
                    @endforelse
                </div>

            </div>
        </div>
    </section>

    {{-- § 5 — History ("The Ledger") --}}
    <section class="border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.deliveries.history_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl leading-[1.05] text-brand-ink lg:text-5xl">
                        {{ __('Past deliveries.') }}
                    </h2></x-reveal>
                </div>
            </div>

            @if ($pastDeliveries->isEmpty())
                <p class="mt-10 text-sm text-brand-ink/40">{{ __('No past deliveries yet.') }}</p>
            @else
                <div class="mt-10 overflow-x-auto">
                    <table class="w-full min-w-[480px] border-collapse">
                        <thead>
                            <tr class="border-b border-brand-ink/10">
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Wk') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Date') }}</th>
                                <th class="pb-3 pr-6 text-left font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40 sm:table-cell hidden">{{ __('Note') }}</th>
                                <th class="pb-3 text-right font-mono text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/40">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pastDeliveries as $past)
                                <tr class="group border-b border-brand-ink/8">
                                    <td class="py-4 pr-6">
                                        <span class="font-mono text-xs tabular-nums text-brand-ink/40">
                                            W{{ $past->scheduled_for_date->weekOfYear }}
                                        </span>
                                    </td>
                                    <td class="py-4 pr-6">
                                        <span class="font-display text-base text-brand-ink/70">
                                            {{ $past->scheduled_for_date->locale(app()->getLocale())->isoFormat('ddd, D. MMM YYYY') }}
                                        </span>
                                    </td>
                                    <td class="py-4 pr-6 text-sm text-brand-ink/45 sm:table-cell hidden">
                                        {{ $past->special_instructions ?: '—' }}
                                    </td>
                                    <td class="py-4 text-right">
                                        <x-status-badge :value="$past->status" type="delivery" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </section>

    <x-customer.editorial-footer-band />

</x-app-layout>
