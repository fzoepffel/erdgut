<?php

namespace App\Exports;

use App\Imports\ProductCsvParser;
use App\Models\Product;
use App\Support\ProductCatalogue\CatalogueSchema;

/**
 * The "Preise" convenience sheet: the German name plus every price field, so an
 * admin can adjust prices without wading through the full catalogue. One row
 * per product (the primary producer's cost). Edits made here are reconciled
 * back onto the same fields on re-import, and edits made on the full sheet flow
 * the other way, so the two stay in step. See {@see ProductCsvParser}.
 */
class CataloguePricesSheet extends CatalogueSheet
{
    public function headings(): array
    {
        return CatalogueSchema::pricesHeaders();
    }

    public function title(): string
    {
        return CatalogueSchema::SHEET_PRICES;
    }

    /**
     * @return list<array<int, mixed>>
     */
    protected function productRows(Product $product): array
    {
        $factor = $this->writer->displayFactor($product);
        $vat = (int) ($product->vat_rate_percent ?? 7);
        $privateGross = $product->unit_price !== null ? round((float) $product->unit_price * $factor, 2) : null;
        $privateNet = $privateGross !== null ? round($privateGross / (1 + $vat / 100), 2) : null;
        $businessNet = $this->businessDisplayPrice($product, $factor);
        // Same cost the full sheet shows on the Main row: the primary
        // producer's pivot price, falling back to the product's own.
        $primary = $product->producers->firstWhere('id', $product->producer_id) ?? $product->producers->first();
        $cost = $this->costFor($product, $primary, isMain: true);

        return [$this->flatten([
            'Erdgut ID' => $product->id,
            'Name (DE)' => CatalogueExportData::germanName($product),
            'Unit' => CatalogueSchema::unitToken($product),
            'Cost' => $this->money($cost),
            'Business Net' => $this->money($businessNet),
            'Business Margin %' => $this->margin($cost, $businessNet),
            'Private Price' => $this->money($privateGross),
            'Private Netto' => $this->money($privateNet),
            'Private Margin %' => $this->margin($cost, $privateNet),
            'VAT %' => $vat,
        ], CatalogueSchema::pricesHeaders())];
    }

    /**
     * @return array<string, int>
     */
    protected function columnWidths(): array
    {
        return [
            'Erdgut ID' => 9, 'Name (DE)' => 30, 'Unit' => 8,
            'Cost' => 10, 'Business Net' => 12, 'Business Margin %' => 16,
            'Private Price' => 12, 'Private Netto' => 12, 'Private Margin %' => 16,
            'VAT %' => 7,
        ];
    }
}
