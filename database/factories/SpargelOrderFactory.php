<?php

namespace Database\Factories;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SpargelOrder>
 */
class SpargelOrderFactory extends Factory
{
    protected $model = SpargelOrder::class;

    public function definition(): array
    {
        $grams = fake()->randomElement([200, 500, 1000, 1500, 2000]);
        $unitPrice = 21.50;
        $fulfillment = $grams < 500 ? 'pickup' : fake()->randomElement(['pickup', 'delivery']);
        $deliveryFee = $fulfillment === 'delivery' ? 2.90 : 0.0;
        $line = round(($grams / 1000) * $unitPrice, 2);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => $fulfillment === 'delivery' ? fake()->phoneNumber() : fake()->optional()->phoneNumber(),
            'asparagus_type' => fake()->randomElement(['green', 'white']),
            'quantity_grams' => $grams,
            'unit_price_eur' => $unitPrice,
            'fulfillment_mode' => $fulfillment,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($line + $deliveryFee, 2),
            'payment_method' => 'online',
            'expected_delivery_date' => now()->next(Carbon::MONDAY)->toDateString(),
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
