<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Set up payment method') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600">
                        {{ __('Add your payment details to activate your subscription.') }}
                    </p>

                    <div id="sumup-card" class="mt-6 rounded-lg border border-dashed border-gray-300 p-6">
                        <p class="text-sm text-gray-500">
                            {{ __('Payment widget will be rendered here in the next step.') }}
                        </p>
                    </div>

                    <a
                        href="{{ route('app.subscriptions.show') }}"
                        class="mt-6 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                    >
                        {{ __('Back to subscription') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
