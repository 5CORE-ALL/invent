<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CloseDbConnections
{
    /**
     * Handle an incoming request and ensure all DB connections are closed after response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Disconnect all database connections after request
        foreach (config('database.connections') as $name => $connection) {
            DB::purge($name);
        }

        return $response;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Final cleanup
        DB::disconnect();
    }
}
