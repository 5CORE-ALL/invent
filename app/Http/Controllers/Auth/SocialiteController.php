<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Attendance\AttendanceAgentController;
use App\Http\Controllers\Controller;
use App\Helpers\PermissionHelper;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Auth;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function googlelogin()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleAuthentication(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser || ! $googleUser->email) {
                throw new Exception('Invalid Google user data');
            }

            $user = User::where('email', $googleUser->email)
                ->orWhere('google_id', $googleUser->id)
                ->first();

            if ($user) {
                if (! $user->is_active) {
                    if ($desktop = AttendanceAgentController::failDesktopGoogleIfPending('account_inactive')) {
                        return $desktop;
                    }

                    return redirect()
                        ->route('login')
                        ->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
                }

                if (empty($user->google_id)) {
                    $user->update(['google_id' => $googleUser->id]);
                }

                $user->update(['logined' => 1]);

                Auth::login($user, true);
                PermissionHelper::cacheUserPermissions($user->id);

                if ($desktop = AttendanceAgentController::completeDesktopGoogleIfPending($user)) {
                    return $desktop;
                }

                return redirect()->intended(RouteServiceProvider::HOME);
            }

            $given = $googleUser->user['given_name'] ?? '';
            $family = $googleUser->user['family_name'] ?? '';
            $fullName = trim($given.($given && $family ? ' ' : '').$family);
            if ($fullName === '') {
                $fullName = $googleUser->name ?? explode('@', $googleUser->email)[0];
            }

            $userData = User::create([
                'name' => $fullName,
                'email' => $googleUser->email,
                'google_id' => $googleUser->id,
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(),
                'logined' => 1,
            ]);

            Auth::login($userData, true);
            PermissionHelper::cacheUserPermissions($userData->id);

            if ($desktop = AttendanceAgentController::completeDesktopGoogleIfPending($userData)) {
                return $desktop;
            }

            return redirect()->intended(RouteServiceProvider::HOME);
        } catch (Exception $e) {
            Log::error('Google Auth Error: '.$e->getMessage());

            if ($desktop = AttendanceAgentController::failDesktopGoogleIfPending('sign_in_failed')) {
                return $desktop;
            }

            return redirect()
                ->route('login')
                ->withErrors(['error' => 'Google authentication failed. Please try again.']);
        }
    }
}
