<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Cache;
use App\Models\Permission;
use App\Helpers\PermissionHelper;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        
        PermissionHelper::cacheUserPermissions($user->id);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        // Store the current session ID before logout
        $sessionId = $request->session()->getId();

        // Perform logout
        Auth::guard('web')->logout();

        // Invalidate the session
        $request->session()->invalidate();

        // Regenerate CSRF token
        $request->session()->regenerateToken();

        // Manually delete the session file if using file driver
        if (config('session.driver') === 'file') {
            $sessionFile = storage_path('framework/sessions/' . $sessionId);
            if (file_exists($sessionFile)) {
                unlink($sessionFile);
            }
        }

        // Clear all cookies
        $cookies = [
            'laravel_session',
            'XSRF-TOKEN',
            Auth::getRecallerName(),
        ];

        foreach ($cookies as $cookie) {
            Cookie::queue(Cookie::forget($cookie));
        }

        // Redirect to logout page with cache-control headers
        return redirect('/auth/logout-page')->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}