<?php

namespace Tests\Unit\Support;

use App\Enums\ProductCategory;
use App\Support\ProductCategoryStyle;
use PHPUnit\Framework\TestCase;

class ProductCategoryStyleTest extends TestCase
{
    public function test_vegetables_and_fruit_map_to_produce_chapter(): void
    {
        $veg = ProductCategoryStyle::for(ProductCategory::Vegetables);
        $fruit = ProductCategoryStyle::for(ProductCategory::Fruit);

        $this->assertSame('produce', $veg['chapter']);
        $this->assertSame('produce', $fruit['chapter']);
        $this->assertSame('#21341d', $veg['dark']);
        $this->assertSame('#709452', $veg['soft']);
        $this->assertSame('filled', $veg['variant']);
        $this->assertStringContainsString('bg-chapter-produce', $veg['badge_classes']);
        $this->assertSame('onion', $veg['mark']);
    }

    public function test_fish_uses_fish_chapter_palette(): void
    {
        $style = ProductCategoryStyle::for(ProductCategory::Fish);

        $this->assertSame('fish', $style['chapter']);
        $this->assertSame('#1d4485', $style['dark']);
        $this->assertSame('#a7d4e3', $style['soft']);
        $this->assertSame('filled', $style['variant']);
        $this->assertSame('bg-chapter-fish text-brand-cream', $style['badge_classes']);
        $this->assertSame('scallop', $style['mark']);
    }

    public function test_meat_uses_meat_chapter_palette(): void
    {
        $style = ProductCategoryStyle::for(ProductCategory::Meat);

        $this->assertSame('meat', $style['chapter']);
        $this->assertSame('#ac273b', $style['dark']);
        $this->assertSame('#e1b4b6', $style['soft']);
        $this->assertSame('filled', $style['variant']);
        $this->assertSame('bg-chapter-meat text-brand-cream', $style['badge_classes']);
        $this->assertSame('beef', $style['mark']);
    }

    public function test_dairy_maps_to_eggdairy_chapter(): void
    {
        $dairy = ProductCategoryStyle::for(ProductCategory::Dairy);

        $this->assertSame('eggdairy', $dairy['chapter']);
        $this->assertSame('#e46f25', $dairy['dark']);
        $this->assertSame('#e6c0a8', $dairy['soft']);
        $this->assertSame('filled', $dairy['variant']);
        $this->assertStringContainsString('bg-chapter-eggdairy', $dairy['badge_classes']);
        $this->assertSame('cheese', $dairy['mark']);
    }

    public function test_bakery_maps_to_bread_chapter(): void
    {
        $bakery = ProductCategoryStyle::for(ProductCategory::Bakery);

        $this->assertSame('bread', $bakery['chapter']);
        $this->assertSame('#944e22', $bakery['dark']);
        $this->assertSame('#dec1ae', $bakery['soft']);
        $this->assertSame('filled', $bakery['variant']);
        $this->assertStringContainsString('bg-chapter-bread', $bakery['badge_classes']);
        $this->assertSame('wheat', $bakery['mark']);
    }

    public function test_text_class_for_chapter_is_deterministic(): void
    {
        $this->assertSame('text-chapter-fish', ProductCategoryStyle::textClassForChapter('fish'));
        $this->assertSame('text-chapter-meat', ProductCategoryStyle::textClassForChapter('meat'));
        $this->assertSame('text-chapter-produce', ProductCategoryStyle::textClassForChapter('produce'));
        $this->assertSame('text-chapter-eggdairy', ProductCategoryStyle::textClassForChapter('eggdairy'));
        $this->assertSame('text-chapter-eggdairy', ProductCategoryStyle::textClassForChapter('dairy'));
        $this->assertSame('text-chapter-bread', ProductCategoryStyle::textClassForChapter('bread'));
        $this->assertSame('text-chapter-bread', ProductCategoryStyle::textClassForChapter('bakery'));
        $this->assertSame('text-chapter-producers', ProductCategoryStyle::textClassForChapter('producers'));
        $this->assertSame('text-chapter-almanach', ProductCategoryStyle::textClassForChapter('almanach'));
        $this->assertSame('text-brand-ink', ProductCategoryStyle::textClassForChapter('anything-else'));
    }

    public function test_every_enum_case_is_mapped(): void
    {
        foreach (ProductCategory::cases() as $case) {
            $style = ProductCategoryStyle::for($case);
            $this->assertArrayHasKey('chapter', $style);
            $this->assertArrayHasKey('dark', $style);
            $this->assertArrayHasKey('soft', $style);
            $this->assertArrayHasKey('variant', $style);
            $this->assertArrayHasKey('badge_classes', $style);
            $this->assertArrayHasKey('mark', $style);
            $this->assertContains($style['variant'], ['filled', 'outlined']);
        }
    }
}
