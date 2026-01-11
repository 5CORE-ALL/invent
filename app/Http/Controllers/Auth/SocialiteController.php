<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Auth;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Helpers\PermissionHelper;

class SocialiteController extends Controller
{
    public function googlelogin()
    {
        return Socialite::driver('google')->redirect();
    }
    public function googleAuthentication(): RedirectResponse
    {
        try {
            // Validate Google response
            $googleUser = Socialite::driver('google')->user();

            if (!$googleUser || !$googleUser->email) {
                throw new Exception('Invalid Google user data');
            }

            // Check if email already exists (even without Google ID)
            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();

            if ($user) {
                // Update Google ID if missing (for existing users)
                if (empty($user->google_id)) {
                    $user->update(['google_id' => $googleUser->id]);
                }

                // Reset require_google_login flag and update last activity
                $user->update([
                    'require_google_login' => 0,
                    'last_activity_at' => now(),
                ]);

                Auth::login($user, true);
                PermissionHelper::cacheUserPermissions($user->id);
                return redirect()->intended(RouteServiceProvider::HOME);
            }

            // Create new user with additional validation
            $userData = User::create([
                'name' => $googleUser->name ?? explode('@', $googleUser->email)[0],
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(), // Google emails are verified
                'last_activity_at' => now(),
                'require_google_login' => 0,
            ]);

            Auth::login($userData, true);
            PermissionHelper::cacheUserPermissions($userData->id);
            return redirect()->intended(RouteServiceProvider::HOME);

        } catch (Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());
            return redirect()
                ->route('auth.login')
                ->withErrors(['error' => 'Google authentication failed. Please try again.']);
        }
    }
}
