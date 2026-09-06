<?php

namespace Tests\Feature;

use Tests\TestCase;

class RunMissedScheduledCommandsTest extends TestCase
{
    public function test_dry_run_exits_successfully(): void
    {
        $this->artisan('cron:run-missed', ['--dry-run' => true])
            ->expectsOutputToContain('cron:run-missed')
            ->assertSuccessful();
    }
}
