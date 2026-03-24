<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ACOSControl\AmazonACOSController;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonSbCampaignReport;
use Illuminate\Console\Command;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\DB;

/**
 * Sponsored Brands (HL / "HEAD") campaign budgets — same ACOS → SBGT rules as KW/PT tabulator.
 */
class AutoUpdateAmazonBgtHl extends Command
{
    protected $signature = 'amazon:auto-update-amz-bgt-hl {--dry-run : Run without updating Amazon (test only)}';

    protected $description = 'Automatically update Amazon Sponsored Brands (HL) campaign daily budgets from ACOS-based SBGT';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');
            $this->info($dryRun ? 'Starting Amazon HL (SB) bgts auto-update (DRY RUN - no updates will be made)...' : 'Starting Amazon HL (SB) bgts auto-update...');

            try {
                DB::connection()->getPdo();
                $this->info('✓ Database connection OK');
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                $this->error('✗ Database connection failed: ' . $e->getMessage());

                return 1;
            }

            $controller = new AmazonACOSController;

            $campaigns = $this->amazonAcosHlControlData();

            DB::connection()->disconnect();

            if (empty($campaigns)) {
                $this->warn('No campaigns matched filter conditions.');

                return 0;
            }

            $bidCapsData = \App\Models\AmazonBidCap::all()->keyBy('sku');
            $skippedDueToCap = [];

            $validCampaigns = collect($campaigns)->filter(function ($campaign) use ($bidCapsData, &$skippedDueToCap) {
                if (empty($campaign->campaign_id) || !isset($campaign->sbgt) || $campaign->sbgt <= 0) {
                    return false;
                }

                $sku = strtoupper($campaign->campaignName ?? '');
                if ($bidCapsData->has($sku)) {
                    $bidCap = $bidCapsData[$sku]->bid_cap;
                    if ($bidCap > 0 && $campaign->sbgt > $bidCap) {
                        $skippedDueToCap[] = [
                            'campaign' => $campaign->campaignName,
                            'sbgt' => $campaign->sbgt,
                            'cap' => $bidCap,
                        ];

                        return false;
                    }
                }

                return true;
            })->values();

            if (count($skippedDueToCap) > 0) {
                $this->warn("\n⚠️  Campaigns SKIPPED due to Bid Cap protection:");
                foreach ($skippedDueToCap as $skipped) {
                    $this->warn("  - {$skipped['campaign']}: SBGT \${$skipped['sbgt']} > Cap \${$skipped['cap']}");
                }
                $this->warn('');
            }

            if ($validCampaigns->isEmpty()) {
                $this->warn('No valid campaigns found (all have empty campaign_id or invalid budget).');

                return 0;
            }

            $campaignIds = $validCampaigns->pluck('campaign_id')->toArray();
            $newBgts = $validCampaigns->pluck('sbgt')->toArray();

            if (count($campaignIds) !== count($newBgts)) {
                $this->error('Error: Campaign IDs and budgets arrays have different lengths!');

                return 1;
            }

