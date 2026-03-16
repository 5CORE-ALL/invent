<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncInventoryToReverb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct()
    {
        $this->onQueue('reverb');
    }

    public function handle(): void
    {
        Log::info('SyncInventoryToReverb: starting inventory sync from Shopify to Reverb');

        Artisan::call('reverb:sync-inventory-from-shopify');

        Log::info('SyncInventoryToReverb: completed', [
            'output' => trim(Artisan::output()),
        ]);
    }
}
