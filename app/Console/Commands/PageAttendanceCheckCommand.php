<?php

namespace App\Console\Commands;

use App\Services\PageAttendanceService;
use Illuminate\Console\Command;

class PageAttendanceCheckCommand extends Command
{
    protected $signature = 'page-attendance:check';

    protected $description = 'Record page-attendance periods that ended without a check-in and generate a task for the person.';

    public function handle(PageAttendanceService $service): int
    {
        if (! PageAttendanceService::available()) {
            $this->warn('Page attendance tables are missing - run php artisan migrate first.');

            return self::SUCCESS;
        }

        $r = $service->processMissed();
        $this->info("Assignments checked: {$r['checked']} | missed periods recorded: {$r['missed']} | tasks created: {$r['tasks']}");

        return self::SUCCESS;
    }
}
