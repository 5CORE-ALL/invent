<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\JungleScoutProductData;
use App\Models\AmazonDatasheet; // Add this at the top with other use statements
use App\Models\MarketplacePercentage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\AmazonDataView; // Import the AmazonDataView model
use App\Models\AmazonListingStatus;

class AmazonZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function getZeroViewCount()
    {
        // Replicate the filtering logic from getViewAmazonZeroData
        $productMasters = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

        // Fetch AmazonDataView for all SKUs
        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // 2. Fetch AmazonDatasheet and ShopifySku for those SKUs
        // Use groupBy to handle duplicate SKUs, then take the earliest record for each (lowest ID)
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)
            ->get()
            ->groupBy(function ($item) {
                return strtoupper($item->sku);
            })
            ->map(function ($group) {
                // Return the record with the lowest ID (earliest/original)
                return $group->sortBy('id')->first();
            });
        $shopifyByPm = ShopifySku::mapByProductSkus($skus);
        $shopifyData = [];
        foreach ($productMasters as $pm) {
            $k = strtoupper($pm->sku);
            $row = $shopifyByPm->get($pm->sku);
            if ($row !== null) {
                $shopifyData[$k] = $row;
            }
        }

        // 3. Fetch API data (Google Sheet)
        $response = $this->apiController->fetchDataFromAmazonGoogleSheet();
        $apiDataArr = ($response->getStatusCode() === 200) ? ($response->getData()->data ?? []) : [];
        // Index API data by SKU (case-insensitive)
        $apiDataBySku = [];
        foreach ($apiDataArr as $item) {
            $sku = isset($item->{'(Child) sku'}) ? strtoupper(trim($item->{'(Child) sku'})) : null;
            if ($sku)
                $apiDataBySku[$sku] = $item;
        }

        $result = [];
        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $apiItem = $apiDataBySku[$sku] ?? null;
            $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
            $shopify = $shopifyData[$sku] ?? null;

            $row = [];
            $row['NR'] = 'REQ';
            $row['(Child) sku'] = $pm->sku;

            // Merge API data into base row if exists
            if ($apiItem) {
                foreach ($apiItem as $k => $v) {
                    $row[$k] = $v;
                }
            }

            // Add AmazonDatasheet fields if available
            if ($amazonSheet) {
                $row['Sess30'] = $row['Sess30'] ?? $amazonSheet->sessions_l30;
            }

            $amazonView = $amazonDataViews[$sku] ?? null;

            if ($amazonView) {
                $jsonValues = json_decode($amazonView->values, true); 
                $row['NR'] = $jsonValues['NR'] ?? 'REQ'; 
            }

            // Add Shopify fields if available
            $row['INV'] = $shopify->inv ?? 0;

            $result[] = (object) $row;
        }

        // Apply the AmazonZero-specific filters
        $result = array_filter($result, function ($item) {
            $childSku = $item->{'(Child) sku'} ?? '';
            $inv = $item->INV ?? 0;
            $sess30 = $item->Sess30 ?? 1; // Default to 1 so items without Sess30 won't be filtered

            return
                stripos($childSku, 'PARENT') === false &&
                $inv > 0 &&
                $sess30 == 0;
        });

        return count($result);
    }

    public function getNrReqCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $reqCount = 0;
        $nrCount = 0;
        $listedCount = 0;
        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData->get($sku)?->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic
            $nrReq = $status['NR'] ?? (floatval($inv) > 0 ? 'REQ' : 'NR');
            if ($nrReq === 'REQ') {
                $reqCount++;
            } elseif ($nrReq === 'NR') {
                $nrCount++; 
            }

            // Listed/Pending logic
            $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            if ($listed === 'Listed') {
                $listedCount++;
            } elseif ($listed === 'Pending') {
                $pendingCount++;
            }
        }

        return [
            'NR'  => $nrCount,
            'REQ' => $reqCount,
            'Listed' => $listedCount,
            'Pending' => $pendingCount,
        ];
    }


    // public function getAmazonListingCounts()
    // {
    //     $productMasters = ProductMaster::whereNull('deleted_at')->get();
    //     $skus = $productMasters->pluck('sku')->unique()->toArray();

    //     $shopifyData = ShopifySku::mapByProductSkus($skus);
    //     $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');
    //     $sheetData = AmazonDataSheet::whereIn('sku', $skus)->get()->keyBy('sku'); // sessions_l30 here

    //     $listedCount = 0;
    //     $liveCount = 0;
    //     $zeroInvCount = 0;
    //     $zeroViewCount = 0;
    //     $nrCount       = 0;

    //     foreach ($productMasters as $item) {
    //         $sku = trim($item->sku);
    //         $inv = $shopifyData[$sku]->inv ?? 0;
    //         $isParent = stripos($sku, 'PARENT') !== false;

    //         // skip parent or invalid SKUs
    //         if ($isParent) continue;

    //         // --- Inventory check ---
    //         if (floatval($inv) <= 0) {
    //             $zeroInvCount++;
    //         }

    //         // --- Status from AmazonListingStatus ---
    //         $status = $statusData[$sku]->value ?? null;
    //         if (is_string($status)) {
    //             $status = json_decode($status, true);
    //         }

    //         $listed = $status['listed'] ?? null;
    //         $live   = $status['live'] ?? null;
    //         $nr   = $status['nr_req'] ?? null;

    //         if ($listed === 'Listed') {
    //             $listedCount++;
    //         }
    //         if ($live === 'Live') {
    //             $liveCount++;
    //         }
    //         if ($nr === 'NR') {
    //             $nrCount++;
    //         }

    //         // --- Zero view check from amazon_data_sheet.sessions_l30 ---
    //         $sessionsL30 = $sheetData[$sku]->sessions_l30 ?? 0;
    //         if (intval($sessionsL30) === 0) {
    //             $zeroViewCount++;
    //         }
    //     }

    //     return [
    //         'Req'        => $nrCount,
    //         'Listed'     => $listedCount,
    //         'Live'       => $liveCount,
    //         'ZeroInv'    => $zeroInvCount,
    //         'ZeroView'   => $zeroViewCount,
    //     ];
    // }
    

    // public function getZeroViewCounts()
    // {
    //     // 1. Reuse the same base data building as in getViewAmazonZeroData()
    //     $productMasters = ProductMaster::orderBy('parent', 'asc')
    //         ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
    //         ->orderBy('sku', 'asc')
    //         ->get();

    //     $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

    //     $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()->keyBy(function ($item) {
    //         return strtoupper($item->sku);
    //     });

    //     $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(function ($item) {
    //         return strtoupper($item->sku);
    //     });
    //     $shopifyData = ShopifySku::mapByProductSkus($skus);

    //     $parents = $productMasters->pluck('parent')->filter()->unique()->map('strtoupper')->values()->all();
    //     $jungleScoutData = JungleScoutProductData::whereIn('parent', $parents)
    //         ->get()
    //         ->groupBy(function ($item) {
    //             return strtoupper(trim($item->parent));
    //         });

    //     $percentage = Cache::remember('amazon_marketplace_percentage', now()->addDays(30), function () {
    //         return MarketplacePercentage::where('marketplace', 'Amazon')->value('percentage') ?? 100;
    //     });
    //     $percentage = $percentage / 100;

    //     $result = [];
    //     foreach ($productMasters as $pm) {
    //         $sku = strtoupper($pm->sku);
    //         $parent = $pm->parent;

    //         $amazonSheet = $amazonDatasheetsBySku[$sku] ?? null;
    //         $shopify = $shopifyData[$pm->sku] ?? null;

    //         $row = [];
    //         $row['Parent'] = $parent;
    //         $row['(Child) sku'] = $pm->sku;

    //         $dataView = $amazonDataViews[$sku] ?? null;
    //         $value = $dataView ? $dataView->value : [];
    //         if (!is_array($value)) {
    //             $value = is_string($value) ? json_decode($value, true) ?: [] : [];
    //         }
    //         $row['NR'] = isset($value['NR']) && in_array($value['NR'], ['REQ', 'NR']) ? $value['NR'] : 'REQ';

    //         if ($amazonSheet) {
    //             $row['Sess30'] = $amazonSheet->sessions_l30;
    //         }

    //         $row['INV'] = $shopify->inv ?? 0;

    //         $result[] = (object) $row;
    //     }

    //     // Apply AmazonZero filters
    //     $result = array_filter($result, function ($item) {
    //         $childSku = $item->{'(Child) sku'} ?? '';
    //         $inv = $item->INV ?? 0;
    //         $sess30 = $item->Sess30 ?? 1;

    //         return stripos($childSku, 'PARENT') === false &&
    //             $inv > 0 &&
    //             $sess30 == 0;
    //     });

    //     $result = array_values($result);

    //     // ✅ Count logic
    //     $collection = collect($result);

    //     $zeroViews = $collection->count(); // all zero view items
    //     $nrCount   = $collection->where('NR', 'NR')->count(); // those marked as NR
    //     $finalCount = $zeroViews - $nrCount;


    //     return [
    //         'zero_views' => $zeroViews,
    //         'nr_count'   => $nrCount,
    //         'finalCount' => $finalCount,
    //     ];
    // }


    public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($productMasters->pluck('sku')->filter()->unique()->values()->all());

        $amazonListingStatus = AmazonListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $amazonDataViews = AmazonDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        // Use groupBy to handle duplicate SKUs, then take the earliest record for each (lowest ID)
        $amazonMetrics = AmazonDatasheet::whereIn('sku', $skus)
            ->get()
            ->groupBy(fn($s) => strtoupper(trim($s->sku)))
            ->map(function ($group) {
                // Return the record with the lowest ID (earliest/original)
                return $group->sortBy('id')->first();
            });

        $listedCount = 0;
        $zeroInvOfListed = 0;
        $liveCount = 0;
        $zeroViewCount = 0;
        $listedAndLiveCount = 0; // Items that are both Listed AND Live (Listed but not Pending)

        foreach ($productMasters as $item) {
            $sku = strtoupper(trim($item->sku));
            $inv = $shopifyData->get($item->sku)?->inv ?? 0;

            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) continue;

            // --- Amazon Listing Status ---
            $status = $amazonListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $amazonDataViews[$sku]->value ?? null;
            if (is_string($dataView)) {
                $dataView = json_decode($dataView, true);
            }
            // $live = ($dataView['Live'] ?? false) === true ? 'Live' : null;
            $live = (!empty($dataView['Live']) && $dataView['Live'] === true) ? 'Live' : null;

            // --- Listed count ---
            if ($listed === 'Listed') {
                $listedCount++;
                if (floatval($inv) <= 0) {
                    $zeroInvOfListed++;
                }
            }

            // --- Live count ---
            if ($live === 'Live') {
                $liveCount++;
            }

            // --- Listed but not Pending count (Listed AND Live) ---
            if ($listed === 'Listed' && $live === 'Live') {
                $listedAndLiveCount++;
            }

            // --- Views / Zero-View logic ---
            $metricRecord = $amazonMetrics[$sku] ?? null;
            $views = null;

            if ($metricRecord) {
                // Direct field
                if (!empty($metricRecord->sessions_l30) || $metricRecord->sessions_l30 === "0" || $metricRecord->sessions_l30 === 0) {
                    $views = (int)$metricRecord->sessions_l30;
                }
                // Or inside JSON column `value`
                elseif (!empty($metricRecord->value)) {
                    $metricData = json_decode($metricRecord->value, true);
                    if (isset($metricData['sessions_l30'])) {
                        $views = (int)$metricData['sessions_l30'];
                    }
                }
            }

            // Normalize $inv to numeric
            $inv = floatval($inv);

            // Count as zero-view if views are exactly 0 and inv > 0
            if ($inv > 0 && $views === 0) {
                $zeroViewCount++;
            }
            // $metricRecord = $amazonMetrics[$sku] ?? null;
            // $views = null;

            // if ($metricRecord) {
            //     // Direct field (if column exists)
            //     if (!empty($metricRecord->sessions_l30)) {
            //         $views = $metricRecord->sessions_l30;
            //     }
            //     // Or inside JSON column `value`
            //     elseif (!empty($metricRecord->value)) {
            //         $metricData = json_decode($metricRecord->value, true);
            //         $views = $metricData['sessions_l30'] ?? null;
            //     }
            // }

            // if ($inv > 0 && $views !== null && intval($views) === 0) {
            //     $zeroViewCount++;
            // }
        }

        $livePending = $listedCount - $liveCount;
        $listedButNotPending = $listedAndLiveCount; // Items that are Listed AND Live (not pending anymore)
        $pendingCount = $livePending; // Items that are Listed but NOT Live yet (still pending)

        return [
            'live_pending' => $livePending,
            'zero_view' => $zeroViewCount,
            'listed_not_pending' => $listedButNotPending,
            'pending_count' => $pendingCount,
        ];
    }



}