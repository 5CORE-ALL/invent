<?php

namespace App\Support;

use App\Support\Marketplace\ListingChannelCounts;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daily automated task for Tannistha. The generated title uses the same
 * Missing L total as the badge on /missing-listing.
 */
class MissingListingDailyAutomatedTask
{
    public const MARKER = 'missing-listing-daily';

    public const ASSIGN_TO = 'pricing1@5core.com';

    public const ASSIGNOR = 'president@5core.com';

    public const TITLE = 'Missing Listing';

    public const GROUP = 'Missing Listing';

    public const LINK = 'https://inventory.5coremanagement.com/missing-listing';

    public static function ensureTemplate(): void
    {
        if (! Schema::hasTable('automate_tasks') || ! Schema::hasColumn('automate_tasks', 'link8')) {
            return;
        }

        $existing = DB::table('automate_tasks')->where('link8', self::MARKER)->first();
        $now = now();
        $payload = [
            'title' => self::TITLE,
            'priority' => 'Normal',
            'split_tasks' => 0,
            'group' => self::GROUP,
            'description' => 'Daily Missing Listing task. The count matches the Missing L badge on /missing-listing.',
            'eta_time' => 60,
            'assign_to' => self::ASSIGN_TO,
            'assignor' => self::ASSIGNOR,
            'link1' => self::LINK,
            'schedule_type' => 'daily',
            'schedule_time' => TaskBusinessTime::dailyGenerateTime(),
            'schedule_days' => '',
            'status' => 'Todo',
            'link8' => self::MARKER,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('automate_tasks')->where('id', $existing->id)->update($payload);

            return;
        }

        $payload['start_date'] = $now;
        $payload['due_date'] = $now;
        $payload['order'] = 0;
        $payload['workspace'] = 0;
        $payload['created_at'] = $now;
        if (Schema::hasColumn('automate_tasks', 'parent_task_id')) {
            $payload['parent_task_id'] = null;
        }
        if (Schema::hasColumn('automate_tasks', 'subtask_order')) {
            $payload['subtask_order'] = 0;
        }

        DB::table('automate_tasks')->insert($payload);
    }

    /**
     * @return array{title: string, description: string, count: int}
     */
    public static function instanceCopy(CarbonInterface $now): array
    {
        $count = ListingChannelCounts::totalMissingL(false);
        $label = number_format($count);

        return [
            'title' => self::TITLE.': '.$label.' [Auto: '.$now->format('d-M-y').']',
            'description' => 'Auto-assigned from /missing-listing Missing L badge ('.$label.').',
            'count' => $count,
        ];
    }

    public static function isTemplate(object $autoTask): bool
    {
        return (string) ($autoTask->link8 ?? '') === self::MARKER;
    }

    public static function createTodayIfMissing(): ?int
    {
        self::ensureTemplate();
        $template = DB::table('automate_tasks')->where('link8', self::MARKER)->first();
        if (! $template) {
            return null;
        }

        TaskBusinessTime::applyDatabaseSession();
        $now = TaskBusinessTime::now();
        $dayStart = TaskBusinessTime::todayStart()->format('Y-m-d H:i:s');
        $dayEnd = TaskBusinessTime::todayEnd()->format('Y-m-d H:i:s');

        $already = DB::table('tasks')
            ->where('automate_task_id', $template->id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('start_date', [$dayStart, $dayEnd])
                    ->orWhereBetween('created_at', [$dayStart, $dayEnd]);
            })
            ->exists();
        if ($already) {
            return null;
        }

        $copy = self::instanceCopy($now);
        $scheduleTime = TaskBusinessTime::dailyGenerateTime();
        $parts = array_map('intval', explode(':', $scheduleTime));
        $startDate = TaskBusinessTime::today()->setTime($parts[0] ?? 12, $parts[1] ?? 0, $parts[2] ?? 0);
        $dueDate = $startDate->copy()->addDays(5);

        try {
            $nextId = ((int) DB::table('tasks')->max('id')) + 1;

            DB::table('tasks')->insert([
                'id' => $nextId,
                'task_id' => null,
                'title' => $copy['title'],
                'group' => self::GROUP,
                'priority' => 'Normal',
                'description' => $copy['description'],
                'eta_time' => 60,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'is_automate_task' => 1,
                'completion_date' => $dueDate,
                'completion_day' => 0,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'split_tasks' => 0,
                'assign_to' => self::ASSIGN_TO,
                'assignor' => self::ASSIGNOR,
                'link1' => self::LINK,
                'link8' => self::MARKER,
                'automate_task_id' => $template->id,
                'task_type' => 'automate_task',
                'schedule_type' => 'daily',
                'schedule_time' => $scheduleTime,
                'status' => 'Todo',
                'delete_rating' => 0,
                'order' => 0,
                'workspace' => 0,
                'is_data_from' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $nextId;
        } catch (\Throwable $e) {
            Log::error('Missing listing daily task insert failed: '.$e->getMessage());

            throw $e;
        }
    }
}
