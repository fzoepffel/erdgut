<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('products.request_availability_nav') }}</h1>
            <span class="text-sm text-gray-500">{{ __('products.request_availability_sub') }}</span>
        </div>
    </x-slot>

    <div class="py-8"
         x-data="{
             modal: null,
             open(row) { this.modal = row; },
             close() { this.modal = null; },
         }"
         @keydown.escape.window="close()">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span>{{ __('products.request_availability_legend_on') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-gray-300"></span>{{ __('products.request_availability_legend_off') }}</span>
                <span>{{ __('products.request_availability_hint_click') }}</span>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3">{{ __('products.request_availability_col_product') }}</th>
                            <th class="px-4 py-3">{{ __('products.request_availability_col_season') }}</th>
                            <th class="px-4 py-3">{{ __('products.request_availability_col_rule') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $i => $row)
                            @php
                                $p = $row['product'];
                                $seasonLabel = $p->is_seasonal && $p->season_start_month !== null
                                    ? sprintf('%02d.%02d. – %02d.%02d.', $p->season_start_day, $p->season_start_month, $p->season_end_day, $p->season_end_month)
                                    : ($p->is_seasonal ? __('products.request_availability_season_always') : __('products.request_availability_not_seasonal'));
                                $ruleLabel = __('products.request_visibility_'.$row['effectiveVisibility']);
                                if ($row['until'] !== null && $row['effectiveVisibility'] !== 'auto') {
                                    $ruleLabel .= ' · '.__('products.request_availability_until_short', ['date' => $row['until']->format('d.m.Y')]);
                                }
                                $modalPayload = [
                                    'id' => $p->id,
                                    'name' => $p->name,
                                    'requestable' => $row['requestable'],
                                    'seasonEnd' => $row['seasonEnd']?->format('d.m.Y'),
                                    'nextStart' => $row['nextStart']?->format('d.m.Y'),
                                    'effective' => $row['effectiveVisibility'],
                                    'until' => $row['until']?->format('d.m.Y'),
                                ];
                            @endphp
                            <tr class="cursor-pointer hover:bg-gray-50" data-testid="request-availability-row-{{ $p->id }}"
                                @click="open({{ \Illuminate\Support\Js::from($modalPayload) }})">
                                <td class="px-4 py-3">
                                    <span class="inline-block h-3 w-3 rounded-full {{ $row['requestable'] ? 'bg-emerald-500' : 'bg-gray-300' }}"
                                          data-testid="request-availability-state-{{ $p->id }}"
                                          title="{{ $row['requestable'] ? __('products.request_availability_legend_on') : __('products.request_availability_legend_off') }}"></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800">{{ $p->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $p->producer?->name }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $seasonLabel }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $ruleLabel }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Toggle modal ────────────────────────────────────────────── --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="close()">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" data-testid="request-availability-modal">
                <template x-if="modal">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900" x-text="modal.name"></h2>

                        {{-- Currently AVAILABLE → switch it off (optionally until a date) --}}
                        <template x-if="modal.requestable">
                            <form method="POST" :action="'{{ url('app/admin/request-availability') }}/' + modal.id" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="off">
                                <p class="text-sm text-gray-600">{{ __('products.request_availability_turn_off_intro') }}</p>
                                <p class="text-xs text-gray-500" x-show="modal.seasonEnd" x-text="'{{ __('products.request_availability_would_end') }} ' + modal.seasonEnd"></p>
                                <p class="text-xs text-gray-500" x-show="!modal.seasonEnd && modal.effective === 'always'">{{ __('products.request_availability_would_run_forever') }}</p>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('products.request_availability_off_until') }}</label>
                                    <input type="date" name="until" min="{{ now()->toDateString() }}" class="block w-full rounded-md border-gray-300 text-sm">
                                    <p class="mt-1 text-xs text-gray-400">{{ __('products.request_availability_until_empty_off') }}</p>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <button type="button" @click="close()" class="text-sm text-gray-500 underline">{{ __('Cancel') }}</button>
                                    <button type="submit" class="rounded bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800" data-testid="request-availability-off">
                                        {{ __('products.request_availability_turn_off') }}
                                    </button>
                                </div>
                            </form>
                        </template>

                        {{-- Currently UNAVAILABLE → switch it on (optionally until a date) --}}
                        <template x-if="!modal.requestable">
                            <form method="POST" :action="'{{ url('app/admin/request-availability') }}/' + modal.id" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="on">
                                <p class="text-sm text-gray-600">{{ __('products.request_availability_turn_on_intro') }}</p>
                                <p class="text-xs text-gray-500" x-show="modal.nextStart" x-text="'{{ __('products.request_availability_would_start') }} ' + modal.nextStart"></p>
                                <p class="text-xs text-gray-500" x-show="!modal.nextStart">{{ __('products.request_availability_no_auto_start') }}</p>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('products.request_availability_on_until') }}</label>
                                    <input type="date" name="until" min="{{ now()->toDateString() }}" class="block w-full rounded-md border-gray-300 text-sm">
                                    <p class="mt-1 text-xs text-gray-400">{{ __('products.request_availability_until_empty_on') }}</p>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <button type="button" @click="close()" class="text-sm text-gray-500 underline">{{ __('Cancel') }}</button>
                                    <button type="submit" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800" data-testid="request-availability-on">
                                        {{ __('products.request_availability_turn_on') }}
                                    </button>
                                </div>
                            </form>
                        </template>

                        {{-- Back to the season logic when an override is active --}}
                        <template x-if="modal.effective !== 'auto'">
                            <form method="POST" :action="'{{ url('app/admin/request-availability') }}/' + modal.id" class="mt-4 border-t border-gray-100 pt-3">
                                @csrf
                                <input type="hidden" name="action" value="auto">
                                <button type="submit" class="text-xs text-gray-500 underline" data-testid="request-availability-auto">
                                    {{ __('products.request_availability_back_to_auto') }}
                                </button>
                            </form>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
