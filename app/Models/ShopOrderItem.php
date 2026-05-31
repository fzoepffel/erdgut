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
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShopOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_order_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price_snapshot_eur',
        'line_total_eur',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price_snapshot_eur' => 'decimal:4',
            'line_total_eur' => 'decimal:2',
        ];
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
}
