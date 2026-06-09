<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Tests\TestCase;

class ProductShopTasteTagsTest extends TestCase
{
    public function test_shop_taste_tags_merge_enum_then_custom_and_cap_at_three(): void
    {
        $product = new Product;
        $product->taste_profile_tags = ['sweet', 'crisp', 'earthy', 'fresh'];
        $product->custom_taste_tags = [
            ['de' => 'Waldig', 'en' => 'Woody', 'fr' => 'Boisé'],
            ['de' => 'Saftig', 'en' => 'Juicy', 'fr' => 'Juteux'],
        ];

        $this->assertSame(
            ['Sweet', 'Crisp', 'Earthy'],
            $product->shopTasteTags(3, 'en'),
        );
    }

    public function test_shop_taste_tags_include_custom_when_enum_slots_remain(): void
    {
        $product = new Product;
        $product->taste_profile_tags = ['sweet'];
        $product->custom_taste_tags = [
            ['de' => 'Waldig', 'en' => 'Woody', 'fr' => 'Boisé'],
        ];

        $this->assertSame(['Sweet', 'Woody'], $product->shopTasteTags(3, 'en'));
    }

    public function test_shop_taste_tags_returns_empty_when_limit_is_zero(): void
    {
        $product = new Product;
        $product->taste_profile_tags = ['sweet'];

        $this->assertSame([], $product->shopTasteTags(0, 'en'));
    }
}
