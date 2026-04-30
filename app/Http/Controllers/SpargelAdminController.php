<?php

namespace App\Http\Controllers;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Disposable admin view for the spargel trial-shop.
 *
 * Intentionally NOT plugged into the main admin sidebar / app shell —
 * this is a standalone page with a single hardcoded URL, kept dead
 * simple so we can rip it out when the proper shop launches. No
 * navigation links from elsewhere, no shared layout, no audit log
 * integration. Lives behind the existing `admin` gate.
 *
 *   GET  /spargel-admin                          list all orders
 *   POST /spargel-admin/{order}/mark-paid        for in-person orders
 *   POST /spargel-admin/{order}/mark-completed   = paid + delivered/picked-up
 */
class SpargelAdminController extends Controller
{
    public function index(): View
    {
        $orders = SpargelOrder::query()
            ->orderByDesc('id')
            ->get();

        return view('spargel.admin.index', [
            'orders' => $orders,
        ]);
    }

    public function markPaid(Request $request, SpargelOrder $spargelOrder): RedirectResponse
    {
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment) {
            $spargelOrder->forceFill([
                'status' => SpargelOrderStatus::Paid,
                'paid_at' => now(),
                'marked_paid_by_admin_at' => now(),
            ])->save();
        }

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_marked_paid', ['id' => $spargelOrder->id]));
    }

    public function markCompleted(Request $request, SpargelOrder $spargelOrder): RedirectResponse
    {
        $now = now();
        $payload = ['fulfilled_at' => $now];

        // If the order was an in-person one and admin only now realized
        // that — flip both states at once. Saves a second click.
        if ($spargelOrder->status === SpargelOrderStatus::PendingPayment) {
            $payload['status'] = SpargelOrderStatus::Fulfilled;
            $payload['paid_at'] = $now;
            $payload['marked_paid_by_admin_at'] = $now;
        } else {
            $payload['status'] = SpargelOrderStatus::Fulfilled;
        }

        $spargelOrder->forceFill($payload)->save();

        return redirect()
            ->route('spargel.admin.index')
            ->with('status', __('spargel.admin.flash_marked_completed', ['id' => $spargelOrder->id]));
    }
}
