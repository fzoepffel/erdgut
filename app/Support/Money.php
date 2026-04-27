<?php

namespace App\Support;

use App\Providers\AppServiceProvider;

/**
 * Centralised currency formatting for Erdgut.
 *
 * Until now, currency was rendered inline via `number_format()` in 30+ Blade
 * templates with drifting conventions: some used "12.50 €", others "12,50 €",
 * some prefixed with "EUR ", others suffixed with "€". This helper produces
 * one canonical German-style format ("12,50 €") so we don't drift again.
 *
 * Use {@see formatEur()} for amounts already in EUR (float) and
 * {@see formatCents()} for amounts stored as integer cents.
 *
 * The {@see AppServiceProvider} also registers a Blade
 * directive `@eur(...)` that calls `formatEur()` for compactness in views.
 */
final class Money
{
    /**
     * Format a EUR amount as "12,50 €" (comma decimal, period thousand-sep).
     */
    public static function formatEur(float|int|string|null $amount): string
    {
        if ($amount === null) {
            return '0,00 €';
        }

        return number_format((float) $amount, 2, ',', '.').' €';
    }

    /**
     * Format an integer-cents amount as "12,50 €". Accepts null (renders as
     * "0,00 €") because some columns are nullable in the schema.
     */
    public static function formatCents(int|string|null $cents): string
    {
        if ($cents === null) {
            return '0,00 €';
        }

        return self::formatEur(((int) $cents) / 100);
    }
}
