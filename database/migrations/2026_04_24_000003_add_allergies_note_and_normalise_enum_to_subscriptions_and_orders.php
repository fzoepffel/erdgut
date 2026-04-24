<?php

use App\Enums\Allergen;
use App\Support\WeeklyDistributionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD-346: Move allergies from free-text to enum-driven multi-select.
 *
 * - Adds `allergies_note` free-text fallback column to subscriptions + orders.
 * - Repurposes the existing `allergies` JSON column as a list of
 *   {@see Allergen} enum values only.
 * - Best-effort migrates existing free-text entries to enum values
 *   using an English + German synonym map. Unmapped free-text is
 *   moved into the new `allergies_note` column.
 *
 * The composition filter in {@see WeeklyDistributionService}
 * already reads `allergies` as an array of enum values, so narrowing the
 * semantics without renaming the column keeps the filtering behaviour intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('allergies_note', 500)->nullable()->after('allergies');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('allergies_note', 500)->nullable()->after('allergies');
        });

        $this->normaliseTable('subscriptions');
        $this->normaliseTable('orders');
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('allergies_note');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('allergies_note');
        });
    }

    private function normaliseTable(string $table): void
    {
        $rows = DB::table($table)->select(['id', 'allergies'])->get();
        $enumValues = array_flip(Allergen::values());

        foreach ($rows as $row) {
            $raw = $row->allergies;
            if ($raw === null || $raw === '') {
                continue;
            }

            $decoded = json_decode((string) $raw, true);
            if (! is_array($decoded)) {
                continue;
            }

            $enumMatches = [];
            $unmapped = [];

            foreach ($decoded as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                if (isset($enumValues[$value])) {
                    $enumMatches[$value] = true;

                    continue;
                }

                $mapped = $this->mapFreeText($value);
                if ($mapped !== null) {
                    $enumMatches[$mapped] = true;

                    continue;
                }

                $unmapped[] = $value;
            }

            DB::table($table)->where('id', $row->id)->update([
                'allergies' => json_encode(array_keys($enumMatches), JSON_UNESCAPED_UNICODE),
                'allergies_note' => $unmapped === [] ? null : mb_substr(implode(', ', $unmapped), 0, 500),
            ]);
        }
    }

    private function mapFreeText(string $value): ?string
    {
        $normalised = mb_strtolower($value);
        $normalised = strtr($normalised, [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
        ]);

        foreach ($this->synonyms() as $enumValue => $synonyms) {
            foreach ($synonyms as $needle) {
                if (str_contains($normalised, $needle)) {
                    return $enumValue;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function synonyms(): array
    {
        return [
            Allergen::Gluten->value => ['gluten', 'weizen', 'wheat', 'grain', 'getreide', 'roggen', 'rye', 'gerste', 'barley', 'hafer', 'oat', 'dinkel', 'spelt'],
            Allergen::Crustaceans->value => ['crustacean', 'krustentier', 'krabbe', 'crab', 'lobster', 'hummer', 'garnele', 'shrimp', 'scampi'],
            Allergen::Eggs->value => ['eier', 'eggs', ' egg', 'eigelb', 'eiweiss'],
            Allergen::Fish->value => ['fisch', 'fish', 'lachs', 'salmon', 'thun', 'tuna'],
            Allergen::Peanuts->value => ['peanut', 'erdnuss', 'erdnusse'],
            Allergen::Soybeans->value => ['soy', 'soja', 'soja-', 'tofu'],
            Allergen::Milk->value => ['milk', 'milch', 'dairy', 'milchprodukt', 'laktose', 'lactose', 'butter', 'kase', 'cheese'],
            Allergen::Nuts->value => ['nut', 'nuss', 'nusse', 'hazel', 'hasel', 'walnut', 'walnuss', 'almond', 'mandel', 'pecan', 'cashew', 'pistachio', 'pistazie'],
            Allergen::Celery->value => ['celery', 'sellerie'],
            Allergen::Mustard->value => ['mustard', 'senf'],
            Allergen::Sesame->value => ['sesame', 'sesam'],
            Allergen::Sulphites->value => ['sulphite', 'sulfite', 'sulfit', 'schwefel'],
            Allergen::Lupin->value => ['lupin', 'lupine'],
            Allergen::Molluscs->value => ['mollusc', 'mollusk', 'weichtier', 'muschel', 'mussel', 'octopus', 'squid', 'tintenfisch'],
        ];
    }
};
