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
