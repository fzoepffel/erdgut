<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop-layout backs both public catalog pages and private surfaces, so
 * indexing must be opt-in: public pages get `index, follow` + a meta
 * description, everything else stays `noindex`.
 */
class ShopSeoIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_shop_is_indexable_with_meta_description(): void
    {
        $response = $this->get(route('public.shop.index'));

        $response->assertOk()
            ->assertSee('index, follow', false)
            ->assertDontSee('noindex', false)
            ->assertSee('name="description"', false)
            // English copy renders under the test locale.
            ->assertSee('straight from the farm to the shop', false);
    }

    public function test_private_shop_layout_page_stays_noindex(): void
    {
        // The unsubscribe confirmation uses shop-layout without opting in.
        $response = $this->get(route('public.shop.notify.unsubscribe', ['token' => 'whatever']));

        $response->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('index, follow', false);
    }
}
