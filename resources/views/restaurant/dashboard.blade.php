<x-shop-layout :title="__('restaurant.dashboard_title')" restaurant-nav="dashboard">
    @php
        $locale = app()->getLocale();
        $weekdayName = fn (int $dow): string => \Illuminate\Support\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY)->addDays($dow)->locale($locale)->isoFormat('dddd');
    @endphp

    {{-- ── Hero: greeting + next delivery day hook ─────────────────────── --}}
    <section class="border-b border-shop-border bg-brand-moss py-14 text-brand-cream">
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-brand-cream/70">{{ __('restaurant.dashboard_eyebrow') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="80">
                <h1 class="mt-3 font-display text-4xl leading-tight sm:text-5xl">{{ __('restaurant.dashboard_hello', ['name' => $profile?->business_name ?? '']) }}</h1>
            </x-reveal>
            @if ($nextDay)
                <x-reveal variant="fade-up" :delay="160">
                    <p class="mt-4 max-w-xl text-base text-brand-cream/85" data-testid="dashboard-next-day">
                        {{ __('restaurant.dashboard_next_day', [
                            'day' => $nextDay['date']->locale($locale)->isoFormat('dddd, D. MMMM'),
                            'count' => $nextDay['in_stock_count'],
                        ]) }}
                    </p>
                </x-reveal>
            @endif
            <x-reveal variant="fade-up" :delay="240">
                <a href="{{ route('app.restaurant.catalog') }}" data-testid="dashboard-order-cta"
                   class="mt-7 inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-7 py-3 font-mono text-[0.7rem] uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream">
                    {{ __('restaurant.dashboard_cta') }} &rarr;
                </a>
            </x-reveal>
        </div>
    </section>

    @php
        // A fresh account has nothing to report yet — skip the zero stats and
        // four empty cards so the opportunity tiles lead instead.
        $isFresh = $upcomingCount === 0 && $openRequests->isEmpty() && $standing->isEmpty() && $recent->isEmpty() && $invoices->isEmpty();
    @endphp
    <div class="mx-auto max-w-[1100px] px-4 py-12 sm:px-6 lg:px-12">
        @unless ($isFresh)
        {{-- ── Diese Woche: the concrete deliveries, simple and visual ──── --}}
        <section data-testid="dashboard-this-week">
            <p class="eyebrow text-shop-primary">{{ __('restaurant.week_this_title') }}</p>
            @if ($thisWeek->isEmpty())
                <div class="shop-card mt-3 flex flex-wrap items-center justify-between gap-3 p-5 text-sm text-shop-muted">
                    <span>{{ __('restaurant.week_empty') }}@if ($nextDay) {{ __('restaurant.week_empty_next_day', ['day' => $nextDay['date']->locale($locale)->isoFormat('dd, D. MMM')]) }}@endif</span>
                    <a href="{{ route('app.restaurant.catalog') }}" class="shrink-0 border border-shop-primary px-4 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary transition hover:bg-shop-primary hover:text-shop-primary-foreground">{{ __('restaurant.nav_shop') }} &rarr;</a>
                </div>
            @else
                <div class="mt-3 space-y-6">
                    @foreach ($thisWeek->groupBy(fn ($o) => $o->expected_delivery_date->toDateString()) as $dayOrders)
                    <div>
                        {{-- The day renders ONCE, even when several orders share it. --}}
                        <p class="font-display text-2xl text-shop-foreground">{{ $dayOrders->first()->expected_delivery_date->locale($locale)->isoFormat('dddd, D. MMMM') }}</p>
                        <div class="mt-3 space-y-4">
                    @foreach ($dayOrders as $order)
                        @php $lock = $weekLockState[$order->id] ?? ['locked' => true, 'cutoff' => null]; @endphp
                        <div class="shop-card p-5" data-testid="week-delivery-{{ $order->id }}">
                            {{-- The whole upper card links to the order detail;
                                 the footer keeps its own action buttons. --}}
                            <a href="{{ route('app.restaurant.orders.show', $order) }}" class="group block" data-testid="week-delivery-link-{{ $order->id }}"
                               aria-label="{{ __('restaurant.order_ref', ['id' => $order->id]) }}">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-xs text-shop-muted transition group-hover:text-shop-primary">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</p>
                                <span class="rounded-full bg-shop-paper px-2.5 py-0.5 text-[11px] font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
                            </div>
                            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach ($order->items as $item)
                                    @continue($item->isDeclined())
                                    <li class="flex items-center gap-3">
                                        @if ($item->product?->thumbUrl())
                                            <img src="{{ $item->product->thumbUrl() }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded object-cover">
                                        @else
                                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-shop-paper text-shop-muted" aria-hidden="true">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z" /></svg>
                                            </span>
                                        @endif
                                        <span class="min-w-0 text-sm text-shop-foreground">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            </a>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-shop-border/60 pt-3">
                                <p class="font-mono text-sm text-shop-foreground">@eur($order->items_total_net_eur) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span></p>
                                <span class="flex flex-wrap items-center gap-3">
                                    @if (! $lock['locked'])
                                        <a href="{{ route('app.restaurant.orders.show', $order) }}" class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                                            {{ __('restaurant.adjust_title') }}@if ($lock['cutoff']) · {{ __('restaurant.week_adjust_until', ['cutoff' => $lock['cutoff']]) }}@endif
                                        </a>
                                    @else
                                        <a href="{{ route('app.restaurant.orders.show', $order) }}" class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:underline">{{ __('restaurant.week_view_order') }}</a>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            {{-- Nächste Woche: slim preview --}}
            @if ($nextWeek->isNotEmpty())
                <div class="mt-6" data-testid="dashboard-next-week">
                    <p class="eyebrow text-shop-muted">{{ __('restaurant.week_next_title') }}</p>
                    <div class="mt-2 divide-y divide-shop-border border border-shop-border">
                        @foreach ($nextWeek->groupBy(fn ($o) => $o->expected_delivery_date->toDateString()) as $dayOrders)
                            <div class="px-4 py-2.5">
                                <p class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ $dayOrders->first()->expected_delivery_date->locale($locale)->isoFormat('dd, D. MMM') }}</p>
                                @foreach ($dayOrders as $order)
                                    <a href="{{ route('app.restaurant.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm hover:text-shop-primary">
                                        <span class="text-shop-foreground">{{ $order->items->reject->isDeclined()->map(fn ($i) => $i->displayName())->take(3)->implode(', ') }}</span>
                                        <span class="font-mono text-xs text-shop-muted">@eur($order->items_total_net_eur)</span>
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>


        {{-- ── Upcoming deliveries + requests + weekly + invoices ──────── --}}
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Open requests (awaiting the Erdgut team) --}}
            <div class="shop-card p-5 lg:col-span-1">
                <p class="eyebrow text-amber-700">{{ __('restaurant.dashboard_requests_title') }}</p>
                <div class="mt-3 divide-y divide-shop-border/60">
                    @forelse ($openRequests as $req)
                        <a href="{{ route('app.restaurant.orders.show', $req) }}" class="flex items-center justify-between gap-2 py-2.5 hover:text-shop-primary">
                            <span class="text-sm text-shop-foreground">{{ __('restaurant.request_ref', ['id' => $req->id]) }}</span>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800">{{ __('restaurant.order_status_'.$req->status->value) }}</span>
                        </a>
                    @empty
                        <p class="py-2.5 text-sm text-shop-muted">{{ __('restaurant.dashboard_requests_empty') }}</p>
                    @endforelse
                </div>
                <a href="{{ route('app.restaurant.requests.index') }}" class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">{{ __('restaurant.nav_requests') }} &rarr;</a>
            </div>

            {{-- Weekly deliveries --}}
            <div class="shop-card p-5 lg:col-span-1">
                <p class="eyebrow text-shop-primary">{{ __('restaurant.standing_title') }}</p>
                <div class="mt-3 divide-y divide-shop-border/60">
                    @forelse ($standing as $so)
                        <div class="py-2.5">
                            <p class="text-sm text-shop-foreground">{{ __('restaurant.dashboard_standing_line', ['day' => $weekdayName($so->delivery_weekday)]) }}</p>
                            <p class="mt-0.5 truncate text-xs text-shop-muted">{{ $so->items->map(fn ($i) => $i->displayName())->implode(', ') }}</p>
                        </div>
                    @empty
                        <p class="py-2.5 text-sm text-shop-muted">{{ __('restaurant.dashboard_standing_empty') }}</p>
                    @endforelse
                </div>
                <a href="{{ route('app.restaurant.standing-orders.index') }}" class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">{{ __('restaurant.dashboard_manage') }}@if ($standingCount > $standing->count()) ({{ $standingCount }})@endif &rarr;</a>
            </div>

            {{-- Invoices --}}
            <div class="shop-card p-5 lg:col-span-1">
                <p class="eyebrow text-shop-primary">{{ __('restaurant.dashboard_invoices_title') }}</p>
                <div class="mt-3 divide-y divide-shop-border/60">
                    @forelse ($invoices as $inv)
                        <a href="{{ route('app.restaurant.orders.invoice-pdf', $inv) }}" class="flex items-center justify-between gap-2 py-2.5 hover:text-shop-primary">
                            <span class="text-sm text-shop-foreground">{{ $inv->invoice_number }}</span>
                            <span class="text-xs text-shop-muted">{{ __('restaurant.invoice_due', ['date' => optional($inv->invoice_due_date)->format('d.m.Y')]) }}</span>
                        </a>
                    @empty
                        <p class="py-2.5 text-sm text-shop-muted">{{ __('restaurant.dashboard_invoices_empty') }}</p>
                    @endforelse
                </div>
                <a href="{{ route('app.restaurant.invoices.index') }}" class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">{{ __('restaurant.invoices_all') }} &rarr;</a>
            </div>
        </div>

        {{-- ── Recent orders + reorder ─────────────────────────────────── --}}
        @if ($recent->isNotEmpty())
            <div class="mt-10">
                <p class="eyebrow text-shop-primary">{{ __('restaurant.dashboard_recent_title') }}</p>
                <div class="shop-card mt-3 divide-y divide-shop-border/60">
                    @foreach ($recent as $order)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <a href="{{ route('app.restaurant.orders.show', $order) }}" class="min-w-0 text-sm text-shop-foreground hover:text-shop-primary">
                                {{ __('restaurant.order_ref', ['id' => $order->id]) }} · {{ $order->expected_delivery_date->format('d.m.Y') }}
                                · <span class="text-shop-muted">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
                            </a>
                            @if ($order->source_standing_order_id !== null)
                                <a href="{{ route('app.restaurant.standing-orders.index') }}"
                                   class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                                    {{ __('restaurant.edit_standing') }}
                                </a>
                            @else
                                <form method="POST" action="{{ route('app.restaurant.orders.reorder', $order) }}">
                                    @csrf
                                    <button type="submit" data-testid="dashboard-reorder-{{ $order->id }}"
                                            class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                                        {{ __('restaurant.reorder') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('app.restaurant.orders.index') }}" class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">{{ __('restaurant.dashboard_all_orders') }} &rarr;</a>
            </div>
        @endif

        @endunless

        {{-- ── Opportunity tiles: what you can do here ─────────────────── --}}
        <div class="{{ $isFresh ? 'mt-2' : 'mt-14' }}">
            <p class="eyebrow text-shop-primary">{{ __('restaurant.dashboard_opportunities_eyebrow') }}</p>
            <div class="reveal-stagger mt-4 grid gap-6 sm:grid-cols-3">
                <x-reveal as="a" href="{{ route('app.restaurant.catalog') }}" style="--i:0" class="group border-t-2 border-chapter-produce pt-5">
                    <p class="eyebrow text-chapter-produce">01</p>
                    <h3 class="mt-2 font-display text-2xl text-shop-foreground">{{ __('restaurant.opportunity_weekly_title') }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-shop-muted">{{ __('restaurant.opportunity_weekly_body') }}</p>
                    <span class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary group-hover:underline">{{ __('restaurant.dashboard_cta') }} &rarr;</span>
                </x-reveal>
                <x-reveal as="a" href="{{ route('app.restaurant.requests.index') }}" style="--i:1" class="group border-t-2 border-chapter-eggdairy pt-5">
                    <p class="eyebrow text-chapter-eggdairy">02</p>
                    <h3 class="mt-2 font-display text-2xl text-shop-foreground">{{ __('restaurant.opportunity_amounts_title') }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-shop-muted">{{ __('restaurant.opportunity_amounts_body') }}</p>
                    <span class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary group-hover:underline">{{ __('restaurant.dashboard_cta') }} &rarr;</span>
                </x-reveal>
                <x-reveal as="a" href="{{ route('app.restaurant.requests.index') }}" style="--i:2" class="group border-t-2 border-chapter-almanach pt-5">
                    <p class="eyebrow text-chapter-almanach">03</p>
                    <h3 class="mt-2 font-display text-2xl text-shop-foreground">{{ __('restaurant.opportunity_special_title') }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-shop-muted">{{ __('restaurant.opportunity_special_body') }}</p>
                    <span class="mt-3 inline-block font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary group-hover:underline">{{ __('restaurant.dashboard_cta') }} &rarr;</span>
                </x-reveal>
            </div>
        </div>
    </div>
</x-shop-layout>
