<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmazonSpApiService;
use App\Models\AmazonListingRaw;
use Illuminate\Support\Facades\Log;

class ImportAmazonListings extends Command
{
    protected $signature = 'import:amazon-listings';
    protected $description = 'Import all Amazon listings via SP-API';

    public function handle()
    {
        $this->info('Starting Amazon listings import...');
        
        try {
            $service = new AmazonSpApiService();
            $result = $service->fetchAndStoreListingsReport();
            
            $count = AmazonListingRaw::count();
            $this->info("Import completed. Total records: {$count}");
            $this->info("Result: " . json_encode($result));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            Log::error('Import command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
