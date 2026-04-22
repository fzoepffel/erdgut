<?php

namespace Database\Seeders;

use App\Models\Producer;
use App\Models\ProducerTranslation;
use Illuminate\Database\Seeder;

class ProducerSeeder extends Seeder
{
    /**
     * Seed representative producer records for local and staging environments.
     *
     * image_url is omitted: placeholder hosts (e.g. images.erdgut.local) do not exist in production.
     * Use admin uploads (image_path) or a real HTTPS image_url where needed.
     */
    public function run(): void
    {
        // Make the seed idempotent and keep environments clean: replace existing test producers.
        Producer::query()->where('is_test', true)->delete();

        $rows = [
            [
                'translations' => [
                    'de' => [
                        'name' => 'Hof Sonnenfeld',
                        'region' => 'Brandenburg',
                        'description' => 'Familienbetrieb mit Fokus auf saisonales Freilandgemüse und kurze Lieferwege.',
                    ],
                    'en' => [
                        'name' => 'Sonnenfeld Farm',
                        'region' => 'Brandenburg',
                        'description' => 'Family-run farm focused on seasonal open-field vegetables and short delivery routes.',
                    ],
                    'fr' => [
                        'name' => 'Ferme Sonnenfeld',
                        'region' => 'Brandebourg',
                        'description' => 'Exploitation familiale axée sur les légumes de saison en plein champ et des circuits courts.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'de' => [
                        'name' => 'Obstgut Rheinau',
                        'region' => 'Baden-Württemberg',
                        'description' => 'Apfel- und Birnenspezialist mit alten Sorten und schonender Lagerung.',
                    ],
                    'en' => [
                        'name' => 'Rheinau Orchard',
                        'region' => 'Baden-Württemberg',
                        'description' => 'Apple and pear specialist with heritage varieties and gentle storage.',
                    ],
                    'fr' => [
                        'name' => 'Verger de Rheinau',
                        'region' => 'Bade-Wurtemberg',
                        'description' => 'Spécialiste des pommes et des poires, variétés anciennes et stockage soigné.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'de' => [
                        'name' => 'Gärtnerei Morgenrot',
                        'region' => 'Berlin',
                        'description' => 'Bio-zertifizierte Gärtnerei für Blattgemüse, Kräuter und essbare Blüten.',
                    ],
                    'en' => [
                        'name' => 'Morgenrot Market Garden',
                        'region' => 'Berlin',
                        'description' => 'Organic-certified market garden for leafy greens, herbs, and edible flowers.',
                    ],
                    'fr' => [
                        'name' => 'Maraîchage Morgenrot',
                        'region' => 'Berlin',
                        'description' => 'Maraîchage bio certifié : légumes feuilles, herbes et fleurs comestibles.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'de' => [
                        'name' => 'Milchhof Lindenau',
                        'region' => 'Mecklenburg-Vorpommern',
                        'description' => 'Regionaler Milchhof mit Weidehaltung und handwerklich verarbeiteten Produkten.',
                    ],
                    'en' => [
                        'name' => 'Lindenau Dairy',
                        'region' => 'Mecklenburg-Vorpommern',
                        'description' => 'Regional dairy farm with pasture grazing and craft-made products.',
                    ],
                    'fr' => [
                        'name' => 'Laiterie Lindenau',
                        'region' => 'Mecklembourg-Poméranie-Occidentale',
                        'description' => 'Ferme laitière régionale avec pâturage et produits artisanaux.',
                    ],
                ],
            ],
            [
                'translations' => [
                    'de' => [
                        'name' => 'Backstube Kornhaus',
                        'region' => 'Berlin',
                        'description' => 'Traditionsbäckerei mit Sauerteigbroten aus regionalem Getreide.',
                    ],
                    'en' => [
                        'name' => 'Kornhaus Bakery',
                        'region' => 'Berlin',
                        'description' => 'Traditional bakery with sourdough loaves from regional grains.',
                    ],
                    'fr' => [
                        'name' => 'Boulangerie Kornhaus',
                        'region' => 'Berlin',
                        'description' => 'Boulangerie traditionnelle : pains au levain à base de céréales régionales.',
                    ],
                ],
            ],
        ];

        foreach ($rows as $row) {
            $producer = Producer::query()->create([
                'image_url' => null,
                'image_path' => null,
                'is_test' => true,
            ]);

            foreach (['de', 'en', 'fr'] as $locale) {
                ProducerTranslation::query()->create([
                    'producer_id' => $producer->id,
                    'locale' => $locale,
                    'name' => $row['translations'][$locale]['name'],
                    'region' => $row['translations'][$locale]['region'],
                    'description' => $row['translations'][$locale]['description'],
                ]);
            }
        }
    }
}
