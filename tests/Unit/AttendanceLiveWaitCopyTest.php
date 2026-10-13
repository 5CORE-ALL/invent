<?php

namespace Tests\Unit;

use App\Services\Attendance\AttendanceLiveWatchService;
use PHPUnit\Framework\TestCase;

class AttendanceLiveWaitCopyTest extends TestCase
{
    public function test_up_to_date_agent_does_not_ask_for_old_version(): void
    {
        $copy = AttendanceLiveWatchService::viewerWaitCopy('1.4.4', '1.4.4', true, true);

        $this->assertStringContainsString('already has v1.4.4', $copy['text']);
        $this->assertStringNotContainsString('1.4.2', $copy['text']);
        $this->assertStringNotContainsString('install v', $copy['text']);
    }

    public function test_old_agent_asks_for_current_latest(): void
    {
        $copy = AttendanceLiveWatchService::viewerWaitCopy('1.4.2', '1.4.4', true, false);

        $this->assertSame('Desktop app needs an update', $copy['title']);
        $this->assertStringContainsString('v1.4.2', $copy['text']);
        $this->assertStringContainsString('v1.4.4', $copy['text']);
    }

    public function test_unseen_agent_asks_to_open_latest(): void
    {
        $copy = AttendanceLiveWatchService::viewerWaitCopy(null, '1.4.4', false, false);

        $this->assertStringContainsString('v1.4.4', $copy['text']);
        $this->assertStringNotContainsString('1.4.2', $copy['text']);
    }
}
