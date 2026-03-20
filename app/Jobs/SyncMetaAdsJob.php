<?php

namespace App\Jobs;

use App\Models\MetaAd;
use App\Models\MetaAdSet;
use App\Services\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncMetaAdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $userId;
    protected $adSetMetaId;

    public function __construct(?int $userId = null, ?string $adSetMetaId = null)
    {
        $this->userId = $userId;
        $this->adSetMetaId = $adSetMetaId;
    }

    public function handle(MetaAdsService $metaAdsService): void
    {
        try {
            if (empty($this->adSetMetaId)) {
                Log::warning('SyncMetaAdsJob: Skipped - No adset ID provided', [
                    'user_id' => $this->userId,
                ]);
                return;
            }

            Log::info('SyncMetaAdsJob: Starting sync', [
                'user_id' => $this->userId,
                'adset_id' => $this->adSetMetaId,
            ]);
            
            $adset = MetaAdSet::where('meta_id', $this->adSetMetaId)->first();
            
            if (!$adset) {
                Log::warning('SyncMetaAdsJob: Adset not found in database', [
                    'user_id' => $this->userId,
                    'adset_meta_id' => $this->adSetMetaId,
                ]);
                return;
            }
            
            // Add delay to avoid rate limiting (Meta API has strict rate limits)
            sleep(1);
            
            $ads = $metaAdsService->fetchAds($this->adSetMetaId);
            $synced = 0;

            foreach ($ads as $ad) {
                $metaId = $ad['id'] ?? null;
                if (!$metaId) continue;

                $metaUpdatedTime = null;
                if (isset($ad['updated_time'])) {
                    try {
                        $metaUpdatedTime = Carbon::parse($ad['updated_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                $creativeId = null;
                if (isset($ad['creative']) && is_array($ad['creative'])) {
                    $creativeId = $ad['creative']['id'] ?? null;
                } elseif (isset($ad['creative_id'])) {
                    $creativeId = $ad['creative_id'];
                }

                MetaAd::updateOrCreate(
                    [
                        'user_id' => $this->userId,
                        'meta_id' => $metaId,
                    ],
                    [
                        'ad_account_id' => $adset?->ad_account_id,
                        'campaign_id' => $adset?->campaign_id,
                        'adset_id' => $adset?->id,
                        'name' => $ad['name'] ?? null,
                        'status' => $ad['status'] ?? null,
                        'effective_status' => $ad['effective_status'] ?? null,
                        'creative_id' => $creativeId,
                        'preview_shareable_link' => $ad['preview_shareable_link'] ?? null,
                        'meta_updated_time' => $metaUpdatedTime,
                        'synced_at' => now(),
                        'raw_json' => $ad,
                    ]
                );
                $synced++;
            }

            Log::info('SyncMetaAdsJob: Completed', [
                'user_id' => $this->userId,
                'synced' => $synced,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncMetaAdsJob: Failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
