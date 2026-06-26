<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One cart line on an anonymous {@see ShopOrder}.
 *
 * `quantity` is stored in the product's *canonical* unit (g / ml / piece
 * / bunch) — the same unit as `product_stock_inflows.quantity` — so the
 * InventoryService outflow math stays unit-consistent across channels.
 * The unit + per-unit price are snapshotted at order time so a later
 * catalog edit can never rewrite the receipt of an order already placed.
 *
 * @property int $id
 * @property int $shop_order_id
 * @property int $product_id
 * @property numeric $quantity
 * @property string $unit
 * @property numeric $unit_price_snapshot_eur
 * @property numeric $line_total_eur
 * @property int $vat_rate_snapshot_percent
 * @property numeric $vat_amount_eur
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShopOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'product_id',
        'variant_id',
        'variant_name_snapshot',
        'quantity',
        'unit',
        'unit_price_snapshot_eur',
        'price_rule_id',
        'line_total_eur',
        'vat_rate_snapshot_percent',
        'vat_amount_eur',
        'packed_at',
        'packed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price_snapshot_eur' => 'decimal:4',
            'line_total_eur' => 'decimal:2',
            'vat_rate_snapshot_percent' => 'integer',
            'vat_amount_eur' => 'decimal:2',
            'packed_at' => 'datetime',
        ];
    }

    /** Whether this line has been packed for fulfilment. */
    public function isPacked(): bool
    {
        return $this->packed_at !== null;
    }

    /**
     * @return BelongsTo<ShopOrder, $this>
     */
    public function shopOrder(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by_user_id');
    }
}
