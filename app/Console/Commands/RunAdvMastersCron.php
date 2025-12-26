<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Channels\AdsMasterController;
use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;

class RunAdvMastersCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adv:run-masters-cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all advertisement masters cron jobs (Amazon & eBay)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting Advertisement Masters Cron Jobs...');
        
        try {
            $apiController = new ApiController();
            $controller = new AdsMasterController($apiController);
            $request = new Request();
            $controller->runAllAdvMastersCronJobs($request);
            
            $this->info('✓ Advertisement masters cron jobs completed successfully.');
            return 0;
        } catch (\Exception $e) {
            $this->error('✗ Error running cron jobs: ' . $e->getMessage());
            return 1;
        }
    }
}
