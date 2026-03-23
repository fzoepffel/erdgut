<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Producer;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with('producer')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('admin.products.create', [
            'producers' => Producer::query()->orderBy('name')->get(),
            'categories' => ProductCategory::values(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $payload = $this->normalizedPayload($this->validatedProductAttributes($request->validated(), ['image']));

        $product = DB::transaction(function () use ($payload, $request): Product {
            $product = Product::query()->create($payload);

            if ($request->hasFile('image')) {
                $path = $this->storeUploadedImage($request->file('image'), $product);
                $product->update(['image_path' => $path]);
            }

            return $product->fresh();
        });

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('Product created.'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('admin.products.edit', [
            'product' => $product->load('producer'),
            'producers' => Producer::query()->orderBy('name')->get(),
            'categories' => ProductCategory::values(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $payload = $this->normalizedPayload($this->validatedProductAttributes($request->validated(), ['image', 'remove_image']));

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
