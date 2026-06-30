<?php

namespace App\Http\Requests\Restaurant;

use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Foundation\Http\FormRequest;
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
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
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
        });
    }
}
