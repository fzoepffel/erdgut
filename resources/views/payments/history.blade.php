<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payment history') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($payments->isEmpty())
                        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <h2 class="mt-4 text-lg font-semibold text-gray-900">{{ __('No payment history yet') }}</h2>
                            <p class="mt-2 text-sm text-gray-600">{{ __('Your subscription charges will appear here once billing starts.') }}</p>
                        </div>
                    @else
                        <div class="block sm:hidden space-y-3">
                            @foreach ($payments as $payment)
                                <div class="rounded-md border border-gray-200 p-4 shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <p class="font-medium text-gray-900">{{ $payment->created_at->format('d M Y') }}</p>
                                        <x-status-badge :value="$payment->status" />
                                    </div>
                                    <dl class="mt-2 space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <dt class="text-gray-600">{{ __('Amount') }}</dt>
                                            <dd class="font-medium text-gray-900">{{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $payment->currency }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-gray-600">{{ __('Attempt') }}</dt>
                                            <dd class="font-medium text-gray-900">{{ $payment->attempt_number }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Date') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Amount') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Status') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Attempt') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($payments as $payment)
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                {{ $payment->created_at->format('d M Y') }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                {{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $payment->currency }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                <x-status-badge :value="$payment->status" />
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                {{ $payment->attempt_number }}
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
    </div>
</x-app-layout>
