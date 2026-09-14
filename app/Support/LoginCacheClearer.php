<?php

namespace App\Support;

use App\Helpers\PermissionHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drop stale app / view / browser caches when a user signs in, then rebuild
 * that user's permission cache. Shared session stores are left intact.
 */
final class LoginCacheClearer
{
    public static function run(?int $userId): void
    {
        $request = request();
        if ($request && $request->attributes->get('login_cache_cleared')) {
            if ($userId) {
                PermissionHelper::cacheUserPermissions($userId);
            }

            return;
        }

        if ($request) {
            $request->attributes->set('login_cache_cleared', true);
        }

        try {
            if ($userId) {
                PermissionHelper::forgetUserPermissions($userId);
            }

            self::flushApplicationCache();
            Artisan::call('view:clear');
            StoragePathGuard::ensure();

            if ($userId) {
                PermissionHelper::cacheUserPermissions($userId);
            }

            if (app()->bound('session') && session()->isStarted()) {
                session()->flash('clear_browser_cache', true);
            }
        } catch (\Throwable $e) {
            Log::warning('Login cache clear failed', ['error' => $e->getMessage()]);

            if ($userId) {
                try {
                    PermissionHelper::cacheUserPermissions($userId);
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    public static function noCacheRedirect(RedirectResponse $redirect): RedirectResponse
    {
        return $redirect->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private static function flushApplicationCache(): void
    {
        $sessionDriver = (string) config('session.driver');
        $sessionUsesDefaultCache = in_array($sessionDriver, ['cache', 'redis', 'memcached', 'dynamodb'], true)
            && (string) config('session.store', config('cache.default')) === (string) config('cache.default');

        if ($sessionUsesDefaultCache) {
            return;
        }

        Cache::flush();
    }
}
