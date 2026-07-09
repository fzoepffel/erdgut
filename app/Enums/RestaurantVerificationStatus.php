<?php

namespace App\Enums;

/**
 * Lifecycle of a restaurant (B2B) account application.
 *
 * Pending  — applied, awaiting admin review.
 * Verified — approved by an admin; the account is open.
 * Rejected — declined. In practice a rejected application is hard-deleted
 *            (user + profile), so this case mainly exists for clarity and
 *            any future soft-reject.
 */
enum RestaurantVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
