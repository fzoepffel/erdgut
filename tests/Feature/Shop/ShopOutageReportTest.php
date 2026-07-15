<?php

namespace Tests\Feature\Shop;

use App\Mail\ShopOutageReportedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The storefront's empty state doubles as an incident signal: the shop
 * is expected to always carry stock, so visitors can notify the admins
 * with one click (capped at one mail burst per hour).
 */
class ShopOutageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_shop_shows_the_outage_notice_and_button(): void
    {
        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee('data-testid="shop-empty"', false)
            ->assertSee(__('shop.empty_body'))
            ->assertSee('data-testid="shop-outage-button"', false)
            ->assertSee(route('public.shop.report-outage'), false);
    }

    public function test_pressing_the_button_mails_every_admin_once_per_hour(): void
    {
        Mail::fake();
        $admins = User::factory()->count(2)->admin()->create();
        User::factory()->create(['is_admin' => false]);

        $this->post(route('public.shop.report-outage'))
            ->assertRedirect(route('public.shop.index'))
            ->assertSessionHas('outage_reported', true);

        foreach ($admins as $admin) {
            Mail::assertQueued(ShopOutageReportedMail::class, fn ($mail) => $mail->hasTo($admin->email));
        }
        Mail::assertQueuedCount(2);

        // A second press within the hour thanks the visitor but sends nothing.
        $this->post(route('public.shop.report-outage'))
            ->assertRedirect(route('public.shop.index'))
            ->assertSessionHas('outage_reported', true);
        Mail::assertQueuedCount(2);
    }

    public function test_thanks_message_replaces_the_button_after_reporting(): void
    {
        $this->withSession(['_flash' => ['new' => [], 'old' => []]]);
        $response = $this->post(route('public.shop.report-outage'));
        $this->followRedirects($response)
            ->assertSee('data-testid="shop-outage-thanks"', false)
            ->assertSee(__('shop.outage_thanks'));
    }
}
