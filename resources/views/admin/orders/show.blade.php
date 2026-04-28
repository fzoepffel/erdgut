<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Trial order') }} #{{ $order->id }}</h2>
    </x-slot>

    @php
        $canSchedule = $order->product_type->value !== 'trial_box'
            || ($order->box && $order->box->items->where('is_extra', false)->isNotEmpty());
    @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">{{ __('Customer & delivery') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-gray-500">{{ __('Customer') }}</dt><dd>{{ $order->user?->name }} ({{ $order->user?->email }})</dd></div>
                        <div><dt class="text-gray-500">{{ __('Phone') }}</dt><dd>{{ $order->user?->phone ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Address') }}</dt><dd>
                            @if ($order->address)
                                {{ $order->address->street_line_1 }}, {{ $order->address->postal_code }} {{ $order->address->city }}
                            @else — @endif
                        </dd></div>
                        <div><dt class="text-gray-500">{{ __('Preferred window') }}</dt><dd>{{ $order->preferred_delivery_window ?: '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Special instructions') }}</dt><dd>{{ $order->special_instructions ?: '—' }}</dd></div>
                    </dl>
                </section>

                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 font-semibold text-gray-800">{{ __('Order') }}</h3>
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd class="font-medium"><x-status-badge :value="$order->status" type="order" /></dd></div>
                        <div><dt class="text-gray-500">{{ __('Box size') }}</dt><dd>{{ __(ucfirst((string) $order->box_size)) }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Amount') }}</dt><dd>@eur($order->amount)</dd></div>
                        <div><dt class="text-gray-500">{{ __('Paid at') }}</dt><dd>{{ $order->paid_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Scheduled for') }}</dt><dd>{{ $order->scheduled_for_date?->format('D d M Y') ?? '—' }} {{ $order->preferred_timeslot }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Delivered at') }}</dt><dd>{{ $order->delivered_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Allergies') }}</dt><dd>{{ ! empty($order->allergies) ? implode(', ', (array) $order->allergies) : '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Preferences') }}</dt><dd>{{ ! empty($order->preferences) ? implode(', ', (array) $order->preferences) : '—' }}</dd></div>
                    </dl>
                </section>
            </div>

            @if ($bulkSetupUrl)
                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('admin.trial_order_box_setup.heading') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ __('admin.trial_order_box_setup.initial_setup_only_bulk') }}</p>
                        </div>
                        <a
                            href="{{ $bulkSetupUrl }}"
                            class="inline-flex items-center rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                        >
                            {{ __('admin.trial_order_box_setup.cta_bulk_setup') }}
                        </a>
                    </div>
                </section>
            @elseif ($order->product_type->value === 'trial_box' && $order->box)
                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="font-semibold text-gray-800">{{ __('admin.trial_order_box_setup.heading') }}</h3>
                        <a href="{{ route('app.admin.boxes.show', $order->box) }}" class="text-sm font-medium text-emerald-700 hover:underline">
                            {{ __('admin.trial_order_box_setup.open_box_detail') }}
                        </a>
                    </div>

                    @if ($budgetDetails !== null)
                        <div class="mt-3 rounded-lg border border-gray-100 bg-white p-4 text-sm shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.included_budget_label') }}</p>
                                    <p class="mt-1 tabular-nums text-gray-900">@eur($budgetDetails['included_budget'])</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.estimated_retail_label') }}</p>
                                    <p class="mt-1 tabular-nums text-gray-900">@eur($budgetDetails['estimated_value'])</p>
                                </div>
                            </div>
                            @php
                                $max = (float) ($budgetDetails['included_budget'] ?? 0);
                                $target = (float) ($budgetDetails['planning_hint'] ?? $max);
                                $value = (float) ($budgetDetails['estimated_value'] ?? 0);
                                $pct = $max > 0 ? max(0, min(100, ($value / $max) * 100)) : 0;
                                $targetPct = $max > 0 ? max(0, min(100, ($target / $max) * 100)) : 0;
                                $bar = $value > $max + 0.005 ? 'bg-rose-500' : ($value > $target + 0.005 ? 'bg-amber-400' : 'bg-emerald-500');
                            @endphp
                            <div class="relative mt-3 h-2 w-full rounded-full bg-gray-100">
                                <div class="h-2 rounded-full {{ $bar }}" style="width: {{ $pct }}%"></div>
                                <div class="absolute -top-0.5 h-3 w-0.5 bg-gray-500" style="left: {{ $targetPct }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                {{ __('admin.box_setup.target_short') }}: @eur($target) /
                                {{ __('admin.box_setup.max_short') }}: @eur($max)
                            </p>
                        </div>
                    @endif

                    @if (! $adminCanMutateItems)
                        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                            {{ __('admin.box_items.read_only_notice') }}
                        </div>
                    @endif

                    <div class="mt-3 rounded-lg border border-gray-100 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <h4 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.items_heading') }}</h4>
                        </div>
                        @if ($order->box->items->isEmpty())
                            <p class="p-4 text-sm text-gray-600">{{ __('admin.box_setup.items_empty') }}</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead>
                                        <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_product') }}</th>
                                            <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_quantity') }}</th>
                                            <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_extra') }}</th>
                                            @if ($adminCanMutateItems)
                                                <th scope="col" class="px-4 py-2 text-right">{{ __('admin.users.col_actions') }}</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($order->box->items as $item)
                                            <tr>
                                                <td class="px-4 py-3 text-gray-900">{{ $item->product?->name ?? '—' }}</td>
                                                <td class="px-4 py-3 tabular-nums text-gray-700">
                                                    @if ($adminCanMutateItems)
                                                        <form method="post" action="{{ route('app.admin.boxes.items.update', [$order->box, $item]) }}" class="flex flex-wrap items-center gap-2">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="number" name="quantity" step="0.01" min="0.1" value="{{ $item->quantity }}" class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                                            <button type="submit" class="rounded-md bg-gray-900 px-2 py-1 text-xs font-semibold text-white hover:bg-gray-800">
                                                                {{ __('admin.box_items.save_quantity') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        {{ $item->quantity }}
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-gray-700">{{ $item->is_extra ? __('admin.box_setup.yes') : __('admin.box_setup.no') }}</td>
                                                @if ($adminCanMutateItems)
                                                    <td class="px-4 py-3 text-right">
                                                        <form method="post" action="{{ route('app.admin.boxes.items.destroy', [$order->box, $item]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">
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
                                <h5 class="mb-3 text-sm font-semibold text-gray-900">{{ __('admin.box_items.add_heading') }}</h5>
                                <form method="post" action="{{ route('app.admin.boxes.items.store', $order->box) }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                                    @csrf
                                    <div class="w-full sm:w-auto sm:min-w-[14rem]">
                                        <label for="admin-order-box-add-product" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.box_items.product_label') }}</label>
                                        <select id="admin-order-box-add-product" name="product_id" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                            <option value="">{{ __('admin.box_items.product_placeholder') }}</option>
                                            @foreach ($catalogProducts as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-full sm:w-auto">
                                        <label for="admin-order-box-add-qty" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.box_items.quantity_label') }}</label>
                                        <input id="admin-order-box-add-qty" type="number" name="quantity" step="0.01" min="0.1" value="1" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-28" />
                                    </div>
                                    <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                        {{ __('admin.box_items.add_submit') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-800">{{ __('Schedule delivery') }}</h3>
                <form method="post" action="{{ route('app.admin.orders.schedule', $order) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="scheduled_for_date" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Date') }}</label>
                        <input id="scheduled_for_date" type="date" name="scheduled_for_date" value="{{ old('scheduled_for_date', $order->scheduled_for_date?->toDateString()) }}" required class="rounded-md border-gray-300 text-sm" @disabled(! $canSchedule) />
                    </div>
                    <div>
                        <label for="timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Timeslot') }}</label>
                        <select id="timeslot" name="timeslot" required class="rounded-md border-gray-300 text-sm" @disabled(! $canSchedule)>
                            @foreach ($timeslots as $slot)
                                <option value="{{ $slot }}" @selected(old('timeslot', $order->preferred_timeslot) === $slot)>{{ $slot }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white" @disabled(! $canSchedule)>{{ __('Schedule') }}</button>
                </form>
                @if (! $canSchedule)
                    <p class="mt-2 text-xs text-amber-700">{{ __('admin.trial_order_schedule.validation_needs_box') }}</p>
                @endif
                @error('scheduled_for_date') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                @error('timeslot') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            </section>

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="font-semibold text-gray-800">{{ __('Payments') }}</h3>
                    <a
                        href="{{ route('app.admin.payments.index', ['kind' => 'order', 'order_id' => $order->id]) }}"
                        class="text-sm font-medium text-emerald-700 hover:underline"
                    >
                        {{ __('Open in payments') }}
                    </a>
                </div>

                @if ($order->payments->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">{{ __('No payments found.') }}</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th class="px-3 py-2">ID</th>
                                    <th class="px-3 py-2">{{ __('admin.operations.amount') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.operations.status') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.operations.created_at') }}</th>
                                    <th class="px-3 py-2">paid_at</th>
                                    <th class="px-3 py-2">failed_at</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($order->payments as $payment)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-gray-700">#{{ $payment->id }}</td>
                                        <td class="px-3 py-2 tabular-nums text-gray-700">{{ $payment->amount }} {{ $payment->currency }}</td>
                                        <td class="px-3 py-2 text-gray-700"><x-status-badge :value="$payment->status" type="payment" /></td>
                                        <td class="px-3 py-2 text-gray-600">{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $payment->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-600">{{ $payment->failed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($order->status->value !== 'delivered' && $order->status->value !== 'cancelled')
                <form method="post" action="{{ route('app.admin.orders.mark-delivered', $order) }}">
                    @csrf
                    <button class="rounded-md bg-emerald-800 px-3 py-2 text-sm font-semibold text-white">{{ __('Mark as delivered') }}</button>
                </form>
            @endif

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.operations.emails.resend.section_heading') }}</h3>
                <p class="mt-1 text-xs text-gray-600">{{ __('admin.operations.emails.resend.section_intro') }}</p>
                @if (session('status') === 'admin-email-resent')
                    <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ __('admin.operations.emails.resend.flash_resent') }}</p>
                @elseif (session('status') === 'admin-email-resend-not-found')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-900">{{ __('admin.operations.emails.resend.flash_not_found') }}</p>
                @endif
                <div class="mt-3">
                    <x-admin.email-resend context="order" :entity-id="$order->id" />
                </div>
            </section>

            <x-admin.audit-timeline entity-type="order" :entity-id="$order->id" />
        </div>
    </div>
</x-admin-layout>
