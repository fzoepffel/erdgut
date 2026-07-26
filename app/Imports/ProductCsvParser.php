<?php

namespace App\Imports;

use App\Enums\Allergen;
use App\Enums\DietaryTag;
use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use App\Exports\ProductCatalogueExport;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductSubcategory;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads a catalogue CSV (as produced by {@see ProductCatalogueExport}
 * or hand-edited from it) into a normalized, validated plan the importer can
 * preview and then apply. It writes NOTHING: {@see parse()} only reads the file
 * and the current catalogue to resolve references and detect creates/updates.
 *
 * Rows are grouped into products by the `Erdgut ID` column (blank ID = a new
 * product, whose secondary-producer rows are the blank-ID rows that directly
 * follow it). Each product plan carries a ready-to-write payload for
 * {@see ProductWriter::upsert()} plus its weekly-availability
 * intents ({@see ProductCatalogueImport} Module B).
 */
class ProductCsvParser
{
    /** @var array<string, int> lowercased producer name (any locale) => id */
    private array $producerByName;

    /** @var array<string, array<string, int>> category => lowercased label => subcategory id */
    private array $subcategoryByLabel;

    /** @var array<string, int> lowercased de group name => group id */
    private array $groupByName;

    private bool $loaded = false;

    /**
     * @return array{
     *   products: list<array<string, mixed>>,
     *   errors: list<array{row:int, message:string}>,
     *   summary: array{to_create:int, to_update:int, new_groups:list<string>, errors:int, weekly_changes:int}
     * }
     */
    public function parse(string $absolutePath): array
    {
        $this->loadLookups();

        [$headers, $rows] = str_ends_with(strtolower($absolutePath), '.xlsx')
            ? $this->readXlsx($absolutePath)
            : $this->readCsv($absolutePath);
        if ($headers === []) {
            return $this->emptyResult('The file is empty or has no header row.');
        }

        $weekColumns = $this->weekColumns($headers);
        $buckets = $this->groupIntoProducts($rows);

        $products = [];
        $errors = [];
        $newGroups = [];

        foreach ($buckets as $bucket) {
            $plan = $this->buildPlan($bucket, $weekColumns);
            if (isset($plan['error'])) {
                $errors[] = ['row' => $bucket['row'], 'message' => $plan['error']];

                continue;
            }
            if ($plan['new_group'] !== null && ! in_array($plan['new_group'], $newGroups, true)) {
                $newGroups[] = $plan['new_group'];
            }
            $products[] = $plan;
        }

        $toCreate = count(array_filter($products, static fn ($p): bool => $p['action'] === 'create'));
        $weeklyChanges = array_sum(array_map(static fn ($p): int => count($p['weekly']), $products));

        return [
            'products' => $products,
            'errors' => $errors,
            'summary' => [
                'to_create' => $toCreate,
                'to_update' => count($products) - $toCreate,
                'new_groups' => $newGroups,
                'errors' => count($errors),
                'weekly_changes' => $weeklyChanges,
            ],
        ];
    }

    // ------------------------------------------------------------- CSV read

    /**
     * @return array{0: list<string>, 1: list<array{_row:int, cells:array<string,string>}>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        // Skip a leading UTF-8 BOM at the byte level. If left in place fgetcsv
        // sees `\xEF\xBB\xBF"Erdgut ID"`, treats the first field as unquoted,
        // and keeps the literal quotes so the header never matches.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $delimiter = $this->sniffDelimiter($path);
        $matrix = [];
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $matrix[] = array_map(static fn ($c): string => (string) $c, $cells);
        }
        fclose($handle);

        return $this->buildHeadersAndRows($matrix);
    }

    /**
     * Read the first sheet of an XLSX into the same [headers, rows] shape as
     * {@see readCsv()}. XLSX has no BOM/delimiter concerns; embedded line
     * breaks in a cell survive as-is.
     *
     * @return array{0: list<string>, 1: list<array{_row:int, cells:array<string,string>}>}
     */
    private function readXlsx(string $path): array
    {
        $sheets = Excel::toArray(new class {}, $path);
        $matrix = $sheets[0] ?? [];

        return $this->buildHeadersAndRows($matrix);
    }

