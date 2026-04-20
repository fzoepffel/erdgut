<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-category-produce">{{ __('Deliveries') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Deliveries timeline') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-5xl space-y-8 px-4 sm:px-6 lg:px-8">
            <div class="border border-brand-ink/15 bg-brand-ink/[0.02] px-6 py-5">
                <p class="eyebrow text-category-produce">{{ __('Default delivery settings') }}</p>
                <p class="mt-2 text-sm leading-relaxed text-brand-ink/75">
                    {{ __('deliveries.default_settings_intro') }}
                </p>
                <a href="{{ route('app.deliveries.preferences.edit') }}" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                    {{ __('Open default delivery settings') }} <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

            @if (session('status') === 'delivery-gifted')
                <p class="border border-category-produce/40 bg-category-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Gift delivery scheduled. Your original week was skipped; the gift ships on the date you chose.') }}
                </p>
            @elseif (session('status') === 'delivery-gift-already-delivered')
                <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink">
                    {{ __('This delivery was already completed; gifting is no longer available.') }}
                </p>
            @endif

            @if ($weeklyDeliveries->isEmpty())
                <x-empty-state
                    :title="__('No upcoming deliveries')"
                    :description="__('When your subscription is active, your delivery weeks appear here. You can still set defaults for the next cycle.')"
                    variant="card"
                >
                    <x-slot name="action">
                        <div class="flex flex-wrap gap-3">
                            <x-solid-link :href="route('app.subscriptions.show')">
                                {{ __('View subscription') }}
                            </x-solid-link>
                            <x-ghost-link :href="route('app.deliveries.preferences.edit')">
                                {{ __('Open default delivery settings') }}
                            </x-ghost-link>
                        </div>
                    </x-slot>
                </x-empty-state>
            @endif

            <ol class="space-y-8">
                @foreach ($weeklyDeliveries as $row)
                    @php
                        $baseDelivery = $row['base'];
                        $effectiveDelivery = $row['effective'];
                        $isFocused = $focusedDeliveryId === $baseDelivery->id;
                        $slotDate = $baseDelivery->scheduled_for_date->copy()->startOfDay();
                        $thisWeekStart = now()->copy()->startOfWeek();
                        $thisWeekEnd = now()->copy()->endOfWeek();
                        $nextWeekStart = $thisWeekStart->copy()->addWeek();
                        $nextWeekEnd = $thisWeekEnd->copy()->addWeek();
                        if ($slotDate->betweenIncluded($thisWeekStart, $thisWeekEnd)) {
                            $relativeWeekLabel = __('This week');
                        } elseif ($slotDate->betweenIncluded($nextWeekStart, $nextWeekEnd)) {
                            $relativeWeekLabel = __('Next week');
                        } else {
                            $relativeWeekLabel = null;
                        }
                    @endphp

                    <x-reveal as="li" variant="fade-up" class="flex gap-6">
                        <div class="flex shrink-0 flex-col items-center">
                            <span class="flex h-12 w-12 items-center justify-center border border-brand-ink font-display text-base font-semibold text-brand-ink">
                                {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </span>
                            @unless ($loop->last)
                                <span class="mt-2 w-px grow min-h-[2rem] bg-brand-ink/20" aria-hidden="true"></span>
                            @endunless
                        </div>

                        <div class="min-w-0 flex-1 pb-4">
                            <section
                                id="delivery-{{ $baseDelivery->id }}"
                                @class([
                                    'border p-6 sm:p-8',
                                    'border-category-produce ring-1 ring-category-produce' => $isFocused,
                                    'border-brand-ink/15' => ! $isFocused,
                                ])
                                aria-labelledby="delivery-heading-{{ $baseDelivery->id }}"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="eyebrow text-category-produce">{{ $relativeWeekLabel ?? __('Upcoming') }}</p>
                                        <h3 id="delivery-heading-{{ $baseDelivery->id }}" class="mt-2 font-display text-2xl text-brand-ink">
                                            {{ $baseDelivery->scheduled_for_date->format('l, d M Y') }}
                                        </h3>
                                        <p class="mt-1 text-xs uppercase tracking-eyebrow text-brand-ink/55">{{ __('Delivery date') }}</p>
                                    </div>
                                    <x-status-badge :value="$effectiveDelivery->status" type="delivery" />
                                </div>

                                <dl class="mt-6 grid gap-5 border-t border-brand-ink/15 pt-5 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="eyebrow text-brand-ink/60">{{ __('Address') }}</dt>
                                        <dd class="mt-2 text-brand-ink/80">
                                            {{ $effectiveDelivery->address?->recipient_name ?? __('No recipient set') }},
                                            {{ $effectiveDelivery->address?->street_line_1 ?? __('No street set') }},
                                            {{ $effectiveDelivery->address?->city ?? __('No city set') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="eyebrow text-brand-ink/60">{{ __('Timeslot') }}</dt>
                                        <dd class="mt-2 text-brand-ink/80">{{ $effectiveDelivery->timeslot }}</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="eyebrow text-brand-ink/60">{{ __('Special instructions') }}</dt>
                                        <dd class="mt-2 text-brand-ink/80">{{ $effectiveDelivery->special_instructions ?: __('No special instructions.') }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-6 flex flex-wrap gap-2">
                                    <a href="{{ route('app.deliveries.gift', $effectiveDelivery) }}" class="btn-ghost text-xs">
                                        {{ __('Gift box') }}
                                    </a>
                                </div>
                            </section>
                        </div>
                    </x-reveal>
                @endforeach
            </ol>
        </div>
    </div>
</x-app-layout>
