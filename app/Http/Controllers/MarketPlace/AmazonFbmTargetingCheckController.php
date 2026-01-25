<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonFbmTargetingCheck;
use App\Models\AmazonFbmTargetingCheckHistory;
use App\Models\AmazonSpCampaignReport;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AmazonFbmTargetingCheckController extends Controller
{
    /**
     * TARGET KW page
     */
    public function targetKw()
    {
        return view('market-places.targeting-check.target-check-kw', [
            'title' => 'Amz FBM Targeting Check - TARGET KW',
        ]);
    }

    /**
     * TARGET PT page
     */
    public function targetPt()
    {
        return view('market-places.targeting-check.target-check-pt', [
            'title' => 'Amz FBM Targeting Check - TARGET PT',
        ]);
    }

    /**
     * Fetch data for TARGET KW (KW campaigns: campaignName NOT LIKE %PT)
     */
    public function targetKwData(Request $request)
    {
        return $this->fetchTargetingCheckData('kw');
    }

    /**
     * Fetch data for TARGET PT (PT campaigns: campaignName LIKE % PT)
     */
    public function targetPtData(Request $request)
    {
        return $this->fetchTargetingCheckData('pt');
    }

    /**
     * Save targeting check (checked, campaign, issue, remark) and append to history
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'type' => 'required|in:kw,pt',
            'checked' => 'boolean',
            'campaign' => 'nullable|string|max:500',
            'issue' => 'nullable|string|max:500',
            'remark' => 'nullable|string',
        ]);

        $checked = $request->boolean('checked');

        $campaign = $request->input('campaign', '');
        $issue = $request->input('issue', '');
        $remark = $request->input('remark', '');

        $rec = AmazonFbmTargetingCheck::updateOrCreate(
            [
                'sku' => $validated['sku'],
                'type' => $validated['type'],
            ],
            [
                'checked' => $checked,
                'campaign' => $campaign,
                'issue' => $issue,
                'remark' => $remark,
                'user_id' => Auth::id(),
            ]
        );

        // Append to history
        AmazonFbmTargetingCheckHistory::create([
            'sku' => $validated['sku'],
            'type' => $validated['type'],
            'campaign' => $campaign,
            'issue' => $issue,
            'remark' => $remark,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);

        $userName = Auth::user() ? Auth::user()->name : null;

        return response()->json([
            'status' => 200,
            'message' => 'Saved successfully',
            'user' => $userName,
            'last_history_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get history for a sku+type
     */
    public function getHistory(Request $request)
    {
        $sku = $request->query('sku');
        $type = $request->query('type');
        if (!$sku || !in_array($type, ['kw', 'pt'])) {
            return response()->json(['status' => 400, 'data' => []]);
        }

        $rows = AmazonFbmTargetingCheckHistory::where('sku', $sku)
            ->where('type', $type)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($h) {
                return [
                    'campaign' => $h->campaign ?? '',
                    'issue' => $h->issue ?? '',
                    'remark' => $h->remark ?? '',
                    'user' => $h->user ? $h->user->name : '',
                    'created_at' => $h->created_at ? $h->created_at->format('Y-m-d H:i') : '',
                ];
            });

        return response()->json(['status' => 200, 'data' => $rows]);
    }

    /**
     * @param string $type 'kw' or 'pt'
     */
    protected function fetchTargetingCheckData(string $type): \Illuminate\Http\JsonResponse
    {
        $productMasters = ProductMaster::where('sku', 'NOT LIKE', 'PARENT %')
            ->orderBy('parent', 'asc')
            ->orderBy('sku', 'asc')
            ->get();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $amazonDatasheetsBySku = AmazonDatasheet::whereIn('sku', $skus)->get()->keyBy(fn ($i) => strtoupper($i->sku));
        $shopifyData = ShopifySku::whereIn('sku', $skus)->get()->keyBy('sku');

        // Prefer L30 then L90 (L30 is more commonly synced in this codebase)
        $dateRanges = ['L30', 'L90'];
        if ($type === 'kw') {
            $campaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', $dateRanges)
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $s) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $s . '%');
                    }
                })
                ->where('campaignName', 'NOT LIKE', '%PT')
                ->where('campaignName', 'NOT LIKE', '%PT.')
                ->get();
            $match = function ($item, $sku) {
                $cn = strtoupper(trim(rtrim($item->campaignName ?? '', '.')));
                $cs = strtoupper(trim(rtrim($sku, '.')));
                return $cn === $cs;
            };
        } else {
            $campaigns = AmazonSpCampaignReport::where('ad_type', 'SPONSORED_PRODUCTS')
                ->whereIn('report_date_range', $dateRanges)
                ->where(function ($q) use ($skus) {
                    foreach ($skus as $s) {
                        $q->orWhere('campaignName', 'LIKE', '%' . $s . '%');
                    }
                })
                ->where(function ($q) {
                    $q->where('campaignName', 'LIKE', '% PT')
                        ->orWhere('campaignName', 'LIKE', '% PT.');
                })
                ->where('campaignName', 'NOT LIKE', '%FBA PT')
                ->where('campaignName', 'NOT LIKE', '%FBA PT.')
                ->get();
            $match = function ($item, $sku) {
                $cn = trim(rtrim($item->campaignName ?? '', '.'));
                $cs = trim(rtrim($sku, '.'));
                $suffix = preg_replace('/\s+PT\.?$/i', '', $cn);
                return strtoupper($suffix) === strtoupper($cs);
            };
        }

        $targetingByKey = AmazonFbmTargetingCheck::where('type', $type)
            ->whereIn('sku', $skus)
            ->with('user:id,name')
            ->get()
            ->keyBy('sku');

        $historyCounts = AmazonFbmTargetingCheckHistory::where('type', $type)
            ->whereIn('sku', $skus)
            ->select('sku', DB::raw('count(*) as c'))
            ->groupBy('sku')
            ->pluck('c', 'sku');

        $lastHistoryAt = AmazonFbmTargetingCheckHistory::where('type', $type)
            ->whereIn('sku', $skus)
            ->selectRaw('sku, MAX(created_at) as last_at')
            ->groupBy('sku')
            ->pluck('last_at', 'sku');

        $result = [];
        foreach ($productMasters as $pm) {
            $sku = $pm->sku;
            $skuUpper = strtoupper($sku);
            $shopify = $shopifyData[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $l30 = (float) ($shopify->quantity ?? 0);
            $dil = $inv > 0 ? round(($l30 / $inv) * 100) : 0;

            $matched = $campaigns->first(fn ($item) => $match($item, $sku));
            $campaignName = $matched ? ($matched->campaignName ?? '') : '';
            $tc = $targetingByKey[$sku] ?? null;
            $campaignDisplay = ($tc && $tc->campaign) ? $tc->campaign : $campaignName;

            // Shopify se image; na mile to ProductMaster (main_image, image1, Values.image_path)
            $shopifyImg = optional($shopify)->image_src;
            $imagePath = ($shopifyImg !== null && trim((string) $shopifyImg) !== '')
                ? $shopifyImg
                : ($pm->main_image ?? $pm->image1 ?? data_get($pm->Values ?? [], 'image_path'));

            $row = [
                'parent' => $pm->parent,
                'sku' => $sku,
                'image_path' => $imagePath,
                'INV' => $inv,
                'L30' => $l30,
                'dil' => $dil,
                'checked' => $tc ? (bool) $tc->checked : false,
                'campaign' => $campaignDisplay,
                'issue' => $tc->issue ?? '',
                'remark' => $tc->remark ?? '',
                'user' => $tc && $tc->user ? $tc->user->name : '',
                'history_count' => (int) ($historyCounts[$sku] ?? 0),
                'last_history_at' => isset($lastHistoryAt[$sku]) ? $lastHistoryAt[$sku] : null,
            ];
            $result[] = $row;
        }

        return response()->json(['status' => 200, 'data' => $result]);
    }
}
