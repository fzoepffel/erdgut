<?php

namespace App\Http\Requests\Restaurant;

use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RestaurantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isVerifiedRestaurant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            // product_id is optional: a line with no product but a `name` is a
            // free-form special request (cross-checked in withValidator()).
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.unit' => ['nullable', 'string', Rule::in(['piece', 'bunch', 'kg', 'g', 'l', 'ml'])],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'expected_delivery_date' => ['required', 'date'],
            'delivery_street' => ['required', 'string', 'max:255'],
            'delivery_postal_code' => ['required', 'string', 'max:16'],
            'delivery_city' => ['required', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $date = (string) $this->input('expected_delivery_date');
            $open = app(RestaurantCatalogService::class)
                ->selectableDates()
                ->contains(fn (array $row): bool => $row['date']->toDateString() === $date);

            if (! $open) {
                $v->errors()->add('expected_delivery_date', __('restaurant.order_date_unavailable'));
            }

            foreach ((array) $this->input('items', []) as $i => $line) {
                $hasProduct = (int) ($line['product_id'] ?? 0) > 0;
                $hasName = trim((string) ($line['name'] ?? '')) !== '';

                if (! $hasProduct && ! $hasName) {
                    $v->errors()->add("items.$i.name", __('restaurant.order_line_needs_product_or_name'));

                    continue;
                }
                // A free-form special request needs a unit.
                if (! $hasProduct && trim((string) ($line['unit'] ?? '')) === '') {
                    $v->errors()->add("items.$i.unit", __('restaurant.order_line_needs_unit'));
                }
            }
        });
    }
}
