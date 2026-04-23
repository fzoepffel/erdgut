<?php

namespace App\Models;

use Database\Factories\BoxItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class BoxItem extends Model
{
    /** @use HasFactory<BoxItemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'box_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price_snapshot',
        'is_extra',
        'is_test',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price_snapshot' => 'decimal:2',
            'is_extra' => 'boolean',
            'is_test' => 'boolean',
        ];
    }

    /**
     * Enforce simple quantity rules at model boundary.
     */
    protected static function booted(): void
    {
        static::saving(function (self $boxItem): void {
            if ((float) $boxItem->quantity <= 0) {
                throw new InvalidArgumentException('Box item quantity must be greater than zero.');
            }
        });
    }

    /**
     * Get the parent box.
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    /**
     * Get the product associated with this box item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
