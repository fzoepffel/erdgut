<?php

namespace App\Enums;

enum PaymentChargeInitiator: string
{
    case Scheduled = 'scheduled';

    case AdminManual = 'admin_manual';

    case Retry = 'retry';
}
