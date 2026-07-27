<?php

namespace App\Exports;

use App\Models\Product;
use App\Support\ProductCatalogue\CatalogueSchema;

/**
 * The full catalogue sheet ("Katalog"): every field, one row per
 * (product × producer). The primary producer's row is marked `Main` and
 * carries every product-level field; secondary rows carry only that farm's
 * `Producer` + `Cost`.
 */
class CatalogueMainSheet extends CatalogueSheet
{
    public function headings(): array
    {
        return array_merge(CatalogueSchema::fixedHeaders(), array_keys($this->data->days));
    }

    public function title(): string
    {
        return CatalogueSchema::SHEET_MAIN;
    }

    protected function dayColumnStartIndex(): ?int
    {
        return count(CatalogueSchema::fixedHeaders()) + 1;
    }

    /**
     * A footer inviting admins to add new products at the bottom of the sheet.
     * New products can only be created from this full sheet, so the guidance
     * lives here and not on the prices/inventory sheets.
     *
     * @return list<array{type: 'section'|'note', text: string}>
     */
    protected function footerRows(): array
    {
        return [
            ['type' => 'section', 'text' => 'Neue Produkte unten hinzufügen'],
            ['type' => 'note', 'text' => 'Die Erdgut ID leer lassen, sie wird beim Import automatisch vergeben. Alle Eingaben werden nach dem Upload geprüft.'],
        ];
    }

    /**
     * @return list<array<int, mixed>>
     */
    protected function productRows(Product $product): array
    {
        $producers = $product->producers->sortBy('pivot.position')->values();

        $primary = $producers->firstWhere('id', $product->producer_id) ?? $producers->first();
        $ordered = $producers->reject(fn ($p): bool => $primary !== null && $p->id === $primary->id);
        if ($primary !== null) {
            $ordered = collect([$primary])->concat($ordered);
        }
        if ($ordered->isEmpty()) {
            $ordered = collect([null]); // product with no producer still exports one row
        }

        $rows = [];
        foreach ($ordered as $index => $producer) {
            $rows[] = $index === 0
                ? $this->mainRow($product, $producer)
                : $this->secondaryRow($product, $producer);
        }

        return $rows;
    }

    /**
     * @return array<int, mixed>
     */
    private function mainRow(Product $product, mixed $producer): array
    {
        $factor = $this->writer->displayFactor($product);
        $meta = is_array($product->metadata) ? $product->metadata : [];

        $vat = (int) ($product->vat_rate_percent ?? 7);
        $privateGross = $product->unit_price !== null ? round((float) $product->unit_price * $factor, 2) : null;
        $privateNet = $privateGross !== null ? round($privateGross / (1 + $vat / 100), 2) : null;
        $businessNet = $this->businessDisplayPrice($product, $factor);
        $cost = $this->costFor($product, $producer, isMain: true);

        $t = fn (string $locale): object => $product->translations->firstWhere('locale', $locale) ?? (object) [];

        $fixed = $this->flatten([
            'Erdgut ID' => $product->id,
            'Name (DE)' => $t('de')->name ?? '',
            'Name (EN)' => $t('en')->name ?? '',
            'Name (FR)' => $t('fr')->name ?? '',
            'Main' => 'Main',
            'Producer' => $this->producerName($producer),
            'Producer Name' => (string) ($meta['producer_product_name'] ?? ''),
            'Sourcing ID' => (string) ($meta['sourcing_id'] ?? ''),
            'Selling ID' => (string) ($meta['selling_id'] ?? ''),
            'Category' => CatalogueSchema::categoryLabel($product->category),
            'Sub-category' => $product->subcategory?->label('de') ?? '',
            'Product Group' => $product->group?->localizedName('de') ?? '',
            'Group Position' => (int) ($product->group_position ?? 0),
            'Origin (DE)' => $t('de')->origin ?? '',
            'Origin (EN)' => $t('en')->origin ?? '',
            'Origin (FR)' => $t('fr')->origin ?? '',
            'Variety (DE)' => $t('de')->variety ?? '',
            'Variety (EN)' => $t('en')->variety ?? '',
            'Variety (FR)' => $t('fr')->variety ?? '',
            'Short Description (DE)' => $t('de')->short_description ?? '',
            'Short Description (EN)' => $t('en')->short_description ?? '',
            'Short Description (FR)' => $t('fr')->short_description ?? '',
            'Long Description (DE)' => $t('de')->taste_profile ?? '',
            'Long Description (EN)' => $t('en')->taste_profile ?? '',
            'Long Description (FR)' => $t('fr')->taste_profile ?? '',
            'Unit' => CatalogueSchema::unitToken($product),
            'Quantity per Unit' => $this->quantityPerUnit($product),
            'Cost' => $this->money($cost),
            'Business Net' => $this->money($businessNet),
            'Business Margin %' => $this->margin($cost, $businessNet),
            'Private Price' => $this->money($privateGross),
            'Private Netto' => $this->money($privateNet),
            'Private Margin %' => $this->margin($cost, $privateNet),
            'VAT %' => $vat,
            'Season Start' => $this->seasonDate($product->season_start_month, $product->season_start_day),
            'Season End' => $this->seasonDate($product->season_end_month, $product->season_end_day),
            'Request Visibility' => $product->request_visibility ?? 'auto',
            'Request Until' => $product->request_visibility_until?->format('Y-m-d') ?? '',
            'Freshness (days)' => (int) ($product->default_freshness_window_days ?? 1),
            'Published' => CatalogueSchema::bool((bool) $product->is_published),
            'Available' => CatalogueSchema::bool((bool) $product->is_available),
            'Egg add-on' => CatalogueSchema::bool((bool) $product->is_egg_addon),
            'Allergens' => CatalogueSchema::joinTags($product->allergens),
            'Dietary Tags' => CatalogueSchema::joinTags($product->dietary_tags),
            'Taste Tags' => CatalogueSchema::joinTags($product->taste_profile_tags),
            'Image Path' => (string) ($product->image_path ?? ''),
            'Comment' => (string) ($meta['comment'] ?? ''),
        ], CatalogueSchema::fixedHeaders());

        return array_merge($fixed, $this->dailyCells($product, $factor));
    }

