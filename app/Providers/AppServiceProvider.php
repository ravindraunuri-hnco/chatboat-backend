<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, $ability, $args) {
            
            if ($user->role && $user->role->name === 'super_admin') {
                return true;
            }

            $actionMap = [
                'viewAny' => 'read',
                'view'    => 'read',
                'create'  => 'write',
                'update'  => 'update',
                'delete'  => 'delete',
            ];
            
            $action = in_array($ability, ['read', 'write', 'update', 'delete']) 
                      ? $ability 
                      : ($actionMap[$ability] ?? null);

            if (!$action || empty($args)) {
                return null; 
            }

            $modelArg = is_array($args) ? $args[0] : $args;
            $className = is_string($modelArg) ? class_basename($modelArg) : class_basename(get_class($modelArg));
            
            // Name mapping for older models (where the model name and the permission name differ)
            $modelMap = [
                'ProductCategory' => 'categories',
            ];

            // New models need no mapping: the permission is found automatically from the model name.
            // Product -> products, Category -> categories, ProductRequest -> product_requests (names are matched after normalization)
            $resourceName = $modelMap[$className] ?? \Illuminate\Support\Str::plural(\Illuminate\Support\Str::snake($className));
            $permissionModel = \App\Models\Permission::findByKey($resourceName);
            $permissionExists = (bool) $permissionModel;

            if ($permissionExists) {
                $query = \App\Models\RolePermission::where('role_id', $user->role_id)
                    ->where('permission_id', $permissionModel->id);

                if ($action === 'read') {
                    $hasAccess = $query->where(function($q) {
                        $q->where('read', 1)
                          ->orWhere('write', 1)
                          ->orWhere('update', 1)
                          ->orWhere('delete', 1);
                    })->exists();
                } else {
                    $hasAccess = $query->where($action, 1)->exists();
                }

                return $hasAccess ? true : false;
            }
            return null;
        });
    }
}