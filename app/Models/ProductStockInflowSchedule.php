<?php

namespace App\Models;

use App\Services\Admin\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-weekday recurring inflow schedule.
 *
 * Models "this product is received in `planned_quantity` every
 * `weekday`, starting on `starts_on`, until `ends_on` (or
 * indefinitely)". One schedule covers one weekday — a producer who
 * delivers on Mon AND Wed gets two schedules.
 *
 * The materializer (see {@see InventoryService})
 * walks active schedules and writes a `product_stock_inflows` row
 * for each weekday occurrence within the window. Read paths query
 * inflows directly; this model is the source-of-truth for cron
 * extension and cancel/adjust semantics.
 *
 * `weekday` follows Carbon's `dayOfWeek` convention (0 = Sunday,
 * 1 = Monday, …, 6 = Saturday) so it lines up with
 * `Subscription.delivery_weekday` and
 * `config('delivery.zones.*.allowed_weekdays')`.
 */
class ProductStockInflowSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'variant_id',
        'producer_id',
        'weekday',
        'planned_quantity',
        'freshness_window_days',
        'b2b_only',
        'starts_on',
        'ends_on',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'planned_quantity' => 'decimal:4',
            'freshness_window_days' => 'integer',
            'b2b_only' => 'boolean',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Inflow rows materialized from this schedule. Used by
     * `cancelScheduleFrom` / `adjustScheduleFrom` to find what's safe
     * to delete vs. preserve as a manual override.
     */
    public function inflows(): HasMany
    {
        return $this->hasMany(ProductStockInflow::class, 'source_schedule_id');
    }

    public function isActive(): bool
    {
        return $this->ends_on === null || $this->ends_on->isFuture();
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }
}
