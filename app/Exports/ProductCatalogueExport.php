<?php

namespace App\Exports;

use App\Imports\ProductCsvParser;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\ProductWriter;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
class ProductCatalogueExport implements FromArray, WithHeadings
{
    /** @var array<string, Carbon> header => that week's Monday */
    private array $weeks;

    public function __construct(private readonly ProductWriter $writer)
    {
        $this->weeks = CatalogueSchema::weekHeadersFrom(Carbon::now(), CatalogueSchema::EXPORT_WEEKS_AHEAD);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_merge(CatalogueSchema::fixedHeaders(), array_keys($this->weeks));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $products = Product::query()
            ->with(['translations', 'producers', 'group', 'subcategory'])
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
            $producer?->name ?? '',
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
        $blanks[2] = $producer?->name ?? '';           // Producer
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
