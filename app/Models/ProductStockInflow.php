<?php

namespace App\Models;

use App\Services\Admin\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One physical receipt of a product into stock.
 *
 * Materialized rows from a recurring schedule keep their
 * `source_schedule_id` back-pointer; one-off receipts entered by
 * admin leave it null.
 *
 * An inflow is "active" on date `D` when
 * `received_on <= D <= received_on + (freshness_window_days - 1)`.
 * Aggregating active inflows for a product gives gross availability;
 * outflows (orders + box items) are subtracted on the fly by
 * {@see InventoryService::availableOnDate}.
 */
class ProductStockInflow extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'received_on',
        'quantity',
        'freshness_window_days',
        'source_schedule_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date:Y-m-d',
            'quantity' => 'decimal:4',
            'freshness_window_days' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductStockInflowSchedule::class, 'source_schedule_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Filter to inflows whose freshness window covers `$date`. The
     * `freshness_window_days >= 1` invariant is enforced at write
     * time, so the window is `[received_on, received_on + (n-1)]`
     * inclusive on both ends.
     *
     * @param  Builder<ProductStockInflow>  $query
     */
    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        $iso = $date->copy()->startOfDay()->toDateString();
        $driver = $query->getConnection()->getDriverName();

        $expiresExpression = match ($driver) {
            'sqlite' => "date(received_on, '+' || (freshness_window_days - 1) || ' day')",
            'pgsql' => "received_on + (freshness_window_days - 1) * INTERVAL '1 day'",
            default => 'DATE_ADD(received_on, INTERVAL (freshness_window_days - 1) DAY)',
        };

        return $query
            ->whereDate('received_on', '<=', $iso)
            ->whereRaw($expiresExpression.' >= ?', [$iso]);
    }
}
