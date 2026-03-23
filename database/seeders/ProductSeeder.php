<?php

namespace Database\Seeders;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed sample product catalog for local and staging environments.
     * Requires ProducerSeeder to run first.
     */
    public function run(): void
    {
        $producerIds = Producer::query()->pluck('id')->all();
        if (empty($producerIds)) {
            return;
        }

        $products = [
            ['name' => 'Bio-Karotten', 'category' => 'vegetables', 'unit_price' => 2.49, 'is_seasonal' => false],
            ['name' => 'Rote Bete', 'category' => 'vegetables', 'unit_price' => 3.20, 'is_seasonal' => false],
            ['name' => 'Zwiebeln', 'category' => 'vegetables', 'unit_price' => 1.80, 'is_seasonal' => false],
            ['name' => 'Kartoffeln festkochend', 'category' => 'vegetables', 'unit_price' => 2.10, 'is_seasonal' => false],
            ['name' => 'Süßkartoffeln', 'category' => 'vegetables', 'unit_price' => 3.50, 'is_seasonal' => false],
            ['name' => 'Spinat', 'category' => 'vegetables', 'unit_price' => 2.90, 'is_seasonal' => true],
            ['name' => 'Mangold', 'category' => 'vegetables', 'unit_price' => 3.40, 'is_seasonal' => true],
            ['name' => 'Brokkoli', 'category' => 'vegetables', 'unit_price' => 2.70, 'is_seasonal' => false],
            ['name' => 'Blumenkohl', 'category' => 'vegetables', 'unit_price' => 2.50, 'is_seasonal' => false],
            ['name' => 'Zucchini', 'category' => 'vegetables', 'unit_price' => 2.30, 'is_seasonal' => true],
            ['name' => 'Aubergine', 'category' => 'vegetables', 'unit_price' => 2.80, 'is_seasonal' => true],
            ['name' => 'Paprika rot', 'category' => 'vegetables', 'unit_price' => 4.20, 'is_seasonal' => true],
            ['name' => 'Tomaten', 'category' => 'vegetables', 'unit_price' => 3.90, 'is_seasonal' => true, 'metadata_extra' => ['variety' => 'San Marzano', 'taste_profile' => 'Rich, low acidity, perfect for sauces']],
            ['name' => 'Gurke', 'category' => 'vegetables', 'unit_price' => 1.90, 'is_seasonal' => true],
            ['name' => 'Kürbis Hokkaido', 'category' => 'vegetables', 'unit_price' => 2.60, 'is_seasonal' => true],
            ['name' => 'Pastinaken', 'category' => 'vegetables', 'unit_price' => 3.10, 'is_seasonal' => false],
            ['name' => 'Sellerie', 'category' => 'vegetables', 'unit_price' => 2.40, 'is_seasonal' => false],
            ['name' => 'Lauch', 'category' => 'vegetables', 'unit_price' => 2.20, 'is_seasonal' => false],
            ['name' => 'Rettich', 'category' => 'vegetables', 'unit_price' => 2.00, 'is_seasonal' => false],
            ['name' => 'Radieschen', 'category' => 'vegetables', 'unit_price' => 1.70, 'is_seasonal' => true],
            ['name' => 'Äpfel Boskoop', 'category' => 'fruit', 'unit_price' => 2.80, 'is_seasonal' => false, 'metadata_extra' => ['variety' => 'Boskoop', 'taste_profile' => 'Aromatic, slightly tart, ideal for baking']],
            ['name' => 'Äpfel Elstar', 'category' => 'fruit', 'unit_price' => 3.20, 'is_seasonal' => false, 'metadata_extra' => ['variety' => 'Elstar', 'taste_profile' => 'Sweet and juicy, crisp texture']],
            ['name' => 'Birnen Conference', 'category' => 'fruit', 'unit_price' => 3.50, 'is_seasonal' => false],
            ['name' => 'Zwetschgen', 'category' => 'fruit', 'unit_price' => 4.20, 'is_seasonal' => true],
            ['name' => 'Kirschen', 'category' => 'fruit', 'unit_price' => 5.90, 'is_seasonal' => true],
            ['name' => 'Erdbeeren', 'category' => 'fruit', 'unit_price' => 4.50, 'is_seasonal' => true],
            ['name' => 'Himbeeren', 'category' => 'fruit', 'unit_price' => 6.20, 'is_seasonal' => true],
            ['name' => 'Brombeeren', 'category' => 'fruit', 'unit_price' => 5.80, 'is_seasonal' => true],
            ['name' => 'Johannisbeeren rot', 'category' => 'fruit', 'unit_price' => 4.80, 'is_seasonal' => true],
            ['name' => 'Stachelbeeren', 'category' => 'fruit', 'unit_price' => 4.60, 'is_seasonal' => true],
            ['name' => 'Quitten', 'category' => 'fruit', 'unit_price' => 3.90, 'is_seasonal' => true],
            ['name' => 'Rhabarber', 'category' => 'fruit', 'unit_price' => 3.40, 'is_seasonal' => true],
            ['name' => 'Frischkäse natur', 'category' => 'dairy', 'unit_price' => 4.20, 'is_seasonal' => false],
            ['name' => 'Joghurt natur', 'category' => 'dairy', 'unit_price' => 2.90, 'is_seasonal' => false],
            ['name' => 'Butter', 'category' => 'dairy', 'unit_price' => 5.50, 'is_seasonal' => false],
            ['name' => 'Sahne', 'category' => 'dairy', 'unit_price' => 2.40, 'is_seasonal' => false],
            ['name' => 'Vollmilch', 'category' => 'dairy', 'unit_price' => 1.40, 'is_seasonal' => false],
            ['name' => 'Sauerteigbrot Roggen', 'category' => 'bakery', 'unit_price' => 4.80, 'is_seasonal' => false],
            ['name' => 'Vollkornbrot', 'category' => 'bakery', 'unit_price' => 4.20, 'is_seasonal' => false],
            ['name' => 'Bauernbrot', 'category' => 'bakery', 'unit_price' => 3.90, 'is_seasonal' => false],
            ['name' => 'Brötchen 6er', 'category' => 'bakery', 'unit_price' => 2.80, 'is_seasonal' => false],
            ['name' => 'Kräuterbrot', 'category' => 'bakery', 'unit_price' => 4.50, 'is_seasonal' => false],
            ['name' => 'Dinkelbrot', 'category' => 'bakery', 'unit_price' => 4.60, 'is_seasonal' => false],
            ['name' => 'Frischkräuter Mix', 'category' => 'vegetables', 'unit_price' => 2.20, 'is_seasonal' => false],
            ['name' => 'Microgreens', 'category' => 'vegetables', 'unit_price' => 3.80, 'is_seasonal' => false],
            ['name' => 'Eier 6er', 'category' => 'dairy', 'unit_price' => 3.50, 'is_seasonal' => false],
        ];

        $index = 0;
        foreach ($products as $product) {
            $producerId = $producerIds[$index % count($producerIds)];
            $metadata = [
                'unit' => in_array($product['category'], ['dairy', 'vegetables', 'fruit']) ? 'kg' : 'piece',
                'origin' => 'regional',
            ];
            if (! empty($product['metadata_extra'])) {
                $metadata = array_merge($metadata, $product['metadata_extra']);
            }
            unset($product['metadata_extra']);
            Product::query()->updateOrCreate(
                [
                    'name' => $product['name'],
                    'producer_id' => $producerId,
                ],
                [
                    'category' => $product['category'],
                    'unit_price' => $product['unit_price'],
                    'is_seasonal' => $product['is_seasonal'],
                    'is_available' => true,
                    'is_published' => true,
                    'metadata' => $metadata,
                    'is_test' => true,
                ]
            );
            $index++;
        }
    }
}
