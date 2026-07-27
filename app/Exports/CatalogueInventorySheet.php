<?php

namespace App\Exports;

use App\Imports\ProductCsvParser;
use App\Models\Product;
use App\Support\ProductCatalogue\CatalogueSchema;

/**
 * The "Bestand" convenience sheet: the German name plus the daily-availability
 * columns, so an admin can adjust stock quickly. One row per product. Edits
 * made here are reconciled back onto the same days on re-import, and edits made
 * on the full sheet flow the other way, so the two stay in step. See
 * {@see ProductCsvParser}.
 */
class CatalogueInventorySheet extends CatalogueSheet
{
    public function headings(): array
    {
        return array_merge(CatalogueSchema::inventoryFixedHeaders(), array_keys($this->data->days));
    }

    public function title(): string
    {
        return CatalogueSchema::SHEET_INVENTORY;
    }

    protected function dayColumnStartIndex(): ?int
    {
        return count(CatalogueSchema::inventoryFixedHeaders()) + 1;
    }

    /**
     * @return list<array<int, mixed>>
     */
    protected function productRows(Product $product): array
    {
        $factor = $this->writer->displayFactor($product);

        $fixed = $this->flatten([
            'Erdgut ID' => $product->id,
            'Name (DE)' => CatalogueExportData::germanName($product),
            'Unit' => CatalogueSchema::unitToken($product),
        ], CatalogueSchema::inventoryFixedHeaders());

        return [array_merge($fixed, $this->dailyCells($product, $factor))];
    }

    /**
     * @return array<string, int>
     */
    protected function columnWidths(): array
    {
        return ['Erdgut ID' => 9, 'Name (DE)' => 30, 'Unit' => 8];
    }
}
