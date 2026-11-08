<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily automated task for the listing manager: review the cells marked with red text on /ebay1-data.
 * The template is created once (found again by its marker). After that it can be edited, archived or
 * deleted on /tasks/automated like any other automated task.
 */
class Ebay1RedTextReviewAutomatedTask
{
    public const MARKER = 'ebay1-data-red-text-review';

    public const ASSIGN_TO = 'listing-mgr@5core.com';

    public const ASSIGNOR = 'president@5core.com';

    public const TITLE = 'Review red text and fix. if text is ok then change background to default';

    public const GROUP = 'Ebay1 Data';

    public const LINK = 'https://inventory.5coremanagement.com/ebay1-data';

    public static function ensureTemplate(): void
    {
        if (! Schema::hasTable('automate_tasks') || ! Schema::hasColumn('automate_tasks', 'link8')) {
            return;
        }
        // Any status counts (archived included): once created, it is the team's to manage.
        if (DB::table('automate_tasks')->where('link8', self::MARKER)->exists()) {
            return;
        }

        $now = now();
        $payload = [
            'title' => self::TITLE,
            'priority' => 'Normal',
            'split_tasks' => 0,
            'group' => self::GROUP,
            'description' => 'Open the Ebay1 Data page and review the cells with red text. Fix the text. If the text is ok, change the cell background to default (No Fill).',
            'eta_time' => 30,
            'assign_to' => self::ASSIGN_TO,
            'assignor' => self::ASSIGNOR,
            'link1' => self::LINK,
            'schedule_type' => 'daily',
            'schedule_time' => TaskBusinessTime::dailyGenerateTime(),
            'schedule_days' => '',
            'status' => 'Todo',
            'link8' => self::MARKER,
            'start_date' => $now,
            'due_date' => $now,
            'order' => 0,
            'workspace' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('automate_tasks', 'parent_task_id')) {
            $payload['parent_task_id'] = null;
        }
        if (Schema::hasColumn('automate_tasks', 'subtask_order')) {
            $payload['subtask_order'] = 0;
        }

        DB::table('automate_tasks')->insert($payload);
    }
}
