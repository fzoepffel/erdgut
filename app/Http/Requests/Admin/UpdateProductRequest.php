<?php

namespace App\Http\Requests\Admin;

use App\Enums\Allergen;
use App\Enums\DietaryTag;
use App\Enums\ProductCategory;
use App\Enums\ProductUnit;
use App\Enums\TasteProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
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
            $translationRules["translations.$locale.taste_profile"] = ['required', 'string', 'max:512'];
        }

        $selfId = $this->route('product')?->id;

        return [
            ...$translationRules,
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'category' => ['required', 'string', Rule::in(ProductCategory::values())],
            'unit' => ['required', 'string', Rule::in(ProductUnit::values())],
            'price_display_unit' => ['required', 'string', Rule::in(['g', 'kg', 'ml', 'l', 'piece', 'bunch'])],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'default_freshness_window_days' => ['nullable', 'integer', 'between:1,60'],
            'buying_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
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
        $this->merge([
            'is_seasonal' => $this->boolean('is_seasonal'),
            'is_available' => $this->boolean('is_available'),
            'is_published' => $this->boolean('is_published'),
            'is_egg_addon' => $this->boolean('is_egg_addon'),
            'is_test' => $this->boolean('is_test'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
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
        });
    }
}
