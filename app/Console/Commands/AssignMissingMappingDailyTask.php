<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Models\Task;
use App\Services\CronMonitor\CronExecutionContext;
use App\Services\TaskWhatsAppNotificationService;
use App\Support\Marketplace\MappingChannelCounts;
use App\Support\TaskBusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily 15:00 IST — assign Missing Mapping task with live badge count to tech-support.
 */
class AssignMissingMappingDailyTask extends Command
{
    use MonitorsCronExecution;

    public const ASSIGN_TO = 'tech-support@5core.com';

    public const ASSIGNOR = 'president@5core.com';

    public const GROUP = 'Missing Mapping with Count';

    public const TITLE_BASE = 'Missing Mapping';

    /** Dedup marker stored in link8 (not shown as a primary link). */
    public const DEDUP_MARKER = 'missing-mapping-daily';

    public const LINK = 'https://inventory.5coremanagement.com/map-issues';

    protected $signature = 'tasks:assign-missing-mapping-daily
        {--dry-run : Compute count and show what would be created, without inserting}
        {--force : Create even if a task for today already exists}';

    protected $description = 'Assign daily Missing Mapping task (badge count) to tech-support@5core.com at 15:00 IST';

    protected string $monitorJobName = 'Missing Mapping Daily Task';

    public function handle(TaskWhatsAppNotificationService $taskWhatsApp): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeAssign($taskWhatsApp, $m),
            $this->monitorJobName
        );
    }

    protected function executeAssign(TaskWhatsAppNotificationService $taskWhatsApp, CronExecutionContext $monitor): int
    {
        TaskBusinessTime::applyDatabaseSession();
        $now = TaskBusinessTime::now();
        $today = $now->toDateString();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('Missing Mapping — daily task assign');
        $this->info('Schedule: daily 15:00 Asia/Kolkata');
        $this->info("Office now: {$now->format('Y-m-d H:i:s')} ({$today})");
        if ($dryRun) {
            $this->warn('DRY RUN — no task will be inserted');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $nmapCount = MappingChannelCounts::totalTitas(false);
        $countLabel = number_format($nmapCount);
        $this->info("Missing Mapping count: {$countLabel}");
        $monitor->markApiConnected();
        $monitor->setFetched(1);
        $monitor->setExpected(1);

        $dayStart = TaskBusinessTime::todayStart();
        $dayEnd = TaskBusinessTime::todayEnd();
        $dayStartStr = $dayStart->format('Y-m-d H:i:s');
        $dayEndStr = $dayEnd->format('Y-m-d H:i:s');

        $alreadyExists = DB::table('tasks')
            ->whereNull('deleted_at')
            ->where('link8', self::DEDUP_MARKER)
            ->where(function ($q) use ($dayStartStr, $dayEndStr) {
                $q->whereBetween('start_date', [$dayStartStr, $dayEndStr])
                    ->orWhereBetween('created_at', [$dayStartStr, $dayEndStr]);
            })
            ->exists();

        if ($alreadyExists && ! $force) {
            $this->warn('⊘ Skipped — task already created today (use --force to recreate)');
            Log::info('Missing Mapping daily task skipped: already exists', ['date' => $today]);
            $monitor->setSkipped(1);
            $monitor->setProcessed(1);

            return self::SUCCESS;
        }

        $title = self::TITLE_BASE.': '.$countLabel.' [Auto: '.$now->format('d-M-y').']';
        $startDate = TaskBusinessTime::today()->setTime(15, 0, 0);
        $dueDate = $startDate->copy()->addDays(5);

        $taskData = [
            'task_id' => null,
            'title' => $title,
            'group' => self::GROUP,
            'priority' => 'Normal',
            'description' => 'Auto-assigned from /map-issues Missing Mapping badge ('.$countLabel.'). Resolve N Map / inventory mapping mismatches across channels.',
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
            'link2' => null,
            'link3' => null,
            'link4' => null,
            'link5' => null,
            'link6' => null,
            'link7' => null,
            'link8' => self::DEDUP_MARKER,
            'link9' => null,
            'image' => null,
            'automate_task_id' => null,
            'task_type' => 'automate_task',
            'schedule_type' => 'daily',
            'schedule_time' => '15:00:00',
            'status' => 'Todo',
            'rework_reason' => null,
            'delete_rating' => 0,
            'delete_feedback' => null,
            'order' => 0,
            'workspace' => 0,
            'is_data_from' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->info("Title: {$title}");
        $this->info('Assignee: '.self::ASSIGN_TO);
        $this->info('Group: '.self::GROUP);
        $this->info('Start: '.$startDate->format('Y-m-d H:i:s').' · Due: '.$dueDate->format('Y-m-d H:i:s'));

        if ($dryRun) {
            $this->info('✓ Dry run complete — would assign task above');
            $monitor->setProcessed(1);
            $monitor->setUpdated(1);

            return self::SUCCESS;
        }

        $lockKey = 'inv_mm_nmap_'.$now->format('Ymd');
        $lockHeld = false;
        if (DB::getDriverName() === 'mysql') {
            $lockRow = DB::selectOne('SELECT GET_LOCK(?, 30) AS acquired', [$lockKey]);
            $acquired = isset($lockRow->acquired) ? (int) $lockRow->acquired : 0;
            if ($acquired !== 1) {
                $this->warn('⊘ Skipped — lock busy');

                return self::SUCCESS;
            }
            $lockHeld = true;
        }

        try {
            if (! $force) {
                $alreadyExists = DB::table('tasks')
                    ->whereNull('deleted_at')
                    ->where('link8', self::DEDUP_MARKER)
                    ->where(function ($q) use ($dayStartStr, $dayEndStr) {
                        $q->whereBetween('start_date', [$dayStartStr, $dayEndStr])
                            ->orWhereBetween('created_at', [$dayStartStr, $dayEndStr]);
                    })
                    ->exists();
                if ($alreadyExists) {
                    $this->warn('⊘ Skipped — task already created today (race)');

                    return self::SUCCESS;
                }
            }

            $taskId = DB::table('tasks')->insertGetId($taskData);
            $taskInstance = Task::find($taskId);

            if ($taskInstance) {
                try {
                    $taskWhatsApp->notifyNewTaskAssigned($taskInstance);
                } catch (\Throwable $e) {
                    Log::warning('Missing Mapping daily task WhatsApp notify failed: '.$e->getMessage(), [
                        'task_id' => $taskId,
                    ]);
                }
            }

            $this->info("✓ Created task #{$taskId} → ".self::ASSIGN_TO);
            Log::info('Missing Mapping daily task assigned', [
                'task_id' => $taskId,
                'nmap_count' => $nmapCount,
                'assign_to' => self::ASSIGN_TO,
                'date' => $today,
            ]);
            $monitor->setProcessed(1);
            $monitor->setUpdated(1);

            return self::SUCCESS;
        } finally {
            if ($lockHeld) {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockKey]);
            }
        }
    }
}
