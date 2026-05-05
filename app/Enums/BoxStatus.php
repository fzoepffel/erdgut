<?php

namespace App\Enums;

enum BoxStatus: string
{
    case Editable = 'editable';
    case Locked = 'locked';
    case Delivered = 'delivered';

    public function label(): string
    {
        return __('status.'.$this->value);
    }
}
