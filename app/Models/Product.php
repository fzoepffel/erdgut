<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use App\Services\Admin\InventoryService;
use App\Services\ImageProcessor;
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
        'subcategory_id',
        'unit',
        'price_display_unit',
        'unit_price',
        'vat_rate_percent',
        'default_freshness_window_days',
        'buying_price_cents',
        'is_seasonal',
        'is_available',
        'is_published',
        'is_egg_addon',
        'metadata',
        'allergens',
        'dietary_tags',
        'taste_profile_tags',
        'custom_taste_tags',
        'image_path',
        'is_test',
        // Per-product quantity controls
        'quantity_step',
        'unit_weight_grams',
        'unit_volume_ml',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            // Storage is per *canonical* unit of the kind (g for mass,
            // ml for volume, piece, bunch). Decimal precision is 4 so
            // sub-canonical prices (€/g, €/ml) round-trip without
            // rounding loss. All display paths go through
            // `displayPrice()` which renders in the admin's chosen
            // `price_display_unit` and formats to 2 decimals.
            'unit_price' => 'decimal:4',
            // Whole-percent German VAT rate (7 reduced / 19 standard).
            // The gross `unit_price` already includes this tax.
            'vat_rate_percent' => 'integer',
            'default_freshness_window_days' => 'integer',
            'buying_price_cents' => 'integer',
            'is_seasonal' => 'boolean',
            'is_available' => 'boolean',
            'is_published' => 'boolean',
            'is_egg_addon' => 'boolean',
            'metadata' => 'array',
            'allergens' => 'array',
            'dietary_tags' => 'array',
            'taste_profile_tags' => 'array',
            'custom_taste_tags' => 'array',
            'is_test' => 'boolean',
            'quantity_step' => 'integer',
            'unit_weight_grams' => 'integer',
            'unit_volume_ml' => 'integer',
        ];
    }

    /**
     * Get the producer that owns the product.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
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

    /**
     * Localized label for the *display* unit (what the admin chose):
     * "kg", "g", "L", "ml", "Stück", "Bund". Used in admin tables and
     * compact catalog rows.
     */
    public function localizedUnit(?string $locale = null): string
    {
        $display = $this->price_display_unit ?? $this->unit?->defaultDisplayUnit();
        if ($display === null) {
            return '';
        }

        return ProductUnit::displaySymbol($display);
    }

    /**
     * The canonical-unit step size to use for this product's quantity selector.
     *
     * - Piece / Bunch: always 1 (whole units only).
     * - Mass: grams per step (falls back to config('shop.quantity_step') when null).
     * - Volume: ml per step (same fallback).
     */
    public function effectiveQuantityStep(): int
    {
        if ($this->unit === ProductUnit::Piece || $this->unit === ProductUnit::Bunch) {
            return 1;
        }

        return $this->quantity_step ?? max(1, (int) (config('shop.quantity_step') ?? 50));
    }

    /**
     * Render the optional "weight per unit" descriptor (for piece/bunch
     * products) as a *mass* quantity — e.g. "100 g", "1,2 kg".
     *
     * Note: this deliberately does NOT use `displayQuantity()`, which
     * formats with the product's own unit (piece/bunch) and would render
     * a gram figure as "100 Bund". The stored value is always grams.
     */
    public function displayUnitWeight(?string $locale = null): ?string
    {
        if ($this->unit_weight_grams === null) {
            return null;
        }

        return ProductUnit::Mass->formatQuantity($this->unit_weight_grams, $locale);
    }

    /**
     * Render the optional "volume per unit" descriptor (for piece/bunch
     * products) as a *volume* quantity — e.g. "250 ml", "1,5 L". The
     * stored value is always millilitres.
     */
    public function displayUnitVolume(?string $locale = null): ?string
    {
        if ($this->unit_volume_ml === null) {
            return null;
        }

        return ProductUnit::Volume->formatQuantity($this->unit_volume_ml, $locale);
    }

    /**
     * Render the given quantity (in canonical units of `$this->unit`)
     * adaptively: under 1000 g → "850 g"; ≥ 1000 g → "2,3 kg" (same
     * for ml / L). Piece + Bunch render as integer counts.
     */
    public function displayQuantity(int|float $canonicalQuantity, ?string $locale = null): string
    {
        if ($this->unit === null) {
            return (string) $canonicalQuantity;
        }

        return $this->unit->formatQuantity($canonicalQuantity, $locale);
    }

    /**
     * Localized "X,XX €/{displayUnit}" string for catalog display.
     * Uses the admin-chosen `price_display_unit`. Falls back to the
     * kind's default display unit when unset.
     */
    public function displayPrice(?string $locale = null): string
    {
        if ($this->unit === null) {
            return number_format((float) $this->unit_price, 2, ',', '.').' €';
        }

        $displayUnit = $this->price_display_unit ?? $this->unit->defaultDisplayUnit();

        return $this->unit->formatPrice((float) $this->unit_price, $displayUnit, $locale);
    }

    public function localizedOrigin(?string $locale = null): string
    {
        return $this->translationFor($locale)?->origin ?? '';
    }

    public function localizedVariety(?string $locale = null): string
    {
        return $this->translationFor($locale)?->variety ?? '';
    }

    /**
     * The long/detailed description, rendered on the product detail page.
     * Stored in the `taste_profile` column for historical reasons.
     */
    public function localizedTasteProfile(?string $locale = null): string
    {
        return $this->translationFor($locale)?->taste_profile ?? '';
    }

    /**
     * The short, card-friendly description. Required on save going forward,
     * but nullable in the DB so products created before this field existed
     * don't break. Use {@see cardDescription()} for display with a graceful
     * fallback to the long description.
     */
    public function localizedShortDescription(?string $locale = null): string
    {
        return $this->translationFor($locale)?->short_description ?? '';
    }

    /**
     * The description to show on product cards (catalogue + shop): the short
     * description when set, otherwise the long description as a fallback so
     * nothing breaks for products migrated before short descriptions existed.
     * Callers should still clamp the line count in CSS for safety.
     */
    public function cardDescription(?string $locale = null): string
    {
        $short = $this->localizedShortDescription($locale);

        return $short !== '' ? $short : $this->localizedTasteProfile($locale);
    }

    /**
     * Free-form custom taste tags resolved to the requested locale.
     *
     * Each stored entry is a `{de, en, fr}` object; this picks the value
     * for the locale (falling back de → en → fr, then any non-empty value)
     * so a custom tag renders in the visitor's language just like the
     * enum-backed tags do. Returns a clean list of non-empty strings.
     *
     * @return list<string>
     */
    public function localizedCustomTasteTags(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $fallbacks = array_values(array_unique([$locale, 'de', 'en', 'fr']));

        $resolved = [];
        foreach ((array) $this->custom_taste_tags as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = '';
            foreach ($fallbacks as $l) {
                if (isset($entry[$l]) && is_string($entry[$l]) && trim($entry[$l]) !== '') {
                    $label = trim($entry[$l]);
                    break;
                }
            }

            if ($label !== '') {
                $resolved[] = $label;
            }
        }

        return $resolved;
    }

    /**
     * Localized labels for enum-backed and custom taste tags.
     *
     * @return list<string>
     */
    public function localizedTasteTagLabels(?string $locale = null): array
    {
        $labels = self::tasteProfileLabelMap($locale);

        $enumTags = array_map(
            fn (string $value) => $labels[$value],
            array_values(array_filter(
                (array) $this->taste_profile_tags,
                fn ($value) => is_string($value) && isset($labels[$value]),
            )),
        );

        return array_merge($enumTags, $this->localizedCustomTasteTags($locale));
    }

    /**
     * Compact taste chips for shop cards — capped so cards stay scannable.
     *
     * @return list<string>
     */
    public function shopTasteTags(int $limit = 3, ?string $locale = null): array
    {
        if ($limit <= 0) {
            return [];
        }

        return array_slice($this->localizedTasteTagLabels($locale), 0, $limit);
    }

    /**
     * @return array<string, string>
     */
    private static function tasteProfileLabelMap(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return [
            TasteProfile::Sweet->value => (string) __('Sweet', [], $locale),
            TasteProfile::Earthy->value => (string) __('Earthy', [], $locale),
            TasteProfile::Fresh->value => (string) __('Fresh', [], $locale),
            TasteProfile::Peppery->value => (string) __('Peppery', [], $locale),
            TasteProfile::Bitter->value => (string) __('Bitter', [], $locale),
            TasteProfile::Umami->value => (string) __('Umami', [], $locale),
            TasteProfile::Crisp->value => (string) __('Crisp', [], $locale),
            TasteProfile::Herbal->value => (string) __('Herbal', [], $locale),
            TasteProfile::Nutty->value => (string) __('Nutty', [], $locale),
            TasteProfile::Spicy->value => (string) __('Spicy', [], $locale),
            TasteProfile::Sour->value => (string) __('Sour', [], $locale),
            TasteProfile::Rich->value => (string) __('Rich', [], $locale),
        ];
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
     * Numeric selling price expressed in the admin-chosen *display* unit
     * (e.g. €/kg), not the canonical storage unit (€/g). `unit_price` is
     * stored per canonical unit, so multiply by the canonical-per-display
     * factor (1000 for kg/L, 100 for 100 g, 1 for g/ml/piece/bunch).
     */
    public function displayUnitPrice(): float
    {
        if ($this->unit === null) {
            return (float) $this->unit_price;
        }

        $displayUnit = $this->price_display_unit ?? $this->unit->defaultDisplayUnit();

        return (float) $this->unit_price * $this->unit->canonicalUnitsPerDisplay($displayUnit);
    }

    /**
     * Gross margin in euros per display unit: (selling − buying). Null if
     * buying price not set.
     *
     * Both sides must be in the same unit. The admin enters the buying
     * price in the display unit (€/kg) and it is stored verbatim in
     * `buying_price_cents`, whereas `unit_price` is canonical (€/g), so we
     * compare against `displayUnitPrice()` — never the raw `unit_price`.
     */
    public function grossMargin(): ?float
    {
        if ($this->buying_price_cents === null) {
            return null;
        }

        return round($this->displayUnitPrice() - ($this->buying_price_cents / 100), 2);
    }

    /**
     * Margin as a percentage of the customer-facing display-unit price.
     * Null if buying price isn't set or price is zero. Used by the admin
     * product list (F6 in UX review notes) so operators can spot
     * pricing/margin issues at a glance.
     */
    public function grossMarginPercent(): ?float
    {
        $margin = $this->grossMargin();
        $sellingPrice = $this->displayUnitPrice();
        if ($margin === null || $sellingPrice <= 0) {
            return null;
        }

        return round($margin / $sellingPrice * 100, 1);
    }

    /**
     * Fraction of a gross line total that is gross margin (0–1), or null when
     * no buying price is set. Used by the ranking bandit to turn a line's
     * gross revenue into realized margin; a null result means callers fall
     * back to revenue (the margin objective's revenue fallback).
     */
    public function marginFraction(): ?float
    {
        $percent = $this->grossMarginPercent();

        return $percent === null ? null : max(0.0, $percent / 100);
    }

    /**
     * Cold-start value estimate (EUR) for a product with no purchase history
     * yet, used as the ranking bandit's prior "value per add-to-cart". Uses
     * the per-display-unit margin when a buying price exists, else the
     * per-display-unit price (revenue fallback) — so an expensive/high-margin
     * new product is explored with an appropriately high value.
     */
    public function rankingPriorValue(): float
    {
        $fraction = $this->marginFraction();
        $price = $this->displayUnitPrice();

        return $fraction !== null ? $price * $fraction : $price;
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
     * Public URL for the small square card thumbnail, falling back to the
     * full image (then null) when no thumbnail has been generated yet.
     *
     * Used on grid/card views so a ~600px JPEG is served instead of the
     * multi-megabyte original. New uploads always get a thumbnail; the
     * `images:reprocess` command backfills pre-existing products.
     */
    public function thumbUrl(): ?string
    {
        $path = $this->image_path;
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');
        $thumb = ImageProcessor::thumbPath($path);

        if ($disk->exists($thumb)) {
            return $disk->url($thumb);
        }

        return $disk->exists($path) ? $disk->url($path) : null;
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
     * Daily inflow rows for this product. One row per physical
     * receipt; aggregated by {@see InventoryService}.
     */
    public function stockInflows(): HasMany
    {
        return $this->hasMany(ProductStockInflow::class);
    }

    /**
     * Recurring per-weekday inflow schedules attached to this product
     * (Phase 2+). Materialized into `stockInflows()` rows by
     * {@see InventoryService::materializeWindow()}.
     */
    public function stockInflowSchedules(): HasMany
    {
        return $this->hasMany(ProductStockInflowSchedule::class);
    }

    /**
     * Gross active inflow minus committed outflow for `$date`.
     * Delegates to {@see InventoryService} so the math stays in one
     * place.
     */
    public function availableOnDate(Carbon $date): float
    {
        return app(InventoryService::class)->availableOnDate($this, $date);
    }

    /**
     * Committed outflow for `$date` (spargel-order grams + box-item
     * quantities aggregated by computed delivery date).
     */
    public function committedOnDate(Carbon $date): float
    {
        return app(InventoryService::class)->committedOnDate($this, $date);
    }

    /**
     * Convenience: `availableOnDate` minus `committedOnDate` exposed
     * as a single read. Identical to `availableOnDate` since the
     * service already subtracts committed, kept as its own method
     * for readability at call sites.
     */
    public function remainingOnDate(Carbon $date): float
    {
        return $this->availableOnDate($date);
    }
}
