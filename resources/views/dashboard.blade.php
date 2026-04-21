@php
    $firstDeliveryCard = $deliveryCards->first();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-category-produce">{{ __('Dashboard') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('This week') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'current-box-missing')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('No current week box is available yet.') }}
                </p>
            @endif

            @if (session('status') === 'subscriber-area-requires-setup')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Complete your subscription and payment setup to access deliveries and billing.') }}
                </p>
            @endif

            @if ($primarySubscription && $primarySubscription->isWithinPauseWindow(now()))
                <div class="flex flex-col gap-3 border border-category-produce/40 bg-category-produce/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p class="text-sm text-brand-ink">
                        <span class="font-semibold">{{ __('subscriptions.active_skip_banner') }}</span>
                        — {{ __('subscriptions.skip_window_text', ['from' => $primarySubscription->pause_from?->format('d.m.Y'), 'until' => $primarySubscription->pause_until?->format('d.m.Y')]) }}
                    </p>
                    <form method="POST" action="{{ route('app.subscriptions.resume') }}">
                        @csrf
                        <x-primary-button type="submit">
                            {{ __('subscriptions.end_skip_cta') }}
                        </x-primary-button>
                    </form>
                </div>
            @endif

            @if (session('status') === 'subscription-onboarded')
                <p class="border border-category-produce/40 bg-category-produce/5 px-4 py-3 text-sm text-brand-ink" role="status">
                    {{ __('Your subscription is active. Deliveries and the full app are now available.') }}
                </p>
            @endif

            @isset($openTrialOrder)
                @if ($openTrialOrder)
                    <div class="flex flex-col gap-3 border border-category-produce/40 bg-category-produce/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" data-testid="dashboard-trial-strip">
                        <p class="text-sm text-brand-ink">
                            <span class="font-semibold">{{ __('Trial box') }}</span>
                            — {{ __('Order #:id · :status', ['id' => $openTrialOrder->id, 'status' => $openTrialOrder->status->label()]) }}
                            @if ($openTrialOrder->scheduled_for_date)
                                · {{ $openTrialOrder->scheduled_for_date->format('D d M') }}
                            @endif
                        </p>
                    </div>
                @endif
            @endisset

            @if ($needsPaymentSetup)
                <div class="flex flex-col gap-3 border border-category-meat/40 bg-category-meat/5 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-brand-ink">
                        <span class="font-semibold">{{ __('Add a payment method') }}</span>
                        — {{ __('Complete setup so we can charge your subscription on schedule.') }}
                    </p>
                    <x-solid-link :href="route('app.subscriptions.onboard.payment')">
                        {{ __('Set up payments') }}
                    </x-solid-link>
                </div>
            @endif

            {{-- This week at a glance --}}
            <section class="border border-brand-ink/15" aria-labelledby="dashboard-this-week-heading">
                <div class="border-b border-brand-ink/15 px-6 py-6">
                    <p class="eyebrow text-category-produce">{{ __('At a glance') }}</p>
                    <h2 id="dashboard-this-week-heading" class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                        {{ __('This week at a glance') }}
                    </h2>
                    <p class="mt-2 text-sm text-brand-ink/70">
                        {{ __('Next delivery, box deadlines, and your subscription status.') }}
                    </p>
                </div>
                <div class="grid gap-8 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="eyebrow text-category-produce">{{ __('Next delivery') }}</p>
                        @if ($firstDeliveryCard)
                            @php($delivery = $firstDeliveryCard['delivery'])
                            <p class="mt-3 font-display text-2xl text-brand-ink">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                            <p class="mt-1 text-sm text-brand-ink/70">{{ $delivery->timeslot }}</p>
                            <p class="text-sm text-brand-ink/70">
                                {{ $delivery->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                            </p>
                            <a href="{{ route('app.deliveries.index') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Manage deliveries') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($primarySubscription)
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No upcoming deliveries scheduled yet.') }}</p>
                            <a href="{{ route('app.deliveries.preferences.edit') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Set delivery preferences') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet — we will plan deliveries once you subscribe.') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Start a subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                    <div>
                        <p class="eyebrow text-category-fish">{{ __('Your box') }}</p>
                        @if ($currentBox)
                            <p class="mt-3 font-display text-2xl text-brand-ink">
                                {{ __('Week of :date', ['date' => $currentBox->week_start_date->format('d M Y')]) }}
                            </p>
                            <a href="{{ route('app.boxes.current') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('View current box') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @elseif ($primarySubscription)
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No box for this week yet.') }}</p>
                            <a href="{{ route('app.subscriptions.show') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('View subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('Start a subscription to receive a weekly box.') }}</p>
                        @endif
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <p class="eyebrow text-category-meat">{{ __('Subscription') }}</p>
                        @if ($primarySubscription)
                            <p class="mt-3 font-display text-2xl text-brand-ink">
                                @switch($primarySubscription->status)
                                    @case(\App\Enums\SubscriptionStatus::Active)
                                        {{ __('Active') }}
                                        @break
                                    @case(\App\Enums\SubscriptionStatus::Paused)
                                        {{ __('Paused') }}
                                        @break
                                    @default
                                        {{ ucfirst($primarySubscription->status->value) }}
                                @endswitch
                            </p>
                            <a href="{{ route('app.subscriptions.show') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Manage subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @else
                            <p class="mt-3 text-sm text-brand-ink/70">{{ __('No subscription yet') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Start a subscription') }} <span aria-hidden="true">&rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Quick actions --}}
                <div class="border-t border-brand-ink/15 bg-brand-ink/[0.02] px-6 py-5">
                    <p class="eyebrow text-brand-ink/60">{{ __('Quick actions') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('app.boxes.current') }}" class="btn-ghost text-xs">{{ __('Current box') }}</a>
                        <a href="{{ route('app.deliveries.index') }}" class="btn-ghost text-xs">{{ __('All deliveries') }}</a>
                        <a href="{{ route('app.subscriptions.show') }}" class="btn-ghost text-xs">{{ __('Subscription') }}</a>
                        <a href="{{ route('app.products.index') }}" class="btn-ghost text-xs">{{ __('products.index_title') }}</a>
                        <a href="{{ route('public.producers') }}" class="btn-ghost text-xs">{{ __('Producers') }}</a>
                        <a href="{{ route('app.payments.history') }}" class="btn-ghost text-xs">{{ __('Payments') }}</a>
                    </div>
                </div>
            </section>

            <div class="grid gap-8 lg:grid-cols-3">
                <section class="border border-brand-ink/15 lg:order-1">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-display text-xl text-brand-ink">{{ __('Next deliveries') }}</h3>
                            <a href="{{ route('app.deliveries.index') }}" class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                {{ __('Manage all') }}
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        @if ($deliveryCards->isEmpty())
                            <x-empty-state
                                :title="__('No deliveries on the calendar')"
                                :description="__('When your subscription is active, your delivery days will show up here.')"
                                variant="inset"
                            >
                                <x-slot name="action">
                                    @if ($primarySubscription)
                                        <a href="{{ route('app.deliveries.preferences.edit') }}" class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                            {{ __('Review delivery preferences') }}
                                        </a>
                                    @else
                                        <a href="{{ route('app.subscriptions.create') }}" class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                            {{ __('Start a subscription') }}
                                        </a>
                                    @endif
                                </x-slot>
                            </x-empty-state>
                        @else
                            <ul class="space-y-4">
                                @foreach ($deliveryCards as $card)
                                    @php($delivery = $card['delivery'])
                                    @php($box = $card['box'])
                                    <li class="border border-brand-ink/15 p-5">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="font-display text-lg text-brand-ink">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                                            <x-status-badge :value="$delivery->status" type="delivery" />
                                        </div>
                                        <p class="mt-1 text-sm text-brand-ink/70">{{ $delivery->timeslot }}</p>
                                        <p class="text-sm text-brand-ink/70">
                                            {{ $delivery->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                                        </p>
                                        @if ($card['deadline'])
                                            <p class="mt-2 text-xs text-brand-ink/55">
                                                {{ __('Modify by:') }} {{ $card['deadline']->format('D, d M Y H:i') }}
                                            </p>
                                        @else
                                            <p class="mt-2 text-xs text-brand-ink/55">{{ __('Modify by: not available yet') }}</p>
                                        @endif

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            @if ($box)
                                                <a href="{{ route('app.boxes.show', ['box' => $box]) }}" class="btn-ghost text-xs">
                                                    {{ __('View box') }}
                                                </a>
                                            @endif
                                            <a href="{{ route('app.deliveries.gift', $delivery) }}" class="btn-ghost text-xs">
                                                {{ __('Gift box') }}
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>

                <section class="border border-brand-ink/15 lg:order-2">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <h3 class="font-display text-xl text-brand-ink">{{ __('Current week box') }}</h3>
                    </div>
                    <div class="p-6">
                        @if ($currentBox)
                            <p class="text-sm text-brand-ink/70">
                                {{ __('Week of :date', ['date' => $currentBox->week_start_date->format('d M Y')]) }}
                            </p>
                            <p class="mt-2">
                                <a href="{{ route('app.boxes.current') }}" class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                    {{ __('View current box') }}
                                </a>
                            </p>
                            <ul class="mt-5 space-y-2 border-t border-brand-ink/10 pt-4">
                                @forelse ($currentBox->items as $item)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="text-brand-ink">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                        <span class="text-brand-ink/60">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                    </li>
                                @empty
                                    <li class="text-sm text-brand-ink/60">{{ __('No items in the current box yet.') }}</li>
                                @endforelse
                            </ul>
                            <p class="mt-5 border-t border-brand-ink/10 pt-4 text-sm font-semibold text-brand-ink">
                                {{ __('Estimated value: EUR :amount', ['amount' => number_format((float) $currentBox->estimated_value, 2)]) }}
                            </p>
                        @else
                            <x-empty-state
                                :title="__('No box for this week')"
                                :description="__('Your weekly box will appear here once it is generated for your delivery week.')"
                                variant="inset"
                            >
                                <x-slot name="action">
                                    <a href="{{ route('app.subscriptions.show') }}" class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                        {{ __('Check subscription') }}
                                    </a>
                                </x-slot>
                            </x-empty-state>
                        @endif
                    </div>
                </section>

                <section class="border border-brand-ink/15 lg:order-3">
                    <div class="border-b border-brand-ink/15 px-6 py-5">
                        <h3 class="font-display text-xl text-brand-ink">{{ __('Current and upcoming boxes') }}</h3>
                    </div>
                    <div class="p-6">
                        @if ($upcomingBoxes->isEmpty())
                            <x-empty-state
                                :title="__('No boxes scheduled')"
                                :description="__('Upcoming box weeks will be listed here as your plan rolls forward.')"
                                variant="inset"
                            >
                                @if (! $primarySubscription)
                                    <x-slot name="action">
                                        <a href="{{ route('app.subscriptions.create') }}" class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                                            {{ __('Start a subscription') }}
                                        </a>
                                    </x-slot>
                                @endif
                            </x-empty-state>
                        @else
                            <ul class="space-y-3">
                                @foreach ($upcomingBoxes as $box)
                                    <li class="border border-brand-ink/15 p-4">
                                        <p class="font-display text-lg text-brand-ink">{{ __('Week of :date', ['date' => $box->week_start_date->format('d M Y')]) }}</p>
                                        <p class="mt-1 text-sm text-brand-ink/70">
                                            {{ __('Status:') }}
                                            <x-status-badge :value="$box->status" type="box" />
                                        </p>
                                        <p class="text-sm text-brand-ink/70">
                                            {{ __('Estimated value: EUR :amount', ['amount' => number_format((float) $box->estimated_value, 2)]) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
