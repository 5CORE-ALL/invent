<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    public function profileView()
    {
        $user = Auth::user();
        return view('pages.profile', compact('user'));
    }

    /**
     * Update basic profile information (name and email)
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email
        ]);

        return back()->with('success', 'Profile updated successfully');
    }

    /**
     * Update password only (without current password requirement)
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
        ]);

        $user = User::findOrFail($request->user_id);
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return back()->with('success', 'Password updated successfully');
    }
}