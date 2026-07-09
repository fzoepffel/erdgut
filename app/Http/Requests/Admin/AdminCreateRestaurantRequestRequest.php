<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Restaurant\RestaurantOrderRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an admin-entered B2B request (Anfrage) on behalf of a restaurant.
 *
 * Diverges from the restaurant-side {@see RestaurantOrderRequest}
 * in intentional ways:
 *  - The acting restaurant is chosen (verified account) or auto-created from
 *    name + email, mirroring the recurring path of {@see AdminCreateShopOrderRequest}.
 *  - `expected_delivery_date` accepts any future date instead of the open-day
 *    list: the admin takes responsibility, requests reserve no stock, and the
 *    weekly materializer skips closed days on its own.
 *  - Catalog lines are checked against `is_available && is_published` because
 *    {@see RestaurantOrderService} silently drops
 *    lines outside that scope; the admin must get an error instead.
 */
class AdminCreateRestaurantRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_user_id' => ['nullable', 'integer', 'exists:users,id'],
            // Auto-create path when no account is chosen.
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.unit' => ['nullable', 'string', Rule::in(['piece', 'bunch', 'kg', 'g', 'l', 'ml'])],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.recurrence' => ['nullable', 'string', Rule::in(['one_time', 'weekly'])],
            'items.*.until' => ['nullable', 'date', 'after:expected_delivery_date'],

            'expected_delivery_date' => ['required', 'date', 'after_or_equal:tomorrow'],
            'delivery_street' => ['required', 'string', 'max:255'],
            'delivery_postal_code' => ['required', 'string', 'max:16'],
            'delivery_city' => ['required', 'string', 'max:255'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Acting restaurant: verified account or enough data to create one.
            if ((int) $this->input('restaurant_user_id') > 0) {
                $accountUser = User::find((int) $this->input('restaurant_user_id'));
                if ($accountUser?->restaurantProfile === null || ! $accountUser->restaurantProfile->isVerified()) {
                    $v->errors()->add('restaurant_user_id', __('shop.admin.errors.recurring_needs_account'));
                }
            } elseif (trim((string) $this->input('name')) === '' || trim((string) $this->input('email')) === '') {
                $v->errors()->add('restaurant_user_id', __('restaurant.admin_create_request_needs_contact'));
            }

            $items = (array) $this->input('items', []);
            $productIds = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id)->filter()->all();
            $products = Product::query()->whereIn('id', $productIds)->with('variants')->get()->keyBy('id');

            foreach ($items as $i => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $hasName = trim((string) ($line['name'] ?? '')) !== '';

                if ($productId === 0 && ! $hasName) {
                    $v->errors()->add("items.$i.name", __('restaurant.order_line_needs_product_or_name'));

                    continue;
                }

                // A free-form special request needs a unit.
                if ($productId === 0) {
                    if (trim((string) ($line['unit'] ?? '')) === '') {
                        $v->errors()->add("items.$i.unit", __('restaurant.order_line_needs_unit'));
                    }

                    continue;
                }

                $product = $products->get($productId);
                if ($product === null || ! ($product->is_available && $product->is_published)) {
                    $v->errors()->add(
                        "items.$i.product_id",
                        __('shop.errors.product_unavailable', ['name' => $product?->name ?? "#$productId"]),
                    );

                    continue;
                }

                // The service silently skips foreign or inactive variants;
                // reject them here so nothing vanishes without a message.
                $variantId = (int) ($line['variant_id'] ?? 0);
                if ($variantId > 0) {
                    $variant = $product->variants->firstWhere('id', $variantId);
                    if ($variant === null || ! $variant->is_active) {
                        $v->errors()->add(
                            "items.$i.variant_id",
                            __('shop.errors.product_unavailable', ['name' => $product->name]),
                        );
                    }
                }
            }
        });
    }
}
