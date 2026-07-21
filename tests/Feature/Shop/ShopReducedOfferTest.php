<?php

namespace Tests\Feature\Shop;

use App\Mail\ShopOfferReopenedMail;
use App\Models\ShopOfferSubscriber;
use App\Models\ShopReducedOfferPeriod;
use App\Models\ShopReducedOfferRule;
use App\Models\User;
use App\Services\Shop\ShopReducedOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reduced-offer mode: the shop stays shoppable but shows a banner + countdown
 * while only a small selection is in stock, with a persistent email waitlist
 * that is notified when the full offer returns.
 */
class ShopReducedOfferTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ShopReducedOfferService
    {
        // Fresh instance each call so the per-request rule cache never leaks
        // across a time change within a single test.
        return new ShopReducedOfferService;
    }

    private function weeklyRule(): ShopReducedOfferRule
    {
        // Reduced Mon 14:00 → reopens Fri 16:00.
        return ShopReducedOfferRule::query()->create([
            'starts_weekday' => Carbon::MONDAY,
            'starts_time' => '14:00:00',
            'reopens_weekday' => Carbon::FRIDAY,
            'reopens_time' => '16:00:00',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- Recurring window --------------------------------------------------

    public function test_recurring_rule_is_reduced_inside_window(): void
    {
        $this->weeklyRule();
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00')); // Wednesday

        $service = $this->service();
        $this->assertTrue($service->isReduced());
        $this->assertSame(
            '2026-07-24 16:00',
            $service->reopenAt()->format('Y-m-d H:i'),
            'Countdown should target the coming Friday 16:00.',
        );
    }

    public function test_recurring_rule_is_full_after_reopen(): void
    {
        $this->weeklyRule();
        Carbon::setTestNow(Carbon::parse('2026-07-25 10:00')); // Saturday, after Fri 16:00

        $this->assertFalse($this->service()->isReduced());
    }

    public function test_recurring_rule_flips_exactly_at_reopen_time(): void
    {
        $this->weeklyRule();

        Carbon::setTestNow(Carbon::parse('2026-07-24 15:59')); // Friday, one minute before
        $this->assertTrue($this->service()->isReduced());

        Carbon::setTestNow(Carbon::parse('2026-07-24 16:00')); // Friday, at reopen
        $this->assertFalse($this->service()->isReduced());
    }

    public function test_inactive_rule_never_reduces(): void
    {
        $rule = $this->weeklyRule();
        $rule->update(['is_active' => false]);
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00'));

        $this->assertFalse($this->service()->isReduced());
    }

    // ---- Manual periods ----------------------------------------------------

    public function test_manual_period_reduces_and_wins_over_full_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00'));
        $service = $this->service();
        $service->startManual(Carbon::now(), Carbon::parse('2026-07-23 09:00'));

        $this->assertTrue($service->isReduced());
        $this->assertSame('2026-07-23 09:00', $service->reopenAt()->format('Y-m-d H:i'));
    }

    // ---- Open now ----------------------------------------------------------

    public function test_open_now_ends_window_and_notifies_waitlist(): void
    {
        Mail::fake();
        $this->weeklyRule();
        ShopOfferSubscriber::query()->create(['email' => 'a@example.com']);
        ShopOfferSubscriber::query()->create(['email' => 'b@example.com']);

        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00')); // Wednesday, reduced
        $service = $this->service();
        $this->assertTrue($service->isReduced());

        $period = $service->openNow();
        $this->assertNotNull($period);
        $sent = $service->notifySubscribers($period);

        $this->assertSame(2, $sent);
        Mail::assertQueued(ShopOfferReopenedMail::class, 0); // sent, not queued
        Mail::assertSent(ShopOfferReopenedMail::class, 2);

        // The shop is no longer reduced after opening early (same instant).
        $this->assertFalse($this->service()->isReduced());
    }

    public function test_notify_is_idempotent_per_period(): void
    {
        Mail::fake();
        ShopOfferSubscriber::query()->create(['email' => 'a@example.com']);
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00'));

        $service = $this->service();
        $period = $service->startManual(Carbon::now()->subHour(), Carbon::now()->addHour());

        $this->assertSame(1, $service->notifySubscribers($period));
        $this->assertSame(0, $service->notifySubscribers($period->fresh()));
        Mail::assertSent(ShopOfferReopenedMail::class, 1);
    }

    public function test_process_due_reopens_notifies_once_for_recurring_cycle(): void
    {
        Mail::fake();
        $this->weeklyRule();
        ShopOfferSubscriber::query()->create(['email' => 'a@example.com']);

        // Saturday: the Friday 16:00 reopen just passed within the look-back.
        Carbon::setTestNow(Carbon::parse('2026-07-25 09:00'));

        $this->assertSame(1, $this->service()->processDueReopens());
        // Running again in the same cycle must not re-notify.
        $this->assertSame(0, $this->service()->processDueReopens());
        Mail::assertSent(ShopOfferReopenedMail::class, 1);
        $this->assertSame(1, ShopReducedOfferPeriod::query()->where('source', 'recurring')->count());
    }

    // ---- Storefront banner -------------------------------------------------

    public function test_banner_shows_on_shop_when_reduced(): void
    {
        $this->weeklyRule();
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00'));

        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertSee('data-testid="reduced-offer-banner"', false);
    }

    public function test_banner_hidden_on_shop_when_full(): void
    {
        $this->weeklyRule();
        Carbon::setTestNow(Carbon::parse('2026-07-25 12:00')); // Saturday, full offer

        $this->get(route('public.shop.index'))
            ->assertOk()
            ->assertDontSee('data-testid="reduced-offer-banner"', false);
    }

    public function test_window_lookup_degrades_gracefully_when_tables_missing(): void
    {
        // A decorative banner must never take the shop (or its error pages)
        // down: if the query fails (e.g. tables not yet migrated), show none.
        Schema::drop('shop_reduced_offer_periods');
        Schema::drop('shop_reduced_offer_rules');

        $this->assertNull($this->service()->currentWindow());
        $this->assertFalse($this->service()->isReduced());
    }

    // ---- Waitlist subscribe / unsubscribe ----------------------------------

    public function test_visitor_can_subscribe_to_waitlist(): void
    {
        $this->post(route('public.shop.notify.store'), ['email' => 'New@Example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('shop_offer_subscribers', ['email' => 'new@example.com']);
    }

    public function test_subscribe_is_idempotent_on_email(): void
    {
        ShopOfferSubscriber::query()->create(['email' => 'dup@example.com']);

        $this->post(route('public.shop.notify.store'), ['email' => 'dup@example.com'])
            ->assertRedirect();

        $this->assertSame(1, ShopOfferSubscriber::query()->where('email', 'dup@example.com')->count());
    }

    public function test_unsubscribe_link_removes_subscriber(): void
    {
        $subscriber = ShopOfferSubscriber::query()->create(['email' => 'bye@example.com']);

        $this->get(route('public.shop.notify.unsubscribe', ['token' => $subscriber->unsubscribe_token]))
            ->assertOk()
            ->assertSee(__('shop.reduced_offer.unsubscribed_title'));

        $this->assertDatabaseMissing('shop_offer_subscribers', ['email' => 'bye@example.com']);
    }

    public function test_unsubscribe_with_unknown_token_still_confirms(): void
    {
        $this->get(route('public.shop.notify.unsubscribe', ['token' => 'does-not-exist']))
            ->assertOk()
            ->assertSee(__('shop.reduced_offer.unsubscribed_title'));
    }

    // ---- Admin controls ----------------------------------------------------

    public function test_admin_can_start_manual_reduced_period(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.shop-closures.reduced.start'), [
                'reopens_at' => '2026-07-30T16:00',
            ])
            ->assertRedirect();

        $this->assertSame(1, ShopReducedOfferPeriod::query()->where('source', 'manual')->count());
    }

    public function test_admin_start_rejects_reopen_before_start(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('app.admin.shop-closures.index'))
            ->post(route('app.admin.shop-closures.reduced.start'), [
                'starts_at' => '2026-07-30T16:00',
                'reopens_at' => '2026-07-30T10:00',
            ])
            ->assertSessionHasErrors('reopens_at');

        $this->assertSame(0, ShopReducedOfferPeriod::query()->count());
    }

    public function test_admin_can_create_and_delete_recurring_rule(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.shop-closures.reduced.rules.store'), [
                'starts_weekday' => 1,
                'starts_time' => '14:00',
                'reopens_weekday' => 5,
                'reopens_time' => '16:00',
            ])
            ->assertRedirect();

        $rule = ShopReducedOfferRule::query()->firstOrFail();
        $this->assertSame('14:00:00', $rule->starts_time);

        $this->actingAs($admin)
            ->delete(route('app.admin.shop-closures.reduced.rules.destroy', ['rule' => $rule->id]))
            ->assertRedirect();

        $this->assertSame(0, ShopReducedOfferRule::query()->count());
    }

    public function test_open_now_endpoint_notifies_and_reports_count(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $this->weeklyRule();
        ShopOfferSubscriber::query()->create(['email' => 'a@example.com']);
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:00'));

        $this->actingAs($admin)
            ->post(route('app.admin.shop-closures.reduced.open-now'))
            ->assertRedirect();

        Mail::assertSent(ShopOfferReopenedMail::class, 1);
    }

    public function test_admin_page_lists_waitlist_emails(): void
    {
        $admin = User::factory()->admin()->create();
        ShopOfferSubscriber::query()->create(['email' => 'wait-a@example.com']);
        ShopOfferSubscriber::query()->create(['email' => 'wait-b@example.com']);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-closures.index'))
            ->assertOk()
            ->assertSee('wait-a@example.com')
            ->assertSee('wait-b@example.com')
            ->assertSee('2 people on the waitlist.');
    }

    public function test_subscriber_count_is_singularised(): void
    {
        $admin = User::factory()->admin()->create();
        ShopOfferSubscriber::query()->create(['email' => 'solo@example.com']);

        $this->actingAs($admin)
            ->get(route('app.admin.shop-closures.index'))
            ->assertOk()
            ->assertSee('1 person on the waitlist.')
            ->assertDontSee('1 people on the waitlist.');
    }

    public function test_reopen_mail_uses_personal_from_and_reply_to(): void
    {
        $subscriber = ShopOfferSubscriber::query()->create(['email' => 'x@example.com']);
        $envelope = (new ShopOfferReopenedMail($subscriber))->envelope();

        $from = $envelope->from;
        $this->assertNotNull($from);
        $this->assertSame('Fred von Erdgut', $from->name);
        $this->assertSame(config('mail.from.address'), $from->address);

        $this->assertSame(config('shop.company_email'), $envelope->replyTo[0]->address);
    }

    public function test_non_admin_cannot_manage_reduced_offer(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('app.admin.shop-closures.reduced.start'), ['reopens_at' => '2026-07-30T16:00'])
            ->assertForbidden();
    }
}
