<?php

namespace App\Support;

use App\Models\Task;
use App\Services\TaskWhatsAppNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * When a parent automate_tasks template fires, also create task instances
 * for its child subtask templates, linked via tasks.parent_task_id.
 */
class AutomatedTaskSubtaskFirer
{
    /**
     * Fire all child subtask templates under $parentAutomateTaskId,
     * linking each new instance to $parentTaskInstanceId.
     *
     * @return int Number of child instances created
     */
    public static function fireChildren(
        int $parentAutomateTaskId,
        int $parentTaskInstanceId,
        Carbon $now,
        Carbon $startDate,
        Carbon $dueDate,
        string $scheduleType,
        string $scheduleTime,
        ?TaskWhatsAppNotificationService $taskWhatsApp = null
    ): int {
        if (! Schema::hasColumn('automate_tasks', 'parent_task_id')) {
            return 0;
        }

        $children = DB::table('automate_tasks')
            ->where('parent_task_id', $parentAutomateTaskId)
            ->whereNotIn('status', ['Done', 'Archived'])
            ->orderBy('subtask_order')
            ->orderBy('id')
            ->get();

        if ($children->isEmpty()) {
            return 0;
        }

        $created = 0;

        foreach ($children as $child) {
            try {
                $assignTo = trim((string) ($child->assign_to ?? ''));
                if ($assignTo === '') {
                    $assignTo = trim((string) ($child->assignor ?? ''));
                }

                $taskData = [
                    'task_id' => null,
                    'title' => ($child->title ?? 'Subtask') . ' [Auto: ' . $now->format('d-M-y') . ']',
                    'group' => $child->group,
                    'priority' => $child->priority ?? 'normal',
                    'description' => $child->description,
                    'eta_time' => $child->eta_time ?? 0,
                    'etc_done' => 0,
                    'is_missed' => 0,
                    'is_missed_track' => 0,
                    'is_automate_task' => 1,
                    'completion_date' => $dueDate,
                    'completion_day' => 0,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'split_tasks' => $child->split_tasks ?? 0,
                    'assign_to' => $assignTo ?: null,
                    'assignor' => $child->assignor,
                    'link1' => $child->link1 ?? null,
                    'link2' => $child->link2 ?? null,
                    'link3' => $child->link3 ?? null,
                    'link4' => $child->link4 ?? null,
                    'link5' => $child->link5 ?? null,
                    'link6' => $child->link6 ?? null,
                    'link7' => $child->link7 ?? null,
                    'link8' => null,
                    'link9' => null,
                    'image' => null,
                    'automate_task_id' => $child->id,
                    'parent_task_id' => $parentTaskInstanceId,
                    'subtask_order' => (int) ($child->subtask_order ?? 0),
                    'task_type' => 'automate_task',
                    'schedule_type' => $scheduleType,
                    'schedule_time' => $scheduleTime,
                    'status' => 'Todo',
                    'rework_reason' => null,
                    'delete_rating' => 0,
                    'delete_feedback' => null,
                    'order' => $child->order ?? 0,
                    'workspace' => $child->workspace ?? 0,
                    'is_data_from' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $taskId = DB::table('tasks')->insertGetId($taskData);
                $created++;

                if ($taskWhatsApp && $assignTo) {
                    $taskInstance = Task::find($taskId);
                    if ($taskInstance) {
                        try {
                            $taskWhatsApp->notifyNewTaskAssigned($taskInstance);
                        } catch (\Throwable $e) {
                            Log::warning('Task WhatsApp notify new assigned (automated subtask) failed: ' . $e->getMessage(), [
                                'task_id' => $taskId,
                                'parent_task_id' => $parentTaskInstanceId,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('AutomatedTaskSubtaskFirer: failed to create child instance', [
                    'parent_automate_task_id' => $parentAutomateTaskId,
                    'child_automate_task_id' => $child->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }
}
