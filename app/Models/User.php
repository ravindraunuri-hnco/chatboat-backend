<?php
// FILE: app/Models/User.php
// ✅ FIX: hasPermission() mein wherePivot($action, true) ki jagah wherePivot($action, 1)
//         Database mein column boolean hai (0/1 integers),
//         strict `true` se match fail hoti thi silently

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
     * Permission check — action optional rakha taaki Policy calls crash na hon.
     *
     * ✅ FIX: wherePivot($action, true)  →  wherePivot($action, 1)
     *
     * Reason: role_permissions table mein read/write/update/delete columns
     * BOOLEAN (tinyint 0/1) hain. MySQL strict mode mein `true` PHP boolean
     * kabhi kabhi integer 1 se match nahi karta pivot query mein.
     * `1` explicitly dena safe aur consistent hai.
     */
    public function hasPermission(string $permissionName, string $action = null): bool
    {
        // 1. Super Admin ko sab kuch allowed
        if ($this->isSuperAdmin()) {
            return true;
        }

        // 2. Role nahi hai to block
        if (!$this->role) {
            return false;
        }

        // 3. Pivot table se check
        $query = $this->role->permissions()->where('permissions.name', $permissionName);

        if ($action) {
            // ✅ FIX: `true` ki jagah `1` — boolean column ke saath reliable match
            $query->wherePivot($action, 1);
        }

        return $query->exists();
    }
}