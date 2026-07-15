<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.create_heading') }}</h1>
        </div>
    </x-slot>

    @include('admin.shop-orders.partials.form', [
        'formAction' => route('app.admin.shop-orders.store'),
        'formMethod' => 'POST',
        'editing' => false,
        'order' => null,
        'preselectedItems' => [],
        'restaurantAccounts' => $restaurantAccounts,
        'submitLabel' => __('shop.admin.create_submit'),
        'cancelUrl' => route('app.admin.shop-orders.index'),
    ])
</x-admin-layout>
