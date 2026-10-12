<?php

namespace Tests\Unit;

use App\Support\TaskDoneChecklistReview;
use PHPUnit\Framework\TestCase;

class TaskDoneChecklistReviewTest extends TestCase
{
    public function test_report_text_rebuilds_yes_and_no_ticks(): void
    {
        $questions = [
            ['id' => 'q1', 'type' => 'checkbox', 'label' => 'Stock checked'],
            ['id' => 'q2', 'type' => 'checkbox', 'label' => 'Price matched'],
            ['id' => 'q3', 'type' => 'text', 'label' => 'Notes'],
        ];

        $report = implode("\n", [
            'Checklist Form #12 — Daily checklist',
            '1. Stock checked: Yes',
            '2. Price matched: No | Action: Update sheet | Action 2: Ping buyer | Corrective action: Reprice SKU',
            '3. Notes: Left a comment',
        ]);

        $answers = TaskDoneChecklistReview::answersFromReport($questions, $report);

        $this->assertTrue($answers['q1']['answer']);
        $this->assertSame([], $answers['q1']['actions']);
        $this->assertFalse($answers['q2']['answer']);
        $this->assertSame(['Update sheet', 'Ping buyer'], $answers['q2']['actions']);
        $this->assertSame('Reprice SKU', $answers['q2']['corrective_action']);
        $this->assertSame('Left a comment', $answers['q3']);
    }

    public function test_plain_completion_report_is_not_treated_as_a_checklist(): void
    {
        $questions = [
            ['id' => 'q1', 'type' => 'checkbox', 'label' => 'Done'],
        ];

        $this->assertSame(
            [],
            TaskDoneChecklistReview::answersFromReport($questions, "1. Done: Yes\nFinished the listing.")
        );
    }
}
