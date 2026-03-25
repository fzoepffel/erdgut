<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'subscriber-area-requires-setup')
                <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    {{ __('Complete your subscription and payment setup to access deliveries, shop extras, and billing.') }}
                </p>
            @endif

            <div class="overflow-hidden rounded-lg border border-emerald-100 bg-white shadow-sm">
                <div class="border-b border-emerald-50 bg-emerald-50/80 px-6 py-5">
                    <h1 class="text-lg font-semibold text-gray-900">
                        {{ __('Welcome to Erdgut') }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ __('Start your subscription in two steps: choose your box and preferences, then add a payment method. After that, deliveries, your box, and billing will unlock in the app.') }}
                    </p>
                </div>
                <div class="space-y-4 px-6 py-6">
                    <div class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">1</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('Plan and preferences') }}</p>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Rhythm, box size, dietary preferences, and allergies.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-800">2</span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ __('Payment method') }}</p>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Secure card setup via SumUp; we only store a payment token.') }}
                            </p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <a
                            href="{{ route('app.subscriptions.onboard') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                        >
                            {{ __('Start subscription') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
