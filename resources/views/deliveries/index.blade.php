@php
    $nextRow       = $weeklyDeliveries->first();
    $nextBase      = $nextRow ? $nextRow['base']      : null;
    $nextEffective = $nextRow ? $nextRow['effective']  : null;

    $chapterRotation = ['produce', 'eggdairy', 'bread', 'fish', 'meat', 'producers', 'almanach'];
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="border-b border-brand-ink/10 bg-brand-cream">
            <div class="mx-auto max-w-[1400px] px-6 py-4 lg:px-12">
                @if (session('status') === 'delivery-gifted')
                    <p class="text-sm text-brand-ink" role="status">{{ __('Gift delivery scheduled. Your original week was skipped; the gift ships on the date you chose.') }}</p>
                @elseif (session('status') === 'delivery-gift-already-delivered')
                    <p class="text-sm text-brand-ink" role="status">{{ __('This delivery was already completed; gifting is no longer available.') }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- § 1 — Hero --}}
    <section class="border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-16">

                {{-- Left: heading + lead + action --}}
                <div class="flex flex-col justify-between">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Deliveries') }}</p>
                        <h1 class="mt-4 font-display text-5xl leading-[1.05] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-7xl">
                            {{ __('customer.deliveries.hero_h1_line1') }}<br>
                            <em class="font-normal italic text-chapter-produce">{{ __('customer.deliveries.hero_h1_line2') }}</em>
                        </h1>
                        <p class="mt-6 max-w-md text-base leading-relaxed text-brand-ink/60">
                            {{ __('customer.story.deliveries.opening') }}
                        </p>
                    </div>
                    <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                        <a href="{{ route('app.deliveries.preferences.edit') }}"
                           class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                            {{ __('Default delivery settings') }}
                        </a>
                    </div>
                </div>

                {{-- Right: next delivery panel --}}
                @if ($nextBase)
                    <div class="bg-brand-paper p-8 lg:p-10">
                        <div class="flex items-start justify-between">
                            <span class="eyebrow text-chapter-produce">{{ __('Next delivery') }}</span>
                            <x-status-badge :value="$nextEffective->status" type="delivery" />
                        </div>
                        <p class="mt-6 font-display text-4xl leading-tight text-brand-ink">
                            {{ $nextBase->scheduled_for_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}
                        </p>
                        <dl class="mt-8 grid grid-cols-2 gap-x-8 gap-y-6 border-t border-brand-ink/10 pt-6">
                            <div class="col-span-2">
                                <dt class="eyebrow text-brand-ink/55">{{ __('Address') }}</dt>
                                <dd class="mt-2 text-sm leading-relaxed text-brand-ink/80">
                                    {{ $nextEffective->address?->recipient_name ?? '—' }},
                                    {{ $nextEffective->address?->street_line_1 ?? '—' }},
                                    {{ $nextEffective->address?->city ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Timeslot') }}</dt>
                                <dd class="mt-2 text-sm text-brand-ink/80">{{ $nextEffective->timeslot ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="eyebrow text-brand-ink/55">{{ __('Special instructions') }}</dt>
                                <dd class="mt-2 text-sm text-brand-ink/80">{{ $nextEffective->special_instructions ?: '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

            </div>
        </div>
    </section>

    {{-- § 2 — Timeline --}}
    @if ($weeklyDeliveries->isEmpty())
        <section class="py-20 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                <x-empty-state
                    :title="__('No upcoming deliveries')"
                    :description="__('Your delivery weeks will appear here as soon as we plan them. You can still set defaults for the next cycle.')"
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
            </div>
        </section>
    @else
        <section class="border-b border-brand-ink/10 py-20 lg:py-24">
            <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

                <p class="eyebrow text-chapter-produce">{{ __('Upcoming.') }}</p>

                <ol class="mt-12 space-y-8" data-testid="deliveries-timeline">
                    @foreach ($weeklyDeliveries as $row)
                        @php
                            $baseDelivery     = $row['base'];
                            $effectiveDelivery = $row['effective'];
                            $isFocused        = $focusedDeliveryId === $baseDelivery->id;
                            $slotDate         = $baseDelivery->scheduled_for_date->copy()->startOfDay();
                            $thisWeekStart    = now()->copy()->startOfWeek();
                            $thisWeekEnd      = now()->copy()->endOfWeek();
                            $nextWeekStart    = $thisWeekStart->copy()->addWeek();
                            $nextWeekEnd      = $thisWeekEnd->copy()->addWeek();
                            if ($slotDate->betweenIncluded($thisWeekStart, $thisWeekEnd)) {
                                $relativeWeekLabel = __('This week');
                            } elseif ($slotDate->betweenIncluded($nextWeekStart, $nextWeekEnd)) {
                                $relativeWeekLabel = __('Next week');
                            } else {
                                $relativeWeekLabel = null;
                            }

                            $rowChapter = $chapterRotation[($loop->iteration - 1) % count($chapterRotation)];
                            $rowEyebrowClass = [
                                'produce'   => 'text-chapter-produce',
                                'eggdairy'  => 'text-chapter-eggdairy',
                                'bread'     => 'text-chapter-bread',
                                'fish'      => 'text-chapter-fish',
                                'meat'      => 'text-chapter-meat',
                                'producers' => 'text-chapter-producers',
                                'almanach'  => 'text-chapter-almanach',
                            ][$rowChapter];
                            $rowBorderNum = [
                                'produce'   => 'border-chapter-produce',
                                'eggdairy'  => 'border-chapter-eggdairy',
                                'bread'     => 'border-chapter-bread',
                                'fish'      => 'border-chapter-fish',
                                'meat'      => 'border-chapter-meat',
                                'producers' => 'border-chapter-producers',
                                'almanach'  => 'border-chapter-almanach',
                            ][$rowChapter];
                            $rowBorderSoft = [
                                'produce'   => 'border-chapter-produce/40',
                                'eggdairy'  => 'border-chapter-eggdairy/40',
                                'bread'     => 'border-chapter-bread/40',
                                'fish'      => 'border-chapter-fish/40',
                                'meat'      => 'border-chapter-meat/40',
                                'producers' => 'border-chapter-producers/40',
                                'almanach'  => 'border-chapter-almanach/40',
                            ][$rowChapter];
                            $rowRingClass = [
                                'produce'   => 'ring-chapter-produce',
                                'eggdairy'  => 'ring-chapter-eggdairy',
                                'bread'     => 'ring-chapter-bread',
                                'fish'      => 'ring-chapter-fish',
                                'meat'      => 'ring-chapter-meat',
                                'producers' => 'ring-chapter-producers',
                                'almanach'  => 'ring-chapter-almanach',
                            ][$rowChapter];
                        @endphp

                        <x-reveal as="li" variant="fade-up" class="flex gap-6">
                            <div class="flex shrink-0 flex-col items-center">
                                <span class="flex h-12 w-12 items-center justify-center border {{ $rowBorderNum }} font-display text-base font-semibold text-brand-ink">
                                    {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                @unless ($loop->last)
                                    <span class="mt-2 min-h-[2rem] w-px grow bg-brand-ink/20" aria-hidden="true"></span>
                                @endunless
                            </div>

                            <div class="min-w-0 flex-1 pb-4">
                                <section
                                    id="delivery-{{ $baseDelivery->id }}"
                                    @class([
                                        'border bg-brand-paper p-6 sm:p-8',
                                        $rowBorderNum.' ring-1 '.$rowRingClass => $isFocused,
                                        $rowBorderSoft => ! $isFocused,
                                    ])
                                    aria-labelledby="delivery-heading-{{ $baseDelivery->id }}"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="eyebrow {{ $rowEyebrowClass }}">{{ $relativeWeekLabel ?? __('Upcoming') }}</p>
                                            <h3 id="delivery-heading-{{ $baseDelivery->id }}" class="mt-2 font-display text-2xl text-brand-ink">
                                                {{ $baseDelivery->scheduled_for_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}
                                            </h3>
                                            <p class="mt-1 text-xs uppercase tracking-eyebrow text-brand-ink/55">{{ __('Delivery date') }}</p>
                                        </div>
                                        <x-status-badge :value="$effectiveDelivery->status" type="delivery" />
                                    </div>

                                    <dl class="mt-6 grid gap-5 border-t border-brand-ink/10 pt-5 text-sm sm:grid-cols-2">
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

                <div class="mt-20 flex flex-col items-center gap-3">
                    <x-triple-star class="opacity-70" />
                    <p class="font-editorial text-sm italic text-brand-ink/60">
                        {{ __('One week at a time, in the rhythm of the harvest.') }}
                    </p>
                </div>

            </div>
        </section>
    @endif

    <x-customer.editorial-footer-band />

</x-app-layout>
