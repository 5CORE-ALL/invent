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
        return "Hi — I'm @invent.\n"
            ."I can create tasks and report overdue, DAR, and SI.\n\n"
            .self::helpText();
    }

    public static function helpText(): string
    {
        return "Commands:\n"
            ."/task Buy packing tape @name high\n"
            ."/overdue\n"
            ."/overdue @name\n"
            ."/dar\n"
            ."/dar @name\n"
            ."/si\n"
            ."/si @name\n"
            ."/help\n\n"
            .'Mention someone with @firstname or @emaillocal. Priority can be low, normal, or high.';
    }

    public static function looksLikeCommand(string $body): bool
    {
        $trim = ltrim($body);

        return Str::startsWith($trim, '/') || Str::startsWith(strtolower($trim), '@invent');
    }

    public static function reply(User $user, ChatChannel $channel, string $body): ?ChatMessage
    {
        if (! self::looksLikeCommand($body)) {
            return null;
        }

        $parsed = self::parse($body);
        $command = $parsed['command'];
        $args = $parsed['args'];

        $text = match ($command) {
            'help', 'invent', '' => self::helpText(),
            'task' => self::handleTask($user, $args),
            'overdue' => self::handleOverdue($user, $args),
            'dar' => self::handleDar($user, $args),
            'si' => self::handleSi($user, $args),
            default => 'Unknown command `'.$command.'`.'."\n\n".self::helpText(),
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
            return ['command' => 'help', 'args' => $trim];
        }

        if (! preg_match('/^\/([A-Za-z]+)(?:\s+([\s\S]+))?$/', $trim, $m)) {
            return ['command' => 'help', 'args' => ''];
        }

        return [
            'command' => strtolower($m[1]),
            'args' => trim((string) ($m[2] ?? '')),
        ];
    }

    public static function handleTask(User $user, string $args): string
    {
        $args = trim($args);
        if ($args === '') {
            return "Please include a title.\nExample: `/task Buy packing tape @aman high`";
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
            return "Please include a title after /task.\nExample: `/task Buy packing tape @aman high`";
        }

        $assignees = $mentions->isNotEmpty() ? $mentions : collect([$user]);
        $emails = $assignees->pluck('email')->filter()->map(fn ($e) => trim((string) $e))->unique()->values()->all();
        if ($emails === []) {
            return 'Could not resolve an assignee. Use @firstname or @emaillocal.';
        }

        $startDate = TaskBusinessTime::now();
        $completionDate = $startDate->copy()->addDays(5);

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
