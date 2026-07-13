<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Models\NumberSequence;
use App\Models\RestaurantOrder;
use App\Services\Admin\ProducerAllocationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prices a confirmed restaurant (B2B) order net-forward and issues an immutable,
 * sequentially-numbered German invoice (Rechnung) with a PDF. Once issued, the
 * order is frozen (see {@see RestaurantOrder::isInvoiced()}).
 */
class RestaurantInvoiceService
{
    /**
     * Apply admin net pricing / quantity edits / line drops. Net entry: the
     * admin gives a net line total + VAT rate; gross and VAT are derived.
     * Reserved-line quantity edits and removals release/adjust the stock hold
     * automatically (InventoryService reads the live quantity), so no inventory
     * call is needed here.
     *
     * @param  array<int, array{id:int, net_total_eur?:mixed, vat_rate_percent?:mixed, quantity?:mixed, remove?:mixed}>  $lines
     */
    public function updatePricing(RestaurantOrder $order, array $lines): RestaurantOrder
    {
        if ($order->isInvoiced()) {
            throw new RuntimeException('Order already invoiced — locked.');
        }

        return DB::transaction(function () use ($order, $lines): RestaurantOrder {
            $items = $order->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $payload) {
                $item = $items->get((int) ($payload['id'] ?? 0));
                if ($item === null) {
                    continue;
                }

                if (! empty($payload['remove'])) {
                    $item->delete(); // a reserved line's hold releases automatically

                    continue;
                }

                $qty = round((float) ($payload['quantity'] ?? $item->quantity), 4);
                $rate = (int) ($payload['vat_rate_percent'] ?? $item->vat_rate_snapshot_percent);
                $net = round((float) ($payload['net_total_eur'] ?? $item->lineNetEur()), 2);

                // Net entry → gross = net × (100 + rate)/100; VAT = gross − net.
                $gross = round($net * (100 + $rate) / 100, 2);
                $vat = round($gross - $net, 2);

                $item->forceFill([
                    'quantity' => $qty,
                    'vat_rate_snapshot_percent' => $rate,
                    'unit_price_snapshot_eur' => $qty > 0 ? round($gross / $qty, 4) : 0,
                    'line_total_eur' => $gross,
                    'vat_amount_eur' => $vat,
                ])->save();
            }

            $this->rerollTotals($order);

            return $order;
        });
    }

    /**
     * Issue the invoice: assign a sequential number, set the dates, and freeze
     * the order. Idempotent + status-guarded; the order row is locked so two
     * concurrent issues can't both allocate a number.
     */
    public function issueInvoice(RestaurantOrder $order): RestaurantOrder
    {
        $order = DB::transaction(function () use ($order): RestaurantOrder {
            /** @var RestaurantOrder $fresh */
            $fresh = RestaurantOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->isInvoiced()) {
                throw new RuntimeException('Order already invoiced.');
            }
            if (! in_array($fresh->status, [RestaurantOrderStatus::Confirmed, RestaurantOrderStatus::Fulfilled], true)) {
                throw new RuntimeException('Only confirmed or fulfilled orders can be invoiced.');
            }

            $this->rerollTotals($fresh);

            // Every billable (non-declined) line must be priced — don't let a
            // special line the admin forgot to price land on the Rechnung at €0.
            $unpriced = $fresh->items->first(fn ($i) => ! $i->isDeclined() && (float) $i->line_total_eur <= 0.0);
            if ($unpriced !== null) {
                throw new RuntimeException(__('restaurant.admin_invoice_unpriced_line'));
            }
            if ((float) $fresh->items_total_gross_eur <= 0) {
                throw new RuntimeException('Cannot invoice a zero-total order.');
            }

            $invoicedAt = Carbon::now();
            $year = $invoicedAt->year;
            $seq = NumberSequence::next("restaurant_invoice:{$year}");
            $number = sprintf('%s-%d-%04d', (string) config('shop.restaurant_invoice_prefix', 'RG'), $year, $seq);

            $fresh->forceFill([
                'invoice_number' => $number,
                'invoiced_at' => $invoicedAt,
                'invoice_due_date' => $invoicedAt->copy()->addDays((int) config('shop.restaurant_invoice_due_days', 14))->toDateString(),
            ])->save();

            return $fresh;
        });

        return $order->refresh();
    }

    /**
     * VAT amount per rate (e.g. 7 → 0.79, 19 → 1.60), sorted ascending.
     *
     * @return Collection<int, float>
     */
    public function vatByRate(RestaurantOrder $order): Collection
    {
        return $order->items
            ->groupBy('vat_rate_snapshot_percent')
            ->map(fn ($group) => round($group->sum(fn ($item) => (float) $item->vat_amount_eur), 2))
            ->sortKeys();
    }

    /** Render the invoice PDF from the frozen order. Shared by download routes + mail. */
    public function renderPdf(RestaurantOrder $order): DomPDF
    {
        $order->loadMissing(['items.product.producer.translations', 'user.restaurantProfile']);

        // The farms that actually delivered (allocation over recorded
        // inflows); lines without a computed share fall back to the
        // product's primary producer in the view.
        $productIds = $order->items->pluck('product_id')->filter()->map(fn ($v): int => (int) $v)->unique()->values()->all();
        $producerNames = ($order->expected_delivery_date !== null && $productIds !== [])
            ? app(ProducerAllocationService::class)->namesByItemKey($productIds, Carbon::parse($order->expected_delivery_date))
            : [];

        return Pdf::loadView('restaurant.pdf.invoice', [
            'order' => $order,
            'profile' => $order->user?->restaurantProfile,
            'producerNames' => $producerNames,
            'vatByRate' => $this->vatByRate($order),
            'seller' => [
                'name' => (string) config('shop.company_name'),
                'street' => (string) config('shop.company_street'),
                'postal_city' => (string) config('shop.company_postal_city'),
                'phone' => (string) config('shop.company_phone'),
                'email' => (string) config('shop.company_email'),
                'vat_id' => (string) config('shop.company_vat_id'),
                'tax_number' => (string) config('shop.company_tax_number'),
                'iban' => (string) config('shop.company_iban'),
                'bank' => (string) config('shop.company_bank'),
                'register' => (string) config('shop.company_register'),
            ],
        ])->setPaper('a4');
    }

    public function rerollTotals(RestaurantOrder $order): void
    {
        $order->load('items');
        // Declined lines never bill.
        $live = $order->items->reject(fn ($i) => $i->isDeclined());
        $gross = round((float) $live->sum(fn ($i) => (float) $i->line_total_eur), 2);
        $vat = round((float) $live->sum(fn ($i) => (float) $i->vat_amount_eur), 2);

        $order->forceFill([
            'items_total_gross_eur' => $gross,
            'vat_total_eur' => $vat,
            'items_total_net_eur' => round($gross - $vat, 2),
        ])->save();
    }
}
