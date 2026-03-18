<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Scheduled = 'scheduled';
    case Skipped = 'skipped';
    case Delivered = 'delivered';
}
