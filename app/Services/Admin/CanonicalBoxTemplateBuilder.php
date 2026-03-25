<?php

namespace App\Services\Admin;

use App\Models\Product;

/**
 * Deterministic greedy core lines for one box size: cheapest-first per category slot,
 * capped by max retail sum (min subscription included value in batch).
 */
final class CanonicalBoxTemplateBuilder
{
    /**
     * @return array{lines: list<array{product: Product, quantity: float, unit: string, line_total: float}>, total: float}
     */
    public function build(string $boxSize, float $maxRetailSum): array
    {
        $rules = (array) config("box.composition_by_size.{$boxSize}", []);

        if ($rules === [] || $maxRetailSum <= 0) {
            return ['lines' => [], 'total' => 0.0];
        }

        $lines = [];
        $sum = 0.0;
        $epsilon = 0.005;

        foreach ($rules as $category => $count) {
            $slots = (int) $count;
            if ($slots <= 0) {
                continue;
            }

            $products = Product::query()
                ->where('category', $category)
                ->visibleInCatalog()
                ->orderBy('unit_price')
                ->orderBy('id')
                ->get();

            $addedInCategory = 0;

            foreach ($products as $product) {
                if ($addedInCategory >= $slots) {
                    break;
                }

                $price = round((float) $product->unit_price, 2);
                if ($sum + $price > $maxRetailSum + $epsilon) {
                    continue;
                }

                $qty = 1.0;
                $lines[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit' => data_get($product->metadata, 'unit', 'piece'),
                    'line_total' => round($qty * $price, 2),
                ];
                $sum += $price;
                $addedInCategory++;
            }
        }

        return [
            'lines' => $lines,
            'total' => round($sum, 2),
        ];
    }
}
