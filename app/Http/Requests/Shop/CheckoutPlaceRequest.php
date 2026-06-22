<?php

namespace App\Http\Requests\Shop;

use App\Services\Shop\CheckoutDraft;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Final step of the storefront checkout. The customer has already entered
 * the basket (step 1) and the details (step 2); here they confirm consent
 * and we hand the merged {@see CheckoutDraft} off to
 * {@see ShopOrderService::createOrder}.
 *
 * Re-asserting draft validity (date still on the menu, stock still
 * available) is the controller's job — the same checks
 * {@see CheckoutBasketRequest} ran on step 1 are re-run there because
 * inventory can shift while the customer fills out details.
 */
class CheckoutPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consent' => ['accepted'],
        ];
    }
}
