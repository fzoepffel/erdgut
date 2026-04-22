<?php

namespace App\Models;

use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;
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
     * @return HasManyRelation<ProducerTranslation, $this>
     */
    public function translations(): HasManyRelation
    {
        return $this->hasMany(ProducerTranslation::class);
    }

    public function translationFor(?string $locale = null): ?ProducerTranslation
    {
        $locale = $locale ?? app()->getLocale();
        $fallbacks = array_values(array_unique([$locale, 'de', 'en', 'fr']));

        /** @var Collection<int, ProducerTranslation>|null $loaded */
        $loaded = $this->getRelationValue('translations');
        if ($loaded instanceof Collection) {
            foreach ($fallbacks as $l) {
                $t = $loaded->firstWhere('locale', $l);
                if ($t !== null) {
                    return $t;
                }
            }

            return null;
        }

        foreach ($fallbacks as $l) {
            $t = $this->translations()->where('locale', $l)->first();
            if ($t !== null) {
                return $t;
            }
        }

        return null;
    }

    public function getNameAttribute(): string
    {
        return $this->translationFor()?->name ?? '';
    }

    public function getRegionAttribute(): ?string
    {
        return $this->translationFor()?->region;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->translationFor()?->description;
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
