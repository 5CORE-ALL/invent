<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\Permission;
use App\Models\FbaManualData;
use App\Observers\FbaManualDataObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }



}
