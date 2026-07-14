<?php

namespace Tests\Unit\Enums;

use App\Enums\CountUnitLabel;
use Tests\TestCase;

/** Display words for piece products: keys resolve, plurals are correct. */
class CountUnitLabelTest extends TestCase
{
    public function test_every_case_resolves_in_all_three_locales(): void
    {
        foreach (CountUnitLabel::cases() as $case) {
            foreach (['de', 'en', 'fr'] as $locale) {
                $word = $case->label(1, $locale);
                $this->assertNotSame('', $word);
                $this->assertStringNotContainsString('units.', $word, $case->value.' missing in '.$locale);
                $this->assertStringNotContainsString('|', $word);
            }
        }
    }

    public function test_german_plurals(): void
    {
        $this->assertSame('Topf', CountUnitLabel::Pot->label(1, 'de'));
        $this->assertSame('Töpfe', CountUnitLabel::Pot->label(3, 'de'));
        $this->assertSame('Glas', CountUnitLabel::Jar->label(1, 'de'));
        $this->assertSame('Gläser', CountUnitLabel::Jar->label(2, 'de'));
        $this->assertSame('Beutel', CountUnitLabel::Bag->label(1, 'de'));
        $this->assertSame('Beutel', CountUnitLabel::Bag->label(5, 'de'));
    }

    public function test_french_irregular_plural(): void
    {
        $this->assertSame('Bocal', CountUnitLabel::Jar->label(1, 'fr'));
        $this->assertSame('Bocaux', CountUnitLabel::Jar->label(4, 'fr'));
    }
}
