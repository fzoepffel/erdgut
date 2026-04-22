<?php

namespace Tests\Feature\Database;

use App\Models\Producer;
use Database\Seeders\ProducerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_producer_seeder_inserts_at_least_ten_sample_producers(): void
    {
        $this->seed(ProducerSeeder::class);

        $this->assertGreaterThanOrEqual(5, Producer::query()->count());

        $producer = Producer::query()->firstOrFail();
        $this->assertTrue((bool) $producer->is_test);
        $this->assertDatabaseHas('producer_translations', [
            'producer_id' => $producer->id,
            'locale' => 'de',
        ]);
    }

    public function test_seeded_producers_are_marked_as_test_data(): void
    {
        $this->seed(ProducerSeeder::class);

        $this->assertSame(0, Producer::query()->where('is_test', false)->count());
    }

    public function test_non_seeded_producers_can_be_created_as_real_data(): void
    {
        $producer = Producer::factory()->create();
        $producer->translations()->where('locale', 'en')->update(['name' => 'Real Producer GmbH']);

        $this->assertFalse($producer->is_test);
        $this->assertDatabaseHas('producers', [
            'id' => $producer->id,
            'is_test' => 0,
        ]);

        $this->assertDatabaseHas('producer_translations', [
            'producer_id' => $producer->id,
            'locale' => 'en',
            'name' => 'Real Producer GmbH',
        ]);
    }
}
