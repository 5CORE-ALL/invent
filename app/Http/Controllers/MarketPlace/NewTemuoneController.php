<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\Ebay2Metric;
use App\Models\EbayMetric;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\TemuAdsApiReport;
use App\Models\TemuDataView;
use App\Models\TemuListingStatus;
use App\Models\TemuLmp;
use App\Models\TemuMetric;
use App\Models\TemuViewData;
use App\Services\DilRuleSpriceApplyService;
use App\Services\LmpSkuGroupService;
use App\Services\TemuShopifySalesService;
use App\Support\AmazonDilGroiRule;
use App\Support\ProductMasterTemuShip;
use App\Support\TemuGoodsIdHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NewTemuoneController extends Controller
{
    public function __construct(protected LmpSkuGroupService $lmpSkuGroupService)
    {
    }

    public function index()
    {
        return view('market-places.new_temuone_tabulator_view');
    }

    public function dataJson()
    {
        try {
            $productMasters = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->get();

            $productMasters = $productMasters->filter(function ($item) {
                return stripos((string) $item->sku, 'PARENT') === false;
            })->values();

            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
            $shopifyData = ShopifySku::mapByProductSkus($skus);

            try {
                $this->lmpSkuGroupService->prepareForSkus($skus);
            } catch (\Throwable $e) {
                Log::warning('New Temu One LmpSkuGroupService prepareForSkus failed: '.$e->getMessage());
            }

            $listingStatusData = TemuListingStatus::whereIn('sku', $skus)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [strtolower((string) $item->sku) => $item];
                });

            $normalizeSku = static function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);

                return $sku;
            };

            $temuMetricsBySku = [];
            foreach (TemuMetric::query()->get(['sku', 'base_price', 'goods_id', 'product_clicks_l30']) as $metric) {
                $key = $normalizeSku($metric->sku);
                if ($key === '') {
                    continue;
                }
                $price = (float) ($metric->base_price ?? 0);
                if (!isset($temuMetricsBySku[$key]) || ($price > 0 && (float) ($temuMetricsBySku[$key]->base_price ?? 0) <= 0)) {
                    $temuMetricsBySku[$key] = $metric;
                }
            }

            // Views — same as /temu1-data: temu_view_data.product_clicks by goods_id,
            // else temu_metrics.product_clicks_l30 + Ads API clicks when the sheet has no row.
            $viewDataByGoodsId = Schema::hasTable('temu_view_data')
                ? TemuViewData::selectRaw('goods_id, SUM(product_clicks) as product_clicks')
                    ->groupBy('goods_id')
                    ->get()
                    ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                : collect();

            $adsViewsByGoodsId = collect();
            if (Schema::hasTable('temu_ads_api_reports')) {
                $adsViewsByGoodsId = TemuAdsApiReport::query()
                    ->liveAds()
                    ->inLatestWindow('L30')
                    ->whereNotNull('goods_id')
                    ->get(['goods_id', 'clicks'])
                    ->filter(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                    ->keyBy(fn ($r) => TemuGoodsIdHelper::normalizeKey($r->goods_id))
                    ->map(fn ($r) => (int) ($r->clicks ?? 0));
            }

            // L30 qty from temu_orders — same source/window as /temu-tabulator (Qty Purchased).
            $l30ByNormalizedSku = [];
            $noSpaceToNormalized = [];
            foreach ($skus as $sku) {
                $n = $normalizeSku($sku);
                if ($n === '') {
                    continue;
                }
                $l30ByNormalizedSku[$n] = 0;
                $noSpace = str_replace(' ', '', $n);
                if ($noSpace !== '') {
                    $noSpaceToNormalized[$noSpace] = $n;
                }
            }

            [$l30Start, $l30End] = TemuShopifySalesService::channelMasterL30Window();
            foreach (TemuShopifySalesService::getOrdersTableRows($l30Start, $l30End) as $orderRow) {
                $raw = trim((string) ($orderRow['contribution_sku'] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $n = $normalizeSku($raw);
                $qty = (int) ($orderRow['quantity_purchased'] ?? 0);
                if (isset($l30ByNormalizedSku[$n])) {
                    $l30ByNormalizedSku[$n] += $qty;
                } else {
                    $nNoSpace = str_replace(' ', '', $n);
                    if (isset($noSpaceToNormalized[$nNoSpace])) {
                        $l30ByNormalizedSku[$noSpaceToNormalized[$nNoSpace]] += $qty;
                    }
                }
            }

            $lookupStdPrc = $this->buildStdPriceLookup($skus, $normalizeSku);
            $amazonPriceBySku = [];
            $ebayPriceBySku = [];
            $ebay2PriceBySku = [];
            $temuDataViewBySku = [];
            foreach (array_chunk($skus, 500) as $skuChunk) {
                $amazonPriceBySku += $this->buildPriceLookup(
                    AmazonDatasheet::whereIn('sku', $skuChunk)->get(['sku', 'price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->price ?? null) ? (float) $row->price : null
                );
                $ebayPriceBySku += $this->buildPriceLookup(
                    EbayMetric::whereIn('sku', $skuChunk)->get(['sku', 'ebay_price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->ebay_price ?? null) ? (float) $row->ebay_price : null
                );
                $ebay2PriceBySku += $this->buildPriceLookup(
                    Ebay2Metric::whereIn('sku', $skuChunk)->get(['sku', 'ebay_price']),
                    $normalizeSku,
                    static fn ($row) => is_numeric($row->ebay_price ?? null) ? (float) $row->ebay_price : null
                );
                if (Schema::hasTable('temu_data_view')) {
                    foreach (TemuDataView::whereIn('sku', $skuChunk)->get(['sku', 'value']) as $dv) {
                        $val = is_array($dv->value)
                            ? $dv->value
                            : (json_decode((string) ($dv->value ?? ''), true) ?: []);
                        $temuDataViewBySku[(string) $dv->sku] = is_array($val) ? $val : [];
                    }
                }
            }

            $dilStore = DilRuleSpriceApplyService::for('temu')->loadDilGroiStore();
            $dilRules = $dilStore['rules'] ?? [];
            $cvrAdj = $dilStore['cvr_adj'] ?? null;
            $percentage = TemuShopifySalesService::temuMarginDecimal();

            $temuLmpByNormalizedSku = [];
            if (Schema::hasTable('temu_lmp')) {
                foreach (TemuLmp::all() as $row) {
                    $nk = $normalizeSku($row->sku);
                    if ($nk !== '' && !isset($temuLmpByNormalizedSku[$nk])) {
                        $temuLmpByNormalizedSku[$nk] = $row;
                    }
                }
            }

            $result = [];

            foreach ($productMasters as $pm) {
                $sku = (string) $pm->sku;
                $shopify = $shopifyData->get($pm->sku);
                $listingStatus = $listingStatusData[strtolower($sku)] ?? null;
                $temuMetric = $temuMetricsBySku[$normalizeSku($sku)] ?? null;

                $inv = (float) ($shopify->inv ?? 0);
                $ovL30 = (float) ($shopify->quantity ?? 0);
                $temuL30 = (int) ($l30ByNormalizedSku[$normalizeSku($sku)] ?? 0);
                $goodsIdKey = $temuMetric
                    ? TemuGoodsIdHelper::normalizeKey($temuMetric->goods_id ?? null)
                    : null;
                $viewDataItem = $goodsIdKey ? $viewDataByGoodsId->get($goodsIdKey) : null;
                $oClicks = $viewDataItem ? (int) $viewDataItem->product_clicks : 0;
                $productClicks = $viewDataItem
                    ? $oClicks
                    : (int) ($temuMetric?->product_clicks_l30 ?? 0);
                $adsViews = $goodsIdKey ? (int) ($adsViewsByGoodsId->get($goodsIdKey) ?? 0) : 0;
                $views = $oClicks > 0 ? $oClicks : ($productClicks + $adsViews);
                $cvrPercent = $views > 0 ? round(($temuL30 / $views) * 100, 2) : 0.0;

                $buyerLink = '';
                $sellerLink = '';
                if ($listingStatus) {
                    $statusValue = is_array($listingStatus->value)
                        ? $listingStatus->value
                        : (json_decode((string) $listingStatus->value, true) ?? []);
                    if (!empty($statusValue['buyer_link'])) {
                        $buyerLink = $statusValue['buyer_link'];
                    }
                    if (!empty($statusValue['seller_link'])) {
                        $sellerLink = $statusValue['seller_link'];
                    }
                }

                $dilPct = $inv == 0.0 ? 0.0 : round(($ovL30 / $inv) * 100, 2);
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);
                $lp = $this->productMasterLp($values, $pm);
                $temuShip = ProductMasterTemuShip::forPricing($values, $pm);
                $basePrice = $temuMetric ? (float) ($temuMetric->base_price ?? 0) : 0.0;
                $rPrice = TemuShopifySalesService::computeRPrice($basePrice);
                $tPrice = $basePrice > 0
                    ? round(TemuShopifySalesService::computeFullTemuPrice($basePrice), 2)
                    : 0.0;
                $amazonPrice = $this->lookupMappedPrice($amazonPriceBySku, $sku, $normalizeSku);
                $ebayPrice = $this->lookupMappedPrice($ebayPriceBySku, $sku, $normalizeSku);
                $ebay2Price = $this->lookupMappedPrice($ebay2PriceBySku, $sku, $normalizeSku);
                $savedView = $temuDataViewBySku[$sku] ?? [];
                $storedSprice = isset($savedView['sprice']) && is_numeric($savedView['sprice'])
                    ? (float) $savedView['sprice']
                    : null;

                $stdPrc = $lookupStdPrc($sku);
                if ($stdPrc === null) {
                    foreach ($this->lmpSkuGroupService->groupContaining($sku) as $linkedSku) {
                        $stdPrc = $lookupStdPrc($linkedSku);
                        if ($stdPrc !== null) {
                            break;
                        }
                    }
                }

                $lmpInfo = $this->resolveTemu2Lmp($sku, $temuLmpByNormalizedSku, $normalizeSku);
                $sprcDil = $this->computeTemu1TableSprcDil(
                    $inv,
                    $lp,
                    $temuShip,
                    $dilPct,
                    $cvrPercent,
                    $dilRules,
                    is_array($cvrAdj) ? $cvrAdj : null
                );
                $spriceCap = $this->capTemu2Sprice(
                    $sprcDil,
                    $this->temuEbayRefPrice($ebayPrice, $ebay2Price),
                    $amazonPrice,
                    $lmpInfo['raw']
                );

                $result[] = [
                    'Parent' => $pm->parent,
                    'sku' => $sku,
                    '(Child) sku' => $sku,
                    'is_parent' => false,
                    'INV' => $inv,
                    'inventory' => $inv,
                    'L30' => $ovL30,
                    'ovl30' => $ovL30,
                    'temu_l30' => $temuL30,
                    'views' => $views,
                    'cvr_percent' => $cvrPercent,
                    'cvr_30' => $cvrPercent,
                    'Dil%' => $dilPct,
                    'dil_percent' => $dilPct,
                    'lp' => $lp,
                    'LP_productmaster' => $lp,
                    'temu_ship' => $temuShip,
                    'Ship_productmaster' => $temuShip,
                    'percentage' => $percentage,
                    'base_price' => $basePrice,
                    'r_price' => $rPrice,
                    't_price' => $tPrice,
                    'temu_price' => $tPrice,
                    'STANDARD_PRICE' => $stdPrc,
                    'a_price' => $amazonPrice,
                    'e_price' => $ebayPrice,
                    'e2_price' => $ebay2Price,
                    'lmp' => $lmpInfo['recovery'],
                    'lmp_raw' => $lmpInfo['raw'],
                    'lmp_link' => $lmpInfo['link'],
                    'lmp_entries' => $lmpInfo['entries'],
                    'sprc_dil' => $sprcDil > 0 ? $sprcDil : null,
                    'sprice' => $spriceCap['sprice'] > 0 ? $spriceCap['sprice'] : null,
                    'SPRICE' => $spriceCap['sprice'] > 0 ? $spriceCap['sprice'] : null,
                    'stored_sprice' => $storedSprice,
                    'sprice_labels' => $spriceCap['labels'],
                    'sprice_lmp_alert' => $spriceCap['lmpAlert'],
                    'B Link' => $buyerLink,
                    'S Link' => $sellerLink,
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching New Temu One data: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to fetch data'], 500);
        }
    }

    public function saveLinks(Request $request)
    {
        $sku = trim((string) $request->input('sku'));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = TemuListingStatus::where('sku', $sku)->first();
        $rawValue = $status ? $status->getRawOriginal('value') : null;
        $existing = $status ? $status->value : [];
        if (!is_array($existing)) {
            $existing = is_string($rawValue) && $rawValue !== ''
                ? (json_decode($rawValue, true) ?: [])
                : [];
        }

        $existing['buyer_link'] = $buyerLink;
        $existing['seller_link'] = $sellerLink;

        TemuListingStatus::updateOrCreate(
            ['sku' => $sku],
            ['value' => $existing]
        );

        return response()->json([
            'success' => true,
            'buyer_link' => $buyerLink,
            'seller_link' => $sellerLink,
        ]);
    }

    /**
     * Sprc Dil using the /temu1-data Dil vs GROI table, with Temu 2 0-sold
     * behavior (Dil-matching slab, not min GROI).
     */
    private function computeTemu1TableSprcDil(
        float $inv,
        float $lp,
        float $ship,
        float $dil,
        float $cvr,
        array $dilRules,
        ?array $cvrAdj
    ): float {
        if (! ($inv > 0) || ! ($lp > 0)) {
            return 0.0;
        }
        $rule = AmazonDilGroiRule::match($dil, $dilRules);
        if ($rule === null) {
            return 0.0;
        }
        $groi = AmazonDilGroiRule::adjustGroiForCvrLevel((float) $rule['groi'], $cvr, $cvrAdj);
        $raw = TemuShopifySalesService::spriceFromTargetSgroi($lp, $ship, (float) $groi, 0.0);
        if (! is_finite($raw) || $raw < 0.01) {
            return 0.0;
        }

        return round($raw, 2);
    }

    /**
     * Same S PRC cap as /temu2-decrease: lowest of eBay / Amazon / LMP when cheaper.
     *
     * @return array{sprice: float, labels: list<string>, lmpAlert: bool}
     */
    private function capTemu2Sprice(float $discounted, float $ebay, float $amz, $lmp): array
    {
        $sprice = $discounted > 0 ? $discounted : 0.0;
        if (! ($sprice > 0)) {
            return ['sprice' => 0.0, 'labels' => [], 'lmpAlert' => false];
        }
        $ebay = $ebay > 0 ? round($ebay, 2) : 0.0;
        $amz = $amz > 0 ? round($amz, 2) : 0.0;
        $lmp = (is_numeric($lmp) && (float) $lmp > 0) ? round((float) $lmp, 2) : 0.0;
        if ($ebay > 0 && $sprice > $ebay) {
            $sprice = round($ebay, 2);
        }
        if ($amz > 0 && $sprice > $amz) {
            $sprice = round($amz, 2);
        }
        if ($lmp > 0 && $sprice > $lmp) {
            $sprice = round($lmp, 2);
        }

        $labels = [];
        if ($ebay > 0 && round($sprice, 2) === $ebay && $discounted > $ebay) {
            $labels[] = 'EB';
        }
        if ($amz > 0 && round($sprice, 2) === $amz && $discounted > $amz) {
            $labels[] = 'Amz';
        }

        return [
            'sprice' => round($sprice, 2),
            'labels' => $labels,
            'lmpAlert' => $lmp > 0 && round($sprice, 2) === $lmp && $discounted > $lmp,
        ];
    }

    private function temuEbayRefPrice(float $ebay, float $ebay2): float
    {
        if ($ebay > 0 && $ebay2 > 0) {
            return min($ebay, $ebay2);
        }

        return $ebay > 0 ? $ebay : $ebay2;
    }

    private function productMasterLp(array $values, ProductMaster $pm): float
    {
        foreach ($values as $k => $v) {
            if (strtolower((string) $k) === 'lp' && is_numeric($v)) {
                return (float) $v;
            }
        }
        if (isset($pm->lp) && is_numeric($pm->lp)) {
            return (float) $pm->lp;
        }
        if (isset($pm->LP) && is_numeric($pm->LP)) {
            return (float) $pm->LP;
        }

        return 0.0;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return array<string, float>
     */
    private function buildPriceLookup(iterable $rows, callable $normalizeSku, callable $valueFn): array
    {
        $out = [];
        foreach ($rows as $row) {
            $val = $valueFn($row);
            if (! is_numeric($val) || (float) $val <= 0) {
                continue;
            }
            $rounded = round((float) $val, 2);
            $raw = trim((string) ($row->sku ?? ''));
            if ($raw === '') {
                continue;
            }
            $out[$raw] = $rounded;
            $out[strtoupper($raw)] = $rounded;
            $norm = $normalizeSku($raw);
            if ($norm !== '') {
                $out[$norm] = $rounded;
            }
        }

        return $out;
    }

    private function lookupMappedPrice(array $map, string $sku, callable $normalizeSku): float
    {
        $raw = trim($sku);
        if ($raw === '') {
            return 0.0;
        }

        return (float) ($map[$raw] ?? $map[strtoupper($raw)] ?? $map[$normalizeSku($raw)] ?? 0);
    }

    /**
     * Same Std Prc source as /temu2-decrease: amazon_data_view.STANDARD_PRICE.
     */
    private function buildStdPriceLookup(array $skus, callable $normalizeSku): \Closure
    {
        $stdLookupSkus = $skus;
        foreach ($skus as $pageSku) {
            foreach ($this->lmpSkuGroupService->groupContaining((string) $pageSku) as $memberSku) {
                $stdLookupSkus[] = $memberSku;
            }
        }
        $stdLookupSkus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $stdLookupSkus
        ))));

        $amazonStandardPrices = [];
        $indexAmazonStdPrc = function ($skuKey, $std) use (&$amazonStandardPrices, $normalizeSku) {
            if (! is_numeric($std) || (float) $std <= 0) {
                return;
            }
            $rounded = round((float) $std, 2);
            $raw = trim((string) $skuKey);
            if ($raw === '') {
                return;
            }
            $amazonStandardPrices[$raw] = $rounded;
            $amazonStandardPrices[strtoupper($raw)] = $rounded;
            $norm = $normalizeSku($raw);
            if ($norm !== '') {
                $amazonStandardPrices[$norm] = $rounded;
            }
        };

        foreach (array_chunk($stdLookupSkus, 500) as $chunk) {
            foreach (AmazonDataView::whereIn('sku', $chunk)->get(['sku', 'value']) as $adv) {
                $val = is_array($adv->value)
                    ? $adv->value
                    : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
            }
        }

        $normalizedStdSet = [];
        foreach ($stdLookupSkus as $s) {
            $n = $normalizeSku($s);
            if ($n !== '') {
                $normalizedStdSet[$n] = true;
            }
        }
        if ($normalizedStdSet !== []) {
            $matchedAmazonSkus = [];
            foreach (AmazonDataView::query()->pluck('sku') as $asku) {
                $n = $normalizeSku($asku);
                if ($n !== '' && isset($normalizedStdSet[$n])) {
                    $matchedAmazonSkus[] = $asku;
                }
            }
            foreach (array_chunk(array_values(array_unique($matchedAmazonSkus)), 500) as $chunk) {
                foreach (AmazonDataView::whereIn('sku', $chunk)->get(['sku', 'value']) as $adv) {
                    $n = $normalizeSku($adv->sku);
                    $val = is_array($adv->value)
                        ? $adv->value
                        : (json_decode((string) ($adv->value ?? ''), true) ?: []);
                    $indexAmazonStdPrc($adv->sku, $val['STANDARD_PRICE'] ?? null);
                    $indexAmazonStdPrc($n, $val['STANDARD_PRICE'] ?? null);
                }
            }
        }

        return function ($candidate) use ($amazonStandardPrices, $normalizeSku) {
            $raw = trim((string) $candidate);
            if ($raw === '') {
                return null;
            }

            return $amazonStandardPrices[$raw]
                ?? $amazonStandardPrices[strtoupper($raw)]
                ?? $amazonStandardPrices[$normalizeSku($raw)]
                ?? null;
        };
    }

    /**
     * Same LMP as /temu2-decrease: lowest Price+Delivery from temu_lmp (Sku Link group).
     *
     * @param  array<string, TemuLmp>  $temuLmpByNormalizedSku
     * @return array{raw: float|null, recovery: float|null, link: string|null, entries: list<array>}
     */
    private function resolveTemu2Lmp(string $sku, array $temuLmpByNormalizedSku, callable $normalizeSku): array
    {
        $linkedLmpSkus = $this->lmpSkuGroupService->groupContaining($sku);
        if ($linkedLmpSkus === []) {
            $linkedLmpSkus = [$sku];
        }

        $lmpEntries = [];
        foreach ($linkedLmpSkus as $linkedSku) {
            $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($linkedSku)] ?? null;
            if (! $temuLmpRow) {
                continue;
            }
            foreach ($this->extractTemuLmpEntries($temuLmpRow) as $e) {
                if (! is_array($e)) {
                    continue;
                }
                $e['source_sku'] = (string) ($temuLmpRow->sku ?? $linkedSku);
                $lmpEntries[] = $e;
            }
        }
        $lmpEntries = $this->dedupeTemuLmpEntries($lmpEntries);
        $temuLmpRow = $temuLmpByNormalizedSku[$normalizeSku($sku)] ?? null;

        $activeEntries = array_values(array_filter($lmpEntries, fn ($e) => empty($e['ignored'])));
        $prices = [];
        foreach ($activeEntries as $e) {
            $eff = $this->temuLmpEntryEffectivePrice($e);
            if ($eff !== null) {
                $prices[] = $eff;
            }
        }
        $lmpRaw = count($prices) > 0 ? min($prices) : null;
        $lmpLink = null;
        if ($lmpRaw !== null) {
            foreach ($activeEntries as $e) {
                $eff = $this->temuLmpEntryEffectivePrice($e);
                if ($eff !== null && round($eff, 2) === round((float) $lmpRaw, 2)) {
                    $lmpLink = $e['link'] ?? null;
                    break;
                }
            }
        }
        if ($lmpRaw === null && $lmpEntries === [] && $temuLmpRow) {
            $legacy = $temuLmpRow->lmp;
            $lmpRaw = ($legacy !== null && $legacy !== '' && is_numeric($legacy)) ? (float) $legacy : null;
            $lmpLink = $temuLmpRow->lmp_link;
        }

        return [
            'raw' => $lmpRaw,
            'recovery' => $this->temuLmpRecoveryPrice($lmpRaw),
            'link' => $lmpLink,
            'entries' => $lmpEntries,
        ];
    }

    private function extractTemuLmpEntries(?TemuLmp $temuLmpRow): array
    {
        if (! $temuLmpRow) {
            return [];
        }

        $entries = $temuLmpRow->lmp_entries;
        if (is_array($entries)) {
            return array_values($entries);
        }

        $lmpEntries = [];
        if ($temuLmpRow->lmp !== null || $temuLmpRow->lmp_link) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp, 'link' => $temuLmpRow->lmp_link];
        }
        if ($temuLmpRow->lmp_2 !== null || $temuLmpRow->lmp_link_2) {
            $lmpEntries[] = ['price' => $temuLmpRow->lmp_2, 'link' => $temuLmpRow->lmp_link_2];
        }

        return $lmpEntries;
    }

    private function temuLmpEntryEffectivePrice(?array $entry): ?float
    {
        if (! is_array($entry)) {
            return null;
        }
        $price = $entry['price'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0) && $p !== 0.0) {
            return null;
        }
        $delivery = $entry['delivery'] ?? 0;
        $d = (is_numeric($delivery) && (float) $delivery > 0) ? (float) $delivery : 0.0;
        if ($d <= 0 && $p < 27) {
            $d = 2.99;
        }

        return round($p + $d, 2);
    }

    private function temuLmpRecoveryPrice($price): ?float
    {
        if ($price === null || $price === '' || ! is_numeric($price)) {
            return null;
        }
        $p = (float) $price;
        if (! ($p > 0)) {
            return null;
        }
        if ($p <= 27) {
            return round(($p * 0.85) + 2.99, 2);
        }

        return round($p * 0.85, 2);
    }

    private function dedupeTemuLmpEntries(array $entries): array
    {
        $seen = [];
        $out = [];

        foreach ($entries as $entry) {
            $price = $entry['price'] ?? null;
            $link = strtoupper(trim((string) ($entry['link'] ?? '')));
            $key = (string) $price.'|'.$link;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $entry;
        }

        usort($out, function ($a, $b) {
            $pa = ($a['price'] ?? null) !== null && $a['price'] !== '' ? (float) $a['price'] : PHP_FLOAT_MAX;
            $pb = ($b['price'] ?? null) !== null && $b['price'] !== '' ? (float) $b['price'] : PHP_FLOAT_MAX;

            return $pa <=> $pb;
        });

        return $out;
    }
}
