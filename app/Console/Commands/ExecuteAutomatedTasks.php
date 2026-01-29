<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExecuteAutomatedTasks extends Command
{
    protected $signature = 'tasks:execute-automated';
    protected $description = 'Execute automated tasks based on their schedule';

    public function handle()
    {
        $this->info('Checking for automated tasks to execute...');
        
        $now = Carbon::now();
        $currentTime = $now->format('H:i');
        $currentDay = $now->format('D'); // Mon, Tue, etc.
        $currentDate = $now->format('j'); // 1-31
        
        // Get all active automated tasks
        $automatedTasks = DB::table('automate_tasks')
            ->where('status', '!=', 'Done')
            ->where('is_pause', '!=', 1)
            ->get();
        
        $executed = 0;
        
        foreach ($automatedTasks as $task) {
            $shouldRun = false;
            
            // Check if it's time to run based on schedule_type
            switch ($task->schedule_type) {
                case 'daily':
                    // Run every day at schedule_time
                    if ($task->schedule_time) {
                        $taskTime = Carbon::parse($task->schedule_time)->format('H:i');
                        $shouldRun = ($currentTime == $taskTime);
                    }
                    break;
                    
                case 'weekly':
                    // Run on specific days of week
                    if ($task->schedule_days && $task->schedule_time) {
                        $scheduledDays = explode(',', $task->schedule_days);
                        $taskTime = Carbon::parse($task->schedule_time)->format('H:i');
                        $shouldRun = in_array($currentDay, $scheduledDays) && ($currentTime == $taskTime);
                    }
                    break;
                    
                case 'monthly':
                    // Run on specific dates of month
                    if ($task->schedule_days && $task->schedule_time) {
                        $scheduledDates = explode(',', $task->schedule_days);
                        $taskTime = Carbon::parse($task->schedule_time)->format('H:i');
                        
                        // Check for End of Month
                        $isEndOfMonth = ($currentDate == $now->endOfMonth()->format('j'));
                        
                        $shouldRun = (in_array($currentDate, $scheduledDates) || (in_array('EOM', $scheduledDates) && $isEndOfMonth)) 
                                     && ($currentTime == $taskTime);
                    }
                    break;
            }
            
            if ($shouldRun) {
                // Calculate NEXT execution date for due_date
                $nextExecutionDate = null;
                
                switch ($task->schedule_type) {
                    case 'daily':
                        $nextExecutionDate = $now->copy()->addDay();
                        break;
                        
                    case 'weekly':
                        // Find next occurrence of selected day
                        $nextExecutionDate = $now->copy()->addWeek();
                        break;
                        
                    case 'monthly':
                        // Add one month
                        $nextExecutionDate = $now->copy()->addMonth();
                        break;
                }
                
                // For automated tasks: completion_date = same as TID (start_date)
                $completionDate = $now;
                
                // Create a new task instance in tasks table
                DB::table('tasks')->insert([
                    'title' => $task->title . ' [Auto: ' . $now->format('d-M-y') . ']',
                    'group' => $task->group,
                    'priority' => $task->priority,
                    'description' => $task->description,
                    'assignor' => $task->assignor,
                    'assign_to' => $task->assign_to,
                    'eta_time' => $task->eta_time,
                    'start_date' => $now,
                    'due_date' => $nextExecutionDate,
                    'completion_date' => $completionDate,
                    'status' => 'Todo',
                    'is_automate_task' => 1,
                    'automate_task_id' => $task->id,
                    'task_type' => 'automate_task',
                    'schedule_type' => $task->schedule_type,
                    'schedule_time' => $task->schedule_time,
                    'link1' => $task->link1 ?? '',
                    'link2' => $task->link2 ?? '',
                    'link3' => $task->link3 ?? '',
                    'link4' => $task->link4 ?? '',
                    'link5' => $task->link5 ?? '',
                    'link6' => $task->link6 ?? '',
                    'link7' => $task->link7 ?? '',
                    'link8' => '',
                    'link9' => '',
                    'split_tasks' => $task->split_tasks ?? 0,
                    'workspace' => $task->workspace ?? 0,
                    'order' => $task->order ?? 0,
                    'task_id' => '',
                    'rework_reason' => '',
                    'delete_rating' => 0,
                    'delete_feedback' => '',
                    'completion_date' => $now,
                    'completion_day' => 0,
                    'etc_done' => 0,
                    'is_missed' => 0,
                    'is_missed_track' => 0,
                    'is_data_from' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                
                $executed++;
                $this->info("✓ Executed: {$task->title}");
            }
        }
        
        $this->info("Completed! {$executed} task(s) executed.");
        return 0;
    }
}
