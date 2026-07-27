<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Groups inventar matrix rows so similarly named products stay together,
 * then paginates without splitting a name-family across page boundaries.
 */
final class InventoryProductClusters
{
    public const PAGE_SIZE = 30;

    /**
     * Colour / size adjectives stripped before picking the family stem so
     * "Gelbe Zucchini" and "Grüne Zucchini" share a key.
     *
     * @var list<string>
     */
    private const STOP_WORDS = [
        'gelbe', 'gelber', 'gelbes', 'gelb',
        'gruene', 'gruener', 'gruenes', 'gruen', 'grüne', 'grüner', 'grünes', 'grün',
        'rote', 'roter', 'rotes', 'rot',
        'weisse', 'weisser', 'weisses', 'weiss', 'weiße', 'weißer', 'weißes', 'weiß',
        'schwarze', 'schwarzer', 'schwarzes', 'schwarz',
        'yellow', 'green', 'red', 'white', 'black',
        'jaune', 'verte', 'vert', 'rouge', 'blanc', 'blanche', 'noir', 'noire',
        'mini', 'maxi', 'klein', 'kleine', 'gross', 'große', 'grosse',
        'bio', 'frisch', 'frische',
        'der', 'die', 'das', 'the', 'le', 'la', 'les', 'un', 'une',
    ];

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public static function sorted(Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return $products->values();
        }

        $stems = $products->mapWithKeys(
            fn (Product $p): array => [$p->id => self::familyStem((string) $p->name)]
        );
        $canonical = self::mergeStems($stems->unique()->values()->all());

        return $products
            ->map(fn (Product $p): array => [
                'product' => $p,
                'name' => mb_strtolower((string) $p->name),
                'family' => $canonical[$stems[$p->id]] ?? $stems[$p->id],
            ])
            ->sort(function (array $a, array $b): int {
                $byFamily = strnatcasecmp($a['family'], $b['family']);
                if ($byFamily !== 0) {
                    return $byFamily;
                }

                return strnatcasecmp($a['name'], $b['name']);
            })
            ->values()
            ->map(fn (array $row): Product => $row['product']);
    }

    /**
     * Pack sorted products into pages without splitting a name-family.
     * A single oversized family may exceed {@see PAGE_SIZE} on its own page.
     *
     * @param  Collection<int, Product>  $sorted  Must already be {@see sorted()}.
     * @return LengthAwarePaginator<int, Product>
     */
    public static function paginate(
        Collection $sorted,
        int $page,
        int $perPage = self::PAGE_SIZE,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $totalProducts = $sorted->count();

        $pages = self::packPages($sorted, $perPage);
        $lastPage = max(1, count($pages));
        $page = min($page, $lastPage);
        $items = $pages[$page - 1] ?? collect();

        return new InventoryClusterPaginator(
            items: $items,
            totalProducts: $totalProducts,
            lastPage: $lastPage,
            currentPage: $page,
            options: [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        );
    }

    /**
     * @param  Collection<int, Product>  $sorted
     * @return list<Collection<int, Product>>
     */
    public static function packPages(Collection $sorted, int $perPage = self::PAGE_SIZE): array
    {
        if ($sorted->isEmpty()) {
            return [];
        }

        $stems = $sorted->mapWithKeys(
            fn (Product $p): array => [$p->id => self::familyStem((string) $p->name)]
        );
        $canonical = self::mergeStems($stems->unique()->values()->all());

        $clusters = [];
        $bucket = collect();
        $currentFamily = null;
        foreach ($sorted as $product) {
            $family = $canonical[$stems[$product->id]] ?? $stems[$product->id];
            if ($currentFamily !== null && $family !== $currentFamily && $bucket->isNotEmpty()) {
                $clusters[] = $bucket;
                $bucket = collect();
            }
            $currentFamily = $family;
            $bucket->push($product);
        }
        if ($bucket->isNotEmpty()) {
            $clusters[] = $bucket;
        }

        $pages = [];
        $current = collect();
        foreach ($clusters as $cluster) {
            if ($current->isNotEmpty() && ($current->count() + $cluster->count()) > $perPage) {
                $pages[] = $current;
                $current = collect();
            }
            $current = $current->concat($cluster)->values();
        }
        if ($current->isNotEmpty()) {
            $pages[] = $current;
        }

        return $pages;
    }

    public static function familyStem(string $name): string
    {
        $normalized = self::normalize($name);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, self::STOP_WORDS, true)
        ));

        if ($tokens === []) {
            return $normalized !== '' ? $normalized : 'zzz';
        }

        usort($tokens, function (string $a, string $b): int {
            $byLen = mb_strlen($b) <=> mb_strlen($a);
            if ($byLen !== 0) {
                return $byLen;
            }

            return strcmp($a, $b);
        });

        return $tokens[0];
    }

    /**
     * Merge stems that share a prefix (Zucchini / Zucchiniblüte).
     *
     * @param  list<string>  $stems
     * @return array<string, string> stem => canonical stem
     */
    public static function mergeStems(array $stems): array
    {
        $unique = array_values(array_unique(array_filter($stems, fn ($s) => is_string($s) && $s !== '')));
        usort($unique, function (string $a, string $b): int {
            $byLen = mb_strlen($a) <=> mb_strlen($b);
            if ($byLen !== 0) {
                return $byLen;
            }

            return strcmp($a, $b);
        });

        $canonical = [];
        foreach ($unique as $stem) {
            $parent = $stem;
            foreach ($canonical as $existingStem => $existingCanonical) {
                if (self::stemsRelated($stem, $existingStem)) {
                    $parent = $existingCanonical;
                    break;
                }
            }
            $canonical[$stem] = $parent;
        }

        foreach ($canonical as $stem => $parent) {
            $root = $parent;
            $guard = 0;
            while (isset($canonical[$root]) && $canonical[$root] !== $root && $guard < 10) {
                $root = $canonical[$root];
                $guard++;
            }
            $canonical[$stem] = $root;
        }

        return $canonical;
    }

    private static function stemsRelated(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $min = 5;
        if (mb_strlen($a) < $min || mb_strlen($b) < $min) {
            return false;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    private static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $name,
        );
    }
}
