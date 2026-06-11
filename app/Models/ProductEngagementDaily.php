<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Anonymous per-product daily engagement counters for the ranking bandit.
 *
 * @property int $product_id
 * @property Carbon $day
 * @property int $impressions
 * @property int $add_to_carts
 */
class ProductEngagementDaily extends Model
{
    protected $table = 'product_engagement_daily';

    protected $fillable = [
        'product_id',
        'day',
        'impressions',
        'add_to_carts',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'impressions' => 'integer',
            'add_to_carts' => 'integer',
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
     * Add to a (product, day) counter, creating the row if needed. `$column`
     * is one of `impressions` / `add_to_carts`. Driver-agnostic and race-safe:
     * an `increment()` updates an existing row; otherwise we insert, and if a
     * concurrent request won the insert (unique violation) we increment instead.
     */
    public static function bumpCounter(int $productId, string $column, string $day, int $by = 1): void
    {
        $affected = static::query()
            ->where('product_id', $productId)
            ->whereDate('day', $day)
            ->increment($column, $by);

        if ($affected > 0) {
            return;
        }

        try {
            static::query()->create([
                'product_id' => $productId,
                'day' => $day,
                'impressions' => $column === 'impressions' ? $by : 0,
                'add_to_carts' => $column === 'add_to_carts' ? $by : 0,
            ]);
        } catch (QueryException) {
            // Lost a create race — the row now exists; just increment.
            static::query()
                ->where('product_id', $productId)
                ->whereDate('day', $day)
                ->increment($column, $by);
        }
    }
}
