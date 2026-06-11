<?php

namespace App\Enums;

/**
 * Whether the customer is a private individual (B2C) or a business (B2B).
 *
 * Storefront orders always default to Private. Admin-created orders can
 * be set to Business for restaurants, caterers, or other trade buyers.
 */
enum CustomerType: string
{
    case Private = 'private';
    case Business = 'business';
}
