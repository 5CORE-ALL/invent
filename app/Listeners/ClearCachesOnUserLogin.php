<?php

namespace App\Listeners;

use App\Support\LoginCacheClearer;
use Illuminate\Auth\Events\Login;

class ClearCachesOnUserLogin
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        LoginCacheClearer::run($event->user?->id);
    }
}
