<?php

namespace App\Http\Controllers;

use App\Models\AmazonDataView;
use App\Models\ChannelMasterCalculatedData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VolumePricingController extends Controller
{
    public function index()
    {
        return view('pricing-master.volume_pricing_tabulator');
    }

    private static function key(?string $sku): string
    {
        return strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $sku)));
    }

    /**
     * Rows for the volume pricing table: Parent, SKU, INV, OV L30 (Dil is computed in the browser),
     * plus what the SPRICE $ column needs (LP, Ship, Amazon Ads %, saved SPRICE $).
     */
    public function data(): JsonResponse
    {
        $productMasters = ProductMaster::query()
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
            ->orderBy('parent', 'asc')
            ->orderBy('sku', 'asc')
            ->get(['sku', 'parent', 'Values'])
            // DC (discontinued) SKUs are not part of volume pricing
            ->reject(fn ($pm) => strtoupper(trim(ProductMaster::statusValueFromValues($pm->Values ?? []))) === 'DC')
            ->values();

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyData = $skus === [] ? collect() : ShopifySku::mapByProductSkus($skus);

        // Same Amazon channel Ads% as /price-increase (used for NPFT / NROI)
        $amazonAds = 0.0;
        try {
            $ch = ChannelMasterCalculatedData::query()
                ->whereRaw('LOWER(TRIM(channel)) = ?', ['amazon'])
                ->first(['ads_percentage', 'tacos_percentage']);
            if ($ch) {
                if ($ch->ads_percentage !== null && $ch->ads_percentage !== '') {
                    $amazonAds = round((float) $ch->ads_percentage, 2);
                } elseif ($ch->tacos_percentage !== null && $ch->tacos_percentage !== '') {
                    $amazonAds = round((float) $ch->tacos_percentage, 2);
                }
            }
        } catch (\Throwable $e) {
            $amazonAds = 0.0;
        }

        // SPRICE $ box store shared with /price-increase (one row per SKU)
        $boxBySku = [];
        if (Schema::hasTable('price_increase_box_sprices')) {
            foreach (DB::table('price_increase_box_sprices')->where('sprice', '>', 0)->get(['sku', 'sprice']) as $r) {
                $boxBySku[self::key($r->sku)] = round((float) $r->sprice, 2);
            }
        }

        // Saved Amazon SPRICE: what the box shows for a SKU that never had its own box price
        $amazonSpriceBySku = [];
        if ($skus !== []) {
            try {
                foreach (AmazonDataView::query()->whereIn('sku', $skus)->get(['sku', 'value']) as $av) {
                    $val = is_array($av->value) ? $av->value : (json_decode((string) $av->value, true) ?: []);
                    $spr = $val['SPRICE'] ?? null;
                    if ($spr !== null && $spr !== '' && (float) $spr > 0) {
                        $amazonSpriceBySku[self::key($av->sku)] = round((float) $spr, 2);
                    }
                }
            } catch (\Throwable $e) {
                $amazonSpriceBySku = [];
            }
        }

        $rows = [];
        foreach ($productMasters as $pm) {
            $shopify = $shopifyData->get($pm->sku);
            $inv = (int) ($shopify->inv ?? 0);
            $ovL30 = (int) ($shopify->quantity ?? 0);

            $values = $pm->Values ?: [];
            $values = is_array($values) ? $values : (json_decode((string) $values, true) ?: []);
            $lp = 0.0;
            $ship = 0.0;
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                }
                if (strtolower((string) $k) === 'ship') {
                    $ship = (float) $v;
                }
            }

            $k = self::key($pm->sku);
            $rows[] = [
                'parent' => $pm->parent,
                'sku' => $pm->sku,
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                'lp' => $lp,
                'ship' => $ship,
                'amazon_ads' => $amazonAds,
                'amazon_margin' => 0.80,
                'box_sprice' => $boxBySku[$k] ?? null,
                'amazon_sprice' => $amazonSpriceBySku[$k] ?? null,
            ];
        }

        return response()->json(['data' => $rows]);
    }
}
