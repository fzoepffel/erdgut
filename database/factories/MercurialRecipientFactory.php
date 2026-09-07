<?php

namespace Database\Factories;

use App\Models\MercurialRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MercurialRecipient>
 */
class MercurialRecipientFactory extends Factory
{
    protected $model = MercurialRecipient::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->firstName(),
            'restaurant' => $this->faker->company(),
        ];
    }
}
