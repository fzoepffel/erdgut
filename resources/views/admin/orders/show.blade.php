<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Trial order') }} #{{ $order->id }}</h2>
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

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-semibold text-gray-800">{{ __('Schedule delivery') }}</h3>
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
                    <button class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">{{ __('Schedule') }}</button>
                </form>
                @error('scheduled_for_date') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
                @error('timeslot') <p class="mt-2 text-xs text-rose-600">{{ $message }}</p> @enderror
            </section>

            <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">{{ __('Box') }}</h3>
                    <form method="post" action="{{ route('app.admin.orders.compose', $order) }}">
                        @csrf
                        <button class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">
                            {{ $order->box ? __('Recompose box') : __('Compose box') }}
                        </button>
                    </form>
                </div>
                @if ($order->box)
                    <ul class="mt-3 divide-y divide-gray-100 text-sm">
                        @foreach ($order->box->items as $item)
                            <li class="flex justify-between py-1">
                                <span>{{ $item->product?->name ?? '—' }}</span>
                                <span class="text-gray-500">{{ $item->quantity }} {{ $item->unit }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-gray-500">{{ __('No box composed yet.') }}</p>
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
