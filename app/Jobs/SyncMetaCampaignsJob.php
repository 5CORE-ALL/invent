<?php

namespace App\Jobs;

use App\Models\MetaCampaign;
use App\Models\MetaAdAccount;
use App\Services\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncMetaCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $userId;
    protected $adAccountMetaId;

    public function __construct(?int $userId = null, ?string $adAccountMetaId = null)
    {
        $this->userId = $userId;
        $this->adAccountMetaId = $adAccountMetaId;
    }

    public function handle(MetaAdsService $metaAdsService): void
    {
        try {
            Log::info('SyncMetaCampaignsJob: Starting sync', [
                'user_id' => $this->userId,
                'ad_account_id' => $this->adAccountMetaId,
            ]);
            
            $campaigns = $metaAdsService->fetchCampaigns($this->adAccountMetaId ?? '');
            $synced = 0;

            $adAccount = null;
            if ($this->adAccountMetaId) {
                $adAccount = MetaAdAccount::where('meta_id', $this->adAccountMetaId)->first();
            }

            foreach ($campaigns as $campaign) {
                $metaId = $campaign['id'] ?? null;
                if (!$metaId) continue;

                $metaUpdatedTime = null;
                if (isset($campaign['updated_time'])) {
                    try {
                        $metaUpdatedTime = Carbon::parse($campaign['updated_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                $startTime = null;
                $stopTime = null;
                if (isset($campaign['start_time'])) {
                    try {
                        $parsed = Carbon::parse($campaign['start_time']);
                        // Only accept dates after 1970-01-01 (Unix epoch)
                        if ($parsed->timestamp > 0) {
                            $startTime = $parsed;
                        }
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }
                if (isset($campaign['stop_time'])) {
                    try {
                        $parsed = Carbon::parse($campaign['stop_time']);
                        // Only accept dates after 1970-01-01 (Unix epoch)
                        if ($parsed->timestamp > 0) {
                            $stopTime = $parsed;
                        }
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                MetaCampaign::updateOrCreate(
                    [
                        'user_id' => $this->userId,
                        'meta_id' => $metaId,
                    ],
                    [
                        'ad_account_id' => $adAccount?->id,
                        'name' => $campaign['name'] ?? null,
                        'status' => $campaign['status'] ?? null,
                        'effective_status' => $campaign['effective_status'] ?? null,
                        'objective' => $campaign['objective'] ?? null,
                        'daily_budget' => isset($campaign['daily_budget']) ? ($campaign['daily_budget'] / 100) : null, // Convert cents to dollars
                        'lifetime_budget' => isset($campaign['lifetime_budget']) ? ($campaign['lifetime_budget'] / 100) : null,
                        'budget_remaining' => isset($campaign['budget_remaining']) ? ($campaign['budget_remaining'] / 100) : null,
                        'start_time' => $startTime,
                        'stop_time' => $stopTime,
                        'buying_type' => $campaign['buying_type'] ?? null,
                        'bid_strategy' => $campaign['bid_strategy'] ?? null,
                        'special_ad_categories' => $campaign['special_ad_categories'] ?? null,
                        'meta_updated_time' => $metaUpdatedTime,
                        'synced_at' => now(),
                        'raw_json' => $campaign,
                    ]
                );
                $synced++;
            }

            // Meta's default /campaigns listing leaves out ARCHIVED campaigns, so
            // campaigns archived after their last sync kept a stale name / status
            // forever (e.g. shown as ACTIVE on /facebook-all-ads-sheet). Refresh
            // name + status of the archived ones we already store. Rows are only
            // updated, never created, so no archived history is added.
            $archivedRefreshed = 0;
            if ($adAccount && $this->adAccountMetaId) {
                try {
                    foreach ($metaAdsService->fetchArchivedCampaigns($this->adAccountMetaId) as $campaign) {
                        $metaId = $campaign['id'] ?? null;
                        if (! $metaId) continue;
                        $archivedRefreshed += MetaCampaign::where('meta_id', $metaId)
                            ->where('ad_account_id', $adAccount->id)
                            ->update([
                                'name' => $campaign['name'] ?? null,
                                'status' => $campaign['status'] ?? null,
                                'effective_status' => $campaign['effective_status'] ?? null,
                                'synced_at' => now(),
                            ]);
                    }
                } catch (\Throwable $e) {
                    // Never fail the main campaign sync because of this refresh.
                    Log::warning('SyncMetaCampaignsJob: archived refresh failed', ['error' => $e->getMessage()]);
                }
            }

            Log::info('SyncMetaCampaignsJob: Completed', [
                'user_id' => $this->userId,
                'synced' => $synced,
                'archived_refreshed' => $archivedRefreshed,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncMetaCampaignsJob: Failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
