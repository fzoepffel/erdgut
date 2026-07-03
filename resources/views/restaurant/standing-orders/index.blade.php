<x-shop-layout :title="__('restaurant.standing_title')" :narrow="true" restaurant-nav="standing">
    <div class="flex items-center justify-between">
        <div>
            <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.standing_title') }}</h1>
        </div>
        <a href="{{ route('app.restaurant.catalog') }}" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.dashboard_cta') }}</a>
    </div>

    @if (session('status'))
        <div class="mt-6 border border-shop-border bg-shop-card p-4 text-sm text-shop-foreground">{{ __('restaurant.standing_flash_'.session('status')) }}</div>
    @endif

    @php $weekdayName = fn (int $w) => \Illuminate\Support\Carbon::now()->startOfWeek(\Carbon\Carbon::SUNDAY)->addDays($w)->locale(app()->getLocale())->isoFormat('dddd'); @endphp

    @if ($standingOrders->isEmpty())
        <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.standing_empty') }}</div>
    @else
        <div class="mt-8 space-y-4">
            @foreach ($standingOrders as $so)
                <div class="shop-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-shop-foreground">{{ __('restaurant.standing_weekly_on', ['day' => $weekdayName($so->delivery_weekday)]) }}</p>
                            <p class="mt-1 text-xs text-shop-muted">{{ __('restaurant.order_status_'.($so->status->value === 'active' ? 'confirmed' : $so->status->value)) }}@if ($so->status->value === 'pending') · {{ __('restaurant.standing_pending_note') }}@endif</p>
                        </div>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[11px] font-medium',
                            'bg-emerald-100 text-emerald-800' => $so->isActive(),
                            'bg-amber-100 text-amber-800' => $so->status->value === 'paused' || $so->isPending(),
                        ])>{{ __('restaurant.standing_status_'.$so->status->value) }}</span>
                    </div>

                    <ul class="mt-3 divide-y divide-shop-border text-sm">
                        @foreach ($so->items as $item)
                            <li class="flex justify-between gap-3 py-2">
                                <span class="text-shop-foreground">{{ $item->displayQuantityLabel($so->locale) }} · {{ $item->displayName() }}</span>
                                <span class="text-xs text-shop-muted">@if ($item->ends_on){{ __('restaurant.standing_until', ['date' => $item->ends_on->format('d.m.Y')]) }}@else{{ __('restaurant.standing_indefinite') }}@endif</span>
                            </li>
                        @endforeach
                    </ul>

                    {{-- End-date editor: pick a future occurrence of the delivery weekday. --}}
                    <form method="POST" action="{{ route('app.restaurant.standing-orders.end', $so) }}" class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                        @csrf
                        @php
                            $occurrences = collect(range(1, 12))
                                ->map(fn ($i) => \Illuminate\Support\Carbon::today()->next($so->delivery_weekday)->addWeeks($i - 1))
                                ->filter(fn ($d) => $d->isFuture());
                            $currentEnd = $so->items->first()?->ends_on;
                        @endphp
                        <label class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" for="ends-{{ $so->id }}">{{ __('restaurant.standing_end_label') }}</label>
                        <select id="ends-{{ $so->id }}" name="ends_on" class="border border-shop-border bg-shop-background px-2 py-1 text-xs text-shop-foreground focus:border-shop-primary focus:outline-none">
                            @foreach ($occurrences as $d)
                                <option value="{{ $d->toDateString() }}" @selected($currentEnd?->toDateString() === $d->toDateString())>{{ $d->locale(app()->getLocale())->isoFormat('dd, D. MMM YYYY') }}</option>
                            @endforeach
                        </select>
                        <button type="submit" data-testid="standing-end-save-{{ $so->id }}" class="border border-shop-border px-2.5 py-1 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">{{ __('restaurant.standing_end_save') }}</button>
                    </form>

                    <div class="mt-4 flex items-center gap-3">
                        @if ($so->isActive())
                            <form method="POST" action="{{ route('app.restaurant.standing-orders.pause', $so) }}">@csrf
                                <button class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted hover:text-shop-primary">{{ __('restaurant.standing_pause') }}</button>
                            </form>
                        @elseif ($so->status->value === 'paused')
                            <form method="POST" action="{{ route('app.restaurant.standing-orders.resume', $so) }}">@csrf
                                <button class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary hover:underline">{{ __('restaurant.standing_resume') }}</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('app.restaurant.standing-orders.destroy', $so) }}"
                              onsubmit="return confirm(@js(__('restaurant.standing_delete_confirm')))">
                            @csrf @method('DELETE')
                            <button class="font-mono text-[0.7rem] uppercase tracking-widest text-chapter-meat hover:underline">{{ __('restaurant.standing_delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <a href="{{ route('app.restaurant.orders.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests') }}</a>
</x-shop-layout>
