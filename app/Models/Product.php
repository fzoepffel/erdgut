<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        'category',
        'unit_price',
        'buying_price_cents',
        'is_seasonal',
        'is_available',
        'is_published',
        'is_egg_addon',
        'metadata',
        'allergens',
        'dietary_tags',
        'taste_profile_tags',
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
            'unit_price' => 'decimal:2',
            'buying_price_cents' => 'integer',
            'is_seasonal' => 'boolean',
            'is_available' => 'boolean',
            'is_published' => 'boolean',
            'is_egg_addon' => 'boolean',
            'metadata' => 'array',
            'allergens' => 'array',
            'dietary_tags' => 'array',
            'taste_profile_tags' => 'array',
            'is_test' => 'boolean',
        ];
    }

    /**
     * Get the producer that owns the product.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * @return HasManyRelation<ProductTranslation, $this>
     */
    public function translations(): HasManyRelation
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function translationFor(?string $locale = null): ?ProductTranslation
    {
        $locale = $locale ?? app()->getLocale();
        $fallbacks = array_values(array_unique([$locale, 'de', 'en', 'fr']));

        /** @var Collection<int, ProductTranslation>|null $loaded */
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

    public function localizedUnit(?string $locale = null): string
    {
        return $this->translationFor($locale)?->unit ?? '';
    }

    public function localizedOrigin(?string $locale = null): string
    {
        return $this->translationFor($locale)?->origin ?? '';
    }

    public function localizedVariety(?string $locale = null): string
    {
        return $this->translationFor($locale)?->variety ?? '';
    }

    public function localizedTasteProfile(?string $locale = null): string
    {
        return $this->translationFor($locale)?->taste_profile ?? '';
    }

    /**
     * Get all box item rows linked to this product.
     */
    public function boxItems(): HasMany
    {
        return $this->hasMany(BoxItem::class);
    }

    /**
     * Get all gallery images for this product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Products this product is commonly paired with.
     */
    public function pairings(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_pairings', 'product_id', 'paired_product_id')
            ->withPivot('note')
            ->withTimestamps();
    }

    /**
     * Gross margin in euros: (unit_price − buying_price). Null if buying price not set.
     */
    public function grossMargin(): ?float
    {
        if ($this->buying_price_cents === null) {
            return null;
        }

        return round(((float) $this->unit_price) - ($this->buying_price_cents / 100), 2);
    }

    /**
     * Margin as a percentage of the customer-facing unit price. Null if
     * buying price isn't set or unit price is zero. Used by the admin
     * product list (F6 in UX review notes) so operators can spot
     * pricing/margin issues at a glance.
     */
    public function grossMarginPercent(): ?float
    {
        $margin = $this->grossMargin();
        if ($margin === null || (float) $this->unit_price <= 0) {
            return null;
        }

        return round($margin / ((float) $this->unit_price) * 100, 1);
    }

    /**
     * Get all boxes that include this product via box items.
     */
    public function boxes(): BelongsToMany
    {
        return $this->belongsToMany(Box::class, 'box_items')
            ->withPivot(['quantity', 'unit', 'is_extra'])
            ->withTimestamps();
    }

    /**
     * Public URL for the catalog image on the public disk, or null if none.
     *
     * If the DB path points to a missing file (e.g. after deploy without persisted storage), returns null
     * so the UI can show a placeholder instead of a 404 request.
     */
    public function imageUrl(): ?string
    {
        $path = $this->image_path;
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }

    /**
     * Products visible to customers in shop and eligible for default box composition / extras.
     */
    public function scopeVisibleInCatalog(Builder $query): void
    {
        $query->where('is_available', true)->where('is_published', true);
    }

    /**
     * Eggs add-on products. These are filtered OUT of the standard size template
     * auto-distribution and surface only in the per-customer eggs add-on slot.
     */
    public function scopeEggAddOns(Builder $query): void
    {
        $query->where('is_egg_addon', true);
    }

    /**
     * Weekly planned stock rows for this product.
     */
    public function weekStocks(): HasMany
    {
        return $this->hasMany(ProductWeekStock::class);
    }

    /**
     * Products with a positive planned quantity for the given Monday.
     */
    public function scopeAvailableForWeek(Builder $query, Carbon $week): void
    {
        $date = $week->copy()->startOfDay()->toDateString();
        $query->visibleInCatalog()->whereHas(
            'weekStocks',
            fn ($q) => $q->whereDate('week_start_date', $date)->where('planned_quantity', '>', 0)
        );
    }

    /**
     * Planned quantity in the weekly catalog for this product (0.0 if no row).
     */
    public function plannedQuantityForWeek(Carbon $week): float
    {
        $date = $week->copy()->startOfDay()->toDateString();
        $stock = $this->weekStocks()
            ->whereDate('week_start_date', $date)
            ->first();

        return $stock !== null ? (float) $stock->planned_quantity : 0.0;
    }

    /**
     * Sum of box item quantities already assigned to boxes that start on the given Monday.
     */
    public function distributedQuantityForWeek(Carbon $week): float
    {
        $date = $week->copy()->startOfDay()->toDateString();

        $sum = DB::table('box_items')
            ->join('boxes', 'boxes.id', '=', 'box_items.box_id')
            ->where('box_items.product_id', $this->id)
            ->whereDate('boxes.week_start_date', $date)
            ->sum('box_items.quantity');

        return (float) $sum;
    }

    /**
     * Remaining = planned − distributed (may be negative → over-allocated).
     */
    public function remainingForWeek(Carbon $week): float
    {
        return round($this->plannedQuantityForWeek($week) - $this->distributedQuantityForWeek($week), 2);
    }
}
