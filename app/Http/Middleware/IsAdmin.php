<?php

namespace App\Http\Middleware;

use App\Support\SuperAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (Auth::check() && SuperAdminAccess::isRoleAdmin($user)) {
            return $next($request);
        }
        abort(403, 'Unauthorized - Admin access required');
    }
}
