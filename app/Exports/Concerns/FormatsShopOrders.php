<?php

namespace App\Exports\Concerns;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Support\PickupLocations;

/**
 * Formatting helpers shared by the six shop-order export sheets. Centralized
 * so unit conversion, status labels, and address formatting stay consistent
 * across the workbook — pivots in Excel rely on identical labels.
 */
trait FormatsShopOrders
{
    /**
     * Convert a canonical quantity (g/ml/piece/bunch) into the product's
     * preferred display unit (kg/L/g/ml/piece/bunch). Returns
     * `[numericQty, localizedSymbol]` so Menge stays sortable in Excel.
     *
     * @return array{0: float, 1: string}
     */
    protected function displayQuantity(?ProductUnit $unit, ?string $priceDisplayUnit, float $canonical): array
    {
        if (! $unit instanceof ProductUnit) {
            return [$canonical, ''];
        }

        $display = $priceDisplayUnit ?? $unit->defaultDisplayUnit();
        $factor = $unit->canonicalUnitsPerDisplay($display);
        $qty = $factor > 0 ? $canonical / $factor : $canonical;

        return [round($qty, 4), ProductUnit::displaySymbol($display)];
    }

    /**
     * Per-display-unit buying price (€/kg, €/L, …) snapshotted via the product.
     * Returns null if the admin never set a buying price.
     */
    protected function buyingPricePerDisplayUnit(?Product $product): ?float
    {
        if ($product === null || $product->buying_price_cents === null) {
            return null;
        }

        return round($product->buying_price_cents / 100, 4);
    }

    /**
     * Procurement cost for one line, in EUR: buying €/display-unit × display-unit qty.
     * Returns null if buying price unknown so callers can render "—".
     */
    protected function buyingTotalForLine(ShopOrderItem $item): ?float
    {
        $perUnit = $this->buyingPricePerDisplayUnit($item->product);
        if ($perUnit === null) {
            return null;
        }

        [$qty] = $this->displayQuantity(
            $item->product?->unit,
            $item->product?->price_display_unit,
            (float) $item->quantity,
        );

        return round($perUnit * $qty, 2);
    }

    protected function customerTypeLabel(?CustomerType $type): string
    {
        return match ($type) {
            CustomerType::Business => (string) __('shop.export.type_business'),
            CustomerType::Private => (string) __('shop.export.type_private'),
            default => '',
        };
    }

    protected function orderSourceLabel(?OrderSource $source): string
    {
        return match ($source) {
            OrderSource::Admin => (string) __('shop.admin.order_source_admin'),
            OrderSource::Storefront => (string) __('shop.admin.order_source_storefront'),
            default => '',
        };
    }

    protected function fulfillmentLabel(?string $mode): string
    {
        return $mode === 'delivery'
            ? (string) __('shop.fulfillment_delivery')
            : (string) __('shop.fulfillment_pickup');
    }

    protected function statusLabel(?ShopOrderStatus $status): string
    {
        if ($status === null) {
            return '';
        }

        return (string) __('shop.admin.status_'.$status->value);
    }

    protected function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'online' => (string) __('shop.export.payment_online'),
            'in_person' => (string) __('shop.export.payment_in_person'),
            default => '',
        };
    }

    protected function addressLine(ShopOrder $order): string
    {
        if ($order->fulfillment_mode !== 'delivery') {
            return '';
        }

        return trim(implode(', ', array_filter([
            $order->delivery_street,
            trim(($order->delivery_postal_code ?? '').' '.($order->delivery_city ?? '')),
        ])), ', ');
    }

    protected function pickupLocationLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        $pickup = PickupLocations::find($key);
        if ($pickup === null) {
            return $key;
        }

        return trim(($pickup['name'] ?? $key).
            (isset($pickup['street']) ? ' — '.$pickup['street'] : ''));
    }

    /**
     * "14:00 – 16:00" if both ends set, else empty. Uses the model's existing
     * formatter so the export matches the admin UI label exactly.
     */
    protected function windowLabel(ShopOrder $order): string
    {
        return $order->deliveryWindowLabel() ?? '';
    }
}
