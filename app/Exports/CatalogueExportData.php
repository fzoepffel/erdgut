<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Support\ProductCatalogue\CatalogueSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The product catalogue loaded once and shared by every sheet of the export
 * workbook ({@see ProductCatalogueExport}). Building it here means the main,
 * prices and inventory sheets all iterate the exact same products in the exact
 * same subcategory-section order, so a row on one sheet lines up with the same
 * product on the others.
 */
final class CatalogueExportData
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  array<int, array<string, float>>  $dailyByProduct  product id => (Y-m-d => canonical quantity)
     * @param  array<string, Carbon>  $days  header (`YYYY-MM-DD`) => that day
     * @param  list<array{title:string, products:list<Product>}>  $sections
     */
    public function __construct(
        public readonly Collection $products,
        public readonly array $dailyByProduct,
        public readonly array $days,
        public readonly array $sections,
    ) {}

    /** Section title used for products that have no subcategory. */
    public const NO_SUBCATEGORY_TITLE = 'Ohne Unterkategorie';

    public static function build(): self
    {
        $products = Product::query()
            ->with(['translations', 'producers.translations', 'group', 'subcategory'])
            ->orderBy('id')
            ->get();

        $days = CatalogueSchema::dayHeadersFrom(Carbon::now(), CatalogueSchema::EXPORT_DAYS_AHEAD);

        // Daily one-off inflow quantities for every product in one query:
        // product_id => [Y-m-d => canonical quantity]. Schedule-materialised
        // recurring batches are owned by the schedule, so they are excluded.
        $dates = array_map(static fn (Carbon $d): string => $d->toDateString(), $days);
        $dailyByProduct = ProductStockInflow::query()
            ->whereIn('product_id', $products->modelKeys())
            ->whereNull('source_schedule_id')
            ->whereIn('received_on', $dates)
            ->get(['product_id', 'received_on', 'quantity'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->groupBy(fn (ProductStockInflow $i): string => $i->received_on->toDateString())
                ->map(fn ($g) => (float) $g->sum('quantity'))->all())
            ->all();

        return new self($products, $dailyByProduct, $days, self::groupIntoSections($products));
    }

    /**
     * Group products into subcategory sections. Named subcategories come first
     * ordered alphabetically by their German label; products with no
     * subcategory make a final "Ohne Unterkategorie" section. Within every
     * section products are sorted alphabetically by German name.
     *
     * @param  Collection<int, Product>  $products
     * @return list<array{title:string, products:list<Product>}>
     */
    private static function groupIntoSections(Collection $products): array
    {
        $grouped = [];
        foreach ($products as $product) {
            $sub = $product->subcategory;
            $key = $sub?->id ?? 0;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'title' => $sub?->label('de') ?: self::NO_SUBCATEGORY_TITLE,
                    'sort' => $sub !== null ? mb_strtolower((string) $sub->label('de')) : "\xff",
                    'products' => [],
                ];
            }
            $grouped[$key]['products'][] = $product;
        }

        foreach ($grouped as &$section) {
            usort(
                $section['products'],
                static fn (Product $a, Product $b): int => strcasecmp(self::germanName($a), self::germanName($b)),
            );
        }
        unset($section);

        usort($grouped, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return array_map(
            static fn (array $s): array => ['title' => $s['title'], 'products' => $s['products']],
            $grouped,
        );
    }

    /** A product's German name, for alphabetical sorting within a section. */
    public static function germanName(Product $product): string
    {
        return (string) ($product->translations->firstWhere('locale', 'de')?->name ?? '');
    }
}