    /**
     * Turn a raw 2D cell matrix (row 1 = headers) into the normalized
     * [headers, rows] structure the plan builder consumes: headers cleaned,
     * empty rows dropped, every value trimmed and keyed by its header.
     *
     * @param  array<int, array<int, mixed>>  $matrix
     * @return array{0: list<string>, 1: list<array{_row:int, cells:array<string,string>}>}
     */
    private function buildHeadersAndRows(array $matrix): array
    {
        $headers = [];
        $rows = [];
        foreach ($matrix as $line0 => $cells) {
            $line = $line0 + 1;
            if ($line === 1) {
                $headers = array_map(fn ($h): string => $this->cleanHeader((string) $h), $cells);

                continue;
            }
            if ($this->isRowEmpty($cells)) {
                continue;
            }
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[] = ['_row' => $line, 'cells' => $assoc];
        }

        return [$headers, $rows];
    }

    private function sniffDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $first = $handle !== false ? (string) fgets($handle) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        return substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    }

    private function cleanHeader(string $header): string
    {
        // Strip a UTF-8 BOM off the first header and normalize whitespace.
        return trim(preg_replace('/\s+/', ' ', str_replace("\u{FEFF}", '', $header)) ?? '');
    }

    /**
     * @param  array<int, string|null>  $cells
     */
    private function isRowEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Week columns keyed by header → that week's Monday.
     *
     * @param  list<string>  $headers
     * @return array<string, Carbon>
     */
    private function weekColumns(array $headers): array
    {
        $out = [];
        foreach ($headers as $header) {
            $monday = CatalogueSchema::parseWeekHeader($header);
            if ($monday !== null) {
                $out[$header] = $monday;
            }
        }

        return $out;
    }

    // -------------------------------------------------------------- Grouping

    /**
     * Group data rows into product buckets. A row with an `Erdgut ID` joins
     * that id's bucket; a blank-ID row starts a new-product bucket unless it is
     * a bare secondary-producer row (only Producer/Cost filled) directly under
     * a new product, in which case it attaches there.
     *
     * @param  list<array{_row:int, cells:array<string,string>}>  $rows
     * @return list<array{id:?int, row:int, rows:list<array<string,string>>}>
     */
    private function groupIntoProducts(array $rows): array
    {
        $buckets = [];
        $byId = [];
        $lastNewKey = null;

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $id = trim($cells['Erdgut ID'] ?? '');

            if ($id !== '' && ctype_digit($id)) {
                $key = 'id:'.$id;
                if (! isset($byId[$key])) {
                    $byId[$key] = count($buckets);
                    $buckets[] = ['id' => (int) $id, 'row' => $row['_row'], 'rows' => []];
                }
                $buckets[$byId[$key]]['rows'][] = $cells;
                $lastNewKey = null;

                continue;
            }

            if ($lastNewKey !== null && $this->isSecondaryRow($cells)) {
                $buckets[$lastNewKey]['rows'][] = $cells;

                continue;
            }

            $lastNewKey = count($buckets);
            $buckets[] = ['id' => null, 'row' => $row['_row'], 'rows' => [$cells]];
        }

        return $buckets;
    }

    /**
     * A secondary-producer row: no product-level content, just a Producer
     * (and maybe its Cost).
     *
     * @param  array<string, string>  $cells
     */
    private function isSecondaryRow(array $cells): bool
    {
        $main = CatalogueSchema::parseBool($cells['Main'] ?? '');
        $hasProduct = ($cells['Name (DE)'] ?? '') !== '' || ($cells['Category'] ?? '') !== '';

        return ! $main && ! $hasProduct && ($cells['Producer'] ?? '') !== '';
    }

    // ---------------------------------------------------------- Plan builder

    /**
     * Validate one bucket and build its write plan, or return `['error' => …]`.
     *
     * @param  array{id:?int, row:int, rows:list<array<string,string>>}  $bucket
     * @param  array<string, Carbon>  $weekColumns
     * @return array<string, mixed>
     */
    private function buildPlan(array $bucket, array $weekColumns): array
    {
        $rows = $bucket['rows'];
        // Primary row: the one flagged Main, else the one with the most
        // product content, else the first.
        $primary = null;
        foreach ($rows as $r) {
            if (CatalogueSchema::parseBool($r['Main'] ?? '')) {
                $primary = $r;
                break;
            }
        }
        $primary ??= $rows[0];

        $existing = null;
        if ($bucket['id'] !== null) {
            $existing = Product::query()->with(['translations', 'producers'])->find($bucket['id']);
            if ($existing === null) {
                return ['error' => "Erdgut ID {$bucket['id']} does not exist."];
            }
        }

        $warnings = [];

        // --- Unit ---
        $unitToken = $primary['Unit'] ?? '';
        $unit = CatalogueSchema::parseUnitToken($unitToken);
        if ($unit === null) {
            return ['error' => "Unrecognised unit \"{$unitToken}\"."];
        }

        // --- Category ---
        $category = CatalogueSchema::parseCategory($primary['Category'] ?? '');
        if ($category === null) {
            return ['error' => 'Unrecognised category "'.($primary['Category'] ?? '').'".'];
        }

        // --- Prices ---
        $price = $this->parseMoney($primary['Private Price'] ?? '');
        if ($price === null) {
            return ['error' => 'Missing or invalid Private Price.'];
        }

        // --- Producers ---
        $producers = [];
        $producerIds = [];
        $unknownProducers = [];
        foreach ($rows as $r) {
            $name = trim($r['Producer'] ?? '');
            if ($name === '') {
                continue;
            }
            $pid = $this->producerByName[mb_strtolower($name)] ?? null;
            if ($pid === null) {
                $warnings[] = "Unknown producer \"{$name}\" ignored.";
                $unknownProducers[] = $name;

                continue;
            }
            if (in_array($pid, $producerIds, true)) {
                continue;
            }
            $producerIds[] = $pid;
            $producers[] = [
                'producer_id' => $pid,
                'buying_price' => $this->parseMoney($r['Cost'] ?? ''),
                'main' => CatalogueSchema::parseBool($r['Main'] ?? '') || $r === $primary,
            ];
        }
        // The primary producer (Main row's) must lead the list.
        usort($producers, static fn ($a, $b): int => ($b['main'] ? 1 : 0) <=> ($a['main'] ? 1 : 0));
        $producerIds = array_map(static fn ($p): int => $p['producer_id'], $producers);
        if ($producerIds === []) {
            return ['error' => $unknownProducers === []
                ? 'No producer given for this product.'
                : 'Unknown producer "'.implode('", "', $unknownProducers).'".'];
        }

        // --- Translations ---
        $translations = $this->parseTranslations($primary);
        if ($bucket['id'] === null && ($translations['de']['name'] ?? '') === '') {
            return ['error' => 'A new product needs a German name.'];
        }

        // --- Subcategory ---
        $subLabel = trim($primary['Sub-category'] ?? '');
        $subcategoryId = null;
        if ($subLabel !== '') {
            $subcategoryId = $this->subcategoryByLabel[$category][mb_strtolower($subLabel)] ?? null;
            if ($subcategoryId === null) {
                $warnings[] = "Sub-category \"{$subLabel}\" not found for this category; left empty.";
            }
        }

        // --- Group (auto-created if the de name is new) ---
        $groupName = trim($primary['Product Group'] ?? '');
        $newGroup = null;
        if ($groupName !== '' && ! isset($this->groupByName[mb_strtolower($groupName)])) {
            $newGroup = $groupName;
        }

        // --- Tags (validated against their enums) ---
        $allergens = $this->validTags($primary['Allergens'] ?? '', Allergen::values(), 'allergen', $warnings);
        $dietary = $this->validTags($primary['Dietary Tags'] ?? '', DietaryTag::values(), 'dietary tag', $warnings);
        $taste = $this->validTags($primary['Taste Tags'] ?? '', TasteProfile::values(), 'taste tag', $warnings);

        // --- Season ---
        [$seasonStartM, $seasonStartD] = $this->parseSeason($primary['Season Start'] ?? '');
        [$seasonEndM, $seasonEndD] = $this->parseSeason($primary['Season End'] ?? '');
        $isSeasonal = $seasonStartM !== null && $seasonEndM !== null;

        // --- VAT ---
        $vat = $this->parseInt($primary['VAT %'] ?? '', 7);
        if (! in_array($vat, [7, 19], true)) {
            $warnings[] = "VAT {$vat}% is not 7 or 19; defaulted to 7.";
            $vat = 7;
        }

        // --- Quantity per unit (only meaningful for piece/bunch) ---
        $quantity = $this->parseInt($primary['Quantity per Unit'] ?? '', 0);
        $useVolume = $existing !== null && $existing->unit_volume_ml !== null && $existing->unit_weight_grams === null;

        $data = [
            'unit' => $unit['unit'],
            'price_display_unit' => $unit['price_display_unit'],
            'count_unit_label' => $unit['count_unit_label'],
            'price' => $price,
            'producer_ids' => $producerIds,
            'producers' => $producers,
            'category' => $category,
            'subcategory_id' => $subcategoryId,
            'vat_rate_percent' => $vat,
            'default_freshness_window_days' => $this->parseInt($primary['Freshness (days)'] ?? '', 1),
            'is_seasonal' => $isSeasonal,
            'season_start_month' => $seasonStartM,
            'season_start_day' => $seasonStartD,
            'season_end_month' => $seasonEndM,
            'season_end_day' => $seasonEndD,
            'request_visibility' => $this->parseVisibility($primary['Request Visibility'] ?? ''),
            'request_visibility_until' => $this->parseDate($primary['Request Until'] ?? ''),
            'is_available' => CatalogueSchema::parseBool($primary['Available'] ?? ''),
            'is_published' => CatalogueSchema::parseBool($primary['Published'] ?? ''),
            'is_egg_addon' => CatalogueSchema::parseBool($primary['Egg add-on'] ?? ''),
            'allergens' => $allergens,
            'dietary_tags' => $dietary,
            'taste_profile_tags' => $taste,
            'buying_price' => $producers[0]['buying_price'],
            'business_price' => $this->parseMoney($primary['Business Net'] ?? ''),
            'group_name' => $groupName !== '' ? ['de' => $groupName] : [],
            'group_position' => $this->parseInt($primary['Group Position'] ?? '', 0),
            'translations' => $translations,
            'metadata' => [
                'producer_product_name' => trim($primary['Producer Name'] ?? ''),
                'sourcing_id' => trim($primary['Sourcing ID'] ?? ''),
                'selling_id' => trim($primary['Selling ID'] ?? ''),
                'comment' => trim($primary['Comment'] ?? ''),
            ],
        ];

        // Quantity descriptor only lands for the count kinds.
        if (in_array($unit['unit'], ['piece', 'bunch'], true) && $quantity > 0) {
            $data[$useVolume ? 'unit_volume_ml' : 'unit_weight_grams'] = $quantity;
        }

        // Preserve product-level fields the CSV has no column for, so a
        // round-trip update never silently wipes them.
        if ($existing !== null) {
            $data['custom_taste_tags'] = $existing->custom_taste_tags;
            $data['quantity_step'] = $existing->quantity_step;
            $data['is_test'] = (bool) $existing->is_test;
        }

        $weekly = $this->parseWeekly($primary, $weekColumns, $unit);

        return [
            'action' => $bucket['id'] === null ? 'create' : 'update',
            'product_id' => $bucket['id'],
            'row' => $bucket['row'],
            'name' => $translations['de']['name'] !== '' ? $translations['de']['name'] : ($existing?->translationFor('de')?->name ?? '#'.$bucket['id']),
            'data' => $data,
            'weekly' => $weekly,
            'new_group' => $newGroup,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, array{name:string, origin:string, variety:string, taste_profile:string, short_description:string}>
     */
    private function parseTranslations(array $row): array
    {
        $suffix = ['de' => '(DE)', 'en' => '(EN)', 'fr' => '(FR)'];
        $out = [];
        foreach ($suffix as $locale => $s) {
            $out[$locale] = [
                'name' => trim($row["Name {$s}"] ?? ''),
                'origin' => trim($row["Origin {$s}"] ?? ''),
                'variety' => trim($row["Variety {$s}"] ?? ''),
                'short_description' => trim($row["Short Description {$s}"] ?? ''),
                'taste_profile' => trim($row["Long Description {$s}"] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Weekly availability intents for the product. Blank cells are skipped
     * (no change); `Unlimited` maps to the sentinel; a number is converted
     * from the display unit to canonical.
     *
     * @param  array<string, string>  $row
     * @param  array<string, Carbon>  $weekColumns
     * @param  array{unit:string, price_display_unit:string, count_unit_label:?string}  $unit
     * @return list<array{monday:string, canonical:float, raw:string}>
     */
    private function parseWeekly(array $row, array $weekColumns, array $unit): array
    {
        $factor = ProductUnit::from($unit['unit'])->canonicalUnitsPerDisplay($unit['price_display_unit']);
        $out = [];
        foreach ($weekColumns as $header => $monday) {
            $raw = trim($row[$header] ?? '');
            if ($raw === '') {
                continue;
            }
            if (mb_strtolower($raw) === 'unlimited') {
                $canonical = (float) CatalogueSchema::UNLIMITED_SENTINEL;
            } else {
                $value = $this->parseDecimal($raw);
                if ($value === null) {
                    continue;
                }
                $canonical = round($value * $factor, 4);
            }
            $out[] = ['monday' => $monday->toDateString(), 'canonical' => $canonical, 'raw' => $raw];
        }

        return $out;
    }

    // ----------------------------------------------------------- primitives

    /**
     * @param  list<string>  $allowed
     * @param  list<string>  $warnings
     * @return list<string>|null
     */
    private function validTags(string $raw, array $allowed, string $kind, array &$warnings): ?array
    {
        $tags = [];
        foreach (CatalogueSchema::splitTags($raw) as $tag) {
            if (in_array($tag, $allowed, true)) {
                $tags[] = $tag;
            } else {
                $warnings[] = "Unknown {$kind} \"{$tag}\" ignored.";
            }
        }

        return $tags === [] ? null : $tags;
    }

    private function parseMoney(string $raw): ?float
    {
        $raw = trim(str_replace(['€', ' ', "\u{00A0}"], '', $raw));
        if ($raw === '') {
            return null;
        }

        return $this->parseDecimal($raw);
    }

    /** Parse a decimal that may use a comma or dot as the decimal separator. */
    private function parseDecimal(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');
        if ($hasComma && $hasDot) {
            // The rightmost separator is the decimal; the other is thousands.
            $raw = strrpos($raw, ',') > strrpos($raw, '.')
                ? str_replace('.', '', $raw)          // 1.234,56
                : str_replace(',', '', $raw);          // 1,234.56
            $raw = str_replace(',', '.', $raw);
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private function parseInt(string $raw, int $default): int
    {
        $raw = trim($raw);

        return $raw !== '' && is_numeric($raw) ? (int) round((float) $raw) : $default;
    }

    /**
     * @return array{0: ?int, 1: ?int} month, day
     */
    private function parseSeason(string $raw): array
    {
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', trim($raw), $m)) {
            $month = (int) $m[1];
            $day = (int) $m[2];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return [$month, $day];
            }
        }

        return [null, null];
    }

    private function parseVisibility(string $raw): string
    {
        $v = mb_strtolower(trim($raw));

        return in_array($v, ['auto', 'always', 'never'], true) ? $v : 'auto';
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------- lookups

    private function loadLookups(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->producerByName = [];
        Producer::query()->with('translations')->get()->each(function (Producer $p): void {
            foreach ($p->translations as $t) {
                $name = mb_strtolower(trim((string) $t->name));
                if ($name !== '') {
                    $this->producerByName[$name] = $p->id;
                }
            }
        });

        $this->subcategoryByLabel = [];
        ProductSubcategory::query()->get()->each(function (ProductSubcategory $s): void {
            foreach ([$s->label_de, $s->label_en, $s->label_fr] as $label) {
                $label = mb_strtolower(trim((string) $label));
                if ($label !== '') {
                    $this->subcategoryByLabel[$s->category][$label] = $s->id;
                }
            }
        });

        $this->groupByName = [];
        ProductGroup::query()->get()->each(function (ProductGroup $g): void {
            $de = mb_strtolower(trim((string) (((array) $g->name)['de'] ?? '')));
            if ($de !== '') {
                $this->groupByName[$de] = $g->id;
            }
        });

        $this->loaded = true;
    }

    /**
     * @return array{products: list<never>, errors: list<array{row:int, message:string}>, summary: array{to_create:int, to_update:int, new_groups:list<never>, errors:int, weekly_changes:int}}
     */
    private function emptyResult(string $message): array
    {
        return [
            'products' => [],
            'errors' => [['row' => 0, 'message' => $message]],
            'summary' => ['to_create' => 0, 'to_update' => 0, 'new_groups' => [], 'errors' => 1, 'weekly_changes' => 0],
        ];
    }
}
