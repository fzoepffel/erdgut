<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Models\ProductSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubcategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin');

        $subcategories = ProductSubcategory::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('label_de')
            ->get()
            ->groupBy('category');

        return view('admin.subcategories.index', [
            'subcategories' => $subcategories,
            'categoryLabels' => $this->categoryLabels(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('admin');

        return view('admin.subcategories.create', [
            'categories' => ProductCategory::valuesSortedByLabel(),
            'categoryLabels' => $this->categoryLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate($this->rules());

        ProductSubcategory::query()->create($validated);

        return redirect()
            ->route('app.admin.subcategories.index')
            ->with('status', __('admin.subcategories.created'));
    }

    public function edit(ProductSubcategory $subcategory): View
    {
        $this->authorize('admin');

        return view('admin.subcategories.edit', [
            'subcategory' => $subcategory,
            'categories' => ProductCategory::valuesSortedByLabel(),
            'categoryLabels' => $this->categoryLabels(),
        ]);
    }

    public function update(Request $request, ProductSubcategory $subcategory): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate($this->rules($subcategory->id));

        $subcategory->update($validated);

        return redirect()
            ->route('app.admin.subcategories.index')
            ->with('status', __('admin.subcategories.updated'));
    }

    public function destroy(ProductSubcategory $subcategory): RedirectResponse
    {
        $this->authorize('admin');

        if ($subcategory->products()->exists()) {
            return redirect()
                ->route('app.admin.subcategories.index')
                ->with('error', __('admin.subcategories.delete_blocked'));
        }

        $subcategory->delete();

        return redirect()
            ->route('app.admin.subcategories.index')
            ->with('status', __('admin.subcategories.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'category' => ['required', 'string', Rule::in(ProductCategory::values())],
            'label_de' => ['required', 'string', 'max:120'],
            'label_en' => ['required', 'string', 'max:120'],
            'label_fr' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * Localized display label for each category value.
     *
     * @return array<string, string>
     */
    private function categoryLabels(): array
    {
        $labels = [];
        foreach (ProductCategory::cases() as $case) {
            $labels[$case->value] = (string) __('admin.category.'.$case->value);
        }

        return $labels;
    }
}
