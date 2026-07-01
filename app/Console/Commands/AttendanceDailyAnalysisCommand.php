<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendanceAiMisuseService;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AttendanceDailyAnalysisCommand extends Command
{
    protected $signature = 'attendance:analyze
                            {--date= : Date to analyze (Y-m-d), defaults to yesterday}
                            {--skip-ai : Skip OpenAI assessment}';

    protected $description = 'Build daily attendance summaries and run AI misuse detection';

    public function handle(
        AttendanceService $attendanceService,
        AttendanceAiMisuseService $aiMisuseService,
    ): int {
        $date = $this->option('date') ?: Carbon::yesterday()->toDateString();
        $useAi = ! $this->option('skip-ai');

        $this->info("Analyzing attendance for {$date}...");

        $closed = $attendanceService->autoCloseStaleSessions();
        $this->line("Auto-closed {$closed} stale session(s).");

        $count = $aiMisuseService->analyzeAllForDate($date);
        $this->info("Processed {$count} employee(s).");

        return self::SUCCESS;
    }
}
