<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    /**
     * Display a listing of users for role management.
     */
    public function index()
    {
        // Get all users except the current authenticated user
        $users = User::where('id', '!=', auth()->id())->get();

        return view('pages.roles', compact('users'));
    }

    /**
     * Update the specified user's role.
     */
    public function update(Request $request, User $user)
    {
        // Validate the request
        $request->validate([
            'role' => 'required|string|in:viewer,user,manager,admin,superadmin'
        ]);

        try {
            DB::beginTransaction();

            Log::info("Attempting to update user {$user->email} to role {$request->role}");

            // Update the user's role directly
            $user->role = $request->role;
            $user->save();

            DB::commit();

            Log::info("Successfully updated user {$user->email} role to {$request->role}");

            return back()->with('success', "User {$user->name}'s role has been updated to {$request->role} successfully.");

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Role update failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return back()->with('error', 'Failed to update user role. Please try again. Error: ' . $e->getMessage());
        }
    }
}