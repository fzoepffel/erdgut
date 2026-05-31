<?php

namespace Database\Factories;

use App\Enums\ShopOrderStatus;
use App\Models\ShopOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ShopOrder>
 */
class ShopOrderFactory extends Factory
{
    protected $model = ShopOrder::class;

    public function definition(): array
    {
        $fulfillment = fake()->randomElement(['pickup', 'delivery']);
        $itemsTotal = fake()->randomFloat(2, 5, 40);
        $deliveryFee = $fulfillment === 'delivery' ? 2.90 : 0.0;

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => $fulfillment === 'delivery' ? fake()->phoneNumber() : fake()->optional()->phoneNumber(),
            'fulfillment_mode' => $fulfillment,
            'expected_delivery_date' => now()->next(Carbon::MONDAY)->toDateString(),
            'pickup_location' => $fulfillment === 'pickup' ? fake()->randomElement(['karl_kunger', 'gotenstrasse']) : null,
            'delivery_street' => $fulfillment === 'delivery' ? fake()->streetAddress() : null,
            'delivery_postal_code' => $fulfillment === 'delivery' ? fake()->postcode() : null,
            'delivery_city' => $fulfillment === 'delivery' ? fake()->city() : null,
            'items_total_eur' => $itemsTotal,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($itemsTotal + $deliveryFee, 2),
            'payment_method' => 'online',
            'status' => ShopOrderStatus::PendingPayment,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => ShopOrderStatus::Paid,
            'paid_at' => now(),
            'sumup_checkout_id' => 'co_test_'.fake()->uuid(),
            'sumup_payment_id' => 'pay_test_'.fake()->uuid(),
        ]);
    }
}
