<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuDataView;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\MarketplacePercentage;
use App\Models\TemuListingStatus;
use App\Models\TemuMetric;
use Illuminate\Support\Facades\Cache;

class TemuZeroController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
public function temuZeroView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });

        return view('market-places.temuZeroView', [
            'mode' => $mode,
            'demo' => $demo,
            'percentage' => $percentage
        ]);
    }
    /**
     * ✅ Get only SKUs where temu_metric.product_clicks_l30 = 0 and shopify_sku.inv > 0
     */
    public function getViewTemuZeroData(Request $request)
    {
        // Get percentage from cache or database
        $percentage = Cache::remember('temu_marketplace_percentage', now()->addDays(30), function () {
            $marketplaceData = MarketplacePercentage::where('marketplace', 'Temu')->first();
            return $marketplaceData ? $marketplaceData->percentage : 100;
        });
        $percentageValue = $percentage / 100;

        // Fetch all ProductMaster records
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        // Fetch related data
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuMetrics = TemuMetric::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $processedData = [];
        $slNo = 1;

        foreach ($productMasters as $productMaster) {
            $sku = strtoupper(trim($productMaster->sku));
            $isParent = stripos($sku, 'PARENT') !== false;
            if ($isParent) continue;

            // Get inventory from ShopifySku
            $inv = $shopifyData[$sku]->inv ?? 0;
            $quantity = $shopifyData[$sku]->quantity ?? 0;

            // Skip items with no inventory
            if ($inv <= 0) {
                continue;
            }

            // Get product_clicks_l30 and product_impressions_l30 from TemuMetric
            $clicks = null;
            $impressions = null;
            $metric = $temuMetrics[$sku] ?? null;
            if ($metric) {
                $clicks = $metric->product_clicks_l30 ?? null;
                $impressions = $metric->product_impressions_l30 ?? null;
                if ($clicks === null && !empty($metric->value)) {
                    $metricValue = json_decode($metric->value, true);
                    $clicks = $metricValue['product_clicks_l30'] ?? null;
                    $impressions = $metricValue['product_impressions_l30'] ?? null;
                }
            }

            // Skip items with clicks > 0 (only show zero-view items)
            if (!is_null($clicks) && $clicks > 0) {
                continue;
            }

            // Fetch NR and A-Z Reason fields
            $dataView = $temuDataViews[$sku]->value ?? [];
            if (is_string($dataView)) {
                $dataView = json_decode($dataView, true);
            }

            $values = $productMaster->Values ?? [];

            $processedItem = [
                'Parent' => $productMaster->parent ?? null,
                'SL No.' => $slNo++,
                'Sku' => $sku,
                'INV' => $inv,
                'L30' => $quantity, // Use Shopify quantity for L30
                'product_clicks_l30' => $clicks ?? 0,
                'product_impressions_l30' => $impressions ?? 0,
                'LP' => $values['lp'] ?? 0,
                'Ship' => $values['ship'] ?? 0,
                'COGS' => $values['cogs'] ?? 0,
                'NR' => $dataView['NR'] ?? 'REQ',
                'A_Z_Reason' => $dataView['A_Z_Reason'] ?? null,
                'A_Z_ActionRequired' => $dataView['A_Z_ActionRequired'] ?? null,
                'A_Z_ActionTaken' => $dataView['A_Z_ActionTaken'] ?? null,
                'percentage' => $percentageValue,
            ];

            $processedData[] = $processedItem;
        }

        return response()->json([
            'message' => 'Data fetched successfully',
            'data' => array_values($processedData),
            'status' => 200
        ]);
    }

    public function updateReasonAction(Request $request)
    {
        $sku = $request->input('sku');
        $reason = $request->input('reason');
        $actionRequired = $request->input('action_required');
        $actionTaken = $request->input('action_taken');

        if (!$sku) {
            return response()->json([
                'status' => 400,
                'message' => 'SKU is required.'
            ], 400);
        }

        $row = TemuDataView::firstOrCreate(['sku' => $sku]);
        $value = $row->value ?? [];
        $value['A_Z_Reason'] = $reason;
        $value['A_Z_ActionRequired'] = $actionRequired;
        $value['A_Z_ActionTaken'] = $actionTaken;
        $row->value = $value;
        $row->save();

        return response()->json([
            'status' => 200,
            'message' => 'Reason and actions updated successfully.'
        ]);
    }

    /**
     * Optional: Quick summary counts for dashboard
     */
   public function getLivePendingAndZeroViewCounts()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();

        // Normalize SKUs (avoid case/space mismatch)
        $skus = $productMasters->pluck('sku')->map(fn($s) => strtoupper(trim($s)))->unique()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuListingStatus = TemuListingStatus::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuDataViews = TemuDataView::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $temuMetrics = TemuMetric::whereIn('sku', $skus)->get()
            ->keyBy(fn($s) => strtoupper(trim($s->sku)));

        $listedCount = 0;
        $zeroInvOfListed = 0;
        $liveCount = 0; 
        $zeroViewCount = 0;

        foreach ($productMasters as $item) {
            $sku = strtoupper(trim($item->sku));
            $inv = $shopifyData[$sku]->inv ?? 0;

            // Skip parent SKUs
            if (stripos($sku, 'PARENT') !== false) continue;

            // --- Amazon Listing Status ---
            $status = $temuListingStatus[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // $listed = $status['listed'] ?? (floatval($inv) > 0 ? 'Pending' : 'Listed');
            $listed = $status['listed'] ?? null;

            // --- Amazon Live Status ---
            $dataView = $temuDataViews[$sku]->value ?? null;
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


            // --- Views / Zero-View logic ---
            $metricRecord = $temuMetrics[$sku] ?? null;
            $views = null;


            if ($metricRecord) {
                // Direct field
                if (!empty($metricRecord->product_clicks_l30) || $metricRecord->product_clicks_l30 === "0" || $metricRecord->product_clicks_l30 === 0) {
                    $views = (int)$metricRecord->product_clicks_l30;
                }
                // Or inside JSON column value
                elseif (!empty($metricRecord->value)) {
                    $metricData = json_decode($metricRecord->value, true);
                    if (isset($metricData['product_clicks_l30'])) {
                        $views = (int)$metricData['product_clicks_l30'];
                    }
                }
            }

            // Normalize $inv to numeric
            $inv = floatval($inv);

            // Count as zero-view if views are exactly 0 or null (no data) and inv > 0
            if ($inv > 0 && ($views === 0 || $views === null)) {
                $zeroViewCount++;
            }

        }

        $livePending = $listedCount - $liveCount;

        return [
            'live_pending' => $livePending,
            'zero_view' => $zeroViewCount,
        ];
    }



    
}
