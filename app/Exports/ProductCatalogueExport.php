<?php

namespace App\Exports;

use App\Imports\ProductCsvParser;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exports the whole product catalogue as a CSV shaped like the admin's master
 * Google Sheet. One row per (product × producer): a product sourced from
 * several farms spans several rows sharing the same `Erdgut ID`, with the
 * primary producer's row marked `Main` and carrying every product-level field;
 * secondary rows carry only that farm's `Producer` + `Cost`.
 *
 * The file round-trips through {@see ProductCsvParser}: the
 * `Erdgut ID` column keys each product on re-import. Derived columns
 * (`Private Netto`, the two margin columns, `Image Path`) are recomputed here
 * and ignored on the way back in.
 */
class ProductCatalogueExport implements FromArray, WithCustomCsvSettings, WithEvents, WithHeadings, WithTitle
{
    /** Forest green matching the admin's master sheet header. */
    private const HEADER_FILL = '38553F';

    /** Very light green-grey for the zebra banding on every other row. */
    private const BAND_FILL = 'F3F6F3';

    private const BORDER_COLOR = 'E4EAE4';

    /** @var array<string, Carbon> header => that week's Monday */
    private array $weeks;

    public function __construct(private readonly ProductWriter $writer)
    {
        $this->weeks = CatalogueSchema::weekHeadersFrom(Carbon::now(), CatalogueSchema::EXPORT_WEEKS_AHEAD);
    }

    /**
     * Semicolon delimiter + UTF-8 BOM so the file opens as proper columns in
     * German-locale Excel/Numbers (which treat `;` as the list separator) and
     * renders umlauts correctly. {@see ProductCsvParser} sniffs the delimiter
     * and strips the BOM, so this round-trips unchanged.
     *
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';',
            'use_bom' => true,
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_merge(CatalogueSchema::fixedHeaders(), array_keys($this->weeks));
    }

    public function title(): string
    {
        return 'Katalog';
    }

    /**
     * Style the sheet to mirror the master Google Sheet: a forest-green bold
     * header, a frozen + auto-filtered header row, zebra-striped rows, sensible
     * column widths, and — crucially — no text wrapping with a fixed row height
     * so the multi-line Long Description cells stay a single line instead of
     * blowing the row height up. The line breaks stay in the data (lossless
     * round-trip); they are just not shown expanded.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $fullRange = "A1:{$lastCol}{$lastRow}";

                // Base font for the whole sheet.
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

                // No wrapping anywhere + vertical centering; this is what keeps
                // the description rows a single line high.
                $sheet->getStyle($fullRange)->getAlignment()
                    ->setWrapText(false)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Thin light borders across the grid.
                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB(self::BORDER_COLOR);

                // Header row: green fill, white bold text, taller row.
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                // Freeze the header row and auto-filter the whole table so the
                // header dropdowns actually filter the data rows.
                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");

                // Zebra striping + fixed data-row height.
                for ($r = 2; $r <= $lastRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(17);
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB(self::BAND_FILL);
                    }
                }

                $this->applyColumnWidths($sheet);
            },
        ];
    }

    /**
     * Widths per header label; anything unlisted gets a sensible default. With
     * wrapping off the wide description columns simply overflow into blank
     * neighbours (as in the master sheet) rather than growing the row.
     */
    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
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

        foreach ($this->headings() as $i => $header) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($letter)->setWidth($widths[$header] ?? 11);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $products = Product::query()
            ->with(['translations', 'producers.translations', 'group', 'subcategory'])
            ->orderBy('id')
            ->get();

