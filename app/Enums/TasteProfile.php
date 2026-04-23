<?php

namespace App\Enums;

use App\Models\ProductTranslation;

/**
 * Structured taste-profile tags for products.
 *
 * Distinct from the free-text {@see ProductTranslation::$taste_profile},
 * which is a prose description per locale. These tags drive structured UX
 * (taste chips, pairing suggestions).
 */
enum TasteProfile: string
{
    case Sweet = 'sweet';
    case Earthy = 'earthy';
    case Fresh = 'fresh';
    case Peppery = 'peppery';
    case Bitter = 'bitter';
    case Umami = 'umami';
    case Crisp = 'crisp';
    case Herbal = 'herbal';
    case Nutty = 'nutty';
    case Spicy = 'spicy';
    case Sour = 'sour';
    case Rich = 'rich';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
