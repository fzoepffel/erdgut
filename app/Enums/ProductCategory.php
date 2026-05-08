<?php

namespace App\Enums;

/**
 * Product category. Aligned with the marketing-home Kapitel taxonomy
 * (D6/D7/F8 in UX review notes): five chapters that map 1:1 to the
 * customer-facing chips and the admin filter dropdown.
 *
 *  - produce         → "Obst & Gemüse"      (chapter: produce)
 *  - fish            → "Fisch & Meeresfrüchte" (chapter: fish)
 *  - meat            → "Fleisch"             (chapter: meat)
 *  - eggs_and_dairy  → "Eier & Milchprodukte" (chapter: eggdairy)
 *  - bread           → "Brot"                (chapter: bread)
 *
 * The older `vegetables / fruit / dairy / bakery` values were merged /
 * renamed by the data migration shipped alongside this batch.
 */
enum ProductCategory: string
{
    case Produce = 'produce';
    case Fish = 'fish';
    case Meat = 'meat';
    case EggsAndDairy = 'eggs_and_dairy';
    case Bread = 'bread';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Cases ordered alphabetically by their localized admin label (de/en/fr).
     * Use this for any UI dropdown — enum declaration order is product-meaningful
     * but not find-able for an admin scanning a list.
     *
     * @return list<self>
     */
    public static function casesSortedByLabel(): array
    {
        $cases = self::cases();
        $collator = class_exists(\Collator::class) ? new \Collator(app()->getLocale()) : null;

        usort($cases, function (self $a, self $b) use ($collator): int {
            $la = (string) __('admin.category.'.$a->value);
            $lb = (string) __('admin.category.'.$b->value);

            return $collator
                ? ($collator->compare($la, $lb) ?: 0)
                : strcasecmp($la, $lb);
        });

        return $cases;
    }

    /**
     * @return list<string>
     */
    public static function valuesSortedByLabel(): array
    {
        return array_map(fn (self $c): string => $c->value, self::casesSortedByLabel());
    }

    /**
     * The visual "market stall" / chapter this category belongs to.
     * Drives the colour system and `<x-eyebrow color="...">` accents.
     */
    public function stall(): string
    {
        return match ($this) {
            self::Produce => 'produce',
            self::Fish => 'fish',
            self::Meat => 'meat',
            self::EggsAndDairy => 'eggdairy',
            self::Bread => 'bread',
        };
    }
}
