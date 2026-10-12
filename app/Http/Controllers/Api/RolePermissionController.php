<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * LIST ALL ROLE PERMISSIONS
     */
    public function index(Request $request)
    {
        // Admin panel users only (not chatbot users)
        if (!$request->user() instanceof \App\Models\User) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Strict Role Filter Engine
        $roleId = $request->query('role_id');
        
        if ($roleId) {
            $data = RolePermission::where('role_id', $roleId)->with('permission')->get();
        } else {
            $data = RolePermission::with(['role', 'permission'])->get();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * STORE OR UPDATE ROLE PERMISSION (Live Matrix Sync)
     */
    public function store(Request $request)
    {
        // ✅ FIX 1: Security check - only a Super Admin can change roles
        if ($request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Only a Super Admin can change access.'], 403);
        }

        // ✅ FIX 2: Converted integers to booleans so React can easily send true/false
        $validated = $request->validate([
            'role_id' => 'required|integer',
            'permission_id' => 'required|integer',
            'write' => 'required|boolean',
            'read' => 'required|boolean',
            'update' => 'required|boolean',
            'delete' => 'required|boolean',
        ]);

        // Without read permission, write/update/delete cannot be enabled either — all become false automatically
        if (!$validated['read']) {
            $validated['write'] = $validated['update'] = $validated['delete'] = false;
        }

        // Industry Standard updateOrCreate Framework
        // If the row already exists it is updated; otherwise a fresh row is inserted for the new role!
        $rolePermission = RolePermission::updateOrCreate(
            [
                'role_id' => $validated['role_id'],
                'permission_id' => $validated['permission_id']
            ],
            [
                'read' => (bool)$validated['read'],
                'write' => (bool)$validated['write'],
                'update' => (bool)$validated['update'],
                'delete' => (bool)$validated['delete'],
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated live in the database!',
            'data' => $rolePermission
        ]);
    }

    /**
     * SHOW SINGLE ROLE-PERMISSION
     */
    public function show(Request $request, $id)
    {
        // ✅ FIX 3: The old code had 'user' here; changed it to 'role_permission'
        if (! $request->user()->hasPermission('role_permissions', 'read') && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = RolePermission::with(['role', 'permission'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * UPDATE ROLE PERMISSION (CRUD FLAGS)
     */
    public function update(Request $request, $id)
    {
        if (! $request->user()->hasPermission('role_permissions', 'update') && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'read' => 'boolean',
            'write' => 'boolean',
            'update' => 'boolean',
            'delete' => 'boolean',
        ]);

        $rolePermission = RolePermission::findOrFail($id);

        $flags = [
            'read' => $request->has('read') ? (bool)$request->read : (bool)$rolePermission->read,
            'write' => $request->has('write') ? (bool)$request->write : (bool)$rolePermission->write,
            'update' => $request->has('update') ? (bool)$request->update : (bool)$rolePermission->update,
            'delete' => $request->has('delete') ? (bool)$request->delete : (bool)$rolePermission->delete,
        ];

        // Without read permission, write/update/delete cannot be enabled either — all become false automatically
        if (!$flags['read']) {
            $flags['write'] = $flags['update'] = $flags['delete'] = false;
        }

        $rolePermission->update($flags);

        return response()->json([
            'status' => 'success',
            'message' => 'Role permission updated successfully',
            'data' => $rolePermission
        ]);
    }

    /**
     * DELETE ROLE PERMISSION
     */
    public function destroy(Request $request, $id)
    {
        if (! $request->user()->hasPermission('role_permissions', 'delete') && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rolePermission = RolePermission::findOrFail($id);
        $rolePermission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Role permission removed successfully'
        ]);
    }
}