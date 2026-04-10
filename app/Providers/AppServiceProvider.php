<?php

namespace App\Providers;

use App\Services\Crm\Contracts\FollowUpServiceInterface;
use App\Services\Crm\Contracts\ShopifyServiceInterface;
use App\Services\Crm\FollowUpService;
use App\Services\Crm\ShopifyService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;
use App\Models\Permission;
use App\Models\FbaManualData;
use App\Observers\FbaManualDataObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FollowUpServiceInterface::class, FollowUpService::class);
        $this->app->singleton(ShopifyServiceInterface::class, ShopifyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register FbaManualData observer
        FbaManualData::observe(FbaManualDataObserver::class);

        View::composer('*', function ($view) {
            // Only set permissions if not already set by controller
            if (!$view->offsetExists('permissions')) {
                $permissions = [];
                if (Auth::check()) {
                    $userRole = Auth::user()->role;
                    $rolePermission = Permission::where('role', $userRole)->first();
                    $permissions = $rolePermission ? $rolePermission->permissions : [];
                }
                $view->with('permissions', $permissions);
            }
        });

        View::composer(['layouts.vertical', 'layouts.horizontal'], function (ViewInstance $view) {
            $this->composeLayoutFavicon($view);
        });
    }

    /**
     * Set favicon / apple-touch icon from config/page_icons.php using page $title, then route name.
     */
    private function composeLayoutFavicon(ViewInstance $view): void
    {
        $data = $view->getData();
        if (! empty($data['favicon'] ?? null)) {
            return;
        }

        $payload = $this->resolvePageIconFromConfig($data);
        if ($payload !== null) {
            $view->with($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $layoutData
     * @return array{favicon: string, faviconType: string, appleTouchIcon: string}|null
     */
    private function resolvePageIconFromConfig(array $layoutData): ?array
    {
        $cfg = config('page_icons', []);
        $definitions = $cfg['definitions'] ?? [];
        $iconKey = null;

        $title = isset($layoutData['title']) ? trim((string) $layoutData['title']) : '';
        if ($title !== '' && isset($cfg['by_title_exact'][$title])) {
            $iconKey = $cfg['by_title_exact'][$title];
        }

        if ($iconKey === null && $title !== '' && ! empty($cfg['by_title_contains']) && is_array($cfg['by_title_contains'])) {
            foreach ($cfg['by_title_contains'] as $needle => $key) {
                if ($needle !== '' && str_contains($title, (string) $needle)) {
                    $iconKey = $key;
                    break;
                }
            }
        }

        $request = request();
        $route = $request->route();

        if ($iconKey === null && $route && $route->getName() === 'any') {
            $seg = $route->parameter('any');
            $anyMap = $cfg['by_any_segment'] ?? [];
            if (is_string($seg) && $seg !== '' && isset($anyMap[$seg])) {
                $iconKey = $anyMap[$seg];
            }
        }

        if ($iconKey === null && $route && ($routeName = $route->getName()) && ! empty($cfg['by_route']) && is_array($cfg['by_route'])) {
            foreach ($cfg['by_route'] as $pattern => $key) {
                if (Str::is($pattern, $routeName)) {
                    $iconKey = $key;
                    break;
                }
            }
        }

        if ($iconKey === null || ! isset($definitions[$iconKey]['file'])) {
            return null;
        }

        $relative = $definitions[$iconKey]['file'];
        $url = preg_match('#^https?://#i', $relative) ? $relative : asset($relative);
        $mime = $this->mimeTypeForPublicIconPath($relative);

        return [
            'favicon' => $url,
            'faviconType' => $mime,
            'appleTouchIcon' => $url,
        ];
    }

    private function mimeTypeForPublicIconPath(string $relativePath): string
    {
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'ico' => 'image/x-icon',
            default => 'image/x-icon',
        };
    }
}
