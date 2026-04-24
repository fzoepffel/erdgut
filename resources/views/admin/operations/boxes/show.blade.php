{{-- ERD-192: admin line-item mutations on box detail --}}
<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.box_detail_title', ['id' => $box->id]) }}
            </h2>
            <div class="flex items-center gap-4">
                @if ($box->week_start_date !== null)
                    <a
                        href="{{ route('app.admin.boxes.week.show', ['week' => $box->week_start_date->toDateString()]) }}"
                        class="text-sm font-medium text-emerald-700 hover:underline"
                    >
                        {{ __('admin.box_setup.cta_view_boxes') }}
                    </a>
                @endif
                <a href="{{ route('app.admin.boxes.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('admin.box_setup.back_to_overview') }}
                </a>
            </div>
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

            @if ($box->isTrial())
                <div class="rounded-md border border-chapter-produce/30 bg-chapter-produce/10 px-3 py-2 text-sm" data-testid="admin-box-trial-badge">
                    {{ __('Trial order box') }} —
                    <a class="underline" href="{{ route('app.admin.orders.show', $box->order_id) }}">{{ __('Open order #:id', ['id' => $box->order_id]) }}</a>
                </div>
            @endif

            <dl class="grid gap-4 rounded-lg border border-gray-100 bg-white p-4 text-sm shadow-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.operations.user') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->user?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.week_start') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->week_start_date?->translatedFormat('d.m.Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.operations.status') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->status->value }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.modification_deadline') }}</dt>
                    <dd class="mt-1 text-gray-900">{{ $box->modification_deadline_at?->translatedFormat('d.m.Y H:i') ?? '—' }}</dd>
                </div>
            </dl>

            <section class="rounded-lg border border-gray-100 bg-white p-4 text-sm shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.next_update_heading') }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.box_setup.next_update_hint') }}</p>
                <form method="post" action="{{ route('app.admin.boxes.meta.update', $box) }}" class="mt-3 flex flex-wrap items-end gap-3">
                    @csrf
                    @method('patch')
                    <label class="flex flex-col gap-1">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.next_update_label') }}</span>
                        <input
                            type="datetime-local"
                            name="next_update_expected_at"
                            value="{{ old('next_update_expected_at', $box->next_update_expected_at?->format('Y-m-d\TH:i')) }}"
                            class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                        />
                    </label>
                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-500">
                        {{ __('admin.box_setup.next_update_save') }}
                    </button>
                </form>
            </section>

            @if ($budgetDetails !== null)
                <div
                    class="rounded-lg border p-4 text-sm shadow-sm {{ $budgetDetails['over_budget'] ? 'border-rose-300 bg-rose-50' : 'border-gray-100 bg-white' }}"
                    role="status"
                >
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.budget_heading') }}</h3>
                    <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.included_budget_label') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ number_format($budgetDetails['included_budget'], 2, ',', '.') }} €</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.estimated_retail_label') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ number_format($budgetDetails['estimated_value'], 2, ',', '.') }} €</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.remainder_label') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ number_format($budgetDetails['remainder'], 2, ',', '.') }} €</dd>
                        </div>
                        @if ($budgetDetails['planning_hint'] !== null)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.planning_hint_label') }}</dt>
                                <dd class="mt-1 tabular-nums text-gray-700">{{ number_format($budgetDetails['planning_hint'], 2, ',', '.') }} €</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($budgetDetails['over_budget'])
                        <p class="mt-3 text-sm font-semibold text-rose-800">{{ __('admin.box_setup.over_budget_detail_notice') }}</p>
                    @endif
                </div>
            @endif

            @if (! $adminCanMutateItems)
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    {{ __('admin.box_items.read_only_notice') }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.items_heading') }}</h3>
                </div>
                @if ($box->items->isEmpty())
                    <p class="p-4 text-sm text-gray-600">{{ __('admin.box_setup.items_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('admin.box_setup.items_table_caption') }}</caption>
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_product') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_quantity') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_extra') }}</th>
                                    @if ($adminCanMutateItems)
                                        <th scope="col" class="px-4 py-2">{{ __('admin.box_items.col_actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($box->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900">
                                            <span>{{ $item->product?->name ?? '—' }}</span>
                                            @if (! empty($itemConflicts[$item->id]))
                                                <span
                                                    class="ml-2 inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800"
                                                    title="{{ __('admin.box_items.conflict_chip_title') }}"
                                                    data-testid="admin-box-item-conflict-chip"
                                                >
                                                    {{ __('admin.box_items.conflict_chip_label') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 tabular-nums text-gray-700">
                                            @if ($adminCanMutateItems)
                                                <form
                                                    method="post"
                                                    action="{{ route('app.admin.boxes.items.update', [$box, $item]) }}"
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        step="0.01"
                                                        min="0.1"
                                                        value="{{ $item->quantity }}"
                                                        class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                                        aria-label="{{ __('admin.box_items.quantity_label') }}"
                                                    />
                                                    <button
                                                        type="submit"
                                                        class="rounded-md bg-gray-900 px-2 py-1 text-xs font-semibold text-white hover:bg-gray-800"
                                                    >
                                                        {{ __('admin.box_items.save_quantity') }}
                                                    </button>
                                                </form>
                                            @else
                                                {{ $item->quantity }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $item->is_extra ? __('admin.box_setup.yes') : __('admin.box_setup.no') }}
                                        </td>
                                        @if ($adminCanMutateItems)
                                            <td class="px-4 py-3">
                                                <form
                                                    method="post"
                                                    action="{{ route('app.admin.boxes.items.destroy', [$box, $item]) }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="text-sm font-medium text-red-700 hover:text-red-900"
                                                    >
                                                        {{ __('admin.box_items.remove_line') }}
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($adminCanMutateItems)
                    <div class="border-t border-gray-100 p-4">
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">{{ __('admin.box_items.add_heading') }}</h4>
                        <p class="mb-3 text-xs text-amber-800" data-testid="admin-box-force-include-hint">
                            {{ __('admin.box_items.force_include_hint') }}
                        </p>
                        <form method="post" action="{{ route('app.admin.boxes.items.store', $box) }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                            @csrf
                            <div class="w-full sm:w-auto sm:min-w-[14rem]">
                                <label for="admin-box-add-product" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.box_items.product_label') }}</label>
                                <select
                                    id="admin-box-add-product"
                                    name="product_id"
                                    required
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                >
                                    <option value="">{{ __('admin.box_items.product_placeholder') }}</option>
                                    @foreach ($catalogProducts as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-full sm:w-auto">
                                <label for="admin-box-add-qty" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.box_items.quantity_label') }}</label>
                                <input
                                    id="admin-box-add-qty"
                                    type="number"
                                    name="quantity"
                                    step="0.01"
                                    min="0.1"
                                    value="1"
                                    required
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-28"
                                />
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                            >
                                {{ __('admin.box_items.add_submit') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
