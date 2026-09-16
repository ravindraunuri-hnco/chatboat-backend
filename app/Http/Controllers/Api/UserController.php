<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('users', 'read')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => User::with('role')->get()
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->hasPermission('users', 'write')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id'
        ]);

        // 🔥 SECURITY FIX: Prevent normal admins from assigning 'super_admin' role
        $role = Role::find($request->role_id);
        if ($role && $role->name === 'super_admin' && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Aapko kisi ko super_admin banane ka access nahi hai.'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user->load('role')
        ], 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'read')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $user = User::with('role')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'update')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $targetUser = User::with('role')->findOrFail($id);

        // 🔥 SECURITY FIX: Prevent normal admins from updating an existing 'super_admin'
        if ($targetUser->role && $targetUser->role->name === 'super_admin' && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Aap super_admin ka data update nahi kar sakte.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($targetUser->id)],
            'role_id' => 'required|exists:roles,id',
            'password' => 'nullable|string|min:6'
        ]);

        // 🔥 SECURITY FIX: Prevent normal admins from changing someone's role TO 'super_admin'
        $newRole = Role::find($request->role_id);
        if ($newRole && $newRole->name === 'super_admin' && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Aap kisi ko super_admin ka role assign nahi kar sakte.'], 403);
        }

        // UPDATE ALL DATA: Name, Email, Role aur (agar dala ho toh) Password
        $targetUser->name = $request->name;
        $targetUser->email = $request->email;
        $targetUser->role_id = $request->role_id;
        
        if ($request->filled('password')) {
            $targetUser->password = Hash::make($request->password);
        }
        
        $targetUser->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $targetUser->load('role')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'delete')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $targetUser = User::with('role')->findOrFail($id);

        // 🔥 SECURITY FIX: Prevent normal admins from deleting a 'super_admin'
        if ($targetUser->role && $targetUser->role->name === 'super_admin' && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'Aap super_admin ko delete nahi kar sakte.'], 403);
        }

        if ($request->user()->id === $targetUser->id) {
            return response()->json(['message' => 'Aap apna khud ka account delete nahi kar sakte.'], 403);
        }

        $targetUser->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}