        // Weekly batch quantities for every exported product in one query:
        // product_id => [Y-m-d => canonical quantity received that day].
        $mondays = array_map(static fn (Carbon $m): string => $m->toDateString(), $this->weeks);
        $weeklyByProduct = ProductStockInflow::query()
            ->whereIn('product_id', $products->modelKeys())
            ->whereIn('received_on', $mondays)
            ->get(['product_id', 'received_on', 'quantity'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->groupBy(fn (ProductStockInflow $i): string => $i->received_on->toDateString())
                ->map(fn ($g) => (float) $g->sum('quantity'))->all())
            ->all();

        $rows = [];
        foreach ($products as $product) {
            foreach ($this->productRows($product, $weeklyByProduct[$product->id] ?? []) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * All CSV rows for one product: the Main producer row (full field set)
     * followed by one lean row per secondary producer.
     *
     * @param  array<string, float>  $weeklyByDay  Y-m-d => canonical quantity
     * @return list<array<int, mixed>>
     */
    private function productRows(Product $product, array $weeklyByDay): array
    {
        $producers = $product->producers->sortBy('pivot.position')->values();

        // Primary producer first, so it becomes the Main row.
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
                ? $this->mainRow($product, $producer, $weeklyByDay)
                : $this->secondaryRow($product, $producer);
        }

        return $rows;
    }

    /**
     * @param  array<string, float>  $weeklyByDay
     * @return array<int, mixed>
     */
    private function mainRow(Product $product, mixed $producer, array $weeklyByDay): array
    {
        $factor = $this->writer->displayFactor($product);
        $meta = is_array($product->metadata) ? $product->metadata : [];

        $vat = (int) ($product->vat_rate_percent ?? 7);
        $privateGross = $product->unit_price !== null ? round((float) $product->unit_price * $factor, 2) : null;
        $privateNet = $privateGross !== null ? round($privateGross / (1 + $vat / 100), 2) : null;
        $businessNet = $this->businessDisplayPrice($product, $factor);
        $cost = $this->costFor($product, $producer, isMain: true);

        $t = fn (string $locale): object => $product->translations->firstWhere('locale', $locale) ?? (object) [];

        $fixed = [
            $product->id,
            'Main',
            $this->producerName($producer),
            (string) ($meta['producer_product_name'] ?? ''),
            (string) ($meta['sourcing_id'] ?? ''),
            (string) ($meta['selling_id'] ?? ''),
            CatalogueSchema::categoryLabel($product->category),
            $product->subcategory?->label('de') ?? '',
            $product->group?->localizedName('de') ?? '',
            (int) ($product->group_position ?? 0),
            $t('de')->name ?? '',
            $t('en')->name ?? '',
            $t('fr')->name ?? '',
            $t('de')->origin ?? '',
            $t('en')->origin ?? '',
            $t('fr')->origin ?? '',
            $t('de')->variety ?? '',
            $t('en')->variety ?? '',
            $t('fr')->variety ?? '',
            $t('de')->short_description ?? '',
            $t('en')->short_description ?? '',
            $t('fr')->short_description ?? '',
            $t('de')->taste_profile ?? '',
            $t('en')->taste_profile ?? '',
            $t('fr')->taste_profile ?? '',
            CatalogueSchema::unitToken($product),
            $this->quantityPerUnit($product),
            $this->money($cost),
            $this->money($businessNet),
            $this->margin($cost, $businessNet),
            $this->money($privateGross),
            $this->money($privateNet),
            $this->margin($cost, $privateNet),
            $vat,
            $this->seasonDate($product->season_start_month, $product->season_start_day),
            $this->seasonDate($product->season_end_month, $product->season_end_day),
            $product->request_visibility ?? 'auto',
            $product->request_visibility_until?->format('Y-m-d') ?? '',
            (int) ($product->default_freshness_window_days ?? 1),
            CatalogueSchema::bool((bool) $product->is_published),
            CatalogueSchema::bool((bool) $product->is_available),
            CatalogueSchema::bool((bool) $product->is_egg_addon),
            CatalogueSchema::joinTags($product->allergens),
            CatalogueSchema::joinTags($product->dietary_tags),
            CatalogueSchema::joinTags($product->taste_profile_tags),
            (string) ($product->image_path ?? ''),
            (string) ($meta['comment'] ?? ''),
        ];

        return array_merge($fixed, $this->weeklyCells($factor, $weeklyByDay));
    }

    /**
     * A secondary producer's lean row: product-level cells blank, only the
     * farm's own `Producer` + `Cost` carried.
     *
     * @return array<int, mixed>
     */
    private function secondaryRow(Product $product, mixed $producer): array
    {
        $blanks = array_fill(0, count(CatalogueSchema::fixedHeaders()), '');
        $blanks[0] = $product->id;                     // Erdgut ID
        $blanks[1] = '';                               // Main (blank)
        $blanks[2] = $this->producerName($producer);   // Producer
        $blanks[27] = $this->money($this->costFor($product, $producer, isMain: false)); // Cost

        // Secondary rows carry no weekly availability (that lives on Main).
        return array_merge($blanks, array_fill(0, count($this->weeks), ''));
    }

    /**
     * Weekly availability cells, canonical → display. Blank when nothing was
     * received that week, `Unlimited` at/above the sentinel.
     *
     * @param  array<string, float>  $weeklyByDay
     * @return list<string>
     */
    private function weeklyCells(int $factor, array $weeklyByDay): array
    {
        $cells = [];
        foreach ($this->weeks as $monday) {
            $canonical = $weeklyByDay[$monday->toDateString()] ?? null;
            if ($canonical === null) {
                $cells[] = '';
            } elseif ($canonical >= CatalogueSchema::UNLIMITED_SENTINEL) {
                $cells[] = 'Unlimited';
            } else {
                $cells[] = $this->number(round($canonical / $factor, 4));
            }
        }

        return $cells;
    }

    private function businessDisplayPrice(Product $product, int $factor): ?float
    {
        $rule = $product->priceRules()
            ->where('customer_type', 'business')
            ->whereNull('starts_on')->whereNull('ends_on')->whereNull('min_quantity')
            ->whereNull('variant_id')
            ->first();

        return $rule !== null ? round((float) $rule->unit_price * $factor, 2) : null;
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

    private function costFor(Product $product, mixed $producer, bool $isMain): ?float
    {
        $cents = $producer?->pivot?->buying_price_cents;
        if ($cents === null && $isMain) {
            $cents = $product->buying_price_cents;
        }

        return $cents === null ? null : round($cents / 100, 2);
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

    /** Money as a plain dot-decimal number (Excel/Sheets friendly), blank if null. */
    private function money(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, '.', '');
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /** Gross margin over the sell price, whole percent; blank if uncomputable. */
    private function margin(?float $cost, ?float $sellNet): string
    {
        if ($cost === null || $sellNet === null || $sellNet <= 0) {
            return '';
        }

        return round(($sellNet - $cost) / $sellNet * 100).'%';
    }
}
