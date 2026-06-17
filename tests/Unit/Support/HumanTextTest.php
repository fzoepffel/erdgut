<?php

namespace Tests\Unit\Support;

use App\Support\HumanText;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HumanTextTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_normalize_decodes_accidental_escapes(string $input, string $expected): void
    {
        $this->assertSame($expected, HumanText::normalize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizationCases(): array
    {
        return [
            'json unicode apostrophe' => [
                'd\\u0027asperge',
                "d'asperge",
            ],
            'html entity apostrophe' => [
                'this week&#039;s harvest',
                "this week's harvest",
            ],
            'double html entity' => [
                'this week&amp;#039;s harvest',
                "this week's harvest",
            ],
            'french asparagus excerpt' => [
                'Fraîches et noisettées avec une nette note d\\u0027asperge — l\\u0027épluchage est rarement nécessaire.',
                "Fraîches et noisettées avec une nette note d'asperge — l'épluchage est rarement nécessaire.",
            ],
            'plain text unchanged' => [
                "Plain apostrophe's and em-dash — stay put.",
                "Plain apostrophe's and em-dash — stay put.",
            ],
        ];
    }

    public function test_needs_normalization(): void
    {
        $this->assertTrue(HumanText::needsNormalization('d\\u0027asperge'));
        $this->assertFalse(HumanText::needsNormalization("d'asperge"));
        $this->assertFalse(HumanText::needsNormalization(''));
        $this->assertFalse(HumanText::needsNormalization(null));
    }
}
