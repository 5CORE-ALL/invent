<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::user();
        if (! $user) {
            return $next($request);
        }

        $raw = $user->getAttributes()['is_active'] ?? null;
        if ($raw === null || (int) $raw === 1) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('attendance/desktop-api/*')) {
            return response()->json([
                'ok' => false,
                'message' => 'This account is inactive. Contact an administrator.',
            ], 403);
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
    }
}
