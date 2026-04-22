<x-app-layout>
    @php
        $weekLabelDate = $box->week_start_date->format('d.m.Y');
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3">
            <p class="eyebrow text-chapter-produce">{{ __('Your weekly food box') }}</p>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                    {{ __('Week of :date', ['date' => $weekLabelDate]) }}
                </h1>
                <x-status-badge :value="$box->status" type="box" />
            </div>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="box-week-heading">
                <h2 id="box-week-heading" class="sr-only">{{ __('Box summary') }}</h2>
                <div class="space-y-3 text-sm">
                    @if ($modificationDeadline)
                        <p class="text-brand-ink/75">
                            {{ __('Box finalized on :datetime', ['datetime' => $modificationDeadline->format('d.m.Y H:i')]) }}
                        </p>
                    @endif

                    <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 font-medium text-brand-ink">
                        {{ __('boxes.read_only_notice') }}
                    </p>
                </div>
            </section>

            <x-box.budget-panel
                :box="$box"
                :budget-details="$budgetDetails"
                :bind-estimate="false"
            />

            <section class="border border-brand-ink/15" aria-labelledby="box-items-heading">
                <div class="border-b border-brand-ink/15 px-6 py-5">
                    <p class="eyebrow text-chapter-produce">{{ __('Contents') }}</p>
                    <h3 id="box-items-heading" class="mt-2 font-display text-xl text-brand-ink">
                        {{ __('This week in the box') }}
                    </h3>
                </div>
                <div class="p-6">
                    @if ($box->items->isEmpty())
                        <x-empty-state
                            :title="__('No items in this box yet.')"
                            :description="__('When the week is curated, items will appear here.')"
                            variant="inset"
                        />
                    @else
                        @php
                            $grouped = $box->items->groupBy(function ($item) {
                                $cat = $item->product?->category;
                                $enum = $cat ? \App\Enums\ProductCategory::tryFrom($cat) : null;
                                return $enum?->stall() ?? 'other';
                            });
                            $stallOrder = ['produce', 'bakery', 'dairy', 'fish', 'meat', 'other'];
                            $stallColor = [
                                'produce' => 'text-chapter-produce',
                                'bakery' => 'text-brand-ink',
                                'dairy' => 'text-brand-ink',
                                'fish' => 'text-chapter-fish',
                                'meat' => 'text-chapter-meat',
                                'other' => 'text-brand-ink/60',
                            ];
                            $stallLabel = [
                                'produce' => __('Greengrocer'),
                                'bakery' => __('Bakery'),
                                'dairy' => __('Dairy'),
                                'fish' => __('Fishmonger'),
                                'meat' => __('Butcher'),
                                'other' => __('Other'),
                            ];
                        @endphp
                        <div class="space-y-8">
                            @foreach ($stallOrder as $stall)
                                @if (isset($grouped[$stall]))
                                    <div>
                                        <p class="eyebrow {{ $stallColor[$stall] }}">{{ $stallLabel[$stall] }}</p>
                                        <ul class="mt-3 divide-y divide-brand-ink/10 border-t border-brand-ink/15">
                                            @foreach ($grouped[$stall] as $item)
                                                <li class="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <span class="font-medium text-brand-ink">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                                        @if ($item->is_extra)
                                                            <span class="ml-2 inline-flex border border-brand-ink/30 px-2 py-0.5 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink">
                                                                {{ __('Extra add-on') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-sm tabular-nums text-brand-ink/70">
                                                        {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                                                    </span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
