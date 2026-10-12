<?php

namespace Tests\Unit;

use App\Models\CustomerFollowup;
use Carbon\Carbon;
use Tests\TestCase;

class CustomerFollowupDoneHistoryTest extends TestCase
{
    public function test_resolved_entry_records_who_marked_done(): void
    {
        $row = new CustomerFollowup();
        $row->appendExecutiveHistoryEntry('Hritiksha', 'created', Carbon::parse('2026-10-01 09:00:00'));
        $row->appendExecutiveHistoryEntry('Jasmine', 'resolved', Carbon::parse('2026-10-13 02:40:00'), ['status' => 'Resolved']);

        $history = $row->executive_history;
        $this->assertCount(2, $history);
        $this->assertSame('resolved', $history[1]['action']);
        $this->assertSame('Jasmine', $history[1]['name']);
        $this->assertSame('Resolved', $history[1]['status']);
    }

    public function test_same_status_by_same_person_does_not_duplicate(): void
    {
        $row = new CustomerFollowup();
        $row->appendExecutiveHistoryEntry('Jasmine', 'status_changed', Carbon::parse('2026-10-13 01:00:00'), ['status' => 'Escalated']);
        $row->appendExecutiveHistoryEntry('Jasmine', 'status_changed', Carbon::parse('2026-10-13 01:05:00'), ['status' => 'Escalated']);

        $this->assertCount(1, $row->executive_history);
        $this->assertSame(
            Carbon::parse('2026-10-13 01:05:00')->toIso8601String(),
            $row->executive_history[0]['at']
        );
    }

    public function test_different_status_appends_a_new_row(): void
    {
        $row = new CustomerFollowup();
        $row->appendExecutiveHistoryEntry('Jasmine', 'status_changed', Carbon::parse('2026-10-13 01:00:00'), ['status' => 'Escalated']);
        $row->appendExecutiveHistoryEntry('Jasmine', 'resolved', Carbon::parse('2026-10-13 02:00:00'), ['status' => 'Resolved']);

        $this->assertCount(2, $row->executive_history);
    }

    public function test_marked_done_index_prefers_explicit_resolved_action(): void
    {
        $history = [
            ['name' => 'Hritiksha', 'at' => '2026-10-01T09:00:00+05:30', 'action' => 'created'],
            ['name' => 'Suman', 'at' => '2026-10-13T02:40:00+05:30', 'action' => 'resolved', 'status' => 'Resolved'],
            ['name' => 'Jasmine', 'at' => '2026-10-13T03:10:00+05:30', 'action' => 'updated'],
        ];

        $index = CustomerFollowup::markedDoneHistoryIndex($history, Carbon::parse('2026-10-13 02:40:00'), 'Resolved');

        $this->assertSame(1, $index);
    }

    public function test_older_rows_match_the_entry_written_with_resolved_at(): void
    {
        $history = [
            ['name' => 'Hritiksha', 'at' => '2026-10-01T09:00:00+05:30', 'action' => 'created'],
            ['name' => 'Jasmine', 'at' => '2026-10-13T02:40:05+05:30', 'action' => 'status_changed'],
            ['name' => 'Suman', 'at' => '2026-10-13T06:00:00+05:30', 'action' => 'updated'],
        ];

        $index = CustomerFollowup::markedDoneHistoryIndex(
            $history,
            Carbon::parse('2026-10-13 02:40:00', 'Asia/Kolkata'),
            'Resolved'
        );

        $this->assertSame(1, $index);
    }

    public function test_open_tickets_have_no_marked_done_row(): void
    {
        $history = [
            ['name' => 'Jasmine', 'at' => '2026-10-13T02:40:00+05:30', 'action' => 'resolved', 'status' => 'Resolved'],
        ];

        $this->assertNull(CustomerFollowup::markedDoneHistoryIndex($history, Carbon::parse('2026-10-13 02:40:00'), 'Pending'));
    }
}
