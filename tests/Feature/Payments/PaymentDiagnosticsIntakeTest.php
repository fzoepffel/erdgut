<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentDiagnostic;
use App\Models\SpargelOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the browser-side telemetry intake works as a beacon target:
 * accepts JSON, persists to payment_diagnostics, links to a SpargelOrder
 * via the checkout id, and rejects unknown phases.
 */
class PaymentDiagnosticsIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_persists_a_phase_event(): void
    {
        $payload = [
            'phase' => 'mounted',
            'checkout_id' => 'sumup_checkout_abc',
            'intent' => 'one_time',
            'ms_since_mount' => 0,
            'ua_summary' => 'mobile / Android',
            'meta' => [
                'ua' => 'Mozilla/5.0 (Linux; Android 14)',
                'viewport' => '393x852',
                'cookieEnabled' => true,
            ],
        ];

        $this->postJson(route('public.payment-diagnostics.store'), $payload)
            ->assertStatus(202)
            ->assertJson(['ok' => true]);

        $row = PaymentDiagnostic::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('mounted', $row->phase);
        $this->assertSame('sumup_checkout_abc', $row->checkout_id);
        $this->assertSame('one_time', $row->intent);
        $this->assertSame('mobile / Android', $row->ua_summary);
        $this->assertSame('393x852', $row->meta['viewport'] ?? null);
        $this->assertNotEmpty($row->ip_hash);
    }

    public function test_endpoint_links_event_to_spargel_order(): void
    {
        $order = SpargelOrder::factory()->create([
            'sumup_checkout_id' => 'sumup_checkout_xyz',
        ]);

        $this->postJson(route('public.payment-diagnostics.store'), [
            'phase' => 'stalled',
            'checkout_id' => 'sumup_checkout_xyz',
            'intent' => 'one_time',
            'ms_since_mount' => 25_400,
            'meta' => ['reason' => 'no_response_25s'],
        ])->assertStatus(202);

        $row = PaymentDiagnostic::query()->latest('id')->first();
        $this->assertSame($order->id, $row->spargel_order_id);
        $this->assertSame('stalled', $row->phase);
        $this->assertSame(25_400, $row->ms_since_mount);
    }

    public function test_endpoint_rejects_unknown_phase(): void
    {
        $this->postJson(route('public.payment-diagnostics.store'), [
            'phase' => 'arbitrary_string_user_set',
            'checkout_id' => 'x',
        ])->assertStatus(422);

        $this->assertSame(0, PaymentDiagnostic::query()->count());
    }

    public function test_endpoint_accepts_event_with_no_checkout_id(): void
    {
        $this->postJson(route('public.payment-diagnostics.store'), [
            'phase' => 'sdk_failed',
            'meta' => ['reason' => 'SumUpCard_undefined'],
        ])->assertStatus(202);

        $row = PaymentDiagnostic::query()->first();
        $this->assertNull($row->checkout_id);
        $this->assertNull($row->spargel_order_id);
    }
}
