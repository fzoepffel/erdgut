<?php

namespace Tests\Unit\Support;

use App\Models\Producer;
use App\Support\ProducerLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_data_uses_shop_routes_by_default(): void
    {
        $producer = Producer::factory()->create();
        $producer->forceFill(['slug' => 'test-farm'])->save();

        $data = ProducerLabels::linkData([$producer]);

        $this->assertFalse($data['collapsed']);
        $this->assertCount(1, $data['items']);
        $this->assertSame(route('public.shop.producers.show', $producer), $data['items'][0]['url']);
        $this->assertSame(route('public.shop.producers.index'), $data['index_url']);
    }

    public function test_link_data_can_use_marketing_routes(): void
    {
        $producer = Producer::factory()->create();
        $producer->forceFill(['slug' => 'market-farm'])->save();

        $data = ProducerLabels::linkData(
            [$producer],
            ProducerLabels::SHOW_ROUTE_MARKETING,
            ProducerLabels::INDEX_ROUTE_MARKETING,
        );

        $this->assertSame(route('public.producers.show', $producer), $data['items'][0]['url']);
        $this->assertSame(route('public.producers'), $data['index_url']);
    }

    public function test_link_data_collapses_long_lists(): void
    {
        $producers = Producer::factory()->count(4)->create();
        foreach ($producers as $i => $producer) {
            $producer->forceFill(['slug' => 'farm-'.$i])->save();
        }

        $data = ProducerLabels::linkData($producers);

        $this->assertTrue($data['collapsed']);
        $this->assertCount(4, $data['items']);
    }
}
