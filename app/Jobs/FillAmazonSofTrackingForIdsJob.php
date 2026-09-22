<?php

namespace App\Jobs;

use App\Services\MarketplaceManager\AmazonTrackingSyncService;
use App\Services\MarketplaceManager\MarketplaceManagerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FillAmazonSofTrackingForIdsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 180;

    public array $backoff = [15, 45];

    /**
     * @param  list<int>  $orderIds
     */
    public function __construct(public array $orderIds)
    {
        $this->onQueue(MarketplaceManagerRegistry::QUEUE_TRACKING);
    }

    public function uniqueId(): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $this->orderIds),
            static fn (int $id) => $id > 0
        )));
        sort($ids);

        return 'mm-amazon-sof-fill-ids-'.md5(implode(',', $ids));
    }

    public function handle(AmazonTrackingSyncService $sync): void
    {
        try {
            $result = $sync->fillMissingSofTrackingForIds($this->orderIds);
            Log::info('FillAmazonSofTrackingForIdsJob', is_array($result) ? $result : []);
        } catch (\Throwable $e) {
            Log::error('FillAmazonSofTrackingForIdsJob failed', [
                'error' => $e->getMessage(),
                'ids' => $this->orderIds,
            ]);
            throw $e;
        }
    }
}
