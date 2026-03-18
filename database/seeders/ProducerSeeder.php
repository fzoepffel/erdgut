<?php

namespace Database\Seeders;

use App\Models\Producer;
use Illuminate\Database\Seeder;

class ProducerSeeder extends Seeder
{
    /**
     * Seed representative producer records for local and staging environments.
     */
    public function run(): void
    {
        $producers = [
            [
                'name' => 'Hof Sonnenfeld',
                'region' => 'Bayern',
                'description' => 'Familienbetrieb mit Fokus auf saisonales Freilandgemuese und kurze Lieferwege.',
                'image_url' => 'https://images.erdgut.local/producers/hof-sonnenfeld.jpg',
            ],
            [
                'name' => 'Obstgut Rheinau',
                'region' => 'Baden-Wuerttemberg',
                'description' => 'Apfel- und Birnenspezialist mit alten Sorten und schonender Lagerung.',
                'image_url' => 'https://images.erdgut.local/producers/obstgut-rheinau.jpg',
            ],
            [
                'name' => 'Gaertnerei Morgenrot',
                'region' => 'Hessen',
                'description' => 'Bio-zertifizierte Gaertnerei fuer Blattgemuese, Kraeuter und essbare Blueten.',
                'image_url' => 'https://images.erdgut.local/producers/gaertnerei-morgenrot.jpg',
            ],
            [
                'name' => 'Milchhof Lindenau',
                'region' => 'Niedersachsen',
                'description' => 'Regionaler Milchhof mit Weidehaltung und handwerklich verarbeiteten Produkten.',
                'image_url' => 'https://images.erdgut.local/producers/milchhof-lindenau.jpg',
            ],
            [
                'name' => 'Backstube Kornhaus',
                'region' => 'Nordrhein-Westfalen',
                'description' => 'Traditionsbaeckerei mit Sauerteigbroten aus regionalem Getreide.',
                'image_url' => 'https://images.erdgut.local/producers/backstube-kornhaus.jpg',
            ],
            [
                'name' => 'Gemuesekollektiv Uferacker',
                'region' => 'Brandenburg',
                'description' => 'Kleines Kollektiv fuer vielfaeltige Gemuesekulturen und bodenschonende Anbauweisen.',
                'image_url' => 'https://images.erdgut.local/producers/uferacker.jpg',
            ],
            [
                'name' => 'Biohof Tannengrund',
                'region' => 'Rheinland-Pfalz',
                'description' => 'Mischbetrieb mit Gemuese, Eiern und regenerativen Bodenaufbauprojekten.',
                'image_url' => 'https://images.erdgut.local/producers/biohof-tannengrund.jpg',
            ],
            [
                'name' => 'Kraeuterfarm Alpengruen',
                'region' => 'Bayern',
                'description' => 'Spezialisiert auf Frischkraeuter, Microgreens und essbare Pflanzenmischungen.',
                'image_url' => 'https://images.erdgut.local/producers/alpengruen.jpg',
            ],
            [
                'name' => 'Fruchtmanufaktur Elbtal',
                'region' => 'Sachsen',
                'description' => 'Verarbeitet Ueberschuesse zu Fruchtaufstrichen, Sirupen und Kompotten.',
                'image_url' => 'https://images.erdgut.local/producers/fruchtmanufaktur-elbtal.jpg',
            ],
            [
                'name' => 'Kartoffelhof Nordstern',
                'region' => 'Schleswig-Holstein',
                'description' => 'Anbau verschiedener Kartoffelsorten mit Schwerpunkt auf Geschmack und Lagerfaehigkeit.',
                'image_url' => 'https://images.erdgut.local/producers/kartoffelhof-nordstern.jpg',
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
