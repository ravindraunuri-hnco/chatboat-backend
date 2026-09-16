<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * 100% DYNAMIC ACCESS CHECKER ENGINE
     * Yeh sidha database se check karega ki user ke paas action ka hak hai ya nahi
     */
    private function checkAccess($user, $resourceName, $action)
    {
        // Super Admin ko hamesha full access
        if ($user->role && $user->role->name === 'super_admin') {
            return true;
        }
        
        // Database queries se exact 0 / 1 permission uthana
        $hasAccess = \App\Models\RolePermission::where('role_id', $user->role_id)
            ->whereHas('permission', function($q) use ($resourceName) {
                $q->where('name', $resourceName);
            })
            ->where($action, 1) // check for 'read', 'write', 'update', or 'delete'
            ->exists();
            
        return $hasAccess;
    }

    public function index(Request $request)
    {
        // Dynamic Read Permission Check
        if (!$this->checkAccess($request->user(), 'roles', 'read')) {
            return response()->json(['message' => 'Aapko Roles dekhne ki permission nahi hai.'], 403);
        }

        return response()->json(['data' => Role::all()]);
    }

    public function store(Request $request)
    {
        // Dynamic Write Permission Check
        if (!$this->checkAccess($request->user(), 'roles', 'write')) {
            return response()->json(['message' => 'Aapko naya Role banane ki permission nahi hai.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
        ]);

        $role = Role::create($validated);

        // Naye role ke liye default 0 permissions automatically set karna
        $permissions = \App\Models\Permission::all();
        foreach ($permissions as $perm) {
            \App\Models\RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => $perm->id,
                'write' => 0,
                'read' => 0,
                'update' => 0,
                'delete' => 0,
            ]);
        }

        return response()->json(['data' => $role], 201);
    }

    public function show(Request $request, $id)
    {
        if (!$this->checkAccess($request->user(), 'roles', 'read')) {
            return response()->json(['message' => 'Permission denied'], 403);
        }

        $role = Role::findOrFail($id);
        return response()->json(['data' => $role]);
    }

    public function update(Request $request, $id)
    {
        // Dynamic Update Permission Check
        if (!$this->checkAccess($request->user(), 'roles', 'update')) {
            return response()->json(['message' => 'Update permission denied'], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot edit super_admin role'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
        ]);

        $role->update($validated);
        return response()->json(['data' => $role]);
    }

    public function destroy(Request $request, $id)
    {
        // Dynamic Delete Permission Check
        if (!$this->checkAccess($request->user(), 'roles', 'delete')) {
            return response()->json(['message' => 'Delete permission denied'], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot delete super_admin role'], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }
}