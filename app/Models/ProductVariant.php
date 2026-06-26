<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named grade of a product (e.g. "Thin / Grade B" asparagus), sold at its own
 * price with its own stock. The product itself is the implicit default grade,
 * so a row here only ever represents an *extra* named grade.
 *
 * `unit_price` is per canonical unit (same convention as products.unit_price);
 * NULL inherits the product's base price via {@see effectiveUnitPrice()}.
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'unit_price',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'unit_price' => 'decimal:4',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Grade label resolved to the requested locale, falling back de → en → fr
     * → any non-empty value (mirrors {@see Product::localizedCustomTasteTags}).
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

    /**
     * Effective per-canonical-unit price: the variant's own price when set,
     * otherwise the parent product's base price.
     */
    public function effectiveUnitPrice(): float
    {
        if ($this->unit_price !== null) {
            return (float) $this->unit_price;
        }

        return (float) ($this->product?->unit_price ?? 0.0);
    }
}
