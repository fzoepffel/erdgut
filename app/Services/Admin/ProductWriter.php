<?php

namespace App\Services\Admin;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Imports\ProductCatalogueImport;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTranslation;
use Illuminate\Support\Facades\DB;

/**
 * The single write path for products, shared by the admin product form
 * ({@see \App\Http\Controllers\Admin\ProductController}) and the catalogue
 * CSV import ({@see ProductCatalogueImport}).
 *
 * {@see coreAttributes()} is the pure field mapping used by both surfaces:
 * it turns a validated/normalized input array into the `products` column
 * payload (display→canonical price, buying cents, season window, unit
 * descriptors, flags, tag arrays). {@see upsert()} is the import entry point
 * that composes the whole product: core columns + producers + per-farm buying
 * prices + group (resolved/created by name) + business price rule +
 * translations + price-history snapshot, all in one call so a CSV row lands
 * exactly like a form save.
 */
class ProductWriter
{
    /**
     * Turn a validated product input array into the `products` column
     * payload. Moved verbatim from the product controller so the form and
     * the CSV import build identical rows.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function coreAttributes(array $validated): array
    {
        // The admin enters a price in their chosen `price_display_unit`
        // (€/g, €/kg, €/ml, €/L, €/piece, €/bunch). Storage is always
        // per *canonical* unit of the kind, so divide by the factor
        // (1000 for kg/L, 1 for g/ml/piece/bunch).
        $unitEnum = ProductUnit::from($validated['unit']);
        $displayUnit = $validated['price_display_unit'];
        $canonicalUnitPrice = round(
            ((float) $validated['price']) / $unitEnum->canonicalUnitsPerDisplay($displayUnit),
            4,
        );

        $payload = [
            'producer_id' => (int) $validated['producer_ids'][0],
            'category' => $validated['category'],
            'subcategory_id' => isset($validated['subcategory_id']) && (int) $validated['subcategory_id'] > 0
                ? (int) $validated['subcategory_id']
                : null,
            'unit' => $validated['unit'],
            'price_display_unit' => $displayUnit,
            'unit_price' => $canonicalUnitPrice,
            'vat_rate_percent' => (int) ($validated['vat_rate_percent'] ?? 7),
            'default_freshness_window_days' => isset($validated['default_freshness_window_days'])
                ? max(1, (int) $validated['default_freshness_window_days'])
                : 1,
            'is_seasonal' => (bool) ($validated['is_seasonal'] ?? false),
            'request_visibility' => in_array($validated['request_visibility'] ?? 'auto', ['auto', 'always', 'never'], true)
                ? ($validated['request_visibility'] ?? 'auto')
                : 'auto',
            // The form sets the PERMANENT state; time-bound overrides are
            // managed on the Anfrage-Sortiment page and reset here.
            'request_visibility_until' => null,
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'is_test' => (bool) ($validated['is_test'] ?? false),
            'allergens' => $this->normalizeEnumArray($validated['allergens'] ?? null),
            'dietary_tags' => $this->normalizeEnumArray($validated['dietary_tags'] ?? null),
            'taste_profile_tags' => $this->normalizeEnumArray($validated['taste_profile_tags'] ?? null),
            'custom_taste_tags' => $this->normalizeCustomTasteTags($validated['custom_taste_tags'] ?? null),
        ];

        $buyingPrice = $validated['buying_price'] ?? null;
        $payload['buying_price_cents'] = ($buyingPrice === null || $buyingPrice === '')
            ? null
            : (int) round(((float) $buyingPrice) * 100);

        // Quantity step: only stored for mass/volume; cleared for piece/bunch.
        $isMeasured = in_array($unitEnum, [ProductUnit::Mass, ProductUnit::Volume], true);
        $payload['quantity_step'] = $isMeasured && isset($validated['quantity_step']) && $validated['quantity_step'] !== ''
            ? (int) $validated['quantity_step']
            : null;

        // Unit descriptors: only stored for piece/bunch; cleared for mass/volume.
        $isCount = in_array($unitEnum, [ProductUnit::Piece, ProductUnit::Bunch], true);
        $payload['unit_weight_grams'] = $isCount && isset($validated['unit_weight_grams']) && $validated['unit_weight_grams'] !== ''
            ? (int) $validated['unit_weight_grams']
            : null;
        $payload['unit_volume_ml'] = $isCount && isset($validated['unit_volume_ml']) && $validated['unit_volume_ml'] !== ''
            ? (int) $validated['unit_volume_ml']
            : null;

        // Display word ("Topf", "Beutel"): only stored for the count kinds
        // (piece/bunch); cleared for every other kind so a switched product
        // loses the stale word.
        $payload['count_unit_label'] = $isCount
            && ($validated['count_unit_label'] ?? '') !== ''
            ? $validated['count_unit_label']
            : null;

        // Recurring yearly season window. Only persisted when the product is
        // flagged seasonal; cleared otherwise so a toggled-off product doesn't
        // keep a stale window.
        $seasonFields = ['season_start_month', 'season_start_day', 'season_end_month', 'season_end_day'];
        foreach ($seasonFields as $field) {
            $payload[$field] = $payload['is_seasonal'] && isset($validated[$field]) && $validated[$field] !== ''
                ? (int) $validated[$field]
                : null;
        }

        return $payload;
    }

    /**
     * Create or update a product from a normalized CSV payload. Composes the
     * full product exactly as the form does. Runs inside the caller's
     * transaction (the importer wraps the whole confirm step).
     *
     * Expected $data keys (all optional unless noted):
     *  - every key {@see coreAttributes()} reads (unit, price_display_unit,
     *    price, producer_ids [main first], category, …) — REQUIRED for create
     *  - is_egg_addon (bool), request_visibility_until (Y-m-d|null)
     *  - metadata (array) — extra sourcing keys, merged over existing
     *  - producers: list<array{producer_id:int, buying_price:?float, main:bool}>
     *  - business_price (?float, display-unit net)
     *  - group_name (?array{de,en,fr}) resolved/created by German name
     *  - group_label (?array{de,en,fr}), group_position (int)
     *  - translations: array<locale, array{name,origin,variety,taste_profile,short_description}>
     *
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data, ?Product $product = null, ?int $userId = null): Product
    {
        $core = $this->coreAttributes($data);

        // CSV can set a time-bound request-visibility override (the form
        // resets it); a per-row egg-addon flag; and the lossless metadata
        // extras, merged over whatever the product already carries.
        if (array_key_exists('request_visibility_until', $data)) {
            $core['request_visibility_until'] = $data['request_visibility_until'] ?: null;
        }
        if (array_key_exists('is_egg_addon', $data)) {
            $core['is_egg_addon'] = (bool) $data['is_egg_addon'];
        }
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $existing = is_array($product?->metadata) ? $product->metadata : [];
            $merged = array_merge($existing, $data['metadata']);
            $core['metadata'] = $merged === [] ? null : $merged;
        }

        return DB::transaction(function () use ($core, $data, $product, $userId): Product {
            $product = $product === null
                ? Product::query()->create($core)
                : tap($product)->update($core);

            $producers = array_values(array_filter(
                (array) ($data['producers'] ?? []),
                static fn ($row): bool => is_array($row) && (int) ($row['producer_id'] ?? 0) > 0,
            ));
            if ($producers !== []) {
                $product->syncProducers(array_map(static fn ($r): int => (int) $r['producer_id'], $producers));
                $this->syncProducerBuyingPrices($product, $producers);
            }

            $this->syncGroupByName($product, $data);
            $this->syncBusinessPrice($product, $data['business_price'] ?? null, $userId);
            $this->syncTranslations($product, (array) ($data['translations'] ?? []));

            $product = $product->fresh();
            app(PriceHistoryRecorder::class)->record($product->load('variants', 'producers'));

            return $product;
        });
    }

    /** Canonical units per display unit (1000 for kg/L, 1 for piece/g/…). */
    public function displayFactor(Product $product): int
    {
        if ($product->unit === null) {
            return 1;
        }
        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
        $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);

