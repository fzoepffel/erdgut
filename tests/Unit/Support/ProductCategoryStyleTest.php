<?php

namespace Tests\Unit\Support;

use App\Enums\ProductCategory;
use App\Support\ProductCategoryStyle;
use PHPUnit\Framework\TestCase;

class ProductCategoryStyleTest extends TestCase
{
    public function test_produce_uses_produce_chapter_palette(): void
    {
        // D6/D7/F8 (UX-review notes): the older Vegetables + Fruit
        // enum cases were merged into a single Produce case that maps
        // to the produce chapter and label "Obst & Gemüse".
        $produce = ProductCategoryStyle::for(ProductCategory::Produce);

        $this->assertSame('produce', $produce['chapter']);
        $this->assertSame('#21341d', $produce['dark']);
        $this->assertSame('#709452', $produce['soft']);
        $this->assertSame('filled', $produce['variant']);
        $this->assertStringContainsString('bg-chapter-produce', $produce['badge_classes']);
        $this->assertSame('onion', $produce['mark']);
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

    public function test_eggs_and_dairy_uses_eggdairy_chapter_palette(): void
    {
        $eggsAndDairy = ProductCategoryStyle::for(ProductCategory::EggsAndDairy);

        $this->assertSame('eggdairy', $eggsAndDairy['chapter']);
        $this->assertSame('#e46f25', $eggsAndDairy['dark']);
        $this->assertSame('#e6c0a8', $eggsAndDairy['soft']);
        $this->assertSame('filled', $eggsAndDairy['variant']);
        $this->assertStringContainsString('bg-chapter-eggdairy', $eggsAndDairy['badge_classes']);
        $this->assertSame('cheese', $eggsAndDairy['mark']);
    }

    public function test_bread_uses_bread_chapter_palette(): void
    {
        $bread = ProductCategoryStyle::for(ProductCategory::Bread);

        $this->assertSame('bread', $bread['chapter']);
        $this->assertSame('#944e22', $bread['dark']);
        $this->assertSame('#dec1ae', $bread['soft']);
        $this->assertSame('filled', $bread['variant']);
        $this->assertStringContainsString('bg-chapter-bread', $bread['badge_classes']);
        $this->assertSame('wheat', $bread['mark']);
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
