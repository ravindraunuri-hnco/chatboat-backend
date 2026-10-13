<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
            return response()->json(['message' => 'You do not have access to make anyone a super_admin.'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id
        ]);
        $user->forceFill(['password_encrypted' => Crypt::encryptString($request->password)])->save();

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
            return response()->json(['message' => 'You cannot update a super_admin\'s data.'], 403);
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
            return response()->json(['message' => 'You cannot assign the super_admin role to anyone.'], 403);
        }

        // UPDATE ALL DATA: Name, Email, Role and (if provided) Password
        $targetUser->name = $request->name;
        $targetUser->email = $request->email;
        $targetUser->role_id = $request->role_id;
        
        if ($request->filled('password')) {
            // A super admin's password can only be changed by that super admin himself
            if ($this->isOtherSuperAdmin($request, $targetUser)) {
                return response()->json(['message' => 'You cannot change another super_admin\'s password.'], 403);
            }
            $targetUser->password = Hash::make($request->password);
            $targetUser->password_encrypted = Crypt::encryptString($request->password);
        }
        
        $targetUser->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $targetUser->load('role')
        ]);
    }

    private function isOtherSuperAdmin(Request $request, User $target): bool
    {
        return $target->role
            && $target->role->name === 'super_admin'
            && $request->user()->id !== $target->id;
    }

    // Super admin only: reveal a user's current password (never for another super admin)
    public function revealPassword(Request $request, $id)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $target = User::with('role')->findOrFail($id);

        if ($this->isOtherSuperAdmin($request, $target)) {
            return response()->json(['message' => 'You cannot view another super_admin\'s password.'], 403);
        }

        $password = null;
        if ($target->password_encrypted) {
            try {
                $password = Crypt::decryptString($target->password_encrypted);
            } catch (\Exception $e) {
                $password = null;
            }
        }

        return response()->json(['status' => 'success', 'password' => $password]);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->hasPermission('users', 'delete')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $targetUser = User::with('role')->findOrFail($id);

        // 🔥 SECURITY FIX: Prevent normal admins from deleting a 'super_admin'
        if ($targetUser->role && $targetUser->role->name === 'super_admin' && $request->user()->role->name !== 'super_admin') {
            return response()->json(['message' => 'You cannot delete a super_admin.'], 403);
        }

        if ($request->user()->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $targetUser->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}