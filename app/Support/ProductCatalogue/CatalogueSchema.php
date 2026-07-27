<?php

namespace App\Support\ProductCatalogue;

use App\Enums\CountUnitLabel;
use App\Enums\ProductCategory;
use App\Enums\ProductUnit;
use App\Exports\ProductCatalogueExport;
use App\Imports\ProductCsvParser;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * The single source of truth for the product-catalogue CSV shape, shared by
 * {@see ProductCatalogueExport} (writes) and the CSV parser
 * ({@see ProductCsvParser}, reads). Everything here is pure
 * mapping — column order, the unit/category tokens the sheet uses, the
 * boolean and tag encodings, and the weekly-availability column naming.
 *
 * Round-trip contract: exporting a product then re-importing the unchanged
 * row must be a no-op, so each `export` encoding has a matching lenient
 * `parse`.
 */
class CatalogueSchema
{
    /** Daily quantity at or above this counts as "unlimited". */
    public const UNLIMITED_SENTINEL = 999999;

    /** How many upcoming days the export emits availability columns for. */
    public const EXPORT_DAYS_AHEAD = 28;

    /** Worksheet (tab) titles of the export workbook. */
    public const SHEET_MAIN = 'Katalog';

    public const SHEET_PRICES = 'Preise';

    public const SHEET_INVENTORY = 'Bestand';

    /**
     * Columns of the "Preise" convenience sheet: the German name for
     * orientation plus every price-related field. The three editable prices are
     * `Cost`, `Business Net`, `Private Price` and `VAT %`; the netto/margin
     * columns are recomputed on export and ignored on import.
     *
     * @return list<string>
     */
    public static function pricesHeaders(): array
    {
        return [
            'Erdgut ID',
            'Name (DE)',
            'Unit',
            'Cost',
            'Business Net',
            'Business Margin %',
            'Private Price',
            'Private Netto',
            'Private Margin %',
            'VAT %',
        ];
    }

    /**
     * Fixed columns of the "Bestand" convenience sheet: the German name and
     * unit for orientation. One dated availability column per upcoming day is
     * appended after these, exactly like the main sheet.
     *
     * @return list<string>
     */
    public static function inventoryFixedHeaders(): array
    {
        return [
            'Erdgut ID',
            'Name (DE)',
            'Unit',
        ];
    }

    /**
     * Fixed (non-daily) columns, in order. One dated availability column per
     * upcoming day (`YYYY-MM-DD`) is appended after these by the export.
     *
     * @return list<string>
     */
    public static function fixedHeaders(): array
    {
        return [
            'Erdgut ID',
            'Name (DE)',
            'Name (EN)',
            'Name (FR)',
            'Main',
            'Producer',
            'Producer Name',
            'Sourcing ID',
            'Selling ID',
            'Category',
            'Sub-category',
            'Product Group',
            'Group Position',
            'Origin (DE)',
            'Origin (EN)',
            'Origin (FR)',
            'Variety (DE)',
            'Variety (EN)',
            'Variety (FR)',
            'Short Description (DE)',
            'Short Description (EN)',
            'Short Description (FR)',
            'Long Description (DE)',
            'Long Description (EN)',
            'Long Description (FR)',
            'Unit',
            'Quantity per Unit',
            'Cost',
            'Business Net',
            'Business Margin %',
            'Private Price',
            'Private Netto',
            'Private Margin %',
            'VAT %',
            'Season Start',
            'Season End',
            'Request Visibility',
            'Request Until',
            'Freshness (days)',
            'Published',
            'Available',
            'Egg add-on',
            'Allergens',
            'Dietary Tags',
            'Taste Tags',
            'Image Path',
            'Comment',
        ];
    }

    /**
     * Columns recomputed on export and ignored on import (they carry no field
     * of their own — they are derived or not editable via CSV).
     *
     * @return list<string>
     */
    public static function exportOnlyHeaders(): array
    {
        return ['Business Margin %', 'Private Netto', 'Private Margin %', 'Image Path'];
    }

    // ---------------------------------------------------------------- Units

    /**
     * The token that represents a product's unit in the sheet, e.g. `kg`,
     * `100g`, `l`, `St`, `Bd`, `Fl`, `Topf`.
     */
    public static function unitToken(Product $product): string
    {
        if ($product->unit === null) {
            return '';
        }

        return match ($product->unit) {
            ProductUnit::Mass, ProductUnit::Volume => $product->price_display_unit
                ?? $product->unit->defaultDisplayUnit(),
            ProductUnit::Bunch => 'Bd',
            ProductUnit::Piece => $product->count_unit_label instanceof CountUnitLabel
                ? self::COUNT_LABEL_TOKENS[$product->count_unit_label->value]
                : 'St',
        };
    }

    /** count_unit_label value → the German token written to the sheet. */
    private const COUNT_LABEL_TOKENS = [
        'pot' => 'Topf',
        'bag' => 'Beutel',
        'jar' => 'Glas',
        'bottle' => 'Fl',
        'pack' => 'Pack',
        'tray' => 'Schale',
        'net' => 'Netz',
        'crate' => 'Kiste',
    ];

    /**
     * Parse a sheet unit token into the trio the writer needs. Lenient:
     * accepts German words, English words, and common abbreviations.
     *
     * @return array{unit: string, price_display_unit: string, count_unit_label: ?string}|null
     */
    public static function parseUnitToken(string $raw): ?array
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/', '', $raw) ?? ''));
        if ($key === '') {
            return null;
        }

