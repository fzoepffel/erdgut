@php
    // Char-count label split around :n placeholder
    $charsRemainingRaw = __('customer.preferences.chars_remaining', ['n' => '§§']);
    [$charsLabelBefore, $charsLabelAfter] = array_pad(explode('§§', $charsRemainingRaw, 2), 2, '');

    // Resolve current timeslot → window key for Alpine initial state
    $timeslotMap = [
        '08:00-12:00' => 'morning',
        '12:00-16:00' => 'midday',
        '16:00-20:00' => 'evening',
    ];
    $savedTimeslot  = old('preferred_timeslot', $deliveryPreference?->preferred_timeslot ?? '08:00-12:00');
    $currentWindow  = $timeslotMap[$savedTimeslot] ?? 'morning';

    // Resolve subscription delivery weekday → day key for Alpine initial state
    // Carbon ISO: Mon=1 Tue=2 Wed=3 Thu=4 Fri=5 Sat=6 Sun=7
    $weekdayMap = [2 => 'tuesday', 4 => 'thursday', 6 => 'saturday'];
    $currentDay = $weekdayMap[$subscription?->delivery_weekday ?? 4] ?? 'thursday';

    // Doorbell: address.label unless it's the default "Primary"
    $doorbellValue = old('doorbell',
        ($address?->label && $address->label !== 'Primary') ? $address->label : ''
    );

    // On-file-since date
    $onFileSinceFormatted = $onFileSince
        ? $onFileSince->locale(app()->getLocale())->isoFormat('D. MMM YYYY')
        : null;

    // Instructions char limit
    $instructionsMax   = 2000;
    $instructionsValue = old('instructions', $deliveryPreference?->instructions ?? $address?->delivery_instructions ?? '');

    // Current phone
    $phoneValue = old('phone', $deliveryPreference?->phone ?? $address?->phone ?? auth()->user()->phone ?? '');

    // Flash: were preferences just saved?
    $justSaved = session('status') === 'delivery-preferences-updated';
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- Alpine state wraps the entire form --}}
    <div
        x-data="{
            dirty: false,
            saved: {{ $justSaved ? 'true' : 'false' }},
            selectedDay: '{{ $currentDay }}',
            selectedWindow: '{{ $currentWindow }}',
            instructionsLen: {{ mb_strlen($instructionsValue) }},
            instructionsMax: {{ $instructionsMax }},
            windowToSlot: {
                morning: '08:00-12:00',
                midday: '12:00-16:00',
                evening: '16:00-20:00',
            },
            get preferredTimeslot() {
                return this.windowToSlot[this.selectedWindow] ?? '08:00-12:00';
            },
            get statusLabel() {
                if (this.saved && !this.dirty) return '{{ __('customer.preferences.status_just_now') }}';
                if (this.dirty) return '{{ __('customer.preferences.status_unsaved') }}';
                return '{{ __('customer.preferences.status_saved') }}';
            },
            get statusClass() {
                if (this.saved && !this.dirty) return 'bg-chapter-produce/10 text-chapter-produce';
                if (this.dirty) return 'bg-amber-50 text-amber-700';
                return 'bg-brand-ink/5 text-brand-ink/50';
            },
            markDirty() { this.dirty = true; this.saved = false; },
        }"
        @input="markDirty()"
        @change="markDirty()"
    >

    <form
        method="POST"
        action="{{ route('app.deliveries.preferences.update') }}"
        id="preferences-form"
    >
        @csrf
        @method('PATCH')

        {{-- Hidden timeslot input — driven by Alpine window picker --}}
        <input type="hidden" name="preferred_timeslot" :value="preferredTimeslot">

        {{-- ═══════════════════════════════════════════
             HERO
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24 bg-brand-cream">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between">

                    {{-- Left: title --}}
                    <div class="max-w-2xl">
                        <p class="eyebrow text-chapter-produce mb-4">
                            {{ __('customer.preferences.hero_eyebrow') }}
                        </p>
                        <h1 class="font-display font-semibold text-brand-ink leading-tight" style="font-size: clamp(2.5rem, 6vw, 4.5rem); line-height: 1.05;">
                            {{ __('customer.preferences.hero_h1_line1') }}<br>
                            <em class="not-italic italic">{{ __('customer.preferences.hero_h1_line2') }}</em>
                        </h1>
                    </div>

                    {{-- Right: on-file panel --}}
                    <div class="shrink-0 border border-brand-ink/15 bg-white p-6 lg:p-8" style="min-width: 220px;">
                        <p class="eyebrow text-brand-ink/40 mb-3">{{ __('customer.preferences.on_file_since_label') }}</p>
                        <p class="font-display text-2xl text-brand-ink">
                            @if ($onFileSinceFormatted)
                                {{ $onFileSinceFormatted }}
                            @else
                                <span class="text-brand-ink/40 text-base font-sans">{{ __('customer.preferences.on_file_since_not_set') }}</span>
                            @endif
                        </p>
                        @if ($upcomingDeliveryCount > 0)
                            <p class="mt-4 text-sm text-chapter-produce font-medium">
                                {{ trans_choice('customer.preferences.upcoming_notice', $upcomingDeliveryCount, ['count' => $upcomingDeliveryCount]) }}
                            </p>
                        @endif
                    </div>

                </div>

                @if ($errors->any())
                    <div class="mt-8 border-l-2 border-chapter-meat/70 bg-chapter-meat/5 px-4 py-3">
                        <ul class="space-y-1 text-sm text-chapter-meat">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>


        {{-- ═══════════════════════════════════════════
             § 01  ADDRESS
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24 bg-brand-paper">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="flex flex-col gap-12 lg:flex-row lg:gap-20">

                    {{-- Section intro --}}
                    <div class="lg:w-64 shrink-0">
                        <p class="eyebrow text-brand-ink/40 mb-3">{{ __('customer.preferences.section_address_eyebrow') }}</p>
                        <h2 class="font-display font-semibold text-brand-ink text-3xl lg:text-4xl leading-tight mb-4">
                            {{ __('customer.preferences.section_address_h2') }}
                        </h2>
                        <p class="text-sm text-brand-ink/60 leading-relaxed">
                            {{ __('customer.preferences.section_address_body') }}
                        </p>
                    </div>

                    {{-- Hairline fields --}}
                    <div class="flex-1 max-w-xl">
                        <div class="space-y-6">

                            {{-- Recipient --}}
                            <div>
                                <label for="recipient_name" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                    {{ __('customer.preferences.recipient_label') }}
                                </label>
                                <input
                                    id="recipient_name" name="recipient_name" type="text"
                                    value="{{ old('recipient_name', $address?->recipient_name ?? auth()->user()->name) }}"
                                    required
                                    class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                >
                                @error('recipient_name')
                                    <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Street 1 --}}
                            <div>
                                <label for="street_line_1" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                    {{ __('customer.preferences.street1_label') }}
                                </label>
                                <input
                                    id="street_line_1" name="street_line_1" type="text"
                                    value="{{ old('street_line_1', $address?->street_line_1) }}"
                                    required
                                    class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                >
                                @error('street_line_1')
                                    <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Street 2 --}}
                            <div>
                                <label for="street_line_2" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                    {{ __('customer.preferences.street2_label') }}
                                    <span class="normal-case tracking-normal text-brand-ink/25 ml-1">(optional)</span>
                                </label>
                                <input
                                    id="street_line_2" name="street_line_2" type="text"
                                    value="{{ old('street_line_2', $address?->street_line_2) }}"
                                    class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                >
                            </div>

                            {{-- Postcode + City --}}
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label for="postal_code" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                        {{ __('customer.preferences.postal_label') }}
                                    </label>
                                    <input
                                        id="postal_code" name="postal_code" type="text"
                                        value="{{ old('postal_code', $address?->postal_code) }}"
                                        required
                                        class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                    >
                                    @error('postal_code')
                                        <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="city" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                        {{ __('customer.preferences.city_label') }}
                                    </label>
                                    <input
                                        id="city" name="city" type="text"
                                        value="{{ old('city', $address?->city) }}"
                                        required
                                        class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                    >
                                    @error('city')
                                        <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Country --}}
                            <div>
                                <label for="country_code" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                    {{ __('customer.preferences.country_label') }}
                                </label>
                                <input
                                    id="country_code" name="country_code" type="text"
                                    value="{{ old('country_code', $address?->country_code ?? 'DE') }}"
                                    required maxlength="2"
                                    class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                >
                                @error('country_code')
                                    <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Phone + Building code --}}
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label for="phone" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                        {{ __('customer.preferences.phone_label') }}
                                    </label>
                                    <input
                                        id="phone" name="phone" type="tel"
                                        value="{{ $phoneValue }}"
                                        class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                    >
                                </div>
                                <div>
                                    <label for="building_code" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                        {{ __('customer.preferences.building_code_label') }}
                                    </label>
                                    <input
                                        id="building_code" name="building_code" type="text"
                                        value="{{ old('building_code', $address?->building_code) }}"
                                        class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                    >
                                </div>
                            </div>

                            {{-- Floor --}}
                            <div>
                                <label for="floor" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                    {{ __('customer.preferences.floor_label') }}
                                </label>
                                <input
                                    id="floor" name="floor" type="text"
                                    value="{{ old('floor', $address?->floor) }}"
                                    class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/30 focus:border-brand-ink focus:outline-none focus:ring-0"
                                >
                            </div>

                        </div>{{-- /space-y-6 --}}
                    </div>{{-- /fields --}}

                </div>
            </div>
        </section>


        {{-- ═══════════════════════════════════════════
             § 02  WINDOW
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="flex flex-col gap-12 lg:flex-row lg:gap-20">

                    {{-- Section intro --}}
                    <div class="lg:w-64 shrink-0">
                        <p class="eyebrow text-brand-ink/40 mb-3">{{ __('customer.preferences.section_window_eyebrow') }}</p>
                        <h2 class="font-display font-semibold text-brand-ink text-3xl lg:text-4xl leading-tight mb-4">
                            {{ __('customer.preferences.section_window_h2') }}
                        </h2>
                        <p class="text-sm text-brand-ink/60 leading-relaxed">
                            {{ __('customer.preferences.section_window_body') }}
                        </p>
                    </div>

                    {{-- Pickers --}}
                    <div class="flex-1 max-w-xl space-y-10">

                        {{-- Day picker (visual / aspirational — no backend field yet) --}}
                        <div>
                            <p class="text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-4">
                                {{ __('customer.preferences.day_label') }}
                            </p>
                            <div class="flex gap-3 flex-wrap">
                                @foreach ([
                                    'tuesday'  => __('customer.preferences.day_tuesday'),
                                    'thursday' => __('customer.preferences.day_thursday'),
                                    'saturday' => __('customer.preferences.day_saturday'),
                                ] as $dayKey => $dayLabel)
                                    <button
                                        type="button"
                                        @click="selectedDay = '{{ $dayKey }}'; markDirty();"
                                        :class="selectedDay === '{{ $dayKey }}'
                                            ? 'border-brand-ink bg-brand-ink text-brand-paper'
                                            : 'border-brand-ink/20 bg-transparent text-brand-ink hover:border-brand-ink/50'"
                                        class="border px-5 py-3 text-sm font-medium transition-all duration-150"
                                    >
                                        {{ $dayLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Window picker (functional → updates hidden preferred_timeslot) --}}
                        <div>
                            <p class="text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-4">
                                {{ __('customer.preferences.window_label') }}
                            </p>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach ([
                                    'morning' => [__('customer.preferences.window_morning'), __('customer.preferences.window_morning_sub')],
                                    'midday'  => [__('customer.preferences.window_midday'),  __('customer.preferences.window_midday_sub')],
                                    'evening' => [__('customer.preferences.window_evening'), __('customer.preferences.window_evening_sub')],
                                ] as $windowKey => [$windowLabel, $windowSub])
                                    <button
                                        type="button"
                                        @click="selectedWindow = '{{ $windowKey }}'; markDirty();"
                                        :class="selectedWindow === '{{ $windowKey }}'
                                            ? 'border-chapter-produce bg-chapter-produce/5 text-brand-ink'
                                            : 'border-brand-ink/20 bg-transparent text-brand-ink hover:border-brand-ink/40'"
                                        class="border p-4 text-left transition-all duration-150"
                                    >
                                        <span class="block text-sm font-medium">{{ $windowLabel }}</span>
                                        <span class="block font-mono text-xs text-brand-ink/50 mt-0.5">{{ $windowSub }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                    </div>{{-- /pickers --}}

                </div>
            </div>
        </section>


        {{-- ═══════════════════════════════════════════
             § 03  COURIER
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24 bg-brand-paper">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="flex flex-col gap-12 lg:flex-row lg:gap-20">

                    {{-- Section intro --}}
                    <div class="lg:w-64 shrink-0">
                        <p class="eyebrow text-brand-ink/40 mb-3">{{ __('customer.preferences.section_courier_eyebrow') }}</p>
                        <h2 class="font-display font-semibold text-brand-ink text-3xl lg:text-4xl leading-tight mb-4">
                            {{ __('customer.preferences.section_courier_h2') }}
                        </h2>
                        <p class="text-sm text-brand-ink/60 leading-relaxed">
                            {{ __('customer.preferences.section_courier_body') }}
                        </p>
                    </div>

                    {{-- Courier fields + pull-quote --}}
                    <div class="flex-1 max-w-xl space-y-10">

                        {{-- Doorbell name --}}
                        <div>
                            <label for="doorbell" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40 mb-1">
                                {{ __('customer.preferences.doorbell_label') }}
                            </label>
                            <input
                                id="doorbell" name="doorbell" type="text"
                                value="{{ $doorbellValue }}"
                                placeholder="{{ __('customer.preferences.doorbell_placeholder') }}"
                                maxlength="255"
                                class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/25 focus:border-brand-ink focus:outline-none focus:ring-0"
                            >
                            @error('doorbell')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Editorial pull-quote --}}
                        <blockquote class="border-l-2 border-brand-ink/20 pl-5 py-1">
                            <p class="font-display italic text-xl text-brand-ink/80 leading-snug">
                                {{ __('customer.preferences.courier_quote') }}
                            </p>
                            <footer class="mt-3 text-xs font-mono uppercase tracking-widest text-brand-ink/40">
                                {{ __('customer.preferences.courier_quote_attr') }}
                            </footer>
                        </blockquote>

                        {{-- Instructions textarea + char count --}}
                        <div x-data="{}">
                            <div class="flex items-baseline justify-between mb-1">
                                <label for="instructions" class="block text-xs font-mono uppercase tracking-widest text-brand-ink/40">
                                    {{ __('customer.preferences.instructions_label') }}
                                </label>
                                <span class="text-xs font-mono text-brand-ink/30">
                                    {{ $charsLabelBefore }}<span x-text="instructionsMax - instructionsLen"></span>{{ $charsLabelAfter }}
                                </span>
                            </div>
                            <textarea
                                id="instructions" name="instructions"
                                rows="5"
                                maxlength="{{ $instructionsMax }}"
                                placeholder="{{ __('customer.preferences.instructions_placeholder') }}"
                                @input="instructionsLen = $event.target.value.length"
                                class="block w-full border-0 border-b border-brand-ink/20 bg-transparent px-0 py-2.5 text-brand-ink placeholder:text-brand-ink/25 focus:border-brand-ink focus:outline-none focus:ring-0 resize-none"
                            >{{ $instructionsValue }}</textarea>
                            @error('instructions')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>{{-- /courier fields --}}

                </div>
            </div>
        </section>

    </form>{{-- /preferences-form --}}


    {{-- ═══════════════════════════════════════════
         STICKY SAVE BAR
    ═══════════════════════════════════════════ --}}
    <div
        class="sticky bottom-0 z-40 border-t border-brand-ink/10 bg-white/95 backdrop-blur-sm"
        style="padding: 14px 24px;"
    >
        <div class="mx-auto max-w-[1400px] flex items-center justify-between gap-4">

            {{-- Status chip --}}
            <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-mono transition-all duration-300"
                :class="statusClass"
                x-text="statusLabel"
            ></span>

            {{-- Action buttons --}}
            <div class="flex items-center gap-3">
                {{-- Discard: reload page to clear unsaved changes --}}
                <a
                    href="{{ route('app.deliveries.preferences.edit') }}"
                    x-show="dirty"
                    x-cloak
                    class="text-sm text-brand-ink/50 hover:text-brand-ink underline underline-offset-2 transition-colors"
                >
                    {{ __('customer.preferences.discard_button') }}
                </a>

                <button
                    type="submit"
                    form="preferences-form"
                    class="bg-brand-ink text-brand-paper text-sm font-medium px-6 py-2.5 hover:bg-brand-ink/85 transition-colors"
                >
                    {{ __('customer.preferences.save_button') }}
                </button>
            </div>

        </div>
    </div>

    </div>{{-- /x-data wrapper --}}

    <x-customer.editorial-footer-band />

</x-app-layout>
