<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manage a product's named grades/variants (Pricing Phase 3).
 *
 * The product itself is the implicit default/"Standard" grade, so these rows
 * are only the *extra* grades. Prices are entered + shown in the product's
 * display unit (€/kg) and stored per canonical unit (€/g), exactly like
 * {@see ProductPriceRuleController}.
 */
class ProductVariantController extends Controller
{
    public function index(Product $product): View
    {
        $this->authorize('admin');

        $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit();
        $factor = $product->unit !== null && $displayUnit !== null
            ? max(1, $product->unit->canonicalUnitsPerDisplay($displayUnit))
            : 1;

        return view('admin.products.variants', [
            'product' => $product,
            'variants' => $product->variants()->get(),
            'displayUnit' => $displayUnit,
            'displayFactor' => $factor,
            'defaultPrice' => $product->displayUnitPrice(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $this->validatePayload($request);

        $product->variants()->create([
            'name' => $this->cleanName($validated['name']),
            'unit_price' => $this->canonicalPrice($product, $validated['unit_price'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'position' => (int) ($validated['position'] ?? 0),
        ]);

        return redirect()
            ->route('app.admin.products.variants', $product->id)
            ->with('status', 'variant-saved');
    }

    public function update(Request $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless((int) $variant->product_id === (int) $product->id, 404);

        $validated = $this->validatePayload($request);

        $variant->update([
            'name' => $this->cleanName($validated['name']),
            'unit_price' => $this->canonicalPrice($product, $validated['unit_price'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'position' => (int) ($validated['position'] ?? 0),
        ]);

        return redirect()
            ->route('app.admin.products.variants', $product->id)
            ->with('status', 'variant-saved');
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless((int) $variant->product_id === (int) $product->id, 404);

        $variant->delete();

        return redirect()
            ->route('app.admin.products.variants', $product->id)
            ->with('status', 'variant-deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'array'],
            'name.de' => ['nullable', 'string', 'max:120'],
            'name.en' => ['nullable', 'string', 'max:120'],
            'name.fr' => ['nullable', 'string', 'max:120'],
            'unit_price' => ['nullable', 'numeric', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * Keep only non-empty locale strings; require at least one.
     *
     * @param  array<string, mixed>  $name
     * @return array<string, string>
     */
    private function cleanName(array $name): array
    {
        $clean = [];
        foreach (['de', 'en', 'fr'] as $locale) {
            $value = isset($name[$locale]) ? trim((string) $name[$locale]) : '';
            if ($value !== '') {
                $clean[$locale] = $value;
            }
        }

        // Mirror an entered locale into the others so the label never renders
        // empty in a missing language (the model still falls back de→en→fr).
        if ($clean === []) {
            abort(422, 'A grade needs a name in at least one language.');
        }

        return $clean;
    }

    /**
     * Convert an entered display-unit price (€/kg) to canonical (€/g), or NULL
     * to inherit the product's base price. Mirrors
     * {@see ProductPriceRuleController::store} unit handling.
     */
    private function canonicalPrice(Product $product, int|float|string|null $displayPrice): ?float
    {
        if ($displayPrice === null || $displayPrice === '') {
            return null;
        }

        $factor = 1;
        if ($product->unit !== null) {
            $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
            $f = $product->unit->canonicalUnitsPerDisplay($displayUnit);
            $factor = $f > 0 ? $f : 1;
        }

        return round((float) $displayPrice / $factor, 4);
    }
}
