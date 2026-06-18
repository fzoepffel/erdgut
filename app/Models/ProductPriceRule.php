<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Services\Shop\PriceResolver;
use Database\Factories\ProductPriceRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A conditional price for a product. See the migration for the unified-table
 * rationale. The {@see PriceResolver} reads these.
 */
class ProductPriceRule extends Model
{
    /** @use HasFactory<ProductPriceRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'customer_type',
        'starts_on',
        'ends_on',
        'min_quantity',
        'unit_price',
        'priority',
        'label',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'min_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'customer_type' => CustomerType::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Rules whose date window covers $date (null bounds are open). */
    public function scopeEffectiveOn(Builder $query, Carbon $date): void
    {
        $iso = $date->toDateString();
        $query->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $iso))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $iso));
    }
}