        return $factor > 0 ? $factor : 1;
    }

    /**
     * Persist per-farm buying prices onto the producer pivot. Mirrors the
     * form's {@see ProductController::syncProducerBuyingPrices()}: an empty
     * value clears the override.
     *
     * @param  list<array{producer_id:int, buying_price:?float}>  $producers
     */
    private function syncProducerBuyingPrices(Product $product, array $producers): void
    {
        foreach ($producers as $row) {
            $id = (int) $row['producer_id'];
            $raw = $row['buying_price'] ?? null;
            $cents = ($raw === null || $raw === '') ? null : (int) round(((float) $raw) * 100);
            $product->producers()->updateExistingPivot($id, ['buying_price_cents' => $cents]);
        }
    }

    /**
     * Resolve the product's group from a German group name: an existing group
     * (matched case-insensitively on its `de` name) is reused, an unknown name
     * creates a new group, an empty name leaves the product ungrouped. Mirrors
     * the form's auto-create-and-cleanup behaviour.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncGroupByName(Product $product, array $data): void
    {
        if (! array_key_exists('group_name', $data)) {
            return;
        }

        $previousGroupId = $product->group_id;
        $name = array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            (array) ($data['group_name'] ?? []),
        ));

        $groupId = null;
        $deName = $name['de'] ?? '';
        if ($deName !== '') {
            // Match case-insensitively on the German name in PHP: json_extract
            // unquoting differs between MySQL and SQLite, and the group set is
            // small enough to scan.
            $target = mb_strtolower($deName);
            $existing = ProductGroup::query()->get()->first(
                static fn (ProductGroup $g): bool => mb_strtolower(trim((string) (((array) $g->name)['de'] ?? ''))) === $target,
            );
            $groupId = $existing !== null
                ? $existing->id
                : ProductGroup::create(['name' => $name, 'position' => 0])->id;
        }

        $label = array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            (array) ($data['group_label'] ?? []),
        ));

        $product->forceFill([
            'group_id' => $groupId,
            'group_label' => $groupId !== null && $label !== [] ? $label : null,
            'group_position' => (int) ($data['group_position'] ?? 0),
        ])->save();

        if ($previousGroupId !== null && $previousGroupId !== $groupId) {
            ProductGroup::query()
                ->whereKey($previousGroupId)
                ->whereDoesntHave('products')
                ->delete();
        }
    }

    /**
     * Set/clear the product-level (default grade) business price. $displayPrice
     * is a display-unit net price; null/empty clears the rule. Mirrors the
     * form's {@see ProductController::upsertBusinessPrice()} for the default
     * grade.
     */
    private function syncBusinessPrice(Product $product, mixed $displayPrice, ?int $userId): void
    {
        $existing = $product->priceRules()
            ->where('customer_type', CustomerType::Business->value)
            ->whereNull('starts_on')->whereNull('ends_on')->whereNull('min_quantity')
            ->whereNull('variant_id')
            ->first();

        if ($displayPrice === null || $displayPrice === '') {
            $existing?->delete();

            return;
        }

        $canonical = round((float) $displayPrice / $this->displayFactor($product), 4);

        if ($existing !== null) {
            $existing->update(['unit_price' => $canonical, 'is_active' => true]);

            return;
        }

        $product->priceRules()->create([
            'variant_id' => null,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => $canonical,
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Upsert the product's translations. Mirrors the form loop: German is
     * always written; EN/FR without a name are removed so the storefront
     * falls back to German.
     *
     * @param  array<string, mixed>  $translations
     */
    private function syncTranslations(Product $product, array $translations): void
    {
        foreach (['de', 'en', 'fr'] as $locale) {
            $row = (array) ($translations[$locale] ?? []);
            if ($locale !== 'de' && trim((string) ($row['name'] ?? '')) === '') {
                ProductTranslation::query()
                    ->where('product_id', $product->id)
                    ->where('locale', $locale)
                    ->delete();

                continue;
            }

            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->id, 'locale' => $locale],
                [
                    'name' => (string) ($row['name'] ?? ''),
                    'origin' => (string) ($row['origin'] ?? ''),
                    'variety' => (string) ($row['variety'] ?? ''),
                    'taste_profile' => (string) ($row['taste_profile'] ?? ''),
                    'short_description' => (string) ($row['short_description'] ?? ''),
                ]
            );
        }
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>|null
     */
    private function normalizeEnumArray(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $values = array_values(array_unique(array_filter(
            $values,
            static fn ($v) => is_string($v) && $v !== ''
        )));

        return $values === [] ? null : $values;
    }

    /**
     * @param  array<int, mixed>|null  $entries
     * @return list<array{de:string, en:string, fr:string}>|null
     */
    private function normalizeCustomTasteTags(?array $entries): ?array
    {
        if ($entries === null) {
            return null;
        }

        $clean = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $de = trim((string) ($entry['de'] ?? ''));
            $en = trim((string) ($entry['en'] ?? ''));
            $fr = trim((string) ($entry['fr'] ?? ''));

            if ($de === '' || $en === '' || $fr === '') {
                continue;
            }

            $clean[] = ['de' => $de, 'en' => $en, 'fr' => $fr];
        }

        return $clean === [] ? null : $clean;
    }
}
