<?php

namespace App\Enums;

use App\Console\Commands\ChargeSubscriptions;

/**
 * How a subscription is billed.
 *
 * - SumupRecurring: automatic via SumUp mandate; the default.
 * - ManualInvoice: admin sends an invoice; admin marks payments paid out of band.
 * - ManualCash: in-person settlement; admin records payments as cash.
 *
 * Only SumupRecurring is touched by the {@see ChargeSubscriptions}
 * cron. Manual modes are admin-driven end to end.
 */
enum BillingMode: string
{
    case SumupRecurring = 'sumup_recurring';
    case ManualInvoice = 'manual_invoice';
    case ManualCash = 'manual_cash';

    public function label(): string
    {
        return match ($this) {
            self::SumupRecurring => 'SumUp (recurring)',
            self::ManualInvoice => 'Manual (invoice)',
            self::ManualCash => 'Manual (cash)',
        };
    }

    public function isAutomatic(): bool
    {
        return $this === self::SumupRecurring;
    }
}
