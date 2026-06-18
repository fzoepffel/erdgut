<?php

namespace App\Http\Requests\Admin;

use App\Enums\Allergen;
use App\Enums\DietaryTag;
use App\Enums\ProductCategory;
use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use App\Http\Requests\Admin\Concerns\NormalizesProductTranslationInput;
use App\Models\Product;
use App\Models\ProductSubcategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use NormalizesProductTranslationInput;

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return ['de', 'en', 'fr'];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $translationRules = [
            'translations' => ['required', 'array'],
        ];
        foreach ($this->locales() as $locale) {
            $translationRules["translations.$locale"] = ['required', 'array'];
            $translationRules["translations.$locale.name"] = ['required', 'string', 'max:255'];
            $translationRules["translations.$locale.origin"] = ['required', 'string', 'max:128'];
            $translationRules["translations.$locale.variety"] = ['required', 'string', 'max:128'];
            // Long/detailed description (detail page). Stored in `taste_profile`.
            $translationRules["translations.$locale.taste_profile"] = ['required', 'string', 'max:1500'];
            // Short, card-friendly description (catalogue + shop cards).
            $translationRules["translations.$locale.short_description"] = ['required', 'string', 'max:240'];
        }

        $selfId = $this->route('product')?->id;

        return [
            ...$translationRules,
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'category' => ['required', 'string', Rule::in(ProductCategory::values())],
            'subcategory_id' => ['nullable', 'integer', 'exists:product_subcategories,id'],
            'unit' => ['required', 'string', Rule::in(ProductUnit::values())],
            'price_display_unit' => ['required', 'string', Rule::in(['g', '100g', 'kg', 'ml', 'l', 'piece', 'bunch'])],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            // German VAT rate: 7% reduced (fresh food) or 19% standard.
            // Form always submits one of the two; absent → controller
            // defaults to 7 (the food rate).
            'vat_rate_percent' => ['nullable', 'integer', 'in:7,19'],
            'default_freshness_window_days' => ['nullable', 'integer', 'between:1,60'],
            'buying_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            // Optional customer-group (business) price, in the display unit.
            'business_price' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
            // Inline grades/variants repeater. Blank rows (no name) are dropped
            // server-side; price is in the display unit, empty inherits base.
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'array'],
            'variants.*.name.de' => ['nullable', 'string', 'max:120'],
            'variants.*.name.en' => ['nullable', 'string', 'max:120'],
            'variants.*.name.fr' => ['nullable', 'string', 'max:120'],
            'variants.*.unit_price' => ['nullable', 'numeric', 'gt:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_seasonal' => ['boolean'],
            'is_available' => ['boolean'],
            'is_published' => ['boolean'],
            'is_egg_addon' => ['boolean'],
            'is_test' => ['boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['boolean'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', Rule::in(Allergen::values())],
            'dietary_tags' => ['nullable', 'array'],
            'dietary_tags.*' => ['string', Rule::in(DietaryTag::values())],
            'taste_profile_tags' => ['nullable', 'array'],
            'taste_profile_tags.*' => ['string', Rule::in(TasteProfile::values())],
            // Free-form custom tags carry a label in every locale so they
            // render in the visitor's language. All three are required per
            // entry — the form's "Add" button enters them together.
            'custom_taste_tags' => ['nullable', 'array', 'max:50'],
            'custom_taste_tags.*.de' => ['required', 'string', 'max:64'],
            'custom_taste_tags.*.en' => ['required', 'string', 'max:64'],
            'custom_taste_tags.*.fr' => ['required', 'string', 'max:64'],
            'quantity_step' => ['nullable', 'integer', 'min:1'],
            'unit_weight_grams' => ['nullable', 'integer', 'min:1'],
            'unit_volume_ml' => ['nullable', 'integer', 'min:1'],
            'pairings' => ['nullable', 'array'],
            'pairings.*' => ['integer', 'exists:products,id', Rule::notIn([$selfId])],
            'gallery_images' => ['nullable', 'array', 'max:12'],
            'gallery_images.*' => ['image', 'max:5120'],
            'gallery_remove' => ['nullable', 'array'],
            'gallery_remove.*' => ['integer', 'exists:product_images,id'],
            'gallery_alt' => ['nullable', 'array'],
            'gallery_alt.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $subcategoryId = $this->input('subcategory_id');
        if ($subcategoryId === '' || $subcategoryId === '0' || $subcategoryId === 0) {
            $subcategoryId = null;
        }

        $this->merge([
            'subcategory_id' => $subcategoryId,
            'is_seasonal' => $this->boolean('is_seasonal'),
            'is_available' => $this->boolean('is_available'),
            'is_published' => $this->boolean('is_published'),
            'is_egg_addon' => $this->boolean('is_egg_addon'),
            'is_test' => $this->boolean('is_test'),
            'remove_image' => $this->boolean('remove_image'),
        ]);

        $this->normalizeProductTranslationInput();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $unit = $this->input('unit');
            $display = $this->input('price_display_unit');
            if (! is_string($unit) || ! is_string($display)) {
                return;
            }

            $kind = ProductUnit::tryFrom($unit);
            if ($kind === null) {
                return;
            }

            if (! in_array($display, $kind->allowedDisplayUnits(), true)) {
                $v->errors()->add('price_display_unit', __('admin.products.price_display_unit_invalid'));
            }

            // Block a unit-KIND change while the product still has stock
            // inflows, recurring schedules, or box items. Those quantities
            // are stored in the OLD kind's canonical unit (e.g. "2 bunches")
            // and have no meaningful interpretation under a new kind ("2 g"),
            // so allowing the switch would silently corrupt inventory. The
            // admin must clear the existing inventory first. Display-unit
            // changes within the same kind (g↔kg, ml↔L) are unaffected.
            $product = $this->route('product');
            if ($product instanceof Product
                && $product->unit !== null
                && $product->unit !== $kind
                && $this->productHasInventory($product)
            ) {
                $v->errors()->add('unit', __('admin.products.unit_change_blocked_inventory', [
                    'unit' => $product->unit->label(),
                ]));
            }

            // Note: irrelevant fields for the chosen kind (quantity_step for
            // piece/bunch, unit_weight/unit_volume for mass/volume) are NOT
            // rejected here — the controller nulls them out based on the
            // final unit kind. Rejecting would trap admins switching kinds,
            // since the hidden form fields still carry their old values.

            $category = $this->input('category');
            $subcategoryId = $this->input('subcategory_id');
            if (is_string($category) && $category !== '' && $subcategoryId !== null && $subcategoryId !== '') {
                $matchesCategory = ProductSubcategory::query()
                    ->whereKey((int) $subcategoryId)
                    ->where('category', $category)
                    ->exists();

                if (! $matchesCategory) {
                    $v->errors()->add('subcategory_id', __('admin.product_form.subcategory_category_mismatch'));
                }
            }
        });
    }

    private function productHasInventory(Product $product): bool
    {
        return $product->stockInflows()->exists()
            || $product->stockInflowSchedules()->exists()
            || $product->boxItems()->exists();
    }
}
