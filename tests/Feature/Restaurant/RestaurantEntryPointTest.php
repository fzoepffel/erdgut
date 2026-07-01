<?php

namespace Tests\Feature\Restaurant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantEntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_surface_links_to_geschaeftskunden_landing(): void
    {
        // A quiet "Geschäftskunden" nav item + the footer both point to the
        // B2B landing page (which carries the login) — never a header login.
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(route('restaurant.landing'), false)
            ->assertSee(__('restaurant.nav_public'));
    }

    public function test_landing_page_links_to_apply_and_login(): void
    {
        $this->get(route('restaurant.landing'))
            ->assertOk()
            ->assertSee(route('restaurant.register'), false)
            ->assertSee(route('auth.login'), false)
            ->assertSee(__('restaurant.landing_title'));
    }

    public function test_register_page_cross_links_to_login(): void
    {
        $this->get(route('restaurant.register'))
            ->assertOk()
            ->assertSee(route('auth.login'), false);
    }

    public function test_register_submitted_page_renders(): void
    {
        $this->get(route('restaurant.register.submitted'))->assertOk();
    }

    public function test_login_page_still_links_to_register(): void
    {
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee(route('restaurant.register'), false);
    }
}
