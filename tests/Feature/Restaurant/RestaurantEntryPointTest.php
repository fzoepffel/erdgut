<?php

namespace Tests\Feature\Restaurant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantEntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_surface_links_to_restaurant_register(): void
    {
        // Shop home carries the shop header + marketing footer.
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee(route('restaurant.register'), false)
            ->assertSee(__('restaurant.nav_public'));
    }

    public function test_register_page_cross_links_to_login(): void
    {
        $this->get(route('restaurant.register'))
            ->assertOk()
            ->assertSee(route('auth.login'), false);
    }

    public function test_login_page_still_links_to_register(): void
    {
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee(route('restaurant.register'), false);
    }
}
