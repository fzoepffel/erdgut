<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('This week\'s box') }}
        </h2>
    </x-slot>

    @php
        $weekLabelDate = $box->week_start_date->format('d.m.Y');
    @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
                aria-labelledby="box-week-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="box-week-heading" class="text-lg font-semibold text-gray-900">
                            {{ __('Week of :date', ['date' => $weekLabelDate]) }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Your weekly food box') }}</p>
                    </div>
                    <x-status-badge :value="$box->status" type="box" />
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    @if ($modificationDeadline)
                        <p class="text-gray-700">
                            {{ __('Box finalized on :datetime', ['datetime' => $modificationDeadline->format('d.m.Y H:i')]) }}
                        </p>
                    @endif

                    <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-800">
                        {{ __('boxes.read_only_notice') }}
                    </p>
                </div>
            </section>

            <x-box.budget-panel
                :box="$box"
                :budget-details="$budgetDetails"
                :bind-estimate="false"
            />

            <section
                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                aria-labelledby="box-items-heading"
            >
                <div class="border-b border-gray-100 bg-gray-50/80 px-4 py-3 sm:px-6">
                    <h3 id="box-items-heading" class="text-sm font-semibold text-gray-900">
                        {{ __('Contents') }}
                    </h3>
                </div>
                <div class="p-4 sm:p-6">
                    @if ($box->items->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-6 text-center">
                            <p class="text-sm text-gray-700">{{ __('No items in this box yet.') }}</p>
                        </div>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($box->items as $item)
                                <li class="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                        @if ($item->is_extra)
                                            <span class="ml-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                {{ __('Extra add-on') }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-sm tabular-nums text-gray-600">
                                        {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
