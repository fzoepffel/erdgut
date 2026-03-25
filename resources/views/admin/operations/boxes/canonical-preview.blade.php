<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_fill_canonical.preview_title') }}
            </h2>
            <a href="{{ route('app.admin.boxes.planner') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_planner.back_to_planner') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.box_fill_canonical.preview_intro', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </p>

            @if ($preview['size_blocks'] === [])
                <p class="text-sm text-gray-600">{{ __('admin.box_fill_canonical.no_blocks') }}</p>
            @else
                @foreach ($preview['size_blocks'] as $block)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">
                            {{ __('admin.box_planner.size_label_' . $block['box_size']) }}
                            <span class="ms-2 font-normal text-gray-600">
                                ({{ __('admin.box_fill_canonical.cap_used') }} {{ number_format($block['cap_used'], 2, ',', '.') }} € ·
                                {{ __('admin.box_fill_canonical.template_total') }} {{ number_format($block['template_total'], 2, ',', '.') }} €)
                            </span>
                        </h3>
                        <p class="mt-2 text-xs text-gray-600">{{ __('admin.box_fill_canonical.same_lines_hint') }}</p>
                        <ul class="mt-2 list-inside list-disc text-sm text-gray-700">
                            @forelse ($block['template_lines'] as $line)
                                @php $p = $line['product']; @endphp
                                <li>
                                    {{ $p->name }}
                                    <span class="tabular-nums text-gray-500">
                                        ({{ number_format($line['line_total'], 2, ',', '.') }} €)
                                    </span>
                                </li>
                            @empty
                                <li>{{ __('admin.box_fill_canonical.no_template_lines') }}</li>
                            @endforelse
                        </ul>

                        <div class="mt-4 border-t border-gray-100 pt-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_fill_canonical.subscriptions_heading') }}</p>
                            <ul class="mt-2 space-y-1 text-sm text-gray-800">
                                @foreach ($block['subscriptions'] as $subRow)
                                    @php $sub = $subRow['subscription']; @endphp
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>{{ $sub->user?->email ?? '—' }}</span>
                                        <span class="tabular-nums text-gray-600">
                                            {{ __('admin.box_fill_random.budget_abbr') }}
                                            {{ number_format($subRow['included_budget'], 2, ',', '.') }} €
                                        </span>
                                        @if ($subRow['over_budget'])
                                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">
                                                {{ __('admin.box_planner.over_budget_badge') }}
                                            </span>
                                        @endif
                                        @if ($subRow['will_create_box'])
                                            <span class="text-xs text-amber-700">{{ __('admin.box_fill_random.will_create_box_short') }}</span>
                                        @endif
                                        @if ($subRow['skipped_not_editable'])
                                            <span class="text-xs text-amber-700">{{ __('admin.box_fill_random.skipped_not_editable_short') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach

                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-medium text-rose-900">{{ __('admin.box_fill_canonical.confirm_title') }}</p>
                    <p class="mt-1 text-sm text-rose-800">{{ __('admin.box_fill_canonical.confirm_body') }}</p>
                    <form method="post" action="{{ route('app.admin.boxes.planner.canonical-apply') }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />
                        <label class="flex items-center gap-2 text-sm text-rose-900">
                            <input type="checkbox" name="confirm_fill" value="1" class="rounded border-gray-300 text-rose-700 focus:ring-rose-600" />
                            {{ __('admin.box_fill_canonical.confirm_checkbox') }}
                        </label>
                        <button type="submit" class="inline-flex rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-800">
                            {{ __('admin.box_fill_random.submit_apply') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
