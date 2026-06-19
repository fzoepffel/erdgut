<?php

namespace App\Support;

use App\Enums\ProductUnit;
use App\Providers\AppServiceProvider;

/**
 * Centralised, locale-aware currency formatting for Erdgut.
 *
 * Currency renders in the active locale's number convention so a price reads
 * naturally in whatever language the page is shown:
 *   - de → "1.234,50 €"  (period thousands, comma decimal)
 *   - en → "1,234.50 €"  (comma thousands, period decimal)
 *   - fr → "1 234,50 €"  (space thousands, comma decimal)
 *
 * This is the single source of truth for number separators: {@see separators()}
 * is reused by {@see ProductUnit::formatPrice()} and
 * {@see ProductUnit::formatQuantity()} so prices, per-unit prices and
 * quantities never mix conventions on the same page.
 *
 * The {@see AppServiceProvider} registers Blade directives `@eur`,
 * `@eurCents`, and `@money` for compactness in views.
 */
final class Money
{
    /**
     * Decimal + thousands separators for a locale (defaults to the active one).
     *
     * @return array{0: string, 1: string} [decimalSep, thousandsSep]
     */
    public static function separators(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return match ($locale) {
            'de' => [',', '.'],
            'fr' => [',', ' '],
            default => ['.', ','],
        };
    }

    /**
     * Format a EUR amount as "12,50 €" in the given (or active) locale.
     */
    public static function formatEur(float|int|string|null $amount, ?string $locale = null): string
    {
        return self::format($amount, 2, $locale).' €';
    }

    /**
     * Format an integer-cents amount as "12,50 €". Accepts null (renders as
     * "0,00 €") because some columns are nullable in the schema.
     */
    public static function formatCents(int|string|null $cents, ?string $locale = null): string
    {
        if ($cents === null) {
            return self::formatEur(0, $locale);
        }

        return self::formatEur(((int) $cents) / 100, $locale);
    }

    /**
     * Format a numeric amount with no currency symbol (e.g. "12,50"), in the
     * given (or active) locale. Use as the `:amount` placeholder in translation
     * strings that already carry the "EUR" prefix or "€" suffix.
     */
    public static function format(float|int|string|null $amount, int $decimals = 2, ?string $locale = null): string
    {
        [$decimalSep, $thousandsSep] = self::separators($locale);

        return number_format((float) ($amount ?? 0), $decimals, $decimalSep, $thousandsSep);
    }
}
