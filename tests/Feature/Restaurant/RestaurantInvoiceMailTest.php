<?php

namespace Tests\Feature\Restaurant;

use App\Enums\ProductUnit;
use App\Mail\RestaurantInvoiceCustomMail;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Restaurant\RestaurantInvoiceService;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The invoice mail editor: precrafted German du-text with an English
 * template one click away, editable subject/body, several recipients,
 * PDF attached. Nothing sends without the admin's explicit click.
 */
class RestaurantInvoiceMailTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $date;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = Carbon::tomorrow();
        $this->admin = User::factory()->admin()->create();
        Producer::factory()->create();
        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece',
            'unit_price' => 10.00, 'vat_rate_percent' => 7,
            'is_available' => true, 'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $this->product->id, 'received_on' => $this->date->toDateString(),
            'quantity' => 50, 'freshness_window_days' => 3,
        ]);
    }

    private function invoicedOrder(): RestaurantOrder
    {
        $profile = RestaurantProfile::factory()->verified()->create(['contact_name' => 'Kim']);
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);
        app(RestaurantOrderService::class)->confirm($order);
        $svc = app(RestaurantInvoiceService::class);
        $svc->updatePricing($order, [['id' => $order->items()->firstOrFail()->id, 'net_total_eur' => 100, 'vat_rate_percent' => 7]]);
        $svc->issueInvoice($order);

        return $order->refresh();
    }

    public function test_editor_prefills_recipient_and_german_du_template_with_english_variant(): void
    {
        $order = $this->invoicedOrder();

        $response = $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.invoice-mail', $order))
            ->assertOk()
            ->assertSee($order->user->email, false)
            ->assertSee('data-testid="invoice-mail-send"', false)
            ->assertSee('data-testid="invoice-mail-lang-en"', false);

        $html = $response->getContent();
        // German du-text and the English variant are both shipped to the page.
        $this->assertStringContainsString('findest du die Rechnung', $html);
        $this->assertStringContainsString('please find attached invoice', $html);
        $this->assertStringContainsString($order->invoice_number, $html);
    }

    public function test_mail_editor_requires_an_issued_invoice_and_admin(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = app(RestaurantOrderService::class)->submitOrder($profile->user, [
            'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 10]],
            'expected_delivery_date' => $this->date->toDateString(),
            'delivery_street' => 'S', 'delivery_postal_code' => '1', 'delivery_city' => 'B',
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.invoice-mail', $order))
            ->assertForbidden();

        $invoiced = $this->invoicedOrder();
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.shop-orders.invoice-mail', $invoiced))
            ->assertForbidden();
    }

    public function test_sending_queues_the_mail_to_every_recipient_with_pdf(): void
    {
        Mail::fake();
        $order = $this->invoicedOrder();

        // Redirects into the ORDER context: firm orders mirror into the
        // unified shop-orders list, so that is where the admin lands.
        $mirror = ShopOrder::query()->where('restaurant_order_id', $order->id)->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.invoice-mail.send', $order), [
                'recipients' => $order->user->email.', buchhaltung@technotofu.de',
                'subject' => 'Deine Rechnung '.$order->invoice_number,
                'body' => "Hallo Kim,\n\nanbei die Rechnung.\n\nHerzliche Grüße",
            ])
            ->assertRedirect(route('app.admin.shop-orders.show', $mirror));

        Mail::assertQueued(RestaurantInvoiceCustomMail::class, fn ($m) => $m->hasTo($order->user->email)
            && $m->mailSubject === 'Deine Rechnung '.$order->invoice_number);
        Mail::assertQueued(RestaurantInvoiceCustomMail::class, fn ($m) => $m->hasTo('buchhaltung@technotofu.de'));
        Mail::assertQueuedCount(2);

        // The attachment carries the invoice PDF.
        $mail = new RestaurantInvoiceCustomMail($order, 'Betreff', 'Text');
        $this->assertCount(1, $mail->attachments());

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'restaurant_order',
            'entity_id' => $order->id,
            'action' => 'invoice_mail_sent',
        ]);
    }

    public function test_invalid_recipient_is_rejected_and_nothing_sends(): void
    {
        Mail::fake();
        $order = $this->invoicedOrder();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.invoice-mail.send', $order), [
                'recipients' => 'kein-email-format',
                'subject' => 'Betreff',
                'body' => 'Text',
            ])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingQueued();
    }
}
