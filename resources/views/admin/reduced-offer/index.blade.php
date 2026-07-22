<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.reduced_offer.page_title') }}
            </h1>
            <a href="{{ route('app.admin.shop-closures.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('admin.shop_closures.nav_label') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            <p class="text-sm text-gray-600">{{ __('admin.reduced_offer.hint') }}</p>

            {{-- ── Reduced-offer mode (banner + waitlist) ────────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                 x-data="{
                     ruleForm: { rule_id: '', starts_weekday: 1, starts_time: '14:00', reopens_weekday: 5, reopens_time: '16:00' },
                     editRule(r) { this.ruleForm = { rule_id: r.id, starts_weekday: r.starts_weekday, starts_time: r.starts_time, reopens_weekday: r.reopens_weekday, reopens_time: r.reopens_time }; },
                     resetRule() { this.ruleForm = { rule_id: '', starts_weekday: 1, starts_time: '14:00', reopens_weekday: 5, reopens_time: '16:00' }; },
                 }">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.reduced_offer.heading') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.reduced_offer.hint') }}</p>
                </div>

                <div class="space-y-6 p-4 sm:p-6">
                    @if (session('status'))
                        <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
                    @endif

                    {{-- Current status --}}
                    <div class="rounded-md border border-gray-100 bg-gray-50 px-4 py-3 text-sm">
                        @if ($reducedOffer['is_reduced'])
                            <p class="font-medium text-amber-700">{{ __('admin.reduced_offer.status_reduced') }}</p>
                            <p class="mt-1 text-gray-600">{{ __('admin.reduced_offer.status_reopens', ['date' => $reducedOffer['reopens_label']]) }}</p>
                        @else
                            <p class="font-medium text-emerald-700">{{ __('admin.reduced_offer.status_full') }}</p>
                        @endif
                        <div class="mt-1" x-data="{ showWaitlist: false }">
                            @if ($reducedOffer['subscriber_count'] > 0)
                                <button type="button" @click="showWaitlist = !showWaitlist"
                                        class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700">
                                    <span>{{ trans_choice('admin.reduced_offer.subscriber_count', $reducedOffer['subscriber_count'], ['count' => $reducedOffer['subscriber_count']]) }}</span>
                                    <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': showWaitlist }"
                                         viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <ul x-show="showWaitlist" x-cloak
                                    class="mt-2 max-h-48 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 bg-white text-xs">
                                    @foreach ($reducedOffer['subscribers'] as $subscriber)
                                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                                            <span class="truncate text-gray-700">{{ $subscriber['email'] }}</span>
                                            @if ($subscriber['since'])
                                                <span class="shrink-0 text-gray-400">{{ $subscriber['since'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-xs text-gray-500">{{ trans_choice('admin.reduced_offer.subscriber_count', 0, ['count' => 0]) }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Open now (only meaningful when reduced) --}}
                    @if ($reducedOffer['is_reduced'])
                        <form method="POST" action="{{ route('app.admin.shop-closures.reduced.open-now') }}"
                              onsubmit="return confirm(@js(__('admin.reduced_offer.open_now_confirm')))">
                            @csrf
                            <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                {{ __('admin.reduced_offer.open_now_button') }}
                            </button>
                            <span class="ml-2 text-xs text-gray-500">{{ __('admin.reduced_offer.open_now_hint') }}</span>
                        </form>
                    @endif

                    {{-- Start a one-off reduced period --}}
                    <form method="POST" action="{{ route('app.admin.shop-closures.reduced.start') }}" class="space-y-3">
                        @csrf
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.reduced_offer.start_heading') }}</p>
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="block text-xs text-gray-600">
                                {{ __('admin.reduced_offer.start_from') }}
                                <input type="datetime-local" name="starts_at"
                                       class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="block text-xs text-gray-600">
                                {{ __('admin.reduced_offer.start_reopen') }}
                                <input type="datetime-local" name="reopens_at" required
                                       class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <button type="submit" class="rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">
                                {{ __('admin.reduced_offer.start_button') }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-400">{{ __('admin.reduced_offer.start_hint') }}</p>
                        @error('reopens_at') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </form>

                    {{-- Recurring weekly window --}}
                    <div class="border-t border-gray-100 pt-6">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.reduced_offer.recurring_heading') }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ __('admin.reduced_offer.recurring_hint') }}</p>

                        @if (count($reducedOffer['rules']) > 0)
                            <ul class="mt-3 divide-y divide-gray-100">
                                @foreach ($reducedOffer['rules'] as $rule)
                                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                        <span class="{{ $rule['is_active'] ? 'text-gray-800' : 'text-gray-400 line-through' }}">
                                            {{ $rule['summary'] }}
                                        </span>
                                        <span class="flex items-center gap-2">
                                            <button type="button" @click="editRule(@js($rule))"
                                                    class="text-xs text-gray-500 hover:text-gray-700">{{ __('admin.reduced_offer.rule_edit') }}</button>
                                            <form method="POST" action="{{ url('app/admin/shop-closures/reduced/rules/'.$rule['id']) }}"
                                                  onsubmit="return confirm(@js(__('admin.reduced_offer.rule_delete_confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:text-red-800">{{ __('admin.reduced_offer.rule_delete') }}</button>
                                            </form>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-3 text-sm text-gray-400">{{ __('admin.reduced_offer.rules_empty') }}</p>
                        @endif

                        {{-- Rule create / edit form --}}
                        <form method="POST" action="{{ route('app.admin.shop-closures.reduced.rules.store') }}" class="mt-4 space-y-3">
                            @csrf
                            <input type="hidden" name="rule_id" :value="ruleForm.rule_id">
                            <input type="hidden" name="is_active" value="1">
                            <div class="flex flex-wrap items-end gap-3">
                                <label class="block text-xs text-gray-600">
                                    {{ __('admin.reduced_offer.field_start_day') }}
                                    <select name="starts_weekday" x-model="ruleForm.starts_weekday"
                                            class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                        @foreach ($weekdayOptions as $opt)
                                            <option value="{{ $opt['v'] }}">{{ $opt['l'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-xs text-gray-600">
                                    {{ __('admin.reduced_offer.field_start_time') }}
                                    <input type="time" name="starts_time" x-model="ruleForm.starts_time" required
                                           class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                                <label class="block text-xs text-gray-600">
                                    {{ __('admin.reduced_offer.field_reopen_day') }}
                                    <select name="reopens_weekday" x-model="ruleForm.reopens_weekday"
                                            class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                        @foreach ($weekdayOptions as $opt)
                                            <option value="{{ $opt['v'] }}">{{ $opt['l'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-xs text-gray-600">
                                    {{ __('admin.reduced_offer.field_reopen_time') }}
                                    <input type="time" name="reopens_time" x-model="ruleForm.reopens_time" required
                                           class="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                    {{ __('admin.reduced_offer.rule_save') }}
                                </button>
                                <button type="button" @click="resetRule()" x-show="ruleForm.rule_id"
                                        class="px-2 py-1.5 text-xs text-gray-500 hover:text-gray-700">{{ __('admin.reduced_offer.rule_new') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
