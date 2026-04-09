<?php

namespace App\Providers;

use App\Models\ResourceMaster;
use App\Models\User;
use App\Policies\ResourceMasterPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Task::class => \App\Policies\TaskPolicy::class,
        ResourceMaster::class => ResourceMasterPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('resources-master.manage', function (User $user): bool {
            $emails = array_map('strtolower', config('resources_master.manager_emails', []));

            return in_array(strtolower((string) $user->email), $emails, true);
        });

        Gate::define('resources-master.force-delete', function (User $user): bool {
            $emails = array_map('strtolower', config('resources_master.force_delete_emails', []));

            return in_array(strtolower((string) $user->email), $emails, true);
        });
    }
}
