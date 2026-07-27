<?php

namespace App\Exports;

use App\Models\Product;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Maatwebsite\Excel\Concerns\FromArray;
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
 * Shared behaviour for every sheet of the catalogue workbook: the
 * subcategory-section layout, the master-sheet styling (forest-green header,
 * frozen + auto-filtered header row, zebra rows, coloured section titles), and
 * the value formatters. Concrete sheets only declare their columns
 * ({@see headings()}), title, and how one product turns into rows
 * ({@see productRows()}).
 */
abstract class CatalogueSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    /** Forest green matching the admin's master sheet header. */
    protected const HEADER_FILL = '38553F';

    /** Very light green-grey for the zebra banding on every other row. */
    protected const BAND_FILL = 'F3F6F3';

    /** Mid green for the subcategory section-title rows. */
    protected const SECTION_FILL = 'CFE0CF';

    protected const BORDER_COLOR = 'E4EAE4';

    /** @var list<int> 1-based sheet rows that hold a subcategory section title */
    protected array $sectionRows = [];

    /** @var list<int> 1-based sheet rows that hold a footer guidance note */
    protected array $noteRows = [];

    public function __construct(
        protected readonly ProductWriter $writer,
        protected readonly CatalogueExportData $data,
    ) {}

    /**
     * All rows a single product contributes to this sheet (one or more).
     *
     * @return list<array<int, mixed>>
     */
    abstract protected function productRows(Product $product): array;

    /**
     * Widths per header label; anything unlisted gets a sensible default.
     *
     * @return array<string, int>
     */
    abstract protected function columnWidths(): array;

    /**
     * 1-based index of the first dated availability column, or null when the
     * sheet has no day columns. Day headers are rotated vertical from here.
     */
    protected function dayColumnStartIndex(): ?int
    {
        return null;
    }

    /**
     * Lay the sheet out in subcategory sections: each opens with a coloured
     * title row, then its products. The title rows carry only text in column A,
     * so the importer skips them.
     *
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $rows = [];
        $this->sectionRows = [];
        $this->noteRows = [];
        foreach ($this->data->sections as $section) {
            $rows[] = $this->dividerRow($section['title']);
            $this->sectionRows[] = count($rows) + 1; // header is row 1

            foreach ($section['products'] as $product) {
                foreach ($this->productRows($product) as $row) {
                    $rows[] = $row;
                }
            }
        }

        // Optional trailing guidance (e.g. an "add new products here" footer).
        foreach ($this->footerRows() as $footer) {
            $rows[] = $this->dividerRow($footer['text']);
            $rowNumber = count($rows) + 1; // header is row 1
            if ($footer['type'] === 'note') {
                $this->noteRows[] = $rowNumber;
            } else {
                $this->sectionRows[] = $rowNumber;
            }
        }

        return $rows;
    }

    /**
     * Extra rows appended after the last product section. Each is either a
     * `section` (styled like a subcategory band) or a `note` (a wrapped, muted
     * instruction line). The importer skips them: their column A text is not a
     * numeric Erdgut ID. Default: none.
     *
     * @return list<array{type: 'section'|'note', text: string}>
     */
    protected function footerRows(): array
    {
        return [];
    }

    /**
     * A section-title row: the subcategory name in column A, every other cell
     * blank. The blank (non-numeric) Erdgut ID makes the importer skip it.
     *
     * @return array<int, mixed>
     */
    protected function dividerRow(string $title): array
    {
        $row = array_fill(0, count($this->headings()), '');
        $row[0] = $title;

        return $row;
    }

    /**
     * Flatten a header => value map into a positional row, in the exact order
     * the given headers declare. Missing keys become blank cells, so a row only
     * names the columns it fills and stays correct if the column order changes.
     * Sheets with appended day columns pass only their fixed headers here and
     * merge the day cells after.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $headers
     * @return list<mixed>
     */
    protected function flatten(array $values, array $headers): array
    {
        return array_map(
            static fn (string $header): mixed => $values[$header] ?? '',
            $headers,
        );
    }

    /**
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

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

                // No wrapping + vertical centering keeps description rows single-line.
                $sheet->getStyle($fullRange)->getAlignment()
                    ->setWrapText(false)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->getStyle($fullRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->getColor()->setRGB(self::BORDER_COLOR);

                // Header row: green fill, white bold text.
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Rotate the dated availability headers vertical so the narrow
                // day columns fit an ISO date.
                $dayStart = $this->dayColumnStartIndex();
                if ($dayStart !== null && $dayStart <= Coordinate::columnIndexFromString($lastCol)) {
                    $firstDayCol = Coordinate::stringFromColumnIndex($dayStart);
                    $sheet->getStyle("{$firstDayCol}1:{$lastCol}1")->getAlignment()
                        ->setTextRotation(90)
                        ->setVertical(Alignment::VERTICAL_BOTTOM)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension(1)->setRowHeight(78);
                } else {
                    $sheet->getRowDimension(1)->setRowHeight(26);
                }

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");

                // Zebra striping + fixed data-row height, skipping section/note rows.
                $skip = array_flip([...$this->sectionRows, ...$this->noteRows]);
                for ($r = 2; $r <= $lastRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(17);
                    if (isset($skip[$r])) {
                        continue;
                    }
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB(self::BAND_FILL);
                    }
                }

                // Subcategory section titles: a solid mid-green band, bold dark text.
                foreach ($this->sectionRows as $r) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1F2D22']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SECTION_FILL]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                // Footer guidance notes: merge across the row, wrap, muted italic.
                foreach ($this->noteRows as $r) {
                    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                    $sheet->getRowDimension($r)->setRowHeight(30);
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '5A6B5E']],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $this->applyColumnWidths($sheet);
            },
        ];
    }

    private function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = $this->columnWidths();
        $dayStart = $this->dayColumnStartIndex();
        foreach ($this->headings() as $i => $header) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            // Day columns are narrow (room for a 4-digit entry); fixed columns
            // use their mapped width, defaulting to 11.
            $width = $dayStart !== null && $i + 1 >= $dayStart ? 5 : ($widths[$header] ?? 11);
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
    }

    // --------------------------------------------------------- value helpers

    /** Money as a plain dot-decimal number (Excel/Sheets friendly), blank if null. */
    protected function money(?float $value): string
    {
        return $value === null ? '' : number_format($value, 2, '.', '');
    }

    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /** Gross margin over the sell price, whole percent; blank if uncomputable. */
    protected function margin(?float $cost, ?float $sellNet): string
    {
        if ($cost === null || $sellNet === null || $sellNet <= 0) {
            return '';
        }

        return round(($sellNet - $cost) / $sellNet * 100).'%';
    }

    /**
     * Daily availability cells, canonical → display. Blank when nothing was
     * received that day, `Unlimited` at/above the sentinel.
     *
     * @return list<string>
     */
    protected function dailyCells(Product $product, int $factor): array
    {
        $dailyByDay = $this->data->dailyByProduct[$product->id] ?? [];
        $cells = [];
        foreach ($this->data->days as $date) {
            $canonical = $dailyByDay[$date->toDateString()] ?? null;
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

    protected function businessDisplayPrice(Product $product, int $factor): ?float
    {
        $rule = $product->priceRules()
            ->where('customer_type', 'business')
            ->whereNull('starts_on')->whereNull('ends_on')->whereNull('min_quantity')
            ->whereNull('variant_id')
            ->first();

        return $rule !== null ? round((float) $rule->unit_price * $factor, 2) : null;
    }

    protected function costFor(Product $product, mixed $producer, bool $isMain): ?float
    {
        $cents = $producer?->pivot?->buying_price_cents;
        if ($cents === null && $isMain) {
            $cents = $product->buying_price_cents;
        }

        return $cents === null ? null : round($cents / 100, 2);
    }
}
