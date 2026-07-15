<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.shop-orders.show', $order) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_order') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.edit_heading', ['id' => $order->id]) }}</h1>
        </div>
    </x-slot>

    @include('admin.shop-orders.partials.form', [
        'formAction' => route('app.admin.shop-orders.update', $order),
        'formMethod' => 'PUT',
        'editing' => true,
        'order' => $order,
        'preselectedItems' => $preselectedItems,
        'submitLabel' => __('shop.admin.edit_submit'),
        'cancelUrl' => route('app.admin.shop-orders.show', $order),
    ])
</x-admin-layout>
