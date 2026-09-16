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
        // ✅ FIX 1: Security Check - Sirf Super Admin hi roles badal sakta hai
        if ($request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Sirf Super Admin access badal sakta hai.'], 403);
        }

        // ✅ FIX 2: Integer ko Boolean kiya, taaki React true/false aaram se bhej sake
        $validated = $request->validate([
            'role_id' => 'required|integer',
            'permission_id' => 'required|integer',
            'write' => 'required|boolean',
            'read' => 'required|boolean',
            'update' => 'required|boolean',
            'delete' => 'required|boolean',
        ]);

        // Industry Standard updateOrCreate Framework
        // Agar pehle se row h to update karega, nahi to bilkul naye role ke liye fresh insert karega!
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
            'message' => 'Permission database me live update ho gayi!',
            'data' => $rolePermission
        ]);
    }

    /**
     * SHOW SINGLE ROLE-PERMISSION
     */
    public function show(Request $request, $id)
    {
        // ✅ FIX 3: Aapke purane code me 'user' likha tha, isko 'role_permission' kar diya
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

        $rolePermission->update([
            'read' => $request->has('read') ? (bool)$request->read : $rolePermission->read,
            'write' => $request->has('write') ? (bool)$request->write : $rolePermission->write,
            'update' => $request->has('update') ? (bool)$request->update : $rolePermission->update,
            'delete' => $request->has('delete') ? (bool)$request->delete : $rolePermission->delete,
        ]);

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