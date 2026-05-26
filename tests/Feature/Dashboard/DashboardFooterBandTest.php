<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardFooterBandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_active_subscriber_sees_editorial_footer_band(): void
    {
        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-editorial-footer-band', false)
            ->assertSee(__('customer.dashboard.footer_band.statement'))
            // Nav mirrors the standard <x-app-footer>'s "Das Buch" column —
            // Über uns / Produzenten / Kontakt — plus a logout button.
            ->assertSee(__('About'))
            ->assertSee(__('Producers'))
            ->assertSee(__('Contact'))
            ->assertSee(__('customer.dashboard.footer_band.nav_logout'));
    }

    public function test_new_visitor_does_not_see_editorial_footer_band(): void
    {
        // No subscription, no trial → NewVisitor state. The editorial band
        // is part of the redesigned dashboard for steady-state subscribers;
        // out-of-scope kinds keep the standard <x-app-footer> only.
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('customer-editorial-footer-band', false);
    }
}
