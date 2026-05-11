<?php

namespace App\Models;

use App\Services\Admin\RecurringStockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring weekly-stock plan for a single product.
 *
 * Materializes into `product_week_stocks` rows for every Monday in
 * `[starts_on_monday, ends_on_monday]` (`null` end = open-ended).
 * Generation happens at write time (via {@see RecurringStockService}),
 * extended by a weekly cron. Read paths never see this model — they
 * keep querying `ProductWeekStock` rows which carry a `source_schedule_id`
 * back-pointer for manual-override detection.
 */
class ProductWeekStockSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'planned_quantity',
        'starts_on_monday',
        'ends_on_monday',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:2',
            'starts_on_monday' => 'date:Y-m-d',
            'ends_on_monday' => 'date:Y-m-d',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Rows materialized from this schedule. Used by `cancelFrom` /
     * `adjustFrom` to find what's safe to delete.
     */
    public function weekStocks(): HasMany
    {
        return $this->hasMany(ProductWeekStock::class, 'source_schedule_id');
    }

    public function isActive(): bool
    {
        return $this->ends_on_monday === null || $this->ends_on_monday->isFuture();
    }
}