            try {
                $this->info("\n========================================");
                $this->info($dryRun ? 'CAMPAIGN BUDGET UPDATE SUMMARY (HL / SB) [DRY RUN]' : 'CAMPAIGN BUDGET UPDATE SUMMARY (HL / SB)');
                $this->info("========================================\n");

                foreach ($validCampaigns as $campaign) {
                    $this->info('Campaign: ' . ($campaign->campaignName ?? 'N/A'));
                    $this->info('  Price: $' . number_format($campaign->price ?? 0, 2));
                    $this->info('  ACOS: ' . number_format($campaign->acos_L30 ?? 0, 2) . '%');
                    $this->info('  New Budget: $' . ($campaign->sbgt ?? 0));
                    $this->info('  Campaign ID: ' . ($campaign->campaign_id ?? 'N/A'));
                    $this->info('---');
                }

                $this->info("\nTotal Campaigns: " . count($campaignIds));
                $this->info("========================================\n");

                if ($dryRun) {
                    $this->warn('DRY RUN - No updates were made to Amazon.');
                    $this->info('Run without --dry-run to apply budget updates.');
                } else {
                    $result = $controller->updateAutoAmazonSbCampaignBgt($campaignIds, $newBgts);
                    if (isset($result['status']) && $result['status'] !== 200) {
                        $this->error('Budget update failed: ' . ($result['message'] ?? 'Unknown error'));

                        return 1;
                    }
                    $this->info('Successfully updated ' . count($campaignIds) . ' SB campaign budgets.');
                }
            } catch (\Exception $e) {
                $this->error('Error updating campaign budgets: ' . $e->getMessage());

                return 1;
            }
        } finally {
            try {
                DB::connection()->disconnect();
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
        }

        return 0;
    }

    public function amazonAcosHlControlData()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            if (empty($skus)) {
                DB::connection()->disconnect();

                return [];
            }

            $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');

            $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
                return strtoupper($item->sku ?? '');
            });

            $amazonSbCampaignReportsL30 = AmazonSbCampaignReport::where('ad_type', 'SPONSORED_BRANDS')
                ->where('report_date_range', 'L30')
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $sku) {
                        $q->orWhere('campaignName', 'LIKE', '%' . strtoupper($sku) . '%');
                    }
                })
                ->whereRaw("UPPER(campaignStatus) = 'ENABLED'")
                ->get();

            DB::connection()->disconnect();

            $childInvSumByParent = [];
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $shopifyChild = $shopifyData[$pm->sku] ?? null;
                $inv = ($shopifyChild && isset($shopifyChild->inv)) ? (int) $shopifyChild->inv : 0;
                if (!isset($childInvSumByParent[$p])) {
                    $childInvSumByParent[$p] = 0;
                }
                $childInvSumByParent[$p] += $inv;
            }

            $childPricesByParent = [];
            foreach ($productMasters as $pm) {
                $norm = str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $pm->sku ?? '');
                $norm = preg_replace('/\s+/', ' ', $norm);
                $skuUpper = strtoupper(trim($norm));
                if (stripos($skuUpper, 'PARENT') !== false) {
                    continue;
                }
                $p = $pm->parent ?? '';
                if ($p === '') {
                    continue;
                }
                $amazonSheetChild = $amazonDatasheetsBySku[$skuUpper] ?? null;
                $childPrice = ($amazonSheetChild && isset($amazonSheetChild->price) && (float) $amazonSheetChild->price > 0)
                    ? (float) $amazonSheetChild->price
                    : null;
                if ($childPrice === null) {
                    $values = $pm->Values;
                    if (is_string($values)) {
                        $values = json_decode($values, true) ?: [];
                    } elseif (is_object($values)) {
                        $values = (array) $values;
                    } elseif (!is_array($values)) {
                        $values = [];
                    }
                    $childPrice = isset($values['msrp']) && (float) $values['msrp'] > 0
                        ? (float) $values['msrp']
                        : (isset($values['map']) && (float) $values['map'] > 0 ? (float) $values['map'] : null);
                }
                if ($childPrice !== null && $childPrice > 0) {
                    $normParent = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $p ?? ''))));
                    $normParent = rtrim($normParent, '.');
                    if (!isset($childPricesByParent[$normParent])) {
                        $childPricesByParent[$normParent] = [];
                    }
                    $childPricesByParent[$normParent][] = $childPrice;
                }
            }
            $avgPriceByParent = [];
            foreach ($childPricesByParent as $p => $prices) {
                $avgPriceByParent[$p] = count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
            }

            $result = [];
            $validCampaignsForTotal = [];

            foreach ($productMasters as $pm) {
                $sku = strtoupper($pm->sku ?? '');

                $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
                $shopify = $shopifyData[$pm->sku] ?? null;

                $matchedCampaignL30 = $amazonSbCampaignReportsL30->first(function ($item) use ($sku) {
                    $cleanName = preg_replace('/\s+/', ' ', strtoupper(trim($item->campaignName ?? '')));
                    $cleanSku = preg_replace('/\s+/', ' ', strtoupper(trim($sku)));

                    return $cleanName === $cleanSku || $cleanName === $cleanSku . ' HEAD';
                });

                if (!$matchedCampaignL30) {
                    continue;
                }

                $inv = (stripos($sku, 'PARENT') !== false)
                    ? (int) ($childInvSumByParent[$pm->parent ?? $pm->sku ?? ''] ?? 0)
                    : (($shopify && isset($shopify->inv)) ? (int) $shopify->inv : 0);

                if ($inv == 0) {
                    continue;
                }

                if (empty($matchedCampaignL30->campaign_id)) {
                    continue;
                }

                $row = [];
                $price = ($amazonSheet && isset($amazonSheet->price)) ? (float) $amazonSheet->price : 0;
                if (($price === 0 || $price === null) && stripos($sku, 'PARENT') !== false) {
                    $normSku = strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83"], ' ', $sku))));
                    $normParentKey = rtrim($normSku, '.');
                    $price = $avgPriceByParent[$normSku] ?? $avgPriceByParent[$normParentKey] ?? $avgPriceByParent[$pm->parent ?? ''] ?? $avgPriceByParent[rtrim($pm->parent ?? '', '.')] ?? 0;
                }
                $row['price'] = $price;
                $row['campaign_id'] = $matchedCampaignL30->campaign_id ?? '';
                $row['campaignName'] = $matchedCampaignL30->campaignName ?? '';

                if (empty($row['campaignName'])) {
                    continue;
                }

                $sales = (float) ($matchedCampaignL30->sales ?? 0);
                $spend = (float) ($matchedCampaignL30->cost ?? 0);
                $row['spend'] = $spend;
                $row['units_ordered_l30'] = $amazonSheet->units_ordered_l30 ?? 0;

                if ($spend > 0 && $sales > 0) {
                    $row['acos_L30'] = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0 && $sales == 0) {
                    $row['acos_L30'] = 100;
                } else {
                    $row['acos_L30'] = 0;
                }

                $tpft = 0;
                if (isset($nrValues[$pm->sku])) {
                    $raw = $nrValues[$pm->sku];
                    if (!is_array($raw)) {
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        $tpft = isset($raw['TPFT']) ? (int) floor($raw['TPFT']) : 0;
                    }
                }
                $row['TPFT'] = $tpft;

                $validCampaignsForTotal[] = $row;
            }

            foreach ($validCampaignsForTotal as $row) {
                $acos = (float) ($row['acos_L30'] ?? 0);

                // ACOS-based SBGT: <20 -> 10, [20,30) -> 5, >=30 -> 2
                if ($acos < 20) {
                    $row['sbgt'] = 10;
                } elseif ($acos < 30) {
                    $row['sbgt'] = 5;
                } else {
                    $row['sbgt'] = 2;
                }

                $result[] = (object) $row;
            }

            DB::connection()->disconnect();

            return $result;
        } catch (\Exception $e) {
            $this->error('Error in amazonAcosHlControlData: ' . $e->getMessage());
            $this->info('Error trace: ' . $e->getTraceAsString());
            try {
                DB::connection()->disconnect();
            } catch (\Exception $ex) {
                // Ignore disconnect errors
            }

            return [];
        }
    }
}
