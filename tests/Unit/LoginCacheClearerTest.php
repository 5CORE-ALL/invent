<?php

namespace Tests\Unit;

use App\Support\LoginCacheClearer;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginCacheClearerTest extends TestCase
{
    public function test_login_flushes_application_cache(): void
    {
        Cache::put('login_clear_probe', 'stale', 60);

        LoginCacheClearer::run(null);

        $this->assertFalse(Cache::has('login_clear_probe'));
    }

    public function test_login_clear_is_idempotent_in_the_same_request(): void
    {
        Cache::put('login_clear_probe', 'stale', 60);
        LoginCacheClearer::run(null);
        Cache::put('login_clear_probe', 'fresh', 60);

        LoginCacheClearer::run(null);

        $this->assertSame('fresh', Cache::get('login_clear_probe'));
    }
}
