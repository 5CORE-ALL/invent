<?php

namespace App\Jobs;

use App\Http\Controllers\MarketPlace\MissingListingController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RebuildMissingListingPageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 180;

    public function uniqueId(): string
    {
        return 'missing-listing-page-rebuild';
    }

    public function handle(): void
    {
        try {
            app(MissingListingController::class)->rebuildPagePayload();
        } catch (\Throwable $e) {
            Log::warning('RebuildMissingListingPageJob failed: '.$e->getMessage());
            throw $e;
        }
    }
}
