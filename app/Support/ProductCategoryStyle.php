<?php

namespace App\Support;

use App\Enums\ProductCategory;

/**
 * Single source of truth for category -> visual stall mapping.
 *
 * Keeps the 5-stall color system (fishmonger / butcher / greengrocer / dairy / bakery)
 * aligned with the Tailwind token names so blades can render consistent badges,
 * eyebrows, and section accents without inlining hex values.
 */
class ProductCategoryStyle
{
    /**
     * @return array{
     *     stall: string,
     *     dark: string,
     *     soft: string,
     *     variant: 'filled'|'outlined',
     *     badge_classes: string,
     *     text_classes: string,
     *     border_classes: string,
     * }
     */
    public static function for(ProductCategory $category): array
    {
        return match ($category->stall()) {
            'fish' => [
                'stall' => 'fish',
                'dark' => '#1c397d',
                'soft' => '#b5daea',
                'variant' => 'filled',
                'badge_classes' => 'bg-category-fish text-white',
                'text_classes' => 'text-category-fish',
                'border_classes' => 'border-category-fish',
            ],
            'meat' => [
                'stall' => 'meat',
                'dark' => '#ae2033',
                'soft' => '#e5bcc0',
                'variant' => 'filled',
                'badge_classes' => 'bg-category-meat text-white',
                'text_classes' => 'text-category-meat',
                'border_classes' => 'border-category-meat',
            ],
            'produce' => [
                'stall' => 'produce',
                'dark' => '#21341d',
                'soft' => '#79954d',
                'variant' => 'filled',
                'badge_classes' => 'bg-category-produce text-white',
                'text_classes' => 'text-category-produce',
                'border_classes' => 'border-category-produce',
            ],
            'dairy' => [
                'stall' => 'dairy',
                'dark' => '#3c3025',
                'soft' => '#ffffff',
                'variant' => 'outlined',
                'badge_classes' => 'border border-brand-ink bg-transparent text-brand-ink',
                'text_classes' => 'text-brand-ink',
                'border_classes' => 'border-brand-ink',
            ],
            'bakery' => [
                'stall' => 'bakery',
                'dark' => '#3c3025',
                'soft' => '#ffffff',
                'variant' => 'outlined',
                'badge_classes' => 'border border-brand-ink bg-transparent text-brand-ink',
                'text_classes' => 'text-brand-ink',
                'border_classes' => 'border-brand-ink',
            ],
        };
    }

    /**
     * Tailwind text color class for a given stall, used by `<x-eyebrow color="...">`
     * and section accents.
     */
    public static function textClassForStall(string $stall): string
    {
        return match ($stall) {
            'fish' => 'text-category-fish',
            'meat' => 'text-category-meat',
            'produce' => 'text-category-produce',
            default => 'text-brand-ink',
        };
    }
}
