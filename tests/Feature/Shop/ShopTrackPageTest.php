<?php

namespace Tests\Feature\Shop;

use App\Enums\ShopOrderStatus;
use App\Mail\ShopOrderWindowMail;
use App\Models\ShopOrder;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Public, signed-URL order tracking page: access control + the two
 * LiveStatus states (window pending vs set) for delivery and pickup.
 */
class ShopTrackPageTest extends TestCase
{
    use RefreshDatabase;

    private function signedTrackUrl(ShopOrder $order): string
    {
        return URL::signedRoute('public.shop.track', ['shopOrder' => $order->id]);
    }

    public function test_unsigned_url_is_forbidden(): void
    {
        $order = ShopOrder::factory()->paid()->create();

        $this->get(route('public.shop.track', ['shopOrder' => $order->id]))
            ->assertForbidden();
    }

    public function test_signed_url_renders_order_details(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'name' => 'Anna Mustermann',
            'fulfillment_mode' => 'delivery',
        ]);

        $this->get($this->signedTrackUrl($order))
            ->assertOk()
            ->assertSee('Anna Mustermann')
            ->assertSee(ShopOrderService::contactPhone($order))
            ->assertSee(__('shop.contact_email'));
    }

    public function test_pending_state_shown_when_no_window(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'delivery_window_from' => null,
            'delivery_window_to' => null,
        ]);

        $this->get($this->signedTrackUrl($order))
            ->assertOk()
            ->assertSee(__('shop.track_pending_eyebrow_delivery'))
            ->assertSee(__('shop.track_pending_helper_delivery'))
            ->assertDontSee(__('shop.track_set_helper_delivery'));
    }

    public function test_set_state_shows_window_for_delivery(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'delivery',
            'delivery_window_from' => '14:00',
            'delivery_window_to' => '16:00',
        ]);

        $this->get($this->signedTrackUrl($order))
            ->assertOk()
            ->assertSee(__('shop.track_set_eyebrow_delivery'))
            ->assertSee('14:00 – 16:00')
            ->assertSee(__('shop.track_set_helper_delivery'));
    }

    public function test_set_state_shows_pickup_copy(): void
    {
        $order = ShopOrder::factory()->paid()->create([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'delivery_window_from' => '10:00',
            'delivery_window_to' => '12:00',
            'status' => ShopOrderStatus::Paid,
        ]);

        $this->get($this->signedTrackUrl($order))
            ->assertOk()
            ->assertSee(__('shop.track_set_eyebrow_pickup'))
            ->assertSee('10:00 – 12:00');
    }

    public function test_contact_phone_depends_on_fulfillment_context(): void
    {
        $delivery = ShopOrder::factory()->paid()->create(['fulfillment_mode' => 'delivery']);
        $karlKunger = ShopOrder::factory()->paid()->create(['fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger']);
        $gotenstrasse = ShopOrder::factory()->paid()->create(['fulfillment_mode' => 'pickup', 'pickup_location' => 'gotenstrasse']);

        $this->get($this->signedTrackUrl($delivery))->assertOk()->assertSee('+33 6 36 31 55 15');
        $this->get($this->signedTrackUrl($karlKunger))->assertOk()->assertSee('+33 6 36 31 55 15');
        $this->get($this->signedTrackUrl($gotenstrasse))->assertOk()->assertSee('+33 7 83 38 85 93');
    }

    public function test_window_email_renders_window_without_leaking_translation_keys(): void
    {
        foreach (['de', 'en', 'fr'] as $locale) {
            $this->app->setLocale($locale);

            $order = ShopOrder::factory()->paid()->create([
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'delivery_window_from' => '10:00',
                'delivery_window_to' => '12:00',
            ]);

            $html = (new ShopOrderWindowMail($order))->render();

            $this->assertStringContainsString('10:00 – 12:00', $html);
            $this->assertStringNotContainsString('shop.window_email', $html, "Leaked a translation key in [$locale]");
            $this->assertStringNotContainsString('shop.track_', $html, "Leaked a translation key in [$locale]");
        }
    }
}
