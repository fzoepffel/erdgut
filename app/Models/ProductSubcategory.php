<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSubcategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'category',
        'label_de',
        'label_en',
        'label_fr',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'subcategory_id');
    }

    /**
     * Localized label for the current (or given) locale, falling back de → en.
     */
    public function label(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($locale) {
            'fr' => $this->label_fr ?: $this->label_de,
            'en' => $this->label_en ?: $this->label_de,
            default => $this->label_de,
        };
    }
}
