<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Background carrier status sync for Sales Order Fulfillment (~thousands of trackings).
 * Keeps the SOF HTTP request instant.
 */
class SyncShipmentTrackingStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1900;

    public function __construct(
        public int $limit = 2000,
        public int $stale = 0,
        public bool $repairQuota = true,
        public bool $catchUp = true,
        public string $carrier = '',
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'sof-sync-shipment-tracking-status';
    }

    public function handle(): void
    {
        $params = [
            '--only-open' => true,
            '--limit' => max(1, min(5000, $this->limit)),
            '--stale' => max(0, $this->stale),
        ];
        if ($this->catchUp) {
            $params['--catch-up'] = true;
        }
        if ($this->repairQuota) {
            $params['--repair-quota'] = true;
        }
        if (trim($this->carrier) !== '') {
            $params['--carrier'] = strtoupper(trim($this->carrier));
        }

        $exit = Artisan::call('tracking:sync-status', $params);
        $output = trim(Artisan::output());

        Log::info('SyncShipmentTrackingStatusJob finished', [
            'exit' => $exit,
            'limit' => $this->limit,
            'stale' => $this->stale,
            'catch_up' => $this->catchUp,
            'output' => mb_substr($output, 0, 2000),
        ]);
    }
}
