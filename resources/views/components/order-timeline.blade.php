@props([
    'cards' => [],
    'emptyTitle' => null,
    'emptyDescription' => null,
    'emptyAction' => null,
])

@php
    $cardsCollection = $cards instanceof \Illuminate\Support\Collection ? $cards : collect($cards);
@endphp

@if ($cardsCollection->isEmpty())
    <x-empty-state
        :title="$emptyTitle ?? __('No deliveries on the calendar')"
        :description="$emptyDescription ?? __('When your subscription is active, your delivery days will show up here.')"
        variant="inset"
    >
        @if ($emptyAction)
            <x-slot name="action">{{ $emptyAction }}</x-slot>
        @endif
    </x-empty-state>
@else
    <ul class="space-y-4">
        @foreach ($cardsCollection as $card)
            @php
                $delivery = $card['delivery'] ?? null;
                $box = $card['box'] ?? null;
                $deadline = $card['deadline'] ?? null;
                $canModify = $card['canModifyBox'] ?? false;
            @endphp
            <li class="border border-brand-ink/15 p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-display text-lg text-brand-ink">
                        @if ($delivery?->scheduled_for_date)
                            {{ $delivery->scheduled_for_date->format('D, d M Y') }}
                        @else
                            <span class="text-brand-ink/50">{{ __('Date pending') }}</span>
                        @endif
                    </p>
                    @if ($delivery)
                        <x-status-badge :value="$delivery->status" type="delivery" />
                    @endif
                </div>
                @if ($delivery?->timeslot)
                    <p class="mt-1 text-sm text-brand-ink/70">{{ $delivery->timeslot }}</p>
                @endif
                <p class="text-sm text-brand-ink/70">
                    {{ $delivery?->address?->city ? __('To :city', ['city' => $delivery->address->city]) : __('Address not set') }}
                </p>
                @if ($deadline)
                    <p class="mt-2 text-xs text-brand-ink/55">
                        {{ __('Modify by:') }} {{ $deadline->format('D, d M Y H:i') }}
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
                    @if ($delivery)
                        <a href="{{ route('app.deliveries.gift', $delivery) }}" class="btn-ghost text-xs">
                            {{ __('Gift box') }}
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
