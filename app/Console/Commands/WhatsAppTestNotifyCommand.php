<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskWhatsAppNotificationService;
use Illuminate\Console\Command;

class WhatsAppTestNotifyCommand extends Command
{
    protected $signature = 'whatsapp:test-notify
                            {task_id : Task ID }
                            {--assignee= : Override assignee email (e.g. user with phone) }';
    protected $description = 'Run "new task assigned" notify for a task. Use --assignee=email to override assign_to.';

    public function handle(TaskWhatsAppNotificationService $svc): int
    {
        $task = Task::find($this->argument('task_id'));
        if (!$task) {
            $this->error('Task not found.');
            return self::FAILURE;
        }

        $override = $this->option('assignee');
        if ($override) {
            $task->assign_to = $override;
            $this->info("Override assign_to => {$override}");
        }

        if (!$task->assign_to) {
            $this->warn('Task has no assignee. Use --assignee=email@example.com');
            return self::FAILURE;
        }

        $this->info("Notifying for task #{$task->id} -> {$task->assign_to}");
        $status = $svc->notifyNewTaskAssigned($task);
        $this->line('Status: ' . $status);
        if ($status === 'sent') {
            $this->info('Done. Check WhatsApp.');
        } elseif ($status === 'skipped_no_phone') {
            $this->warn('Skipped: assignee has no phone. Add users.phone for that user.');
        } elseif ($status === 'skipped_no_user') {
            $this->warn('Skipped: assignee user not found.');
        } else {
            $this->line('Check storage/logs/laravel.log for details.');
        }
        return self::SUCCESS;
    }
}
