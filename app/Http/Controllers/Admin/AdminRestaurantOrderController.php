<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInvoiceEditorRequest;
use App\Http\Requests\Admin\AdminPriceRestaurantOrderRequest;
use App\Mail\RestaurantInvoiceCustomMail;
use App\Mail\RestaurantOrderCancelledMail;
use App\Mail\RestaurantOrderConfirmedMail;
use App\Mail\RestaurantOrderDeclinedMail;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin queue + management of restaurant (B2B) requests. Order-level only:
 * confirm / decline / fulfil the whole request (per-line edits + pricing are
 * the invoice step). Declining releases the stock hold automatically.
 */
class AdminRestaurantOrderController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $orders,
        private readonly RestaurantInvoiceService $invoices,
    ) {}

    /**
     * The Anfragen queue: REQUESTS only. Firm restaurant orders (including
     * granted requests) live in the unified shop-orders overview — this page
     * is where pending requests wait for per-line accept/decline.
     */
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $status = RestaurantOrderStatus::tryFrom((string) $request->query('status', ''));

        $orders = RestaurantOrder::query()
            ->requests()
            ->with('user')
            ->withCount('items')
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            // Submitted (awaiting action) first, then newest.
            ->orderByRaw("CASE WHEN status = '".RestaurantOrderStatus::Submitted->value."' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // Open requests need admin action — surface the count in the heading.
        $openRequests = RestaurantOrder::query()
            ->requests()
            ->where('status', RestaurantOrderStatus::Submitted->value)
            ->count();

        return view('admin.restaurant-orders.index', [
            'orders' => $orders,
            'statuses' => RestaurantOrderStatus::cases(),
            'activeStatus' => $status,
            'openRequests' => $openRequests,
        ]);
    }

    public function show(RestaurantOrder $restaurantOrder): View
    {
        $this->authorize('admin');

        $restaurantOrder->load(['user.restaurantProfile', 'standingOrder', 'items.product.translations', 'items.product.producer.translations']);

        return view('admin.restaurant-orders.show', [
            'order' => $restaurantOrder,
            // Sequence preview so the operator can align the counter with
            // manually issued invoices before the first system invoice.
            'nextInvoiceNumber' => $restaurantOrder->isInvoiceable()
                ? $this->invoices->nextInvoiceNumberPreview()
                : null,
        ]);
    }

    public function confirm(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'confirm');
    }

    public function decline(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'decline');
    }

    public function fulfil(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        return $this->act($request, $restaurantOrder, 'fulfil');
    }

    /** Accept a single on-request/special line. Order settles to Confirmed once none pend. */
    public function acceptLine(Request $request, RestaurantOrder $restaurantOrder, RestaurantOrderItem $item): RedirectResponse
    {
        return $this->actOnLine($request, $restaurantOrder, $item, 'accept');
    }

    /** Decline a single on-request/special line. */
    public function declineLine(Request $request, RestaurantOrder $restaurantOrder, RestaurantOrderItem $item): RedirectResponse
    {
        return $this->actOnLine($request, $restaurantOrder, $item, 'decline');
    }

    private function actOnLine(Request $request, RestaurantOrder $order, RestaurantOrderItem $item, string $action): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless($item->restaurant_order_id === $order->id, 404);

        $from = $order->status->value;
        $applied = $action === 'accept'
            ? $this->orders->acceptLine($item)
            : $this->orders->declineLine($item);

        if ($applied) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $order->id,
                action: $action.'_line',
                fromState: $from,
                toState: $order->fresh()->status->value,
                metadata: ['channel' => 'admin', 'item_id' => (int) $item->id],
                actorUserId: (int) $request->user()->id,
            );

            // If resolving this line settled the whole order, tell the restaurant.
            $order->refresh();
            if ($from === RestaurantOrderStatus::Submitted->value) {
                if ($order->status === RestaurantOrderStatus::Confirmed) {
                    $this->notify($order, 'confirm', null);
                } elseif ($order->status === RestaurantOrderStatus::Declined) {
                    $this->notify($order, 'decline', null);
                }
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('restaurant.admin_line_flash_'.$action));
    }

    /** Cancel an order before delivery/invoice (releases the reservation). */
    public function cancel(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        $note = trim((string) $request->input('note')) ?: null;
        $from = $restaurantOrder->status->value;

        if ($this->orders->adminCancel($restaurantOrder)) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $restaurantOrder->id,
                action: 'cancel',
                fromState: $from,
                toState: $restaurantOrder->status->value,
                reason: $note,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );

            if ($restaurantOrder->user !== null) {
                try {
                    Mail::to($restaurantOrder->user->email)
                        ->locale($restaurantOrder->locale ?? app()->getLocale())
                        ->queue(new RestaurantOrderCancelledMail($restaurantOrder, $note));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_orders_flash_cancel', ['id' => $restaurantOrder->id]));
    }

    private function act(Request $request, RestaurantOrder $order, string $action): RedirectResponse
    {
        $this->authorize('admin');

        $note = trim((string) $request->input('note')) ?: null;
        $from = $order->status->value;

        $applied = match ($action) {
            'confirm' => $this->orders->confirm($order),
            'decline' => $this->orders->decline($order),
            'fulfil' => $this->orders->fulfil($order),
            default => false,
        };

        if ($applied) {
            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $order->id,
                action: $action,
                fromState: $from,
                toState: $order->status->value,
                reason: $note,
                metadata: ['channel' => 'admin'],
                actorUserId: (int) $request->user()->id,
            );

            $this->notify($order, $action, $note);
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('restaurant.admin_orders_flash_'.$action, ['id' => $order->id]));
    }

    private function notify(RestaurantOrder $order, string $action, ?string $note): void
    {
        $mailable = match ($action) {
            'confirm' => new RestaurantOrderConfirmedMail($order, $note),
            'decline' => new RestaurantOrderDeclinedMail($order, $note),
            default => null, // fulfil is internal — no restaurant email
        };

        if (! $mailable instanceof Mailable || $order->user === null) {
            return;
        }

        try {
            Mail::to($order->user->email)
                ->locale($order->locale ?? app()->getLocale())
                ->queue($mailable);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // --- Invoice (R6) ---------------------------------------------------

    public function updatePricing(AdminPriceRestaurantOrderRequest $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        abort_if($restaurantOrder->isInvoiced(), 403);
        abort_unless($restaurantOrder->isInvoiceable(), 403);

        $this->invoices->updatePricing(
            $restaurantOrder,
            $request->validated('items'),
            $request->filled('delivery_fee_net_eur') ? (float) $request->validated('delivery_fee_net_eur') : null,
            $request->filled('delivery_fee_vat_rate_percent') ? (int) $request->validated('delivery_fee_vat_rate_percent') : null,
        );

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_priced_flash'));
    }

    /**
     * WYSIWYG invoice editor: a page that looks like the invoice where every
     * variable part is an input. Available before AND after issuing (issued
     * invoices stay editable by product decision; the number never changes).
     */
    public function invoiceEditor(RestaurantOrder $restaurantOrder): View
    {
        $this->authorize('admin');

        $restaurantOrder->load(['user.restaurantProfile', 'items.product.translations', 'items.product.producer.translations']);
        $profile = $restaurantOrder->user?->restaurantProfile;

        // The same fallbacks the PDF uses, prefilled as editable values.
        $billingAddress = $profile !== null
            ? trim(($profile->billing_street ?? '').', '.trim(($profile->billing_postal_code ?? '').' '.($profile->billing_city ?? '')), ', ')
            : '';
        $deliveryAddress = trim($restaurantOrder->delivery_street.', '.$restaurantOrder->delivery_postal_code.' '.$restaurantOrder->delivery_city, ', ');
        $dueDays = (int) config('shop.restaurant_invoice_due_days', 7);

        $defaults = [
            'seller_name' => (string) config('shop.company_name'),
            'seller_register' => (string) config('shop.company_register'),
            'seller_vat_id' => config('shop.company_vat_id') ? 'Umsatzsteuer-ID: '.config('shop.company_vat_id') : '',
            'seller_address' => trim((string) config('shop.company_street').', '.(string) config('shop.company_postal_city'), ', '),
            'seller_email' => (string) config('shop.company_email'),
            'recipient_name' => (string) ($profile?->business_name ?? $restaurantOrder->user?->name ?? ''),
            'recipient_extra' => $profile?->contact_name ? 'z. Hd. '.$profile->contact_name : '',
            'recipient_address' => $billingAddress !== '' ? $billingAddress : $deliveryAddress,
            'recipient_phone' => (string) ($profile?->phone ?? $restaurantOrder->user?->phone ?? ''),
            'recipient_vat_id' => $profile?->ust_id_nr ? 'USt-IdNr.: '.$profile->ust_id_nr : '',
            'bank_name' => (string) config('shop.company_bank'),
            'bank_holder' => (string) config('shop.company_name'),
            'bank_iban' => (string) config('shop.company_iban'),
            'bank_reference' => (string) ($restaurantOrder->invoice_number ?? $this->invoices->nextInvoiceNumberPreview()),
            'delivery_line_label' => 'Lieferung '.$deliveryAddress,
            'questions_text' => trim((config('shop.company_email') ? 'Email '.config('shop.company_email') : '')
                .(config('shop.company_phone') ? "\noder anrufen unter ".config('shop.company_phone') : '')),
            'terms_text' => 'Der Rechnungsbetrag ist innerhalb von '.$dueDays.' Tagen nach Lieferung zahlbar. Leistungsdatum entspricht dem Lieferdatum.',
        ];

        $values = [];
        foreach ($defaults as $key => $default) {
            $values[$key] = $restaurantOrder->invoiceOverride($key) ?? $default;
        }

        $items = $restaurantOrder->items->reject(fn ($i) => $i->isDeclined())->values();

        return view('admin.shop-orders.invoice-editor', [
            'order' => $restaurantOrder,
            'items' => $items,
            'values' => $values,
            'previewNumber' => $restaurantOrder->invoice_number ?? $this->invoices->nextInvoiceNumberPreview(),
            'dueDays' => $dueDays,
            'backUrl' => $this->orderContextUrl($restaurantOrder),
        ]);
    }

    /** Persist everything the invoice editor can change, in one save. */
    public function saveInvoiceEditor(AdminInvoiceEditorRequest $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $before = [
            'net' => (float) $restaurantOrder->items_total_net_eur,
            'gross' => (float) $restaurantOrder->items_total_gross_eur,
            'item_count' => $restaurantOrder->items()->count(),
        ];

        $this->invoices->updatePricing(
            $restaurantOrder,
            $request->validated('items'),
            $request->filled('delivery_fee_net_eur') ? (float) $request->validated('delivery_fee_net_eur') : null,
            $request->filled('delivery_fee_vat_rate_percent') ? (int) $request->validated('delivery_fee_vat_rate_percent') : null,
        );

        $this->invoices->saveInvoiceOverrides($restaurantOrder, (array) $request->validated('overrides', []));

        if ($request->filled('invoice_due_date')) {
            $restaurantOrder->forceFill(['invoice_due_date' => $request->validated('invoice_due_date')])->save();
        }
        if ($request->filled('expected_delivery_date')) {
            $restaurantOrder->forceFill(['expected_delivery_date' => $request->validated('expected_delivery_date')])->save();
            // Stock holds read the order row live; the mirror carries the date too.
            $this->orders->syncShopOrderMirror($restaurantOrder);
        }

        $restaurantOrder->refresh();

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $restaurantOrder->id,
            action: 'invoice_edit',
            fromState: $restaurantOrder->status->value,
            toState: $restaurantOrder->status->value,
            metadata: [
                'channel' => 'admin',
                'before' => $before,
                'after' => [
                    'net' => (float) $restaurantOrder->items_total_net_eur,
                    'gross' => (float) $restaurantOrder->items_total_gross_eur,
                    'item_count' => $restaurantOrder->items()->count(),
                ],
                'override_keys' => array_keys((array) ($restaurantOrder->invoice_overrides ?? [])),
                'invoiced' => $restaurantOrder->isInvoiced(),
            ],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.shop-orders.invoice-editor', $restaurantOrder)
            ->with('status', __('restaurant.invoice_editor_saved_flash'));
    }

    /**
     * Live PDF preview from the editor: the submitted (possibly UNSAVED)
     * form state is applied inside a transaction, the PDF rendered, and
     * everything rolled back — the admin sees exactly what the current
     * sheet would print without persisting anything.
     */
    public function invoiceEditorLivePreview(AdminInvoiceEditorRequest $request, RestaurantOrder $restaurantOrder): SymfonyResponse
    {
        DB::beginTransaction();
        try {
            $this->invoices->updatePricing(
                $restaurantOrder,
                $request->validated('items'),
                $request->filled('delivery_fee_net_eur') ? (float) $request->validated('delivery_fee_net_eur') : null,
                $request->filled('delivery_fee_vat_rate_percent') ? (int) $request->validated('delivery_fee_vat_rate_percent') : null,
            );
            $this->invoices->saveInvoiceOverrides($restaurantOrder, (array) $request->validated('overrides', []));
            if ($request->filled('invoice_due_date')) {
                $restaurantOrder->forceFill(['invoice_due_date' => $request->validated('invoice_due_date')])->save();
            }
            if ($request->filled('expected_delivery_date')) {
                $restaurantOrder->forceFill(['expected_delivery_date' => $request->validated('expected_delivery_date')])->save();
            }

            $preview = $restaurantOrder->invoice_number === null
                ? $this->invoices->nextInvoiceNumberPreview()
                : null;
            $pdf = $this->invoices->renderPdf($restaurantOrder->refresh(), $preview)->output();
        } finally {
            DB::rollBack();
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="erdgut-rechnung-vorschau-'.$restaurantOrder->id.'.pdf"',
        ]);
    }

    /**
     * PDF exactly as it would print right now: the real document once
     * issued, a clearly marked draft with the upcoming number before.
     */
    public function invoicePreviewPdf(RestaurantOrder $restaurantOrder): SymfonyResponse
    {
        $this->authorize('admin');

        $preview = $restaurantOrder->invoice_number === null
            ? $this->invoices->nextInvoiceNumberPreview()
            : null;

        return $this->invoices->renderPdf($restaurantOrder, $preview)
            ->stream('erdgut-rechnung-vorschau-'.$restaurantOrder->id.'.pdf');
    }

    /**
     * Align the yearly invoice-number sequence so the NEXT invoice gets the
     * entered number. Needed because invoices were issued manually before the
     * system took over and the counter must continue, not restart.
     */
    public function setInvoiceSequence(Request $request): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'next_invoice_number' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $this->invoices->setNextSequenceNumber((int) $validated['next_invoice_number']);

        AuditLogger::log(
            entityType: 'number_sequence',
            entityId: (int) now()->year,
            action: 'set_invoice_sequence',
            fromState: null,
            toState: (string) $validated['next_invoice_number'],
            metadata: ['channel' => 'admin', 'next_number' => $this->invoices->nextInvoiceNumberPreview()],
            actorUserId: (int) $request->user()->id,
        );

        return back()->with('status', __('restaurant.admin_invoice_sequence_set_flash', [
            'number' => $this->invoices->nextInvoiceNumberPreview(),
        ]));
    }

    public function issueInvoice(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        abort_if($restaurantOrder->isInvoiced(), 403);
        abort_unless($restaurantOrder->isInvoiceable(), 403);

        try {
            $this->invoices->issueInvoice($restaurantOrder);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('app.admin.restaurant-orders.show', $restaurantOrder)
                ->with('status', $e->getMessage());
        }

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $restaurantOrder->id,
            action: 'invoice',
            fromState: $restaurantOrder->status->value,
            toState: $restaurantOrder->status->value,
            metadata: ['channel' => 'admin', 'invoice_number' => $restaurantOrder->invoice_number],
            actorUserId: (int) $request->user()->id,
        );

        // No auto-mail: the admin reviews and sends the invoice email in the
        // mail editor (precrafted text, editable subject/recipients/body).
        return redirect()
            ->route('app.admin.shop-orders.invoice-mail', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_issued_flash', ['number' => $restaurantOrder->invoice_number]));
    }

    /**
     * WYSIWYG mail editor for the invoice email: precrafted German du-text
     * (English template one click away), editable subject, body and
     * recipients (several possible). Reachable right after issuing and any
     * time later for a resend.
     */
    public function invoiceMailEditor(RestaurantOrder $restaurantOrder): View
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 403);

        $restaurantOrder->load('user.restaurantProfile');

        return view('admin.shop-orders.invoice-mail', [
            'recipients' => (string) ($restaurantOrder->user?->email ?? ''),
            'templates' => $this->invoiceMailTemplates($restaurantOrder),
            'backUrl' => $this->orderContextUrl($restaurantOrder),
            'sendUrl' => route('app.admin.shop-orders.invoice-mail.send', $restaurantOrder),
            'number' => (string) $restaurantOrder->invoice_number,
            'attachmentName' => 'erdgut-rechnung-'.$restaurantOrder->invoice_number.'.pdf',
        ]);
    }

    /** Send the composed invoice email to every recipient, PDF attached. */
    public function sendInvoiceMail(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 403);

        $validated = $request->validate([
            'recipients' => ['required', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // One field, several recipients: split on commas/semicolons and
        // validate each address individually.
        $recipients = collect(preg_split('/[,;]+/', $validated['recipients']))
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique()
            ->values();

        $invalid = $recipients->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false);
        if ($recipients->isEmpty() || $invalid->isNotEmpty()) {
            return back()->withInput()->withErrors([
                'recipients' => __('restaurant.invoice_mail_invalid_recipients', ['emails' => $invalid->implode(', ')]),
            ]);
        }

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->queue(new RestaurantInvoiceCustomMail(
                    $restaurantOrder,
                    $validated['subject'],
                    $validated['body'],
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $restaurantOrder->id,
            action: 'invoice_mail_sent',
            fromState: $restaurantOrder->status->value,
            toState: $restaurantOrder->status->value,
            metadata: [
                'channel' => 'admin',
                'invoice_number' => $restaurantOrder->invoice_number,
                'recipients' => $recipients->all(),
                'subject' => $validated['subject'],
            ],
            actorUserId: (int) $request->user()->id,
        );

        return redirect($this->orderContextUrl($restaurantOrder))
            ->with('status', __('restaurant.invoice_mail_sent_flash', ['recipients' => $recipients->implode(', ')]));
    }

    /**
     * Where invoice pages lead back to: the mirror ShopOrder in the unified
     * orders list when one exists (firm orders always mirror), otherwise the
     * restaurant request page. Keeps the admin in the Bestellungen context;
     * invoicing has nothing to do with the requests queue.
     */
    private function orderContextUrl(RestaurantOrder $order): string
    {
        $mirror = ShopOrder::query()->where('restaurant_order_id', $order->id)->first();

        return $mirror !== null
            ? route('app.admin.shop-orders.show', $mirror)
            : route('app.admin.restaurant-orders.show', $order);
    }

    /**
     * Precrafted subject + body in German (informal du) and English; the
     * mail editor starts on German and swaps the texts on button click.
     *
     * @return array<string, array{subject: string, body: string}>
     */
    private function invoiceMailTemplates(RestaurantOrder $order): array
    {
        $name = $order->user?->restaurantProfile?->contact_name
            ?: ($order->user?->restaurantProfile?->business_name ?? $order->user?->name ?? '');
        $number = (string) $order->invoice_number;
        $total = number_format((float) $order->items_total_gross_eur, 2, ',', '.').' €';
        $totalEn = number_format((float) $order->items_total_gross_eur, 2, '.', ',').' EUR';
        $deliveryDe = $order->expected_delivery_date?->format('d.m.Y') ?? '';
        $deliveryEn = $order->expected_delivery_date?->format('d/m/Y') ?? '';
        $dueDe = $order->invoice_due_date?->format('d.m.Y') ?? '';
        $dueEn = $order->invoice_due_date?->format('d/m/Y') ?? '';

        return [
            'de' => [
                'subject' => 'Deine Rechnung '.$number.' von Erdgut',
                'body' => 'Hallo'.($name !== '' ? ' '.$name : '').",\n\n"
                    ."anbei findest du die Rechnung {$number} zu deiner Lieferung vom {$deliveryDe} über {$total}.\n\n"
                    ."Der Betrag ist bis zum {$dueDe} auf das in der Rechnung angegebene Konto zahlbar. Bitte gib die Rechnungsnummer als Verwendungszweck an.\n\n"
                    ."Wenn etwas nicht stimmt oder du Fragen hast, melde dich gern direkt bei uns.\n\n"
                    ."Herzliche Grüße\ndein Erdgut-Team",
            ],
            'en' => [
                'subject' => 'Your invoice '.$number.' from Erdgut',
                'body' => 'Hello'.($name !== '' ? ' '.$name : '').",\n\n"
                    ."please find attached invoice {$number} for your delivery of {$deliveryEn}, totalling {$totalEn}.\n\n"
                    ."The amount is due by {$dueEn} to the bank account stated on the invoice. Please use the invoice number as the payment reference.\n\n"
                    ."If anything looks off or you have questions, just reply to this email.\n\n"
                    ."Best regards\nyour Erdgut team",
            ],
        ];
    }

    /** Mark an issued invoice as paid (bank transfer arrived). Idempotent. */
    public function markPaid(Request $request, RestaurantOrder $restaurantOrder): RedirectResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 403);

        if (! $restaurantOrder->isInvoicePaid()) {
            $restaurantOrder->forceFill(['invoice_paid_at' => now()])->save();

            AuditLogger::log(
                entityType: 'restaurant_order',
                entityId: (int) $restaurantOrder->id,
                action: 'invoice_paid',
                fromState: $restaurantOrder->status->value,
                toState: $restaurantOrder->status->value,
                metadata: ['channel' => 'admin', 'invoice_number' => $restaurantOrder->invoice_number],
                actorUserId: (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('app.admin.restaurant-orders.show', $restaurantOrder)
            ->with('status', __('restaurant.admin_invoice_paid_flash', ['number' => $restaurantOrder->invoice_number]));
    }

    public function invoicePdf(RestaurantOrder $restaurantOrder): SymfonyResponse
    {
        $this->authorize('admin');
        abort_unless($restaurantOrder->isInvoiced(), 404);

        return $this->invoices->renderPdf($restaurantOrder)
            ->download('erdgut-rechnung-'.$restaurantOrder->invoice_number.'.pdf');
    }
}
