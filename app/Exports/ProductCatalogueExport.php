<?php

namespace App\Exports;

use App\Imports\ProductCsvParser;
use App\Services\Admin\ProductWriter;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Exports the whole product catalogue as a styled XLSX workbook with three
 * sheets, all showing the same products in the same subcategory-section order:
 *
 *  - "Katalog"  ({@see CatalogueMainSheet})      every field, one row per
 *               (product × producer); the full editing surface.
 *  - "Preise"   ({@see CataloguePricesSheet})    German name + all prices, for
 *               quick price adjustments.
 *  - "Bestand"  ({@see CatalogueInventorySheet}) German name + daily
 *               availability, for quick stock adjustments.
 *
 * The workbook round-trips through {@see ProductCsvParser}: the `Erdgut ID`
 * column keys each product on re-import. Prices and stock can be edited on
 * either the full sheet or the matching convenience sheet; the parser
 * reconciles the two so an edit on one is reflected on the other.
 */
class ProductCatalogueExport implements WithMultipleSheets
{
    public function __construct(private readonly ProductWriter $writer) {}

    /**
     * @return list<CatalogueSheet>
     */
    public function sheets(): array
    {
        $data = CatalogueExportData::build();

        return [
            new CatalogueMainSheet($this->writer, $data),
            new CataloguePricesSheet($this->writer, $data),
            new CatalogueInventorySheet($this->writer, $data),
        ];
    }
}
