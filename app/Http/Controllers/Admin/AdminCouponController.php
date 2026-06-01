<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for shop discount {@see Coupon}s.
 *
 * Lives inside the `app.admin.*` group (sidebar shell, admin-gated). The
 * validation that keeps coupons safe — percent < 100, fixed needs a minimum
 * order — lives in {@see CouponRequest}; the discount math and cap
 * bookkeeping live in {@see App\Services\Shop\CouponService}. This controller
 * stays a thin CRUD surface.
 *
 *   GET    /app/admin/coupons              list
 *   GET    /app/admin/coupons/create       new form
 *   POST   /app/admin/coupons              persist
 *   GET    /app/admin/coupons/{coupon}     detail + edit + redemptions
 *   PUT    /app/admin/coupons/{coupon}     update
 *   POST   /app/admin/coupons/{coupon}/toggle  activate / deactivate
 *   DELETE /app/admin/coupons/{coupon}     delete
 */
class AdminCouponController extends Controller
{
    public function index(Request $request): View
    {
        $coupons = Coupon::query()
            ->withCount(['redemptions as redeemed_total' => fn ($q) => $q->where('status', CouponRedemption::STATUS_REDEEMED)])
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.coupons.index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create', [
            'types' => CouponType::cases(),
        ]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $coupon = Coupon::create([
            ...$request->validated(),
            'created_by_user_id' => (int) $request->user()->id,
        ]);

        return redirect()
            ->route('app.admin.coupons.show', $coupon)
            ->with('status', __('coupon.admin.flash_created', ['code' => $coupon->code]));
    }

    public function show(Coupon $coupon): View
    {
        $coupon->load(['redemptions' => fn ($q) => $q->latest()->limit(100)]);

        return view('admin.coupons.show', [
            'coupon' => $coupon,
            'types' => CouponType::cases(),
        ]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->validated());

        return redirect()
            ->route('app.admin.coupons.show', $coupon)
            ->with('status', __('coupon.admin.flash_updated', ['code' => $coupon->code]));
    }

    public function toggle(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->forceFill(['is_active' => ! $coupon->is_active])->save();

        return redirect()
            ->route('app.admin.coupons.show', $coupon)
            ->with('status', $coupon->is_active
                ? __('coupon.admin.flash_activated', ['code' => $coupon->code])
                : __('coupon.admin.flash_deactivated', ['code' => $coupon->code]));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $code = $coupon->code;
        $coupon->delete();

        return redirect()
            ->route('app.admin.coupons.index')
            ->with('status', __('coupon.admin.flash_deleted', ['code' => $code]));
    }
}
