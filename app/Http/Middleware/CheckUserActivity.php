<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActivity
{
    /**
     * Handle an incoming request.
     * Auto logout user after 6 hours of inactivity
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Define inactivity timeout (6 hours in minutes)
            $inactivityTimeout = 360; // 6 hours = 360 minutes
            
            // Check if user has last_activity_at timestamp
            if ($user->last_activity_at) {
                $lastActivity = Carbon::parse($user->last_activity_at);
                $minutesInactive = $lastActivity->diffInMinutes(Carbon::now());
                
                // If inactive for more than 6 hours, auto logout
                if ($minutesInactive >= $inactivityTimeout) {
                    // Record logout timestamp
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'auto_logged_out_at' => Carbon::now(),
                        ]);
                    
                    // Logout the user
                    Auth::logout();
                    
                    // Invalidate session
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    // Redirect to login with message
                    return redirect()->route('login')
                        ->with('error', 'Aap 6 hours se inactive the. Please login again.');
                }
            }
            
            // Update last activity timestamp
            DB::table('users')
                ->where('id', $user->id)
                ->update(['last_activity_at' => Carbon::now()]);
        }
        
        return $next($request);
    }
}

