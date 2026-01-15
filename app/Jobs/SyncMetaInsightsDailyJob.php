<?php

namespace App\Jobs;

use App\Models\MetaInsightDaily;
use App\Models\MetaCampaign;
use App\Models\MetaAdSet;
use App\Models\MetaAd;
use App\Services\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncMetaInsightsDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes for insights
    public $tries = 3;

    protected $userId;
    protected $entityType;
    protected $entityMetaId;
    protected $dateStart;
    protected $dateEnd;

    public function __construct(
        ?int $userId = null,
        string $entityType = 'campaign',
        ?string $entityMetaId = null,
        ?string $dateStart = null,
        ?string $dateEnd = null
    ) {
        $this->userId = $userId;
        $this->entityType = $entityType;
        $this->entityMetaId = $entityMetaId;
        // Default to last 1 day for daily graph data
        $this->dateStart = $dateStart ?? Carbon::yesterday()->format('Y-m-d');
        $this->dateEnd = $dateEnd ?? Carbon::yesterday()->format('Y-m-d');
    }

    public function handle(MetaAdsService $metaAdsService): void
    {
        try {
            Log::info('SyncMetaInsightsDailyJob: Starting sync', [
                'user_id' => $this->userId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityMetaId,
                'date_start' => $this->dateStart,
                'date_end' => $this->dateEnd,
            ]);
            
            if (!$this->entityMetaId) {
                Log::warning('SyncMetaInsightsDailyJob: No entity ID provided');
                return;
            }

            $insights = $metaAdsService->fetchInsightsDaily(
                $this->entityType,
                $this->entityMetaId,
                $this->dateStart,
                $this->dateEnd
            );

            $synced = 0;

            // Get local entity ID
            $localEntityId = null;
            if ($this->entityType === 'campaign') {
                $entity = MetaCampaign::where('meta_id', $this->entityMetaId)->first();
            } elseif ($this->entityType === 'adset') {
                $entity = MetaAdSet::where('meta_id', $this->entityMetaId)->first();
            } elseif ($this->entityType === 'ad') {
                $entity = MetaAd::where('meta_id', $this->entityMetaId)->first();
            } else {
                Log::warning('SyncMetaInsightsDailyJob: Unknown entity type', ['type' => $this->entityType]);
                return;
            }

            if (!$entity) {
                Log::warning('SyncMetaInsightsDailyJob: Entity not found locally', [
                    'type' => $this->entityType,
                    'meta_id' => $this->entityMetaId,
                ]);
                return;
            }

            $localEntityId = $entity->id;

            foreach ($insights as $insight) {
                $dateStart = $insight['date_start'] ?? $this->dateStart;
                $breakdowns = $insight['breakdowns'] ?? [];
                $breakdownHash = md5(json_encode($breakdowns));

                // Parse actions
                $actions = $insight['actions'] ?? [];
                $actionValues = 0;
                $purchases = 0;
                $purchaseRoas = 0;

                if (is_array($actions)) {
                    foreach ($actions as $action) {
                        if (isset($action['action_type']) && $action['action_type'] === 'purchase') {
                            $purchases = (int) ($action['value'] ?? 0);
                        }
                    }
                }

                if (isset($insight['action_values'])) {
                    foreach ($insight['action_values'] as $actionValue) {
                        if (isset($actionValue['action_type']) && $actionValue['action_type'] === 'purchase') {
                            $actionValues = (float) ($actionValue['value'] ?? 0);
                        }
                    }
                }

                if ($purchases > 0 && isset($insight['spend']) && $insight['spend'] > 0) {
                    $purchaseRoas = $actionValues / $insight['spend'];
                }

                $cpa = 0;
                if ($purchases > 0 && isset($insight['spend']) && $insight['spend'] > 0) {
                    $cpa = $insight['spend'] / $purchases;
                }

                MetaInsightDaily::updateOrCreate(
                    [
                        'user_id' => $this->userId,
                        'entity_type' => $this->entityType,
                        'entity_id' => $localEntityId,
                        'date_start' => $dateStart,
                        'breakdown_hash' => $breakdownHash,
                    ],
                    [
                        'impressions' => (int) ($insight['impressions'] ?? 0),
                        'clicks' => (int) ($insight['clicks'] ?? 0),
                        'reach' => (int) ($insight['reach'] ?? 0),
                        'spend' => (float) ($insight['spend'] ?? 0),
                        'ctr' => (float) ($insight['ctr'] ?? 0),
                        'cpc' => (float) ($insight['cpc'] ?? 0),
                        'cpm' => (float) ($insight['cpm'] ?? 0),
                        'cpp' => (float) ($insight['cpp'] ?? 0),
                        'frequency' => (float) ($insight['frequency'] ?? 0),
                        'actions_count' => count($actions),
                        'actions' => $actions,
                        'action_values' => $actionValues,
                        'action_values_breakdown' => $insight['action_values'] ?? null,
                        'purchases' => $purchases,
                        'purchase_roas' => $purchaseRoas,
                        'cpa' => $cpa,
                        'breakdowns_json' => $breakdowns,
                        'synced_at' => now(),
                    ]
                );
                $synced++;
            }

            Log::info('SyncMetaInsightsDailyJob: Completed', [
                'user_id' => $this->userId,
                'synced' => $synced,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncMetaInsightsDailyJob: Failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
