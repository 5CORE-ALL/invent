<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        // Active users only (not deactivated)
        $users = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'designation')
            ->orderBy('name')
            ->get();

        // Get inactive users (is_active = false)
        $inactiveUsers = User::query()
            ->where('is_active', false)
            ->select('id', 'name', 'email', 'designation', 'deactivated_at')
            ->orderByDesc('deactivated_at')
            ->get();

        // Check if current user has edit permission
        $canEdit = auth()->check() && auth()->user()->email === 'president@5core.com';

        return view('pages.add-user', compact('users', 'inactiveUsers', 'canEdit'));
    }

    public function update(Request $request, User $user)
    {
        // Check permission - only president@5core.com can edit
        if (auth()->user()->email !== 'president@5core.com') {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit user data.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'designation' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->designation = $request->designation;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ]
        ]);
    }

    public function destroy(User $user)
    {
        if (auth()->user()->email !== 'president@5core.com') {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete users.'
            ], 403);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.'
            ], 422);
        }

        $user->is_active = false;
        $user->deactivated_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User deactivated. They cannot sign in until recovered.',
        ]);
    }

    public function restore(int $id)
    {
        if (auth()->user()->email !== 'president@5core.com') {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to restore users.'
            ], 403);
        }

        $user = User::query()->where('is_active', false)->findOrFail($id);
        $user->is_active = true;
        $user->deactivated_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
            ]
        ]);
    }
}
