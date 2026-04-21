<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Scheduled = 'scheduled';
    case Delivered = 'delivered';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Paid => 'Paid',
            self::Scheduled => 'Scheduled',
            self::Delivered => 'Delivered',
            self::Refunded => 'Refunded',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Is this order still open and operationally active (not closed/terminal)?
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::PendingPayment, self::Paid, self::Scheduled], true);
    }
}
