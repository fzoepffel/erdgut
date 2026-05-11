<?php

use App\Enums\ProductUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill `products.unit` from the historical `product_translations.unit`
 * freeform values.
 *
 * Strategy: prefer the German row (highest signal for production data),
 * fall back to any other locale row, fall back to 'piece' for products
 * with no translation at all. Unmapped strings are logged so the team
 * can audit before the next migration drops the freeform column.
 *
 * Step 2 of 3 in the freeform → enum migration. Idempotent: re-running
 * is safe (only writes products with a NULL unit).
 */
return new class extends Migration
{
    /**
     * Map normalized freeform values to the ProductUnit enum.
     * Keys are lowercase + trimmed + punctuation-stripped versions of
     * what we've actually seen in seeders/factories/admin input.
     */
    private const TEXT_TO_UNIT = [
        'g' => 'g',
        'gr' => 'g',
        'gram' => 'g',
        'grams' => 'g',
        'gramm' => 'g',

        'kg' => 'kg',
        'kilo' => 'kg',
        'kilos' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'kilogramm' => 'kg',

        'ml' => 'ml',
        'milliliter' => 'ml',
        'milliliters' => 'ml',

        'l' => 'l',
        'liter' => 'l',
        'liters' => 'l',
        'litre' => 'l',
        'litres' => 'l',

        'piece' => 'piece',
        'pieces' => 'piece',
        'pc' => 'piece',
        'pcs' => 'piece',
        'stk' => 'piece',
        'stueck' => 'piece',
        'stuck' => 'piece',

        'bunch' => 'bunch',
        'bunches' => 'bunch',
        'bund' => 'bunch',
        'bundle' => 'bunch',
    ];

    public function up(): void
    {
        $rows = DB::table('products')
            ->whereNull('unit')
            ->select(['id'])
            ->get();

        $unmapped = [];

        foreach ($rows as $row) {
            $translation = DB::table('product_translations')
                ->where('product_id', $row->id)
                ->orderByRaw("CASE locale WHEN 'de' THEN 1 WHEN 'en' THEN 2 WHEN 'fr' THEN 3 ELSE 4 END")
                ->whereNotNull('unit')
                ->where('unit', '!=', '')
                ->first(['unit', 'locale']);

            $mapped = ProductUnit::Piece->value;
            if ($translation !== null) {
                $normalized = $this->normalize((string) $translation->unit);
                if (isset(self::TEXT_TO_UNIT[$normalized])) {
                    $mapped = self::TEXT_TO_UNIT[$normalized];
                } else {
                    $unmapped[] = [
                        'product_id' => $row->id,
                        'locale' => $translation->locale,
                        'raw_unit' => $translation->unit,
                        'defaulted_to' => $mapped,
                    ];
                }
            }

            DB::table('products')->where('id', $row->id)->update(['unit' => $mapped]);
        }

        if ($unmapped !== []) {
            Log::warning('product_unit_backfill: unmapped translation unit values defaulted to "piece"', [
                'count' => count($unmapped),
                'rows' => $unmapped,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')->update(['unit' => null]);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ö', 'ä', 'ü', 'ß', '.', ',', '/', '\\'], ['oe', 'ae', 'ue', 'ss', '', '', '', ''], $value);

        return preg_replace('/\s+/', '', $value) ?? $value;
    }
};
