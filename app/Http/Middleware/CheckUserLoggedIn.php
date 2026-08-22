<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserLoggedIn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();

            $rawActive = $user->getAttributes()['is_active'] ?? null;
            if ($rawActive !== null && (int) $rawActive !== 1) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->is('attendance/desktop-api/*')) {
                    return response()->json([
                        'ok' => false,
                        'force_logout' => true,
                        'message' => 'This account is inactive. Contact an administrator.',
                    ], 401);
                }

                return redirect()->route('login')
                    ->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
            }

            // Stay-logged-in survives idle/browser close, not an admin kick.
            if ($user->staysLoggedIn() && (! isset($user->logined) || (int) $user->logined !== 0)) {
                return $next($request);
            }
            
            // If logined field exists and is 0, logout the user
            if (isset($user->logined) && $user->logined == 0) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
        
        return $next($request);
    }
}
