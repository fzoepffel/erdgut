<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.payment_diagnostics.title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                {{ __('admin.operations.payment_diagnostics.intro') }}
            </div>

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.payment-diagnostics.index') }}" class="flex flex-col flex-wrap gap-3 md:flex-row md:items-end">
                        <div class="w-full sm:w-auto">
                            <label for="diag-phase" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.payment_diagnostics.phase') }}</label>
                            <select id="diag-phase" name="phase" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-44">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                @foreach ($phases as $phase)
                                    <option value="{{ $phase }}" @selected(request('phase') === $phase)>{{ $phase }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="diag-checkout" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.payment_diagnostics.checkout_id') }}</label>
                            <input id="diag-checkout" type="text" name="checkout_id" value="{{ request('checkout_id') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-64" />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="diag-spargel" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.payment_diagnostics.spargel_order_id') }}</label>
                            <input id="diag-spargel" type="number" min="1" name="spargel_order_id" value="{{ request('spargel_order_id') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-32" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-primary-button size="sm">{{ __('admin.catalog.apply_filters') }}</x-primary-button>
                            <a href="{{ route('app.admin.payment-diagnostics.index') }}" class="btn-ghost-sm">{{ __('admin.catalog.reset_filters') }}</a>
                        </div>
                    </form>

                    @if ($diagnostics->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                            <p class="text-sm font-medium text-gray-900">{{ __('admin.operations.payment_diagnostics.empty') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ __('admin.operations.payment_diagnostics.empty_hint') }}</p>
                        </div>
                    @else
                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.phase') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.ua_summary') }}</th>
                                        <th scope="col" class="py-2 pe-4">ms</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.checkout_id') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.intent') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.linked') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.payment_diagnostics.created_at') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.payment_diagnostics.meta') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($diagnostics as $row)
                                        @php
                                            $phaseColor = match ($row->phase) {
                                                'stalled', 'sdk_failed', 'load_timeout', 'mount_threw', 'response_error', 'response_fail', 'user_reported' => 'bg-rose-100 text-rose-900',
                                                'response_success', 'redirect_starting', 'widget_ready' => 'bg-emerald-100 text-emerald-900',
                                                default => 'bg-gray-100 text-gray-700',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $row->id }}</td>
                                            <td class="py-3 pe-4">
                                                <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider {{ $phaseColor }}">{{ $row->phase }}</span>
                                            </td>
                                            <td class="py-3 pe-4 text-gray-700">{{ $row->ua_summary ?? '—' }}</td>
                                            <td class="py-3 pe-4 tabular-nums text-gray-500">{{ $row->ms_since_mount !== null ? number_format($row->ms_since_mount).' ms' : '—' }}</td>
                                            <td class="py-3 pe-4 font-mono text-xs text-gray-700">{{ $row->checkout_id ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $row->intent ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-xs text-gray-600">
                                                @if ($row->spargel_order_id)
                                                    <a class="text-emerald-700 hover:underline" href="{{ route('spargel.admin.index', ['search' => $row->spargel_order_id]) }}">spargel #{{ $row->spargel_order_id }}</a>
                                                @elseif ($row->payment_id)
                                                    <a class="text-emerald-700 hover:underline" href="{{ route('app.admin.payments.show', $row->payment_id) }}">payment #{{ $row->payment_id }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-gray-500 tabular-nums">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</td>
                                            <td class="py-3 pe-0">
                                                @if ($row->meta)
                                                    <details class="text-xs">
                                                        <summary class="cursor-pointer text-emerald-700 hover:underline">{{ __('admin.operations.payment_diagnostics.expand') }}</summary>
                                                        <pre class="mt-1 max-w-md overflow-auto whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-[11px] text-gray-700">{{ json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                                    </details>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-2">{{ $diagnostics->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
