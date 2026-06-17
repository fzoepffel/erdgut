<?php

namespace Tests\Feature\Shop;

use App\Models\BoxWaitlistSignup;
use App\Models\ShopOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxWaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_form_stores_an_interested_signup(): void
    {
        $this->post(route('public.box-waitlist.store'), ['email' => 'Jane@Example.com'])
            ->assertRedirect(route('public.home'))
            ->assertSessionHas('status', 'box-waitlist-subscribed');

        $signup = BoxWaitlistSignup::sole();
        $this->assertSame('jane@example.com', $signup->email);
        $this->assertTrue($signup->interested);
        $this->assertSame('homepage', $signup->source);
    }

    public function test_homepage_form_rejects_an_invalid_email(): void
    {
        $this->post(route('public.box-waitlist.store'), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('box_waitlist_signups', 0);
    }

    public function test_homepage_honeypot_blocks_the_signup(): void
    {
        $this->post(route('public.box-waitlist.store'), [
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('box_waitlist_signups', 0);
    }

    public function test_repeated_homepage_signup_does_not_duplicate(): void
    {
        $payload = ['email' => 'dup@example.com'];
        $this->post(route('public.box-waitlist.store'), $payload);
        $this->post(route('public.box-waitlist.store'), $payload);

        $this->assertDatabaseCount('box_waitlist_signups', 1);
    }

    public function test_return_page_shows_prompt_when_email_not_recorded(): void
    {
        $order = ShopOrder::factory()->paid()->create(['email' => 'fresh@example.com']);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertViewHas('showBoxPrompt', true)
            ->assertSee('shop-return-box-prompt', escape: false);
    }

    public function test_return_page_hides_prompt_when_already_recorded(): void
    {
        $order = ShopOrder::factory()->paid()->create(['email' => 'known@example.com']);
        BoxWaitlistSignup::factory()->create(['email' => 'known@example.com']);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertViewHas('showBoxPrompt', false)
            ->assertDontSee('shop-return-box-prompt', escape: false);
    }

    public function test_checkout_prompt_records_a_yes_answer(): void
    {
        $order = ShopOrder::factory()->paid()->create(['email' => 'yes@example.com']);

        $this->post(route('public.shop.box-interest', ['shopOrder' => $order->id]), ['interested' => '1'])
            ->assertRedirect(route('public.shop.return', ['shopOrder' => $order->id]));

        $signup = BoxWaitlistSignup::sole();
        $this->assertSame('yes@example.com', $signup->email);
        $this->assertTrue($signup->interested);
        $this->assertSame('checkout', $signup->source);
    }

    public function test_checkout_prompt_records_a_no_answer(): void
    {
        $order = ShopOrder::factory()->paid()->create(['email' => 'no@example.com']);

        $this->post(route('public.shop.box-interest', ['shopOrder' => $order->id]), ['interested' => '0'])
            ->assertRedirect(route('public.shop.return', ['shopOrder' => $order->id]));

        $signup = BoxWaitlistSignup::sole();
        $this->assertFalse($signup->interested);
        $this->assertSame('checkout', $signup->source);
    }

    public function test_checkout_prompt_ignores_unpaid_online_orders(): void
    {
        // Online order still awaiting its SumUp charge — not complete.
        $order = ShopOrder::factory()->create([
            'email' => 'pending@example.com',
            'payment_method' => 'online',
        ]);

        $this->post(route('public.shop.box-interest', ['shopOrder' => $order->id]), ['interested' => '1']);

        $this->assertDatabaseCount('box_waitlist_signups', 0);
    }

    public function test_return_page_shows_prompt_for_pay_in_person_orders(): void
    {
        // Pay-in-person orders stay PendingPayment but are complete checkouts.
        $order = ShopOrder::factory()->create([
            'email' => 'cash@example.com',
            'payment_method' => 'in_person',
        ]);

        $this->get(route('public.shop.return', ['shopOrder' => $order->id]))
            ->assertOk()
            ->assertViewHas('showBoxPrompt', true)
            ->assertSee('shop-return-box-prompt', escape: false);
    }

    public function test_checkout_prompt_records_answer_for_pay_in_person_orders(): void
    {
        $order = ShopOrder::factory()->create([
            'email' => 'cash@example.com',
            'payment_method' => 'in_person',
        ]);

        $this->post(route('public.shop.box-interest', ['shopOrder' => $order->id]), ['interested' => '1'])
            ->assertRedirect(route('public.shop.return', ['shopOrder' => $order->id]));

        $signup = BoxWaitlistSignup::sole();
        $this->assertSame('cash@example.com', $signup->email);
        $this->assertTrue($signup->interested);
        $this->assertSame('checkout', $signup->source);
    }
}
