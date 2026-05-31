<?php

namespace Database\Factories;

use App\Models\Producer;
use App\Models\ProducerTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    protected $model = Producer::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Producer $producer): void {
            foreach (['de', 'en', 'fr'] as $locale) {
                ProducerTranslation::query()->create([
                    'producer_id' => $producer->id,
                    'locale' => $locale,
                    'name' => fake()->company().' ('.$locale.')',
                    'region' => fake()->city(),
                    'description' => fake()->sentence(),
                ]);
            }

            // Derive the public slug from the freshly created translations so
            // route-model binding (/producers/{producer:slug}) resolves in tests
            // and seeds, mirroring the admin create/update flow.
            $producer->load('translations')->ensureSlug();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image_url' => fake()->imageUrl(),
            'image_path' => null,
            'is_test' => false,
        ];
    }
}
