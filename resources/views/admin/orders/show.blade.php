<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Trial order') }} #{{ $order->id }}</h2>
    </x-slot>

    @php
        $canSchedule = $order->product_type->value !== 'trial_box'
            || ($order->box && $order->box->items->where('is_extra', false)->isNotEmpty());

        $initialState = null;
        if ($setupData) {
            $initialState = [
                'margin_rate' => $setupData['margin_rate'],
                'stock' => $setupData['stock'],
                'sizes' => [],
            ];
            $sizeMeta = [];
            foreach ($setupData['size_blocks'] as $block) {
                $sizeMeta[$block['box_size']] = [
                    'n' => $block['n'],
                    'ttc' => $block['ttc'],
                    'target' => $block['target'],
                    'max' => $block['max'],
                ];
                $initialState['sizes'][$block['box_size']] = array_map(fn ($line) => [
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                ], $block['lines']);
            }
            $initialState['size_meta'] = $sizeMeta;
        }
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
                        <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd class="font-medium">{{ $order->status->label() }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Box size') }}</dt><dd>{{ ucfirst((string) $order->box_size) }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Amount') }}</dt><dd>EUR {{ number_format((float) $order->amount, 2) }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Paid at') }}</dt><dd>{{ $order->paid_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Scheduled for') }}</dt><dd>{{ $order->scheduled_for_date?->format('D d M Y') ?? '—' }} {{ $order->preferred_timeslot }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Delivered at') }}</dt><dd>{{ $order->delivered_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Allergies') }}</dt><dd>{{ ! empty($order->allergies) ? implode(', ', (array) $order->allergies) : '—' }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Preferences') }}</dt><dd>{{ ! empty($order->preferences) ? implode(', ', (array) $order->preferences) : '—' }}</dd></div>
                    </dl>
                </section>
            </div>

            @if ($setupWeek && $setupData)
                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="font-semibold text-gray-800">{{ __('admin.trial_order_box_setup.heading') }}</h3>
                        <form method="get" action="{{ route('app.admin.orders.show', $order) }}" class="flex flex-wrap items-end gap-2">
                            <div>
                                <label for="week" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.trial_order_box_setup.inventory_week') }}</label>
                                <input id="week" type="date" name="week" value="{{ $setupWeek->toDateString() }}" class="rounded-md border-gray-300 text-sm" />
                            </div>
                            <button class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">{{ __('admin.trial_order_box_setup.change_week') }}</button>
                        </form>
                    </div>

                    @if ($errors->any())
                        <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                            <ul class="list-inside list-disc space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (empty($setupData['stock']))
                        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                            {{ __('admin.box_setup.no_stock_warning') }}
                            <a href="{{ route('app.admin.products.weekly.show', ['week' => $setupWeek->toDateString()]) }}" class="font-medium underline">
                                {{ __('admin.box_setup.goto_weekly_catalog') }}
                            </a>
                        </div>
                    @endif

                    @include('admin.operations.boxes.partials.box-setup-alpine')

                    <div class="mt-3" x-data="boxSetup(@js($initialState))" x-cloak>
                        <form method="post" action="{{ route('app.admin.orders.box-setup.apply', $order) }}">
                            @csrf
                            <input type="hidden" name="week_start_date" value="{{ $setupWeek->toDateString() }}" />

                            <div class="grid gap-4 lg:grid-cols-[minmax(280px,1fr)_3fr]">
                                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.stock_heading') }}</h4>
                                        <span class="text-xs font-semibold" :class="anyOver ? 'text-rose-700' : 'text-gray-500'">
                                            <template x-if="anyOver">
                                                <span>{{ __('admin.box_setup.over_planned_warning') }}</span>
                                            </template>
                                        </span>
                                    </div>
                                    <div class="mt-3 max-h-[50vh] overflow-auto">
                                        <table class="min-w-full text-xs">
                                            <thead class="sticky top-0 bg-white">
                                                <tr class="border-b border-gray-200 text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                                    <th class="py-1.5">{{ __('admin.box_setup.col_product') }}</th>
                                                    <th class="py-1.5 text-right">{{ __('admin.box_setup.col_planned') }}</th>
                                                    <th class="py-1.5 text-right">{{ __('admin.box_setup.col_used') }}</th>
                                                    <th class="py-1.5 text-right">{{ __('admin.box_setup.col_remaining') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                <template x-for="row in stockRows" :key="row.product_id">
                                                    <tr :class="row.remaining < 0 ? 'bg-rose-50' : (row.missing_stock ? 'bg-amber-50/50' : '')">
                                                        <td class="py-1 pr-2">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="truncate font-medium text-gray-900" x-text="row.name"></span>
                                                                <template x-if="row.missing_stock">
                                                                    <span class="rounded-full border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-800" x-text="stockOutOfWeekLabel"></span>
                                                                </template>
                                                            </div>
                                                            <div class="text-[10px] text-gray-500">
                                                                <span x-text="row.unit"></span>
                                                                ·
                                                                <span x-text="formatEur(row.unit_price)"></span>
                                                            </div>
                                                        </td>
                                                        <td class="py-1 text-right tabular-nums text-gray-700" x-text="formatNumber(row.planned)"></td>
                                                        <td class="py-1 text-right tabular-nums" :class="row.remaining < 0 ? 'font-semibold text-rose-700' : 'text-gray-700'" x-text="formatNumber(row.used_total)"></td>
                                                        <td class="py-1 text-right tabular-nums" :class="row.remaining < 0 ? 'font-semibold text-rose-700' : 'text-gray-700'" x-text="formatNumber(row.remaining)"></td>
                                                    </tr>
                                                </template>
                                                <template x-if="stockRows.length === 0">
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-gray-500">{{ __('admin.box_setup.stock_empty') }}</td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="grid gap-4">
                                    <template x-for="size in sizes" :key="size.key">
                                        <div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <h4 class="text-sm font-semibold text-gray-900">
                                                        <span x-text="sizeLabel(size.key)"></span>
                                                    </h4>
                                                    <p class="text-xs text-gray-500">
                                                        <span x-text="size.meta.n"></span>
                                                        {{ __('admin.box_setup.boxes_suffix') }}
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.retail_sum') }}</p>
                                                    <p class="text-sm font-semibold tabular-nums" :class="retailClass(size)">
                                                        <span x-text="formatEur(retailSum(size))"></span>
                                                    </p>
                                                    <p class="text-[11px] text-gray-500">
                                                        {{ __('admin.box_setup.target_short') }}:
                                                        <span x-text="formatEur(size.meta.target)"></span>
                                                        /
                                                        {{ __('admin.box_setup.max_short') }}:
                                                        <span x-text="formatEur(size.meta.max)"></span>
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="relative mt-2 h-1.5 w-full rounded-full bg-gray-100">
                                                <div class="h-1.5 rounded-full" :class="progressClass(size)" :style="'width:'+ progressWidth(size) +'%'"></div>
                                                <div class="absolute -top-0.5 h-2.5 w-0.5 bg-gray-500" :style="'left:'+ targetMarkerPct(size) +'%'" aria-hidden="true"></div>
                                            </div>

                                            <ul class="mt-3 flex-1 divide-y divide-gray-100 text-xs">
                                                <template x-for="(line, idx) in size.lines" :key="size.key+'-'+idx">
                                                    <li class="flex items-center gap-2 py-1.5">
                                                        <input type="hidden" :name="'sizes['+size.key+']['+idx+'][product_id]'" :value="line.product_id" />
                                                        <div class="min-w-0 flex-1">
                                                            <div class="truncate font-medium text-gray-900" x-text="productName(line.product_id)"></div>
                                                            <div class="text-[11px] text-gray-500">
                                                                <span x-text="productUnit(line.product_id)"></span>
                                                                ·
                                                                <span x-text="formatEur(productPrice(line.product_id))"></span>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <button type="button" class="rounded border border-gray-200 px-1.5 text-gray-600 hover:bg-gray-50" @click="decQty(size.key, idx)" aria-label="{{ __('admin.box_setup.decrease') }}">−</button>
                                                            <input
                                                                type="number"
                                                                min="1"
                                                                step="1"
                                                                class="w-14 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                                :name="'sizes['+size.key+']['+idx+'][quantity]'"
                                                                x-model.number="line.quantity"
                                                            />
                                                            <button type="button" class="rounded border border-gray-200 px-1.5 text-gray-600 hover:bg-gray-50" @click="incQty(size.key, idx)" aria-label="{{ __('admin.box_setup.increase') }}">+</button>
                                                            <button type="button" class="ml-1 text-rose-600 hover:text-rose-800" @click="removeLine(size.key, idx)" aria-label="{{ __('admin.box_setup.remove_line') }}">×</button>
                                                        </div>
                                                    </li>
                                                </template>
                                            </ul>

                                            <div class="mt-3 flex items-center gap-2">
                                                <select x-model="size.addProductId" class="w-full rounded-md border-gray-300 text-xs">
                                                    <option value="">{{ __('admin.box_setup.add_product') }}</option>
                                                    <template x-for="opt in addableOptions(size)" :key="opt.product_id">
                                                        <option :value="opt.product_id" x-text="opt.name"></option>
                                                    </template>
                                                </select>
                                                <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-800 hover:bg-gray-50" @click="addLine(size.key)">
                                                    {{ __('admin.box_setup.add') }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="flex items-center justify-end">
                                        <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">
                                            {{ __('admin.trial_order_box_setup.apply') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
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
                                        <td class="px-3 py-2 text-gray-700">{{ $payment->status }}</td>
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
        </div>
    </div>
</x-admin-layout>
