<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'alt',
        'position',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Public URL for the image on the public disk, or null if the file is missing.
     */
    public function url(): ?string
    {
        if ($this->path === null || $this->path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->path) ? $disk->url($this->path) : null;
    }
}
