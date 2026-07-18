<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\MonitorsCronExecution;
use App\Console\Commands\Concerns\ProcessesUpdatesInChunks;
use App\Models\AmazonDataView;
use App\Models\AmazonSpCampaignReport;
use App\Models\AmazonUtilizationCount;
use App\Models\FbaTable;
use App\Services\CronMonitor\CronExecutionContext;
use App\Support\AmazonAdsSbidRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAmazonFbaUtilizationCounts extends Command
{
    use MonitorsCronExecution;
    use ProcessesUpdatesInChunks;

    protected $signature = 'amazon-fba:store-utilization-counts
        {--chunk= : Override chunk size (default from cron-monitor config)}';

    protected $description = 'Store daily counts of over/under/correctly utilized Amazon FBA KW and PT campaigns';

    protected string $monitorJobName = 'Amazon FBA Store Utilization Counts';

    public function handle(): int
    {
        return $this->runMonitored(
            fn (CronExecutionContext $m) => $this->executeStore($m),
            $this->monitorJobName
        );
    }

    protected function executeStore(CronExecutionContext $monitor): int
    {
        $this->info('Starting to store Amazon FBA utilization counts...');

        $this->processCampaignType($monitor, 'KW');
        $this->processCampaignType($monitor, 'PT');

        return self::SUCCESS;
    }

    private function processCampaignType(CronExecutionContext $monitor, $campaignType)
    {
        $this->info("Processing FBA {$campaignType} campaigns...");
        $chunkSize = $this->monitoredChunkSize();

        $fbaData = collect();
        FbaTable::whereRaw("seller_sku LIKE '%FBA%' OR seller_sku LIKE '%fba%'")
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$fbaData) {
                foreach ($rows as $row) {
                    $fbaData->push($row);
                }
            });
        $fbaData = $fbaData->sortBy('seller_sku')->values();

        $campaignMap = [];
        $baseCampaigns = collect();
        AmazonSpCampaignReport::query()
            ->where('ad_type', 'SPONSORED_PRODUCTS')
            ->where('report_date_range', 'L30')
            ->whereNotNull('campaign_id')
            ->where('campaignStatus', '!=', 'ARCHIVED')
            ->where('campaignName', 'LIKE', '%FBA%')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$baseCampaigns) {
                foreach ($rows as $row) {
                    $baseCampaigns->push($row);
                }
            });
        $baseCampaigns = $baseCampaigns->unique('campaign_id')->sortBy('campaignName');

        foreach ($baseCampaigns as $baseCampaign) {
            $campaignId = trim((string) ($baseCampaign->campaign_id ?? ''));
            $campaignName = trim((string) ($baseCampaign->campaignName ?? ''));
            if ($campaignId === '' || $campaignName === '') {
                continue;
            }

            $detectedType = $this->detectCampaignType($campaignName, 'kw');
            if (($campaignType === 'PT' && $detectedType !== 'pt') || ($campaignType === 'KW' && $detectedType !== 'kw')) {
                continue;
            }

            $matchedCampaignL7 = AmazonSpCampaignReport::query()
                ->where('campaign_id', $campaignId)
                ->where('report_date_range', 'L7')
                ->latest('id')
                ->first();
            $matchedCampaignL1 = AmazonSpCampaignReport::query()
                ->where('campaign_id', $campaignId)
                ->where('report_date_range', 'L1')
                ->latest('id')
                ->first();

            $inventory = 1;
            foreach ($fbaData as $fba) {
                $sellerSku = trim((string) ($fba->seller_sku ?? ''));
                if ($sellerSku !== '' && str_contains(strtoupper($campaignName), strtoupper($sellerSku))) {
                    $inventory = (int) ($fba->quantity_available ?? 0);
                    break;
                }
            }
            if ($inventory <= 0) {
                $inventory = 1;
            }

            $this->info("Processing campaign: {$campaignName} (Type: ".strtoupper($detectedType).')');

            $campaignMap[$campaignId] = [
                'campaign_id' => $campaignId,
                'campaignName' => $campaignName,
                'budget' => $matchedCampaignL7->campaignBudgetAmount ?? ($matchedCampaignL1->campaignBudgetAmount ?? ($baseCampaign->campaignBudgetAmount ?? 0)),
                'l7_spend' => $matchedCampaignL7->spend ?? 0,
                'l1_spend' => $matchedCampaignL1->spend ?? 0,
                'inv' => $inventory,
            ];
        }

        $overUtilizedCount7ub = 0;
        $underUtilizedCount7ub = 0;
        $correctlyUtilizedCount7ub = 0;

        $overUtilizedCount7ub1ub = 0;
        $underUtilizedCount7ub1ub = 0;
        $correctlyUtilizedCount7ub1ub = 0;

        $fbaTypeKey = $campaignType === 'PT' ? 'fba_pt' : 'fba_kw';
        $sbidRule = AmazonAdsSbidRule::resolvedRule();
        $utilLow = (float) ($sbidRule['util_low'] ?? 66);
        $utilHigh = (float) ($sbidRule['util_high'] ?? 99);

        $entries = [];
        foreach ($campaignMap as $campaignId => $campaignData) {
            $entries[] = ['campaign_id' => $campaignId, 'campaignData' => $campaignData];
        }

        $monitor->setFetched(($monitor->fetchedRecords ?? 0) + count($entries));

        foreach (array_chunk($entries, $chunkSize) as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $fbaTypeKey,
                $utilLow,
                $utilHigh,
                &$overUtilizedCount7ub,
                &$underUtilizedCount7ub,
                &$correctlyUtilizedCount7ub,
                &$overUtilizedCount7ub1ub,
                &$underUtilizedCount7ub1ub,
                &$correctlyUtilizedCount7ub1ub,
                $monitor
            ) {
                $updated = 0;
                foreach ($chunk as $entry) {
                    $campaignId = $entry['campaign_id'];
                    $campaignData = $entry['campaignData'];

                    $budget = $campaignData['budget'] ?? 0;
                    $l7_spend = $campaignData['l7_spend'] ?? 0;
                    $l1_spend = $campaignData['l1_spend'] ?? 0;

                    $ub7 = $budget > 0 ? ($l7_spend / ($budget * 7)) * 100 : 0;
                    $ub1 = $budget > 0 ? ($l1_spend / ($budget * 1)) * 100 : 0;

                    try {
                        AmazonUtilizationCount::updateOrCreate(
                            [
                                'campaign_id' => $campaignId,
                                'campaign_type' => $fbaTypeKey,
                            ],
                            [
                                'campaign_name' => (string) ($campaignData['campaignName'] ?? ''),
                                'ub7' => round($ub7, 2),
                                'ub1' => round($ub1, 2),
                                'inventory' => (int) ($campaignData['inv'] ?? 0),
                            ]
                        );
                        $updated++;
                        if ($this->output->isVerbose()) {
                            $this->line("  FBA utilization upsert: {$campaignId} ({$fbaTypeKey}) ub7=".round($ub7, 2).'% ub1='.round($ub1, 2).'%');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('StoreAmazonFbaUtilizationCounts: failed to upsert utilization row', [
                            'campaign_id' => $campaignId,
                            'campaign_type' => $fbaTypeKey,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    if ($ub7 > $utilHigh) {
                        $overUtilizedCount7ub++;
                    } elseif ($ub7 < $utilLow) {
                        $underUtilizedCount7ub++;
                    } elseif ($ub7 >= $utilLow && $ub7 <= $utilHigh) {
                        $correctlyUtilizedCount7ub++;
                    }

                    if ($ub7 > $utilHigh && $ub1 > $utilHigh) {
                        $overUtilizedCount7ub1ub++;
                    } elseif ($ub7 < $utilLow && $ub1 < $utilLow) {
                        $underUtilizedCount7ub1ub++;
                    } elseif ($ub7 >= $utilLow && $ub7 <= $utilHigh && $ub1 >= $utilLow && $ub1 <= $utilHigh) {
                        $correctlyUtilizedCount7ub1ub++;
                    }
                }
                $monitor->incrementUpdated($updated);
                $monitor->incrementProcessed(count($chunk));
            });
        }

        $today = now()->format('Y-m-d');
        $tomorrow = now()->copy()->addDay()->format('Y-m-d');

        $data = [
            'over_utilized_7ub' => $overUtilizedCount7ub,
            'under_utilized_7ub' => $underUtilizedCount7ub,
            'correctly_utilized_7ub' => $correctlyUtilizedCount7ub,
            'over_utilized_7ub_1ub' => $overUtilizedCount7ub1ub,
            'under_utilized_7ub_1ub' => $underUtilizedCount7ub1ub,
            'correctly_utilized_7ub_1ub' => $correctlyUtilizedCount7ub1ub,
            'date' => $today
        ];

        $blankData = [
            'over_utilized_7ub' => 0,
            'under_utilized_7ub' => 0,
            'correctly_utilized_7ub' => 0,
            'over_utilized_7ub_1ub' => 0,
            'under_utilized_7ub_1ub' => 0,
            'correctly_utilized_7ub_1ub' => 0,
            'date' => $tomorrow
        ];

        $skuKeyToday = 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_' . $today;
        $skuKeyTomorrow = 'AMAZON_FBA_UTILIZATION_' . $campaignType . '_' . $tomorrow;

        $existingToday = AmazonDataView::where('sku', $skuKeyToday)->first();

        if ($existingToday) {
            $existingToday->update(['value' => $data]);
            $this->info("Updated FBA {$campaignType} utilization counts for {$today}");
        } else {
            AmazonDataView::create([
                'sku' => $skuKeyToday,
                'value' => $data
            ]);
            $this->info("Created FBA {$campaignType} utilization counts for {$today}");
        }

        $existingTomorrow = AmazonDataView::where('sku', $skuKeyTomorrow)->first();

        if (!$existingTomorrow) {
            AmazonDataView::create([
                'sku' => $skuKeyTomorrow,
                'value' => $blankData
            ]);
            $this->info("Created blank FBA {$campaignType} utilization counts for {$tomorrow}");
        } else {
            $this->info("Tomorrow's data already exists for {$tomorrow}, skipping blank data creation");
        }

        $this->info("FBA {$campaignType} - 7UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub}");
        $this->info("FBA {$campaignType} - 7UB + 1UB Condition:");
        $this->info("  Over-utilized: {$overUtilizedCount7ub1ub}");
        $this->info("  Under-utilized: {$underUtilizedCount7ub1ub}");
        $this->info("  Correctly-utilized: {$correctlyUtilizedCount7ub1ub}");

        Log::info('StoreAmazonFbaUtilizationCounts: amazon_utilization_counts upserts', [
            'campaign_family' => 'FBA_'.$campaignType,
            'campaign_type_key' => $fbaTypeKey,
            'distinct_campaigns' => count($campaignMap),
        ]);
    }

    private function detectCampaignType(string $campaignName, string $default = 'kw'): string
    {
        $name = strtoupper(trim(preg_replace('/\s+/', ' ', rtrim($campaignName, '.'))));
        if ($name === '') {
            return $default;
        }

        if (preg_match('/\bPT\b/', $name)) {
            return 'pt';
        }
        if (preg_match('/\bKW\b/', $name)) {
            return 'kw';
        }
        if (preg_match('/\b(HL|HEAD)\b/', $name)) {
            return 'hl';
        }

        return $default;
    }
}
