<?php

namespace Tests\Unit\Support;

use App\Support\SumUpCheckoutLogger;
use Tests\TestCase;

class SumUpCheckoutLoggerTest extends TestCase
{
    public function test_checkout_snapshot_summarizes_without_raw_token(): void
    {
        $checkout = (object) [
            'checkout_reference' => 'sub-1-setup-123',
            'status' => 'PAID',
            'customer_id' => 'sub-1',
            'amount' => 1,
            'currency' => 'EUR',
            'purpose' => 'SETUP_RECURRING_PAYMENT',
            'payment_instrument' => (object) ['token' => 'secret-should-not-appear-in-snapshot'],
            'mandate' => (object) ['type' => 'recurrent', 'status' => 'active'],
            'transactions' => [
                (object) ['status' => 'SUCCESSFUL', 'payment_type' => 'ECOM'],
            ],
        ];

        $snapshot = SumUpCheckoutLogger::checkoutSnapshot($checkout);

        $this->assertSame('sub-1-setup-123', $snapshot['checkout_reference']);
        $this->assertSame('PAID', $snapshot['checkout_status']);
        $this->assertTrue($snapshot['has_payment_instrument_token']);
        $this->assertCount(1, $snapshot['transactions']);
        $this->assertStringNotContainsString('secret', json_encode($snapshot));
    }
}
