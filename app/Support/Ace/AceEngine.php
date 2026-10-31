<?php

namespace App\Support\Ace;

use App\Models\AutoChatEvent;
use App\Models\AutoChatEventRun;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ACE (Automated Chat Event) engine.
 *
 *  - trigger "column":       the scheduler (ace:run) tests the page's rows against the rule's
 *                            column condition and creates a one-time task per matching row.
 *  - trigger "chat":         when a chat message contains the keyword (in the chosen channel,
 *                            or any channel) one one-time task is created for that message.
 *  - trigger "chat_column":  when such a message arrives, a one-time task is created for every
 *                            row that currently matches the column condition.
 *
 * "One-time": (rule, row/message) can only fire once (auto_chat_event_runs is unique on it).
 */
class AceEngine
{
    public const OPERATORS = [
        'equals' => 'is',
        'not_equals' => 'is not',
        'contains' => 'contains',
        'not_contains' => 'does not contain',
        'gt' => 'is greater than',
        'lt' => 'is less than',
        'empty' => 'is empty',
        'not_empty' => 'is not empty',
    ];

    /** Safety valve: tasks one rule may create per run. */
    public const MAX_PER_RUN = 100;

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('auto_chat_events') && Schema::hasTable('auto_chat_event_runs');
    }

    // ------------------------------------------------------------------
    // Condition testing
    // ------------------------------------------------------------------

    public static function matches(array $row, ?string $key, ?string $operator, ?string $value): bool
    {
        if (! $key || ! $operator) {
            return true; // no column condition: every row qualifies
        }

        $cell = isset($row[$key]) ? trim((string) $row[$key]) : '';
        $want = trim((string) $value);
        $a = mb_strtolower($cell);
        $b = mb_strtolower($want);

        return match ($operator) {
            'equals' => $a === $b,
            'not_equals' => $a !== $b,
            'contains' => $b !== '' && str_contains($a, $b),
            'not_contains' => $b === '' || ! str_contains($a, $b),
            'gt' => is_numeric($cell) && is_numeric($want) && (float) $cell > (float) $want,
            'lt' => is_numeric($cell) && is_numeric($want) && (float) $cell < (float) $want,
            'empty' => $cell === '',
            'not_empty' => $cell !== '',
            default => false,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function matchingRows(AutoChatEvent $ace): array
    {
        $rows = AcePages::rows($ace->page);

        return array_values(array_filter($rows, fn ($r) => self::matches($r, $ace->column_key, $ace->operator, $ace->value)));
    }

    // ------------------------------------------------------------------
    // Scheduler entry point (column rules)
    // ------------------------------------------------------------------

    /**
     * Run every active column-only rule (or just one). Returns the number of tasks created.
     */
    public static function runColumnRules(?int $aceId = null): int
    {
        if (! self::ready()) {
            return 0;
        }

        $q = AutoChatEvent::query()->where('is_active', true)->where('trigger_type', 'column');
        if ($aceId) {
            $q->where('id', $aceId);
        }

        $created = 0;
        foreach ($q->get() as $ace) {
            try {
                $created += self::fireForRows($ace, self::matchingRows($ace), 'column', null);
            } catch (\Throwable $e) {
                Log::warning('ACE #'.$ace->id.' column run failed: '.$e->getMessage());
            }
        }

        return $created;
    }

    /**
     * Run a single rule on demand ("Run now"). Chat-triggered rules need a message, so only
     * column rules can be run manually.
     */
    public static function runNow(AutoChatEvent $ace): int
    {
        if ($ace->trigger_type !== 'column') {
            return 0;
        }

        return self::runColumnRules((int) $ace->id);
    }

    // ------------------------------------------------------------------
    // Chat hook
    // ------------------------------------------------------------------

    public static function onChatMessage(ChatMessage $message): void
    {
        if (! self::ready() || $message->is_bot) {
            return;
        }
        $body = trim((string) $message->body);
        if ($body === '') {
            return;
        }

        $rules = AutoChatEvent::query()
            ->where('is_active', true)
            ->whereIn('trigger_type', ['chat', 'chat_column'])
            ->where(function ($q) use ($message) {
                $q->whereNull('chat_channel_id')->orWhere('chat_channel_id', $message->channel_id);
            })
            ->get();

        foreach ($rules as $ace) {
            if (! self::keywordHit($ace->chat_keyword, $body)) {
                continue;
            }
            try {
                $ctx = ['message' => $body, 'message_id' => (int) $message->id, 'sender_id' => (int) $message->user_id,
                    'channel' => (string) (ChatChannel::query()->whereKey($message->channel_id)->value('name') ?? ''),
                    'sender' => (string) (User::query()->whereKey($message->user_id)->value('name') ?? '')];

                if ($ace->trigger_type === 'chat_column') {
                    self::fireForRows($ace, self::matchingRows($ace), 'chat', $ctx);
                } else {
                    self::fireOne($ace, 'msg:'.$message->id, Str::limit((string) preg_replace('/\s+/', ' ', $body), 120), null, 'chat', $ctx);
                }
            } catch (\Throwable $e) {
                Log::warning('ACE #'.$ace->id.' chat run failed: '.$e->getMessage());
            }
        }
    }

    public static function keywordHit(?string $keywords, string $body): bool
    {
        $lower = mb_strtolower($body);
        foreach (explode(',', (string) $keywords) as $k) {
            $k = trim(mb_strtolower($k));
            if ($k !== '' && str_contains($lower, $k)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Firing
    // ------------------------------------------------------------------

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $ctx  chat context (message, sender, channel...)
     */
    private static function fireForRows(AutoChatEvent $ace, array $rows, string $source, ?array $ctx): int
    {
        if ($rows === []) {
            return 0;
        }

        $done = AutoChatEventRun::query()->where('ace_id', $ace->id)
            ->whereIn('subject_key', array_map(fn ($r) => 'row:'.$r['id'], $rows))
            ->pluck('subject_key')->flip();

        $created = 0;
        foreach ($rows as $row) {
            if ($created >= self::MAX_PER_RUN) {
                break;
            }
            $key = 'row:'.$row['id'];
            if (isset($done[$key])) {
                continue;
            }
            if (self::fireOne($ace, $key, (string) ($row['label'] ?? $key), $row, $source, $ctx)) {
                $created++;
            }
        }
        $ace->forceFill(['last_run_at' => now()])->save();

        return $created;
    }

    /**
     * Create the one-time task for a subject. Returns true if a task was created.
     *
     * @param  array<string, mixed>|null  $row
     * @param  array<string, mixed>|null  $ctx
     */
    private static function fireOne(AutoChatEvent $ace, string $subjectKey, string $label, ?array $row, string $source, ?array $ctx): bool
    {
        // Claim the (rule, subject) slot first: the unique key makes this race-safe.
        try {
            $run = AutoChatEventRun::query()->create([
                'ace_id' => $ace->id,
                'subject_key' => $subjectKey,
                'subject_label' => mb_substr($label, 0, 255),
                'source' => $source,
                'status' => 'created',
            ]);
        } catch (\Throwable $e) {
            return false; // already fired for this subject
        }

        try {
            $task = self::createTask($ace, $row ?? [], $ctx ?? [], $label);
            $run->forceFill(['task_id' => $task->id])->save();
            $ace->forceFill(['last_run_at' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            $run->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)])->save();
            Log::warning('ACE #'.$ace->id.' could not create task: '.$e->getMessage());

            return false;
        }
    }

    // ------------------------------------------------------------------
    // Task creation (same `tasks` table / fields the task manager uses)
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     */
    private static function createTask(AutoChatEvent $ace, array $row, array $ctx, string $label): Task
    {
        $vars = array_merge(
            array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $row),
            [
                'row' => $label,
                'message' => (string) ($ctx['message'] ?? ''),
                'channel' => (string) ($ctx['channel'] ?? ''),
                'sender' => (string) ($ctx['sender'] ?? ''),
                'date' => now()->toDateString(),
            ]
        );

        $creator = $ace->created_by ? User::query()->find($ace->created_by) : null;
        $assignee = self::resolveAssignee($ace, $row, $ctx) ?? $creator;
        if (! $assignee) {
            throw new \RuntimeException('No assignee could be resolved for this rule.');
        }

        $due = now()->addDays((int) $ace->due_in_days);
        $data = [
            'title' => mb_substr(self::render($ace->task_title, $vars), 0, 1000),
            'description' => self::render((string) $ace->task_description, $vars) ?: null,
            'group' => $ace->task_group ?: null,
            'priority' => in_array($ace->priority, ['low', 'normal', 'high'], true) ? $ace->priority : 'normal',
            'assignor' => $creator?->email ?? $assignee->email,
            'assign_to' => $assignee->email,
            'split_tasks' => 0,
            'parent_task_id' => null,
            'subtask_order' => 0,
            'status' => 'Todo',
            'eta_time' => 10,
            'start_date' => now(),
            'completion_date' => $due,
            'due_date' => $due,
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'link1' => '', 'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '',
            'link6' => '', 'link7' => '', 'link8' => '', 'link9' => '',
            'image' => null,
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual', // a one-time task
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
        ];
        if (Schema::hasColumn('tasks', 'screenshots')) {
            $data['screenshots'] = [];
        }

        return DB::transaction(function () use ($data) {
            $task = new Task($data);
            if (! self::tasksIdAutoIncrements()) {
                $max = (int) (DB::table('tasks')->lockForUpdate()->max('id') ?? 0);
                if (Schema::hasTable('deleted_tasks')) {
                    $max = max($max, (int) (DB::table('deleted_tasks')->max('id') ?? 0));
                }
                $task->incrementing = false;
                $task->id = $max + 1;
            }
            $task->save();

            return $task;
        });
    }

    private static function tasksIdAutoIncrements(): bool
    {
        try {
            $col = DB::selectOne("SHOW COLUMNS FROM `tasks` WHERE Field = 'id'");

            return str_contains(strtolower((string) ($col->Extra ?? '')), 'auto_increment');
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     */
    private static function resolveAssignee(AutoChatEvent $ace, array $row, array $ctx): ?User
    {
        return match ($ace->assignee_mode) {
            'row_user' => isset($row['user_id']) ? User::query()->find((int) $row['user_id']) : null,
            'chat_sender' => isset($ctx['sender_id']) ? User::query()->find((int) $ctx['sender_id']) : null,
            default => $ace->assignee_id ? User::query()->find($ace->assignee_id) : null,
        };
    }

    /**
     * Replace {placeholders} with row/chat values; unknown placeholders are left empty.
     *
     * @param  array<string, string>  $vars
     */
    public static function render(string $template, array $vars): string
    {
        return trim((string) preg_replace_callback('/\{([a-z0-9_]+)\}/i', fn ($m) => $vars[$m[1]] ?? '', $template));
    }
}
