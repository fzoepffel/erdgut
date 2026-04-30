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
        // Pick at least 100 g of green asparagus by default; sometimes
        // add a bit of white on top so factory rows exercise the
        // mixed-variety path too.
        $green = fake()->randomElement([100, 200, 500, 1000]);
        $white = fake()->randomElement([0, 0, 200, 500]);
        $totalGrams = $green + $white;
        $unitPrice = 21.50;
        $fulfillment = $totalGrams < 500 ? 'pickup' : fake()->randomElement(['pickup', 'delivery']);
        $deliveryFee = $fulfillment === 'delivery' ? 2.90 : 0.0;
        $line = round(($totalGrams / 1000) * $unitPrice, 2);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => $fulfillment === 'delivery' ? fake()->phoneNumber() : fake()->optional()->phoneNumber(),
            'quantity_green_grams' => $green,
            'quantity_white_grams' => $white,
            'unit_price_eur' => $unitPrice,
            'fulfillment_mode' => $fulfillment,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($line + $deliveryFee, 2),
            'payment_method' => 'online',
            'expected_delivery_date' => now()->next(Carbon::MONDAY)->toDateString(),
            'delivery_street' => $fulfillment === 'delivery' ? fake()->streetAddress() : null,
            'delivery_postal_code' => $fulfillment === 'delivery' ? fake()->postcode() : null,
            'delivery_city' => $fulfillment === 'delivery' ? fake()->city() : null,
            'pickup_location' => $fulfillment === 'pickup' ? fake()->randomElement(['karl_kunger', 'gotenstrasse']) : null,
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
