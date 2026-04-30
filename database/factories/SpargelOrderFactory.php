<?php

namespace Database\Factories;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpargelOrder>
 */
class SpargelOrderFactory extends Factory
{
    protected $model = SpargelOrder::class;

    public function definition(): array
    {
        $quantity = fake()->randomElement([0.5, 1.0, 1.5, 2.0]);
        $unitPrice = 20.0;
        $fulfillment = $quantity < 1.0 ? 'pickup' : fake()->randomElement(['pickup', 'delivery']);
        $deliveryFee = $fulfillment === 'delivery' ? 1.99 : 0.0;

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'asparagus_type' => fake()->randomElement(['green', 'white']),
            'quantity_kg' => $quantity,
            'unit_price_eur' => $unitPrice,
            'fulfillment_mode' => $fulfillment,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($quantity * $unitPrice + $deliveryFee, 2),
            'delivery_street' => $fulfillment === 'delivery' ? fake()->streetAddress() : null,
            'delivery_postal_code' => $fulfillment === 'delivery' ? fake()->postcode() : null,
            'delivery_city' => $fulfillment === 'delivery' ? fake()->city() : null,
            'status' => SpargelOrderStatus::PendingPayment,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => SpargelOrderStatus::Paid,
            'paid_at' => now(),
            'sumup_checkout_id' => 'co_test_'.fake()->uuid(),
            'sumup_payment_id' => 'pay_test_'.fake()->uuid(),
        ]);
    }
}
