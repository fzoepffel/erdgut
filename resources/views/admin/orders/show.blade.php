<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Trial order') }} #{{ $order->id }}</h1>
    </x-slot>


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

            {{-- Trial-box composition lives in the unified Box-Setup view —
                 this card is just a deep link to the right week. The order
                 must be scheduled first so we know which week to open. --}}
            @if ($order->product_type->value === 'trial_box')
                <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">{{ __('admin.trial_order_box_setup.heading') }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ __('admin.trial_order_box_setup.compose_in_box_setup_hint') }}</p>
                        </div>
                        @if ($boxSetupUrl)
                            <a href="{{ $boxSetupUrl }}" class="btn-solid-sm">
                                {{ __('admin.trial_order_box_setup.cta_open_box_setup') }}
                            </a>
                        @else
                            <span class="inline-flex items-center rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
                                {{ __('admin.trial_order_box_setup.schedule_first') }}
                            </span>
                        @endif
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-800">{{ __('Schedule delivery') }}</h3>
                <p class="mb-3 text-xs text-gray-500">{{ __('admin.trial_order_schedule.intro') }}</p>
                <form method="post" action="{{ route('app.admin.orders.schedule', $order) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label for="scheduled_for_date" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Date') }}</label>
                        <input id="scheduled_for_date" type="date" name="scheduled_for_date" value="{{ old('scheduled_for_date', $order->scheduled_for_date?->toDateString()) }}" required class="rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label for="timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Timeslot') }}</label>
                        <select id="timeslot" name="timeslot" required class="rounded-md border-gray-300 text-sm">
                            @foreach ($timeslots as $slot)
                                <option value="{{ $slot }}" @selected(old('timeslot', $order->preferred_timeslot) === $slot)>{{ $slot }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button size="sm">{{ __('Schedule') }}</x-primary-button>
                </form>
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
                    <x-primary-button size="sm">{{ __('Mark as delivered') }}</x-primary-button>
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
