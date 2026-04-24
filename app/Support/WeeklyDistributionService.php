<?php

namespace App\Support;

use App\Enums\DietaryTag;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Product selection engine used by box composition flows.
 *
 * Single entry point for:
 *  - Filtering the product pool by a subscriber's allergies_snapshot.
 *  - Filtering by dietary preferences (veg_only, eggs_dairy add-on, etc).
 *  - Random-ordered selection for composition.
 *
 * ERD-313 scope this pass covers the allergen + dietary filter + selection primitives.
 * The admin "force include anyway" override + WeeklyBoxSetupService delegation land
 * in a follow-up.
 */
class WeeklyDistributionService
{
    /**
     * @param  array<int, string>  $allergies  Allergen values to exclude (matches Product.allergens[]).
     * @param  array<string, mixed>  $preferences  Subscription preferences block.
     *                                             Respected keys: `veg_only` (bool), `eggs_dairy_add_on` (bool).
     */
    public function pickProducts(string $category, int $limit, array $allergies = [], array $preferences = []): Collection
    {
        if ($limit <= 0) {
            return new Collection;
        }

        return $this->filteredQuery($category, $allergies, $preferences)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Exposed for callers that need the filtered builder (e.g. admin stock planners).
     */
    public function filteredQuery(string $category, array $allergies = [], array $preferences = [])
    {
        $query = Product::query()
            ->where('category', $category)
            ->visibleInCatalog();

        $excludedDietaryTags = $this->excludedDietaryTags($preferences);
        $allergenValues = array_values(array_filter(array_map('strval', $allergies)));

        // JSON-column filtering in SQL would need dialect-specific JSON_CONTAINS. Do it
        // in PHP: cardinality is low (hundreds of products, single-digit filters).
        if ($allergenValues !== [] || $excludedDietaryTags !== []) {
            $survivingIds = $query->get()
                ->reject(fn (Product $p) => $this->conflicts($p, $allergenValues, $excludedDietaryTags))
                ->pluck('id')
                ->all();

            return Product::query()->whereIn('id', $survivingIds);
        }

        return $query;
    }

    /**
     * @param  list<string>  $allergens
     * @param  list<string>  $excludedDietaryTags
     */
    public function conflicts(Product $product, array $allergens, array $excludedDietaryTags): bool
    {
        $productAllergens = array_map('strval', (array) $product->allergens);
        if (array_intersect($allergens, $productAllergens) !== []) {
            return true;
        }

        $productDietary = array_map('strval', (array) $product->dietary_tags);
        if (array_intersect($excludedDietaryTags, $productDietary) !== []) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function excludedDietaryTags(array $preferences): array
    {
        $excluded = [];

        if ((bool) ($preferences['veg_only'] ?? false)) {
            $excluded[] = DietaryTag::ContainsMeat->value;
            $excluded[] = DietaryTag::ContainsFish->value;
        }

        if (! (bool) ($preferences['eggs_dairy_add_on'] ?? false)) {
            $excluded[] = DietaryTag::ContainsEggs->value;
            $excluded[] = DietaryTag::ContainsDairy->value;
        }

        return $excluded;
    }
}
