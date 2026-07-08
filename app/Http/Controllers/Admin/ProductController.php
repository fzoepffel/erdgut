<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Allergen;
use App\Enums\CustomerType;
use App\Enums\DietaryTag;
use App\Enums\ProductCategory;
use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPriceRule;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\ImageProcessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            ->with(['producer.translations', 'translations']);

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
            $request->session()->flashInput(['producer_id' => $preselectProducerId]);
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

            if ($request->hasFile('image')) {
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            $this->syncPairings($product, $validated['pairings'] ?? []);

            $this->syncBusinessPrice($product, $validated, $request->user()?->id);
            $this->syncVariants($product, $validated, $request->user()?->id);
            $this->syncQuantityTiers($product, $validated, $request->user()?->id);

            if ($request->hasFile('gallery_images')) {
                $this->storeGalleryUploads($product, $request->file('gallery_images'));
            }

            return $product->fresh();
        });

        DB::transaction(function () use ($product, $translations): void {
            foreach ($this->locales() as $locale) {
                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'origin' => $translations[$locale]['origin'],
                        'variety' => $translations[$locale]['variety'],
                        'taste_profile' => $translations[$locale]['taste_profile'],
                        'short_description' => $translations[$locale]['short_description'],
                    ]
                );
            }
        });

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

            if ($request->hasFile('image')) {
                $this->deletePublicImage($product->refresh()->image_path);
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            $this->syncPairings($product, $validated['pairings'] ?? []);

            $this->syncBusinessPrice($product, $validated, $request->user()?->id);
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
                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'origin' => $translations[$locale]['origin'],
                        'variety' => $translations[$locale]['variety'],
                        'taste_profile' => $translations[$locale]['taste_profile'],
                        'short_description' => $translations[$locale]['short_description'],
                    ]
                );
            }
        });

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
            'product' => $product?->load(['producer', 'translations', 'images', 'pairings.translations']),
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

            // Each grade carries its own optional business price + volume tiers.
            $this->upsertBusinessPrice($product, $variant->id, $row['business_price'] ?? null, $factor, $userId);
            $this->syncTiersFor($product, $variant->id, (array) ($row['tiers'] ?? []), $userId);
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
            'producer_id' => $validated['producer_id'],
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

    private function deletePublicImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk('public');

        // Remove the stored original and its derived thumbnail together.
        foreach ([$path, ImageProcessor::thumbPath($path)] as $target) {
            if ($disk->exists($target)) {
                $disk->delete($target);
            }
        }
    }
}
