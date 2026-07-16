<?php

namespace App\Support;

use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ONE yearly invoice ledger: restaurant (B2B) invoices and shop-order
 * invoices draw their numbers from the same gap-free counter, continuing
 * the manually issued EB260046-style series. The NumberSequence key keeps
 * its historical name for continuity with already-issued numbers.
 */
class InvoiceSequence
{
    private static function key(Carbon $at): string
    {
        return 'restaurant_invoice:'.$at->year;
    }

    /** Format a sequence value as the compact number (EB + yy + 4 digits). */
    public static function format(int $sequence, ?Carbon $at = null): string
    {
        $at ??= Carbon::now();

        return sprintf('%s%s%04d', (string) config('shop.restaurant_invoice_prefix', 'EB'), $at->format('y'), $sequence);
    }

    /**
     * Allocate the next number. MUST be called inside an open transaction
     * (see NumberSequence::next).
     */
    public static function next(?Carbon $at = null): string
    {
        $at ??= Carbon::now();

        return self::format(NumberSequence::next(self::key($at)), $at);
    }

    /** The number the NEXT invoice would get, without consuming it. */
    public static function preview(?Carbon $at = null): string
    {
        $at ??= Carbon::now();
        $current = (int) (NumberSequence::query()->find(self::key($at))?->value ?? 0);

        return self::format($current + 1, $at);
    }

    /**
     * Align the sequence so the NEXT invoice gets number $next. Forward
     * only — lowering the counter could re-issue a used number.
     *
     * @throws ValidationException
     */
    public static function setNext(int $next): void
    {
        $key = self::key(Carbon::now());

        DB::transaction(function () use ($key, $next): void {
            $row = NumberSequence::query()->lockForUpdate()->find($key);
            $current = (int) ($row?->value ?? 0);
            if ($next <= $current) {
                throw ValidationException::withMessages([
                    'next_invoice_number' => __('restaurant.admin_invoice_sequence_too_low', ['current' => $current + 1]),
                ]);
            }

            NumberSequence::query()->updateOrCreate(['key' => $key], ['value' => $next - 1]);
        });
    }
}
