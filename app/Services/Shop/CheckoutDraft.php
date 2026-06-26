<?php

namespace App\Services\Shop;

use Carbon\CarbonImmutable;
use Illuminate\Session\Store as Session;

/**
 * Session-backed draft of an in-progress checkout. Each customer-facing
 * step writes its slice (basket → details → consent), and the final
 * {@see ShopCheckoutController::place} action reads the merged draft
 * before handing it to {@see ShopOrderService::createOrder}.
 *
 * The draft is held in the user's session under {@see self::KEY} and
 * expires after {@see self::TTL_HOURS} hours so an abandoned cart can't
 * resurface days later with a stale delivery date. Cleared explicitly
 * after a successful order create.
 */
class CheckoutDraft
{
    public const KEY = 'shop.checkout.draft';

    public const TTL_HOURS = 24;

    /**
     * Whitelisted detail fields persisted across steps. Other request input
     * is dropped on save so the session never accumulates noise.
     *
     * @var list<string>
     */
    public const DETAIL_FIELDS = [
        'fulfillment_mode',
        'pickup_location',
        'payment_method',
        'delivery_street',
        'delivery_postal_code',
        'delivery_city',
        'delivery_notes',
        'name',
        'email',
        'phone',
        'coupon_code',
    ];

    public function __construct(private readonly Session $session) {}

    /**
     * Current draft state. Empty array means "no draft yet" — callers should
     * treat that as a fresh checkout.
     *
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $draft = (array) $this->session->get(self::KEY, []);
        if ($draft === []) {
            return [];
        }
        if ($this->isExpired($draft)) {
            $this->clear();

            return [];
        }

        return $draft;
    }

    /**
     * Replace the basket slice (items + delivery date). Keeps any previously
     * captured details intact so the customer can hop between step 1 and
     * step 2 without losing their address.
     *
     * @param  list<array{product_id: int, variant_id?: int|null, quantity: float|int}>  $items
     */
    public function setBasket(array $items, string $expectedDeliveryDate): void
    {
        $draft = $this->current();
        $draft['items'] = array_values($items);
        $draft['expected_delivery_date'] = $expectedDeliveryDate;
        $draft['updated_at'] = CarbonImmutable::now()->toIso8601String();
        $this->session->put(self::KEY, $draft);
    }

    /**
     * Merge a slice of detail fields into the draft. Only fields named in
     * {@see self::DETAIL_FIELDS} survive; null values are stored so unsetting
     * is explicit.
     *
     * @param  array<string, mixed>  $fields
     */
    public function setDetails(array $fields): void
    {
        $draft = $this->current();
        foreach (self::DETAIL_FIELDS as $name) {
            if (array_key_exists($name, $fields)) {
                $value = $fields[$name];
                $draft[$name] = is_string($value) && trim($value) === '' ? null : $value;
            }
        }
        $draft['updated_at'] = CarbonImmutable::now()->toIso8601String();
        $this->session->put(self::KEY, $draft);
    }

    public function hasBasket(): bool
    {
        $draft = $this->current();

        return ! empty($draft['items']) && ! empty($draft['expected_delivery_date']);
    }

    public function hasDetails(): bool
    {
        $draft = $this->current();
        if (! $this->hasBasket()) {
            return false;
        }

        // Minimum to advance to review: a fulfillment mode + name + email.
        // Per-mode address / pickup / payment fields are enforced by
        // CheckoutDetailsRequest validation when the form is submitted.
        return ! empty($draft['fulfillment_mode'])
            && ! empty($draft['name'])
            && ! empty($draft['email']);
    }

    /**
     * Remember which ShopOrder this draft produced. We keep the draft alive
     * after {@see ShopCheckoutController::place} so the customer's basket
     * survives an abandoned SumUp checkout. The id lets /shop find the
     * orphan and release its stock when the customer returns mid-flow.
     */
    public function setPlacedOrderId(int $orderId): void
    {
        $draft = $this->current();
        if ($draft === []) {
            return;
        }
        $draft['placed_order_id'] = $orderId;
        $draft['updated_at'] = CarbonImmutable::now()->toIso8601String();
        $this->session->put(self::KEY, $draft);
    }

    public function placedOrderId(): ?int
    {
        $draft = $this->current();
        $id = $draft['placed_order_id'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }

    public function forgetPlacedOrderId(): void
    {
        $draft = $this->current();
        if ($draft === []) {
            return;
        }
        unset($draft['placed_order_id']);
        $this->session->put(self::KEY, $draft);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function isExpired(array $draft): bool
    {
        $stamp = isset($draft['updated_at']) ? (string) $draft['updated_at'] : null;
        if ($stamp === null) {
            return false;
        }
        try {
            $updatedAt = CarbonImmutable::parse($stamp);
        } catch (\Throwable) {
            return true;
        }

        return $updatedAt->lessThan(CarbonImmutable::now()->subHours(self::TTL_HOURS));
    }
}
