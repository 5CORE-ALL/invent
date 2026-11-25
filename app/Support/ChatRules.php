<?php

namespace App\Support;

use App\Http\Controllers\TaskController;
use App\Models\ChatMessage;
use App\Models\ChatRule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Chat Rules: "column  operator  number" conditions on the /tasks/summary numbers.
 * A member who matches gets the rule's message from the 5 Core Bot (their bot inbox).
 */
class ChatRules
{
    /** @var array<string, string> */
    public const OPERATORS = [
        '>=' => 'is at least',
        '>' => 'is more than',
        '<=' => 'is at most',
        '<' => 'is less than',
        '=' => 'is exactly',
        '!=' => 'is not',
    ];

    /**
     * The columns of the Task Summary table a rule can look at.
     * key = the field in the Task Summary row; value is shown to the director.
     *
     * @return array<string, array{label: string, group: string, hint: string, default_message: string}>
     */
    public static function metrics(): array
    {
        return [
            'dar_today' => [
                'label' => 'DAR filed today (1 = yes, 0 = no)',
                'group' => 'DAR',
                'hint' => 'Use "is exactly 0" to message people who have not filed today\'s DAR.',
                'default_message' => "Hi {first_name}, you have not filed today's DAR yet. Please fill it from the top bar.",
            ],
            'dar_l30_pct' => [
                'label' => 'DAR % (last 30 days)',
                'group' => 'DAR',
                'hint' => 'Unique daily reports in the last 30 days as a % of the 25-day target.',
                'default_message' => 'Hi {first_name}, your DAR is at {value}% for the last 30 days (target {threshold}%). Please keep your reports up to date.',
            ],
            'overdue' => [
                'label' => 'Overdue tasks',
                'group' => 'Tasks',
                'hint' => 'The Overdue column.',
                'default_message' => 'Hi {first_name}, you have {value} overdue tasks. Please close or reschedule them today.',
            ],
            'soi_count' => [
                'label' => 'Scope of Improvement (SI) count',
                'group' => 'SI',
                'hint' => 'The SI column.',
                'default_message' => 'Hi {first_name}, you have {value} Scope of Improvement items. Please work on them.',
            ],
            'task' => [
                'label' => 'Open tasks assigned',
                'group' => 'Tasks',
                'hint' => 'The Task column.',
                'default_message' => 'Hi {first_name}, you have {value} tasks assigned to you.',
            ],
            'done' => [
                'label' => 'Done tasks',
                'group' => 'Tasks',
                'hint' => 'The Done column.',
                'default_message' => 'Hi {first_name}, you have completed {value} tasks. Keep it up.',
            ],
            'y_done' => [
                'label' => 'Tasks completed yesterday (Y Done)',
                'group' => 'Tasks',
                'hint' => 'The Y Done column.',
                'default_message' => 'Hi {first_name}, you completed {value} tasks yesterday.',
            ],
            'assignor_task' => [
                'label' => 'Tasks given to others',
                'group' => 'Tasks',
                'hint' => 'The Task Given column.',
                'default_message' => 'Hi {first_name}, you have given {value} tasks to others.',
            ],
            'a_task_h' => [
                'label' => 'Automated task hours',
                'group' => 'Tasks',
                'hint' => 'Automated task ETC hours.',
                'default_message' => 'Hi {first_name}, your automated tasks add up to {value} hours.',
            ],
            'missed_l30' => [
                'label' => 'Missed tasks (last 30 days)',
                'group' => 'Tasks',
                'hint' => 'Missed in the last 30 days.',
                'default_message' => 'Hi {first_name}, you missed {value} tasks in the last 30 days.',
            ],
            'missed_p30' => [
                'label' => 'Missed tasks (days 31-60)',
                'group' => 'Tasks',
                'hint' => 'The Miss column.',
                'default_message' => 'Hi {first_name}, you missed {value} tasks in days 31-60.',
            ],
            'tat_l30_days' => [
                'label' => 'TAT, average days to finish (last 30 days)',
                'group' => 'Time',
                'hint' => 'The TAT column.',
                'default_message' => 'Hi {first_name}, your average turn-around time is {value} days.',
            ],
            'etc_pending_h' => [
                'label' => 'ETC pending hours',
                'group' => 'Time',
                'hint' => 'The ETC P column.',
                'default_message' => 'Hi {first_name}, you have {value} hours of pending work.',
            ],
            'etc_l30_h' => [
                'label' => 'ETC hours closed (last 30 days)',
                'group' => 'Time',
                'hint' => 'The ETC column.',
                'default_message' => 'Hi {first_name}, you closed {value} estimated hours in the last 30 days.',
            ],
            'atc_l30_h' => [
                'label' => 'ATC actual hours (last 30 days)',
                'group' => 'Time',
                'hint' => 'The ATC column.',
                'default_message' => 'Hi {first_name}, you logged {value} actual hours on closed work in the last 30 days.',
            ],
            'att_l30_pct' => [
                'label' => 'Attendance % (last 30 days)',
                'group' => 'Attendance',
                'hint' => 'The Attendance column.',
                'default_message' => 'Hi {first_name}, your attendance is at {value}% for the last 30 days.',
            ],
            'y_time_hours' => [
                'label' => 'Active hours yesterday (Y Time)',
                'group' => 'Attendance',
                'hint' => 'The Y Time column, in hours.',
                'default_message' => 'Hi {first_name}, you were active for {value} hours yesterday.',
            ],
            'ann_unseen' => [
                'label' => 'Unseen announcements',
                'group' => 'Chat',
                'hint' => 'The Ann column.',
                'default_message' => 'Hi {first_name}, you have {value} announcements you have not seen.',
            ],
            'chat_red' => [
                'label' => 'Chats with unread messages (red dot)',
                'group' => 'Chat',
                'hint' => 'The red dot column.',
                'default_message' => 'Hi {first_name}, you have {value} chats with unread messages.',
            ],
            'chat_yellow' => [
                'label' => 'Chats read, no unread (yellow dot)',
                'group' => 'Chat',
                'hint' => 'The yellow dot column.',
                'default_message' => 'Hi {first_name}, you have {value} chats waiting.',
            ],
        ];
    }

