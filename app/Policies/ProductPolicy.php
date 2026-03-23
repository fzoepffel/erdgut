<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products (admin catalog list).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admin');
    }

    /**
     * Determine whether the user can view the product (admin).
     */
    public function view(User $user, Product $product): bool
    {
        return $user->can('admin');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->can('admin');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->can('admin');
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can('admin');
    }
}
