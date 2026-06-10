<?php

namespace Tests\Feature\Shop;

use App\Mail\ShopOrderConfirmationMail;
use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * PDF receipt (Kaufbeleg) download route and email attachment.
 */
class ShopReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function signedReceiptUrl(ShopOrder $order): string
    {
        return URL::signedRoute('public.shop.receipt', ['shopOrder' => $order->id]);
    }

    public function test_unsigned_receipt_url_is_forbidden(): void
    {
        $order = ShopOrder::factory()->paid()->create();

        $this->get(route('public.shop.receipt', ['shopOrder' => $order->id]))
            ->assertForbidden();
    }

    public function test_signed_receipt_url_returns_pdf(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'name' => 'Anna Mustermann',
        ]);

        $response = $this->get($this->signedReceiptUrl($order));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'erdgut-beleg-'.$order->id.'.pdf',
            $response->headers->get('Content-Disposition', ''),
        );
    }

    public function test_receipt_url_helper_generates_signed_url(): void
    {
        $order = ShopOrder::factory()->paid()->create();

        $url = ShopOrderService::receiptUrl($order);

        $this->assertStringContainsString(
            route('public.shop.receipt', ['shopOrder' => $order->id]),
            $url,
        );
        // Signed URLs carry the signature query param.
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_return_page_shows_receipt_download_button_for_paid_order(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
        ]);

        // Return page is accessible without a signed URL.
        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertSee(__('shop.receipt_download_button'))
            ->assertSee('shop-receipt-download', false);
    }

    public function test_confirmation_email_has_pdf_attachment(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'name' => 'Test Kunde',
        ]);

        $mailable = new ShopOrderConfirmationMail($order);
        $attachments = $mailable->attachments();

        $this->assertCount(1, $attachments);
    }
}
