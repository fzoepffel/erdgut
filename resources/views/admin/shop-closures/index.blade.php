<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.shop_closures.title') }}
            </h1>
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('shop.admin.nav_label') }}
            </a>
        </div>
    </x-slot>

    @php
        $weekdayOptions = [
            ['v' => 1, 'l' => __('admin.shop_closures.weekday_mon')],
            ['v' => 2, 'l' => __('admin.shop_closures.weekday_tue')],
            ['v' => 3, 'l' => __('admin.shop_closures.weekday_wed')],
            ['v' => 4, 'l' => __('admin.shop_closures.weekday_thu')],
            ['v' => 5, 'l' => __('admin.shop_closures.weekday_fri')],
            ['v' => 6, 'l' => __('admin.shop_closures.weekday_sat')],
            ['v' => 0, 'l' => __('admin.shop_closures.weekday_sun')],
        ];
    @endphp

    <div class="py-8 sm:py-12"
         x-data="closureCalendar({
             initialCalendar: @js($calendar),
             initialRules: @js($rules),
             toggleUrl: @js(route('app.admin.shop-closures.toggle')),
             resetUrl: @js(route('app.admin.shop-closures.reset')),
             setDayUrl: @js(route('app.admin.shop-closures.set-day')),
             rulesUrl: @js(route('app.admin.shop-closures.rules.store')),
             rulesDestroyBase: @js(url('app/admin/shop-closures/rules')),
             indexUrl: @js(route('app.admin.shop-closures.index')),
             weekdayOptions: @js($weekdayOptions),
             csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            <p class="text-sm text-gray-600">{{ __('admin.shop_closures.intro_calendar') }}</p>

            {{-- ── Recurring auto-closure rules ──────────────────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.rules_heading') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.rules_hint') }}</p>
                </div>

                <div class="p-4 sm:p-6">
                    {{-- Existing rules --}}
                    <ul class="space-y-2" x-show="rules.length" x-cloak>
                        <template x-for="rule in rules" :key="rule.id">
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm">
                                <span class="text-gray-800">
                                    <span class="font-medium" x-text="rule.weekday_label"></span>
                                    <span class="text-gray-500" x-text="rulesSummary(rule)"></span>
                                    <span x-show="!rule.is_active" x-cloak
                                          class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-[0.65rem] uppercase tracking-wide text-gray-500">{{ __('admin.shop_closures.rule_inactive') }}</span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <button type="button" @click="toggleActive(rule)" class="text-xs font-medium text-gray-600 hover:text-gray-900"
                                            x-text="rule.is_active ? @js(__('admin.shop_closures.rule_pause')) : @js(__('admin.shop_closures.rule_resume'))"></button>
                                    <button type="button" @click="editRule(rule)" class="text-xs font-medium text-emerald-700 hover:text-emerald-900">{{ __('admin.shop_closures.rule_edit') }}</button>
                                    <button type="button" @click="deleteRule(rule)" class="text-xs font-medium text-rose-600 hover:text-rose-800">{{ __('admin.shop_closures.rule_delete') }}</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p class="text-sm text-gray-500" x-show="!rules.length" x-cloak>{{ __('admin.shop_closures.rules_empty') }}</p>

                    {{-- Add / edit form --}}
                    <form @submit.prevent="saveRule()" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                        <label class="block text-xs text-gray-600">
                            {{ __('admin.shop_closures.rule_field_weekday') }}
                            <select x-model.number="form.weekday" class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <template x-for="opt in weekdayOptions" :key="opt.v">
                                    <option :value="opt.v" x-text="opt.l"></option>
                                </template>
                            </select>
                        </label>
                        <label class="block text-xs text-gray-600">
                            {{ __('admin.shop_closures.rule_field_lead_days') }}
                            <input type="number" min="0" max="30" x-model.number="form.lead_days"
                                   class="mt-1 block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <label class="block text-xs text-gray-600">
                            {{ __('admin.shop_closures.rule_field_cutoff_time') }}
                            <input type="time" x-model="form.cutoff_time"
                                   class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <button type="submit"
                                class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            {{ __('admin.shop_closures.rule_save') }}
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-gray-400">{{ __('admin.shop_closures.rule_form_hint') }}</p>
                </div>
            </div>

            {{-- ── Calendar ──────────────────────────────────────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.calendar_heading') }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.calendar_hint') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="goMonth(calendar.prev_month)"
                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                aria-label="{{ __('admin.shop_closures.prev_month') }}">
                            &larr;
                        </button>
                        <span class="min-w-[9rem] text-center text-sm font-medium text-gray-900" x-text="calendar.label"></span>
                        <button type="button"
                                @click="goMonth(calendar.next_month)"
                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                aria-label="{{ __('admin.shop_closures.next_month') }}">
                            &rarr;
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                        <template x-for="label in calendar.weekdays" :key="label">
                            <div x-text="label"></div>
                        </template>
                    </div>

                    <div class="space-y-1" :key="renderKey">
                        <template x-for="(week, wi) in calendar.weeks" :key="`${renderKey}-${wi}`">
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="cell in week" :key="`${renderKey}-${cell.iso}`">
                                    <div class="relative aspect-square">
                                        <button type="button"
                                                @click="!cell.is_disabled && openDayEditor(cell)"
                                                :disabled="cell.is_disabled || loadingDate === cell.iso"
                                                :aria-pressed="cell.closed ? 'true' : 'false'"
                                                :aria-disabled="cell.is_disabled ? 'true' : 'false'"
                                                :aria-label="cell.iso"
                                                :title="cell.cutoff_label ? `{{ __('admin.shop_closures.auto_closes_at') }} ${cell.cutoff_label}` : null"
                                                class="flex h-full w-full items-center justify-center rounded-md text-sm tabular-nums transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:cursor-not-allowed"
                                                :class="{
                                                    'bg-red-100 text-red-900 ring-1 ring-red-200 hover:bg-red-200': cell.state === 'manual_closed' && cell.in_month,
                                                    'bg-rose-300 text-rose-950 ring-1 ring-rose-400 hover:bg-rose-400': cell.state === 'rule_closed' && cell.in_month,
                                                    'bg-amber-100 text-amber-900 ring-1 ring-amber-300 hover:bg-amber-200': cell.state === 'rule_pending' && cell.in_month,
                                                    'bg-amber-50 text-amber-900 ring-2 ring-amber-400 hover:bg-amber-100': cell.state === 'manual_scheduled' && cell.in_month,
                                                    'bg-white text-emerald-800 ring-2 ring-emerald-400 hover:bg-emerald-50': cell.state === 'forced_open' && cell.in_month,
                                                    'bg-white text-gray-800 hover:bg-gray-50 ring-1 ring-gray-100': cell.state === 'open' && cell.in_month && !cell.is_disabled,
                                                    'bg-gray-100 text-gray-300 ring-1 ring-gray-100': cell.is_disabled && cell.in_month,
                                                    'bg-gray-50 text-gray-300': !cell.in_month,
                                                    'ring-2 ring-emerald-500 ring-offset-1': cell.is_today && cell.in_month && !cell.is_disabled,
                                                    'opacity-60': loadingDate === cell.iso,
                                                }">
                                            <span x-text="cell.day"></span>
                                            <span x-show="cell.has_rule && cell.in_month && !cell.is_disabled" x-cloak
                                                  class="absolute bottom-0.5 left-0 right-0 text-center text-[0.5rem] uppercase tracking-wide leading-none opacity-70">{{ __('admin.shop_closures.rule_tag') }}</span>
                                        </button>
                                        {{-- Override marker → click to follow the rule again. --}}
                                        <button type="button"
                                                x-show="cell.has_override && cell.in_month && !cell.is_disabled"
                                                x-cloak
                                                @click.stop="resetDay(cell.iso)"
                                                :title="@js(__('admin.shop_closures.follow_rule'))"
                                                class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-white/80 text-[0.6rem] text-gray-600 ring-1 ring-gray-300 hover:bg-white hover:text-gray-900">
                                            &#8635;
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-red-100 ring-1 ring-red-200"></span>
                            {{ __('admin.shop_closures.legend_manual_closed') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-rose-300 ring-1 ring-rose-400"></span>
                            {{ __('admin.shop_closures.legend_rule_closed') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-amber-100 ring-1 ring-amber-300"></span>
                            {{ __('admin.shop_closures.legend_rule_pending') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-amber-50 ring-2 ring-amber-400"></span>
                            {{ __('admin.shop_closures.legend_scheduled') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-white ring-2 ring-emerald-400"></span>
                            {{ __('admin.shop_closures.legend_forced_open') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-white ring-1 ring-gray-100"></span>
                            {{ __('admin.shop_closures.legend_open') }}
                        </span>
                    </div>
                </div>
            </div>

        </div>

        {{-- ── Day editor (set a one-off closing time, close now, keep open) ── --}}
        <div x-show="dayEditor.open" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="closeDayEditor()">
            <div class="absolute inset-0 bg-black/30" @click="closeDayEditor()"></div>
            <div class="relative w-full max-w-sm rounded-lg bg-white p-5 shadow-xl">
                <h3 class="text-sm font-semibold text-gray-900" x-text="dayEditor.label"></h3>
                <p class="mt-1 text-xs text-gray-500" x-show="dayEditor.has_rule" x-cloak>
                    {{ __('admin.shop_closures.editor_rule_note') }}
                </p>

                <label class="mt-4 block text-xs text-gray-600">
                    {{ __('admin.shop_closures.editor_closes_at') }}
                    <input type="datetime-local" x-model="dayEditor.closesAt"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                </label>
                <button type="button" @click="applyDay('closes_at')"
                        class="mt-3 w-full rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    {{ __('admin.shop_closures.editor_set_closing') }}
                </button>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
                    <button type="button" @click="applyDay('close_now')"
                            class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                        {{ __('admin.shop_closures.editor_close_now') }}
                    </button>
                    <button type="button" @click="applyDay('keep_open')"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('admin.shop_closures.editor_keep_open') }}
                    </button>
                    <button type="button" x-show="dayEditor.has_rule || dayEditor.has_override" x-cloak
                            @click="applyDay('reset')"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('admin.shop_closures.follow_rule') }}
                    </button>
                    <button type="button" @click="closeDayEditor()"
                            class="ml-auto px-2 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                        {{ __('admin.shop_closures.editor_cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function closureCalendar(config) {
        return {
            calendar: config.initialCalendar,
            rules: config.initialRules,
            weekdayOptions: config.weekdayOptions,
            loadingDate: null,
            renderKey: 0,
            form: { weekday: 3, lead_days: 2, cutoff_time: '18:00' },
            dayEditor: { open: false, iso: null, label: '', closesAt: '', has_rule: false, has_override: false },

            goMonth(month) {
                window.location.href = config.indexUrl + '?month=' + encodeURIComponent(month);
            },

            rulesSummary(rule) {
                return ' · ' + @js(__('admin.shop_closures.rule_summary_prefix')) + ' '
                    + rule.lead_days + ' ' + @js(__('admin.shop_closures.rule_summary_days')) + ' · ' + rule.cutoff_time;
            },

            applyResponse(data) {
                if (data.calendar) this.calendar = data.calendar;
                if (data.rules) this.rules = data.rules;
                this.renderKey++;
            },

            async post(url, body, method = 'POST') {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(body),
                });
                if (! response.ok) return null;
                return response.json();
            },

            async toggleDay(iso) {
                if (this.loadingDate) return;
                this.loadingDate = iso;
                try {
                    const data = await this.post(config.toggleUrl, { date: iso, month: this.calendar.month });
                    if (data) this.applyResponse(data);
                } finally {
                    this.loadingDate = null;
                }
            },

            async resetDay(iso) {
                if (this.loadingDate) return;
                this.loadingDate = iso;
                try {
                    const data = await this.post(config.resetUrl, { date: iso, month: this.calendar.month });
                    if (data) this.applyResponse(data);
                } finally {
                    this.loadingDate = null;
                }
            },

            openDayEditor(cell) {
                this.dayEditor = {
                    open: true,
                    iso: cell.iso,
                    label: cell.iso,
                    closesAt: cell.closes_at || cell.rule_cutoff || (cell.iso + 'T18:00'),
                    has_rule: !! cell.has_rule,
                    has_override: !! cell.has_override,
                };
            },

            closeDayEditor() {
                this.dayEditor.open = false;
            },

            async applyDay(action) {
                const iso = this.dayEditor.iso;
                if (! iso) return;
                this.closeDayEditor();
                this.loadingDate = iso;
                try {
                    const data = await this.post(config.setDayUrl, {
                        date: iso,
                        action,
                        closes_at: action === 'closes_at' ? this.dayEditor.closesAt : null,
                        month: this.calendar.month,
                    });
                    if (data) this.applyResponse(data);
                } finally {
                    this.loadingDate = null;
                }
            },

            editRule(rule) {
                this.form = { weekday: rule.weekday, lead_days: rule.lead_days, cutoff_time: rule.cutoff_time };
            },

            async saveRule() {
                const data = await this.post(config.rulesUrl, {
                    weekday: this.form.weekday,
                    lead_days: this.form.lead_days,
                    cutoff_time: this.form.cutoff_time,
                    is_active: true,
                    month: this.calendar.month,
                });
                if (data) this.applyResponse(data);
            },

            async toggleActive(rule) {
                const data = await this.post(config.rulesUrl, {
                    weekday: rule.weekday,
                    lead_days: rule.lead_days,
                    cutoff_time: rule.cutoff_time,
                    is_active: ! rule.is_active,
                    month: this.calendar.month,
                });
                if (data) this.applyResponse(data);
            },

            async deleteRule(rule) {
                const data = await this.post(
                    config.rulesDestroyBase + '/' + rule.id,
                    { month: this.calendar.month },
                    'DELETE',
                );
                if (data) this.applyResponse(data);
            },
        };
    }
    </script>
</x-admin-layout>
