<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDiagnosticsRequest;
use App\Models\Payment;
use App\Models\PaymentDiagnostic;
use App\Models\SpargelOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Browser-side telemetry intake for the SumUp card widget.
 *
 * The {@see resources/views/components/payment/sumup-widget.blade.php}
 * component fires `navigator.sendBeacon` calls at each phase transition
 * (mounted → ready → submit → response/redirect, plus a `stalled` event
 * after 25s in `processing`). We log the row to a dedicated daily channel
 * AND persist to {@see PaymentDiagnostic} so admins can grep production
 * stalls. No PII: emails / IPs are hashed; meta is capability flags only.
 *
 * Added 2026-05-05 after a friend on /e/OS Murena had a 3DS redirect
 * silently fail with no server-side trace.
 */
class PaymentDiagnosticsController extends Controller
{
    public function store(PaymentDiagnosticsRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $checkoutId = isset($data['checkout_id']) ? (string) $data['checkout_id'] : null;

        // Best-effort linkage to the originating order/payment so admins can
        // pivot from a stuck row to the customer record. Both lookups are
        // cheap (indexed) and tolerated to fail silently.
        $spargelOrderId = null;
        $paymentId = null;
        if ($checkoutId !== null && $checkoutId !== '') {
            $spargelOrderId = SpargelOrder::query()
                ->where('sumup_checkout_id', $checkoutId)
                ->value('id');
            $paymentId = Payment::query()
                ->where('sumup_checkout_id', $checkoutId)
                ->value('id');
        }

        $row = PaymentDiagnostic::query()->create([
            'phase' => $data['phase'],
            'checkout_id' => $checkoutId,
            'intent' => $data['intent'] ?? null,
            'spargel_order_id' => $spargelOrderId,
            'payment_id' => $paymentId,
            'ms_since_mount' => $data['ms_since_mount'] ?? null,
            'ua_summary' => isset($data['ua_summary']) ? mb_substr((string) $data['ua_summary'], 0, 160) : null,
            'meta' => $data['meta'] ?? null,
            'ip_hash' => $this->hashIp((string) $request->ip()),
            'created_at' => now(),
        ]);

        // Also write to the dedicated channel so ops can `tail -f` during an
        // incident without DB access.
        Log::channel('payment_diag')->info('payment.widget.phase', [
            'id' => $row->id,
            'phase' => $row->phase,
            'checkout_id' => $row->checkout_id,
            'intent' => $row->intent,
            'spargel_order_id' => $row->spargel_order_id,
            'payment_id' => $row->payment_id,
            'ms_since_mount' => $row->ms_since_mount,
            'ua_summary' => $row->ua_summary,
            'meta' => $row->meta,
        ]);

        return response()->json(['ok' => true], 202);
    }

    /**
     * Hash the IP with the app key so the same source maps to the same hash
     * across rows (useful for following one stuck session) without storing
     * the raw IP. Truncated to 16 hex chars — collision risk is fine for
     * triage-grade analytics.
     */
    private function hashIp(string $ip): string
    {
        if ($ip === '') {
            return '';
        }

        return mb_substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 16);
    }
}
