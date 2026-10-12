<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 🔥 FIX 1: removed '.permission' from the eager loading. 
        // Because Role -> permissions() is a BelongsToMany relation, it returns Permission models directly.
        $user = User::with(['role', 'role.permissions'])
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Incorrect email or password.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        
        // Calculate permissions safely using a helper function
        $permissions = $this->getUserPermissions($user);

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'permissions' => $permissions
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        // Chatbot user tokens are also Sanctum tokens — they must not get admin data
        if (!$request->user() instanceof User) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 🔥 FIX 1 (the same eager loading fix applied here too)
        $user = User::with(['role', 'role.permissions'])->find($request->user()->id);
        
        $permissions = $this->getUserPermissions($user);

        return response()->json([
            'data' => [
                'user' => $user,
                'permissions' => $permissions
            ]
        ]);
    }

    /**
     * 100% CRASH-FREE PERMISSION ENGINE (Pivot Handled)
     */
    private function getUserPermissions($user)
    {
        if (!$user->role) {
            return [];
        }

        if ($user->role->name === 'super_admin') {
            return ['all' => ['read' => true, 'write' => true, 'update' => true, 'delete' => true]];
        }

        $permissions = [];
        
        if ($user->role->permissions) {
            foreach ($user->role->permissions as $rp) {
                
                // 🔥 FIX 2: Your database uses a pivot table. 
                // So we read $rp->name (the permission name) and $rp->pivot->read (the access flag) directly.
                if (isset($rp->pivot) && isset($rp->name)) {
                    
                    // Previous fix: convert spaces to underscores ('Role Permissions' -> 'role_permissions')
                    $rawName = strtolower($rp->name);
                    $name = str_replace([' ', '&'], ['_', ''], $rawName); 
                    $name = str_replace('__', '_', $name); 

                    $permissions[$name] = [
                        'read'   => (bool) ($rp->pivot->read ?? false),
                        'write'  => (bool) ($rp->pivot->write ?? false),
                        'update' => (bool) ($rp->pivot->update ?? false),
                        'delete' => (bool) ($rp->pivot->delete ?? false),
                    ];
                }
            }
        }

        return $permissions;
    }
}