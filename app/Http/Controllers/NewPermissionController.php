<?php

namespace App\Http\Controllers;

use App\Helpers\PermissionHelper;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            'rolePermissions' => $permissions,
            'grantsByModule' => $this->grantsByModule(),
        ]);
    }

    /**
     * Person-level grants grouped by module: [module => [['user_id', 'name', 'actions'], ...]].
     */
    protected function grantsByModule(): array
    {
        if (! Schema::hasTable('user_permission_grants')) {
            return [];
        }

        $out = [];
        $rows = UserPermissionGrant::query()->with('user:id,name')->orderBy('module')->get();
        foreach ($rows as $row) {
            $out[(string) $row->module][] = [
                'user_id' => (int) $row->user_id,
                'name' => (string) ($row->user->name ?? ('User #'.$row->user_id)),
                'actions' => array_values((array) $row->actions),
            ];
        }

        return $out;
    }

    /**
     * People who can be picked in the Grant access window.
     */
    public function grantUsers()
    {
        $users = User::query()
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'org_level']);

        return response()->json([
            'success' => true,
            'users' => $users->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) $u->email,
                'role' => strtolower((string) $u->role),
                'org_level' => strtolower((string) $u->org_level),
            ])->values(),
        ]);
    }

    /**
     * Give (or take back) view / create / edit / delete on the chosen pages for the chosen people.
     * A grant is added on top of the person's role; revoking removes only the ticked actions.
     */
    public function grantStore(Request $request)
    {
        if (! Schema::hasTable('user_permission_grants')) {
            return response()->json(['success' => false, 'message' => 'Run the database migration first (user_permission_grants table is missing).'], 500);
        }

        $data = $request->validate([
            'mode' => 'required|in:grant,revoke',
            'modules' => 'required|array|min:1|max:500',
            'modules.*' => 'required|string|max:191',
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'required|integer|exists:users,id',
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|string|in:'.implode(',', UserPermissionGrant::ACTIONS),
        ]);

        // Only pages the permissions screen already lists can be granted.
        $known = [];
        foreach (Permission::whereNotNull('role')->get() as $perm) {
            foreach (array_keys((array) $perm->permissions) as $module) {
                $known[(string) $module] = true;
            }
        }
        $modules = array_values(array_unique($data['modules']));
        $unknown = array_values(array_filter($modules, fn ($m) => ! isset($known[$m])));
        if ($unknown !== []) {
            return response()->json(['success' => false, 'message' => 'Unknown page: '.implode(', ', array_slice($unknown, 0, 5))], 422);
        }

        $actions = array_values(array_intersect(UserPermissionGrant::ACTIONS, $data['actions']));
        $userIds = array_values(array_unique(array_map('intval', $data['user_ids'])));
        $by = $request->user()?->id;
        $changed = 0;

        try {
            DB::transaction(function () use ($data, $modules, $userIds, $actions, $by, &$changed) {
                foreach ($userIds as $userId) {
                    foreach ($modules as $module) {
                        $row = UserPermissionGrant::query()->where('user_id', $userId)->where('module', $module)->first();
                        $current = $row ? array_values((array) $row->actions) : [];

                        if ($data['mode'] === 'grant') {
                            $next = array_values(array_unique(array_merge($current, $actions)));
                        } else {
                            $next = array_values(array_diff($current, $actions));
                        }
                        if ($next === $current) {
                            continue;
                        }

                        if ($next === []) {
                            $row?->delete();
                        } elseif ($row) {
                            $row->update(['actions' => $next, 'granted_by' => $by]);
                        } else {
                            UserPermissionGrant::create(['user_id' => $userId, 'module' => $module, 'actions' => $next, 'granted_by' => $by]);
                        }
                        $changed++;
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Permission grant failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not save the grants.'], 500);
        }

        foreach ($userIds as $userId) {
            PermissionHelper::forgetUserPermissions($userId);
        }

        return response()->json([
            'success' => true,
            'changed' => $changed,
            'message' => ($data['mode'] === 'grant' ? 'Access granted' : 'Access removed')
                .': '.count($modules).' page(s) for '.count($userIds).' person(s) ('.$changed.' change'.($changed === 1 ? '' : 's').').',
            'grants' => $this->grantsByModule(),
        ]);
    }
}
