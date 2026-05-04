<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('spargel.admin.page_title') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-cream font-sans text-brand-ink antialiased">
    {{-- Disposable trial-shop admin. Intentionally minimal: no sidebar,
         no shared layout, no fancy data table — a single page where the
         operator can see every Spargel-Bestellung and click "paid" /
         "completed". Will be ripped out when the proper shop launches. --}}
    <header
        class="border-b"
        style="background-color: #709452; border-color: rgba(33, 52, 29, 0.15);"
    >
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('public.home') }}" class="flex items-center gap-3">
                <span
                    aria-hidden="true"
                    class="block h-9 w-9 shrink-0"
                    style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
                ></span>
                <span
                    role="img"
                    aria-label="Erdgut"
                    class="block h-7 w-32 sm:w-36"
                    style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                ></span>
            </a>
            <span class="text-xs uppercase tracking-eyebrow" style="color: rgba(33, 52, 29, 0.85);">
                {{ __('spargel.admin.eyebrow') }}
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow text-chapter-meat">{{ __('spargel.admin.eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
                    {{ __('spargel.admin.heading') }}
                </h1>
                <p class="mt-2 text-sm text-brand-ink/70">
                    {{ __('spargel.admin.subheading', ['count' => $orders->count()]) }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-6 border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm font-medium text-brand-ink" role="status" data-testid="spargel-admin-flash">
                {{ session('status') }}
            </div>
        @endif

        @if ($orders->isEmpty())
            <div class="mt-10 border border-brand-ink/15 bg-brand-paper px-6 py-12 text-center text-sm text-brand-ink/65">
                {{ __('spargel.admin.empty') }}
            </div>
        @else
            <div class="mt-8 overflow-x-auto border border-brand-ink/15 bg-white">
                <table class="min-w-full divide-y divide-brand-ink/10 text-sm">
                    <thead class="bg-brand-paper">
                        <tr class="text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/65">
                            <th class="px-3 py-3">{{ __('spargel.admin.col_id') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_placed') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_customer') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_order') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_fulfillment') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_payment') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_when') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('spargel.admin.col_total') }}</th>
                            <th class="px-3 py-3">{{ __('spargel.admin.col_status') }}</th>
                            <th class="px-3 py-3 text-right">{{ __('spargel.admin.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-ink/10">
                        @foreach ($orders as $order)
                            @php
                                $statusKey = match ($order->status) {
                                    \App\Enums\SpargelOrderStatus::PendingPayment => 'pending',
                                    \App\Enums\SpargelOrderStatus::Paid => 'paid',
                                    \App\Enums\SpargelOrderStatus::Failed => 'failed',
                                    \App\Enums\SpargelOrderStatus::Fulfilled => 'fulfilled',
                                    \App\Enums\SpargelOrderStatus::Cancelled => 'cancelled',
                                    \App\Enums\SpargelOrderStatus::Refunded => 'refunded',
                                };
                                $statusBadge = match ($statusKey) {
                                    'pending' => 'bg-chapter-meat/10 text-chapter-meat border border-chapter-meat/30',
                                    'paid' => 'bg-chapter-produce/10 text-chapter-produce border border-chapter-produce/40',
                                    'failed' => 'bg-chapter-meat/15 text-chapter-meat border border-chapter-meat/50 font-semibold',
                                    'fulfilled' => 'bg-chapter-bread/10 text-chapter-bread border border-chapter-bread/40',
                                    'cancelled', 'refunded' => 'bg-brand-ink/10 text-brand-ink/70 border border-brand-ink/30',
                                };
                            @endphp
                            <tr class="align-top" data-testid="spargel-admin-row-{{ $order->id }}">
                                <td class="whitespace-nowrap px-3 py-3 text-brand-ink/75">#{{ $order->id }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-brand-ink/75">
                                    {{ $order->created_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm') }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-brand-ink">{{ $order->name }}</div>
                                    <div class="text-xs text-brand-ink/60">
                                        <a href="mailto:{{ $order->email }}" class="hover:underline">{{ $order->email }}</a>
                                        @if ($order->phone)
                                            · {{ $order->phone }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-brand-ink/85">
                                    @if ((int) $order->quantity_white_grams > 0)
                                        <div>{{ (int) $order->quantity_white_grams }} g <span class="text-brand-ink/55">·</span> {{ __('spargel.type_white') }}</div>
                                    @endif
                                    @if ((int) $order->quantity_green_grams > 0)
                                        <div>{{ (int) $order->quantity_green_grams }} g <span class="text-brand-ink/55">·</span> {{ __('spargel.type_green') }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($order->fulfillment_mode === 'delivery')
                                        <span class="text-brand-ink">{{ __('spargel.fulfillment_delivery') }}</span>
                                        <div class="mt-1 text-xs text-brand-ink/60">
                                            {{ $order->delivery_street }}<br>
                                            {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                                        </div>
                                        @if ($order->delivery_notes)
                                            <div class="mt-1 text-xs italic text-brand-ink/55">{{ $order->delivery_notes }}</div>
                                        @endif
                                    @else
                                        @php $pickup = \App\Services\SpargelOrderService::pickupLocation($order->pickup_location); @endphp
                                        <span class="text-brand-ink">{{ __('spargel.fulfillment_pickup') }}</span>
                                        @if ($pickup)
                                            <div class="mt-1 text-xs text-brand-ink/65">
                                                <span class="font-semibold text-brand-ink/85">{{ $pickup['name'] }}</span><br>
                                                {{ $pickup['street'] }}<br>
                                                {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-brand-ink/80">
                                    {{ $order->payment_method === 'in_person' ? __('spargel.payment_in_person') : __('spargel.payment_online') }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-brand-ink/80">
                                    @if ($order->expected_delivery_date)
                                        {{ $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dd, D. MMM') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums font-medium text-brand-ink">
                                    {{ number_format((float) $order->total_amount_eur, 2, ',', '.') }} €
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium {{ $statusBadge }}">
                                        {{ __('spargel.admin.status_'.$statusKey) }}
                                    </span>
                                    @if ((int) $order->payment_attempts > 0)
                                        {{-- Surface payment-attempt history even after the customer
                                             clicks "Try again" (which resets status back to PendingPayment
                                             in SpargelOrderController::pay). Without this, repeated
                                             failures leave no visible trace and the row looks "Open". --}}
                                        <div class="mt-1 inline-flex items-center gap-1 border border-chapter-meat/40 bg-chapter-meat/5 px-1.5 py-0.5 text-[11px] font-semibold text-chapter-meat" data-testid="spargel-admin-failed-attempts">
                                            <span aria-hidden="true">⚠</span>
                                            {{ trans_choice('spargel.admin.failed_attempts', (int) $order->payment_attempts, ['count' => (int) $order->payment_attempts]) }}
                                        </div>
                                        @if ($order->last_payment_failed_at)
                                            <div class="mt-0.5 text-[11px] text-brand-ink/55">
                                                {{ __('spargel.admin.last_failed_at', ['date' => $order->last_payment_failed_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm')]) }}
                                            </div>
                                        @endif
                                    @endif
                                    @if ($order->paid_at)
                                        <div class="mt-1 text-[11px] text-brand-ink/55">
                                            {{ __('spargel.admin.paid_at', ['date' => $order->paid_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm')]) }}
                                        </div>
                                    @endif
                                    @if ($order->fulfilled_at)
                                        <div class="mt-1 text-[11px] text-brand-ink/55">
                                            {{ __('spargel.admin.fulfilled_at', ['date' => $order->fulfilled_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm')]) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right">
                                    <div class="flex flex-col items-end gap-1.5">
                                        @if ($order->status === \App\Enums\SpargelOrderStatus::PendingPayment)
                                            <form method="POST" action="{{ route('spargel.admin.mark-paid', $order) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 border border-chapter-produce bg-chapter-produce/10 px-3 py-1.5 text-xs font-semibold text-chapter-produce transition hover:bg-chapter-produce hover:text-brand-cream"
                                                    data-testid="spargel-admin-mark-paid-{{ $order->id }}"
                                                >
                                                    {{ __('spargel.admin.action_mark_paid') }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($order->status !== \App\Enums\SpargelOrderStatus::Fulfilled && $order->status !== \App\Enums\SpargelOrderStatus::Cancelled && $order->status !== \App\Enums\SpargelOrderStatus::Refunded)
                                            <form method="POST" action="{{ route('spargel.admin.mark-completed', $order) }}">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 border border-brand-ink bg-brand-ink px-3 py-1.5 text-xs font-semibold text-brand-cream transition hover:bg-brand-ink/90"
                                                    data-testid="spargel-admin-mark-completed-{{ $order->id }}"
                                                >
                                                    {{ __('spargel.admin.action_mark_completed') }}
                                                </button>
                                            </form>
                                        @endif
                                        @if ($order->status === \App\Enums\SpargelOrderStatus::Paid && $order->payment_method !== 'in_person')
                                            <form
                                                method="POST"
                                                action="{{ route('spargel.admin.refund', $order) }}"
                                                onsubmit="return confirm({{ Js::from(__('spargel.admin.confirm_refund', ['id' => $order->id, 'amount' => number_format((float) $order->total_amount_eur, 2, ',', '.')])) }})"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 border border-chapter-meat bg-chapter-meat/10 px-3 py-1.5 text-xs font-semibold text-chapter-meat transition hover:bg-chapter-meat hover:text-brand-cream"
                                                    data-testid="spargel-admin-refund-{{ $order->id }}"
                                                >
                                                    {{ __('spargel.admin.action_refund') }}
                                                </button>
                                            </form>
                                        @endif
                                        <form
                                            method="POST"
                                            action="{{ route('spargel.admin.destroy', $order) }}"
                                            onsubmit="return confirm({{ Js::from(__('spargel.admin.confirm_delete', ['id' => $order->id])) }})"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="{{ __('spargel.admin.action_delete') }}"
                                                aria-label="{{ __('spargel.admin.action_delete') }}"
                                                class="inline-flex h-8 w-8 items-center justify-center border border-chapter-meat/40 bg-chapter-meat/[0.06] text-chapter-meat transition hover:border-chapter-meat hover:bg-chapter-meat hover:text-brand-cream"
                                                data-testid="spargel-admin-delete-{{ $order->id }}"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>

    <footer class="mt-12 border-t border-brand-ink/10 py-6 text-center text-xs text-brand-ink/60">
        <p>{{ __('spargel.admin.footer_disposable') }}</p>
    </footer>
</body>
</html>
