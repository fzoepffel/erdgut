<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticked-off line on the producer receiving checklist: the goods for one
 * (product, variant, delivery day) arrived from a given farm. Row present =
 * received. Operational only; never affects inventory or availability.
 */
class ProducerReceipt extends Model
{
    protected $fillable = [
        'producer_id',
        'product_id',
        'variant_id',
        'delivery_date',
        'received_by_user_id',
    ];

    // delivery_date is kept as a plain 'Y-m-d' string (not a datetime cast) so
    // exact-match lookups against the order delivery day work identically on
    // MySQL (real DATE column) and SQLite (tests).
    protected $casts = [
        'variant_id' => 'integer',
    ];

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