    public static function tablesReady(): bool
    {
        try {
            return Schema::hasTable('chat_rules') && ChatWorkspace::tablesReady();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Clean and validate what the director typed. Returns [fields, error].
     *
     * @param  array<string, mixed>  $input
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    public static function sanitize(array $input): array
    {
        $metrics = self::metrics();
        $name = trim((string) ($input['name'] ?? ''));
        $metric = (string) ($input['metric'] ?? '');
        $operator = (string) ($input['operator'] ?? '');
        $threshold = $input['threshold'] ?? null;
        $message = trim((string) ($input['message'] ?? ''));
        $sendTime = trim((string) ($input['send_time'] ?? '10:00'));
        $audience = ($input['audience'] ?? 'all') === 'users' ? 'users' : 'all';
        $frequency = ($input['frequency'] ?? 'daily') === 'weekly' ? 'weekly' : 'daily';

        if ($name === '') {
            return [null, 'Give the rule a name.'];
        }
        if (! isset($metrics[$metric])) {
            return [null, 'Pick a column for the condition.'];
        }
        if (! isset(self::OPERATORS[$operator])) {
            return [null, 'Pick how to compare the number.'];
        }
        if (! is_numeric($threshold)) {
            return [null, 'Enter the number to compare with.'];
        }
        if ($message === '') {
            return [null, 'Write the message the bot should send.'];
        }
        if (mb_strlen($message) > 1500) {
            return [null, 'The message is too long (1500 characters at most).'];
        }
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sendTime) !== 1) {
            return [null, 'Pick a valid send time.'];
        }

        $userIds = [];
        if ($audience === 'users') {
            $userIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['user_ids'] ?? [])))));
            if ($userIds === []) {
                return [null, 'Pick at least one member, or choose All members.'];
            }
        }

        return [[
            'name' => mb_substr($name, 0, 120),
            'is_active' => ! array_key_exists('is_active', $input) || filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN),
            'metric' => $metric,
            'operator' => $operator,
            'threshold' => round((float) $threshold, 2),
            'audience' => $audience,
            'user_ids' => $audience === 'users' ? $userIds : null,
            'message' => $message,
            'send_time' => $sendTime,
            'weekdays_only' => ! array_key_exists('weekdays_only', $input) || filter_var($input['weekdays_only'], FILTER_VALIDATE_BOOLEAN),
            'frequency' => $frequency,
        ], null];
    }

    /**
     * The Task Summary rows (one per team member) with the extra numbers rules can use.
     * Built the way an admin sees the page, so every task is counted whoever runs it.
     *
     * @return array<int, array<string, mixed>> user id => row
     */
    public static function memberRows(): array
    {
        $previous = Auth::user();
        $admin = self::adminUser();
        if ($admin) {
            Auth::setUser($admin);
        }

        try {
            $controller = app(TaskController::class);
            $method = new \ReflectionMethod($controller, 'getTaskSummaryMemberRows');
            $method->setAccessible(true);
            $rows = (array) $method->invoke($controller);
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } elseif (method_exists(Auth::guard(), 'forgetUser')) {
                Auth::guard()->forgetUser();
            }
        }

        $byUser = [];
        foreach ($rows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id > 0) {
                $byUser[$id] = $row;
            }
        }

        return self::withExtraNumbers($byUser);
    }

    protected static function adminUser(): ?User
    {
        $admin = User::query()->where('role', 'admin')->where('is_active', true)->orderBy('id')->first();
        if ($admin) {
            return $admin;
        }
        $emails = array_map('strtolower', (array) config('super_admin.emails', []));

        if ($emails === []) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) IN ('.implode(',', array_fill(0, count($emails), '?')).')', $emails)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $byUser
     * @return array<int, array<string, mixed>>
     */
    protected static function withExtraNumbers(array $byUser): array
    {
        $filedToday = [];
        try {
            if ($byUser !== [] && Schema::hasTable('dars')) {
                $filedToday = \App\Models\Dar::query()
                    ->whereIn('user_id', array_keys($byUser))
                    ->whereDate('report_date', TaskBusinessTime::today()->toDateString())
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->flip()
                    ->all();
            }
        } catch (\Throwable) {
            $filedToday = [];
        }

        foreach ($byUser as $id => &$row) {
            $row['dar_today'] = isset($filedToday[$id]) ? 1 : 0;
            $row['y_time_hours'] = round(((int) ($row['y_time_seconds'] ?? 0)) / 3600, 1);
        }
        unset($row);

        return $byUser;
    }

    public static function compare(float $value, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>=' => $value >= $threshold,
            '>' => $value > $threshold,
            '<=' => $value <= $threshold,
            '<' => $value < $threshold,
            '=' => abs($value - $threshold) < 0.005,
            '!=' => abs($value - $threshold) >= 0.005,
            default => false,
        };
    }

    /**
     * Who matches a rule right now.
     *
     * @param  array<int, array<string, mixed>>  $rows  from memberRows()
     * @return list<array{user_id: int, name: string, email: string, value: float}>
     */
    public static function matches(ChatRule $rule, array $rows): array
    {
        $out = [];
        $only = $rule->audience === 'users' ? array_flip(array_map('intval', (array) $rule->user_ids)) : null;

        foreach ($rows as $id => $row) {
            if ($only !== null && ! isset($only[(int) $id])) {
                continue;
            }
            $raw = $row[$rule->metric] ?? null;
            if ($raw === null || ! is_numeric($raw)) {
                continue; // no number for this person (for example no TAT yet): the rule does not apply
            }
            $value = (float) $raw;
            if (! self::compare($value, (string) $rule->operator, (float) $rule->threshold)) {
                continue;
            }
            $out[] = [
                'user_id' => (int) $id,
                'name' => (string) ($row['team_member'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'value' => $value,
            ];
        }

        usort($out, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    public static function formatNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') ?: '0';
    }

    public static function render(ChatRule $rule, string $name, float $value): string
    {
        $metrics = self::metrics();
        $first = trim(explode(' ', trim($name))[0] ?? '') ?: $name;

        return strtr($rule->message, [
            '{name}' => $name,
            '{first_name}' => $first,
            '{value}' => self::formatNumber($value),
            '{threshold}' => self::formatNumber((float) $rule->threshold),
            '{metric}' => $metrics[$rule->metric]['label'] ?? $rule->metric,
            '{rule}' => $rule->name,
        ]);
    }

    /**
     * Run a rule: message everyone who matches and has not had it already in this period.
     * $dryRun only reports who would get it.
     *
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array{matched: int, sent: int, already: int, people: list<array<string, mixed>>}
     */
    public static function run(ChatRule $rule, bool $dryRun = false, ?array $rows = null): array
    {
        $rows ??= self::memberRows();
        $matches = self::matches($rule, $rows);
        $users = $matches === []
            ? collect()
            : User::query()->whereIn('id', array_column($matches, 'user_id'))->get()->keyBy('id');

        $since = $rule->frequency === 'weekly'
            ? TaskBusinessTime::now()->subDays(7)->timezone(config('app.timezone'))->toDateTimeString()
            : TaskBusinessTime::todayStart()->timezone(config('app.timezone'))->toDateTimeString();

        $sent = 0;
        $already = 0;
        $people = [];

        foreach ($matches as $m) {
            $user = $users->get($m['user_id']);
            if (! $user || InventChatBot::isExemptFromDailyNudges($user)) {
                continue;
            }
            $message = self::render($rule, $m['name'], $m['value']);
            $status = 'would_send';

            try {
                $inbox = ChatWorkspace::botInboxFor($user);
                $had = ChatMessage::query()
                    ->where('channel_id', $inbox->id)
                    ->where('command', $rule->botCommand())
                    ->where('created_at', '>=', $since)
                    ->exists();
                if ($had) {
                    $already++;
                    $status = 'already_sent';
                } elseif (! $dryRun) {
                    InventChatBot::postBot($inbox, $message, $rule->botCommand());
                    ChatWorkspace::forgetUnreadCache((int) $user->id);
                    $sent++;
                    $status = 'sent';
                }
            } catch (\Throwable $e) {
                report($e);
                $status = 'failed';
            }

            $people[] = [
                'name' => $m['name'],
                'value' => self::formatNumber($m['value']),
                'status' => $status,
                'message' => $message,
            ];
        }

        if (! $dryRun) {
            $rule->forceFill(['last_run_at' => now(), 'last_sent_count' => $sent])->save();
        }

        return ['matched' => count($matches), 'sent' => $sent, 'already' => $already, 'people' => $people];
    }
}
