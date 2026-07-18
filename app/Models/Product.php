<?php

namespace App\Models;

use App\Enums\CountUnitLabel;
use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use App\Services\Admin\InventoryService;
use App\Services\ImageProcessor;
use App\Support\HumanText;
use App\Support\InventoryCache;
use Carbon\CarbonInterface;
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
        'group_id',
        'group_label',
        'group_position',
        'category',
        'subcategory_id',
        'unit',
        'price_display_unit',
        'unit_price',
        'vat_rate_percent',
        'default_freshness_window_days',
        'buying_price_cents',
        'is_seasonal',
        'request_visibility',
        'request_visibility_until',
        'season_start_month',
        'season_start_day',
        'season_end_month',
        'season_end_day',
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
        'count_unit_label',
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
            'group_label' => 'array',
            'group_position' => 'integer',
            'count_unit_label' => CountUnitLabel::class,
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
            'season_start_month' => 'integer',
            'season_start_day' => 'integer',
            'season_end_month' => 'integer',
            'season_end_day' => 'integer',
            'request_visibility_until' => 'date',
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
    /**
     * The display group this product belongs to (nullable). See
     * {@see ProductGroup}: the group is the card, this product is a grade.
     *
     * @return BelongsTo<ProductGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /**
     * Short grade label inside the group's card (localized, fallback chain
     * like {@see ProductVariant::localizedName}); '' when unset — callers
     * fall back to the producer name.
     */
    public function localizedGroupLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $label = (array) ($this->group_label ?? []);

        foreach (array_unique([$locale, 'de', 'en', 'fr']) as $l) {
            if (isset($label[$l]) && is_string($label[$l]) && trim($label[$l]) !== '') {
                return trim($label[$l]);
            }
        }

        foreach ($label as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * All producers supplying this product, primary first. The single
     * `producer_id` column stays as the eager-loadable PRIMARY producer
     * (mirror of the position-0 pivot row, maintained by syncProducers()).
     */
    public function producers(): BelongsToMany
    {
        return $this->belongsToMany(Producer::class, 'product_producer')
            ->withPivot(['position', 'buying_price_cents'])
            ->withTimestamps()
            ->orderBy('product_producer.position')
            ->orderBy('product_producer.id');
    }

    /**
     * Single write path for the producer set: syncs the pivot (order =
     * position) and mirrors the first id into producer_id, so column and
     * pivot can never drift. Bumps the inventory cache explicitly because
     * BelongsToMany::sync() fires no Product model events.
     *
     * @param  list<int>  $producerIds  ordered; first = primary; must not be empty
     */
    public function syncProducers(array $producerIds): void
    {
        $producerIds = array_values(array_unique(array_map('intval', $producerIds)));
        if ($producerIds === []) {
            throw new \InvalidArgumentException('A product needs at least one producer.');
        }

        $pivot = [];
        foreach ($producerIds as $position => $id) {
            $pivot[$id] = ['position' => $position];
        }
        // sync() updates existing pivot rows with ONLY the given attributes,
        // so per-farm buying_price_cents survives reorders (pinned by
        // ProductProducersTest). A detached-and-reattached farm loses its
        // price on purpose: the row is deleted and recreated.
        $this->producers()->sync($pivot);
        $this->forceFill(['producer_id' => $producerIds[0]])->save();

        InventoryCache::bump();
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

    /**
     * Conditional price rules (date overrides, customer-group prices, …).
     *
     * @return HasManyRelation<ProductPriceRule, $this>
     */
    public function priceRules(): HasManyRelation
    {
        return $this->hasMany(ProductPriceRule::class);
    }

    /**
     * Named grades of this product (e.g. "Thin"). The product itself is the
     * implicit default/"Standard" grade, so this only holds the extra grades.
     *
     * @return HasManyRelation<ProductVariant, $this>
     */
    public function variants(): HasManyRelation
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Buying price overrides per (farm, grade); see
     * {@see buyingPriceCentsForProducer()} for the resolution chain.
     *
     * @return HasManyRelation<ProductProducerVariantPrice, $this>
     */
    public function producerVariantPrices(): HasManyRelation
    {
        return $this->hasMany(ProductProducerVariantPrice::class);
    }

    /**
     * Active named grades, ready for the storefront grade selector.
     *
     * @return Collection<int, ProductVariant>
     */
    public function activeVariants(): Collection
    {
        $loaded = $this->getRelationValue('variants');
        if ($loaded instanceof Collection) {
            return $loaded->where('is_active', true)->values();
        }

        return $this->variants()->where('is_active', true)->get();
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
        if (($word = $this->countUnitWord(1, $locale)) !== null) {
            return $word;
        }

        $display = $this->price_display_unit ?? $this->unit?->defaultDisplayUnit();
        if ($display === null) {
            return '';
        }

        return ProductUnit::displaySymbol($display);
    }

    /**
     * The display word replacing "Stück"/"Bund" for THIS product (a pot
     * of basil, a bag of flour, a crate of radishes), correctly
     * pluralized. Null when the product is not count-kind (piece/bunch)
     * or carries no label, so a stale DB value on a mass product stays
     * harmless everywhere.
     */
    public function countUnitWord(int|float $count = 1, ?string $locale = null): ?string
    {
        $isCountKind = $this->unit === ProductUnit::Piece || $this->unit === ProductUnit::Bunch;
        if (! $isCountKind || $this->count_unit_label === null) {
            return null;
        }

        return $this->count_unit_label->label($count, $locale);
    }

    /**
     * The unit label that matches the quantity the admin (or service) actually
     * stores — i.e. the canonical unit, not the display/pricing unit.
     *
     * - Mass   → "g"    (quantities are in grams, regardless of €/kg pricing)
     * - Volume → "ml"   (quantities are in ml, regardless of €/L pricing)
     * - Piece  → "Stück"
     * - Bunch  → "Bund"
     *
     * Used in the admin manual-order form so the operator sees the right unit
     * next to the quantity input.
     */
    public function canonicalUnitLabel(): string
    {
        return match ($this->unit) {
            ProductUnit::Mass => 'g',
            ProductUnit::Volume => 'ml',
            ProductUnit::Piece => $this->countUnitWord(1) ?? 'Stück',
            ProductUnit::Bunch => $this->countUnitWord(1) ?? 'Bund',
            default => '',
        };
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

        return $this->unit->formatQuantity($canonicalQuantity, $locale, $this->countUnitWord($canonicalQuantity, $locale));
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

        return $this->unit->formatPrice((float) $this->unit_price, $displayUnit, $locale, $this->countUnitWord(1, $locale));
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
        return HumanText::normalize($this->translationFor($locale)?->taste_profile);
    }

    /**
     * The short, card-friendly description. Required on save going forward,
     * but nullable in the DB so products created before this field existed
     * don't break. Use {@see cardDescription()} for display with a graceful
     * fallback to the long description.
     */
    public function localizedShortDescription(?string $locale = null): string
    {
        return HumanText::normalize($this->translationFor($locale)?->short_description);
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
     * Effective buying price for ONE farm, in display-unit cents: the
     * per-farm pivot override when set, else the product default. A null
     * or 0 producer id ("unattributable") always means the default.
     */
    public function buyingPriceCentsForProducer(?int $producerId, ?int $variantId = null): ?int
    {
        if ($producerId === null || $producerId <= 0) {
            return $this->buying_price_cents !== null ? (int) $this->buying_price_cents : null;
        }

        // Grade-level override first: (farm, grade) row when present.
        if ($variantId !== null && $variantId > 0) {
            $gradeCents = $this->relationLoaded('producerVariantPrices')
                ? $this->producerVariantPrices
                    ->first(fn ($r): bool => (int) $r->producer_id === $producerId && (int) $r->variant_id === $variantId)
                    ?->buying_price_cents
                : $this->producerVariantPrices()
                    ->where('producer_id', $producerId)
                    ->where('variant_id', $variantId)
                    ->value('buying_price_cents');
            if ($gradeCents !== null) {
                return (int) $gradeCents;
            }
        }

        $pivotCents = $this->relationLoaded('producers')
            ? $this->producers->firstWhere('id', $producerId)?->pivot?->buying_price_cents
            : $this->producers()->where('producers.id', $producerId)->first()?->pivot?->buying_price_cents;

        $cents = $pivotCents ?? $this->buying_price_cents;

        return $cents !== null ? (int) $cents : null;
    }

    /** Effective per-farm buying price in display-unit euros. */
    public function buyingPriceForProducer(?int $producerId, ?int $variantId = null): ?float
    {
        $cents = $this->buyingPriceCentsForProducer($producerId, $variantId);

        return $cents !== null ? round($cents / 100, 4) : null;
    }

    /**
     * The most expensive applicable buying price (max of the default and
     * every SET per-farm override) in display-unit cents — the
     * conservative basis for the minimum-margin price floor.
     */
    public function maxBuyingPriceCents(): ?int
    {
        $candidates = $this->relationLoaded('producers')
            ? $this->producers->map(fn ($p) => $p->pivot?->buying_price_cents)->all()
            : [$this->producers()->max('product_producer.buying_price_cents')];
        $candidates[] = $this->buying_price_cents;
        $candidates = array_filter($candidates, fn ($v): bool => $v !== null);

        return $candidates !== [] ? (int) max($candidates) : null;
    }

    /** {@see maxBuyingPriceCents}, in display-unit euros. */
    public function maxBuyingPriceEur(): ?float
    {
        $cents = $this->maxBuyingPriceCents();

        return $cents !== null ? round($cents / 100, 4) : null;
    }

    /**
     * The most expensive applicable buying price FOR ONE GRADE: the max
     * over every registered farm's effective price for that grade
     * ((farm, grade) override, else farm price, else default), plus the
     * default itself. Null variant delegates to the product-level max.
     */
    public function maxBuyingPriceCentsForVariant(?int $variantId): ?int
    {
        if ($variantId === null || $variantId <= 0) {
            return $this->maxBuyingPriceCents();
        }

        $farmIds = $this->relationLoaded('producers')
            ? $this->producers->pluck('id')->map(fn ($v): int => (int) $v)->all()
            : $this->producers()->pluck('producers.id')->map(fn ($v): int => (int) $v)->all();

        $candidates = [$this->buying_price_cents];
        foreach ($farmIds as $farmId) {
            $candidates[] = $this->buyingPriceCentsForProducer($farmId, $variantId);
        }
        $candidates = array_filter($candidates, fn ($v): bool => $v !== null);

        return $candidates !== [] ? (int) max($candidates) : null;
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
     * Whether the product is in its recurring yearly season window on a given
     * date (defaults to now). Non-seasonal products are always in season; a
     * seasonal product with no/incomplete window is treated as in season
     * year-round. Windows may wrap the year boundary (e.g. Nov 15 – Feb 28).
     *
     * Drives the restaurant (B2B) catalog (in-stock OR in-season); the
     * consumer storefront does not use this.
     */
    /**
     * Effective request-area visibility on a date: an 'always'/'never'
     * override may carry an expiry, after which the product falls back to
     * 'auto' (the season window).
     */
    public function requestVisibilityOn(?CarbonInterface $on = null): string
    {
        $on ??= Carbon::now();
        $visibility = (string) ($this->request_visibility ?? 'auto');
        if ($visibility === 'auto') {
            return 'auto';
        }
        if ($this->request_visibility_until !== null && $on->startOfDay()->gt($this->request_visibility_until)) {
            return 'auto';
        }

        return $visibility;
    }

    /** Would this product appear in the B2B request area on that date (stock aside)? */
    public function isRequestableOn(?CarbonInterface $on = null): bool
    {
        $visibility = $this->requestVisibilityOn($on);

        return $visibility === 'always'
            || ($visibility !== 'never' && $this->is_seasonal && $this->isInSeason($on));
    }

    /**
     * End of the CURRENT season window (for "regularly available until ...").
     * Null when the product has no bounded window or is out of season.
     */
    public function currentSeasonEnd(?CarbonInterface $on = null): ?Carbon
    {
        $on = Carbon::parse($on ?? Carbon::now())->startOfDay();
        if (! $this->is_seasonal || $this->season_end_month === null || $this->season_end_day === null || ! $this->isInSeason($on)) {
            return null;
        }
        $end = Carbon::create($on->year, (int) $this->season_end_month, (int) $this->season_end_day)->startOfDay();
        if ($end->lt($on)) {
            $end = $end->addYear(); // wrapping window (e.g. Nov -> Feb)
        }

        return $end;
    }

    /**
     * Start of the NEXT season window (for "regularly available from ...").
     * Null when the product is non-seasonal/unbounded (always in season).
     */
    public function nextSeasonStart(?CarbonInterface $on = null): ?Carbon
    {
        $on = Carbon::parse($on ?? Carbon::now())->startOfDay();
        if (! $this->is_seasonal || $this->season_start_month === null || $this->season_start_day === null) {
            return null;
        }
        $start = Carbon::create($on->year, (int) $this->season_start_month, (int) $this->season_start_day)->startOfDay();
        if ($start->lte($on)) {
            $start = $start->addYear();
        }

        return $start;
    }

    public function isInSeason(?CarbonInterface $on = null): bool
    {
        if (! $this->is_seasonal) {
            return true;
        }

        $sm = $this->season_start_month;
        $sd = $this->season_start_day;
        $em = $this->season_end_month;
        $ed = $this->season_end_day;

        if ($sm === null || $sd === null || $em === null || $ed === null) {
            return true;
        }

        $on ??= Carbon::now();
        $start = $sm * 100 + $sd;
        $end = $em * 100 + $ed;
        $cur = $on->month * 100 + $on->day;

        return $start <= $end
            ? ($cur >= $start && $cur <= $end)
            : ($cur >= $start || $cur <= $end);
    }

    /**
     * Products that are in season on the given date (defaults to now): not
     * seasonal, or seasonal with no window, or seasonal with a window that
     * contains the date (wrap-aware). SQL mirror of {@see isInSeason()}.
     */
    public function scopeInSeason(Builder $query, ?CarbonInterface $on = null): void
    {
        $on ??= Carbon::now();
        $cur = $on->month * 100 + $on->day;

        $query->where(function (Builder $q) use ($cur): void {
            $q->where('is_seasonal', false)
                ->orWhereNull('season_start_month')
                ->orWhere(function (Builder $q) use ($cur): void {
                    $start = '(season_start_month * 100 + season_start_day)';
                    $end = '(season_end_month * 100 + season_end_day)';
                    $q->whereNotNull('season_start_day')
                        ->whereNotNull('season_end_month')
                        ->whereNotNull('season_end_day')
                        ->whereRaw(
                            "(($start <= $end AND ? BETWEEN $start AND $end)"
                            ." OR ($start > $end AND (? >= $start OR ? <= $end)))",
                            [$cur, $cur, $cur],
                        );
                });
        });
    }

    /**
     * Human-readable season window for the admin badge, e.g. "1.4.–24.6.".
     * Null when the product is not seasonal or has no window set.
     */
    public function seasonLabel(): ?string
    {
        if (! $this->is_seasonal
            || $this->season_start_month === null || $this->season_start_day === null
            || $this->season_end_month === null || $this->season_end_day === null) {
            return null;
        }

        return sprintf(
            '%d.%d.–%d.%d.',
            $this->season_start_day,
            $this->season_start_month,
            $this->season_end_day,
            $this->season_end_month,
        );
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
