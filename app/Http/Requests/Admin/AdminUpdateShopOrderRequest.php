<?php

namespace App\Http\Requests\Admin;

use App\Models\ShopOrder;
use Illuminate\Support\Carbon;

/**
 * Validates an admin edit of an existing shop order. Shares the manual-order
 * rules with {@see AdminCreateShopOrderRequest}, minus what has no place in
 * an edit: no coupon input (the stored discount is frozen), no recurring
 * B2B branch, no customer-type switch (pricing pools stay as sold).
 *
 * The stock check re-uses the order's own committed quantities (an order
 * that consumes all remaining stock must be re-savable unchanged) and
 * tolerates products that were unpublished after the order was placed.
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
     * @return array<int, float>
     */
    protected function ownCommittedQuantities(): array
    {
        $order = $this->order();
        if ($order === null || ! $order->commitsStockNow()) {
            return [];
        }

        // A date change frees the old date; the new one must fit raw.
        $date = (string) $this->input('expected_delivery_date');
        if ($date === '' || ! Carbon::parse($date)->isSameDay($order->expected_delivery_date)) {
            return [];
        }

        $own = [];
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }
            $own[$item->product_id] = ($own[$item->product_id] ?? 0.0) + (float) $item->quantity;
        }

        return $own;
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
