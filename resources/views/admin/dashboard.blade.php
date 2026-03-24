<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.dashboard.intro_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('admin.dashboard.intro_body') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a
                        href="{{ route('app.admin.producers.index') }}"
                        class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        {{ __('Producers') }}
                    </a>
                    <a
                        href="{{ route('app.admin.products.index') }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        {{ __('Products') }}
                    </a>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <a
                    href="{{ route('app.admin.producers.index') }}"
                    class="group rounded-lg border border-gray-100 bg-white p-6 shadow-sm transition hover:border-emerald-200 hover:shadow-md sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-emerald-800">{{ __('admin.dashboard.card_producers_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('admin.dashboard.card_producers_desc') }}</p>
                    <span class="mt-4 inline-flex text-sm font-medium text-emerald-700">{{ __('admin.dashboard.view_list') }} →</span>
                </a>
                <a
                    href="{{ route('app.admin.products.index') }}"
                    class="group rounded-lg border border-gray-100 bg-white p-6 shadow-sm transition hover:border-emerald-200 hover:shadow-md sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-emerald-800">{{ __('admin.dashboard.card_products_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('admin.dashboard.card_products_desc') }}</p>
                    <span class="mt-4 inline-flex text-sm font-medium text-emerald-700">{{ __('admin.dashboard.view_list') }} →</span>
                </a>
            </div>
        </div>
    </div>
</x-admin-layout>
