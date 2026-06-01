<?php

namespace App\Http\Middleware;

use App\Models\Wms\WmsApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogWmsApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $reqBody = json_encode($request->except(['password', 'password_confirmation', 'token']), JSON_UNESCAPED_UNICODE);
        if (strlen($reqBody) > 8000) {
            $reqBody = substr($reqBody, 0, 8000).'…';
        }

        WmsApiRequestLog::query()->create([
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => substr($request->path(), 0, 500),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'request_body' => $reqBody,
            'response_body' => null,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $response;
    }
}
