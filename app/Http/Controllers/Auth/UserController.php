<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RrPortfolioUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index()
    {
        // Active users only (not deactivated)
        // select() must run before withCount(): a later select() replaces columns and drops the count subquery.
        $users = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'phone', 'email', 'designation')
            ->with('userRR')
            ->withCount('rrPortfolioAssignments')
            ->orderBy('name')
            ->get();

        // Get inactive users (is_active = false)
        $inactiveUsers = User::query()
            ->where('is_active', false)
            ->select('id', 'name', 'phone', 'email', 'designation', 'deactivated_at')
            ->with('userRR')
            ->withCount('rrPortfolioAssignments')
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
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'designation' => 'nullable|string|max:255',
            'rr_role' => 'nullable|string|max:255',
            'training' => 'nullable|string|max:65535',
            'resources' => 'nullable|string|max:65535',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->email = $request->email;
        $user->designation = $request->designation;
        $user->save();

        $user->userRR()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'role' => $request->input('rr_role'),
                'training' => $request->input('training'),
                'resources' => $request->input('resources'),
            ]
        );
        $user->load('userRR');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'designation' => $user->designation,
                'rr_role' => $user->userRR->role ?? '',
                'training' => $user->userRR->training ?? '',
                'resources' => $user->userRR->resources ?? '',
                'has_rr_portfolio' => RrPortfolioUser::where('user_id', $user->id)->exists(),
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

    /**
     * Get active users for API
     */
    public function getActiveUsers()
    {
        $users = User::where('is_active', true)
            ->with('userRR')
            ->select('id', 'name', 'phone', 'email', 'designation')
            ->orderBy('name')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'designation' => $user->designation,
                    'rr_role' => $user->userRR->role ?? '',
                    'training' => $user->userRR->training ?? '',
                    'resources' => $user->userRR->resources ?? '',
                ];
            });

        return response()->json($users);
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
        $user->load('userRR');

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'designation' => $user->designation,
                'rr_role' => $user->userRR->role ?? '',
                'training' => $user->userRR->training ?? '',
                'resources' => $user->userRR->resources ?? '',
            ]
        ]);
    }
}
