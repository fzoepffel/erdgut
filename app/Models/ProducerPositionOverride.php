<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual sourcing decision for one receiving position: the committed demand
 * for (product_id, variant_id, delivery_date) is sourced from
 * target_producer_id via target_product_id instead of the computed
 * attribution. Consulted by the receiving panel and the farmer order export;
 * never mutates the placed order, so removing the row restores the default.
 */
class ProducerPositionOverride extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'delivery_date',
        'target_product_id',
        'target_producer_id',
        'created_by_user_id',
    ];

    // delivery_date stays a plain 'Y-m-d' string (not a datetime cast) so
    // exact-match lookups against the order delivery day behave identically on
    // MySQL (real DATE column) and SQLite (tests). Mirrors ProducerReceipt.
    protected $casts = [
        'variant_id' => 'integer',
        'target_product_id' => 'integer',
        'target_producer_id' => 'integer',
    ];

    /** The product as ordered by the customer. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The product we now source the position from (may equal {@see product}). */
    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function targetProducer(): BelongsTo
    {
        return $this->belongsTo(Producer::class, 'target_producer_id');
    }

    /** Stable key "pid|vkey|iso" for O(1) lookup while building read-models. */
    public function positionKey(): string
    {
        return $this->product_id.'|'.$this->variant_id.'|'.$this->delivery_date;
    }
}
