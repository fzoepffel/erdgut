<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductTranslation;
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
            'categories' => ProductCategory::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', [
            'producers' => Producer::query()
                ->select('producers.*')
                ->join('producer_translations as pt', function ($join): void {
                    $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', app()->getLocale());
                })
                ->orderBy('pt.name')
                ->get(),
            'categories' => ProductCategory::values(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();
        $payload = $this->normalizedPayload($this->validatedProductAttributes($validated, ['image', 'translations']));
        $translations = $validated['translations'];

        $product = DB::transaction(function () use ($payload, $request): Product {
            $product = Product::query()->create($payload);

            if ($request->hasFile('image')) {
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            return $product->fresh();
        });

        DB::transaction(function () use ($product, $translations): void {
            foreach ($this->locales() as $locale) {
                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'unit' => $translations[$locale]['unit'],
                        'origin' => $translations[$locale]['origin'],
                        'variety' => $translations[$locale]['variety'],
                        'taste_profile' => $translations[$locale]['taste_profile'],
                    ]
                );
            }
        });

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('Product created.'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.edit', [
            'product' => $product->load(['producer', 'translations']),
            'producers' => Producer::query()
                ->select('producers.*')
                ->join('producer_translations as pt', function ($join): void {
                    $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', app()->getLocale());
                })
                ->orderBy('pt.name')
                ->get(),
            'categories' => ProductCategory::values(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $payload = $this->normalizedPayload($this->validatedProductAttributes($validated, ['image', 'remove_image', 'translations']));
        $translations = $validated['translations'];

        if ($request->boolean('remove_image')) {
            $this->deletePublicImage($product->image_path);
            $payload['image_path'] = null;
        }

        DB::transaction(function () use ($request, $product, $payload): void {
            $product->update($payload);

            if ($request->hasFile('image')) {
                $this->deletePublicImage($product->refresh()->image_path);
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }
        });

        DB::transaction(function () use ($product, $translations): void {
            foreach ($this->locales() as $locale) {
                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $product->id, 'locale' => $locale],
                    [
                        'name' => $translations[$locale]['name'],
                        'unit' => $translations[$locale]['unit'],
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
        $product->delete();

        return redirect()
            ->route('app.admin.products.index')
            ->with('status', __('Product deleted.'));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $without
     * @return array<string, mixed>
     */
    private function validatedProductAttributes(array $validated, array $without): array
    {
        foreach ($without as $key) {
            unset($validated[$key]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload): array
    {
        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $payload['metadata'] = array_filter(
                $payload['metadata'],
                static fn (mixed $v): bool => $v !== null && $v !== ''
            );
        }

        return $payload;
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
