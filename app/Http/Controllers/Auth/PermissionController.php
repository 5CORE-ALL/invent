<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $sidebarPages = config('sidebar_pages');
        $users = User::with('permission')->get(); // Get all users
        return view('pages.permission', compact('users', 'sidebarPages'));
    }



    public function getPermissions()
    {
        $sidebarPages = config('sidebar_pages');
        $users = User::with('permission')->get(); // Get all users
        return view('pages.permissions', compact('users', 'sidebarPages'));
    }

    // Rename this method to avoid conflict with parent class
    public function getUserPermissionData(User $user)
    {
        try {
            $user->load('permission');

            return response()->json([
                'success' => true,
                'permissions' => $user->permission->permissions ?? []
            ]);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load permissions',
                'permissions' => []
            ], 500);
        }
    }

    public function updatePermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'string|in:view,create,edit,delete'
        ]);

        try {
            $permission = Permission::updateOrCreate(
                ['role' => $user->role],
                ['permissions' => $request->permissions]
            );

            return response()->json([
                'success' => true,
                'message' => 'Permissions updated successfully',
                'permissions' => $permission->permissions
            ]);

        } catch (\Exception $e) {
            Log::error('Permission update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getTableData()
    {
        $users = User::with('permission')->paginate(10);
        return response()->json(['users' => $users]);
    }


    public function saveColumnPermission(Request $request)
    {
        $request->validate([
            'user_email' => 'required|email',
            'columns' => 'required|array',
            'module' => 'required|string'
        ]);

        $user = User::where('email', $request->user_email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $permission = Permission::firstOrCreate(['role' => $user->role]);
        $module = $request->input('module');

        // Always treat columns as a simple array
        $newColumns = array_values($request->columns);

        // Update only this module's columns
        $culomn_permission = $permission->culomn_permission ?? [];
        $culomn_permission[$module] = $newColumns;
        $permission->culomn_permission = $culomn_permission;
        $permission->save();

        return response()->json(['success' => true, 'columns' => $newColumns]);
    }
}