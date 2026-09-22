<?php

namespace Tests\Unit;

use App\Models\Task;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function test_monitor_is_a_selectable_task_status(): void
    {
        $this->assertContains('Monitor', Task::STATUSES);
        $this->assertContains('Hold', Task::STATUSES);
        $this->assertContains('Rework', Task::STATUSES);
    }
}
