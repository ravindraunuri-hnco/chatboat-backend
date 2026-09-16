<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    /**
     * ONE ROLE → MANY USERS
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * MANY permissions
     */
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions'
        )->withPivot([
            'read',
            'write',
            'update',
            'delete'
        ]);
    }
}
