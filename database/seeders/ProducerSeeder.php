<?php

namespace Database\Seeders;

use App\Models\Producer;
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
        $producers = [
            [
                'name' => 'Hof Sonnenfeld',
                'region' => 'Bayern',
                'description' => 'Familienbetrieb mit Fokus auf saisonales Freilandgemuese und kurze Lieferwege.',
                'image_url' => null,
            ],
            [
                'name' => 'Obstgut Rheinau',
                'region' => 'Baden-Wuerttemberg',
                'description' => 'Apfel- und Birnenspezialist mit alten Sorten und schonender Lagerung.',
                'image_url' => null,
            ],
            [
                'name' => 'Gaertnerei Morgenrot',
                'region' => 'Hessen',
                'description' => 'Bio-zertifizierte Gaertnerei fuer Blattgemuese, Kraeuter und essbare Blueten.',
                'image_url' => null,
            ],
            [
                'name' => 'Milchhof Lindenau',
                'region' => 'Niedersachsen',
                'description' => 'Regionaler Milchhof mit Weidehaltung und handwerklich verarbeiteten Produkten.',
                'image_url' => null,
            ],
            [
                'name' => 'Backstube Kornhaus',
                'region' => 'Nordrhein-Westfalen',
                'description' => 'Traditionsbaeckerei mit Sauerteigbroten aus regionalem Getreide.',
                'image_url' => null,
            ],
            [
                'name' => 'Gemuesekollektiv Uferacker',
                'region' => 'Brandenburg',
                'description' => 'Kleines Kollektiv fuer vielfaeltige Gemuesekulturen und bodenschonende Anbauweisen.',
                'image_url' => null,
            ],
            [
                'name' => 'Biohof Tannengrund',
                'region' => 'Rheinland-Pfalz',
                'description' => 'Mischbetrieb mit Gemuese, Eiern und regenerativen Bodenaufbauprojekten.',
                'image_url' => null,
            ],
            [
                'name' => 'Kraeuterfarm Alpengruen',
                'region' => 'Bayern',
                'description' => 'Spezialisiert auf Frischkraeuter, Microgreens und essbare Pflanzenmischungen.',
                'image_url' => null,
            ],
            [
                'name' => 'Fruchtmanufaktur Elbtal',
                'region' => 'Sachsen',
                'description' => 'Verarbeitet Ueberschuesse zu Fruchtaufstrichen, Sirupen und Kompotten.',
                'image_url' => null,
            ],
            [
                'name' => 'Kartoffelhof Nordstern',
                'region' => 'Schleswig-Holstein',
                'description' => 'Anbau verschiedener Kartoffelsorten mit Schwerpunkt auf Geschmack und Lagerfaehigkeit.',
                'image_url' => null,
            ],
        ];

        foreach ($producers as $producer) {
            Producer::query()->updateOrCreate(
                ['name' => $producer['name']],
                [...$producer, 'is_test' => true]
            );
        }
    }
}
