<?php

namespace Database\Factories;

use App\Models\BoxWaitlistSignup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoxWaitlistSignup>
 */
class BoxWaitlistSignupFactory extends Factory
{
    protected $model = BoxWaitlistSignup::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'interested' => true,
            'source' => 'homepage',
            'locale' => 'de',
            'ip_address' => fake()->ipv4(),
        ];
    }
}
