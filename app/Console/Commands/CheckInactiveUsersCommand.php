<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckInactiveUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for inactive users (placeholder command)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Check inactive users command is not implemented yet.');
        return 0;
    }
}

