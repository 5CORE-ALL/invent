<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
     * Update basic profile information (name, email, and optional avatar)
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
            'phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'avatar_position_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'avatar_position_y' => ['nullable', 'integer', 'min:0', 'max:100'],
            'avatar_zoom' => ['nullable', 'integer', 'min:50', 'max:200'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->filled('phone') ? $request->phone : null,
        ];

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        if ($request->has('avatar_position_x')) {
            $data['avatar_position_x'] = (int) $request->avatar_position_x;
        }
        if ($request->has('avatar_position_y')) {
            $data['avatar_position_y'] = (int) $request->avatar_position_y;
        }
        if ($request->has('avatar_zoom')) {
            $data['avatar_zoom'] = (int) $request->avatar_zoom;
        }

        $user->update($data);

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