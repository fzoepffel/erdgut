<?php

namespace Database\Seeders;

use App\Models\ProductSubcategory;
use Illuminate\Database\Seeder;

class ProductSubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ── Produce ───────────────────────────────────────────────
            ['category' => 'produce', 'sort_order' => 10, 'label_de' => 'Blattgemüse & Salate', 'label_en' => 'Leaves & Salads', 'label_fr' => 'Feuilles & Salades'],
            ['category' => 'produce', 'sort_order' => 20, 'label_de' => 'Wurzeln & Knollen', 'label_en' => 'Roots & Tubers', 'label_fr' => 'Racines & Tubercules'],
            ['category' => 'produce', 'sort_order' => 30, 'label_de' => 'Kohlgewächse', 'label_en' => 'Brassicas', 'label_fr' => 'Crucifères'],
            ['category' => 'produce', 'sort_order' => 40, 'label_de' => 'Hülsenfrüchte', 'label_en' => 'Pods & Legumes', 'label_fr' => 'Légumineuses'],
            ['category' => 'produce', 'sort_order' => 50, 'label_de' => 'Fruchtgemüse', 'label_en' => 'Fruiting Vegetables', 'label_fr' => 'Légumes-fruits'],
            ['category' => 'produce', 'sort_order' => 60, 'label_de' => 'Zwiebelgewächse', 'label_en' => 'Alliums', 'label_fr' => 'Alliacées'],
            ['category' => 'produce', 'sort_order' => 70, 'label_de' => 'Stängel & Sprossen', 'label_en' => 'Stems & Shoots', 'label_fr' => 'Tiges & Pousses'],
            ['category' => 'produce', 'sort_order' => 80, 'label_de' => 'Steinobst', 'label_en' => 'Stone Fruit', 'label_fr' => 'Fruits à noyau'],
            ['category' => 'produce', 'sort_order' => 90, 'label_de' => 'Beeren', 'label_en' => 'Berries', 'label_fr' => 'Baies'],
            ['category' => 'produce', 'sort_order' => 100, 'label_de' => 'Kernobst', 'label_en' => 'Tree Fruit', 'label_fr' => 'Fruits à pépins'],
            ['category' => 'produce', 'sort_order' => 110, 'label_de' => 'Zitrusfrüchte', 'label_en' => 'Citrus', 'label_fr' => 'Agrumes'],
            ['category' => 'produce', 'sort_order' => 120, 'label_de' => 'Pilze', 'label_en' => 'Mushrooms', 'label_fr' => 'Champignons'],
            ['category' => 'produce', 'sort_order' => 130, 'label_de' => 'Kräuter', 'label_en' => 'Herbs', 'label_fr' => 'Herbes aromatiques'],

            // ── Meat ──────────────────────────────────────────────────
            ['category' => 'meat', 'sort_order' => 10, 'label_de' => 'Rind', 'label_en' => 'Beef', 'label_fr' => 'Bœuf'],
            ['category' => 'meat', 'sort_order' => 20, 'label_de' => 'Schwein', 'label_en' => 'Pork', 'label_fr' => 'Porc'],
            ['category' => 'meat', 'sort_order' => 30, 'label_de' => 'Geflügel', 'label_en' => 'Poultry', 'label_fr' => 'Volaille'],
            ['category' => 'meat', 'sort_order' => 40, 'label_de' => 'Lamm', 'label_en' => 'Lamb', 'label_fr' => 'Agneau'],
            ['category' => 'meat', 'sort_order' => 50, 'label_de' => 'Wild', 'label_en' => 'Game', 'label_fr' => 'Gibier'],
            ['category' => 'meat', 'sort_order' => 60, 'label_de' => 'Wurstwaren', 'label_en' => 'Charcuterie', 'label_fr' => 'Charcuterie'],
            ['category' => 'meat', 'sort_order' => 70, 'label_de' => 'Innereien', 'label_en' => 'Offal', 'label_fr' => 'Abats'],

            // ── Fish ──────────────────────────────────────────────────
            ['category' => 'fish', 'sort_order' => 10, 'label_de' => 'Süßwasserfisch', 'label_en' => 'Freshwater Fish', 'label_fr' => 'Poisson d\'eau douce'],
            ['category' => 'fish', 'sort_order' => 20, 'label_de' => 'Meeresfisch', 'label_en' => 'Saltwater Fish', 'label_fr' => 'Poisson de mer'],
            ['category' => 'fish', 'sort_order' => 30, 'label_de' => 'Schalentiere & Krustentiere', 'label_en' => 'Shellfish', 'label_fr' => 'Crustacés & Coquillages'],
            ['category' => 'fish', 'sort_order' => 40, 'label_de' => 'Weichtiere', 'label_en' => 'Molluscs', 'label_fr' => 'Mollusques'],
            ['category' => 'fish', 'sort_order' => 50, 'label_de' => 'Geräuchert & Gepökelt', 'label_en' => 'Smoked & Cured', 'label_fr' => 'Fumé & Mariné'],
            ['category' => 'fish', 'sort_order' => 60, 'label_de' => 'Fischprodukte', 'label_en' => 'Fish Products', 'label_fr' => 'Produits de la pêche'],

            // ── Eggs & Dairy ──────────────────────────────────────────
            ['category' => 'eggs_and_dairy', 'sort_order' => 10, 'label_de' => 'Eier', 'label_en' => 'Eggs', 'label_fr' => 'Œufs'],
            ['category' => 'eggs_and_dairy', 'sort_order' => 20, 'label_de' => 'Milch', 'label_en' => 'Milk', 'label_fr' => 'Lait'],
            ['category' => 'eggs_and_dairy', 'sort_order' => 30, 'label_de' => 'Butter & Sahne', 'label_en' => 'Butter & Cream', 'label_fr' => 'Beurre & Crème'],
            ['category' => 'eggs_and_dairy', 'sort_order' => 40, 'label_de' => 'Käse', 'label_en' => 'Cheese', 'label_fr' => 'Fromage'],
            ['category' => 'eggs_and_dairy', 'sort_order' => 50, 'label_de' => 'Joghurt & Quark', 'label_en' => 'Yogurt & Quark', 'label_fr' => 'Yaourt & Fromage blanc'],
            ['category' => 'eggs_and_dairy', 'sort_order' => 60, 'label_de' => 'Fermentiertes', 'label_en' => 'Fermented', 'label_fr' => 'Fermenté'],

            // ── Bread ─────────────────────────────────────────────────
            ['category' => 'bread', 'sort_order' => 10, 'label_de' => 'Sauerteig', 'label_en' => 'Sourdough', 'label_fr' => 'Pain au levain'],
            ['category' => 'bread', 'sort_order' => 20, 'label_de' => 'Roggen', 'label_en' => 'Rye', 'label_fr' => 'Seigle'],
            ['category' => 'bread', 'sort_order' => 30, 'label_de' => 'Weizen', 'label_en' => 'Wheat', 'label_fr' => 'Blé'],
            ['category' => 'bread', 'sort_order' => 40, 'label_de' => 'Mischbrot', 'label_en' => 'Mixed Loaves', 'label_fr' => 'Pain mixte'],
            ['category' => 'bread', 'sort_order' => 50, 'label_de' => 'Gebäck & Brötchen', 'label_en' => 'Pastries & Rolls', 'label_fr' => 'Viennoiseries & Petits pains'],
            ['category' => 'bread', 'sort_order' => 60, 'label_de' => 'Knäckebrot & Kekse', 'label_en' => 'Crispbread & Crackers', 'label_fr' => 'Craquins & Crackers'],
        ];

        foreach ($rows as $row) {
            ProductSubcategory::query()->firstOrCreate(
                ['category' => $row['category'], 'label_en' => $row['label_en']],
                $row,
            );
        }
    }
}
