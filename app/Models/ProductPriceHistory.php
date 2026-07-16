<?php

namespace App\Models;

use App\Services\Admin\PriceHistoryRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One data point of a product's price development. Series key is
 * (kind, variant_id, producer_id); prices are € per DISPLAY unit.
 * Rows are appended by {@see PriceHistoryRecorder}
 * only when a series' value actually changed.
 */
class ProductPriceHistory extends Model
{
    public const KIND_BASE_GROSS = 'base_gross';

    public const KIND_BUSINESS_NET = 'business_net';

    public const KIND_BUYING = 'buying';

    protected $fillable = [
        'product_id',
        'variant_id',
        'producer_id',
        'kind',
        'price',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<Producer, $this> */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }
}
