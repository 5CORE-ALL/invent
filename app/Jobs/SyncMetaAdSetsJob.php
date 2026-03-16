<?php

namespace App\Jobs;

use App\Models\MetaAdSet;
use App\Models\MetaCampaign;
use App\Services\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncMetaAdSetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $userId;
    protected $campaignMetaId;

    public function __construct(?int $userId = null, ?string $campaignMetaId = null)
    {
        $this->userId = $userId;
        $this->campaignMetaId = $campaignMetaId;
    }

    public function handle(MetaAdsService $metaAdsService): void
    {
        try {
            Log::info('SyncMetaAdSetsJob: Starting sync', [
                'user_id' => $this->userId,
                'campaign_id' => $this->campaignMetaId,
            ]);
            
            $adsets = $metaAdsService->fetchAdSets($this->campaignMetaId ?? '');
            $synced = 0;

            $campaign = null;
            if ($this->campaignMetaId) {
                $campaign = MetaCampaign::where('meta_id', $this->campaignMetaId)->first();
            }

            foreach ($adsets as $adset) {
                $metaId = $adset['id'] ?? null;
                if (!$metaId) continue;

                $metaUpdatedTime = null;
                if (isset($adset['updated_time'])) {
                    try {
                        $metaUpdatedTime = Carbon::parse($adset['updated_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                $startTime = null;
                $endTime = null;
                if (isset($adset['start_time'])) {
                    try {
                        $startTime = Carbon::parse($adset['start_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }
                if (isset($adset['end_time'])) {
                    try {
                        $endTime = Carbon::parse($adset['end_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                MetaAdSet::updateOrCreate(
                    [
                        'user_id' => $this->userId,
                        'meta_id' => $metaId,
                    ],
                    [
                        'ad_account_id' => $campaign?->ad_account_id,
                        'campaign_id' => $campaign?->id,
                        'name' => $adset['name'] ?? null,
                        'status' => $adset['status'] ?? null,
                        'effective_status' => $adset['effective_status'] ?? null,
                        'optimization_goal' => $adset['optimization_goal'] ?? null,
                        'daily_budget' => isset($adset['daily_budget']) ? ($adset['daily_budget'] / 100) : null,
                        'lifetime_budget' => isset($adset['lifetime_budget']) ? ($adset['lifetime_budget'] / 100) : null,
                        'budget_remaining' => isset($adset['budget_remaining']) ? ($adset['budget_remaining'] / 100) : null,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'billing_event' => $adset['billing_event'] ?? null,
                        'bid_amount' => $adset['bid_amount'] ?? null,
                        'targeting' => $adset['targeting'] ?? null,
                        'meta_updated_time' => $metaUpdatedTime,
                        'synced_at' => now(),
                        'raw_json' => $adset,
                    ]
                );
                $synced++;
            }

            Log::info('SyncMetaAdSetsJob: Completed', [
                'user_id' => $this->userId,
                'synced' => $synced,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncMetaAdSetsJob: Failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
