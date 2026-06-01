<x-admin-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('coupon.admin.create_heading') }}</h1>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('app.admin.coupons.index') }}" class="text-sm text-gray-600 underline">← {{ __('coupon.admin.back_to_list') }}</a>

            <form method="POST" action="{{ route('app.admin.coupons.store') }}" class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm" data-testid="coupon-form">
                @csrf
                @include('admin.coupons.form', ['coupon' => null, 'types' => $types])

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button data-testid="coupon-submit">{{ __('coupon.admin.save') }}</x-primary-button>
                    <a href="{{ route('app.admin.coupons.index') }}" class="text-sm text-gray-600 underline">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
