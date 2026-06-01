<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                <span class="font-mono">{{ $coupon->code }}</span>
            </h1>
            <span class="text-sm text-gray-500">
                {{ __('coupon.admin.redeemed_summary', ['count' => (int) $coupon->redeemed_count, 'cap' => $coupon->max_redemptions !== null ? (int) $coupon->max_redemptions : '∞']) }}
            </span>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('app.admin.coupons.index') }}" class="text-sm text-gray-600 underline">← {{ __('coupon.admin.back_to_list') }}</a>

            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('app.admin.coupons.update', $coupon) }}" class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm" data-testid="coupon-form">
                @csrf
                @method('PUT')
                @include('admin.coupons.form', ['coupon' => $coupon, 'types' => $types])

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button data-testid="coupon-submit">{{ __('coupon.admin.save') }}</x-primary-button>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('app.admin.coupons.toggle', $coupon) }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-testid="coupon-toggle">
                        {{ $coupon->is_active ? __('coupon.admin.deactivate') : __('coupon.admin.activate') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('app.admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('{{ __('coupon.admin.confirm_delete') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50" data-testid="coupon-delete">
                        {{ __('coupon.admin.delete') }}
                    </button>
                </form>
            </div>

            {{-- Redemptions --}}
            <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('coupon.admin.redemptions_heading') }}</h2>
                @if ($coupon->redemptions->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('coupon.admin.redemptions_empty') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <th class="px-2 py-1">{{ __('coupon.admin.col_order') }}</th>
                                <th class="px-2 py-1">{{ __('coupon.admin.col_email') }}</th>
                                <th class="px-2 py-1 text-right">{{ __('coupon.admin.col_discount') }}</th>
                                <th class="px-2 py-1">{{ __('coupon.admin.col_status') }}</th>
                                <th class="px-2 py-1">{{ __('coupon.admin.col_when') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($coupon->redemptions as $r)
                                <tr>
                                    <td class="px-2 py-1 font-mono text-gray-700">
                                        @if ($r->shop_order_id)
                                            <a href="{{ route('app.admin.shop-orders.show', $r->shop_order_id) }}" class="text-emerald-700 underline">#{{ $r->shop_order_id }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-gray-700">{{ $r->email }}</td>
                                    <td class="px-2 py-1 text-right tabular-nums text-gray-800">@eur($r->discount_eur)</td>
                                    <td class="px-2 py-1">
                                        @if ($r->status === \App\Models\CouponRedemption::STATUS_VOID)
                                            <span class="text-gray-400">{{ __('coupon.admin.redemption_void') }}</span>
                                        @else
                                            <span class="text-emerald-700">{{ __('coupon.admin.redemption_redeemed') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-1 text-gray-500">{{ $r->redeemed_at?->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
