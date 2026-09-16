<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('product', 'read');
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user): bool
    {
        return $user->hasPermission('product', 'read');
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('product', 'write');
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user): bool
    {
        return $user->hasPermission('product', 'update');
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user): bool
    {
        return $user->hasPermission('product', 'delete');
    }
}
