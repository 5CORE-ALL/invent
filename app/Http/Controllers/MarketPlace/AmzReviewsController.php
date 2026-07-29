<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonProductReview;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmzReviewsController extends Controller
{
    public function index()
    {
        return view('market-places.amz_reviews');
    }

    /**
     * Tabulator JSON — Product Master rows + amazon_product_reviews (rating / count).
     */
    public function data(Request $request)
    {
        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image']);

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);

        $asinBySku = [];
        $amzL30BySku = [];
        if ($skus !== []) {
            foreach (array_chunk($skus, 500) as $skuChunk) {
                AmazonDatasheet::query()
                    ->whereIn('sku', $skuChunk)
                    ->select('id', 'sku', 'asin', 'units_ordered_l30')
                    ->orderBy('id')
                    ->get()
                    ->each(function ($row) use (&$asinBySku, &$amzL30BySku) {
                        $key = strtoupper(trim((string) $row->sku));
                        if ($key === '') {
                            return;
                        }
                        if (! isset($asinBySku[$key]) && ! empty($row->asin)) {
                            $asinBySku[$key] = (string) $row->asin;
                        }
                        if (! isset($amzL30BySku[$key])) {
                            $amzL30BySku[$key] = (float) ($row->units_ordered_l30 ?? 0);
                        }
                    });
            }
        }

        $amzReviewsBySku = [];
        if (Schema::hasTable('amazon_product_reviews')) {
            try {
                AmazonProductReview::query()
                    ->where(function ($q) {
                        $q->where('channel', 'Amazon')->orWhereNull('channel')->orWhere('channel', '');
                    })
                    ->whereNotNull('sku')
                    ->get(['sku', 'product_rating', 'review_count', 'asin', 'source', 'fetched_at'])
                    ->each(function ($rr) use (&$amzReviewsBySku) {
                        $k = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $rr->sku)));
                        if ($k === '') {
                            return;
                        }
                        $amzReviewsBySku[$k] = $rr;
                    });
            } catch (\Throwable $e) {
                Log::warning('AmzReviews: failed loading amazon_product_reviews', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $data = [];
        $stopAdsCount = 0;
        foreach ($productMasters as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $skuKey = strtoupper($sku);
            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            $ovL30 = (float) ($shopify->quantity ?? 0);
            $amzL30 = (float) ($amzL30BySku[$skuKey] ?? 0);
            $dilPct = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;
            $amzRev = $amzReviewsBySku[$skuKey] ?? null;
            $avgRating = $amzRev && $amzRev->product_rating !== null
                ? (float) $amzRev->product_rating
                : null;
            $stopAds = $avgRating !== null && $avgRating > 0 && $avgRating < 3;
            if ($stopAds) {
                $stopAdsCount++;
            }

            $data[] = [
                'parent' => trim((string) ($pm->parent ?? '')),
                'sku' => $sku,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil_pct' => $dilPct,
                'amz_l30' => $amzL30,
                'image' => $pm->main_image ?: null,
                'asin' => $amzRev?->asin ?? ($asinBySku[$skuKey] ?? null),
                'amz_avg_rating' => $avgRating,
                'amz_review_count' => $amzRev ? (int) ($amzRev->review_count ?? 0) : null,
                'amz_reviews_source' => $amzRev?->source,
                'stop_ads' => $stopAds,
            ];
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'sku_count' => count($data),
                'stop_ads_count' => $stopAdsCount,
                'reviews_cached' => count($amzReviewsBySku),
                'refreshed_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}
