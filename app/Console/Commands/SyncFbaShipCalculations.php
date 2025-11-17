<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FbaManualDataService;

class SyncFbaShipCalculations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fba:sync-ship-calculations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync and update all FBA Ship Calculations to database';

    protected $fbaManualDataService;

    public function __construct(FbaManualDataService $fbaManualDataService)
    {
        parent::__construct();
        $this->fbaManualDataService = $fbaManualDataService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting FBA Ship Calculations sync...');
        
        $result = $this->fbaManualDataService->bulkUpdateCalculations();
        
        if ($result['success']) {
            $this->info("✅ Successfully synced {$result['updated']} FBA Ship Calculations!");
        } else {
            $this->error("❌ Error: {$result['message']}");
        }
        
        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
