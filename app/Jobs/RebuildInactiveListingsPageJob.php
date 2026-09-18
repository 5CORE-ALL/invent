<?php

namespace App\Jobs;

use App\Support\Marketplace\MappingChannelCounts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RebuildInactiveListingsPageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 400;

    public int $uniqueFor = 400;

    public function uniqueId(): string
    {
        return 'inactive-listings-page-rebuild';
    }

    public function handle(): void
    {
        try {
            MappingChannelCounts::inactiveMasterRows(false);
        } catch (\Throwable $e) {
            Log::warning('RebuildInactiveListingsPageJob failed: '.$e->getMessage());
            throw $e;
        }
    }
}
