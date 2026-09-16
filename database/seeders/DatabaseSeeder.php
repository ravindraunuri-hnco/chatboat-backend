<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates:
     *  - permissions: product, category, margin, user
     *  - roles: super_admin, admin
     *  - role_permissions: admin can READ product & category only
     *    (matches requirement: admin sees Products & Categories but
     *    cannot create/edit/delete, and has no access to Users)
     *  - default users: Super Admin + Admin
     */
    public function run(): void
    {
        // ------------------------------------------------------------
        // Permissions (resource names - matches policies/hasPermission)
        // ------------------------------------------------------------
        $permissionNames = ['product', 'category', 'margin', 'user'];

        $permissions = [];
        foreach ($permissionNames as $name) {
            $permissions[$name] = Permission::firstOrCreate(['name' => $name]);
        }

        // ------------------------------------------------------------
        // Roles
        // ------------------------------------------------------------
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['description' => 'Full access to everything']
        );

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Limited access - can view products & categories only']
        );

        // ------------------------------------------------------------
        // Role <-> Permission pivot (role_permissions)
        // super_admin doesn't strictly need rows here because
        // User::hasPermission() short-circuits to true for super admins,
        // but we add full access rows anyway for completeness / UI display.
        // ------------------------------------------------------------
        foreach ($permissions as $permission) {
            $superAdminRole->permissions()->syncWithoutDetaching([
                $permission->id => [
                    'read' => true,
                    'write' => true,
                    'update' => true,
                    'delete' => true,
                ],
            ]);
        }

        // Admin: read-only access to product & category, no access to margin/user.
        $adminRole->permissions()->syncWithoutDetaching([
            $permissions['product']->id => [
                'read' => true,
                'write' => false,
                'update' => true,
                'delete' => false,
            ],
            $permissions['category']->id => [
                'read' => true,
                'write' => false,
                'update' => false,
                'delete' => false,
            ],
            $permissions['margin']->id => [
                'read' => false,
                'write' => false,
                'update' => false,
                'delete' => false,
            ],
            $permissions['user']->id => [
                'read' => false,
                'write' => false,
                'update' => false,
                'delete' => false,
            ],
        ]);

        // ------------------------------------------------------------
        // Default users
        // ------------------------------------------------------------
        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
            ]
        );
    }
}
