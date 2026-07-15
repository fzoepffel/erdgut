<?php

namespace App\Http\Requests\Admin;

use App\Models\ShopOrder;

/**
 * Validates an admin edit of an existing shop order. Shares the manual-order
 * rules with {@see AdminCreateShopOrderRequest}, minus what has no place in
 * an edit: no coupon input (the stored discount is frozen), no recurring
 * B2B branch, no customer-type switch (pricing pools stay as sold).
 *
 * Like admin creation there is no stock cap (the operator may deliberately
 * sell beyond stock); products that were unpublished after the order was
 * placed may stay on it.
 */
class AdminUpdateShopOrderRequest extends AdminCreateShopOrderRequest
{
    public function authorize(): bool
    {
        if (parent::authorize() !== true) {
            return false;
        }

        $order = $this->route('shopOrder');

        return $order instanceof ShopOrder && $order->isEditableByAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset(
            $rules['coupon_code'],
            $rules['customer_type'],
            $rules['restaurant_user_id'],
            $rules['recurring'],
            $rules['recurring_until'],
        );

        return $rules;
    }

    protected function order(): ?ShopOrder
    {
        $order = $this->route('shopOrder');

        return $order instanceof ShopOrder ? $order : null;
    }

    /**
     * @return array<int, int>
     */
    protected function preexistingProductIds(): array
    {
        $order = $this->order();
        if ($order === null) {
            return [];
        }

        return $order->items->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all();
    }
}
