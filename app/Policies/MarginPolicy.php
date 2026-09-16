<?php

namespace App\Policies;

use App\Models\Margin;
use App\Models\User;

class MarginPolicy
{
    /**
     * Determine whether the user can view any margins.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('margin', 'read');
    }

    /**
     * Determine whether the user can view the margin.
     */
    public function view(User $user, Margin $margin): bool
    {
        return $user->hasPermission('margin', 'read');
    }

    /**
     * Determine whether the user can create margins.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('margin', 'write');
    }

    /**
     * Determine whether the user can update the margin.
     */
    public function update(User $user, Margin $margin): bool
    {
        return $user->hasPermission('margin', 'update');
    }

    /**
     * Determine whether the user can delete the margin.
     */
    public function delete(User $user, Margin $margin): bool
    {
        return $user->hasPermission('margin', 'delete');
    }
}
