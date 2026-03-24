<?php

namespace Database\Factories;

use App\Enums\PaymentChargeInitiator;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'amount' => fake()->randomFloat(2, 10, 80),
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
            'sumup_checkout_id' => fake()->optional()->uuid(),
            'sumup_payment_id' => fake()->optional()->uuid(),
            'failure_reason' => null,
            'provider_payload' => [
                'provider' => 'sumup',
                'mode' => 'sandbox',
            ],
            'paid_at' => null,
            'failed_at' => null,
        ];
    }
}
