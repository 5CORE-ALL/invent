<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();

        $query = User::query();

        if ($currentUser->role === 'admin') {
            // Admins can't see super admins
            $query->where('role', '!=', 'superadmin');
        }

        $users = $query->get();

        return view('pages.roles', compact('users'));
    }

    public function update(Request $request, User $user)
    {
        // Prevent editing own role
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot edit your own role.');
        }

        $request->validate([
            'role' => 'required|in:user,admin,superadmin,super admin,manager,viewer',
        ]);

        $user->role = $request->role;
        $user->save();

        return back()->with('success', 'Role updated successfully.');
    }
}
