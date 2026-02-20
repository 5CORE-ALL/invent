<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckScheduler extends Command
{
    protected $signature = 'scheduler:check';

    protected $description = 'Check if scheduler is running and show crontab / reverb:fetch status';

    public function handle(): int
    {
        $this->info('Checking scheduler status...');

        Log::info('Scheduler heartbeat at: ' . now()->toDateTimeString());

        // Crontab (Unix; on Windows this may be empty)
        $crontab = null;
        if (PHP_OS_FAMILY !== 'Windows') {
            $crontab = @shell_exec('crontab -l 2>/dev/null');
        }
        if ($crontab && trim($crontab) !== '') {
            $this->info('Crontab entries:');
            $this->line($crontab);
        } else {
            $this->warn('No crontab found (or Windows). Ensure cron runs: php artisan schedule:run');
        }

        $this->info('To run Reverb fetch manually: php artisan reverb:fetch');
        $this->info('Schedule definition is in app/Console/Kernel.php (reverb:fetch every 5 min).');

        return self::SUCCESS;
    }
}
