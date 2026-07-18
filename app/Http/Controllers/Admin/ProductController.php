<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Allergen;
use App\Enums\CustomerType;
use App\Enums\DietaryTag;
use App\Enums\ProductCategory;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Enums\TasteProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductPriceHistory;
use App\Models\ProductPriceRule;
use App\Models\ProductProducerVariantPrice;
use App\Models\ProductStockInflow;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\ShopOrderItem;
use App\Services\Admin\PriceHistoryRecorder;
use App\Services\ImageProcessor;
use App\Support\AuditLogger;
use App\Support\ProducerLabels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return ['de', 'en', 'fr'];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'in:'.implode(',', ProductCategory::values())],
            'published' => ['nullable', 'string', Rule::in(['0', '1'])],
            'available' => ['nullable', 'string', Rule::in(['0', '1'])],
        ]);

        $locale = app()->getLocale();
        $query = Product::query()
            ->select('products.*')
            ->join('product_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->leftJoin('producers', 'producers.id', '=', 'products.producer_id')
            ->leftJoin('producer_translations as prt', function ($join) use ($locale): void {
                $join->on('prt.producer_id', '=', 'producers.id')->where('prt.locale', '=', $locale);
            })
            ->with(['producer.translations', 'translations', 'group']);

        $search = isset($validated['q']) ? trim($validated['q']) : '';
        if ($search !== '') {
            $escaped = addcslashes($search, '%_\\');
            $query->where(function ($q) use ($escaped): void {
                $q->where('pt.name', 'like', '%'.$escaped.'%')
                    ->orWhereHas('producer', function ($pq) use ($escaped): void {
                        $pq->whereHas('translations', function ($tq) use ($escaped): void {
                            $tq->where('locale', app()->getLocale())->where('name', 'like', '%'.$escaped.'%');
                        });
                    });
            });
        }

        $categoryFilter = $validated['category'] ?? null;
        if ($categoryFilter instanceof ProductCategory) {
            $query->where('category', $categoryFilter->value);
        }

        if (isset($validated['published'])) {
            $query->where('is_published', $validated['published'] === '1');
        }

        if (isset($validated['available'])) {
            $query->where('is_available', $validated['available'] === '1');
        }

        $products = $query
            ->orderBy('pt.name')
            ->paginate(24)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'groups' => ProductGroup::query()->orderBy('position')->orderBy('id')->get(),
            'categories' => ProductCategory::casesSortedByLabel(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        // E4 (UX notes): the producer-list "0 — Add product" link sends
        // `?producer_id=...` so the operator lands on the create form
        // with the right producer pre-selected. Surface that as a flash
        // value the form blade picks up via `old()`.
        $preselectProducerId = $request->integer('producer_id');
        if ($preselectProducerId > 0) {
            $request->session()->flashInput(['producer_ids' => [$preselectProducerId]]);
        }

        return view('admin.products.create', $this->formViewData(null));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();
        $payload = $this->buildCoreAttributes($validated);
        $translations = $validated['translations'];

        $product = DB::transaction(function () use ($payload, $validated, $request): Product {
            $product = Product::query()->create($payload);
            $product->syncProducers(array_map('intval', $validated['producer_ids']));
            $this->syncProducerBuyingPrices($product, $validated);

            if ($request->hasFile('image')) {
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            $this->syncPairings($product, $validated['pairings'] ?? []);

            $this->syncBusinessPrice($product, $validated, $request->user()?->id);
            $this->syncGroup($product, $validated);
            $this->syncVariants($product, $validated, $request->user()?->id);
            $this->syncQuantityTiers($product, $validated, $request->user()?->id);

            if ($request->hasFile('gallery_images')) {
                $this->storeGalleryUploads($product, $request->file('gallery_images'));
            }

            return $product->fresh();
        });

        DB::transaction(function () use ($product, $translations): void {
            foreach ($this->locales() as $locale) {
                // EN/FR are optional: a locale without a name is not stored
                // (an existing row is removed), so the storefront falls back
                // to the German texts via Product::translationFor().
                $row = (array) ($translations[$locale] ?? []);
                if ($locale !== 'de' && trim((string) ($row['name'] ?? '')) === '') {
                    ProductTranslation::query()
                        ->where('product_id', $product->id)
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => (string) ($row['name'] ?? ''),
                        'origin' => (string) ($row['origin'] ?? ''),
                        'variety' => (string) ($row['variety'] ?? ''),
                        'taste_profile' => (string) ($row['taste_profile'] ?? ''),
                        'short_description' => (string) ($row['short_description'] ?? ''),
                    ]
                );
            }
        });

        app(PriceHistoryRecorder::class)->record($product->fresh()->load('variants', 'producers'));

        // Redirect to the index for clearer "X created" feedback (matching the
        // producer-create flow); the inline edit link in the flash gets the
        // admin straight back to the form if they want to keep editing.
        return redirect()
            ->route('app.admin.products.index')
            ->with('status', __('Product “:name” created.', ['name' => $product->fresh()->name ?? '']))
            ->with('flash_edit_url', route('app.admin.products.edit', $product));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.edit', $this->formViewData($product));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $payload = $this->buildCoreAttributes($validated);
        $translations = $validated['translations'];

        if ($request->boolean('remove_image')) {
            $this->deletePublicImage($product->image_path);
            $payload['image_path'] = null;
        }

        DB::transaction(function () use ($request, $product, $payload, $validated): void {
            $product->update($payload);
            $product->syncProducers(array_map('intval', $validated['producer_ids']));
            $this->syncProducerBuyingPrices($product, $validated);

            if ($request->hasFile('image')) {
                $this->deletePublicImage($product->refresh()->image_path);
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            $this->syncPairings($product, $validated['pairings'] ?? []);

            $this->syncBusinessPrice($product, $validated, $request->user()?->id);
            $this->syncGroup($product, $validated);
            $this->syncVariants($product, $validated, $request->user()?->id);
            $this->syncQuantityTiers($product, $validated, $request->user()?->id);

            $this->removeGalleryImages($validated['gallery_remove'] ?? [], $product);

            $this->updateGalleryAlts($validated['gallery_alt'] ?? [], $product);

            if ($request->hasFile('gallery_images')) {
                $this->storeGalleryUploads($product, $request->file('gallery_images'));
            }
        });

        DB::transaction(function () use ($product, $translations): void {
            foreach ($this->locales() as $locale) {
                // EN/FR are optional: a locale without a name is not stored
                // (an existing row is removed), so the storefront falls back
                // to the German texts via Product::translationFor().
                $row = (array) ($translations[$locale] ?? []);
                if ($locale !== 'de' && trim((string) ($row['name'] ?? '')) === '') {
                    ProductTranslation::query()
                        ->where('product_id', $product->id)
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => (string) ($row['name'] ?? ''),
                        'origin' => (string) ($row['origin'] ?? ''),
                        'variety' => (string) ($row['variety'] ?? ''),
                        'taste_profile' => (string) ($row['taste_profile'] ?? ''),
                        'short_description' => (string) ($row['short_description'] ?? ''),
                    ]
                );
            }
        });

        app(PriceHistoryRecorder::class)->record($product->fresh()->load('variants', 'producers'));

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('Product updated.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->boxItems()->exists()) {
            return redirect()
                ->route('app.admin.products.index')
                ->with('error', __('This product cannot be deleted because it appears on one or more boxes.'));
        }

        $this->deletePublicImage($product->image_path);
        foreach ($product->images as $image) {
            $this->deletePublicImage($image->path);
        }
        $product->delete();

        return redirect()
            ->route('app.admin.products.index')
            ->with('status', __('Product deleted.'));
    }

    /**
     * View data shared by create + edit forms.
     *
     * @return array<string, mixed>
     */
    private function formViewData(?Product $product): array
    {
        $locale = app()->getLocale();

        $pairableProducts = Product::query()
            ->select('products.id')
            ->join('product_translations as pt', function ($j) use ($locale): void {
                $j->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->when($product !== null, fn ($q) => $q->where('products.id', '!=', $product->id))
            ->orderBy('pt.name')
            ->with('translations')
            ->get();

        return [
            'product' => $product?->load(['producer', 'producers', 'producerVariantPrices', 'translations', 'images', 'pairings.translations']),
            'groups' => ProductGroup::query()->orderBy('position')->orderBy('id')->get(),
            // Duplicate-name advisory data for the CREATE form only: every
            // existing product's names (all locales), farm label and edit
            // URL, so typing a known name suggests extending that product
            // instead of creating a twin.
            // Merge targets for the EDIT page's danger zone: every other
            // product this one could be merged into (P6).
            'mergeTargets' => $product === null ? null : Product::query()
                ->where('id', '!=', $product->id)
                ->with(['translations', 'producers.translations', 'producer.translations'])
                ->get()
                ->map(fn (Product $p): array => [
                    'id' => $p->id,
                    'name' => (string) $p->name,
                    'producer' => ProducerLabels::label(
                        $p->producers->isNotEmpty() ? $p->producers : collect([$p->producer])->filter()
                    ),
                ])->values()->all(),
            'existingProductNames' => $product !== null ? null : Product::query()
                ->with(['translations', 'producers.translations', 'producer.translations'])
                ->get()
                ->map(fn (Product $p): array => [
                    'id' => $p->id,
                    'names' => collect(['de', 'en', 'fr'])->mapWithKeys(
                        fn (string $l): array => [$l => (string) ($p->translations->firstWhere('locale', $l)?->name ?? '')]
                    )->all(),
                    'producer' => ProducerLabels::label(
                        $p->producers->isNotEmpty() ? $p->producers : collect([$p->producer])->filter()
                    ),
                    'url' => route('app.admin.products.edit', $p),
                ])->values()->all(),
            'producers' => Producer::query()
                ->select('producers.*')
                ->join('producer_translations as pt', function ($join) use ($locale): void {
                    $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', $locale);
                })
                ->orderBy('pt.name')
                ->get(),
            'categories' => ProductCategory::valuesSortedByLabel(),
            'allergenCases' => Allergen::cases(),
            'dietaryCases' => DietaryTag::cases(),
            'tasteCases' => TasteProfile::cases(),
            'pairableProducts' => $pairableProducts,
            // Customer-group (business) price + grades, edited inline on the
            // product form. Both prices are shown in the display unit (€/kg).
            'businessPrice' => $product !== null ? $this->businessDisplayPriceFor($product, null) : null,
            'existingVariants' => $product !== null
                ? $product->variants()->get()->map(fn (ProductVariant $v): array => [
                    'id' => $v->id,
                    'name' => (array) $v->name,
                    'label' => $v->localizedName(),
                    'price' => $v->unit_price !== null
                        ? round((float) $v->unit_price * $this->displayFactor($product), 2)
                        : null,
                    'business_price' => $this->businessDisplayPriceFor($product, $v->id),
                    'is_active' => (bool) $v->is_active,
                    'tiers' => $this->tierRowsFor($product, $v->id),
                ])->all()
                : [],
            // Default-grade quantity tiers (volume discounts), in the display
            // unit (threshold = kg, price = €/kg).
            'existingTiers' => $product !== null ? $this->tierRowsFor($product, null) : [],
        ];
    }

    /** Canonical units per display unit (1000 for kg/L, 1 for piece/g/…). */
    private function displayFactor(Product $product): int
    {
        if ($product->unit === null) {
            return 1;
        }
        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
        $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);

        return $factor > 0 ? $factor : 1;
    }

    /**
     * The plain business price rule for a product grade (no date/qty scope).
     * $variantId null = the default grade (the product itself).
     */
    private function businessRuleFor(Product $product, ?int $variantId): ?ProductPriceRule
    {
        return $product->priceRules()
            ->where('customer_type', CustomerType::Business->value)
            ->whereNull('starts_on')
            ->whereNull('ends_on')
            ->whereNull('min_quantity')
            ->when($variantId === null, fn ($q) => $q->whereNull('variant_id'), fn ($q) => $q->where('variant_id', $variantId))
            ->first();
    }

    /** A grade's business price in the display unit (€/kg), or null when unset. */
    private function businessDisplayPriceFor(Product $product, ?int $variantId): ?float
    {
        $rule = $this->businessRuleFor($product, $variantId);

        return $rule === null ? null : round((float) $rule->unit_price * $this->displayFactor($product), 2);
    }

    /**
     * Set/clear a grade's plain business price. $raw is a display-unit price;
     * empty clears it. $variantId null = the default grade.
     */
    private function upsertBusinessPrice(Product $product, ?int $variantId, mixed $raw, float $factor, ?int $userId): void
    {
        $existing = $this->businessRuleFor($product, $variantId);

        if ($raw === null || $raw === '') {
            $existing?->delete();

            return;
        }

        $canonical = round((float) $raw / $factor, 4);

        if ($existing !== null) {
            $existing->update(['unit_price' => $canonical, 'is_active' => true]);

            return;
        }

        $product->priceRules()->create([
            'variant_id' => $variantId,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => $canonical,
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Persist the optional customer-group (business) price from the product
     * form. An empty value clears it; a value (display unit) is stored as a
     * single plain business {@see ProductPriceRule} (canonical units).
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncBusinessPrice(Product $product, array $validated, ?int $userId): void
    {
        // Product-level field = the default grade's business price.
        $this->upsertBusinessPrice($product, null, $validated['business_price'] ?? null, $this->displayFactor($product), $userId);
    }

    /**
     * Upsert the product's grades from the form's repeater and delete any the
     * admin removed. Rows with no name in any locale are ignored. Prices are
     * entered in the display unit; an empty price inherits the base price.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncVariants(Product $product, array $validated, ?int $userId): void
    {
        // Absent `variants` means the admin removed every grade (the form sends
        // no variant inputs then). Treat it as an empty set — NOT a no-op — so
        // removing the last grade actually deletes it.
        $rows = is_array($validated['variants'] ?? null) ? $validated['variants'] : [];

        // Grade-level farm prices ride along the rows. Rows of farms no
        // longer selected are dropped here (the producer pivot carries no
        // FK into the price table); deleted grades cascade via variant_id.
        $farmIds = array_map('intval', (array) ($validated['producer_ids'] ?? []));
        ProductProducerVariantPrice::query()
            ->where('product_id', $product->id)
            ->whereNotIn('producer_id', $farmIds ?: [0])
            ->delete();

        $factor = $this->displayFactor($product);
        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $name = $this->cleanVariantName((array) ($row['name'] ?? []));
            if ($name === []) {
                continue; // blank row
            }

            $price = ($row['unit_price'] ?? null) === null || $row['unit_price'] === ''
                ? null
                : round((float) $row['unit_price'] / $factor, 4);

            $attributes = [
                'name' => $name,
                'unit_price' => $price,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'position' => (int) ($row['position'] ?? $index),
            ];

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $variant = $id !== null ? $product->variants()->whereKey($id)->first() : null;

            if ($variant !== null) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;

            // Each grade carries its own optional business price + volume
            // tiers + per-farm buying prices.
            $this->upsertBusinessPrice($product, $variant->id, $row['business_price'] ?? null, $factor, $userId);
            $this->syncTiersFor($product, $variant->id, (array) ($row['tiers'] ?? []), $userId);
            $this->syncGradeFarmPrices($product, $variant, (array) ($row['producer_buying_prices'] ?? []), $farmIds);
        }

        // Remove grades the admin deleted from the form, plus any price rules
        // scoped to them (the variant_id column isn't a DB-level FK).
        $removedIds = $product->variants()->whereNotIn('id', $keptIds ?: [0])->pluck('id');
        if ($removedIds->isNotEmpty()) {
            $product->priceRules()->whereIn('variant_id', $removedIds)->delete();
            $product->variants()->whereKey($removedIds)->delete();
        }
    }

    /**
     * Quantity-tier rules (volume discounts) for one grade: min_quantity set,
     * no date/customer scope. $variantId null = the default grade.
     *
     * @return Collection<int, ProductPriceRule>
     */
    private function quantityTierRules(Product $product, ?int $variantId = null): Collection
    {
        return $product->priceRules()
            ->whereNull('customer_type')
            ->whereNull('starts_on')
            ->whereNull('ends_on')
            ->whereNotNull('min_quantity')
            ->when($variantId === null, fn ($q) => $q->whereNull('variant_id'), fn ($q) => $q->where('variant_id', $variantId))
            ->orderBy('min_quantity')
            ->get();
    }

    /** Display-unit tier rows for a grade, for prefilling the form. */
    private function tierRowsFor(Product $product, ?int $variantId): array
    {
        $factor = $this->displayFactor($product);

        return $this->quantityTierRules($product, $variantId)->map(fn (ProductPriceRule $r): array => [
            'id' => $r->id,
            'min_quantity' => round((float) $r->min_quantity / $factor, 3),
            'unit_price' => round((float) $r->unit_price * $factor, 2),
        ])->all();
    }

    /** Default-grade quantity tiers from the top-level form section. */
    private function syncQuantityTiers(Product $product, array $validated, ?int $userId): void
    {
        $this->syncTiersFor($product, null, (array) ($validated['tiers'] ?? []), $userId);
    }

    /**
     * Upsert one grade's quantity tiers and delete removed rows. Threshold +
     * price are entered in the display unit; stored canonical. $variantId null
     * = the default grade.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTiersFor(Product $product, ?int $variantId, array $rows, ?int $userId): void
    {
        $factor = $this->displayFactor($product);
        $keptIds = [];

        foreach ($rows as $row) {
            $minRaw = $row['min_quantity'] ?? null;
            $priceRaw = $row['unit_price'] ?? null;
            if ($minRaw === null || $minRaw === '' || $priceRaw === null || $priceRaw === '') {
                continue; // incomplete row
            }

            $attributes = [
                'variant_id' => $variantId,
                'min_quantity' => round((float) $minRaw * $factor, 4),
                'unit_price' => round((float) $priceRaw / $factor, 4),
                'is_active' => true,
                'created_by_user_id' => $userId,
            ];

            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $tier = $id !== null
                ? $product->priceRules()->whereKey($id)->whereNotNull('min_quantity')->first()
                : null;

            if ($tier !== null) {
                $tier->update($attributes);
            } else {
                $tier = $product->priceRules()->create($attributes);
            }

            $keptIds[] = $tier->id;
        }

        // Remove tiers the admin deleted from this grade's scope.
        $product->priceRules()
            ->whereNull('customer_type')
            ->whereNull('starts_on')
            ->whereNull('ends_on')
            ->whereNotNull('min_quantity')
            ->when($variantId === null, fn ($q) => $q->whereNull('variant_id'), fn ($q) => $q->where('variant_id', $variantId))
            ->whereNotIn('id', $keptIds ?: [0])
            ->delete();
    }

    /**
     * Keep only non-empty locale strings for a grade name.
     *
     * @param  array<string, mixed>  $name
     * @return array<string, string>
     */
    private function cleanVariantName(array $name): array
    {
        $clean = [];
        foreach (['de', 'en', 'fr'] as $locale) {
            $value = isset($name[$locale]) ? trim((string) $name[$locale]) : '';
            if ($value !== '') {
                $clean[$locale] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildCoreAttributes(array $validated): array
    {
        // The admin enters a price in their chosen `price_display_unit`
        // (€/g, €/kg, €/ml, €/L, €/piece, €/bunch). Storage is always
        // per *canonical* unit of the kind, so divide by the factor
        // (1000 for kg/L, 1 for g/ml/piece/bunch).
        $unitEnum = ProductUnit::from($validated['unit']);
        $displayUnit = $validated['price_display_unit'];
        $canonicalUnitPrice = round(
            ((float) $validated['price']) / $unitEnum->canonicalUnitsPerDisplay($displayUnit),
            4,
        );

        $payload = [
            'producer_id' => (int) $validated['producer_ids'][0],
            'category' => $validated['category'],
            'subcategory_id' => isset($validated['subcategory_id']) && (int) $validated['subcategory_id'] > 0
                ? (int) $validated['subcategory_id']
                : null,
            'unit' => $validated['unit'],
            'price_display_unit' => $displayUnit,
            'unit_price' => $canonicalUnitPrice,
            'vat_rate_percent' => (int) ($validated['vat_rate_percent'] ?? 7),
            'default_freshness_window_days' => isset($validated['default_freshness_window_days'])
                ? max(1, (int) $validated['default_freshness_window_days'])
                : 1,
            'is_seasonal' => (bool) ($validated['is_seasonal'] ?? false),
            'request_visibility' => in_array($validated['request_visibility'] ?? 'auto', ['auto', 'always', 'never'], true)
                ? ($validated['request_visibility'] ?? 'auto')
                : 'auto',
            // The form sets the PERMANENT state; time-bound overrides are
            // managed on the Anfrage-Sortiment page and reset here.
            'request_visibility_until' => null,
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'is_test' => (bool) ($validated['is_test'] ?? false),
            'allergens' => $this->normalizeEnumArray($validated['allergens'] ?? null),
            'dietary_tags' => $this->normalizeEnumArray($validated['dietary_tags'] ?? null),
            'taste_profile_tags' => $this->normalizeEnumArray($validated['taste_profile_tags'] ?? null),
            'custom_taste_tags' => $this->normalizeCustomTasteTags($validated['custom_taste_tags'] ?? null),
        ];

        $buyingPrice = $validated['buying_price'] ?? null;
        $payload['buying_price_cents'] = ($buyingPrice === null || $buyingPrice === '')
            ? null
            : (int) round(((float) $buyingPrice) * 100);

        // Quantity step: only stored for mass/volume; cleared for piece/bunch.
        $isMeasured = in_array($unitEnum, [ProductUnit::Mass, ProductUnit::Volume], true);
        $payload['quantity_step'] = $isMeasured && isset($validated['quantity_step']) && $validated['quantity_step'] !== ''
            ? (int) $validated['quantity_step']
            : null;

        // Unit descriptors: only stored for piece/bunch; cleared for mass/volume.
        $isCount = in_array($unitEnum, [ProductUnit::Piece, ProductUnit::Bunch], true);
        $payload['unit_weight_grams'] = $isCount && isset($validated['unit_weight_grams']) && $validated['unit_weight_grams'] !== ''
            ? (int) $validated['unit_weight_grams']
            : null;
        $payload['unit_volume_ml'] = $isCount && isset($validated['unit_volume_ml']) && $validated['unit_volume_ml'] !== ''
            ? (int) $validated['unit_volume_ml']
            : null;

        // Display word ("Topf", "Beutel"): only stored for the count kinds
        // (piece/bunch); cleared for every other kind so a switched product
        // loses the stale word.
        $payload['count_unit_label'] = $isCount
            && ($validated['count_unit_label'] ?? '') !== ''
            ? $validated['count_unit_label']
            : null;

        // Recurring yearly season window. Only persisted when the product is
        // flagged seasonal; cleared otherwise so a toggled-off product doesn't
        // keep a stale window.
        $seasonFields = ['season_start_month', 'season_start_day', 'season_end_month', 'season_end_day'];
        foreach ($seasonFields as $field) {
            $payload[$field] = $payload['is_seasonal'] && isset($validated[$field]) && $validated[$field] !== ''
                ? (int) $validated[$field]
                : null;
        }

        return $payload;
    }

    /**
     * @param  list<string>|null  $values
     * @return list<string>|null
     */
    private function normalizeEnumArray(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        $values = array_values(array_unique(array_filter(
            $values,
            static fn ($v) => is_string($v) && $v !== ''
        )));

        return $values === [] ? null : $values;
    }

    /**
     * Normalize the trilingual custom taste tags into a clean list of
     * `{de, en, fr}` rows. Trims each label and drops any entry that lost
     * one of its three locales. Returns null when nothing usable remains.
     *
     * @param  array<int, mixed>|null  $entries
     * @return list<array{de:string, en:string, fr:string}>|null
     */
    private function normalizeCustomTasteTags(?array $entries): ?array
    {
        if ($entries === null) {
            return null;
        }

        $clean = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $de = trim((string) ($entry['de'] ?? ''));
            $en = trim((string) ($entry['en'] ?? ''));
            $fr = trim((string) ($entry['fr'] ?? ''));

            if ($de === '' || $en === '' || $fr === '') {
                continue;
            }

            $clean[] = ['de' => $de, 'en' => $en, 'fr' => $fr];
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * @param  list<int>  $pairedIds
     */
    /**
     * Pairings are RECIPROCAL: "A pairs well with B" implies "B pairs well
     * with A", so every change here maintains the inverse rows too — adding
     * a partner adds the mirror, removing one removes the mirror.
     */
    private function syncPairings(Product $product, array $pairedIds): void
    {
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $pairedIds),
            static fn (int $id) => $id > 0 && $id !== $product->id
        )));

        $before = $product->pairings()->pluck('products.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $product->pairings()->sync($clean);

        $added = array_values(array_diff($clean, $before));
        $removed = array_values(array_diff($before, $clean));

        if ($added !== []) {
            $now = now();
            DB::table('product_pairings')->insertOrIgnore(array_map(
                static fn (int $id): array => [
                    'product_id' => $id,
                    'paired_product_id' => $product->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $added,
            ));
        }

        if ($removed !== []) {
            DB::table('product_pairings')
                ->whereIn('product_id', $removed)
                ->where('paired_product_id', $product->id)
                ->delete();
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storeGalleryUploads(Product $product, array $files): void
    {
        $nextPosition = ((int) $product->images()->max('position')) + 1;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = app(ImageProcessor::class)->storeUpload($file, 'products/'.$product->id);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'position' => $nextPosition++,
                'is_primary' => false,
            ]);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function removeGalleryImages(array $ids, Product $product): void
    {
        if ($ids === []) {
            return;
        }

        $images = ProductImage::query()
            ->whereIn('id', $ids)
            ->where('product_id', $product->id)
            ->get();

        foreach ($images as $image) {
            $this->deletePublicImage($image->path);
            $image->delete();
        }
    }

    /**
     * @param  array<int|string, string|null>  $alts
     */
    private function updateGalleryAlts(array $alts, Product $product): void
    {
        if ($alts === []) {
            return;
        }

        $normalized = [];
        foreach ($alts as $id => $alt) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $normalized[$id] = is_string($alt) && $alt !== '' ? $alt : null;
        }

        if ($normalized === []) {
            return;
        }

        $images = ProductImage::query()
            ->whereIn('id', array_keys($normalized))
            ->where('product_id', $product->id)
            ->get();

        foreach ($images as $image) {
            $image->update(['alt' => $normalized[$image->id] ?? null]);
        }
    }

    private function storeUploadedImage(UploadedFile $file, Product $product): string
    {
        return app(ImageProcessor::class)->storeUpload($file, 'products/'.$product->id);
    }

    /**
     * Persist per-farm buying prices onto the producer pivot. Runs right
     * after syncProducers(), so a pivot row exists for every selected
     * farm. Empty input clears the override (the default applies again).
     *
     * @param  array<string, mixed>  $validated
     */
    /**
     * Buying price overrides per (farm, grade) for ONE grade row of the
     * form (works at creation too: the row identifies the grade, not a
     * pre-existing id). Empty input clears the override.
     *
     * @param  array<int|string, mixed>  $prices  [producerId => euros]
     * @param  list<int>  $farmIds
     */
    private function syncGradeFarmPrices(Product $product, ProductVariant $variant, array $prices, array $farmIds): void
    {
        foreach ($farmIds as $farmId) {
            $raw = $prices[$farmId] ?? null;
            if ($raw === null || $raw === '') {
                ProductProducerVariantPrice::query()
                    ->where('product_id', $product->id)
                    ->where('producer_id', $farmId)
                    ->where('variant_id', $variant->id)
                    ->delete();

                continue;
            }
            ProductProducerVariantPrice::query()->updateOrCreate(
                ['product_id' => $product->id, 'producer_id' => $farmId, 'variant_id' => $variant->id],
                ['buying_price_cents' => (int) round(((float) $raw) * 100)],
            );
        }
    }

    private function syncProducerBuyingPrices(Product $product, array $validated): void
    {
        $prices = (array) ($validated['producer_buying_prices'] ?? []);
        foreach (array_map('intval', $validated['producer_ids']) as $id) {
            $raw = $prices[$id] ?? null;
            $cents = ($raw === null || $raw === '') ? null : (int) round(((float) $raw) * 100);
            $product->producers()->updateExistingPivot($id, ['buying_price_cents' => $cents]);
        }
    }

    /**
     * Wire the product into its ProductGroup from the form: an existing id,
     * 'new' (create from new_group_name), or empty = ungrouped. A group the
     * product leaves is deleted once it has no members left, so stale empty
     * groups never linger.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncGroup(Product $product, array $validated): void
    {
        if (! array_key_exists('group_id', $validated)) {
            return;
        }

        $previousGroupId = $product->group_id;
        $choice = (string) ($validated['group_id'] ?? '');

        $groupId = null;
        if ($choice === 'new') {
            $name = array_filter(array_map(
                fn ($v) => trim((string) $v),
                (array) ($validated['new_group_name'] ?? []),
            ));
            $groupId = ProductGroup::create(['name' => $name, 'position' => 0])->id;
        } elseif ($choice !== '') {
            $groupId = (int) $choice;
        }

        $label = array_filter(array_map(
            fn ($v) => trim((string) $v),
            (array) ($validated['group_label'] ?? []),
        ));

        $product->forceFill([
            'group_id' => $groupId,
            'group_label' => $groupId !== null && $label !== [] ? $label : null,
            'group_position' => (int) ($validated['group_position'] ?? 0),
        ])->save();

        $this->deleteGroupIfEmpty($previousGroupId, $groupId);
    }

    private function deleteGroupIfEmpty(?int $previousGroupId, ?int $currentGroupId): void
    {
        if ($previousGroupId === null || $previousGroupId === $currentGroupId) {
            return;
        }
        ProductGroup::query()
            ->whereKey($previousGroupId)
            ->whereDoesntHave('products')
            ->delete();
    }

    /**
     * Bulk action from the products index: put the selected products into a
     * group (existing or freshly named) so duplicates from different farms
     * become one card. New members queue behind existing ones.
     */
    public function assignGroup(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'group_id' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== 'new' && ! ProductGroup::query()->whereKey((int) $value)->exists()) {
                    $fail(__('admin.products.group_invalid'));
                }
            }],
            'new_group_name' => ['nullable', 'array'],
            'new_group_name.de' => ['required_if:group_id,new', 'nullable', 'string', 'max:120'],
            'new_group_name.en' => ['nullable', 'string', 'max:120'],
            'new_group_name.fr' => ['nullable', 'string', 'max:120'],
        ]);

        $group = $validated['group_id'] === 'new'
            ? ProductGroup::create(['name' => array_filter(array_map(fn ($v) => trim((string) $v), (array) ($validated['new_group_name'] ?? []))), 'position' => 0])
            : ProductGroup::query()->findOrFail((int) $validated['group_id']);

        $position = (int) (Product::query()->where('group_id', $group->id)->max('group_position') ?? -1);
        $products = Product::query()->findMany($validated['product_ids']);

        foreach ($validated['product_ids'] as $productId) {
            $product = $products->firstWhere('id', (int) $productId);
            if ($product === null || $product->group_id === $group->id) {
                continue;
            }
            $previousGroupId = $product->group_id;
            $product->forceFill([
                'group_id' => $group->id,
                'group_position' => ++$position,
            ])->save();
            $this->deleteGroupIfEmpty($previousGroupId, $group->id);
        }

        AuditLogger::log(
            entityType: 'product_group',
            entityId: (int) $group->id,
            action: 'product_group_assign',
            metadata: ['channel' => 'admin', 'product_ids' => array_map('intval', $validated['product_ids'])],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.products.index')
            ->with('status', __('admin.products.group_assign_flash', [
                'name' => $group->localizedName(),
                'count' => count($validated['product_ids']),
            ]));
    }

    private function deletePublicImage(?string $path): void
    {
        ImageProcessor::deletePublicImage($path);
    }

    /**
     * Price development page: a step chart of the recorded list prices
     * (B2C gross, B2B net, buying per farm), one grade at a time. The
     * chart is a server-rendered SVG; coordinates are precomputed here so
     * the Blade stays presentation-only.
     */
    public function priceHistory(Request $request, Product $product): View
    {
        $product->load('variants', 'producers.translations');

        // Grade selector: null = the base product; only grades that have
        // history rows of their own appear.
        $variantId = $request->integer('variant') ?: null;
        $variantIdsWithHistory = ProductPriceHistory::query()
            ->where('product_id', $product->id)->whereNotNull('variant_id')
            ->distinct()->pluck('variant_id')->all();

        $rows = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->where(fn ($q) => $variantId === null ? $q->whereNull('variant_id') : $q->where('variant_id', $variantId))
            ->orderBy('recorded_at')->orderBy('id')
            ->get();

        $producerNames = $product->producers->keyBy('id')->map(fn ($p) => (string) $p->name);
        $seriesLabel = function (ProductPriceHistory $h) use ($producerNames): string {
            return match ($h->kind) {
                ProductPriceHistory::KIND_BASE_GROSS => __('admin.price_history.series_base'),
                ProductPriceHistory::KIND_BUSINESS_NET => __('admin.price_history.series_business'),
                default => trim(__('admin.price_history.series_buying').' '.($h->producer_id !== null ? ($producerNames[$h->producer_id] ?? '#'.$h->producer_id) : '')),
            };
        };

        // Group into series and extend each one to "now" (step chart:
        // the last price is still valid today).
        $palette = ['#047857', '#1d4ed8', '#b45309', '#9333ea', '#0e7490', '#be123c', '#4d7c0f', '#6b7280'];
        $series = [];
        foreach ($rows as $row) {
            $key = $row->kind.'|'.($row->producer_id ?? 0);
            $series[$key]['label'] ??= $seriesLabel($row);
            $series[$key]['kind'] ??= $row->kind;
            $series[$key]['points'][] = ['t' => $row->recorded_at->getTimestamp(), 'v' => (float) $row->price, 'date' => $row->recorded_at->format('d.m.Y H:i')];
        }
        $series = array_values($series);
        foreach ($series as $i => &$s) {
            $s['color'] = $palette[$i % count($palette)];
            $last = end($s['points']);
            if ($last['t'] < now()->getTimestamp()) {
                $s['points'][] = ['t' => now()->getTimestamp(), 'v' => $last['v'], 'date' => null];
            }
        }
        unset($s);

        // Scale to SVG space (step lines; x = time, y = € per display unit).
        $allT = collect($series)->flatMap(fn ($s) => array_column($s['points'], 't'));
        $allV = collect($series)->flatMap(fn ($s) => array_column($s['points'], 'v'));
        $chart = null;
        if ($allT->isNotEmpty()) {
            $w = 720;
            $h = 260;
            $padL = 56;
            $padR = 16;
            $padT = 12;
            $padB = 28;
            $t0 = (int) $allT->min();
            $t1 = max((int) $allT->max(), $t0 + 1);
            $vMax = max((float) $allV->max() * 1.1, 0.01);
            $sx = fn (int $t): float => round($padL + ($t - $t0) / ($t1 - $t0) * ($w - $padL - $padR), 1);
            $sy = fn (float $v): float => round($h - $padB - ($v / $vMax) * ($h - $padT - $padB), 1);

            foreach ($series as &$s) {
                $path = '';
                $prevY = null;
                foreach ($s['points'] as $j => $p) {
                    $x = $sx($p['t']);
                    $y = $sy($p['v']);
                    $path .= $j === 0 ? "M{$x},{$y}" : "H{$x}V{$y}";
                    $s['points'][$j]['x'] = $x;
                    $s['points'][$j]['y'] = $y;
                }
                $s['path'] = $path;
            }
            unset($s);

            // 4 horizontal grid lines + a handful of date ticks.
            $yTicks = [];
            for ($i = 0; $i <= 4; $i++) {
                $v = $vMax / 4 * $i;
                $yTicks[] = ['y' => $sy($v), 'label' => number_format($v, 2, ',', '.')];
            }
            $xTicks = [];
            $tickCount = min(5, max(2, (int) floor(($t1 - $t0) / 86400) + 1));
            for ($i = 0; $i < $tickCount; $i++) {
                $t = (int) round($t0 + ($t1 - $t0) / max(1, $tickCount - 1) * $i);
                $xTicks[] = ['x' => $sx($t), 'label' => date('d.m.', $t)];
            }

            $chart = compact('w', 'h', 'padL', 'padR', 'padT', 'padB', 'yTicks', 'xTicks');
        }

        $displayUnit = $product->unit !== null
            ? ($product->price_display_unit ?? $product->unit->defaultDisplayUnit())
            : 'piece';

        [$quantityChart, $revenueChart] = $this->weeklyProductCharts($product, $factor ?? $this->displayFactor($product));

        return view('admin.products.price-history', [
            'product' => $product,
            'series' => $series,
            'chart' => $chart,
            'unitLabel' => ProductUnit::displaySymbol($displayUnit),
            'variantId' => $variantId,
            'variantOptions' => $product->variants->whereIn('id', $variantIdsWithHistory)->values(),
            'entries' => $rows->sortByDesc('recorded_at')->take(50)->values(),
            'entryLabel' => $seriesLabel,
            'quantityChart' => $quantityChart,
            'revenueChart' => $revenueChart,
        ]);
    }

    /**
     * Weekly bar charts for the last 12 weeks: goods received (stock
     * inflows) vs. goods sold (finalized orders, mirrors included so B2B
     * counts once), plus gross revenue. Quantities in the display unit;
     * grades are aggregated — this is the whole-product view.
     *
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function weeklyProductCharts(Product $product, int $factor): array
    {
        $weeks = 12;
        $start = now()->startOfWeek()->subWeeks($weeks - 1);

        // Bucket skeleton: ISO week keys in chronological order.
        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $w = $start->copy()->addWeeks($i);
            $buckets[$w->format('o-W')] = ['label' => 'KW'.$w->isoWeek(), 'inflow' => 0.0, 'sold' => 0.0, 'revenue' => 0.0];
        }

        foreach (ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->whereDate('received_on', '>=', $start->toDateString())
            ->get(['received_on', 'quantity']) as $inflow) {
            $key = Carbon::parse($inflow->received_on)->format('o-W');
            if (isset($buckets[$key])) {
                $buckets[$key]['inflow'] += (float) $inflow->quantity / $factor;
            }
        }

        $soldRows = ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->where('shop_order_items.product_id', $product->id)
            ->whereDate('shop_orders.expected_delivery_date', '>=', $start->toDateString())
            // Same definition as ShopOrder::scopeFinalized, column-qualified
            // for the join: paid/fulfilled plus in-person awaiting payment.
            ->where(function ($inner): void {
                $inner->whereIn('shop_orders.status', [
                    ShopOrderStatus::Paid->value,
                    ShopOrderStatus::Fulfilled->value,
                ])->orWhere(function ($pickup): void {
                    $pickup->where('shop_orders.status', ShopOrderStatus::PendingPayment->value)
                        ->where('shop_orders.payment_method', 'in_person');
                });
            })
            ->get(['shop_orders.expected_delivery_date as d', 'shop_order_items.quantity as q', 'shop_order_items.line_total_eur as t']);
        foreach ($soldRows as $row) {
            $key = Carbon::parse($row->d)->format('o-W');
            if (isset($buckets[$key])) {
                $buckets[$key]['sold'] += (float) $row->q / $factor;
                $buckets[$key]['revenue'] += (float) $row->t;
            }
        }

        $buckets = array_values($buckets);
        $hasData = collect($buckets)->contains(fn ($b) => $b['inflow'] > 0 || $b['sold'] > 0 || $b['revenue'] > 0);
        if (! $hasData) {
            return [null, null];
        }

        $qty = $this->barChartGeometry($buckets, [
            ['field' => 'inflow', 'color' => '#047857', 'label' => __('admin.product_stats.series_inflow')],
            ['field' => 'sold', 'color' => '#1d4ed8', 'label' => __('admin.product_stats.series_sold')],
        ]);
        $rev = $this->barChartGeometry($buckets, [
            ['field' => 'revenue', 'color' => '#b45309', 'label' => __('admin.product_stats.series_revenue')],
        ]);

        return [$qty, $rev];
    }

    /**
     * Precomputed SVG geometry for a grouped weekly bar chart, so the
     * Blade partial stays presentation-only.
     *
     * @param  array<int, array<string, mixed>>  $buckets
     * @param  array<int, array{field: string, color: string, label: string}>  $seriesDefs
     * @return array<string, mixed>
     */
    private function barChartGeometry(array $buckets, array $seriesDefs): array
    {
        $w = 720;
        $h = 220;
        $padL = 56;
        $padR = 16;
        $padT = 12;
        $padB = 28;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;
        $groupW = $plotW / max(1, count($buckets));
        $barW = round($groupW * (count($seriesDefs) === 1 ? 0.5 : 0.32), 1);

        $vMax = 0.0;
        foreach ($buckets as $b) {
            foreach ($seriesDefs as $def) {
                $vMax = max($vMax, (float) $b[$def['field']]);
            }
        }
        $vMax = max($vMax * 1.1, 0.01);

        $groups = [];
        foreach ($buckets as $i => $b) {
            $x0 = $padL + $groupW * $i;
            $bars = [];
            foreach ($seriesDefs as $j => $def) {
                $v = (float) $b[$def['field']];
                $height = round($v / $vMax * $plotH, 1);
                $bars[] = [
                    'x' => round($x0 + $groupW / 2 - ($barW * count($seriesDefs)) / 2 + $j * $barW, 1),
                    'y' => round($h - $padB - $height, 1),
                    'w' => $barW,
                    'h' => $height,
                    'color' => $def['color'],
                    'title' => $def['label'].' '.$b['label'].': '.number_format($v, 2, ',', '.'),
                ];
            }
            $groups[] = ['label' => $b['label'], 'labelX' => round($x0 + $groupW / 2, 1), 'bars' => $bars];
        }

        $yTicks = [];
        for ($i = 0; $i <= 4; $i++) {
            $v = $vMax / 4 * $i;
            $yTicks[] = ['y' => round($h - $padB - $v / $vMax * $plotH, 1), 'label' => number_format($v, $vMax >= 20 ? 0 : 1, ',', '.')];
        }

        return [
            'w' => $w, 'h' => $h, 'padL' => $padL, 'padR' => $padR, 'padB' => $padB,
            'groups' => $groups, 'yTicks' => $yTicks,
            'legend' => array_map(fn ($d) => ['color' => $d['color'], 'label' => $d['label']], $seriesDefs),
        ];
    }
}
