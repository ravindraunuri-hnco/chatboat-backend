<?php
// FILE: app/Models/User.php
// ✅ FIX: in hasPermission(), use wherePivot($action, 1) instead of wherePivot($action, true)
//         The database column is a boolean (0/1 integers),
//         matching against strict `true` failed silently

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * User belongs to one Role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Permission check — the action is optional so Policy calls do not crash.
     *
     * ✅ FIX: wherePivot($action, true)  →  wherePivot($action, 1)
     *
     * Reason: the read/write/update/delete columns of the role_permissions table are
     * BOOLEAN (tinyint 0/1). In MySQL strict mode a PHP `true` boolean
     * sometimes fails to match integer 1 in the pivot query.
     * Passing `1` explicitly is safe and consistent.
     */
    public function hasPermission(string $permissionName, string $action = null): bool
    {
        // 1. Super Admin is allowed everything
        if ($this->isSuperAdmin()) {
            return true;
        }

        // 2. Block if there is no role
        if (!$this->role) {
            return false;
        }

        // 3. Match the name against the role's permissions.
        //    Names are normalized, so "product requests" / "product_requests" / "product_request" are all the same permission.
        foreach ($this->role->permissions as $permission) {
            if (!Permission::sameKey($permission->name, $permissionName)) {
                continue;
            }

            // If no action is given, just check that the role has this permission assigned
            if (!$action) {
                return true;
            }

            if ((int) ($permission->pivot->{$action} ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }
}