<x-shop-layout :title="__('restaurant.catalog_title')">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.catalog_title') }}</h1>
            </div>

            @if ($selectableDates->isNotEmpty() && $selectedDate)
                <form method="GET" action="{{ route('app.restaurant.catalog') }}" class="flex flex-col gap-1">
                    <label for="date" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.catalog_date_label') }}</label>
                    <select id="date" name="date" onchange="this.form.submit()"
                            class="min-w-[16rem] border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary">
                        @foreach ($selectableDates as $row)
                            @php $iso = $row['date']->toDateString(); @endphp
                            <option value="{{ $iso }}" @selected($selectedDate->toDateString() === $iso)>
                                {{ $row['date']->locale(app()->getLocale())->isoFormat('dd, D. MMM') }}@if ($row['cutoff_label']) · {{ __('restaurant.orderable_until', ['time' => $row['cutoff_label']]) }}@endif
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($selectableDates->isEmpty())
            <div class="shop-card mt-10 p-8 text-center text-shop-muted">{{ __('restaurant.no_open_days') }}</div>
        @elseif ($products->isEmpty())
            <div class="shop-card mt-10 p-8 text-center text-shop-muted">{{ __('restaurant.catalog_empty') }}</div>
        @else
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $row)
                    @php
                        $product = $row['product'];
                        $unitSymbol = \App\Enums\ProductUnit::displaySymbol($row['displayUnit']);
                    @endphp
                    <div class="shop-card flex flex-col gap-3 p-5" data-testid="restaurant-catalog-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg text-shop-foreground">{{ $product->name }}</h2>
                                <p class="text-xs text-shop-muted">{{ $product->producer?->name }}</p>
                            </div>
                            @if ($row['inStock'])
                                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">{{ __('restaurant.in_stock') }}</span>
                            @else
                                <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('restaurant.on_request') }}</span>
                            @endif
                        </div>

                        <div class="mt-auto">
                            <p class="font-mono text-lg text-shop-foreground">
                                @if ($row['priceFrom'])<span class="text-sm text-shop-muted">{{ __('restaurant.price_from') }} </span>@endif
                                @eur($row['netPerDisplay']) <span class="text-sm text-shop-muted">{{ __('restaurant.net_suffix') }}/{{ $unitSymbol }}</span>
                            </p>
                            <p class="text-[11px] text-shop-muted">{{ __('restaurant.vat_note', ['rate' => $row['vatRate']]) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-shop-layout>
