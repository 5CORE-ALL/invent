<?php

namespace App\Support\Ace;

use App\Models\AutoChatEvent;
use App\Models\AutoChatEventRun;
use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use App\Support\AutomatedTaskSchedule;
use App\Support\TaskBusinessTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ATE (Auto Task Event) engine.
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
     * The rule's column conditions: the `conditions` list when set, else the legacy single
     * column_key / operator / value.
     *
     * @return list<array{column_key: string, operator: string, value: string|null}>
     */
    public static function conditionsOf(AutoChatEvent $ace): array
    {
        $list = is_array($ace->conditions) ? $ace->conditions : [];
        $list = array_values(array_filter($list, fn ($c) => is_array($c) && ! empty($c['column_key']) && ! empty($c['operator'])));
        if ($list !== []) {
            return array_map(fn ($c) => [
                'column_key' => (string) $c['column_key'],
                'operator' => (string) $c['operator'],
                'value' => isset($c['value']) ? (string) $c['value'] : null,
                'label' => isset($c['label']) && $c['label'] !== '' ? (string) $c['label'] : null,
            ], $list);
        }
        if ($ace->column_key && $ace->operator) {
            return [['column_key' => (string) $ace->column_key, 'operator' => (string) $ace->operator, 'value' => $ace->value]];
        }

        return [];
    }

    /**
     * Does a row satisfy all (AND) or any (OR) of the conditions? No conditions = every row.
     *
     * @param  array<string, mixed>  $row
     * @param  list<array{column_key: string, operator: string, value: string|null}>  $conditions
     */
    public static function rowMatches(array $row, array $conditions, string $mode = 'all'): bool
    {
        if ($conditions === []) {
            return true;
        }
        foreach ($conditions as $c) {
            $hit = self::matches($row, $c['column_key'], $c['operator'], $c['value'] ?? null);
            if ($mode === 'any' && $hit) {
                return true;
            }
            if ($mode !== 'any' && ! $hit) {
                return false;
            }
        }

        return $mode !== 'any';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function matchingRows(AutoChatEvent $ace): array
    {
        $conditions = self::conditionsOf($ace);
        $mode = $ace->match_mode === 'any' ? 'any' : 'all';

        $rows = AcePages::rows((string) $ace->page, $ace->source_url ?: null, $ace->created_by ? (int) $ace->created_by : null);

        return array_values(array_filter($rows, fn ($r) => self::rowMatches($r, $conditions, $mode)));
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
            // Rules on analytics pages read a (large) page data feed: check them at most hourly,
            // unless the last run hit the per-run cap and more rows are waiting.
            $feed = ! empty($ace->source_url);
            $gate = 'ate:checked:'.$ace->id;
            if ($feed && ! $aceId && Cache::has($gate)) {
                continue;
            }
            try {
                $n = self::fireForRows($ace, self::matchingRows($ace), 'column', null);
                $created += $n;
                if ($feed && $n < self::MAX_PER_RUN) {
                    Cache::put($gate, 1, now()->addMinutes(55));
                }
            } catch (\Throwable $e) {
                Log::warning('ACE #'.$ace->id.' column run failed: '.$e->getMessage());
                if ($feed) {
                    Cache::put($gate, 1, now()->addMinutes(10));
                }
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

    public const FREQUENCIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    /** @return list<string> the checked frequencies (valid ones only); empty = legacy once-per-row */
    public static function frequenciesOf(AutoChatEvent $ace): array
    {
        $f = is_array($ace->frequencies ?? null) ? $ace->frequencies : [];

        return array_values(array_filter(array_keys(self::FREQUENCIES), fn ($k) => in_array($k, $f, true)));
    }

    /** @return list<string> subject-key suffixes for the current day / ISO week / month */
    private static function periodSuffixes(AutoChatEvent $ace): array
    {
        $now = now();
        $map = ['daily' => 'd:'.$now->format('Y-m-d'), 'weekly' => 'w:'.$now->format('o-\\WW'), 'monthly' => 'm:'.$now->format('Y-m')];

        return array_map(fn ($f) => $map[$f], self::frequenciesOf($ace));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $ctx  chat context (message, sender, channel...)
     */
    private static function fireForRows(AutoChatEvent $ace, array $rows, string $source, ?array $ctx): int
    {
        if ($rows === []) {
            return 0;
        }

        // Periods this rule repeats on. Empty = legacy rule: one task per row, ever.
        $periods = self::periodSuffixes($ace);
        $keysFor = fn ($r) => $periods === []
            ? ['row:'.AcePages::rowId($r)]
            : array_map(fn ($p) => 'row:'.AcePages::rowId($r).':'.$p, $periods);

        $allKeys = [];
        foreach ($rows as $r) {
            $allKeys = array_merge($allKeys, $keysFor($r));
        }
        $done = [];
        foreach (array_chunk($allKeys, 500) as $chunk) {
            $done += AutoChatEventRun::query()->where('ace_id', $ace->id)
                ->whereIn('subject_key', $chunk)->pluck('subject_key')->flip()->all();
        }

        $created = 0;
        foreach ($rows as $row) {
            if ($created >= self::MAX_PER_RUN) {
                break;
            }
            // Keys whose day / week / month has no task yet for this row.
            $due = array_values(array_filter($keysFor($row), fn ($k) => ! isset($done[$k])));
            if ($due === []) {
                continue;
            }
            $label = AcePages::rowLabel($row) ?: $due[0];
            if (self::fireOne($ace, $due[0], $label, $row, $source, $ctx)) {
                $created++;
                // One task covers every due period (e.g. daily + monthly on the 1st).
                foreach (array_slice($due, 1) as $extraKey) {
                    try {
                        AutoChatEventRun::query()->create([
                            'ace_id' => $ace->id, 'subject_key' => $extraKey, 'subject_label' => mb_substr($label, 0, 255),
                            'source' => $source, 'status' => 'skipped',
                        ]);
                    } catch (\Throwable $e) {
                        // already claimed
                    }
                }
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
            $tasks = self::createTasks($ace, $row ?? [], $ctx ?? [], $label);
            // task_id = first task created (null when only recurring templates were scheduled).
            $run->forceFill(['task_id' => $tasks[0]->id ?? null])->save();
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
     * Build the task(s) from the rule's template: one per title (x one per assignee when "split"),
     * plus recurring templates (automate_tasks) for the Daily / Weekly / Monthly frequencies.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     * @return list<Task>
     */
    private static function createTasks(AutoChatEvent $ace, array $row, array $ctx, string $label): array
    {
        // {placeholders}: every scalar column, plus a [a-z0-9_] alias for names like "(Child) sku" -> {Child_sku}.
        $cols = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $row);
        foreach ($cols as $k => $v) {
            $alias = trim((string) preg_replace('/[^a-z0-9_]+/i', '_', (string) $k), '_');
            if ($alias !== '' && ! array_key_exists($alias, $cols)) {
                $cols[$alias] = $v;
            }
        }
        $vars = array_merge(
            $cols,
            [
                'row' => $label,
                'message' => (string) ($ctx['message'] ?? ''),
                'channel' => (string) ($ctx['channel'] ?? ''),
                'sender' => (string) ($ctx['sender'] ?? ''),
                'date' => now()->toDateString(),
            ]
        );
        $opt = is_array($ace->task_options ?? null) ? $ace->task_options : [];

        $creator = $ace->created_by ? User::query()->find($ace->created_by) : null;
        $assignees = self::resolveAssignees($ace, $row, $ctx, $opt);
        if ($assignees === [] && $creator) {
            $assignees = [$creator];
        }
        if ($assignees === []) {
            throw new \RuntimeException('No assignee could be resolved for this rule.');
        }
        $assignor = (! empty($opt['assignor_id']) ? User::query()->find((int) $opt['assignor_id']) : null) ?? $creator ?? $assignees[0];

        $r = fn (string $key) => mb_substr(self::render((string) ($opt[$key] ?? ''), $vars), 0, 2048);
        $link1 = mb_substr(self::render((string) ($ace->task_link ?? ''), $vars), 0, 2048);

        $titles = [];
        foreach ((array) ($opt['titles'] ?? []) as $t) {
            $t = mb_substr(self::render((string) $t, $vars), 0, 1000);
            if ($t !== '') {
                $titles[] = $t;
            }
        }
        if ($titles === []) {
            $titles = [mb_substr(self::render((string) $ace->task_title, $vars), 0, 1000)];
        }

        $freqs = array_values(array_unique(array_map('strval', (array) ($opt['task_frequency'] ?? ['one_time']))));
        $recurring = array_values(array_intersect(['daily', 'weekly', 'monthly'], $freqs));
        $includeOneTime = in_array('one_time', $freqs, true) || $recurring === [];
        $split = ! empty($opt['split']) && count($assignees) > 1;

        $due = now()->addDays((int) $ace->due_in_days);
        $priority = in_array($ace->priority, ['low', 'normal', 'high'], true) ? $ace->priority : 'normal';
        $links = [
            'link1' => $link1, 'link2' => $r('l2'), 'link3' => $r('training_link'), 'link4' => $r('video_link'),
            'link5' => $r('form_link'), 'link6' => $r('form_report_link'), 'link7' => $r('checklist_link'),
            'link8' => $r('pl'), 'link9' => $r('process'),
        ];
        $description = self::render((string) $ace->task_description, $vars) ?: null;
        $etc = max(0, (int) ($opt['etc_minutes'] ?? 10));

        $base = $links + [
            'description' => $description,
            'group' => $ace->task_group ?: null,
            'priority' => $priority,
            'assignor' => $assignor->email,
            'split_tasks' => 0,
            'parent_task_id' => null,
            'subtask_order' => 0,
            'status' => 'Todo',
            'eta_time' => $etc ?: 10,
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
            'image' => null,
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual', // a one-time task
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
        ];
        if (Schema::hasColumn('tasks', 'screenshots')) {
            $base['screenshots'] = [];
        }
        if (Schema::hasColumn('tasks', 'is_corrective_action')) {
            $base['is_corrective_action'] = ! empty($opt['is_corrective_action']) ? 1 : 0;
        }

        $joinedEmails = implode(', ', array_map(fn ($u) => $u->email, $assignees));
        $created = [];

        if ($includeOneTime) {
            foreach ($titles as $title) {
                if ($split) {
                    foreach ($assignees as $person) {
                        $created[] = self::saveTask(array_merge($base, ['title' => $title, 'assign_to' => $person->email, 'split_tasks' => 1]));
                    }
                } else {
                    $created[] = self::saveTask(array_merge($base, ['title' => $title, 'assign_to' => $joinedEmails]));
                }
            }
        }

        if ($recurring !== [] && Schema::hasTable('automate_tasks')) {
            foreach ($recurring as $freq) {
                self::scheduleRecurring($freq, $titles, $assignees, $split, $joinedEmails, $assignor->email, $base, $links, $description, $etc ?: 10, $priority, $ace->task_group ?: null);
            }
        }

        return $created;
    }

    /** @param  array<string, mixed>  $data */
    private static function saveTask(array $data): Task
    {
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

    /**
     * Daily / Weekly / Monthly: same automate_tasks templates the task manager creates. Skipped when an
     * identical template (title + assignee + schedule + group) already exists, so a rule that fires
     * every period never piles up duplicate templates.
     *
     * @param  list<string>  $titles
     * @param  list<User>  $assignees
     * @param  array<string, mixed>  $base
     * @param  array<string, string>  $links
     */
    private static function scheduleRecurring(string $freq, array $titles, array $assignees, bool $split, string $joinedEmails, string $assignorEmail, array $base, array $links, ?string $description, int $etc, string $priority, ?string $group): void
    {
        $tpl = $links + [
            'group' => $group,
            'priority' => ucfirst($priority),
            'description' => $description ?? '',
            'assignor' => $assignorEmail,
            'eta_time' => $etc,
            'start_date' => now(),
            'due_date' => now(),
            'schedule_type' => $freq,
            'schedule_time' => $freq === 'daily' ? TaskBusinessTime::dailyGenerateTime() : '12:00',
            'schedule_days' => $freq === 'weekly' ? AutomatedTaskSchedule::resolveWeeklyDays(null) : ($freq === 'monthly' ? '1' : ''),
            'status' => 'Todo',
            'workspace' => 0,
            'order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $targets = $split ? array_map(fn ($u) => [$u->email, 1], $assignees) : [[$joinedEmails, 0]];
        foreach ($titles as $title) {
            foreach ($targets as [$email, $isSplit]) {
                $exists = DB::table('automate_tasks')->where('title', $title)->where('assign_to', $email)->where('schedule_type', $freq)
                    ->when($group, fn ($q) => $q->where('group', $group), fn ($q) => $q->whereNull('group'))->exists();
                if (! $exists) {
                    DB::table('automate_tasks')->insert($tpl + ['title' => $title, 'assign_to' => $email, 'split_tasks' => $isSplit]);
                }
            }
        }
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
     * @param  array<string, mixed>  $opt
     * @return list<User>
     */
    private static function resolveAssignees(AutoChatEvent $ace, array $row, array $ctx, array $opt): array
    {
        $one = fn (?User $u) => $u ? [$u] : [];

        return match ($ace->assignee_mode) {
            'row_user' => $one(isset($row['user_id']) ? User::query()->find((int) $row['user_id']) : null),
            'chat_sender' => $one(isset($ctx['sender_id']) ? User::query()->find((int) $ctx['sender_id']) : null),
            default => (function () use ($ace, $opt) {
                $ids = array_values(array_filter(array_map('intval', (array) ($opt['assignee_ids'] ?? []))));
                if ($ids === [] && $ace->assignee_id) {
                    $ids = [(int) $ace->assignee_id];
                }

                return $ids === [] ? [] : User::query()->whereIn('id', $ids)->get()->all();
            })(),
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
