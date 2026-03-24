@php
    $firstDeliveryCard = $deliveryCards->first();
    $deadlineCard = $deliveryCards->first(fn ($c) => $c['deadline'] !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'current-box-missing')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    {{ __('No current week box is available yet.') }}
                </p>
            @endif

            @if ($needsPaymentSetup)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 sm:flex sm:items-center sm:justify-between sm:gap-4">
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">{{ __('Add a payment method') }}</span>
                        — {{ __('Complete setup so we can charge your subscription on schedule.') }}
                    </p>
                    <a href="{{ route('app.payments.setup') }}" class="mt-3 inline-flex shrink-0 items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:mt-0">
                        {{ __('Set up payments') }}
                    </a>
                </div>
            @endif

            {{-- This week at a glance --}}
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="dashboard-this-week-heading">
                <div class="border-b border-gray-100 bg-emerald-50/60 px-4 py-3 sm:px-6">
                    <h3 id="dashboard-this-week-heading" class="text-base font-semibold text-gray-900">
                        {{ __('This week at a glance') }}
                    </h3>
                    <p class="mt-0.5 text-sm text-gray-600">
                        {{ __('Next delivery, box deadlines, and your subscription status.') }}
                    </p>
                </div>
                <div class="grid gap-6 p-4 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Next delivery') }}</p>
                        @if ($firstDeliveryCard)
                            @php($delivery = $firstDeliveryCard['delivery'])
                            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                            <p class="text-sm text-gray-600">{{ $delivery->timeslot }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $delivery->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                            </p>
                            <a href="{{ route('app.deliveries.index') }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                {{ __('Manage deliveries') }}
                            </a>
                        @elseif ($primarySubscription)
                            <p class="mt-2 text-sm text-gray-600">{{ __('No upcoming deliveries scheduled yet.') }}</p>
                            <a href="{{ route('app.deliveries.preferences.edit') }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                {{ __('Set delivery preferences') }}
                            </a>
                        @else
                            <p class="mt-2 text-sm text-gray-600">{{ __('No subscription yet — we will plan deliveries once you subscribe.') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                {{ __('Start a subscription') }}
                            </a>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Box changes') }}</p>
                        @if ($deadlineCard && $deadlineCard['deadline'])
                            <p class="mt-2 text-sm text-gray-700">
                                {{ __('Modify your box by') }}
                                <span class="font-semibold text-gray-900">{{ $deadlineCard['deadline']->format('D, d M Y H:i') }}</span>
                            </p>
                            @if ($deadlineCard['box'] && $deadlineCard['canModifyBox'])
                                <a href="{{ route('app.boxes.show', ['box' => $deadlineCard['box'], 'mode' => 'edit']) }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    {{ __('Modify box now') }}
                                </a>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-gray-600">{{ __('No box modification window is open right now.') }}</p>
                            @if ($currentBox)
                                <a href="{{ route('app.boxes.current') }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    {{ __('View current box') }}
                                </a>
                            @elseif ($primarySubscription)
                                <a href="{{ route('app.subscriptions.show') }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    {{ __('View subscription') }}
                                </a>
                            @endif
                        @endif
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Subscription') }}</p>
                        @if ($primarySubscription)
                            <p class="mt-2 text-sm text-gray-700">
                                <span class="font-semibold text-gray-900">{{ __('Status') }}:</span>
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
                            <a href="{{ route('app.subscriptions.show') }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                {{ __('Manage subscription') }}
                            </a>
                        @else
                            <p class="mt-2 text-sm text-gray-600">{{ __('No subscription yet') }}</p>
                            <a href="{{ route('app.subscriptions.create') }}" class="mt-2 inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                {{ __('Start a subscription') }}
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Quick actions --}}
                <div class="border-t border-gray-100 bg-gray-50/80 px-4 py-4 sm:px-6">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Quick actions') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('app.boxes.current') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                            {{ __('Current box') }}
                        </a>
                        <a href="{{ route('app.deliveries.index') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                            {{ __('All deliveries') }}
                        </a>
                        <a href="{{ route('app.subscriptions.show') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                            {{ __('Subscription') }}
                        </a>
                        @if (config('features.shop_extras_enabled', true))
                            <a href="{{ route('app.shop.index') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                                {{ __('Shop extras') }}
                            </a>
                        @endif
                        <a href="{{ route('app.payments.history') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                            {{ __('Payments') }}
                        </a>
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 lg:order-1">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold">{{ __('Next deliveries') }}</h3>
                            <a href="{{ route('app.deliveries.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                {{ __('Manage all') }}
                            </a>
                        </div>

                        @if ($deliveryCards->isEmpty())
                            <x-empty-state
                                class="mt-4"
                                :title="__('No deliveries on the calendar')"
                                :description="__('When your subscription is active, your delivery days will show up here.')"
                                variant="inset"
                            >
                                <x-slot name="action">
                                    @if ($primarySubscription)
                                        <a href="{{ route('app.deliveries.preferences.edit') }}" class="inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                            {{ __('Review delivery preferences') }}
                                        </a>
                                    @else
                                        <a href="{{ route('app.subscriptions.create') }}" class="inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                            {{ __('Start a subscription') }}
                                        </a>
                                    @endif
                                </x-slot>
                            </x-empty-state>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($deliveryCards as $card)
                                    @php($delivery = $card['delivery'])
                                    @php($box = $card['box'])
                                    <li class="rounded-md border border-gray-200 p-4 shadow-sm">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                                            <x-status-badge :value="$delivery->status" type="delivery" />
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600">{{ $delivery->timeslot }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ $delivery->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                                        </p>
                                        @if ($card['deadline'])
                                            <p class="mt-2 text-xs text-gray-500">
                                                {{ __('Modify by:') }} {{ $card['deadline']->format('D, d M Y H:i') }}
                                            </p>
                                        @else
                                            <p class="mt-2 text-xs text-gray-500">{{ __('Modify by: not available yet') }}</p>
                                        @endif

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($box && $card['canModifyBox'])
                                                <a href="{{ route('app.boxes.show', ['box' => $box, 'mode' => 'edit']) }}" class="rounded-md border border-emerald-600 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                                    {{ __('Modify box') }}
                                                </a>
                                            @endif

                                            <a href="{{ route('app.deliveries.show', $delivery) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                {{ __('Change delivery') }}
                                            </a>

                                            @if ($delivery->status->value === 'scheduled')
                                                <form method="POST" action="{{ route('app.deliveries.skip', $delivery) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-amber-400 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                                                        {{ __('Skip week') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('app.deliveries.gift', $delivery) }}" class="rounded-md border border-purple-400 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                                                {{ __('Gift box') }}
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 lg:order-2">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold">{{ __('Current week box') }}</h3>

                        @if ($currentBox)
                            <p class="mt-2 text-sm text-gray-600">
                                {{ __('Week of :date', ['date' => $currentBox->week_start_date->format('d M Y')]) }}
                            </p>
                            <p class="mt-2">
                                <a href="{{ route('app.boxes.current') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    {{ __('View current box') }}
                                </a>
                            </p>
                            <ul class="mt-4 space-y-2">
                                @forelse ($currentBox->items as $item)
                                    <li class="flex items-center justify-between text-sm">
                                        <span>{{ $item->product?->name ?? __('Unknown product') }}</span>
                                        <span class="text-gray-600">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                    </li>
                                @empty
                                    <li class="text-sm text-gray-600">{{ __('No items in the current box yet.') }}</li>
                                @endforelse
                            </ul>
                            <p class="mt-4 text-sm font-semibold">
                                {{ __('Estimated value: EUR :amount', ['amount' => number_format((float) $currentBox->estimated_value, 2)]) }}
                            </p>
                        @else
                            <x-empty-state
                                class="mt-4"
                                :title="__('No box for this week')"
                                :description="__('Your weekly box will appear here once it is generated for your delivery week.')"
                                variant="inset"
                            >
                                <x-slot name="action">
                                    <a href="{{ route('app.subscriptions.show') }}" class="inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                        {{ __('Check subscription') }}
                                    </a>
                                </x-slot>
                            </x-empty-state>
                        @endif
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 lg:order-3">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold">{{ __('Current and upcoming boxes') }}</h3>

                        @if ($upcomingBoxes->isEmpty())
                            <x-empty-state
                                class="mt-4"
                                :title="__('No boxes scheduled')"
                                :description="__('Upcoming box weeks will be listed here as your plan rolls forward.')"
                                variant="inset"
                            >
                                @if (! $primarySubscription)
                                    <x-slot name="action">
                                        <a href="{{ route('app.subscriptions.create') }}" class="inline-block text-sm font-semibold text-emerald-700 hover:text-emerald-800">
                                            {{ __('Start a subscription') }}
                                        </a>
                                    </x-slot>
                                @endif
                            </x-empty-state>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($upcomingBoxes as $box)
                                    <li class="rounded-md border border-gray-200 p-3">
                                        <p class="font-medium">{{ __('Week of :date', ['date' => $box->week_start_date->format('d M Y')]) }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Status:') }}
                                            <x-status-badge :value="$box->status" type="box" />
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Estimated value: EUR :amount', ['amount' => number_format((float) $box->estimated_value, 2)]) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
