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

        // 🔥 FIX 1: Eager loading me se '.permission' hata diya. 
        // Kyuki Role -> permissions() ek BelongsToMany relation hai, ye direct Permission models lata hai.
        $user = User::with(['role', 'role.permissions'])
            ->where('email', $request->email)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email ya password galat hai.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        
        // Helper function se safe permissions calculate karna
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
        // 🔥 FIX 1 (Same yahan bhi eagerly loading theek ki hai)
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
                
                // 🔥 FIX 2: Tumhare database me Pivot table lagai hui hai. 
                // Isliye hum seedha $rp->name (Permission ka naam) aur $rp->pivot->read (Access flag) nikalenge.
                if (isset($rp->pivot) && isset($rp->name)) {
                    
                    // Pichla fix: Space ko underscore me badalna ('Role Permissions' -> 'role_permissions')
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