<?php

namespace Tests\Unit\Support;

use App\Enums\ProductCategory;
use App\Support\ProductCategoryStyle;
use PHPUnit\Framework\TestCase;

class ProductCategoryStyleTest extends TestCase
{
    public function test_vegetables_and_fruit_map_to_produce_stall(): void
    {
        $veg = ProductCategoryStyle::for(ProductCategory::Vegetables);
        $fruit = ProductCategoryStyle::for(ProductCategory::Fruit);

        $this->assertSame('produce', $veg['stall']);
        $this->assertSame('produce', $fruit['stall']);
        $this->assertSame('#21341d', $veg['dark']);
        $this->assertSame('#79954d', $veg['soft']);
        $this->assertSame('filled', $veg['variant']);
        $this->assertStringContainsString('bg-category-produce', $veg['badge_classes']);
    }

    public function test_fish_uses_fishmonger_palette(): void
    {
        $style = ProductCategoryStyle::for(ProductCategory::Fish);

        $this->assertSame('fish', $style['stall']);
        $this->assertSame('#1c397d', $style['dark']);
        $this->assertSame('#b5daea', $style['soft']);
        $this->assertSame('filled', $style['variant']);
        $this->assertSame('bg-category-fish text-white', $style['badge_classes']);
    }

    public function test_meat_uses_butcher_palette(): void
    {
        $style = ProductCategoryStyle::for(ProductCategory::Meat);

        $this->assertSame('meat', $style['stall']);
        $this->assertSame('#ae2033', $style['dark']);
        $this->assertSame('#e5bcc0', $style['soft']);
        $this->assertSame('filled', $style['variant']);
        $this->assertSame('bg-category-meat text-white', $style['badge_classes']);
    }

    public function test_dairy_and_bakery_are_outlined_ink(): void
    {
        $dairy = ProductCategoryStyle::for(ProductCategory::Dairy);
        $bakery = ProductCategoryStyle::for(ProductCategory::Bakery);

        $this->assertSame('outlined', $dairy['variant']);
        $this->assertSame('outlined', $bakery['variant']);
        $this->assertStringContainsString('border-brand-ink', $dairy['badge_classes']);
        $this->assertStringContainsString('border-brand-ink', $bakery['badge_classes']);
    }

    public function test_text_class_for_stall_is_deterministic(): void
    {
        $this->assertSame('text-category-fish', ProductCategoryStyle::textClassForStall('fish'));
        $this->assertSame('text-category-meat', ProductCategoryStyle::textClassForStall('meat'));
        $this->assertSame('text-category-produce', ProductCategoryStyle::textClassForStall('produce'));
        $this->assertSame('text-brand-ink', ProductCategoryStyle::textClassForStall('dairy'));
        $this->assertSame('text-brand-ink', ProductCategoryStyle::textClassForStall('bakery'));
        $this->assertSame('text-brand-ink', ProductCategoryStyle::textClassForStall('anything-else'));
    }

    public function test_every_enum_case_is_mapped(): void
    {
        foreach (ProductCategory::cases() as $case) {
            $style = ProductCategoryStyle::for($case);
            $this->assertArrayHasKey('stall', $style);
            $this->assertArrayHasKey('dark', $style);
            $this->assertArrayHasKey('soft', $style);
            $this->assertArrayHasKey('variant', $style);
            $this->assertArrayHasKey('badge_classes', $style);
            $this->assertContains($style['variant'], ['filled', 'outlined']);
        }
    }
}
