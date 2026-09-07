<?php

namespace Tests\Unit;

use App\Models\CronExecutionLog;
use PHPUnit\Framework\TestCase;

class CronExecutionLogSuccessPercentageTest extends TestCase
{
    public function test_clamps_overflow_from_row_vs_work_item_counts(): void
    {
        // google:save-badge-l30-snapshots wrote 303 campaign rows / 3 channel-days = 10100
        $this->assertSame(100.0, CronExecutionLog::clampSuccessPercentage(10100));
        $this->assertSame(100.0, CronExecutionLog::clampSuccessPercentage(100));
        $this->assertSame(66.67, CronExecutionLog::clampSuccessPercentage(66.666));
        $this->assertSame(0.0, CronExecutionLog::clampSuccessPercentage(-5));
        $this->assertNull(CronExecutionLog::clampSuccessPercentage(null));
    }
}
