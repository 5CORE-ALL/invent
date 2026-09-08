<?php

namespace Tests\Unit;

use App\Models\Task;
use PHPUnit\Framework\TestCase;

class TaskScreenshotFilenamesTest extends TestCase
{
    public function test_legacy_image_column_is_included(): void
    {
        $task = new Task;
        $task->setRawAttributes([
            'image' => 'shot-one.png',
        ]);

        $this->assertSame(['shot-one.png'], $task->screenshotFilenames());
    }

    public function test_json_screenshots_merge_with_legacy_image(): void
    {
        $task = new Task;
        $task->setRawAttributes([
            'image' => 'first.png',
            'screenshots' => json_encode(['first.png', 'second.png', 'third.png']),
        ]);

        $this->assertSame(['first.png', 'second.png', 'third.png'], $task->screenshotFilenames());
    }

    public function test_path_segments_are_rejected(): void
    {
        $task = new Task;
        $task->setRawAttributes([
            'image' => '../secret.png',
            'screenshots' => json_encode(['ok.png', 'folder/bad.png']),
        ]);

        $this->assertSame(['ok.png'], $task->screenshotFilenames());
    }
}
