<?php

namespace App\Jobs;

use App\Console\Commands\FetchShopifyMetaCampaigns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class FetchShopifyMetaCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dateRange;
    protected $channel;

    /**
     * Create a new job instance.
     */
    public function __construct($dateRange = 'all', $channel = 'both')
    {
        $this->dateRange = $dateRange;
        $this->channel = $channel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('shopify:fetch-meta-campaigns', [
            '--range' => $this->dateRange,
            '--channel' => $this->channel,
        ]);
    }
}

