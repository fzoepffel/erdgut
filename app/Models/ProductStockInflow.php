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
        'original_received_on',
        'quantity',
        'freshness_window_days',
        'b2b_only',
        'source_schedule_id',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date:Y-m-d',
            // The originally-scheduled date for this row. For one-off
            // inflows it equals received_on; for schedule-materialized
            // rows it sticks even when admin edits the received_on,
            // so the materializer can dedup on its intent rather than
            // the latest physical date.
            'original_received_on' => 'date:Y-m-d',
            'quantity' => 'decimal:4',
            'freshness_window_days' => 'integer',
            'b2b_only' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Default `original_received_on` to `received_on` when the
        // caller doesn't supply it. Keeps every existing
        // `ProductStockInflow::create([...])` call (including the ones
        // in the data migration and in tests) working without having
        // to thread the column through every fixture.
        static::creating(function (self $inflow): void {
            if ($inflow->original_received_on === null && $inflow->received_on !== null) {
                $inflow->original_received_on = $inflow->received_on;
            }
        });
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
