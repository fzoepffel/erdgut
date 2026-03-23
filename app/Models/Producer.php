<?php

namespace App\Models;

use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'region',
        'description',
        'image_url',
        'image_path',
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
            'is_test' => 'boolean',
        ];
    }

    /**
     * Get all products offered by this producer.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Prefer uploaded catalog image; fall back to legacy external image_url.
     *
     * Missing files on disk (orphaned image_path) are ignored so callers fall back to image_url or null.
     */
    public function primaryImageUrl(): ?string
    {
        $path = $this->image_path;
        if ($path !== null && $path !== '') {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                return $disk->url($path);
            }
        }

        $url = $this->image_url;
        if ($url !== null && $url !== '') {
            return $url;
        }

        return null;
    }
}
