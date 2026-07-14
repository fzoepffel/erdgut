<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buying price override for one (product, farm, grade) combination, in
 * display-unit cents. Row exists = override set; the resolution chain
 * lives in {@see Product::buyingPriceCentsForProducer()}.
 */
class ProductProducerVariantPrice extends Model
{
    protected $table = 'product_producer_variant_prices';

    protected $fillable = [
        'product_id',
        'producer_id',
        'variant_id',
        'buying_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'producer_id' => 'integer',
            'variant_id' => 'integer',
            'buying_price_cents' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
