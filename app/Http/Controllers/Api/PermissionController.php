<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    /**
     * GET ALL PERMISSIONS (No Auto-Sync anymore, purely fetch data)
     */
    public function index(Request $request)
    {
        if (! $request->user()->hasPermission('permissions', 'read')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => Permission::all()
        ]);
    }

    /**
     * CREATE PERMISSION (With STRICT Database Table Validation)
     */
    public function store(Request $request)
    {
        if (! $request->user()->hasPermission('permissions', 'write')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions'
        ]);

        // 🔥 VALIDATION LOGIC: Check if table exists in Database
        $inputName = strtolower(str_replace([' ', '&'], ['_', ''], $request->name)); // Example: "Role Permissions" -> "role_permissions"
        $pluralName = Str::plural($inputName);

        // Fetch all actual tables from MySQL
        $tablesList = DB::select('SHOW TABLES');
        $tables = array_map('current', $tablesList);

        // Agar SQLite use ho raha ho fallback ke liye
        if (empty($tables)) {
            $tablesList = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = array_map(function($t) { return $t->name; }, $tablesList);
        }

        // Check karte hain ki user ne jo naam daala he uska singular ya plural table DB me hai ya nahi
        if (!in_array($inputName, $tables) && !in_array($pluralName, $tables)) {
            return response()->json([
                'message' => 'Aap ye permission create nahi kar sakte! Database me "' . $request->name . '" (ya "' . $pluralName . '") naam ka koi table maujud nahi hai.'
            ], 422);
        }

        // Agar validation pass ho gayi toh permission ban jayegi
        $permission = Permission::create([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * SHOW SINGLE PERMISSION
     */
    public function show(Request $request, Permission $permission)
    {
        if (! $request->user()->hasPermission('permissions', 'read')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $permission
        ]);
    }

    /**
     * UPDATE PERMISSION
     */
    public function update(Request $request, Permission $permission)
    {
        if (! $request->user()->hasPermission('permissions', 'update')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update([
            'name' => $request->name
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * DELETE PERMISSION
     */
    public function destroy(Request $request, Permission $permission)
    {
        if (! $request->user()->hasPermission('permissions', 'delete')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully'
        ]);
    }
}