<?php

namespace Database\Seeders;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed sample product catalog for local and staging environments.
     * Requires ProducerSeeder to run first.
     */
    public function run(): void
    {
        // Make the seed idempotent and keep environments clean: replace existing test products.
        Product::query()->where('is_test', true)->delete();

        $producers = Producer::query()->where('is_test', true)->get();
        if ($producers->isEmpty()) {
            return;
        }

        // Chapter taxonomy (D6/D7/F8 in UX-review notes): seeded
        // products use the canonical produce / eggs_and_dairy / bread
        // values that match the marketing chapters.
        $products = [
            [
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                // €2.49/kg → store €/g.
                'unit_price' => 0.00249,
                'is_seasonal' => false,
                'translations' => [
                    'de' => ['name' => 'Bio-Karotten', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Süß, knackig und ideal für Salate oder zum Rösten.'],
                    'en' => ['name' => 'Organic carrots', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Sweet, crisp, and great for salads or roasting.'],
                    'fr' => ['name' => 'Carottes bio', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'Douces, croquantes, parfaites en salade ou rôties.'],
                ],
            ],
            [
                'category' => 'produce',
                'unit' => 'mass',
                'price_display_unit' => 'kg',
                'unit_price' => 0.00320,
                'is_seasonal' => false,
                'translations' => [
                    'de' => ['name' => 'Äpfel Elstar', 'origin' => 'regional', 'variety' => 'Elstar', 'taste_profile' => 'Saftig, ausgewogen süß-säuerlich und sehr aromatisch.'],
                    'en' => ['name' => 'Elstar apples', 'origin' => 'regional', 'variety' => 'Elstar', 'taste_profile' => 'Juicy, balanced sweet-tart flavor with great aroma.'],
                    'fr' => ['name' => 'Pommes Elstar', 'origin' => 'régional', 'variety' => 'Elstar', 'taste_profile' => 'Juteuses, équilibre sucré-acidulé, très aromatiques.'],
                ],
            ],
            [
                'category' => 'eggs_and_dairy',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'unit_price' => 4.20,
                'is_seasonal' => false,
                'translations' => [
                    'de' => ['name' => 'Frischkäse natur', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Cremig, mild und vielseitig – pur oder mit Kräutern.'],
                    'en' => ['name' => 'Plain cream cheese', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'Creamy, mild, and versatile—plain or with herbs.'],
                    'fr' => ['name' => 'Fromage frais nature', 'origin' => 'régional', 'variety' => '—', 'taste_profile' => 'Crémeux, doux et polyvalent – nature ou aux herbes.'],
                ],
            ],
            [
                'category' => 'bread',
                'unit' => 'piece',
                'price_display_unit' => 'piece',
                'unit_price' => 4.80,
                'is_seasonal' => false,
                'translations' => [
                    'de' => ['name' => 'Sauerteigbrot Roggen', 'origin' => 'regional', 'variety' => 'Roggen', 'taste_profile' => 'Kräftig, saftig und lange frisch – klassisches Roggen-Sauerteigbrot.'],
                    'en' => ['name' => 'Rye sourdough loaf', 'origin' => 'regional', 'variety' => 'Rye', 'taste_profile' => 'Hearty, moist, and stays fresh—classic rye sourdough.'],
                    'fr' => ['name' => 'Pain au levain de seigle', 'origin' => 'régional', 'variety' => 'Seigle', 'taste_profile' => 'Rustique, moelleux et se conserve bien – pain au levain de seigle.'],
                ],
            ],
        ];

        $index = 0;
        foreach ($products as $row) {
            $producer = $producers[$index % $producers->count()];

            $product = Product::query()->create([
                'producer_id' => $producer->id,
                'category' => $row['category'],
                'unit' => $row['unit'],
                'price_display_unit' => $row['price_display_unit'],
                'unit_price' => $row['unit_price'],
                'is_seasonal' => $row['is_seasonal'],
                'is_available' => true,
                'is_published' => true,
                'metadata' => null,
                'image_path' => null,
                'is_test' => true,
            ]);

            foreach (['de', 'en', 'fr'] as $locale) {
                ProductTranslation::query()->create([
                    'product_id' => $product->id,
                    'locale' => $locale,
                    'name' => $row['translations'][$locale]['name'],
                    'origin' => $row['translations'][$locale]['origin'],
                    'variety' => $row['translations'][$locale]['variety'],
                    'taste_profile' => $row['translations'][$locale]['taste_profile'],
                    // Seed rows reuse the (already short) taste_profile sentence
                    // as the card description unless an explicit short is given.
                    'short_description' => $row['translations'][$locale]['short_description']
                        ?? $row['translations'][$locale]['taste_profile'],
                ]);
            }

            $index++;
        }
    }
}
