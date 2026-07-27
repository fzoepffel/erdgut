<?php

namespace Tests\Unit\Support;

use App\Support\InventoryProductClusters;
use PHPUnit\Framework\TestCase;

class InventoryProductClustersTest extends TestCase
{
    public function test_colour_variants_and_compound_share_a_family_stem(): void
    {
        $merged = InventoryProductClusters::mergeStems([
            InventoryProductClusters::familyStem('Gelbe Zucchini'),
            InventoryProductClusters::familyStem('Grüne Zucchini'),
            InventoryProductClusters::familyStem('Zucchiniblüte'),
        ]);

        $this->assertCount(1, array_unique(array_values($merged)));
    }

    public function test_unrelated_stems_stay_separate(): void
    {
        $merged = InventoryProductClusters::mergeStems([
            InventoryProductClusters::familyStem('Spargel'),
            InventoryProductClusters::familyStem('Rhabarber'),
        ]);

        $this->assertCount(2, array_unique(array_values($merged)));
    }
}
