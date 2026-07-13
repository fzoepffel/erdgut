<?php

namespace App\Models;

use App\Services\ImageProcessor;
use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'slug',
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

    /** Products this producer supplies via the multi-producer pivot. */
    public function suppliedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_producer')
            ->withPivot('position')
            ->withTimestamps();
    }

    /**
     * Union of the legacy hasMany (primary producer) and the pivot,
     * deduplicated. Use for producer pages so retro-added products appear.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function allProducts(): \Illuminate\Support\Collection
    {
        // Relation properties (not ->get()) so eager loads on the producer
        // are honored; they lazy-load when nothing was eager loaded.
        return $this->products
            ->concat($this->suppliedProducts)
            ->unique('id')
            ->values();
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
     * Use the slug for route-model binding so /producers/{producer} resolves
     * by the URL-safe slug rather than the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Ensure this producer has a slug derived from its German name (falling
     * back to en/fr, then producer-{id}), deduped against every other row so
     * the unique index never collides. Idempotent: only writes when the slug
     * is currently empty, so admin edits don't silently change a live URL.
     */
    public function ensureSlug(): void
    {
        if ($this->slug !== null && $this->slug !== '') {
            return;
        }

        $name = $this->translationFor('de')?->name
            ?? $this->translationFor('en')?->name
            ?? $this->translationFor('fr')?->name
            ?? '';

        $base = Str::slug($name) ?: 'producer-'.$this->id;
        $slug = $base;
        $n = 2;
        while (static::query()->where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        $this->forceFill(['slug' => $slug])->save();
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

    /**
     * Square card thumbnail for grids. Uses the generated thumbnail of an
     * uploaded image when present, otherwise falls back to the full image
     * resolution from {@see primaryImageUrl()} (external `image_url` values
     * can't be resized, so they pass through unchanged).
     */
    public function primaryThumbUrl(): ?string
    {
        $path = $this->image_path;
        if ($path !== null && $path !== '') {
            $disk = Storage::disk('public');
            $thumb = ImageProcessor::thumbPath($path);
            if ($disk->exists($thumb)) {
                return $disk->url($thumb);
            }
        }

        return $this->primaryImageUrl();
    }
}
