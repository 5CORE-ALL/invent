<?php

namespace App\Jobs\missing_listing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseInventoryFetchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $platform;
    protected $batchId;
    public $timeout = 600; // 10 minutes
    public $tries = 3;     // Number of retry attempts

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    abstract protected function fetchInventory();

    public function handle()
    {
        try {
            Log::info("Starting inventory fetch for {$this->platform}", ['batch_id' => $this->batchId]);
            $result = $this->fetchInventory();
            
            // Store the result in cache or temporary storage
            cache()->put("inventory_fetch_{$this->batchId}_{$this->platform}", [
                'status' => 'completed',
                'data' => $result,
                'timestamp' => now()
            ], 3600); // Cache for 1 hour

            Log::info("Completed inventory fetch for {$this->platform}", [
                'batch_id' => $this->batchId,
                'count' => is_array($result) ? count($result) : 0
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching inventory for {$this->platform}: " . $e->getMessage(), [
                'batch_id' => $this->batchId,
                'exception' => $e
            ]);

            cache()->put("inventory_fetch_{$this->batchId}_{$this->platform}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 3600);

            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error("Job failed for {$this->platform}: " . $exception->getMessage(), [
            'batch_id' => $this->batchId,
            'exception' => $exception
        ]);
    }
}