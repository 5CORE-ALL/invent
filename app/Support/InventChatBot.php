<?php

namespace App\Support;

use App\Models\ChatChannel;
use App\Models\ChatMessage;
use App\Models\ScopeOfImprovement;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InventChatBot
{
    public static function welcomeText(): string
    {
        return "Hi, I'm 5 Core Bot.\n"
            .'I can create a task or check overdue, DAR, and SI. Tap a button or just tell me what you need.';
    }

    public static function helpText(): string
    {
        return "Here's what I can do:\n"
            ."• Create a task Buy packing tape @aman tomorrow\n"
            ."• Complete task 123\n"
            ."• Assign task 123 @aman\n"
            ."• Change deadline 123 tomorrow\n"
            ."• Show my overdue / Show @aman overdue\n"
            ."• Show today's DAR / Show my DAR\n"
            ."• SI count\n";
    }

    public static function looksLikeCommand(string $body): bool
    {
        $trim = ltrim($body);
        $lower = strtolower($trim);

        return Str::startsWith($trim, '/')
            || Str::startsWith($lower, '@invent')
            || (bool) preg_match('/\b(overdue|dar|daily activity|scope of improvement|si|task|help|complete|assign|deadline|due)\b/i', $trim);
    }

    public static function reply(User $user, ChatChannel $channel, string $body, bool $force = false): ?ChatMessage
    {
        if (! $force && ! self::looksLikeCommand($body) && ! $channel->isBotInbox()) {
            return null;
        }

        $parsed = self::parse($body);
        $command = $parsed['command'];
        $args = $parsed['args'];

        $text = match ($command) {
            'help', 'invent', '' => self::helpText(),
            'task' => self::handleTask($user, $args),
            'complete' => self::handleComplete($user, $args),
            'assign' => self::handleAssign($user, $args),
            'deadline', 'due' => self::handleDeadline($user, $args),
            'overdue' => self::handleOverdue($user, $args),
            'dar' => self::handleDar($user, $args),
            'si' => self::handleSi($user, $args),
            default => "I can create, complete, assign, or reschedule a task, and check overdue, DAR, and SI.",
        };

        return ChatMessage::query()->create([
            'channel_id' => $channel->id,
            'user_id' => null,
            'is_bot' => true,
            'bot_name' => ChatWorkspace::BOT_NAME,
            'body' => $text,
            'command' => $command !== '' ? $command : 'help',
        ]);
    }

    /**
     * @return array{command: string, args: string}
     */
    public static function parse(string $body): array
    {
        $trim = trim($body);
        $trim = preg_replace('/^@invent\b[,:]?\s*/i', '', $trim) ?? $trim;
        $trim = trim($trim);

        if ($trim === '' || $trim === '@invent') {
            return ['command' => 'help', 'args' => ''];
        }

        if (! str_starts_with($trim, '/')) {
            return self::parseNatural($trim);
        }

        if (! preg_match('/^\/([A-Za-z]+)(?:\s+([\s\S]+))?$/', $trim, $m)) {
            return ['command' => 'help', 'args' => ''];
        }

        return [
            'command' => strtolower($m[1]),
            'args' => trim((string) ($m[2] ?? '')),
        ];
    }

    /**
     * @return array{command: string, args: string}
     */
    private static function parseNatural(string $trim): array
    {
        $lower = strtolower($trim);

        if (preg_match('/\b(complete|done|close)\b.*\btasks?\b|\bcomplete task\b/i', $trim)) {
            return ['command' => 'complete', 'args' => $trim];
        }
        if (preg_match('/\bassign\b.*\btasks?\b/i', $trim)) {
            return ['command' => 'assign', 'args' => $trim];
        }
        if (preg_match('/\b(deadline|due date|change deadline)\b/i', $trim)) {
            return ['command' => 'deadline', 'args' => $trim];
        }
        if (preg_match('/\b(overdue|overdues)\b/', $lower)) {
            return ['command' => 'overdue', 'args' => $trim];
        }
        if (preg_match('/\b(dar|daily activity)\b/', $lower)) {
            return ['command' => 'dar', 'args' => $trim];
        }
        if (preg_match('/\b(scope of improvement|si)\b/', $lower)) {
            return ['command' => 'si', 'args' => $trim];
        }
        if (preg_match('/\b(create|add|make)\b.*\btasks?\b/i', $trim) || preg_match('/^task\b/i', $trim)) {
            $args = trim((string) preg_replace('/^(\/)?((please|pls)\s+)?(create|add|make)\s*(a\s+|an\s+)?tasks?\b[:\s-]*/i', '', $trim));

            return ['command' => 'task', 'args' => $args];
        }
        if ($lower === 'help' || $lower === 'hi' || $lower === 'hello' || $lower === 'hey') {
            return ['command' => 'help', 'args' => ''];
        }

        return ['command' => 'unknown', 'args' => $trim];
    }

    public static function handleTask(User $user, string $args): string
    {
        $args = trim($args);
        if ($args === '') {
            return 'What should the task say? Example: Create a task Buy packing tape @aman';
        }

        $priority = 'normal';
        if (preg_match('/\b(low|normal|high)\s*$/i', $args, $m)) {
            $priority = strtolower($m[1]);
            $args = trim(preg_replace('/\b(low|normal|high)\s*$/i', '', $args) ?? $args);
        }

        $mentions = ChatWorkspace::resolveMentions($args);
        $title = trim((string) preg_replace('/@([A-Za-z0-9._-]+)/', '', $args));
        $title = preg_replace('/\s+/', ' ', $title) ?? $title;
        $title = trim($title);

        if ($title === '') {
            return 'What should the task say? Example: Create a task Buy packing tape @aman';
        }

        $assignees = $mentions->isNotEmpty() ? $mentions : collect([$user]);
        $emails = $assignees->pluck('email')->filter()->map(fn ($e) => trim((string) $e))->unique()->values()->all();
        if ($emails === []) {
            return 'Could not resolve an assignee. Use @firstname or @emaillocal.';
        }

        $startDate = TaskBusinessTime::now();
        $completionDate = self::parseWhen($args) ?: $startDate->copy()->addDays(5);
        $title = trim((string) preg_replace('/\b(today|tomorrow|tonight)\b/i', '', $title));
        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        $taskData = [
            'title' => Str::limit($title, 1000, ''),
            'description' => 'Created from Invent Chat by '.$user->name,
            'priority' => $priority,
            'assignor' => $user->email,
            'assign_to' => implode(', ', $emails),
            'status' => 'Todo',
            'eta_time' => 10,
            'start_date' => $startDate,
            'completion_date' => $completionDate,
            'due_date' => $completionDate,
            'completion_day' => 0,
            'etc_done' => 0,
            'is_missed' => 0,
            'is_missed_track' => 0,
            'workspace' => 0,
            'order' => 0,
            'task_id' => '',
            'is_data_from' => 0,
            'is_automate_task' => 0,
            'task_type' => 'manual',
            'rework_reason' => '',
            'delete_rating' => 0,
            'delete_feedback' => '',
        ];

        try {
            $task = new Task($taskData);
            $nextId = self::nextIdIfNoAutoincrement('tasks');
            if ($nextId) {
                $task->incrementing = false;
                $task->id = $nextId;
            }
            $task->save();
        } catch (\Throwable $e) {
            return 'Could not create the task. '.$e->getMessage();
        }

        $taskId = $nextId ?: (int) $task->id;
        $names = $assignees->pluck('name')->implode(', ');
        $url = url('/tasks');

        return 'Created task #'.$taskId.' — '.$task->title."\n"
            .'Assigned to '.$names.' · '.$priority.' · due '.$completionDate->format('M j')."\n"
            .'Open Tasks: '.$url;
    }

    public static function handleOverdue(User $viewer, string $args): string
    {
        $target = self::resolveTarget($viewer, $args);
        if (is_string($target)) {
            return $target;
        }

        $count = UserOverdueNudge::countForUser($target);
        $url = url('/tasks');
        $who = $target->id === $viewer->id ? 'You have' : $target->name.' has';

        if ($count < 1) {
            return $who.' no overdue tasks. Keep it that way.';
        }

        $msg = UserOverdueNudge::messages()[array_rand(UserOverdueNudge::messages())];

        return $who.' '.$count.' overdue task'.($count === 1 ? '' : 's').".\n"
            .$msg."\n"
            .'Open Tasks: '.$url;
    }

    public static function handleDar(User $viewer, string $args): string
    {
        $target = self::resolveTarget($viewer, $args);
        if (is_string($target)) {
            return $target;
        }

        $metrics = DarL30Metrics::forUserId((int) $target->id);
        $pct = (int) ($metrics['dar_l30_pct'] ?? 0);
        $count = (int) ($metrics['dar_l30_count'] ?? 0);
        $goal = (int) ($metrics['dar_l30_target'] ?? DarL30Metrics::TARGET);
        $who = $target->id === $viewer->id ? 'Your' : $target->name."'s";
        $hint = $pct > 90
            ? 'Above 90% — keep filing every weekday.'
            : UserDarNudge::logoutMessages()[array_rand(UserDarNudge::logoutMessages())];

        return $who.' DAR is '.$pct.'% ('.$count.'/'.$goal.' last 30 days).'."\n"
            .$hint."\n"
            .'Fill DAR from the top bar, or open '.url('/dar');
    }

    public static function handleSi(User $viewer, string $args): string
    {
        $target = self::resolveTarget($viewer, $args);
        if (is_string($target)) {
            return $target;
        }

        $count = 0;
        if (Schema::hasTable('scope_of_improvements')) {
            $count = (int) ScopeOfImprovement::query()->where('user_id', $target->id)->count();
        }

        $who = $target->id === $viewer->id ? 'You have' : $target->name.' has';
        $noun = $count === 1 ? 'Scope of Improvement' : 'Scope of Improvement items';

        return $who.' '.$count.' '.$noun.".\n"
            .'Open SI from the SI button on Task Summary or the top bar.';
    }

    public static function handleComplete(User $viewer, string $args): string
    {
        $task = self::findTaskFromArgs($viewer, $args);
        if (is_string($task)) {
            return $task;
        }

        $task->status = 'Done';
        $task->save();

        return 'Marked task #'.$task->id.' as Done — '.$task->title."\n"
            .'Open: '.url('/tasks?highlight='.$task->id);
    }

    public static function handleAssign(User $viewer, string $args): string
    {
        $task = self::findTaskFromArgs($viewer, $args);
        if (is_string($task)) {
            return $task;
        }

        $people = ChatWorkspace::resolveMentions($args);
        if ($people->isEmpty()) {
            return 'Assign who? Example: Assign task '.$task->id.' @aman';
        }
        if (! ChatWorkspace::canInspectOther($viewer) && $people->every(fn (User $u) => (int) $u->id !== (int) $viewer->id)) {
            return 'You can only reassign to yourself unless you manage tasks.';
        }

        $task->assign_to = $people->pluck('name')->implode(', ');
        $task->save();

        return 'Assigned task #'.$task->id.' to '.$task->assign_to."\n"
            .'Open: '.url('/tasks?highlight='.$task->id);
    }

    public static function handleDeadline(User $viewer, string $args): string
    {
        $task = self::findTaskFromArgs($viewer, $args);
        if (is_string($task)) {
            return $task;
        }

        $when = self::parseWhen($args);
        if (! $when) {
            return 'When? Example: Change deadline '.$task->id.' tomorrow';
        }

        $task->completion_date = $when;
        $task->save();

        return 'Deadline for task #'.$task->id.' is now '.$when->format('M j, Y')."\n"
            .'Open: '.url('/tasks?highlight='.$task->id);
    }

    /**
     * @return Task|string
     */
    private static function findTaskFromArgs(User $viewer, string $args): Task|string
    {
        if (! preg_match('/#?(\d{1,10})/', $args, $m)) {
            return 'Which task? Include the task number, e.g. Complete task 123.';
        }
        $task = Task::query()->find((int) $m[1]);
        if (! $task) {
            return 'I could not find task #'.$m[1].'.';
        }
        if (! self::canTouchTask($viewer, $task)) {
            return 'You do not have access to task #'.$task->id.'.';
        }

        return $task;
    }

    private static function canTouchTask(User $viewer, Task $task): bool
    {
        if (ChatWorkspace::canInspectOther($viewer)) {
            return true;
        }
        $names = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) $task->assign_to))));

        return in_array(strtolower((string) $viewer->name), $names, true)
            || strcasecmp((string) $task->created_by, (string) $viewer->name) === 0;
    }

    private static function parseWhen(string $args): ?\Carbon\Carbon
    {
        $lower = strtolower($args);
        $now = TaskBusinessTime::now();
        if (preg_match('/\btomorrow\b/', $lower)) {
            return $now->copy()->addDay()->startOfDay();
        }
        if (preg_match('/\btoday\b/', $lower)) {
            return $now->copy()->endOfDay();
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/', $args, $m)) {
            $year = isset($m[3]) ? (int) $m[3] : (int) $now->year;
            if ($year < 100) {
                $year += 2000;
            }
            try {
                return \Carbon\Carbon::create($year, (int) $m[1], (int) $m[2], 18, 0, 0, TaskBusinessTime::tz());
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return User|string
     */
    private static function resolveTarget(User $viewer, string $args): User|string
    {
        $mentions = ChatWorkspace::resolveMentions($args);
        if ($mentions->isEmpty()) {
            return $viewer;
        }

        $target = $mentions->first();
        if (! $target) {
            return $viewer;
        }

        if ((int) $target->id === (int) $viewer->id) {
            return $viewer;
        }

        if (! ChatWorkspace::canInspectOther($viewer)) {
            return 'You can only view your own score here.';
        }

        return $target;
    }

    public static function dailyDarBody(User $user): ?string
    {
        $today = TaskBusinessTime::today()->toDateString();
        $alreadyFiled = Schema::hasTable('dars')
            && \App\Models\Dar::query()
                ->where('user_id', $user->id)
                ->whereDate('report_date', $today)
                ->exists();

        if ($alreadyFiled) {
            return null;
        }

        $metrics = DarL30Metrics::forUserId((int) $user->id);
        $pct = (int) ($metrics['dar_l30_pct'] ?? 0);
        $count = (int) ($metrics['dar_l30_count'] ?? 0);
        $goal = (int) ($metrics['dar_l30_target'] ?? DarL30Metrics::TARGET);
        $msg = UserDarNudge::logoutMessages()[array_rand(UserDarNudge::logoutMessages())];

        return "📋 DAR reminder — you are at {$pct}% ({$count}/{$goal} last 30 days).\n"
            .$msg."\n"
            .'Fill DAR from the top bar.';
    }

    public static function dailyOverdueBody(User $user): ?string
    {
        $count = UserOverdueNudge::countForUser($user);
        if ($count < 1) {
            return null;
        }

        $msg = UserOverdueNudge::messages()[array_rand(UserOverdueNudge::messages())];

        return "😢 You have {$count} overdue task".($count === 1 ? '' : 's').".\n"
            .$msg."\n"
            .'Open Tasks: '.url('/tasks');
    }

    public static function alreadyNudgedToday(ChatChannel $channel, string $command): bool
    {
        $start = TaskBusinessTime::todayStart()->timezone(config('app.timezone'))->toDateTimeString();

        return ChatMessage::query()
            ->where('channel_id', $channel->id)
            ->where('command', $command)
            ->where('created_at', '>=', $start)
            ->exists();
    }

    /**
     * Restored dumps often drop AUTO_INCREMENT on id (SQLSTATE 1364).
     */
    private static function nextIdIfNoAutoincrement(string $table): ?int
    {
        try {
            $col = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (str_contains($extra, 'auto_increment')) {
                return null;
            }
        } catch (\Throwable) {
            // fall through
        }

        return ((int) (DB::table($table)->max('id') ?? 0)) + 1;
    }

    public static function postBot(ChatChannel $channel, string $body, string $command): ChatMessage
    {
        return ChatMessage::query()->create([
            'channel_id' => $channel->id,
            'user_id' => null,
            'is_bot' => true,
            'bot_name' => ChatWorkspace::BOT_NAME,
            'body' => $body,
            'command' => $command,
        ]);
    }
}
