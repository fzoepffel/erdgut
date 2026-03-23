<?php

namespace App\Policies;

use App\Models\Producer;
use App\Models\User;

class ProducerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('admin');
    }

    public function view(User $user, Producer $producer): bool
    {
        return $user->can('admin');
    }

    public function create(User $user): bool
    {
        return $user->can('admin');
    }

    public function update(User $user, Producer $producer): bool
    {
        return $user->can('admin');
    }

    public function delete(User $user, Producer $producer): bool
    {
        return $user->can('admin');
    }
}
