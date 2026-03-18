<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'producer_id',
        'name',
        'category',
        'unit_price',
        'is_seasonal',
        'is_available',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_seasonal' => 'boolean',
            'is_available' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the producer that owns the product.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }
}
