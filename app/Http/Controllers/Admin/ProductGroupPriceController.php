<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Services\Shop\PriceResolver;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Per-product customer-group prices (Pricing Phase: customer groups).
 *
 * Manages the "plain" business price for a product — a {@see ProductPriceRule}
 * scoped to customer_type = business with no date window, variant or quantity
 * tier. The default (private) price is the product's own unit_price. The
 * {@see PriceResolver} already applies these to B2B orders.
 *
 * Prices are entered + shown in the product's display unit (€/kg) and stored
 * per canonical unit (€/g), exactly like {@see ProductPriceRuleController}.
 */
class ProductGroupPriceController extends Controller
{
    public function index(Product $product): View
    {
        $this->authorize('admin');

        return view('admin.products.group-prices', [
            'product' => $product,
            'displayUnit' => $product->price_display_unit ?? $product->unit?->defaultDisplayUnit(),
            'privatePrice' => $product->displayUnitPrice(),
            'businessRule' => $this->businessRule($product)->first(),
            'businessDisplayPrice' => $this->businessDisplayPrice($product),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'unit_price' => ['required', 'numeric', 'gt:0'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $canonical = round((float) $validated['unit_price'] / $this->displayFactor($product), 4);

        // One plain business price per product: replace any existing.
        $this->businessRule($product)->delete();

        $product->priceRules()->create([
            'customer_type' => CustomerType::Business->value,
            'unit_price' => $canonical,
            'label' => $validated['label'] ?? null,
            'is_active' => true,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('app.admin.products.group-prices', $product->id)
            ->with('status', 'group-price-saved');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $this->businessRule($product)->delete();

        return redirect()
            ->route('app.admin.products.group-prices', $product->id)
            ->with('status', 'group-price-cleared');
    }

    /**
     * The plain business price rule(s) for a product: customer_type business,
     * no date window, no variant, no quantity tier.
     *
     * @return HasMany<ProductPriceRule, Product>
     */
    private function businessRule(Product $product)
    {
        return $product->priceRules()
            ->where('customer_type', CustomerType::Business->value)
            ->whereNull('starts_on')
            ->whereNull('ends_on')
            ->whereNull('variant_id')
            ->whereNull('min_quantity');
    }

    /** The business price expressed in the display unit (€/kg), or null. */
    private function businessDisplayPrice(Product $product): ?float
    {
        $rule = $this->businessRule($product)->first();
        if ($rule === null) {
            return null;
        }

        return round((float) $rule->unit_price * $this->displayFactor($product), 2);
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
}
