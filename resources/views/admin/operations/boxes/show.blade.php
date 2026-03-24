<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_planner.box_detail_title', ['id' => $box->id]) }}
            </h2>
            <a href="{{ route('app.admin.boxes.planner') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_planner.back_to_planner') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <dl class="grid gap-4 rounded-lg border border-gray-100 bg-white p-4 text-sm shadow-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.operations.user') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->user?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_planner.week_start') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->week_start_date?->translatedFormat('d.m.Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.operations.status') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->status->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_planner.modification_deadline') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->modification_deadline_at?->translatedFormat('d.m.Y H:i') ?? '—' }}</dd>
                </div>
            </dl>

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_planner.items_heading') }}</h3>
                </div>
                @if ($box->items->isEmpty())
                    <p class="p-4 text-sm text-gray-600">{{ __('admin.box_planner.items_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('admin.box_planner.items_table_caption') }}</caption>
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_planner.col_product') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_planner.col_quantity') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_planner.col_extra') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($box->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900">{{ $item->product?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 tabular-nums text-gray-700">{{ $item->quantity }}</td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $item->is_extra ? __('admin.box_planner.yes') : __('admin.box_planner.no') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
