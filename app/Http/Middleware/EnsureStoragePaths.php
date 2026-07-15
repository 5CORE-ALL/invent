<?php

namespace App\Http\Middleware;

use App\Support\StoragePathGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoragePaths
{
    public function handle(Request $request, Closure $next): Response
    {
        StoragePathGuard::ensure();

        return $next($request);
    }
}
