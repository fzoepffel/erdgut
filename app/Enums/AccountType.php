<?php

namespace App\Enums;

use App\Models\User;

/**
 * What kind of account a {@see User} is. Orthogonal to is_admin.
 * Set server-side only — never from user-supplied registration input.
 */
enum AccountType: string
{
    case Customer = 'customer';
    case Restaurant = 'restaurant';
}
