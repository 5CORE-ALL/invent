<?php

namespace App\Support;

use App\Models\DeletedTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks that became missed yesterday, grouped for the assignee's morning message.
 *
 * Daily automated and normal tasks are missed on their start date.
 * Weekly and monthly automated tasks are missed 6 days after they were created.
 */
final class UserMissedYesterday
{
    public const LIST_LIMIT = 15;

    public static function messageFor(User $user): ?string
    {
        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return null;
        }

        $titles = self::titlesForEmail($email);
        if ($titles === []) {
            return null;
        }

        return self::formatBody(
            $titles,
            TaskBusinessTime::today()->subDay()->format('j M'),
            url('/tasks')
        );
    }

    /**
     * @param  list<string>  $titles
     */
    public static function formatBody(array $titles, string $dateLabel, string $tasksUrl = '/tasks'): ?string
    {
        $titles = array_values(array_filter(array_map(
            fn ($title) => self::cleanTitle((string) $title),
            $titles
        )));
        if ($titles === []) {
            return null;
        }

        $count = count($titles);
        $shown = array_slice($titles, 0, self::LIST_LIMIT);
        $lines = array_map(fn ($title) => '• '.$title, $shown);
        $extra = $count - count($shown);
        if ($extra > 0) {
            $lines[] = '• +'.$extra.' more';
        }

        $word = $count === 1 ? 'task' : 'tasks';

        return "You missed {$count} {$word} yesterday ({$dateLabel}).\n"
            .implode("\n", $lines)."\n"
            .'Open Tasks: '.$tasksUrl;
    }

    /**
     * @return list<string>
     */
    public static function titlesForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [];
        }

        $yesterday = TaskBusinessTime::today()->subDay()->toDateString();
        $days = (int) TaskBusinessTime::weeklyMonthlyOverdueDays();
        $titles = [];

        $live = Task::query()
            ->whereNotNull('start_date')
            ->whereNotIn('status', ['Done', 'Archived', 'Cancelled'])
            ->where(function ($query) use ($email) {
                self::whereAssignee($query, $email);
            })
            ->whereRaw(
                "(CASE
                    WHEN COALESCE(is_automate_task, 0) = 1
                         AND LOWER(COALESCE(schedule_type, '')) IN ('weekly', 'monthly')
                    THEN DATE_ADD(DATE(COALESCE(created_at, start_date)), INTERVAL {$days} DAY) = ?
                    ELSE DATE(start_date) = ?
                END)",
                [$yesterday, $yesterday]
            )
            ->orderBy('id')
            ->get(['title']);

        foreach ($live as $task) {
            self::pushTitle($titles, (string) ($task->title ?? ''));
        }

        if (Schema::hasTable('deleted_tasks')) {
            $archived = DeletedTask::query()
                ->whereNotNull('start_date')
                ->whereRaw('DATE(start_date) = ?', [$yesterday])
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', '')
                        ->orWhereNotIn('status', ['Done', 'Archived', 'Cancelled']);
                })
                ->where(function ($query) use ($email) {
                    self::whereAssignee($query, $email);
                })
                ->orderBy('id')
                ->get(['title']);

            foreach ($archived as $task) {
                self::pushTitle($titles, (string) ($task->title ?? ''));
            }
        }

        return array_values($titles);
    }

    /**
     * @param  array<string, string>  $titles
     */
    private static function pushTitle(array &$titles, string $title): void
    {
        $clean = self::cleanTitle($title);
        if ($clean === '') {
            return;
        }
        $titles[strtolower($clean)] = $clean;
    }

    public static function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s*\[Auto:\s*\d{1,2}-[A-Za-z]{3}-\d{2}\]\s*$/i', '', $title) ?? $title);

        return trim($title);
    }

    private static function whereAssignee($query, string $email): void
    {
        $query->whereRaw('LOWER(TRIM(assign_to)) = ?', [$email])
            ->orWhereRaw('LOWER(assign_to) LIKE ?', [$email.',%'])
            ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email])
            ->orWhereRaw('LOWER(assign_to) LIKE ?', ['%,'.$email.',%']);
    }
}
