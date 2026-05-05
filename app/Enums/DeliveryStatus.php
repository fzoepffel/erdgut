<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return __('status.'.$this->value);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Skipped, self::Failed], true);
    }
}
