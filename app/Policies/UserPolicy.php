<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the authenticated user can view this user record.
     */
    public function view(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->is($targetUser);
    }

    /**
     * Determine whether the authenticated user can update this user record.
     */
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->is($targetUser);
    }

    /**
     * Determine whether the authenticated user can delete this user record.
     */
    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->is($targetUser);
    }
}
