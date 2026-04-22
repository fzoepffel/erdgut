<?php

namespace App\Support;

use App\Enums\ProductCategory;

/**
 * Single source of truth for category -> chapter mapping.
 *
 * Chapters come from the Erdgut brand catalogue (F&V green, Meat red, Fish blue,
 * Eggs & Dairy orange, Bread brown, Producers ochre, Almanach purple).  Only the
 * five chapters that correspond to product categories are returned here;
 * `producers` and `almanach` are page-level chapters invoked directly in blades.
 *
 * Keeps hex values and Tailwind token names in sync so blades can render
 * consistent badges, eyebrows, and section accents without inlining hex.
 */
class ProductCategoryStyle
{
    /**
     * @return array{
     *     chapter: string,
     *     dark: string,
     *     soft: string,
     *     variant: 'filled'|'outlined',
     *     badge_classes: string,
     *     text_classes: string,
     *     border_classes: string,
     *     mark: string,
     * }
     */
    public static function for(ProductCategory $category): array
    {
        return match ($category->stall()) {
            'fish' => [
                'chapter' => 'fish',
                'dark' => '#1d4485',
                'soft' => '#a7d4e3',
                'variant' => 'filled',
                'badge_classes' => 'bg-chapter-fish text-brand-cream',
                'text_classes' => 'text-chapter-fish',
                'border_classes' => 'border-chapter-fish',
                'mark' => 'scallop',
            ],
            'meat' => [
                'chapter' => 'meat',
                'dark' => '#ac273b',
                'soft' => '#e1b4b6',
                'variant' => 'filled',
                'badge_classes' => 'bg-chapter-meat text-brand-cream',
                'text_classes' => 'text-chapter-meat',
                'border_classes' => 'border-chapter-meat',
                'mark' => 'beef',
            ],
            'produce' => [
                'chapter' => 'produce',
                'dark' => '#21341d',
                'soft' => '#709452',
                'variant' => 'filled',
                'badge_classes' => 'bg-chapter-produce text-brand-cream',
                'text_classes' => 'text-chapter-produce',
                'border_classes' => 'border-chapter-produce',
                'mark' => 'onion',
            ],
            'dairy' => [
                'chapter' => 'eggdairy',
                'dark' => '#e46f25',
                'soft' => '#e6c0a8',
                'variant' => 'filled',
                'badge_classes' => 'bg-chapter-eggdairy text-brand-cream',
                'text_classes' => 'text-chapter-eggdairy',
                'border_classes' => 'border-chapter-eggdairy',
                'mark' => 'cheese',
            ],
            'bakery' => [
                'chapter' => 'bread',
                'dark' => '#944e22',
                'soft' => '#dec1ae',
                'variant' => 'filled',
                'badge_classes' => 'bg-chapter-bread text-brand-cream',
                'text_classes' => 'text-chapter-bread',
                'border_classes' => 'border-chapter-bread',
                'mark' => 'wheat',
            ],
        };
    }

    /**
     * Tailwind text color class for a given chapter, used by `<x-eyebrow color="...">`
     * and section accents.  Accepts legacy "stall" names for back-compat callers.
     */
    public static function textClassForChapter(string $chapter): string
    {
        return match ($chapter) {
            'fish' => 'text-chapter-fish',
            'meat' => 'text-chapter-meat',
            'produce' => 'text-chapter-produce',
            'eggdairy', 'dairy' => 'text-chapter-eggdairy',
            'bread', 'bakery' => 'text-chapter-bread',
            'producers' => 'text-chapter-producers',
            'almanach' => 'text-chapter-almanach',
            default => 'text-brand-ink',
        };
    }

    /**
     * @deprecated use textClassForChapter() — kept for a single migration cycle.
     */
    public static function textClassForStall(string $stall): string
    {
        return self::textClassForChapter($stall);
    }
}
