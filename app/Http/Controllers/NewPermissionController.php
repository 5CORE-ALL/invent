<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NewPermissionController extends Controller
{
    /**
     * Return canonical action set for a role.
     */
    protected function actionsForRole(string $role): array
    {
        return match($role) {
            'viewer' => ['view'],
            'user' => ['view','create'],
            'manager' => ['view','create','edit'],
            'admin', 'superadmin' => ['view','create','edit','delete'],
            default => ['view']
        };
    }
    public function index()
    {
        // Get all role-based permissions
        $rolePermissions = Permission::all();
        
        // Format permissions for the view
        $permissions = [];
        foreach ($rolePermissions as $permission) {
            $permissions[$permission->role] = $permission->permissions ?? [];
        }
        
        return view('pages.permissions', compact('permissions'));
    }

    public function store(Request $request)
    {
        $permissions = $request->input('permissions', []);

        try {
            DB::transaction(function () use ($permissions) {
                
                // First, clear existing role-based permissions
                Permission::whereNotNull('role')->delete();

                // Define all possible roles
                $allRoles = ['viewer', 'user', 'manager', 'admin', 'superadmin'];
                
                foreach ($allRoles as $role) {
                    $rolePermissions = [];
                    if (isset($permissions[$role])) {
                        foreach ($permissions[$role] as $module => $checked) {
                            if ($checked) {
                                $rolePermissions[$module] = $this->actionsForRole($role);
                            }
                        }
                    }
                    Permission::create([
                        'role' => $role,
                        'user_id' => null,
                        'permissions' => $rolePermissions
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Permissions saved successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function edit()
    {
        
        // Reuse the same structure as index(): [role => [module => [actions]]]
        $rolePermissions = Permission::whereNotNull('role')->get();
        
        $permissions = [];
        foreach ($rolePermissions as $permission) {
            $permissions[$permission->role] = $permission->permissions ?? [];
        }
        
        return view('pages.permissions', compact('permissions') + ['mode' => 'edit']);
    }


 
    public function view()
    {
        // Get all role-based permissions
        $rolePermissions = Permission::whereNotNull('role')->get();
        
        // Build permissions array: [module][role] = actions
        $permissions = [];
        $modules = [];
        
        foreach ($rolePermissions as $permission) {
            $role = $permission->role;
            $perms = $permission->permissions;
            
            if (is_array($perms)) {
                foreach ($perms as $module => $actions) {
                    // Track modules
                    if (!in_array($module, $modules)) {
                        $modules[] = $module;
                    }
                    
                    // Build permissions array
                    if (!isset($permissions[$module])) {
                        $permissions[$module] = [];
                    }
                    
                    if (!empty($actions)) {
                        $permissions[$module][$role] = $actions;
                    }
                }
            }
        }

        return view('pages.permission-view', [
            'modules' => $modules,
            'rolePermissions' => $permissions
        ]);
    }
}