    /**
     * A secondary producer's lean row: product-level cells blank, only the
     * farm's own `Producer` + `Cost` carried.
     *
     * @return array<int, mixed>
     */
    private function secondaryRow(Product $product, mixed $producer): array
    {
        $fixed = $this->flatten([
            'Erdgut ID' => $product->id,
            'Producer' => $this->producerName($producer),
            'Cost' => $this->money($this->costFor($product, $producer, isMain: false)),
        ], CatalogueSchema::fixedHeaders());

        // Secondary rows carry no daily availability (that lives on Main).
        return array_merge($fixed, array_fill(0, count($this->data->days), ''));
    }

    /**
     * The producer's German display name. The catalogue is German-facing and
     * the importer matches producers by name across every locale, so emitting
     * the `de` name keeps the round-trip stable regardless of the app locale.
     */
    private function producerName(mixed $producer): string
    {
        if ($producer === null) {
            return '';
        }

        return $producer->translationFor('de')?->name ?? $producer->name ?? '';
    }

    private function quantityPerUnit(Product $product): string
    {
        $value = $product->unit_weight_grams ?? $product->unit_volume_ml;

        return $value === null ? '' : (string) (int) $value;
    }

    private function seasonDate(?int $month, ?int $day): string
    {
        return $month !== null && $day !== null ? sprintf('%02d-%02d', $month, $day) : '';
    }

    /**
     * @return array<string, int>
     */
    protected function columnWidths(): array
    {
        return [
            'Erdgut ID' => 9, 'Main' => 7, 'Producer' => 22, 'Producer Name' => 20,
            'Sourcing ID' => 12, 'Selling ID' => 12, 'Category' => 16, 'Sub-category' => 18,
            'Product Group' => 18, 'Group Position' => 8,
            'Name (DE)' => 24, 'Name (EN)' => 24, 'Name (FR)' => 24,
            'Origin (DE)' => 20, 'Origin (EN)' => 20, 'Origin (FR)' => 20,
            'Variety (DE)' => 16, 'Variety (EN)' => 16, 'Variety (FR)' => 16,
            'Short Description (DE)' => 40, 'Short Description (EN)' => 40, 'Short Description (FR)' => 40,
            'Long Description (DE)' => 60, 'Long Description (EN)' => 60, 'Long Description (FR)' => 60,
            'Unit' => 8, 'Quantity per Unit' => 12,
            'Cost' => 9, 'Business Net' => 12, 'Business Margin %' => 14,
            'Private Price' => 12, 'Private Netto' => 12, 'Private Margin %' => 14,
            'VAT %' => 7, 'Season Start' => 12, 'Season End' => 12,
            'Request Visibility' => 14, 'Request Until' => 13, 'Freshness (days)' => 12,
            'Published' => 10, 'Available' => 10, 'Egg add-on' => 10,
            'Allergens' => 18, 'Dietary Tags' => 18, 'Taste Tags' => 18,
            'Image Path' => 24, 'Comment' => 24,
        ];
    }
}
