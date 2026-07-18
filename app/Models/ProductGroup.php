<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A display bundle of several full Products ("grades" from different farms,
 * possibly different units). The group carries its OWN localized name that
 * becomes the card title in the storefront and the B2B catalog; each member
 * product keeps its complete identity (unit, price, stock, producer, rules)
 * and contributes a short {@see Product::localizedGroupLabel()}. Groups are
 * created from the admin product form or the products-index bulk action and
 * are deleted automatically when their last member leaves.
 */
class ProductGroup extends Model
{
    protected $fillable = [
        'name',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * Members in display order; the first one is the LEAD (supplies the
     * card's image, link and category placement).
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'group_id')
            ->orderBy('group_position')
            ->orderBy('id');
    }

    /**
     * Group name resolved to the requested locale, falling back de → en → fr
     * → any non-empty value (mirrors {@see ProductVariant::localizedName}).
     */
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = (array) $this->name;

        foreach (array_unique([$locale, 'de', 'en', 'fr']) as $l) {
            if (isset($name[$l]) && is_string($name[$l]) && trim($name[$l]) !== '') {
                return trim($name[$l]);
            }
        }

        foreach ($name as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
