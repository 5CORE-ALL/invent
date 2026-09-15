<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\InactiveListingsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshInactiveListingsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function uniqueId(): string
    {
        return 'inactive-listings-portal-sync';
    }

    public function handle(InactiveListingsSyncService $sync): void
    {
        $sync->run();
    }

    public function failed(?\Throwable $e): void
    {
        $current = InactiveListingsSyncService::status();
        Cache::put(InactiveListingsSyncService::STATUS_KEY, [
            'status' => 'failed',
            'started_at' => $current['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'message' => $e?->getMessage() ?: 'Inactive listings sync failed.',
            'channels' => $current['channels'] ?? [],
        ], now()->addDays(2));
        Log::error('RefreshInactiveListingsJob failed', [
            'error' => $e?->getMessage(),
        ]);
    }
}
