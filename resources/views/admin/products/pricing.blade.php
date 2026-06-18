<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.product_pricing.title', ['name' => $product->name]) }}
            </h1>
            <a href="{{ route('app.admin.products.edit', $product->id) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('admin.product_pricing.back_to_product') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12"
         x-data="pricingCalendar({
            calendar: @js($calendar),
            defaultPrice: {{ (float) $defaultPrice }},
            defaultPriceLabel: @js(\App\Support\Money::formatEur($defaultPrice)),
            storeUrl: @js(route('app.admin.products.pricing.store', $product->id)),
            destroyUrl: @js(route('app.admin.products.pricing.destroy', $product->id)),
            csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status') === 'price-rule-saved')
                <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ __('admin.product_pricing.saved') }}</div>
            @elseif (session('status') === 'price-rule-cleared')
                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-200">{{ __('admin.product_pricing.cleared') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.product_pricing.intro', ['price' => \App\Support\Money::formatEur($defaultPrice)]) }}
            </p>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_pricing.calendar_heading') }}</h2>
                    <div class="flex items-center gap-2">
                        <a href="?month={{ $calendar['prev_month'] }}" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&larr;</a>
                        <span class="min-w-[9rem] text-center text-sm font-medium text-gray-900">{{ $calendar['label'] }}</span>
                        <a href="?month={{ $calendar['next_month'] }}" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&rarr;</a>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                        @foreach ($calendar['weekdays'] as $label)
                            <div>{{ $label }}</div>
                        @endforeach
                    </div>

                    @php
                        // Static literal classes so Tailwind's JIT keeps them.
                        // Darker green = lower; darker red = higher; white = default.
                        $levelClasses = [
                            -4 => 'bg-green-800 text-white hover:bg-green-900',
                            -3 => 'bg-green-600 text-white hover:bg-green-700',
                            -2 => 'bg-green-400 text-green-950 hover:bg-green-500',
                            -1 => 'bg-green-200 text-green-900 hover:bg-green-300',
                            0 => 'bg-white text-gray-800 ring-1 ring-gray-100 hover:bg-gray-50',
                            1 => 'bg-red-200 text-red-900 hover:bg-red-300',
                            2 => 'bg-red-400 text-red-950 hover:bg-red-500',
                            3 => 'bg-red-600 text-white hover:bg-red-700',
                            4 => 'bg-red-800 text-white hover:bg-red-900',
                        ];
                    @endphp
                    <div class="space-y-1">
                        @foreach ($calendar['weeks'] as $week)
                            <div class="grid grid-cols-7 gap-1">
                                @foreach ($week as $cell)
                                    <button type="button"
                                            @if ($cell['is_disabled'] || ! $cell['in_month'])
                                                disabled
                                            @else
                                                @click="pick('{{ $cell['iso'] }}')"
                                            @endif
                                            @class([
                                                'relative flex aspect-square flex-col items-center justify-center rounded-md text-sm tabular-nums transition focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed',
                                                $levelClasses[$cell['level']] => $cell['in_month'] && ! $cell['is_disabled'],
                                                'bg-gray-50 text-gray-300' => ! $cell['in_month'] || $cell['is_disabled'],
                                            ])
                                            :class="inRange('{{ $cell['iso'] }}') ? 'ring-2 ring-emerald-500 ring-offset-1' : ''">
                                        <span>{{ $cell['day'] }}</span>
                                        @if ($cell['in_month'] && $cell['price'] !== null)
                                            <span class="text-[0.6rem] leading-none opacity-80">{{ number_format($cell['price'], 2, ',', '.') }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-4 w-4 rounded bg-green-600"></span>{{ __('admin.product_pricing.legend_lower') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-4 w-4 rounded bg-white ring-1 ring-gray-200"></span>{{ __('admin.product_pricing.legend_default') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-4 w-4 rounded bg-red-600"></span>{{ __('admin.product_pricing.legend_higher') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Set-price modal --}}
        <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4" style="display:none">
            <div class="absolute inset-0 bg-gray-900/40" @click="close()"></div>
            <div class="relative w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900" x-text="rangeLabel()"></h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('admin.product_pricing.default_compare', ['price' => $defaultPriceLabel ?? \App\Support\Money::formatEur($defaultPrice)]) }}</p>

                <form method="POST" action="{{ route('app.admin.products.pricing.store', $product->id) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="starts_on" :value="rangeStart">
                    <input type="hidden" name="ends_on" :value="rangeEnd">
                    <input type="hidden" name="month" value="{{ $calendar['month'] }}">
                    <div>
                        <label for="rule_price" class="block text-sm font-medium text-gray-700">
                            {{ __('admin.product_pricing.price_label') }}@if (! empty($displayUnit)) <span class="text-gray-400">(€ / {{ $displayUnit }})</span>@endif
                        </label>
                        <input id="rule_price" type="number" name="unit_price" step="0.01" min="0.01" required x-model="price"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label for="rule_label" class="block text-sm font-medium text-gray-700">{{ __('admin.product_pricing.note_label') }}</label>
                        <input id="rule_label" type="text" name="label" maxlength="120"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-2">
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('admin.product_pricing.save') }}</button>
                        <button type="button" @click="close()" class="text-sm text-gray-500 hover:text-gray-700">{{ __('admin.product_pricing.cancel') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('app.admin.products.pricing.destroy', $product->id) }}" class="mt-3 border-t border-gray-100 pt-3">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="starts_on" :value="rangeStart">
                    <input type="hidden" name="ends_on" :value="rangeEnd">
                    <input type="hidden" name="month" value="{{ $calendar['month'] }}">
                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_pricing.remove') }}</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    function pricingCalendar(config) {
        return {
            defaultPrice: config.defaultPrice,
            first: null,
            rangeStart: null,
            rangeEnd: null,
            modalOpen: false,
            price: config.defaultPrice,
            pick(iso) {
                if (this.first === null) {
                    this.first = iso;
                    return;
                }
                // Second click (same date = single day) opens the modal.
                this.rangeStart = this.first < iso ? this.first : iso;
                this.rangeEnd = this.first < iso ? iso : this.first;
                this.first = null;
                this.price = this.defaultPrice;
                this.modalOpen = true;
            },
            inRange(iso) {
                if (this.first !== null) return iso === this.first;
                if (! this.modalOpen) return false;
                return iso >= this.rangeStart && iso <= this.rangeEnd;
            },
            rangeLabel() {
                if (this.rangeStart === this.rangeEnd) return this.rangeStart;
                return this.rangeStart + ' → ' + this.rangeEnd;
            },
            close() {
                this.modalOpen = false;
                this.first = null;
                this.rangeStart = null;
                this.rangeEnd = null;
            },
        };
    }
    </script>
</x-admin-layout>