        return match ($key) {
            'st', 'stk', 'stück', 'stueck', 'piece', 'pc', 'pcs' => self::unitTrio('piece', 'piece', null),
            'bd', 'bund', 'bunch' => self::unitTrio('bunch', 'bunch', null),
            'kg' => self::unitTrio('mass', 'kg', null),
            'g', 'gr', 'gramm' => self::unitTrio('mass', 'g', null),
            '100g' => self::unitTrio('mass', '100g', null),
            'ml' => self::unitTrio('volume', 'ml', null),
            'l', 'liter', 'litre', 'ltr' => self::unitTrio('volume', 'l', null),
            'fl', 'flasche', 'bottle' => self::unitTrio('piece', 'piece', 'bottle'),
            'topf', 'pot' => self::unitTrio('piece', 'piece', 'pot'),
            'schale', 'tray' => self::unitTrio('piece', 'piece', 'tray'),
            'beutel', 'bag' => self::unitTrio('piece', 'piece', 'bag'),
            'glas', 'jar' => self::unitTrio('piece', 'piece', 'jar'),
            'pack', 'packung' => self::unitTrio('piece', 'piece', 'pack'),
            'netz', 'net' => self::unitTrio('piece', 'piece', 'net'),
            'kiste', 'crate' => self::unitTrio('piece', 'piece', 'crate'),
            default => null,
        };
    }

    /**
     * @return array{unit: string, price_display_unit: string, count_unit_label: ?string}
     */
    private static function unitTrio(string $unit, string $displayUnit, ?string $countLabel): array
    {
        return ['unit' => $unit, 'price_display_unit' => $displayUnit, 'count_unit_label' => $countLabel];
    }

    // ------------------------------------------------------------ Categories

    /** German admin label for a category value, written to the sheet. */
    public static function categoryLabel(?string $value): string
    {
        return match ($value) {
            'produce' => 'Obst & Gemüse',
            'fish' => 'Fisch & Meeresfrüchte',
            'meat' => 'Fleisch',
            'eggs_and_dairy' => 'Eier & Milchprodukte',
            'bread' => 'Brot',
            default => '',
        };
    }

    /**
     * Parse a category label (German, English, or the enum value itself)
     * into a {@see ProductCategory} value. Null if unrecognised.
     */
    public static function parseCategory(string $raw): ?string
    {
        $key = mb_strtolower(trim($raw));
        if ($key === '') {
            return null;
        }

        $map = [
            'produce' => 'produce',
            'obst & gemüse' => 'produce',
            'obst & gemuese' => 'produce',
            'obst und gemüse' => 'produce',
            'fruit & vegetables' => 'produce',
            'fish' => 'fish',
            'fisch' => 'fish',
            'fisch & meeresfrüchte' => 'fish',
            'fish & seafood' => 'fish',
            'meat' => 'meat',
            'fleisch' => 'meat',
            'eggs_and_dairy' => 'eggs_and_dairy',
            'eggs & dairy' => 'eggs_and_dairy',
            'eggs and dairy' => 'eggs_and_dairy',
            'eier & milchprodukte' => 'eggs_and_dairy',
            'bread' => 'bread',
            'brot' => 'bread',
            'bakery' => 'bread',
            'bakery & dry goods' => 'bread',
        ];

        $value = $map[$key] ?? null;

        return $value !== null && in_array($value, ProductCategory::values(), true) ? $value : null;
    }

    // --------------------------------------------------------------- Tags

    /**
     * @param  list<string>|null  $values
     */
    public static function joinTags(?array $values): string
    {
        return implode('; ', array_filter((array) $values, static fn ($v): bool => is_string($v) && $v !== ''));
    }

    /**
     * @return list<string>
     */
    public static function splitTags(string $raw): array
    {
        $parts = preg_split('/[;,]/', $raw) ?: [];

        return array_values(array_filter(array_map(
            static fn ($v): string => mb_strtolower(trim((string) $v)),
            $parts,
        ), static fn ($v): bool => $v !== ''));
    }

    // ------------------------------------------------------------- Booleans

    public static function bool(?bool $value): string
    {
        return $value ? '1' : '0';
    }

    public static function parseBool(string $raw): bool
    {
        return in_array(mb_strtolower(trim($raw)), ['1', 'true', 'ja', 'yes', 'y', 'x'], true);
    }

    // ----------------------------------------------------------------- Days

    /**
     * Header for a day's availability column: the plain ISO date `YYYY-MM-DD`,
     * unambiguous across locales and year boundaries and trivially parseable
     * on re-import.
     */
    public static function dayHeader(Carbon $date): string
    {
        return $date->toDateString();
    }

    /**
     * The day a `YYYY-MM-DD` header refers to (start of that day), or null if
     * the header isn't a recognisable date column. Only strict ISO dates count
     * as availability columns, so ordinary text headers are never mistaken for
     * one.
     */
    public static function parseDayHeader(string $header): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($header), $m)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($header))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The next `$count` days starting from the day of `$from`, keyed by their
     * column header.
     *
     * @return array<string, Carbon>
     */
    public static function dayHeadersFrom(Carbon $from, int $count): array
    {
        $day = $from->copy()->startOfDay();
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $date = $day->copy()->addDays($i);
            $out[self::dayHeader($date)] = $date;
        }

        return $out;
    }
}
