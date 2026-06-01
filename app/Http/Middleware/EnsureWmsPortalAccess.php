<?php

namespace App\Http\Middleware;

use App\Services\Wms\WmsAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWmsPortalAccess
{
    /**
     * Limit WMS UI to known roles; admins always pass.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $allowed = WmsAuthorization::isAdmin($user)
            || WmsAuthorization::isWarehouseManager($user)
            || WmsAuthorization::isWarehouseStaff($user)
            || in_array($user->role, ['user'], true);

        if (! $allowed) {
            abort(403, 'WMS requires an allowed role (admin, manager, warehouse_*, staff, or user).');
        }

        return $next($request);
    }
}
