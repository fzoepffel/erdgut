<?php

namespace App\Enums;

enum OrderProductType: string
{
    case TrialBox = 'trial_box';

    public function label(): string
    {
        return match ($this) {
            self::TrialBox => 'Trial box',
        };
    }
}
