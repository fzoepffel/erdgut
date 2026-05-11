<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Allergen;
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
use App\Models\ProductTranslation;
use App\Models\ProductWeekStockSchedule;
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
            // Recurring weekly-stock schedules attached to this product
            // (Phase 2). Empty for new products since they aren't saved yet.
            'schedules' => $product?->id !== null
                ? ProductWeekStockSchedule::query()
                    ->where('product_id', $product->id)
                    ->orderByDesc('starts_on_monday')
                    ->get()
                : collect(),
        ];
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
            'unit' => $validated['unit'],
            'price_display_unit' => $displayUnit,
            'unit_price' => $canonicalUnitPrice,
            'is_seasonal' => (bool) ($validated['is_seasonal'] ?? false),
            'is_available' => (bool) ($validated['is_available'] ?? false),
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'is_test' => (bool) ($validated['is_test'] ?? false),
            'allergens' => $this->normalizeEnumArray($validated['allergens'] ?? null),
            'dietary_tags' => $this->normalizeEnumArray($validated['dietary_tags'] ?? null),
            'taste_profile_tags' => $this->normalizeEnumArray($validated['taste_profile_tags'] ?? null),
        ];

        $buyingPrice = $validated['buying_price'] ?? null;
        $payload['buying_price_cents'] = ($buyingPrice === null || $buyingPrice === '')
            ? null
            : (int) round(((float) $buyingPrice) * 100);

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
     * @param  list<int>  $pairedIds
     */
    private function syncPairings(Product $product, array $pairedIds): void
    {
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $pairedIds),
            static fn (int $id) => $id > 0 && $id !== $product->id
        )));

        $product->pairings()->sync($clean);
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

            $path = Storage::disk('public')->putFile('products/'.$product->id, $file);

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
        return Storage::disk('public')->putFile('products/'.$product->id, $file);
    }

    private function deletePublicImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
