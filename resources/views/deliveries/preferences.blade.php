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

    // Formatted dates for the panel
    $locale = app()->getLocale();
    $onFileSinceFormatted = $onFileSince
        ? $onFileSince->locale($locale)->isoFormat('MMMM YYYY')
        : null;
    $lastChangeFormatted = ($lastChange ?? null)
        ? $lastChange->locale($locale)->isoFormat('D MMM YYYY')
        : null;

    // Instructions
    $instructionsMax   = 2000;
    $instructionsValue = old('instructions', $deliveryPreference?->instructions ?? $address?->delivery_instructions ?? '');

    // Phone
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
        <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">

            {{-- Top-right tag --}}
            <span class="absolute right-6 top-6 hidden font-mono text-xs tracking-widest text-brand-ink/40 lg:block" aria-hidden="true">
                {{ __('customer.preferences.hero_tag') }}
            </span>

            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <div class="grid gap-10 lg:grid-cols-[1.1fr_auto] lg:gap-16 lg:items-start">

                    {{-- Left: editorial heading --}}
                    <div>
                        <p class="eyebrow text-brand-ink/40">{{ __('customer.preferences.hero_eyebrow') }}</p>
                        <h1 class="mt-5 font-display text-5xl leading-[1.02] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-[5rem]">
                            {{ __('customer.preferences.hero_h1_a') }}<em class="italic">{{ __('customer.preferences.hero_h1_em') }}</em>{{ __('customer.preferences.hero_h1_b') }}
                        </h1>
                        <p class="mt-6 max-w-lg text-sm leading-relaxed text-brand-ink/55">
                            {{ __('customer.preferences.hero_body') }}
                        </p>
                    </div>

                    {{-- Right: on-file panel --}}
                    <div class="shrink-0 border border-brand-ink/10 bg-brand-paper p-7 lg:p-8" style="min-width: 240px;">

                        {{-- Saved since --}}
                        <p class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35">{{ __('customer.preferences.panel_since_label') }}</p>
                        <p class="mt-1 font-display text-3xl leading-tight text-brand-ink">
                            @if ($onFileSinceFormatted)
                                {{ $onFileSinceFormatted }}
                            @else
                                <span class="text-base font-sans text-brand-ink/35">{{ __('customer.preferences.on_file_since_not_set') }}</span>
                            @endif
                        </p>

                        {{-- Divider --}}
                        <div class="my-6 h-px bg-brand-ink/10"></div>

                        {{-- Detail rows --}}
                        <dl class="space-y-3">
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35 shrink-0">{{ __('customer.preferences.panel_day_label') }}</dt>
                                <dd class="font-display text-base text-brand-ink text-right">{{ $panelDayName }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35 shrink-0">{{ __('customer.preferences.panel_window_label') }}</dt>
                                <dd class="font-display text-base text-brand-ink text-right tabular-nums">{{ $panelWindowDisplay }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35 shrink-0">{{ __('customer.preferences.panel_rider_label') }}</dt>
                                <dd class="font-display text-base text-brand-ink text-right">{{ __('customer.preferences.panel_rider_value') }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35 shrink-0">{{ __('customer.preferences.panel_last_change_label') }}</dt>
                                <dd class="font-display text-base text-brand-ink text-right">
                                    {{ $lastChangeFormatted ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        @if ($upcomingDeliveryCount > 0)
                            <div class="mt-5 border-t border-brand-ink/10 pt-4 text-xs text-chapter-produce">
                                {{ trans_choice('customer.preferences.upcoming_notice', $upcomingDeliveryCount, ['count' => $upcomingDeliveryCount]) }}
                            </div>
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
        <section class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                {{-- Section header --}}
                <div class="grid gap-8 lg:grid-cols-2 lg:gap-16 mb-14">
                    <div>
                        <p class="eyebrow text-brand-ink/35 mb-5">{{ __('customer.preferences.section_address_eyebrow') }}</p>
                        <h2 class="font-display text-brand-ink leading-tight" style="font-size: clamp(2rem, 4vw, 3rem);">
                            <span class="mr-3 font-mono text-sm text-brand-ink/20">01</span>{{ __('customer.preferences.section_address_h2') }}
                        </h2>
                    </div>
                    <div class="flex items-end">
                        <p class="text-sm leading-relaxed text-brand-ink/50">
                            {{ __('customer.preferences.section_address_body') }}
                        </p>
                    </div>
                </div>

                {{-- Fields --}}
                <div class="space-y-0 divide-y divide-brand-ink/10 border-t border-brand-ink/10">

                    {{-- RECIPIENT --}}
                    <div class="py-6">
                        <label for="recipient_name" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                            {{ __('customer.preferences.recipient_label') }}
                        </label>
                        <input
                            id="recipient_name" name="recipient_name" type="text"
                            value="{{ old('recipient_name', $address?->recipient_name ?? auth()->user()->name) }}"
                            required
                            class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                        >
                        @error('recipient_name')
                            <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- STREET + HOUSE / STAIRCASE --}}
                    <div class="grid grid-cols-[1.6fr_1fr] gap-8 py-6">
                        <div>
                            <label for="street_line_1" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.street1_label') }}
                            </label>
                            <input
                                id="street_line_1" name="street_line_1" type="text"
                                value="{{ old('street_line_1', $address?->street_line_1) }}"
                                required
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                            @error('street_line_1')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="street_line_2" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.street2_label') }}
                            </label>
                            <input
                                id="street_line_2" name="street_line_2" type="text"
                                value="{{ old('street_line_2', $address?->street_line_2) }}"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                        </div>
                    </div>

                    {{-- POSTAL CODE + CITY + COUNTRY --}}
                    <div class="grid grid-cols-3 gap-8 py-6">
                        <div>
                            <label for="postal_code" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.postal_label') }}
                            </label>
                            <input
                                id="postal_code" name="postal_code" type="text"
                                value="{{ old('postal_code', $address?->postal_code) }}"
                                required
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                            @error('postal_code')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="city" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.city_label') }}
                            </label>
                            <input
                                id="city" name="city" type="text"
                                value="{{ old('city', $address?->city) }}"
                                required
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                            @error('city')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="country_code" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.country_label') }}
                            </label>
                            <input
                                id="country_code" name="country_code" type="text"
                                value="{{ old('country_code', $address?->country_code ?? 'DE') }}"
                                required maxlength="2"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                            @error('country_code')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- BUILDING CODE + FLOOR + PHONE --}}
                    <div class="grid grid-cols-3 gap-8 py-6">
                        <div>
                            <label for="building_code" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.building_code_label') }}
                            </label>
                            <input
                                id="building_code" name="building_code" type="text"
                                value="{{ old('building_code', $address?->building_code) }}"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                        </div>
                        <div>
                            <label for="floor" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.floor_label') }}
                            </label>
                            <input
                                id="floor" name="floor" type="text"
                                value="{{ old('floor', $address?->floor) }}"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                        </div>
                        <div>
                            <label for="phone" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.phone_label') }}
                            </label>
                            <input
                                id="phone" name="phone" type="tel"
                                value="{{ $phoneValue }}"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                        </div>
                    </div>

                </div>
            </div>
        </section>


        {{-- ═══════════════════════════════════════════
             § 02  WINDOW / THE HOUR
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 py-16 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                {{-- Section header --}}
                <div class="grid gap-8 lg:grid-cols-2 lg:gap-16 mb-14">
                    <div>
                        <p class="eyebrow text-brand-ink/35 mb-5">{{ __('customer.preferences.section_window_eyebrow') }}</p>
                        <h2 class="font-display text-brand-ink leading-tight" style="font-size: clamp(2rem, 4vw, 3rem);">
                            <span class="mr-3 font-mono text-sm text-brand-ink/20">02</span>{{ __('customer.preferences.section_window_h2') }}
                        </h2>
                    </div>
                    <div class="flex items-end">
                        <p class="text-sm leading-relaxed text-brand-ink/50">
                            {{ __('customer.preferences.section_window_body') }}
                        </p>
                    </div>
                </div>

                {{-- Day picker --}}
                <div class="mb-12">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-brand-ink/35 mb-6">{{ __('customer.preferences.day_label') }}</p>
                    <div class="grid grid-cols-3 gap-4 max-w-2xl">
                        @foreach ([
                            'tuesday'  => ['TUE', __('customer.preferences.day_tuesday'),  __('customer.preferences.day_tuesday_sub')],
                            'thursday' => ['THU', __('customer.preferences.day_thursday'), __('customer.preferences.day_thursday_sub')],
                            'saturday' => ['SAT', __('customer.preferences.day_saturday'), __('customer.preferences.day_saturday_sub')],
                        ] as $dayKey => [$dayAbbr, $dayLabel, $daySub])
                            <button
                                type="button"
                                @click="selectedDay = '{{ $dayKey }}'; markDirty();"
                                :class="selectedDay === '{{ $dayKey }}'
                                    ? 'border-brand-ink bg-brand-ink/5'
                                    : 'border-brand-ink/15 hover:border-brand-ink/35'"
                                class="border p-5 text-left transition-all duration-150 flex flex-col gap-1"
                            >
                                <span class="font-mono text-[0.6rem] tracking-widest text-brand-ink/35">{{ $dayAbbr }}</span>
                                <span class="font-display text-xl text-brand-ink leading-tight">{{ $dayLabel }}</span>
                                <span class="text-xs text-brand-ink/45 mt-1">{{ $daySub }}</span>
                                <span
                                    class="mt-3 font-mono text-[0.55rem] tracking-widest"
                                    :class="selectedDay === '{{ $dayKey }}' ? 'text-chapter-produce' : 'text-brand-ink/30'"
                                    x-text="selectedDay === '{{ $dayKey }}' ? '{{ strtoupper(__('Chosen')) }}' : '{{ strtoupper(__('Pick')) }}'"
                                ></span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Window picker — vertical radio list --}}
                <div>
                    <p class="eyebrow text-[0.65rem] tracking-widest text-brand-ink/35 mb-6">{{ __('customer.preferences.window_label') }}</p>
                    <div class="divide-y divide-brand-ink/10 border-y border-brand-ink/10 max-w-2xl">
                        @foreach ([
                            'morning' => [__('customer.preferences.window_morning'), __('customer.preferences.window_morning_sub'), __('customer.preferences.window_morning_desc')],
                            'midday'  => [__('customer.preferences.window_midday'),  __('customer.preferences.window_midday_sub'),  __('customer.preferences.window_midday_desc')],
                            'evening' => [__('customer.preferences.window_evening'), __('customer.preferences.window_evening_sub'), __('customer.preferences.window_evening_desc')],
                        ] as $windowKey => [$windowLabel, $windowTime, $windowDesc])
                            <button
                                type="button"
                                @click="selectedWindow = '{{ $windowKey }}'; markDirty();"
                                class="w-full grid items-center gap-x-4 py-5 text-left transition-all duration-150"
                                style="grid-template-columns: 1.5rem 10rem 8rem 1fr;"
                            >
                                {{-- Radio indicator --}}
                                <span class="flex items-center justify-center">
                                    <span
                                        class="inline-block h-2.5 w-2.5 rounded-full border transition-all duration-150"
                                        :class="selectedWindow === '{{ $windowKey }}'
                                            ? 'bg-chapter-produce border-chapter-produce'
                                            : 'bg-transparent border-brand-ink/30'"
                                    ></span>
                                </span>
                                {{-- Name --}}
                                <span
                                    class="font-display text-xl leading-tight transition-colors"
                                    :class="selectedWindow === '{{ $windowKey }}' ? 'text-brand-ink' : 'text-brand-ink/60'"
                                >{{ $windowLabel }}</span>
                                {{-- Time --}}
                                <span class="font-mono text-sm tabular-nums text-brand-ink/50">{{ $windowTime }}</span>
                                {{-- Description --}}
                                <span class="text-sm text-brand-ink/40">{{ $windowDesc }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

            </div>
        </section>


        {{-- ═══════════════════════════════════════════
             § 03  COURIER / A NOTE FOR THE RIDER
        ═══════════════════════════════════════════ --}}
        <section class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                {{-- Section header --}}
                <div class="grid gap-8 lg:grid-cols-2 lg:gap-16 mb-14">
                    <div>
                        <p class="eyebrow text-brand-ink/35 mb-5">{{ __('customer.preferences.section_courier_eyebrow') }}</p>
                        <h2 class="font-display text-brand-ink leading-tight" style="font-size: clamp(2rem, 4vw, 3rem);">
                            <span class="mr-3 font-mono text-sm text-brand-ink/20">03</span>{{ __('customer.preferences.section_courier_h2') }}
                        </h2>
                    </div>
                    <div class="flex items-end">
                        <p class="text-sm leading-relaxed text-brand-ink/50">
                            {{ __('customer.preferences.section_courier_body') }}
                        </p>
                    </div>
                </div>

                {{-- NAME ON THE BELL + INSTRUCTIONS --}}
                <div class="divide-y divide-brand-ink/10 border-t border-brand-ink/10">

                    <div class="grid grid-cols-2 gap-8 py-6">
                        {{-- Doorbell name --}}
                        <div>
                            <label for="doorbell" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35 mb-2">
                                {{ __('customer.preferences.doorbell_label') }}
                            </label>
                            <input
                                id="doorbell" name="doorbell" type="text"
                                value="{{ $doorbellValue }}"
                                placeholder="{{ __('customer.preferences.doorbell_placeholder') }}"
                                maxlength="255"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/20 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30"
                            >
                            @error('doorbell')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Instructions --}}
                        <div x-data="{}">
                            <div class="flex items-baseline justify-between mb-2">
                                <label for="instructions" class="block text-[0.65rem] font-mono uppercase tracking-widest text-brand-ink/35">
                                    {{ __('customer.preferences.instructions_label') }}
                                </label>
                                <span class="text-[0.6rem] font-mono text-brand-ink/25">
                                    {{ $charsLabelBefore }}<span x-text="instructionsMax - instructionsLen"></span>{{ $charsLabelAfter }}
                                </span>
                            </div>
                            <textarea
                                id="instructions" name="instructions"
                                rows="3"
                                maxlength="{{ $instructionsMax }}"
                                placeholder="{{ __('customer.preferences.instructions_placeholder') }}"
                                @input="instructionsLen = $event.target.value.length"
                                class="block w-full bg-transparent py-1 font-display text-xl text-brand-ink placeholder:text-brand-ink/20 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30 resize-none"
                            >{{ $instructionsValue }}</textarea>
                            @error('instructions')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </div>

                {{-- Pull-quote --}}
                <div class="mt-10 border border-brand-ink/10 p-7 lg:p-8">
                    <p class="eyebrow text-[0.6rem] tracking-widest text-brand-ink/35 mb-4">{{ __('customer.preferences.courier_quote_eyebrow') }}</p>
                    <blockquote class="font-display italic text-xl leading-snug text-brand-ink/70">
                        {{ __('customer.preferences.courier_quote') }}
                    </blockquote>
                </div>

            </div>
        </section>

    </form>{{-- /preferences-form --}}


    {{-- ═══════════════════════════════════════════
         STICKY SAVE BAR
    ═══════════════════════════════════════════ --}}
    <div class="sticky bottom-0 z-40 border-t border-brand-ink/10 bg-white/95 backdrop-blur-sm">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12" style="padding-top: 14px; padding-bottom: 14px;">
            <div class="flex items-center justify-between gap-6">

                {{-- Context note --}}
                <p class="hidden text-sm text-brand-ink/45 lg:block">
                    {{ __('customer.preferences.save_bar_note') }}
                </p>

                {{-- Actions --}}
                <div class="flex items-center gap-4 ml-auto">
                    <a
                        href="{{ route('app.deliveries.preferences.edit') }}"
                        x-show="dirty"
                        x-cloak
                        class="text-sm text-brand-ink/45 hover:text-brand-ink transition-colors underline underline-offset-2"
                    >
                        {{ __('customer.preferences.discard_button') }}
                    </a>

                    <button
                        type="submit"
                        form="preferences-form"
                        class="bg-brand-ink text-brand-paper text-sm font-medium px-7 py-3 hover:bg-brand-ink/85 transition-colors"
                    >
                        {{ __('customer.preferences.save_button') }}
                    </button>
                </div>

            </div>
        </div>
    </div>

    </div>{{-- /x-data wrapper --}}

    <x-customer.editorial-footer-band />

</x-app-layout>
