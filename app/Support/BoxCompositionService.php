<?php

namespace App\Support;

use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;

class BoxCompositionService
{
    public function __construct(
        private readonly BoxValueRecalculationService $recalculationService
    ) {}

    /**
     * Fill a box with default products based on its size's composition rules.
     *
     * @return int Number of items added.
     */
    public function compose(Box $box, string $boxSize): int
    {
        $rules = (array) config("box.composition_by_size.{$boxSize}", []);

        if ($rules === []) {
            return 0;
        }

        $added = 0;

        foreach ($rules as $category => $count) {
            $products = Product::query()
                ->where('category', $category)
                ->visibleInCatalog()
                ->inRandomOrder()
                ->limit((int) $count)
                ->get();

            foreach ($products as $product) {
                BoxItem::create([
                    'box_id' => $box->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit' => data_get($product->metadata, 'unit', 'piece'),
                    'is_extra' => false,
                ]);

                $added++;
            }
        }

        if ($added > 0) {
            $this->recalculationService->recalculate($box);
        }

        return $added;
    }
}
