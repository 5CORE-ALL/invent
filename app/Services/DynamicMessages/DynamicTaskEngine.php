<?php

namespace App\Services\DynamicMessages;

use App\Models\DynamicTaskCreation;
use App\Models\DynamicTaskRule;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWhatsAppNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Creates pre-saved Task Manager tasks for pre-saved users from page data / events.
 * Reuses the event catalogue, condition check and placeholder rendering of the message engine.
 * Every task is assigned BY Amarjit (config dynamic_messages.task_assignor_email).
 */
class DynamicTaskEngine
{
    public function __construct(
        private readonly DynamicMessageEngine $helper,
        private readonly TaskWhatsAppNotificationService $whatsApp
    ) {}

    public static function assignorEmail(): string
    {
        return strtolower(trim((string) config('dynamic_messages.task_assignor_email')));
    }

    /**
     * @return array{matches: list<array{user_id: int, name: string, title: string, description: string, skipped: ?string}>, created: int, skipped_duplicates: int, skipped_open: int, no_match: int, task_ids: list<int>}
     */
    public function runRule(DynamicTaskRule $rule, bool $dryRun = false): array
    {
        $result = ['matches' => [], 'created' => 0, 'skipped_duplicates' => 0, 'skipped_open' => 0, 'no_match' => 0, 'task_ids' => []];

        $def = DynamicMessageEvents::definition($rule->event_key);
        if (! $def) {
            return $result;
        }
        $manual = ! empty($def['manual']);

        $recipients = $this->helper->recipients($rule);
        if ($recipients->isEmpty()) {
            return $result;
        }

        $contexts = DynamicMessageEvents::resolve($rule->event_key, $recipients);
        $dedupe = $manual
            ? 'manual:'.now()->format('YmdHis').':'.bin2hex(random_bytes(3))
            : $this->helper->dedupeKey($rule, $def);

        foreach ($contexts as $ctx) {
            $targets = $ctx['user_id'] !== null
                ? $recipients->only([$ctx['user_id']])
                : $recipients;

            if (! $manual && ! $this->helper->conditionPasses($rule, $ctx['vars'])) {
                $result['no_match'] += $targets->count();

                continue;
            }

            foreach ($targets as $user) {
                $vars = $ctx['vars'];
                $title = mb_substr($this->helper->render($rule->title, $user, $vars), 0, 190);
                $description = $this->helper->render((string) $rule->description, $user, $vars);

                $skipped = null;
                if (! $manual && DynamicTaskCreation::query()
                    ->where('rule_id', $rule->id)->where('assignee_user_id', $user->id)->where('dedupe_key', $dedupe)->exists()) {
                    $skipped = 'duplicate';
                } elseif ($rule->skip_if_open && $this->hasOpenTask($rule, $user)) {
                    $skipped = 'open';
                }

                $result['matches'][] = [
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'title' => $title,
                    'description' => $description,
                    'skipped' => $skipped,
                ];

                if ($skipped === 'duplicate') {
                    $result['skipped_duplicates']++;

                    continue;
                }
                if ($skipped === 'open') {
                    $result['skipped_open']++;

                    continue;
                }
                if ($dryRun) {
                    continue;
                }

                $task = $this->createTask($rule, $user, $title, $description, $dedupe, $vars);
                if ($task === null) {
                    $result['skipped_duplicates']++;

                    continue;
                }
                $result['created']++;
                $result['task_ids'][] = (int) $task->id;
            }
        }

        if (! $dryRun) {
            $rule->forceFill(['last_run_at' => now()])->saveQuietly();
        }

        return $result;
    }

    /** Run every active, non-manual rule. @return array<int, array> keyed by rule id */
    public function runAll(bool $dryRun = false): array
    {
        $out = [];
        DynamicTaskRule::query()->where('is_active', true)->orderBy('id')->each(function (DynamicTaskRule $rule) use (&$out, $dryRun) {
            $def = DynamicMessageEvents::definition($rule->event_key);
            if (! $def || ! empty($def['manual'])) {
                return;
            }
            try {
                $out[$rule->id] = $this->runRule($rule, $dryRun);
            } catch (\Throwable $e) {
                report($e); // one broken rule must not block the others
                $out[$rule->id] = ['error' => $e->getMessage()];
            }
        });

        return $out;
    }

    /** True while an earlier task created by this rule for this person is still open. */
    private function hasOpenTask(DynamicTaskRule $rule, User $user): bool
    {
        $ids = DynamicTaskCreation::query()
            ->where('rule_id', $rule->id)
            ->where('assignee_user_id', $user->id)
            ->whereNotNull('task_id')
            ->pluck('task_id');
        if ($ids->isEmpty()) {
            return false;
        }

        // Task uses SoftDeletes: a deleted task no longer blocks a new one.
        return Task::query()
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['Done', 'Archived']);
            })
            ->exists();
    }

    private function createTask(DynamicTaskRule $rule, User $user, string $title, string $description, string $dedupe, array $vars): ?Task
    {
        // TID uses the app clock, same as every other task creator (ArrivedContainer, Temu reject, Tasks page).
        $tid = now()->addDays((int) $rule->tid_in_days);
        $completion = $tid->copy()->addDays(5); // same rule as tasks created from the Tasks page (TID + 5)

        $footer = 'Auto-created by dynamic task rule "'.$rule->name.'".';
        $data = [
            'title' => $title,
            'description' => trim($description) !== '' ? trim($description)."\n\n".$footer : $footer,
            'group' => $rule->task_group,
            'priority' => $rule->priority,
            'assignor' => self::assignorEmail(),
            'assign_to' => $user->email,
            'status' => 'Todo',
            'eta_time' => (int) $rule->eta_minutes,
            'start_date' => $tid,
            'completion_date' => $completion,
            'due_date' => $completion,
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'link1' => (string) ($rule->link ?? ''),
            'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '',
            'link6' => '', 'link7' => '', 'link8' => '', 'link9' => '',
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual',
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
            'split_tasks' => 0,
        ];
        if (Schema::hasColumn('tasks', 'is_corrective_action')) {
            $data['is_corrective_action'] = 0;
        }

        try {
            $task = DB::transaction(function () use ($rule, $user, $title, $dedupe, $vars, $data) {
                $creation = DynamicTaskCreation::firstOrCreate(
                    ['rule_id' => $rule->id, 'assignee_user_id' => $user->id, 'dedupe_key' => $dedupe],
                    ['title' => $title, 'context' => $vars]
                );
                if (! $creation->wasRecentlyCreated) {
                    return null;
                }
                $task = Task::create($data);
                $creation->forceFill(['task_id' => $task->id])->save();

                return $task;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'dyn_task_unique_creation')) {
                return null; // lost a race with another run: someone else created it
            }
            throw $e;
        }

        if ($task) {
            try {
                $this->whatsApp->notifyNewTaskAssigned($task);
            } catch (\Throwable $e) {
                Log::warning('Dynamic task WhatsApp notify failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return $task;
    }
}
