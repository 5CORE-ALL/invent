<?php

namespace Tests\Unit;

use App\Models\CronExecutionLog;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\CronMonitor\CronStatusResolver;
use App\Services\CronMonitor\CronValidationService;
use Tests\TestCase;

class CronMonitorBadgeValidationTest extends TestCase
{
    public function test_local_only_badge_job_does_not_require_api(): void
    {
        $ctx = new CronExecutionContext;
        $ctx->started = true;
        $ctx->setExpected(14);
        $ctx->setFetched(14);
        $ctx->setProcessed(14);
        $ctx->setUpdated(14);
        $ctx->markLocalOnly();

        $validation = (new CronValidationService)->validate($ctx);

        $this->assertTrue($validation['passed']);
        $this->assertSame([], $validation['messages']);
    }

    public function test_db_only_job_without_local_only_fails_api_check(): void
    {
        $ctx = new CronExecutionContext;
        $ctx->started = true;
        $ctx->setExpected(14);
        $ctx->setFetched(14);
        $ctx->setProcessed(14);
        $ctx->setUpdated(14);

        $validation = (new CronValidationService)->validate($ctx);

        $this->assertFalse($validation['passed']);
        $this->assertContains(
            'API did not connect or return a successful response.',
            $validation['messages']
        );
    }

    public function test_success_percentage_never_overflows_decimal_column(): void
    {
        $ctx = new CronExecutionContext;
        $ctx->setExpected(3);
        $ctx->setFetched(3);
        $ctx->setProcessed(3);
        $ctx->setUpdated(303);

        $result = (new CronStatusResolver)->resolve($ctx, true);

        $this->assertSame(100.0, $result['success_percentage']);
        $this->assertSame(CronExecutionLog::STATUS_SUCCESS, $result['status']);
    }
}
