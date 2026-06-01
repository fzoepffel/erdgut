<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('coupon.admin.heading') }}</h1>
            <a href="{{ route('app.admin.coupons.create') }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700" data-testid="coupon-create">
                {{ __('coupon.admin.create') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            <div class="overflow-x-auto rounded-lg border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-600">
                            <th class="px-3 py-2">{{ __('coupon.admin.col_code') }}</th>
                            <th class="px-3 py-2">{{ __('coupon.admin.col_type') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('coupon.admin.col_value') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('coupon.admin.col_redeemed') }}</th>
                            <th class="px-3 py-2">{{ __('coupon.admin.col_active') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($coupons as $coupon)
                            <tr class="align-top" data-testid="coupon-row-{{ $coupon->id }}">
                                <td class="whitespace-nowrap px-3 py-2 font-mono font-medium text-gray-800">
                                    {{ $coupon->code }}
                                    @if ($coupon->label)
                                        <div class="font-sans text-xs font-normal text-gray-500">{{ $coupon->label }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ __('coupon.type_' . $coupon->type->value) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-gray-800">
                                    @if ($coupon->type === \App\Enums\CouponType::Percent)
                                        {{ rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',') }} %
                                    @elseif ($coupon->type === \App\Enums\CouponType::FreeDelivery)
                                        <span class="text-gray-500">{{ __('coupon.admin.free_delivery_short') }}</span>
                                    @else
                                        @eur($coupon->value)
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-gray-700">
                                    {{ (int) $coupon->redeemed_count }}{{ $coupon->max_redemptions !== null ? ' / ' . (int) $coupon->max_redemptions : '' }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($coupon->is_active)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('coupon.admin.active') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ __('coupon.admin.inactive') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <a href="{{ route('app.admin.coupons.show', $coupon) }}" class="text-emerald-700 underline" data-testid="coupon-open-{{ $coupon->id }}">{{ __('Open') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('coupon.admin.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $coupons->links() }}</div>
        </div>
    </div>
</x-admin-layout>
