<?php

namespace App\Http\Controllers\Channels;

use App\Console\Commands\TiktokSheetData;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAliexpressController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAmazonController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAppscenicController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingAutoDSController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBestbuyUSAController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingBusiness5CoreController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingDobaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayThreeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingEbayTwoController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFaireController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBMarketplaceController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingFBShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingInstagramShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMacysController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWoShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingMercariWShipController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2BController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingNeweggB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingOfferupController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPlsController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingPoshmarkController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingReverbController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSheinController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyB2CController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingShopifyWholesaleController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSpocketController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSWGearExchangeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingSynceeController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTemuController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiendamiaController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingTiktokShopController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWalmartController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingWayfairController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingYamibuyController;
use App\Http\Controllers\MarketPlace\ListingMarketPlace\ListingZendropController;
use App\Http\Controllers\MarketPlace\EbayThreeController as MarketPlaceEbayThreeController;
use App\Http\Controllers\MarketPlace\OverallAmazonController;
use App\Models\AliExpressSheetData;
use App\Models\AliexpressDailyData;
use App\Models\AliexpressListingStatus;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderItem;
use App\Models\AmazonSpCampaignReport;
use App\Models\ApiCentralWalmartApiData;
use App\Models\ApiCentralWalmartMetric;
use App\Models\BestbuyUsaProduct;
use App\Models\BusinessFiveCoreSheetdata;
use App\Models\ChannelMaster;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\DobaSheetdata;
use App\Models\Ebay2Metric;
use App\Models\Ebay2Order;
use App\Models\EbayTwoListingStatus;
use App\Models\Ebay3Metric;
use App\Models\EbayThreeListingStatus;
use App\Models\EbayGeneralReport;
use App\Models\EbayMetric;
use App\Models\EbayOrder;
use App\Models\EbayOrderItem;
use App\Models\EbayPriorityReport;
use App\Models\FaireListingStatus;
use App\Models\FbMarketplaceSheetdata;
use App\Models\FBMarketplaceListingStatus;
use App\Models\FbShopSheetdata;
use App\Models\FBShopListingStatus;
use App\Models\Business5CoreListingStatus;
use App\Models\InstagramShopSheetdata;
use App\Models\InstagramShopListingStatus;
use App\Models\MacyProduct;
use App\Models\MacysListingStatus;
use App\Models\MarketplaceDailyMetric;
use App\Models\MarketplacePercentage;
use App\Models\MercariWoShipSheetdata;
use App\Models\MercariWShipSheetdata;
use App\Models\MercariWShipListingStatus;
use App\Models\MercariWoShipListingStatus;
use App\Http\Controllers\MarketPlace\AliexpressController;
use App\Http\Controllers\MarketPlace\FaireController;
use App\Http\Controllers\MarketPlace\SheinController;
use App\Http\Controllers\MarketPlace\PlsController;
use App\Http\Controllers\MarketPlace\WayfairController;
use App\Models\PlsListingStatus;
use App\Models\ProductMaster;
use App\Models\ProductStockMapping;
use App\Models\ReverbProduct;
use App\Models\ReverbListingStatus;
use App\Models\SheinSheetData;
use App\Models\SheinDailyData;
use App\Models\SheinListingStatus;
use App\Models\ShopifySku;
use App\Models\TemuDailyData;
use App\Models\TemuDailyDataL60;
use App\Models\Temu2DailyData;
use App\Models\Temu2DailyDataL60;
use App\Models\TemuMetric;
use App\Models\TemuProductSheet;
use App\Models\TiendamiaProduct;
use App\Models\TiendamiaListingStatus;
use App\Models\TiktokCampaignReport;
use App\Models\TiktokSheet;
use App\Models\TiktokSalesTwo;
use App\Models\TiktokShopListingStatus;
use App\Models\DepopSheetData;
use App\Models\DepopSalesData;
use App\Models\TopDawgSheetdata;
use App\Models\WayfairDailyData;
use App\Models\WayfairListingStatus;
use App\Models\WalmartListingStatus;
use App\Models\WalmartMetrics;
use App\Models\AmazonListingStatus;
use App\Models\EbayListingStatus;
use App\Models\TemuListingStatus;
use App\Services\EbayChannelMetricsService;
use App\Services\SheinShopifySalesService;
use App\Services\TemuShopifySalesService;
use App\Models\BestbuyUSAListingStatus;
use App\Models\AmazonChannelSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\FlareClient\Api;

class ChannelMasterController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    private function defaultMissingLinkForChannel(string $channel): ?string
    {
        $paths = [
            'PLS' => '/pls-pricing',
            'Wayfair' => '/wayfair-pricing',
            'Aliexpress' => '/aliexpress-pricing',
            'Shein' => '/shein-pricing',
            'Faire' => '/faire-pricing',
            'Reverb' => '/reverb-pricing',
            'TopDawg' => '/topdawg-pricing',
        ];

        $path = $paths[trim($channel)] ?? null;

        return $path ? (str_starts_with($path, '/') ? $path : url($path)) : null;
    }

    /**
     * Fill missing_link on channel rows when not set in channel_master / calculated data.
     */
    private function applyDefaultMissingLinks(array $rows): array
    {
        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            if ($name === '' || ! empty($row['missing_link'])) {
                continue;
            }
            $row['missing_link'] = $this->defaultMissingLinkForChannel($name);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get Map, Miss, NMap, and total_views from amazon_channel_summary_data table
     * This is a generic helper that works for all channels
     */
    private function getMapAndMissCounts($channelName)
    {
        $summaryData = AmazonChannelSummary::where('channel', strtolower($channelName))
            ->latest('snapshot_date')
            ->first();
        
        $mapCount = 0;
        $missCount = 0;
        $nmapCount = 0;
        $totalViews = 0;
        
        if ($summaryData && $summaryData->summary_data) {
            // Check for 'map_count', 'mapping_count', or 'mapped_count' field names
            $mapCount = $summaryData->summary_data['map_count'] 
                     ?? $summaryData->summary_data['mapping_count']
                     ?? $summaryData->summary_data['mapped_count']
                     ?? 0;
            
            // Check for both 'missing_count' and 'missing_amazon_count' field names
            $missCount = $summaryData->summary_data['missing_count'] 
                      ?? $summaryData->summary_data['missing_amazon_count']
                      ?? 0;
            
            // Get nmap_count directly from summary data (check multiple possible field names)
            $nmapCount = $summaryData->summary_data['nmap_count']
                      ?? $summaryData->summary_data['not_mapped_count']
                      ?? $summaryData->summary_data['not_map_count']
                      ?? $summaryData->summary_data['nmapping_count']
                      ?? $summaryData->summary_data['inv_stock_count']  // eBay 3
                      ?? $summaryData->summary_data['inv_r_stock_count']  // Reverb
                      ?? $summaryData->summary_data['inv_tt_stock_count']  // TikTok
                      ?? 0;
            
            // Total views from same summary (e.g. Amazon saves total_views in summary_data)
            $totalViews = (int) ($summaryData->summary_data['total_views'] ?? 0);
        }
        
        return [
            'map' => $mapCount, 
            'miss' => $missCount,
            'nmap' => $nmapCount,
            'total_views' => $totalViews,
        ];
    }

    /**
     * Live Amazon Map/Miss/NMap for the all-marketplace-master, computed exactly like
     * OverallAmazonController::saveDailySummaryIfNeeded (the amazon-tabulator-view backend),
     * so the master page never shows stale 0s from amazon_channel_summary_data.
     *
     *   Valid rows: non-parent, INV > 0, NR !== 'NR'.
     *   Miss  (Missing L): REQ and (not in amazon_datsheets OR price <= 0), non-FBA.
     *   Map / NMap:        REQ, listed (price > 0) AND INV_AMZ > 0 (both sides have stock, like /map-issues);
     *                      N Map when |INV - INV_AMZ| > 3 (when 3% of INV < 3) else rounded % > 3, otherwise Map.
     *                      A listed row with 0 Amazon stock is neither Map nor N Map. FBA excluded from Miss + NMap.
     *   Listing match uses AmazonDatasheet::normalizeSkuForLookup (spaces removed + PCS/PC fold) and
     *   NR resolves from amazon_data_view NRL then amazon_listing_statuses.nr_req (default REQ).
     */
    private function getAmazonLiveMapMissNMapCounts(): array
    {
        try {
            $productMasters = ProductMaster::whereNull('deleted_at')->get();
            $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();

            $shopifyData = ShopifySku::mapByProductSkus($skus);
            $nrValues = AmazonDataView::whereIn('sku', $skus)->pluck('value', 'sku');
            $listingStatus = AmazonListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');
            $stockMappings = ProductStockMapping::whereIn('sku', $skus)->get()->keyBy('sku');

            // Datasheets keyed by the shared Amazon normalization (spaces removed + PCS/PC fold).
            $datasheetsByNorm = AmazonDatasheet::query()->get()->keyBy(function ($item) {
                return AmazonDatasheet::normalizeSkuForLookup($item->sku ?? '');
            });

            $map = 0;
            $miss = 0;
            $nmap = 0;
            $views = 0;

            foreach ($productMasters as $pm) {
                $sku = (string) $pm->sku;
                if (stripos($sku, 'PARENT') === 0 || stripos($sku, 'PARENT ') !== false) {
                    continue;
                }

                $inv = (float) ($shopifyData[$sku]->inv ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $nr = $this->resolveAmazonChannelNrReq($nrValues, $listingStatus, $sku);
                if ($nr === 'NR') {
                    continue; // RL filter: exclude NR
                }

                $sheet = $datasheetsByNorm[AmazonDatasheet::normalizeSkuForLookup($sku)] ?? null;
                $isMissingAmazon = ($sheet === null);
                $price = (float) ($sheet->price ?? 0);

                // Views: sessions over all valid rows (same scope as the page summary).
                $views += (float) ($sheet->sessions_l30 ?? 0);

                $childSku = strtoupper($sku);
                $parentSku = strtoupper((string) ($pm->parent ?? ''));
                $isFbaRow = ((int) ($pm->fba ?? 0) === 1)
                    || str_contains($childSku, 'FBA')
                    || str_contains($parentSku, 'FBA');

                // Map/Miss/NMap only for REQ rows with INV > 0.
                if ($nr !== 'REQ') {
                    continue;
                }

                if (($isMissingAmazon || $price <= 0) && ! $isFbaRow) {
                    $miss++;
                } elseif (! $isMissingAmazon && $price > 0) {
                    $invAmzRaw = $stockMappings[$sku]->inventory_amazon ?? 0;
                    $invAmz = is_numeric($invAmzRaw) ? (float) $invAmzRaw : 0.0;
                    // Map / N Map only when BOTH sides have stock (same as /map-issues);
                    // a listed row with 0 Amazon stock is neither Map nor N Map.
                    if ($invAmz > 0) {
                        $diff = abs($inv - $invAmz);
                        // /map-issues tolerance: < 3 units when 3% of INV < 3, else rounded % > 3.
                        if ($inv * 0.03 < 3) {
                            $isNotMap = $diff > 3;
                        } else {
                            $isNotMap = round(($diff / $inv) * 100) > 3;
                        }
                        if ($isNotMap) {
                            if (! $isFbaRow) {
                                $nmap++;
                            }
                        } else {
                            $map++;
                        }
                    }
                }
            }

            return [
                'map' => $map,
                'miss' => $miss,
                'nmap' => $nmap,
                'total_views' => (int) $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('Amazon live map/miss fallback used: ' . $e->getMessage());
            return $this->getMapAndMissCounts('amazon');
        }
    }

    /**
     * Resolve Amazon REQ/NR the same way OverallAmazonController does: amazon_data_view
     * NRL flag first ('NRL' => NR, 'REQ' => REQ), then amazon_listing_statuses.nr_req,
     * default REQ.
     */
    private function resolveAmazonChannelNrReq($nrValues, $listingStatus, string $sku): string
    {
        if (isset($nrValues[$sku])) {
            $raw = $nrValues[$sku];
            if (! is_array($raw)) {
                $raw = json_decode((string) $raw, true) ?: [];
            }
            $nrl = is_array($raw) ? ($raw['NRL'] ?? null) : null;
            if ($nrl === 'NRL') {
                return 'NR';
            }
            if ($nrl === 'REQ') {
                return 'REQ';
            }
        }

        $ls = $listingStatus[$sku] ?? null;
        if ($ls && $ls->value) {
            $val = is_array($ls->value) ? $ls->value : (json_decode((string) $ls->value, true) ?: []);
            $nrReq = strtoupper((string) ($val['nr_req'] ?? ''));
            if ($nrReq === 'NR' || $nrReq === 'NRL') {
                return 'NR';
            }
            if ($nrReq === 'REQ') {
                return 'REQ';
            }
        }

        return 'REQ';
    }

    /**
     * Map tolerance — matches amazon-tabulator-view / temu2_decrease:
     * |INV − stock| <= 3 units OR <= 3% of INV. INV <= 0 always counts as mapped.
     */
    private function temuInvWithinMapTolerance(float $inv, float $stock): bool
    {
        if ($inv <= 0) {
            return true;
        }
        $diff = abs($inv - $stock);
        if ($diff <= 3 + 1e-9) {
            return true;
        }

        return $diff <= ($inv * 0.03) + 1e-9;
    }

    /**
     * Map / Miss (Missing L) / NMap for Temu & Temu 2 — same rules as temu_decrease / temu2_decrease
     * badges and /map-issues: Missing L = missing='M', INV>0, REQ; Map/N Map = listed, REQ, price>0,
     * INV>0 and temu_stock>0, tolerance < 3 units when 3% of INV < 3, else rounded % > 3.
     */
    private function getTemuLiveMapMissNMapFromDecreaseData(bool $isTemu2 = false): array
    {
        try {
            $req = Request::create($isTemu2 ? '/temu2-decrease-data' : '/temu-decrease-data', 'GET');
            $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
            $response = $isTemu2
                ? $temuCtrl->getTemu2DecreaseData($req)
                : $temuCtrl->getTemuDecreaseData($req);
            $responseData = json_decode($response->getContent(), true);
            if (! is_array($responseData)) {
                $ch = $isTemu2 ? 'temu2' : 'temu';

                return $this->getMapAndMissCounts($ch);
            }
            $data = $responseData['data'] ?? [];
            if (! is_array($data)) {
                $ch = $isTemu2 ? 'temu2' : 'temu';

                return $this->getMapAndMissCounts($ch);
            }

            $missingC = 0;
            $mapC = 0;
            $nmapC = 0;
            $totalViews = 0;

            foreach ($data as $row) {
                if (empty($row['sku'] ?? null)) {
                    continue;
                }
                $inventory = (float) ($row['inventory'] ?? 0);
                $temuStock = (float) ($row['temu_stock'] ?? 0);
                $missing = (string) ($row['missing'] ?? '');
                $temuPrice = (float) ($row['temu_price'] ?? 0);
                $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? 'REQ')));
                $totalViews += (int) ($row['product_clicks'] ?? 0);

                // Missing L: not listed (missing='M'), INV > 0, REQ only — same rule as /map-issues.
                if ($missing === 'M' && $inventory > 0 && $nrReq === 'REQ') {
                    $missingC++;
                }

                // Map / Missing M (N Map): listed, REQ, price > 0, both sides with stock — same gate as
                // /map-issues. Tolerance: < 3 units when 3% of INV < 3, else rounded % > 3.
                if ($inventory > 0 && $nrReq === 'REQ' && $missing !== 'M' && $temuPrice > 0 && $temuStock > 0) {
                    $diff = abs($inventory - $temuStock);
                    if ($inventory * 0.03 < 3) {
                        $isNotMap = $diff > 3;
                    } else {
                        $isNotMap = round(($diff / $inventory) * 100) > 3;
                    }
                    if ($isNotMap) {
                        $nmapC++;
                    } else {
                        $mapC++;
                    }
                }
            }

            return [
                'map' => $mapC,
                'miss' => $missingC,
                'nmap' => $nmapC,
                'total_views' => $totalViews,
            ];
        } catch (\Throwable $e) {
            Log::warning('Temu live map/miss/nmap fallback: '.$e->getMessage());
            $ch = $isTemu2 ? 'temu2' : 'temu';

            return $this->getMapAndMissCounts($ch);
        }
    }

    /**
     * L30 sales summary — Temu from shopify_order_items (/shopify-orders); Temu 2 from tabulator.
     *
     * @return array{total_orders: int, total_quantity: int, total_revenue: float}|null
     */
    private function getTemuLiveSalesSummaryFromTabulator(bool $isTemu2 = false): ?array
    {
        if (! $isTemu2) {
            try {
                $start = Carbon::now()->subDays(30)->startOfDay();
                $end = Carbon::now()->endOfDay();
                $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);

                return [
                    'total_orders' => $m['orders'],
                    'total_quantity' => $m['qty'],
                    'total_revenue' => $m['sales'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Temu orders live sales summary failed: '.$e->getMessage());

                return null;
            }
        }

        try {
            $req = Request::create($isTemu2 ? '/temu2-decrease-data' : '/temu-decrease-data', 'GET');
            $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
            $response = $isTemu2
                ? $temuCtrl->getTemu2DecreaseData($req)
                : $temuCtrl->getTemuDecreaseData($req);
            $payload = json_decode($response->getContent(), true);
            $summary = is_array($payload) ? ($payload['sales_summary'] ?? null) : null;
            if (! is_array($summary)) {
                return null;
            }

            return [
                'total_orders' => (int) ($summary['total_orders'] ?? 0),
                'total_quantity' => (int) ($summary['total_quantity'] ?? 0),
                'total_revenue' => (float) ($summary['total_revenue'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('Temu live sales summary fallback: '.$e->getMessage(), ['temu2' => $isTemu2]);

            return null;
        }
    }

    /**
     * Sum L60 rows the same way /temu-tabulator's L60 Sales badge does
     * (getDailyDataL60 rows + hasSales gate + fbPrice).
     *
     * @return array{total_orders: int, total_revenue: float}
     */
    private function summarizeTemuTabulatorL60Rows(array $rows): array
    {
        $totalRevenue = 0.0;
        $orderIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parent = (string) ($row['Parent'] ?? '');
            if ($parent !== '' && stripos($parent, 'PARENT') === 0) {
                continue;
            }
            $sku = trim((string) ($row['contribution_sku'] ?? ''));
            $orderId = trim((string) ($row['order_id'] ?? ''));
            if ($sku === '' || $orderId === '') {
                continue;
            }
            $qty = (int) ($row['quantity_purchased'] ?? 0);
            $base = (float) ($row['base_price_total'] ?? 0);
            if ($qty <= 0 || $base <= 0) {
                continue;
            }
            $lineTotal = $base * $qty;
            $fbPrice = $lineTotal < 27 ? $base + 2.99 : $base;
            $totalRevenue += $fbPrice * $qty;
            $orderIds[$orderId] = true;
        }

        return [
            'total_orders' => count($orderIds),
            'total_revenue' => round($totalRevenue, 2),
        ];
    }

    /**
     * L60 sales from uploaded temu_daily_data_l60 / temu2_daily_data_l60 — same as tabulator L60 badge.
     * Returns null when the L60 table is empty (caller should fall back to historical snapshot).
     *
     * @return array{total_orders: int, total_revenue: float}|null
     */
    private function getTemuLiveL60SalesSummary(bool $isTemu2 = false): ?array
    {
        try {
            $table = $isTemu2 ? 'temu2_daily_data_l60' : 'temu_daily_data_l60';
            if (! Schema::hasTable($table)) {
                return null;
            }

            $modelClass = $isTemu2 ? Temu2DailyDataL60::class : TemuDailyDataL60::class;
            if ($modelClass::count() === 0) {
                return null;
            }

            if (! $isTemu2) {
                $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
                $response = $temuCtrl->getDailyDataL60(Request::create('/temu/daily-data-l60', 'GET'));
                $rows = json_decode($response->getContent(), true);

                return is_array($rows) ? $this->summarizeTemuTabulatorL60Rows($rows) : null;
            }

            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);

                return $sku;
            };

            $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
                ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                ->orderBy('sku', 'asc')
                ->pluck('sku')
                ->filter(fn ($sku) => stripos($sku, 'PARENT') === false)
                ->unique()
                ->values()
                ->all();

            $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
                return [$normalizeSku($s) => true];
            })->all();

            $allowedRawSkus = Temu2DailyDataL60::select('contribution_sku')->distinct()
                ->get()
                ->filter(function ($r) use ($normalizeSku, $normalizedPmSet) {
                    return isset($normalizedPmSet[$normalizeSku($r->contribution_sku ?? '')]);
                })
                ->pluck('contribution_sku')
                ->unique()
                ->values()
                ->all();

            $pmByNormalized = ProductMaster::whereIn('sku', $productMasterSkus)->get()
                ->keyBy(fn ($pm) => $normalizeSku($pm->sku ?? ''));

            $rows = [];
            foreach (Temu2DailyDataL60::whereIn('contribution_sku', $allowedRawSkus)->get() as $item) {
                $pm = $pmByNormalized[$normalizeSku($item->contribution_sku ?? '')] ?? null;
                $rows[] = [
                    'Parent' => $pm ? ($pm->parent ?? '') : '',
                    'contribution_sku' => $item->contribution_sku,
                    'order_id' => $item->order_id,
                    'quantity_purchased' => $item->quantity_purchased,
                    'base_price_total' => $item->base_price_total,
                ];
            }

            return $this->summarizeTemuTabulatorL60Rows($rows);
        } catch (\Throwable $e) {
            Log::warning('Temu live L60 sales summary fallback: '.$e->getMessage(), ['temu2' => $isTemu2]);

            return null;
        }
    }

    /**
     * Resolve L60 sales/orders for Temu channels: uploaded L60 table first, then historical snapshot.
     *
     * @return array{sales: float, orders: int}
     */
    private function resolveTemuL60SalesAndOrders(bool $isTemu2): array
    {
        if (! $isTemu2) {
            try {
                [$start, $end] = TemuShopifySalesService::channelMasterL60Window();
                $m = TemuShopifySalesService::computeMetricsFromOrders($start, $end);

                return [
                    'sales' => (float) $m['sales'],
                    'orders' => (int) $m['orders'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Temu orders L60 failed: '.$e->getMessage());

                return ['sales' => 0.0, 'orders' => 0];
            }
        }

        $channelKey = 'temu2';
        $l60Sales = 0.0;
        $l60Orders = 0;

        $liveL60 = $this->getTemuLiveL60SalesSummary($isTemu2);
        if ($liveL60) {
            return [
                'sales' => (float) ($liveL60['total_revenue'] ?? 0),
                'orders' => (int) ($liveL60['total_orders'] ?? 0),
            ];
        }

        $derivedL60 = $this->deriveTemuL60FromHistoricalL30($channelKey);
        if ($derivedL60) {
            $l60Sales = (float) $derivedL60['sales'];
            $l60Orders = (int) $derivedL60['orders'];
        }

        $legacyTable = $isTemu2 ? 'temu2_daily_data_l60' : 'temu_daily_data_l60';
        if ($l60Sales <= 0 && Schema::hasTable($legacyTable)) {
            $l60Data = DB::table($legacyTable)
                ->select('order_id', 'base_price_total', 'quantity_purchased', 'contribution_sku')
                ->get();

            $uniqueOrders = [];
            foreach ($l60Data as $row) {
                $sku = trim((string) ($row->contribution_sku ?? ''));
                $orderId = trim((string) ($row->order_id ?? ''));
                if ($sku === '' || $orderId === '') {
                    continue;
                }
                if (! in_array($orderId, $uniqueOrders, true)) {
                    $uniqueOrders[] = $orderId;
                    $l60Orders++;
                }

                $basePrice = (float) ($row->base_price_total ?? 0);
                $quantity = (int) ($row->quantity_purchased ?? 0);
                if ($quantity <= 0 || $basePrice <= 0) {
                    continue;
                }
                $total = $basePrice * $quantity;
                $fbPrice = $total < 27 ? $basePrice + 2.99 : $basePrice;
                $l60Sales += $fbPrice * $quantity;
            }
        }

        return [
            'sales' => $l60Sales,
            'orders' => $l60Orders,
        ];
    }

    /**
     * Keep cached all-marketplace-master Temu / Temu 2 rows aligned with tabulator sales badges.
     */
    private function overlayLiveTemuMetricsOnChannelRows(array $rows): array
    {
        $liveByChannel = [
            'Temu' => fn () => $this->getTemuLiveSalesSummaryFromTabulator(false),
            'Temu 2' => fn () => $this->getTemuLiveSalesSummaryFromTabulator(true),
        ];

        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            if ($name === '' || ! isset($liveByChannel[$name])) {
                continue;
            }

            $isTemu2 = $name === 'Temu 2';
            $liveSales = $liveByChannel[$name]();
            $liveL60 = $this->resolveTemuL60SalesAndOrders($isTemu2);
            $l60Sales = (float) $liveL60['sales'];

            if ($liveSales) {
                $l30Sales = (float) $liveSales['total_revenue'];
                $row['L30 Sales'] = (int) round($l30Sales);
                $row['L30 Orders'] = (int) $liveSales['total_orders'];
                $row['Qty'] = (int) $liveSales['total_quantity'];
            }

            $row['L-60 Sales'] = (int) round($l60Sales);
            $row['L60 Orders'] = (int) $liveL60['orders'];
            if ($l60Sales > 0 && $liveSales) {
                $row['Growth'] = round(((($liveSales['total_revenue'] ?? 0) - $l60Sales) / $l60Sales) * 100, 2).'%';
            } elseif ($l60Sales > 0) {
                $l30Cached = (float) preg_replace('/[^0-9.-]/', '', (string) ($row['L30 Sales'] ?? 0));
                $row['Growth'] = round((($l30Cached - $l60Sales) / $l60Sales) * 100, 2).'%';
            }

            $ySales = $this->computeTemuYSalesLikeAmazon($isTemu2);
            if ($ySales !== null) {
                $row['Y Sales'] = $ySales;
            }
            $l7Sales = $this->computeTemuL7SalesLikeAmazon($isTemu2);
            if ($l7Sales !== null) {
                $row['L7 Sales'] = $l7Sales;
            }

            $mapMiss = $this->getTemuLiveMapMissNMapFromDecreaseData($isTemu2);
            $row['Map'] = $mapMiss['map'];
            $row['Miss'] = $mapMiss['miss'];
            $row['NMap'] = $mapMiss['nmap'];
            if (array_key_exists('total_views', $mapMiss)) {
                $row['Total Views'] = $mapMiss['total_views'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Overlay live eBay 1/2/3 L30 (marketplace_daily_metrics) + L60 (orders / ebay3 dates)
     * so cached channel_master_calculated_data stays fast but sales match latest fetch + metrics sync.
     */
    private function overlayLiveEbayMetricsOnChannelRows(array $rows): array
    {
        $displayToWhich = [
            'eBay' => 1,
            'EbayTwo' => 2,
            'EbayThree' => 3,
        ];

        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            if ($name === '' || ! isset($displayToWhich[$name])) {
                continue;
            }

            $which = $displayToWhich[$name];
            $live = EbayChannelMetricsService::liveChannelSummary($which);
            if ($live === null) {
                continue;
            }

            $l30Sales = (float) $live['l30_sales'];
            $l60Sales = (float) $live['l60_sales'];

            $row['L30 Sales'] = (int) round($l30Sales);
            $row['L30 Orders'] = (int) $live['l30_orders'];
            $row['Qty'] = (int) $live['qty'];
            $row['L-60 Sales'] = (int) round($l60Sales);
            $row['L60 Orders'] = (int) $live['l60_orders'];

            if ($l60Sales > 0) {
                $row['Growth'] = round((($l30Sales - $l60Sales) / $l60Sales) * 100, 2).'%';
            }

            if ($l60Sales > 0 && isset($live['l60_profit'])) {
                $row['gprofitL60'] = round(($live['l60_profit'] / $l60Sales) * 100, 2).'%';
            }

            if (! empty($live['l60_cogs']) && isset($live['l60_profit'])) {
                $row['G RoiL60'] = round(($live['l60_profit'] / $live['l60_cogs']) * 100, 2);
            }

            $ySales = $this->computeEbayYSalesLikeAmazon($which);
            if ($ySales !== null) {
                $row['Y Sales'] = $ySales;
            }

            $l7Sales = $this->computeEbayL7SalesLikeAmazon($which);
            if ($l7Sales !== null) {
                $row['L7 Sales'] = $l7Sales;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Live L30/L60 from shopify_order_items — same windows as /pp-sales-stats and getPurchasingPowerChannelData().
     *
     * @return array{l30_sales: float, l30_orders: int, qty: int, l60_sales: float, l60_orders: int, pft: float, cogs: float, l60_pft: float, l60_cogs: float}|null
     */
    private function getPurchasingPowerLiveMetricsSummary(): ?array
    {
        try {
            $pst = 'America/Los_Angeles';
            $todayPst = Carbon::now($pst);
            $l30Start = $todayPst->copy()->subDays(29)->startOfDay();
            $l30End = $todayPst->copy()->endOfDay();
            $l60Start = $todayPst->copy()->subDays(59)->startOfDay();
            $l60End = $todayPst->copy()->subDays(30)->endOfDay();

            $l30 = $this->computePurchasingPowerMetricsFromShopify($l30Start, $l30End);
            $l60 = $this->computePurchasingPowerMetricsFromShopify($l60Start, $l60End);

            return [
                'l30_sales' => $l30['sales'],
                'l30_orders' => $l30['orders'],
                'qty' => $l30['qty'],
                'l60_sales' => $l60['sales'],
                'l60_orders' => $l60['orders'],
                'pft' => $l30['pft'],
                'cogs' => $l30['cogs'],
                'l60_pft' => $l60['pft'],
                'l60_cogs' => $l60['cogs'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power live metrics summary failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Overlay live Purchasing Power L30/L60 from Shopify so /all-marketplace-master matches /purchasing-power-sales
     * even when channel_master_calculated_data was built before the latest Shopify sync.
     */
    private function overlayLivePurchasingPowerMetricsOnChannelRows(array $rows): array
    {
        $live = $this->getPurchasingPowerLiveMetricsSummary();
        if ($live === null) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            if ($name !== 'Purchasing Power') {
                continue;
            }

            $l30Sales = (float) $live['l30_sales'];
            $l60Sales = (float) $live['l60_sales'];

            $row['L30 Sales'] = (int) round($l30Sales);
            $row['L30 Orders'] = (int) $live['l30_orders'];
            $row['Qty'] = (int) $live['qty'];
            $row['L-60 Sales'] = (int) round($l60Sales);
            $row['L60 Orders'] = (int) $live['l60_orders'];

            if ($l60Sales > 0) {
                $row['Growth'] = round((($l30Sales - $l60Sales) / $l60Sales) * 100, 2).'%';
            }

            $row['Total PFT'] = round($live['pft'], 2);
            $row['cogs'] = round($live['cogs'], 2);

            if ($l30Sales > 0) {
                $gProfitPct = round(($live['pft'] / $l30Sales) * 100, 2);
                $row['Gprofit%'] = $gProfitPct.'%';
                $row['N PFT'] = $gProfitPct.'%';
            }

            if ($live['cogs'] > 0) {
                $gRoi = round(($live['pft'] / $live['cogs']) * 100, 2);
                $row['G Roi'] = $gRoi;
                $row['N ROI'] = $gRoi;
            }

            if ($l60Sales > 0) {
                $row['gprofitL60'] = round(($live['l60_pft'] / $l60Sales) * 100, 2).'%';
            }

            if ($live['l60_cogs'] > 0) {
                $row['G RoiL60'] = round(($live['l60_pft'] / $live['l60_cogs']) * 100, 2);
            }

            $ySales = $this->computePurchasingPowerYSalesLikeAmazon();
            if ($ySales !== null) {
                $row['Y Sales'] = $ySales;
            }

            $l7Sales = $this->computePurchasingPowerL7SalesLikeAmazon();
            if ($l7Sales !== null) {
                $row['L7 Sales'] = $l7Sales;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Apply the same shopify_raw_orders exclusions as the /shopify page (ShopifyRawDataController):
     * skips rows whose source_name/tags match a known marketplace and SKUs containing "XYZ".
     *
     * Note: we deliberately do NOT apply the page's "Hide Unknown" toggle (rows with empty tags).
     * Those rows are legitimate Shopify direct sales that simply have no source tag, so they belong
     * in the Active Channel Sales total. The /shopify page card matches this overlay when the user
     * toggles "Show Unknown" on; with the default "Hide Unknown" toggle the page card will be lower.
     */
    private function applyShopifyDirectOrderExclusions($query)
    {
        foreach (\App\Http\Controllers\ShopifyRawDataController::EXCLUDE_SOURCES as $term) {
            $query->whereRaw('LOWER(COALESCE(source_name,"")) NOT LIKE ?', ['%' . strtolower($term) . '%'])
                  ->whereRaw('LOWER(COALESCE(tags,"")) NOT LIKE ?',        ['%' . strtolower($term) . '%']);
        }

        $query->where(function ($q) {
            $q->whereNull('sku')->orWhere('sku', 'NOT LIKE', '%XYZ%');
        });

        return $query;
    }

    /**
     * Sum net_sales (and order/qty counts) from shopify_raw_orders for a given window.
     * Mirrors the /shopify page Net Sales card: same EXCLUDE_SOURCES + XYZ filter.
     *
     * Falls back to the legacy `shopify_orders` table for dates that pre-date the
     * earliest record in `shopify_raw_orders` so L60 / longer-lookback windows still
     * produce a meaningful total (the Shopify Admin API in this account refuses to
     * return older `created_at` orders, so the new raw-orders table can't be
     * backfilled past its current floor). The legacy table is per-order (not per
     * line), stores source_name/tags inside `raw_payload`, and `total_price` is
     * effectively the order-level net since `total_discounts` is empty there.
     *
     * @return array{sales: float, orders: int, qty: int}
     */
    private function computeShopifyDirectMetricsFromOrders(Carbon $start, Carbon $end): array
    {
        $startDate = $start->toDateString();
        $endDate   = $end->toDateString();

        $rawBase = DB::table('shopify_raw_orders')
            ->where('order_date', '>=', $startDate)
            ->where('order_date', '<=', $endDate);
        $this->applyShopifyDirectOrderExclusions($rawBase);

        $sales  = (float) (clone $rawBase)->sum('net_sales');
        $orders = (int)   (clone $rawBase)->distinct('order_id')->count('order_id');
        $qty    = (int)   (clone $rawBase)->sum('quantity');

        // Legacy fallback for any dates in the window that pre-date
        // shopify_raw_orders' earliest record (avoids double counting because the
        // two tables don't currently overlap — legacy stops 2026-05-01, raw starts
        // 2026-05-11; we still cut at raw_orders.min(order_date) - 1 day to stay
        // safe if backfills add overlap in the future).
        if (Schema::hasTable('shopify_orders')) {
            try {
                $rawMin = DB::table('shopify_raw_orders')->min('order_date');
                if ($rawMin && Carbon::parse($rawMin)->gt($start)) {
                    $legacyEnd = Carbon::parse($rawMin)->subDay()->endOfDay();
                    if ($legacyEnd->gte($start)) {
                        $legacyBase = DB::table('shopify_orders')
                            ->where('order_date', '>=', $start->copy()->startOfDay())
                            ->where('order_date', '<=', $legacyEnd);
                        $this->applyShopifyDirectOrderExclusionsLegacy($legacyBase);
                        $sales  += (float) (clone $legacyBase)->sum('total_price');
                        $orders += (int)   (clone $legacyBase)->distinct('shopify_order_id')->count('shopify_order_id');
                        // shopify_orders doesn't have a per-line quantity column,
                        // so we approximate qty from line_items_count when present.
                        $qty    += (int)   (clone $legacyBase)->sum('line_items_count');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Shopify legacy-orders fallback failed: ' . $e->getMessage());
            }
        }

        return [
            'sales'  => round($sales, 2),
            'orders' => $orders,
            'qty'    => $qty,
        ];
    }

    /**
     * EXCLUDE_SOURCES filter for the legacy shopify_orders table. Standalone
     * source_name/tags columns are empty on that table so we apply the same
     * filter against `raw_payload->source_name` / `raw_payload->tags` JSON paths.
     * SKU-level XYZ exclusion is skipped here because line items live inside
     * raw_payload; the L60 fallback is small enough that this is acceptable.
     */
    private function applyShopifyDirectOrderExclusionsLegacy($query)
    {
        foreach (\App\Http\Controllers\ShopifyRawDataController::EXCLUDE_SOURCES as $term) {
            $query->whereRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, "$.source_name")),"")) NOT LIKE ?', ['%' . strtolower($term) . '%'])
                  ->whereRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(raw_payload, "$.tags")),"")) NOT LIKE ?',        ['%' . strtolower($term) . '%']);
        }

        return $query;
    }

    /**
     * Live L30/L60 Shopify "Direct" metrics from shopify_raw_orders, matching the
     * /shopify page Net Sales card. Used to overlay /all-marketplace-master so the
     * "Shopify" channel Sales column reflects the same value the user sees on /shopify.
     *
     * Uses the server's configured timezone so the date window aligns with the
     * /shopify page (the page's flatpickr sends date_from/date_to in the user's
     * browser-local timezone; deploying the app in the user's timezone keeps the
     * server-side overlay window in sync with that view).
     *
     * @return array{l30_sales: float, l30_orders: int, qty: int, l60_sales: float, l60_orders: int}|null
     */
    private function getShopifyDirectLiveMetricsSummary(): ?array
    {
        try {
            $today = Carbon::now();

            // L30 window matches the /shopify page default: today − 30 days … today.
            $l30Start = $today->copy()->subDays(30)->startOfDay();
            $l30End   = $today->copy()->endOfDay();

            // L60 = prior period (days 31-60) so Growth on the Active Channel row works.
            $l60Start = $today->copy()->subDays(60)->startOfDay();
            $l60End   = $today->copy()->subDays(31)->endOfDay();

            $l30 = $this->computeShopifyDirectMetricsFromOrders($l30Start, $l30End);
            $l60 = $this->computeShopifyDirectMetricsFromOrders($l60Start, $l60End);

            return [
                'l30_sales'  => $l30['sales'],
                'l30_orders' => $l30['orders'],
                'qty'        => $l30['qty'],
                'l60_sales'  => $l60['sales'],
                'l60_orders' => $l60['orders'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Shopify Direct live metrics summary failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Y Sales for the "Shopify" Active Channel row — net_sales sum for the Pacific day
     * before the latest order_date in shopify_raw_orders (same clock convention as
     * other channels' Y Sales). Same exclusions as the /shopify Net Sales card.
     */
    private function computeShopifyDirectYSalesLikeAmazon(): ?float
    {
        try {
            $latest = DB::table('shopify_raw_orders')
                ->whereNotNull('order_date')
                ->max('order_date');
            if (! $latest) {
                return null;
            }

            $pst = 'America/Los_Angeles';
            $latestPacific = Carbon::parse($latest, $pst);
            $yStart = $latestPacific->copy()->subDay()->startOfDay();
            $yEnd   = $latestPacific->copy()->subDay()->endOfDay();

            $q = DB::table('shopify_raw_orders')
                ->where('order_date', '>=', $yStart->toDateString())
                ->where('order_date', '<=', $yEnd->toDateString());
            $this->applyShopifyDirectOrderExclusions($q);

            return round((float) $q->sum('net_sales'), 2);
        } catch (\Throwable $e) {
            Log::warning('Shopify Direct Y Sales failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * L7 Sales for the "Shopify" Active Channel row — net_sales sum for the 7-day Pacific
     * window ending on the same "yesterday" used by Y Sales. Same exclusions as /shopify.
     */
    private function computeShopifyDirectL7SalesLikeAmazon(): ?float
    {
        try {
            $latest = DB::table('shopify_raw_orders')
                ->whereNotNull('order_date')
                ->max('order_date');
            if (! $latest) {
                return null;
            }

            $pst = 'America/Los_Angeles';
            $latestPacific = Carbon::parse($latest, $pst);
            [$l7Start, $l7End] = $this->pacificL7WindowEndingYesterday($latestPacific);

            $q = DB::table('shopify_raw_orders')
                ->where('order_date', '>=', $l7Start->toDateString())
                ->where('order_date', '<=', $l7End->toDateString());
            $this->applyShopifyDirectOrderExclusions($q);

            return round((float) $q->sum('net_sales'), 2);
        } catch (\Throwable $e) {
            Log::warning('Shopify Direct L7 Sales failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Overlay live Shopify Net Sales (from /shopify shopify_raw_orders) onto the "Shopify"
     * Active Channel row so /all-marketplace-master Sales column matches the /shopify page
     * Net Sales card. Without this overlay the row shows 0 because channel_master only
     * knows about "Shopify B2C" / "Shopify B2B" controllers.
     */
    private function overlayLiveShopifyDirectMetricsOnChannelRows(array $rows): array
    {
        $live = $this->getShopifyDirectLiveMetricsSummary();
        if ($live === null) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            // Match the channel_master row literally named "Shopify" (id 60).
            if (strcasecmp($name, 'Shopify') !== 0) {
                continue;
            }

            $l30Sales = (float) $live['l30_sales'];
            $l60Sales = (float) $live['l60_sales'];

            $row['L30 Sales']  = (int) round($l30Sales);
            $row['L30 Orders'] = (int) $live['l30_orders'];
            $row['Qty']        = (int) $live['qty'];
            $row['L-60 Sales'] = (int) round($l60Sales);
            $row['L60 Orders'] = (int) $live['l60_orders'];

            if ($l60Sales > 0) {
                $row['Growth'] = round((($l30Sales - $l60Sales) / $l60Sales) * 100, 2) . '%';
            }

            $ySales = $this->computeShopifyDirectYSalesLikeAmazon();
            if ($ySales !== null) {
                $row['Y Sales'] = $ySales;
            }

            $l7Sales = $this->computeShopifyDirectL7SalesLikeAmazon();
            if ($l7Sales !== null) {
                $row['L7 Sales'] = $l7Sales;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Map / Miss / NMap for Macys — same rules as macys-pricing badges.
     * Missing L: REQ + INV>0 + MC Price=0
     * Missing M: REQ + INV>0 + MC Price>0 + |INV-MC INV|>3 (macys-pricing default filters)
     */
    private function getMacysLiveMapMissNMapFromPricingData(): array
    {
        try {
            $macyCtrl = app(\App\Http\Controllers\MarketPlace\MacyController::class);
            $response = $macyCtrl->getViewMacysTabulatorData(Request::create('/macys-data-json', 'GET'));
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('macys');
            }

            $counts = \App\Http\Controllers\MarketPlace\MacyController::countMacysPricingBadgeTotals($rows);

            return [
                'map' => $counts['map'],
                'miss' => $counts['miss'],
                'nmap' => $counts['nmap'],
                'total_views' => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('Macys live map/miss/nmap fallback: ' . $e->getMessage());
            return $this->getMapAndMissCounts('macys');
        }
    }

    /**
     * Replace stale cached Map/Miss/NMap on channel rows with live pricing-page counts.
     */
    private function overlayLiveMapMissNMapOnChannelRows(array $rows): array
    {
        $liveByChannel = [
            'Amazon' => fn () => $this->getAmazonLiveMapMissNMapCounts(),
            'Macys' => fn () => $this->getMacysLiveMapMissNMapFromPricingData(),
            'PLS' => fn () => $this->getPlsLiveMapMissNMapFromPricingData(),
            'Wayfair' => fn () => $this->getWayfairLiveMapMissNMapFromPricingData(),
            'Aliexpress' => fn () => $this->getAliexpressLiveMapMissNMapFromPricingData(),
            'Shein' => fn () => $this->getSheinLiveMapMissNMapFromPricingData(),
            'Faire' => fn () => $this->getFaireLiveMapMissNMapFromPricingData(Request::create('/faire/pricing-data', 'GET')),
            'Reverb' => fn () => $this->getReverbLiveMapMissNMapFromPricingData(Request::create('/reverb-data-json', 'GET')),
            'TopDawg' => fn () => $this->getTopDawgLiveMapMissNMapFromPricingData(),
            'Temu' => fn () => $this->getTemuLiveMapMissNMapFromDecreaseData(false),
            'Temu 2' => fn () => $this->getTemuLiveMapMissNMapFromDecreaseData(true),
        ];

        foreach ($rows as &$row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? ''));
            if ($name === '' || ! isset($liveByChannel[$name])) {
                continue;
            }
            $counts = $liveByChannel[$name]();
            $row['Map'] = $counts['map'];
            $row['Miss'] = $counts['miss'];
            $row['NMap'] = $counts['nmap'];
            if (array_key_exists('total_views', $counts)) {
                $row['Total Views'] = $counts['total_views'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Map / Miss / NMap for PLS — same rules as pls-pricing badges.
     * Missing: INV > 0 AND price <= 0
     * N Map: missing !== 'M' AND ((INV > 0 AND PLS_INV = 0 AND INV > 3) OR (INV > 0 AND PLS_INV > 0 AND |INV - PLS_INV| > 3))
     * Map: missing !== 'M' AND NOT N Map (difference ≤ 3)
     */
    private function getPlsLiveMapMissNMapFromPricingData(): array
    {
        try {
            $req = Request::create('/pls-pricing-data-json', 'GET');
            $plsCtrl = app(PlsController::class);
            $response = $plsCtrl->pricingDataJson($req);
            $rows = json_decode($response->getContent(), true);
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('pls');
            }

            return PlsController::countPlsPricingBadgeTotals($rows);
        } catch (\Throwable $e) {
            Log::warning('PLS live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('pls');
        }
    }

    /**
     * Map / Miss / NMap for Wayfair — same rules as wayfair-pricing badges.
     */
    private function getWayfairLiveMapMissNMapFromPricingData(): array
    {
        try {
            $response = app(WayfairController::class)->getWayfairPricingData(Request::create('/wayfair/pricing-data', 'GET'));
            $rows = json_decode($response->getContent(), true);
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('wayfair');
            }

            return WayfairController::countWayfairPricingBadgeTotals($rows);
        } catch (\Throwable $e) {
            Log::warning('Wayfair live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('wayfair');
        }
    }

    /**
     * Map / Miss / NMap for AliExpress — same rules as aliexpress-pricing badges.
     */
    private function getAliexpressLiveMapMissNMapFromPricingData(): array
    {
        try {
            $response = app(AliexpressController::class)->getPricingData(Request::create('/aliexpress/pricing-data', 'GET'));
            $rows = json_decode($response->getContent(), true);
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('aliexpress');
            }

            return AliexpressController::countAliexpressPricingBadgeTotals($rows);
        } catch (\Throwable $e) {
            Log::warning('AliExpress live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('aliexpress');
        }
    }

    /**
     * Map / Miss / NMap for Shein — same rules as shein-pricing badges.
     */
    private function getSheinLiveMapMissNMapFromPricingData(): array
    {
        try {
            $response = app(SheinController::class)->getSheinPricingData(Request::create('/shein/pricing-data', 'GET'));
            $rows = json_decode($response->getContent(), true);
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('shein');
            }

            return SheinController::countSheinPricingBadgeTotals($rows);
        } catch (\Throwable $e) {
            Log::warning('Shein live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('shein');
        }
    }

    /**
     * Map / Miss / NMap for Best Buy USA — same rules as bestbuy-pricing (MISSING + N Map badges).
     * Miss: REQ + INV>0 + BB Price=0
     * Map / NMap: REQ + INV>0 + BB Price>0 + |INV − BB INV| ≤ 3 (map) or > 3 (NMap)
     */
    private function getBestbuyLiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $ctrl = app(\App\Http\Controllers\MarketPlace\BestBuyPricingController::class);
            $response = $ctrl->getViewBestBuyData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('bestbuy');
            }

            $map = 0;
            $miss = 0;
            $nmap = 0;
            $views = 0;

            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row)) {
                    continue;
                }

                $parent = trim((string) ($row['Parent'] ?? ''));
                if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT')) {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                $bbInv = (float) ($row['BB INV'] ?? 0);
                $price = (float) ($row['BB Price'] ?? 0);
                $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? 'REQ')));
                $isReq = ($nrReq === 'REQ');

                if ($isReq && $inv > 0 && $price == 0.0) {
                    $miss++;
                }

                if ($isReq && $inv > 0 && $price > 0) {
                    $diff = abs($inv - $bbInv);
                    if ($diff <= 3) {
                        $map++;
                    } else {
                        $nmap++;
                    }
                }
            }

            return [
                'map' => $map,
                'miss' => $miss,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('BestBuy live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('bestbuy');
        }
    }

    /**
     * Map / Miss / NMap for Reverb — same rules as reverb-pricing (Missing L badge + MAP / N MP column).
     * Miss: REQ + INV>0 + RV Price=0 (no live Reverb listing; same as Macys MC Price / Best Buy BB Price).
     * Map / NMap: REQ + INV>0 + listed (RV Price>0) + R Stock>0, using the same 3% tolerance as /map-issues
     *   (absolute gap > 3 units when 3% of INV is below 3 units, otherwise rounded % > 3). Map = within tolerance,
     *   NMap = exceeds tolerance. Total Views summed for those listed rows.
     */
    private function getReverbLiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\ReverbController::class)->getViewReverbTabularData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('reverb');
            }

            return app(\App\Http\Controllers\MarketPlace\ReverbController::class)
                ->computeReverbMapMissCounts($rows);
        } catch (\Throwable $e) {
            Log::warning('Reverb live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('reverb');
        }
    }

    /**
     * Map / Miss / NMap for TopDawg — same rules as topdawg-pricing (Missing L + |INV − TD Stock| ≤ 3).
     */
    private function getTopDawgLiveMapMissNMapFromPricingData(): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\TopDawgPricingController::class)
                ->getViewTopDawgTabularData(Request::create('/topdawg-data-json', 'GET'));
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('topdawg');
            }

            return \App\Http\Controllers\MarketPlace\TopDawgPricingController::countTopDawgPricingBadgeTotals($rows);
        } catch (\Throwable $e) {
            Log::warning('TopDawg live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('topdawg');
        }
    }

    /**
     * Map / Miss / NMap for TikTok Shop — same rules as tiktok-pricing / Reverb (|INV − TT Stock| ≤ 3).
     * Miss: listed row Missing === 'M' with INV > 0 (non-parent).
     * Map / NMap: INV > 0, listed, |INV − TT Stock| ≤ 3 (map) or > 3 (NMap). Views = video + ads + affl on those rows.
     */
    private function getTiktokLiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\TikTokPricingController::class)->getViewTikTokTabularData($request, 'v1');
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('tiktok');
            }

            $map = 0;
            $miss = 0;
            $nmap = 0;
            $views = 0;

            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row)) {
                    continue;
                }

                $parent = trim((string) ($row['Parent'] ?? ''));
                if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT')) {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $isMissing = strtoupper(trim((string) ($row['Missing'] ?? ''))) === 'M';
                if ($isMissing) {
                    $miss++;

                    continue;
                }

                $ttStock = (float) ($row['TT Stock'] ?? 0);
                $diff = abs($inv - $ttStock);
                if ($diff <= 3) {
                    $map++;
                } else {
                    $nmap++;
                }

                $views += (int) ($row['video_views'] ?? 0) + (int) ($row['ads_views'] ?? 0) + (int) ($row['affl_views'] ?? 0);
            }

            return [
                'map' => $map,
                'miss' => $miss,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTok live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('tiktok');
        }
    }

    /**
     * Map / Miss / NMap for TikTok Shop 2 — same rules as TikTok 1 / tiktok-pricing v2 (|INV − TT Stock| ≤ 3).
     */
    private function getTiktok2LiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\TikTokPricingController::class)->getViewTikTokTabularData($request, 'v2');
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('tiktok2');
            }

            $map = 0;
            $miss = 0;
            $nmap = 0;
            $views = 0;

            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row)) {
                    continue;
                }

                $parent = trim((string) ($row['Parent'] ?? ''));
                if ($parent !== '' && str_starts_with(strtoupper($parent), 'PARENT')) {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $isMissing = strtoupper(trim((string) ($row['Missing'] ?? ''))) === 'M';
                if ($isMissing) {
                    $miss++;

                    continue;
                }

                $ttStock = (float) ($row['TT Stock'] ?? 0);
                $diff = abs($inv - $ttStock);
                if ($diff <= 3) {
                    $map++;
                } else {
                    $nmap++;
                }

                $views += (int) ($row['video_views'] ?? 0) + (int) ($row['ads_views'] ?? 0) + (int) ($row['affl_views'] ?? 0);
            }

            return [
                'map' => $map,
                'miss' => $miss,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('TikTok 2 live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('tiktok2');
        }
    }

    /**
     * Map / Miss / NMap for Purchasing Power — same rules as purchasing-power-pricing
     * (REQ + INV>0 + PP Price>0; |INV − PP Stock| ≤ 3 → map, else NMap; miss: REQ + INV>0 + PP Price=0).
     */
    private function getPurchasingPowerLiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\PurchasingPowerController::class)->getViewData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('purchasingpower');
            }

            $map = 0;
            $miss = 0;
            $nmap = 0;

            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row)) {
                    continue;
                }

                if (strtoupper(trim((string) ($row['nr_req'] ?? 'REQ'))) === 'NR') {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                if ($inv <= 0) {
                    continue;
                }

                $price = (float) ($row['PP Price'] ?? 0);
                if ($price <= 0) {
                    $miss++;

                    continue;
                }

                $ppStock = (float) ($row['PP INV'] ?? 0);
                $diff = abs($inv - $ppStock);
                if ($diff <= 3) {
                    $map++;
                } else {
                    $nmap++;
                }
            }

            return [
                'map' => $map,
                'miss' => $miss,
                'nmap' => $nmap,
                'total_views' => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('purchasingpower');
        }
    }

    /**
     * Map / Miss / NMap for Faire — same rules as /faire-pricing (Amazon-aligned: REQ + INV>0 + no price → Missing L; Map if |INV−stock| ≤ 3 or ≤ 3% INV).
     */
    private function getFaireLiveMapMissNMapFromPricingData(Request $request): array
    {
        try {
            $response = app(FaireController::class)->getFairePricingData($request);
            $rows = json_decode($response->getContent(), true);
            if (! is_array($rows) || isset($rows['error'])) {
                return $this->getMapAndMissCounts('faire');
            }

            $totals = FaireController::countFairePricingBadgeTotals($rows);
            $views = 0;
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row) || ! empty($row['is_parent'])) {
                    continue;
                }
                $views += (int) ($row['ov_l30'] ?? 0);
            }

            return [
                'map' => $totals['map'],
                'miss' => $totals['miss'],
                'nmap' => $totals['nmap'],
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('Faire live map/miss/nmap fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('faire');
        }
    }

    /**
     * eBay 1/2/3: sum of listing "eBay L30" units from the latest tabulator snapshot (amazon_channel_summary_data).
     * Same snapshot as Total Views (tabulator save), so CVR = L30 Orders / Total Views matches ebay-tabulator-view
     * (units ÷ views). Falls back to marketplace_daily_metrics when the key is absent (older snapshots).
     *
     * @param string $amazonSummaryChannel Channel key stored on AmazonChannelSummary: ebay, ebay2, ebay3
     */
    private function getEbayTabulatorL30UnitsForCvr(string $amazonSummaryChannel): ?float
    {
        $row = AmazonChannelSummary::where('channel', strtolower($amazonSummaryChannel))
            ->latest('snapshot_date')
            ->first();
        if (!$row || empty($row->summary_data) || !is_array($row->summary_data)) {
            return null;
        }
        if (!array_key_exists('total_ebay_l30', $row->summary_data)) {
            return null;
        }

        return (float) $row->summary_data['total_ebay_l30'];
    }

    /**
     * Stub values for Shipping Health, CC Health, Returns %, A2Z Claims, Ratings & Reviews columns.
     * Override in channel-specific data when sources (e.g. AccountHealthMaster, ChannelsReviewsData) are wired.
     */
    private function getChannelHealthAndReviewsStub(): array
    {
        return [
            'Shipping Health' => '-',
            'CC Health' => '-',
            'Returns %' => 0,
            'A2Z Claims' => 0,
            'Avg Rating' => 0,
            'Total Reviews' => 0,
            'Seller Avg Rating' => 0,
            'Seller Total Reviews' => 0,
        ];
    }

    /**
     * Sum of (inventory * Amazon price) across all SKUs for the INV Val badge.
     * Uses product_stock_mappings.inventory_amazon and amazon_datsheets.price.
     */
    private function getInventoryValueAmazon(): float
    {
        $total = DB::table('product_stock_mappings as psm')
            ->join('amazon_datsheets as ad', DB::raw('TRIM(UPPER(psm.sku))'), '=', DB::raw('TRIM(UPPER(ad.sku))'))
            ->whereNotNull('psm.sku')
            ->whereNotNull('ad.sku')
            ->selectRaw('SUM(COALESCE(psm.inventory_amazon, 0) * COALESCE(ad.price, 0)) as total')
            ->value('total');

        return (float) ($total ?? 0);
    }

    /**
     * Shopify inventory + LP from product_master Values JSON (same matching as Inv@LP).
     * Only includes ACTIVE status SKUs from product_master.
     *
     * @return array{inv_sum: float, inv_at_lp: float, weighted_avg_lp: float}
     */
    private function getShopifyInvLpMetrics(): array
    {
        // Get active SKUs from product_master (excluding deleted and non-active)
        $productMasters = ProductMaster::whereNull('deleted_at')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`Values`, "$.status"))) = ?', ['active'])
            ->get();
            
        if ($productMasters->isEmpty()) {
            return ['inv_sum' => 0.0, 'inv_at_lp' => 0.0, 'weighted_avg_lp' => 0.0];
        }
        
        $activeSkus = $productMasters->pluck('sku')->unique()->filter()->values()->toArray();
        
        // Get Shopify inventory for active SKUs only
        $shopifySkus = ShopifySku::whereNotNull('sku')
            ->whereIn('sku', $activeSkus)
            ->get(['sku', 'inv']);
            
        if ($shopifySkus->isEmpty()) {
            return ['inv_sum' => 0.0, 'inv_at_lp' => 0.0, 'weighted_avg_lp' => 0.0];
        }
        
        $pmBySku = $productMasters->keyBy(function ($item) {
            return strtoupper(trim((string) $item->sku));
        });

        $invSum = 0.0;
        $invAtLp = 0.0;
        foreach ($shopifySkus as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            
            // Exclude SKUs with null or 0 inventory
            if ($row->inv === null || $row->inv === '' || !is_numeric($row->inv)) {
                continue;
            }
            
            $inv = (float) $row->inv;
            
            // Exclude SKUs with 0 or near-0 inventory (< 0.01)
            if ($inv < 0.01) {
                continue;
            }
            
            $invSum += $inv;
            $pm = $pmBySku->get(strtoupper($sku));
            $lp = 0.0;
            if ($pm) {
                $values = is_array($pm->Values ?? null) ? $pm->Values : (is_string($pm->Values ?? null) ? json_decode($pm->Values, true) : []);
                $lp = isset($values['lp']) ? (float) $values['lp'] : (isset($pm->lp) ? (float) $pm->lp : 0);
            }
            $invAtLp += $inv * $lp;
        }
        $weightedAvgLp = $invSum > 0 ? round($invAtLp / $invSum, 2) : 0.0;

        return [
            'inv_sum' => round($invSum, 2),
            'inv_at_lp' => round($invAtLp, 2),
            'weighted_avg_lp' => $weightedAvgLp,
        ];
    }

    /**
     * Read a display color from product_master.Values (merged keys in Product Master UI).
     */
    private function resolveColorLabelFromProductMaster(?ProductMaster $pm): string
    {
        if (! $pm) {
            return 'Unknown';
        }
        $raw = $pm->Values ?? null;
        $values = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : []);
        if (! is_array($values)) {
            $values = [];
        }
        foreach (['Color', 'color', 'COLOUR', 'Colour', 'colour', 'CLR', 'clr'] as $k) {
            if (! array_key_exists($k, $values)) {
                continue;
            }
            $s = trim((string) ($values[$k] ?? ''));
            if ($s !== '') {
                return strlen($s) > 80 ? substr($s, 0, 80) . '…' : $s;
            }
        }

        return 'Unknown';
    }

    /**
     * Calculate DIL color category based on L30 sales and inventory
     * Returns simple color names without DIL percentage ranges
     * 
     * @param float $l30 L30 sales
     * @param float $inv Inventory
     * @return string Color category
     */
    private function getDilColorCategory(float $l30, float $inv): string
    {
        // Include inventory from 0.01 onwards; exclude only 0
        if ($inv < 0.01) {
            return 'No Inventory';
        }
        
        $dilPercent = ($l30 / $inv) * 100;
        
        if ($dilPercent < 16.66) {
            return 'Red';
        } elseif ($dilPercent < 25) {
            return 'Yellow';
        } elseif ($dilPercent < 50) {
            return 'Green';
        } else {
            return 'Pink';
        }
    }

    /**
     * Sum of inventory VALUE (INV * Amazon Price) grouped by DIL color categories based on L30 sales.
     * DIL % = (L30 / INV) × 100, categorized into 4 color bands.
     * Note: Using 'quantity' field as L30 (consistent with other controllers)
     * Amazon prices fetched from amazon_datsheets table
     * Returns value instead of count - 'inv' field now represents $ value
     *
     * @return list<array{color: string, inv: float, percent: float}>
     */
    private function getShopifyInventoryByColor(): array
    {
        // Get active SKUs from product_master (excluding deleted and non-active)
        $activeSkus = ProductMaster::whereNull('deleted_at')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`Values`, "$.status"))) = ?', ['active'])
            ->pluck('sku')
            ->toArray();
        
        if (empty($activeSkus)) {
            return [];
        }
        
        // Fetch Amazon prices from amazon_datsheets table (gracefully handle if table doesn't exist)
        $amazonPrices = collect();
        try {
            if (Schema::hasTable('amazon_datsheets')) {
                $amazonPrices = DB::table('amazon_datsheets')
                    ->whereIn('sku', $activeSkus)
                    ->select('sku', 'price')
                    ->get()
                    ->keyBy('sku');
            }
        } catch (\Exception $e) {
            \Log::warning('Could not fetch Amazon prices from amazon_datsheets table: ' . $e->getMessage());
        }
        
        // Get Shopify SKUs with inventory and L30 sales data for active SKUs only
        // Note: 'quantity' field is the L30 sales data used across the application
        $shopifySkus = ShopifySku::whereNotNull('sku')
            ->whereIn('sku', $activeSkus)
            ->get(['sku', 'inv', 'quantity']);
        
        if ($shopifySkus->isEmpty()) {
            return [];
        }

        $byColor = [];
        $countByColor = [];
        foreach ($shopifySkus as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            
            // Exclude parent rows (same pattern as other calculations in this controller)
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }
            
            // Exclude SKUs with null or 0 inventory
            if ($row->inv === null || $row->inv === '' || !is_numeric($row->inv)) {
                continue;
            }
            
            $inv = (float) $row->inv;
            
            // Exclude SKUs with 0 or near-0 inventory (using DIL threshold: < 0.01)
            if ($inv < 0.01) {
                continue;
            }
            
            $l30 = is_numeric($row->quantity) ? (float) $row->quantity : 0.0;
            
            // Get Amazon price for this SKU (default to 0 if not found)
            $amazonPrice = isset($amazonPrices[$sku]) ? (float) $amazonPrices[$sku]->price : 0.0;
            
            // Calculate inventory value (INV * Amazon Price)
            $invValue = $inv * $amazonPrice;
            
            // Determine DIL color category based on L30 and inventory
            $color = $this->getDilColorCategory($l30, $inv);
            $byColor[$color] = ($byColor[$color] ?? 0.0) + $invValue;
            $countByColor[$color] = ($countByColor[$color] ?? 0) + 1;
        }

        if ($byColor === []) {
            return [];
        }

        $totalValue = array_sum($byColor);
        $pct = function (float $value) use ($totalValue): float {
            return $totalValue > 0 ? round(($value / $totalValue) * 100, 1) : 0.0;
        };

        // Define the order of DIL categories for consistent display
        $categoryOrder = [
            'Pink',
            'Green',
            'Yellow',
            'Red',
            'No Inventory'
        ];

        $out = [];
        foreach ($categoryOrder as $category) {
            if (isset($byColor[$category]) && $byColor[$category] > 0) {
                $value = (float) $byColor[$category];
                $out[] = [
                    'color' => $category,
                    'inv' => round($value, 2), // Keep 'inv' key for frontend compatibility but now it's value
                    'percent' => $pct($value),
                    'count' => $countByColor[$category] ?? 0,
                ];
            }
        }

        // Add any categories not in the predefined order (shouldn't happen, but just in case)
        foreach ($byColor as $color => $value) {
            if (!in_array($color, $categoryOrder) && $value > 0) {
                $out[] = [
                    'color' => (string) $color,
                    'inv' => round($value, 2), // Keep 'inv' key for frontend compatibility but now it's value
                    'percent' => $pct($value),
                    'count' => $countByColor[$color] ?? 0,
                ];
            }
        }

        return $out;
    }

    /**
     * Get stock availability: count of ACTIVE SKUs with <0.01 stock vs >=0.01 stock
     * Matches the DIL threshold: inventory from 0.01 onwards is considered "in stock"
     * Only includes Active status SKUs from product_master
     * 
     * @return array{zero_stock: int, in_stock: int}
     */
    private function getStockAvailability(): array
    {
        // Get active SKUs from product_master (excluding deleted and non-active)
        $activeSkus = ProductMaster::whereNull('deleted_at')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(`Values`, "$.status"))) = ?', ['active'])
            ->pluck('sku')
            ->toArray();
        
        if (empty($activeSkus)) {
            return ['zero_stock' => 0, 'in_stock' => 0];
        }
        
        // Get shopify inventory data for active SKUs only
        $shopifySkus = ShopifySku::whereNotNull('sku')
            ->whereIn('sku', $activeSkus)
            ->get(['sku', 'inv']);
        
        if ($shopifySkus->isEmpty()) {
            return ['zero_stock' => 0, 'in_stock' => 0];
        }

        $zeroStock = 0;
        $inStock = 0;

        foreach ($shopifySkus as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            
            // Exclude parent rows (same pattern as DIL calculation)
            if (stripos($sku, 'PARENT') !== false) {
                continue;
            }
            
            $inv = is_numeric($row->inv) ? (float) $row->inv : 0.0;
            
            // Match DIL logic: < 0.01 is considered zero stock
            if ($inv < 0.01) {
                $zeroStock++;
            } else {
                $inStock++;
            }
        }

        return [
            'zero_stock' => $zeroStock,
            'in_stock' => $inStock,
        ];
    }

    /**
     * L30 Amazon Seller + Amazon FBA campaign rows (latest id per campaignName) with spend for color attribution.
     * Mirrors {@see fetchAdMetricsFromTables()} amazon / amazonfba spend sources.
     *
     * @return \Illuminate\Support\Collection<int, array{campaignName: string, spend: float}>
     */
    private function collectAmazonFamilyL30CampaignNameSpendPairs()
    {
        $pairs = [];

        $appendSp = function ($ids) use (&$pairs) {
            if ($ids->isEmpty()) {
                return;
            }
            foreach (DB::table('amazon_sp_campaign_reports')->whereIn('id', $ids)->get(['campaignName', 'spend']) as $r) {
                $pairs[] = [
                    'campaignName' => (string) ($r->campaignName ?? ''),
                    'spend' => (float) ($r->spend ?? 0),
                ];
            }
        };

        // Amazon Seller — KW
        $kwSellerIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        $appendSp($kwSellerIds);

        // Amazon Seller — PT
        $ptSellerIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        $appendSp($ptSellerIds);

        // Amazon Seller — SB (HL): cost column
        $hlLatestIds = DB::table('amazon_sb_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->groupBy('campaignName')
            ->pluck('id');
        if ($hlLatestIds->isNotEmpty()) {
            foreach (DB::table('amazon_sb_campaign_reports')->whereIn('id', $hlLatestIds)->get(['campaignName', 'cost']) as $r) {
                $pairs[] = [
                    'campaignName' => (string) ($r->campaignName ?? ''),
                    'spend' => (float) ($r->cost ?? 0),
                ];
            }
        }

        // Amazon FBA — KW
        $kwFbaIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->whereRaw("campaignName LIKE '%FBA'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        $appendSp($kwFbaIds);

        // Amazon FBA — PT
        $ptFbaIds = DB::table('amazon_sp_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_date_range', 'L30')
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'");
            })
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->groupBy('campaignName')
            ->pluck('id');
        $appendSp($ptFbaIds);

        return collect($pairs);
    }

    /**
     * SKU → product_master map for matching campaign / listing text to a color (shared across channels).
     *
     * @return array{pmByUpper: \Illuminate\Support\Collection, skuSorted: \Illuminate\Support\Collection}|null
     */
    private function buildColorAttributionLookupForAdCharts(): ?array
    {
        $skuPool = collect()
            ->merge(ShopifySku::query()->whereNotNull('sku')->pluck('sku'))
            ->merge(DB::table('amazon_datsheets')->whereNotNull('sku')->pluck('sku'))
            ->map(function ($s) {
                return trim((string) $s);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $pms = collect();
        foreach (array_chunk($skuPool, 2500) as $chunk) {
            $pms = $pms->merge(
                ProductMaster::whereNull('deleted_at')
                    ->whereIn('sku', $chunk)
                    ->get(['sku', 'Values'])
            );
        }
        $pms = $pms->unique('sku')->values()->filter(function ($pm) {
            $sku = trim((string) ($pm->sku ?? ''));

            return $sku !== '' && stripos($sku, 'PARENT') === false;
        });

        $pmByUpper = $pms->keyBy(function ($p) {
            return strtoupper(trim((string) $p->sku));
        });

        $skuSorted = $pms->pluck('sku')->map(function ($s) {
            return trim((string) $s);
        })->unique()->filter(function ($s) {
            return strlen($s) >= 4;
        })->sortByDesc(function ($s) {
            return strlen($s);
        })->values();

        if ($skuSorted->isEmpty()) {
            return null;
        }

        return ['pmByUpper' => $pmByUpper, 'skuSorted' => $skuSorted];
    }

    /**
     * @param  iterable<int, array<string, mixed>|\stdClass>  $campaigns  rows with campaignName + spend
     * @return list<array{color: string, spend: float, percent: float}>
     */
    private function aggregateAdSpendByColorFromCampaignPairs(iterable $campaigns, $pmByUpper, $skuSorted): array
    {
        $byColor = [];
        foreach ($campaigns as $c) {
            $row = is_array($c) ? $c : (array) $c;
            $name = (string) ($row['campaignName'] ?? $row['campaign_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $spend = (float) ($row['spend'] ?? 0);
            if ($spend <= 0) {
                continue;
            }
            $matchedPm = null;
            foreach ($skuSorted as $sku) {
                if (stripos($name, $sku) !== false) {
                    $matchedPm = $pmByUpper->get(strtoupper(trim($sku)));
                    break;
                }
            }
            $color = $matchedPm ? $this->resolveColorLabelFromProductMaster($matchedPm) : 'Unmatched';
            $byColor[$color] = ($byColor[$color] ?? 0.0) + $spend;
        }

        if ($byColor === []) {
            return [];
        }

        arsort($byColor, SORT_NUMERIC);
        $maxSlices = 14;
        $merged = [];
        $tail = 0.0;
        $i = 0;
        foreach ($byColor as $color => $sp) {
            if ($i < $maxSlices) {
                $merged[$color] = $sp;
            } else {
                $tail += $sp;
            }
            $i++;
        }
        $otherLabel = 'All other';
        if ($tail > 0) {
            $merged[$otherLabel] = ($merged[$otherLabel] ?? 0.0) + $tail;
        }

        $total = array_sum($merged);
        $out = [];
        foreach ($merged as $color => $sp) {
            $sp = (float) $sp;
            $out[] = [
                'color' => (string) $color,
                'spend' => round($sp, 2),
                'percent' => $total > 0 ? round(($sp / $total) * 100, 1) : 0.0,
            ];
        }
        usort($out, function ($a, $b) {
            return $b['spend'] <=> $a['spend'];
        });

        return $out;
    }

    private function parseEbayFeeStringToFloat(?string $raw): float
    {
        return (float) preg_replace('/[^\d.]/', '', (string) ($raw ?? '0'));
    }

    /**
     * PMT (general listing) spend rolled up to SKU for the same substring color matcher as KW campaign names.
     * Joins listing_id → metrics.item_id (same idea as EbayPMPAds / Ebay2PMTAd controllers).
     *
     * @return list<array{campaignName: string, spend: float}>
     */
    private function collectEbayL30PmtSpendPairsMappedToSku(string $channelKey): array
    {
        $channelKey = strtolower(trim($channelKey));
        [$genTable, $metricsTable] = match ($channelKey) {
            'ebay' => ['ebay_general_reports', 'ebay_metrics'],
            'ebaytwo' => ['ebay_2_general_reports', 'ebay_2_metrics'],
            'ebaythree' => ['ebay_3_general_reports', 'ebay_3_metrics'],
            default => [null, null],
        };
        if ($genTable === null || $metricsTable === null
            || ! Schema::hasTable($genTable) || ! Schema::hasTable($metricsTable)
            || ! Schema::hasColumn($genTable, 'listing_id') || ! Schema::hasColumn($genTable, 'ad_fees')
            || ! Schema::hasColumn($metricsTable, 'item_id') || ! Schema::hasColumn($metricsTable, 'sku')) {
            return [];
        }

        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');
        $feeSum = 'COALESCE(SUM(REPLACE(REPLACE(g.ad_fees, "USD ", ""), ",", "")), 0)';

        $out = [];
        try {
            $rows = DB::table($genTable.' as g')
                ->join($metricsTable.' as m', function ($join) {
                    $join->whereRaw('TRIM(CAST(g.listing_id AS CHAR)) = TRIM(CAST(m.item_id AS CHAR))');
                })
                ->where('g.report_range', '>=', $startDate)
                ->where('g.report_range', '<=', $endDate)
                ->where('g.report_range', 'NOT LIKE', 'L%')
                ->whereNotNull('g.listing_id')
                ->where('g.listing_id', '!=', '')
                ->whereNotNull('m.sku')
                ->where('m.sku', '!=', '')
                ->selectRaw('TRIM(m.sku) as campaign_key, '.$feeSum.' as spend')
                ->groupBy(DB::raw('TRIM(m.sku)'))
                ->havingRaw($feeSum.' > 0')
                ->get();

            foreach ($rows as $r) {
                $sku = trim((string) ($r->campaign_key ?? ''));
                if ($sku === '') {
                    continue;
                }
                $spend = (float) ($r->spend ?? 0);
                if ($spend <= 0) {
                    continue;
                }
                $out[] = [
                    'campaignName' => $sku,
                    'spend' => $spend,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('collectEbayL30PmtSpendPairsMappedToSku: '.$e->getMessage(), ['gen' => $genTable, 'exception' => $e]);
        }

        return $out;
    }

    /**
     * eBay KW (priority) + PMT (general) spend for color attribution.
     * KW: last 31 days of daily priority rows (report_range NOT LIKE 'L%'), same window as dashboard eBay ads.
     * PMT: same window on general reports, rolled up to SKU via listing_id → metrics.item_id (fills eBay 1/2 when KW is sparse).
     * KW L30 snapshot fallback only when daily KW is empty and campaign_name exists.
     *
     * @return \Illuminate\Support\Collection<int, array{campaignName: string, spend: float}>
     */
    private function collectEbayL30CampaignNameSpendPairs(string $channelKey): \Illuminate\Support\Collection
    {
        $channelKey = strtolower(trim($channelKey));
        $table = match ($channelKey) {
            'ebay' => 'ebay_priority_reports',
            'ebaytwo' => 'ebay_2_priority_reports',
            'ebaythree' => 'ebay_3_priority_reports',
            default => null,
        };

        $pairs = [];
        $hasCampaignName = false;

        if ($table !== null && Schema::hasTable($table) && Schema::hasColumn($table, 'cpc_ad_fees_payout_currency')) {
            $hasCampaignName = Schema::hasColumn($table, 'campaign_name');
            $hasCampaignId = Schema::hasColumn($table, 'campaign_id');
            $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');
            $feeSum = 'COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0)';
            if ($hasCampaignName && $hasCampaignId) {
                $groupSql = 'NULLIF(TRIM(COALESCE(NULLIF(TRIM(campaign_name), ""), TRIM(campaign_id))), "")';
            } elseif ($hasCampaignName) {
                $groupSql = 'NULLIF(TRIM(campaign_name), "")';
            } elseif ($hasCampaignId) {
                $groupSql = 'NULLIF(TRIM(campaign_id), "")';
            } else {
                $groupSql = null;
            }

            try {
                $kwDaily = $groupSql !== null
                    ? DB::table($table)
                        ->where('report_range', '>=', $startDate)
                        ->where('report_range', '<=', $endDate)
                        ->where('report_range', 'NOT LIKE', 'L%')
                        ->where(function ($q) use ($hasCampaignName, $hasCampaignId) {
                            if ($hasCampaignId && $hasCampaignName) {
                                $q->where(function ($w) {
                                    $w->whereNotNull('campaign_id')->where('campaign_id', '!=', '');
                                })->orWhere(function ($w) {
                                    $w->whereNotNull('campaign_name')->where('campaign_name', '!=', '');
                                });
                            } elseif ($hasCampaignId) {
                                $q->whereNotNull('campaign_id')->where('campaign_id', '!=', '');
                            } elseif ($hasCampaignName) {
                                $q->whereNotNull('campaign_name')->where('campaign_name', '!=', '');
                            }
                        })
                        ->selectRaw($groupSql.' as campaign_key, '.$feeSum.' as spend')
                        ->groupBy(DB::raw($groupSql))
                        ->havingRaw($feeSum.' > 0')
                        ->get()
                    : collect();

                foreach ($kwDaily as $r) {
                    $name = trim((string) ($r->campaign_key ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $spend = (float) ($r->spend ?? 0);
                    if ($spend <= 0) {
                        continue;
                    }
                    $pairs[] = [
                        'campaignName' => $name,
                        'spend' => $spend,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('collectEbayL30CampaignNameSpendPairs daily KW: '.$e->getMessage(), ['table' => $table, 'exception' => $e]);
            }

            if ($pairs === [] && $hasCampaignName) {
                try {
                    $ids = DB::table($table)
                        ->selectRaw('MAX(id) as id')
                        ->where('report_range', 'L30')
                        ->whereNotNull('campaign_name')
                        ->where('campaign_name', '!=', '')
                        ->groupBy('campaign_name')
                        ->pluck('id');
                    if ($ids->isNotEmpty()) {
                        foreach (DB::table($table)->whereIn('id', $ids)->get(['campaign_name', 'cpc_ad_fees_payout_currency']) as $r) {
                            $pairs[] = [
                                'campaignName' => (string) ($r->campaign_name ?? ''),
                                'spend' => $this->parseEbayFeeStringToFloat($r->cpc_ad_fees_payout_currency ?? null),
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('collectEbayL30CampaignNameSpendPairs L30 KW: '.$e->getMessage(), ['table' => $table, 'exception' => $e]);
                }
            }
        }

        $pairs = array_merge($pairs, $this->collectEbayL30PmtSpendPairsMappedToSku($channelKey));

        return collect($pairs);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{campaignName: string, spend: float}>
     */
    private function collectWalmartL30CampaignNameSpendPairs(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('walmart_campaign_reports')) {
            return collect();
        }

        $ids = DB::table('walmart_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_range', 'L30')
            ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
            ->whereNotNull('campaignName')
            ->where('campaignName', '!=', '')
            ->groupBy('campaignName')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $pairs = [];
        foreach (DB::table('walmart_campaign_reports')->whereIn('id', $ids)->get(['campaignName', 'spend']) as $r) {
            $pairs[] = [
                'campaignName' => (string) ($r->campaignName ?? ''),
                'spend' => (float) ($r->spend ?? 0),
            ];
        }

        return collect($pairs);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{campaignName: string, spend: float}>
     */
    private function collectTemuL30GoodsNameSpendPairs(): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('temu_campaign_reports')) {
            return collect();
        }

        $ids = DB::table('temu_campaign_reports')
            ->selectRaw('MAX(id) as id')
            ->where('report_range', 'L30')
            ->whereNotNull('goods_id')
            ->where('goods_id', '!=', '')
            ->groupBy('goods_id')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $pairs = [];
        foreach (DB::table('temu_campaign_reports')->whereIn('id', $ids)->get(['goods_name', 'spend']) as $r) {
            $pairs[] = [
                'campaignName' => (string) ($r->goods_name ?? ''),
                'spend' => (float) ($r->spend ?? 0),
            ];
        }

        return collect($pairs);
    }

    /**
     * Amazon + FBA L30 ad spend grouped by product_master color (SKU substring match in campaign name, longest SKU wins).
     *
     * @return list<array{color: string, spend: float, percent: float}>
     */
    private function getAdSpendByProductColorAmazonFamilyL30(): array
    {
        try {
            $lookup = $this->buildColorAttributionLookupForAdCharts();
            if ($lookup === null) {
                return [];
            }
            $campaigns = $this->collectAmazonFamilyL30CampaignNameSpendPairs();
            if ($campaigns->isEmpty()) {
                return [];
            }

            return $this->aggregateAdSpendByColorFromCampaignPairs(
                $campaigns,
                $lookup['pmByUpper'],
                $lookup['skuSorted']
            );
        } catch (\Throwable $e) {
            Log::warning('getAdSpendByProductColorAmazonFamilyL30: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }
    }

    /**
     * Per-channel L30 ad spend by color + combined (same attribution rules as Amazon pie).
     *
     * @return array{combined: list<array{color: string, spend: float, percent: float>>, channels: list<array{channel: string, slices: list<array{color: string, spend: float, percent: float}>>}}
     */
    private function getAdSpendByColorByChannelL30(): array
    {
        try {
            $lookup = $this->buildColorAttributionLookupForAdCharts();
            if ($lookup === null) {
                return ['combined' => [], 'channels' => []];
            }
            $pm = $lookup['pmByUpper'];
            $skus = $lookup['skuSorted'];

            $defs = [
                ['channel' => 'Amazon + FBA', 'pairs' => $this->collectAmazonFamilyL30CampaignNameSpendPairs()],
                ['channel' => 'eBay 1', 'pairs' => $this->collectEbayL30CampaignNameSpendPairs('ebay')],
                ['channel' => 'eBay 2', 'pairs' => $this->collectEbayL30CampaignNameSpendPairs('ebaytwo')],
                ['channel' => 'eBay 3', 'pairs' => $this->collectEbayL30CampaignNameSpendPairs('ebaythree')],
                ['channel' => 'Walmart', 'pairs' => $this->collectWalmartL30CampaignNameSpendPairs()],
                ['channel' => 'Temu', 'pairs' => $this->collectTemuL30GoodsNameSpendPairs()],
            ];

            $channels = [];
            $mergedPairs = collect();
            foreach ($defs as $def) {
                $pairs = $def['pairs'] instanceof \Illuminate\Support\Collection ? $def['pairs'] : collect($def['pairs']);
                if ($pairs->isEmpty()) {
                    continue;
                }
                $slices = $this->aggregateAdSpendByColorFromCampaignPairs($pairs, $pm, $skus);
                if ($slices !== []) {
                    $channels[] = [
                        'channel' => $def['channel'],
                        'slices' => $slices,
                    ];
                }
                $mergedPairs = $mergedPairs->merge($pairs);
            }

            $combined = $mergedPairs->isEmpty()
                ? []
                : $this->aggregateAdSpendByColorFromCampaignPairs($mergedPairs, $pm, $skus);

            return ['combined' => $combined, 'channels' => $channels];
        } catch (\Throwable $e) {
            Log::warning('getAdSpendByColorByChannelL30: ' . $e->getMessage(), ['exception' => $e]);

            return ['combined' => [], 'channels' => []];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $finalData
     * @return list<array{channel: string, spend: float, percent: float}>
     */
    private function buildAdSpendByChannelFromRows(array $finalData): array
    {
        $byChannel = [];
        foreach ($finalData as $row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? 'Unknown'));
            if ($name === '') {
                $name = 'Unknown';
            }
            $raw = $row['Total Ad Spend'] ?? 0;
            $spend = is_numeric($raw) ? (float) $raw : (float) str_replace(['$', ',', '%'], '', (string) $raw);
            if ($spend <= 0) {
                continue;
            }
            $byChannel[$name] = ($byChannel[$name] ?? 0.0) + $spend;
        }
        if ($byChannel === []) {
            return [];
        }
        arsort($byChannel, SORT_NUMERIC);
        $total = array_sum($byChannel);
        $out = [];
        foreach ($byChannel as $channel => $spend) {
            $spend = (float) $spend;
            $out[] = [
                'channel' => (string) $channel,
                'spend' => round($spend, 2),
                'percent' => $total > 0 ? round(($spend / $total) * 100, 1) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Build sales by channel data with percentage difference calculation
     * Shows Y Sales vs L30 daily average: ((L30 Sales / 30) - Y Sales) / (L30 Sales / 30) * 100
     * Positive % = Y Sales below average (decrease), Negative % = Y Sales above average (increase)
     */
    private function buildSalesByChannelWithPercentage(array $finalData): array
    {
        $byChannel = [];
        foreach ($finalData as $row) {
            $name = trim((string) ($row['Channel '] ?? $row['Channel'] ?? 'Unknown'));
            if ($name === '') {
                $name = 'Unknown';
            }
            
            $l30Sales = is_numeric($row['L30 Sales'] ?? 0) 
                ? (float) $row['L30 Sales'] 
                : (float) str_replace(['$', ',', '%'], '', (string) ($row['L30 Sales'] ?? 0));
            
            $ySales = is_numeric($row['Y Sales'] ?? 0)
                ? (float) $row['Y Sales']
                : (float) str_replace(['$', ',', '%'], '', (string) ($row['Y Sales'] ?? 0));
            
            $byChannel[$name] = [
                'l30_sales' => $l30Sales,
                'y_sales' => $ySales,
            ];
        }
        
        if ($byChannel === []) {
            return [];
        }
        
        // Calculate totals
        $totalL30Sales = array_sum(array_column($byChannel, 'l30_sales'));
        $totalYSales = array_sum(array_column($byChannel, 'y_sales'));
        
        // Calculate percentage difference
        $dailyAverage = $totalL30Sales / 30;
        $difference = $dailyAverage - $totalYSales;
        $percentageDiff = $dailyAverage > 0 ? round(($difference / $dailyAverage) * 100, 1) : 0.0;
        
        $out = [];
        
        // Add Y Sales data
        arsort($byChannel);
        foreach ($byChannel as $channel => $data) {
            if ($data['y_sales'] > 0) {
                $out['y_sales'][] = [
                    'channel' => (string) $channel,
                    'sales' => round($data['y_sales'], 2),
                    'percent' => $totalYSales > 0 ? round(($data['y_sales'] / $totalYSales) * 100, 1) : 0.0,
                ];
            }
        }
        
        // Add L30 Sales data (sort by L30)
        uasort($byChannel, function($a, $b) {
            return $b['l30_sales'] <=> $a['l30_sales'];
        });
        
        foreach ($byChannel as $channel => $data) {
            if ($data['l30_sales'] > 0) {
                $out['l30_sales'][] = [
                    'channel' => (string) $channel,
                    'sales' => round($data['l30_sales'], 2),
                    'percent' => $totalL30Sales > 0 ? round(($data['l30_sales'] / $totalL30Sales) * 100, 1) : 0.0,
                ];
            }
        }
        
        // Add summary with percentage difference
        $out['summary'] = [
            'total_l30_sales' => round($totalL30Sales, 2),
            'total_y_sales' => round($totalYSales, 2),
            'daily_average' => round($dailyAverage, 2),
            'percentage_diff' => $percentageDiff,
            'trend' => $percentageDiff > 0 ? 'decrease' : ($percentageDiff < 0 ? 'increase' : 'stable'),
        ];
        
        return $out;
    }

    /**
     * Sum of (Shopify inventory * LP) across all SKUs for the Inv@LP badge.
     * Uses shopify_skus.inv and product_master LP (from Values->lp or lp column).
     */
    private function getInvAtLpShopify(): float
    {
        return $this->getShopifyInvLpMetrics()['inv_at_lp'];
    }

    /**
     * Fetch Amazon ad spend breakdown (KW, PT, HL) - same logic as Amazon KW/PT/HL Utilized charts.
     * Uses daily date rows (not L30) with default range: 31 days ago to 2 days ago.
     *
     * @return array{kw: float, pt: float, hl: float}
     */
    private function fetchAmazonAdSpendBreakdownFromTables(): array
    {
        $end = Carbon::now()->subDays(2)->format('Y-m-d');
        $start = Carbon::now()->subDays(31)->format('Y-m-d');

        // KW: same as filterAmazonUtilizedChartKw - daily dates, exclude PT/FBA, exclude ARCHIVED
        $kwSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
            ->sum('spend'), 2);

        // PT: same as filterAmazonUtilizedChartPt - daily dates, PT campaigns only
        $ptSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'");
            })
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
            ->sum('spend'), 2);

        // HL: same as filterAmazonUtilizedChartHl - daily dates, amazon_sb_campaign_reports
        $hlSpent = round((float) DB::table('amazon_sb_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->sum('cost'), 2);

        return ['kw' => $kwSpent, 'pt' => $ptSpent, 'hl' => $hlSpent];
    }

    /**
     * Fetch Amazon FBA ad spend breakdown (KW, PT) - FBA-specific campaigns.
     * FBA KW: campaigns ending with 'FBA' but NOT 'FBA PT'
     * FBA PT: campaigns ending with 'FBA PT' or 'FBA PT.'
     *
     * @return array{kw: float, pt: float}
     */
    private function fetchAmazonFbaAdSpendBreakdownFromTables(): array
    {
        $end = Carbon::now()->subDays(2)->format('Y-m-d');
        $start = Carbon::now()->subDays(31)->format('Y-m-d');

        // FBA KW: campaigns ending with 'FBA' but NOT 'FBA PT' or 'FBA PT.'
        $kwSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->whereRaw("campaignName LIKE '%FBA'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
            ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
            ->sum('spend'), 2);

        // FBA PT: campaigns ending with 'FBA PT' or 'FBA PT.'
        $ptSpent = round((float) DB::table('amazon_sp_campaign_reports')
            ->whereNotNull('report_date_range')
            ->whereBetween('report_date_range', [$start, $end])
            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
            ->where(function ($q) {
                $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'");
            })
            ->sum('spend'), 2);

        return ['kw' => $kwSpent, 'pt' => $ptSpent];
    }

    /**
     * Fetch eBay KW and PMT spend from tables - same logic as Ebay KW Ads and PMT Ads pages.
     * Uses L30 rows, whereDate(updated_at >= 30 days ago), no campaignStatus filter (matches utilized pages).
     *
     * @param string $channel ebay, ebaytwo, or ebaythree
     * @return array{kw: float, pmt: float}
     */
    private function fetchEbayAdSpendBreakdownFromTables(string $channel): array
    {
        $channel = strtolower(trim($channel));
        // Use 31 days to match eBay Seller Hub "last 31 days" view
        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $kwTable = match ($channel) {
            'ebay' => 'ebay_priority_reports',
            'ebaytwo' => 'ebay_2_priority_reports',
            'ebaythree' => 'ebay_3_priority_reports',
            default => null,
        };
        $pmtTable = match ($channel) {
            'ebay' => 'ebay_general_reports',
            'ebaytwo' => 'ebay_2_general_reports',
            'ebaythree' => 'ebay_3_general_reports',
            default => null,
        };

        if (!$kwTable || !$pmtTable) {
            return ['kw' => 0.0, 'pmt' => 0.0];
        }

        // Sum daily reports (individual date report_ranges) instead of L30 aggregate
        // Daily data is closer to eBay Seller Hub dashboard values
        if ($channel === 'ebaythree') {
            $kwSpent = (float) DB::table($kwTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->get()
                ->sum(fn ($r) => (float) preg_replace('/[^\d.]/', '', $r->cpc_ad_fees_payout_currency ?? '0'));
            $pmtSpent = (float) DB::table($pmtTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->get()
                ->sum(fn ($r) => (float) preg_replace('/[^\d.]/', '', $r->ad_fees ?? '0'));
        } else {
            $kwSpent = (float) DB::table($kwTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as total')
                ->value('total') ?? 0;
            $pmtSpent = (float) DB::table($pmtTable)
                ->where('report_range', '>=', $startDate)
                ->where('report_range', '<=', $endDate)
                ->where('report_range', 'NOT LIKE', 'L%')
                ->selectRaw('COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as total')
                ->value('total') ?? 0;
        }

        return ['kw' => round($kwSpent, 2), 'pmt' => round($pmtSpent, 2)];
    }

    /**
     * Fetch AD CLICKS, AD SALES, AD SOLD directly from campaign/report tables.
     * Same tables and logic as Ad Spend for each channel.
     *
     * @param string $channel Normalized channel key (amazon, ebay, ebaytwo, ebaythree, temu, walmart, shopifyb2c, tiktokshop)
     * @return array{clicks: int, ad_sales: float, ad_sold: int}
     */
    private function fetchAdMetricsFromTables(string $channel): array
    {
        $channel = strtolower(trim($channel));
        $defaults = [
            'clicks' => 0, 'ad_sales' => 0.0, 'ad_sold' => 0,
            'KW Clicks' => 0, 'PT Clicks' => 0, 'HL Clicks' => 0, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
            'KW Sales' => 0, 'PT Sales' => 0, 'HL Sales' => 0, 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
            'KW Sold' => 0, 'PT Sold' => 0, 'HL Sold' => 0, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
            'KW ACOS' => 0, 'PT ACOS' => 0, 'HL ACOS' => 0, 'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
            'KW CVR' => 0, 'PT CVR' => 0, 'HL CVR' => 0, 'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
        ];

        try {
            switch ($channel) {
                case 'amazon': {
                    // Use LATEST L30 row per campaign (by MAX(id)) to get current values
                    // This avoids inflated values from MAX(spend) when multiple L30 rows exist per campaign

                    // KW: SP campaigns NOT ending with PT or FBA - get latest L30 row IDs
                    $kwLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $kwData = $kwLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $kwLatestIds)->get()
                        : collect();

                    // PT: SP campaigns ending with PT (not FBA PT) - get latest L30 row IDs
                    $ptLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->where(function ($q) {
                            $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'");
                        })
                        ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $ptData = $ptLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $ptLatestIds)->get()
                        : collect();

                    // HL: SB campaigns - get latest L30 row IDs
                    $hlLatestIds = DB::table('amazon_sb_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $hlData = $hlLatestIds->isNotEmpty()
                        ? DB::table('amazon_sb_campaign_reports')->whereIn('id', $hlLatestIds)->get()
                        : collect();

                    // Use 7-day attribution (sales7d, purchases7d) to match Amazon Advertising dashboard default
                    $kwC = (int) $kwData->sum('clicks'); $kwS = (float) $kwData->sum('sales7d'); $kwU = (int) $kwData->sum('purchases7d'); $kwSp = (float) $kwData->sum('spend');
                    $ptC = (int) $ptData->sum('clicks'); $ptS = (float) $ptData->sum('sales7d'); $ptU = (int) $ptData->sum('purchases7d'); $ptSp = (float) $ptData->sum('spend');
                    $hlC = (int) $hlData->sum('clicks'); $hlS = (float) $hlData->sum('sales'); $hlU = (int) $hlData->sum('purchases'); $hlSp = (float) $hlData->sum('cost');

                    $totalSpend = round($kwSp + $ptSp + $hlSp, 2);

                    return [
                        'clicks' => $kwC + $ptC + $hlC, 'ad_sales' => round($kwS + $ptS + $hlS, 2), 'ad_sold' => $kwU + $ptU + $hlU,
                        'KW Clicks' => $kwC, 'PT Clicks' => $ptC, 'HL Clicks' => $hlC, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => round($ptS, 2), 'HL Sales' => round($hlS, 2), 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => $ptU, 'HL Sold' => $hlU, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => round($ptSp, 2), 'HL Spent' => round($hlSp, 2),
                        'PMT Spent' => 0, 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => $totalSpend,
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => $ptS > 0 ? round(($ptSp / $ptS) * 100, 1) : 0,
                        'HL ACOS' => $hlS > 0 ? round(($hlSp / $hlS) * 100, 1) : 0,
                        'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => $ptC > 0 ? round(($ptU / $ptC) * 100, 1) : 0,
                        'HL CVR' => $hlC > 0 ? round(($hlU / $hlC) * 100, 1) : 0,
                        'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'amazonfba': {
                    // Use LATEST L30 row per campaign (by MAX(id)) to get current values
                    // KW FBA: SP campaigns ending with FBA (not FBA PT)
                    $kwLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->whereRaw("campaignName LIKE '%FBA'")
                        ->whereRaw("campaignName NOT LIKE '%FBA PT%'")
                        ->whereRaw("campaignName NOT LIKE '%FBA PT.%'")
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $kwData = $kwLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $kwLatestIds)->get()
                        : collect();

                    // PT FBA: SP campaigns ending with FBA PT
                    $ptLatestIds = DB::table('amazon_sp_campaign_reports')
                        ->selectRaw('MAX(id) as id')
                        ->where('report_date_range', 'L30')
                        ->where(function ($q) {
                            $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'");
                        })
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->groupBy('campaignName')
                        ->pluck('id');
                    $ptData = $ptLatestIds->isNotEmpty()
                        ? DB::table('amazon_sp_campaign_reports')->whereIn('id', $ptLatestIds)->get()
                        : collect();

                    // Use 7-day attribution (sales7d, purchases7d) to match Amazon Advertising dashboard default
                    $kwC = (int) $kwData->sum('clicks'); $kwS = (float) $kwData->sum('sales7d'); $kwU = (int) $kwData->sum('purchases7d'); $kwSp = (float) $kwData->sum('spend');
                    $ptC = (int) $ptData->sum('clicks'); $ptS = (float) $ptData->sum('sales7d'); $ptU = (int) $ptData->sum('purchases7d'); $ptSp = (float) $ptData->sum('spend');

                    $totalSpend = round($kwSp + $ptSp, 2);

                    return [
                        'clicks' => $kwC + $ptC, 'ad_sales' => round($kwS + $ptS, 2), 'ad_sold' => $kwU + $ptU,
                        'KW Clicks' => $kwC, 'PT Clicks' => $ptC, 'HL Clicks' => 0, 'PMT Clicks' => 0, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => round($ptS, 2), 'HL Sales' => 0, 'PMT Sales' => 0, 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => $ptU, 'HL Sold' => 0, 'PMT Sold' => 0, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => round($ptSp, 2), 'HL Spent' => 0,
                        'PMT Spent' => 0, 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => $totalSpend,
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => $ptS > 0 ? round(($ptSp / $ptS) * 100, 1) : 0,
                        'HL ACOS' => 0, 'PMT ACOS' => 0, 'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => $ptC > 0 ? round(($ptU / $ptC) * 100, 1) : 0,
                        'HL CVR' => 0, 'PMT CVR' => 0, 'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'ebay':
                case 'ebaytwo':
                case 'ebaythree': {
                    // Use daily data sum (individual date report_ranges) instead of L30 aggregate
                    // Daily data is closer to eBay Seller Hub dashboard values
                    $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                    $endDate = Carbon::now()->format('Y-m-d');
                    $kwTable = match ($channel) {
                        'ebay' => 'ebay_priority_reports',
                        'ebaytwo' => 'ebay_2_priority_reports',
                        'ebaythree' => 'ebay_3_priority_reports',
                        default => null,
                    };
                    $pmtTable = match ($channel) {
                        'ebay' => 'ebay_general_reports',
                        'ebaytwo' => 'ebay_2_general_reports',
                        'ebaythree' => 'ebay_3_general_reports',
                        default => null,
                    };
                    if (!$kwTable || !$pmtTable) return $defaults;

                    $kw = DB::table($kwTable)
                        ->where('report_range', '>=', $startDate)
                        ->where('report_range', '<=', $endDate)
                        ->where('report_range', 'NOT LIKE', 'L%')
                        ->selectRaw('COALESCE(SUM(cpc_clicks), 0) as c, COALESCE(SUM(REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")), 0) as s, COALESCE(SUM(cpc_attributed_sales), 0) as u, COALESCE(SUM(REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")), 0) as sp')
                        ->first();
                    $pmt = DB::table($pmtTable)
                        ->where('report_range', '>=', $startDate)
                        ->where('report_range', '<=', $endDate)
                        ->where('report_range', 'NOT LIKE', 'L%')
                        ->selectRaw('COALESCE(SUM(clicks), 0) as c, COALESCE(SUM(REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")), 0) as s, COALESCE(SUM(sales), 0) as u, COALESCE(SUM(REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")), 0) as sp')
                        ->first();

                    $kwC = (int) ($kw->c ?? 0); $kwS = (float) ($kw->s ?? 0); $kwU = (int) ($kw->u ?? 0); $kwSp = (float) ($kw->sp ?? 0);
                    $pmtC = (int) ($pmt->c ?? 0); $pmtS = (float) ($pmt->s ?? 0); $pmtU = (int) ($pmt->u ?? 0); $pmtSp = (float) ($pmt->sp ?? 0);

                    return [
                        'clicks' => $kwC + $pmtC, 'ad_sales' => round($kwS + $pmtS, 2), 'ad_sold' => $kwU + $pmtU,
                        'KW Clicks' => $kwC, 'PT Clicks' => 0, 'HL Clicks' => 0, 'PMT Clicks' => $pmtC, 'Shopping Clicks' => 0, 'SERP Clicks' => 0,
                        'KW Sales' => round($kwS, 2), 'PT Sales' => 0, 'HL Sales' => 0, 'PMT Sales' => round($pmtS, 2), 'Shopping Sales' => 0, 'SERP Sales' => 0,
                        'KW Sold' => $kwU, 'PT Sold' => 0, 'HL Sold' => 0, 'PMT Sold' => $pmtU, 'Shopping Sold' => 0, 'SERP Sold' => 0,
                        'KW Spent' => round($kwSp, 2), 'PT Spent' => 0, 'HL Spent' => 0,
                        'PMT Spent' => round($pmtSp, 2), 'Shopping Spent' => 0, 'SERP Spent' => 0,
                        'Total Ad Spend' => round($kwSp + $pmtSp, 2),
                        'KW ACOS' => $kwS > 0 ? round(($kwSp / $kwS) * 100, 1) : 0,
                        'PT ACOS' => 0, 'HL ACOS' => 0,
                        'PMT ACOS' => $pmtS > 0 ? round(($pmtSp / $pmtS) * 100, 1) : 0,
                        'Shopping ACOS' => 0, 'SERP ACOS' => 0,
                        'KW CVR' => $kwC > 0 ? round(($kwU / $kwC) * 100, 1) : 0,
                        'PT CVR' => 0, 'HL CVR' => 0,
                        'PMT CVR' => $pmtC > 0 ? round(($pmtU / $pmtC) * 100, 1) : 0,
                        'Shopping CVR' => 0, 'SERP CVR' => 0,
                    ];
                }

                case 'temu': {
                    // Compute totals exactly like temu-decrease endpoint + frontend badges.
                    $request = \Illuminate\Http\Request::create('/temu-decrease-data', 'GET');
                    $temuCtrl = app(\App\Http\Controllers\MarketPlace\TemuController::class);
                    $response = $temuCtrl->getTemuDecreaseData($request);
                    $responseData = json_decode($response->getContent(), true);
                    if (!is_array($responseData)) {
                        return $defaults;
                    }

                    // Prefer the file totals returned in `ad_totals` (computed
                    // directly from temu_campaign_reports for the active range).
                    // The previous implementation summed per-row over the
                    // ProductMaster-matched rows in `data`, which silently dropped
                    // any campaign row whose goods_id wasn't in temu_pricing AND
                    // whose SKU column was empty — producing badges below the
                    // actual upload total. We still fall back to per-row sums for
                    // older deploys / cached responses that don't include
                    // ad_totals yet.
                    $adTotals = is_array($responseData['ad_totals'] ?? null)
                        ? $responseData['ad_totals']
                        : null;

                    if ($adTotals !== null) {
                        $sp = round((float) ($adTotals['spend'] ?? 0), 2);
                        $c  = (int) ($adTotals['clicks'] ?? 0);
                        $s  = round((float) ($adTotals['base_price_sales'] ?? 0), 2);
                        $u  = (int) ($adTotals['sub_orders'] ?? 0);
                        $spSnapshot = $sp;
                    } else {
                        $rows = $responseData['data'] ?? [];
                        $sp = 0; $spSnapshot = 0; $c = 0; $s = 0; $u = 0;
                        foreach ($rows as $row) {
                            if (empty($row['sku'])) continue;
                            $sp += round((float) ($row['spend_l30'] ?? 0), 2);
                            $spSnapshot += round((float) ($row['spend'] ?? 0), 2);
                            $c += (int) ($row['clicks_l30'] ?? 0);
                            $s += round((float) ($row['ad_sales_l30'] ?? 0), 2);
                            $u += (int) ($row['ad_sold_l30'] ?? 0);
                        }
                    }

                    // Same ads% source priority as temu_decrease badge:
                    // 1) backend aggregate_ads_percent (when positive),
                    // 2) computed using spend_l30, else spend snapshot over sales_summary revenue.
                    $aggregateAds = (float) ($responseData['aggregate_ads_percent'] ?? 0);
                    $salesSummaryRevenue = (float) (($responseData['sales_summary']['total_revenue'] ?? 0));
                    $spendForAdsPercent = $sp > 0 ? $sp : $spSnapshot;
                    $adsPercent = $aggregateAds > 0
                        ? $aggregateAds
                        : ($salesSummaryRevenue > 0 ? ($spendForAdsPercent / $salesSummaryRevenue) * 100 : 0);

                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => $u,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2), 'KW Sold' => $u,
                        'KW Spent' => round($spendForAdsPercent, 2), 'Total Ad Spend' => round($spendForAdsPercent, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                        'KW CVR' => $c > 0 ? round(($u / $c) * 100, 1) : 0,
                        'Ads%' => round($adsPercent, 2),
                    ]);
                }

                case 'temu2':
                    // Temu 2: no ad data table; return zeros
                    return $defaults;

                case 'topdawg':
                    return $defaults;

                case 'walmart': {
                    $row = DB::table('walmart_campaign_reports')
                        ->where('report_range', 'L30')
                        ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                        ->whereNotNull('campaignName')->where('campaignName', '!=', '')
                        ->selectRaw('COALESCE(SUM(clicks), 0) as c, COALESCE(SUM(sales), 0) as s, COALESCE(SUM(spend), 0) as sp')
                        ->first();
                    $c = (int) ($row->c ?? 0); $s = (float) ($row->s ?? 0); $sp = (float) ($row->sp ?? 0);
                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => 0,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2),
                        'KW Spent' => round($sp, 2), 'Total Ad Spend' => round($sp, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                    ]);
                }

                case 'shopifyb2c': {
                    $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
                    $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                    // Shopping ads
                    $shopping = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->where('campaign_status', 'ENABLED')
                        ->selectRaw('COALESCE(SUM(metrics_clicks), 0) as c, COALESCE(SUM(ga4_ad_sales), 0) as s, COALESCE(SUM(ga4_sold_units), 0) as u, COALESCE(SUM(metrics_cost_micros), 0) as sp')
                        ->first();
                    // SERP (Search) ads
                    $serp = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SEARCH')
                        ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                        ->selectRaw('COALESCE(SUM(metrics_clicks), 0) as c, COALESCE(SUM(ga4_ad_sales), 0) as s, COALESCE(SUM(ga4_sold_units), 0) as u, COALESCE(SUM(metrics_cost_micros), 0) as sp')
                        ->first();
                    $shpC = (int) ($shopping->c ?? 0); $shpS = (float) ($shopping->s ?? 0); $shpU = (int) ($shopping->u ?? 0); $shpSp = (float) (($shopping->sp ?? 0) / 1000000);
                    $serpC = (int) ($serp->c ?? 0); $serpS = (float) ($serp->s ?? 0); $serpU = (int) ($serp->u ?? 0); $serpSp = (float) (($serp->sp ?? 0) / 1000000);
                    return array_merge($defaults, [
                        'clicks' => $shpC + $serpC, 'ad_sales' => round($shpS + $serpS, 2), 'ad_sold' => $shpU + $serpU,
                        'Shopping Clicks' => $shpC, 'SERP Clicks' => $serpC,
                        'Shopping Sales' => round($shpS, 2), 'SERP Sales' => round($serpS, 2),
                        'Shopping Sold' => $shpU, 'SERP Sold' => $serpU,
                        'Shopping Spent' => round($shpSp, 2), 'SERP Spent' => round($serpSp, 2),
                        'Total Ad Spend' => round($shpSp + $serpSp, 2),
                        'Shopping ACOS' => $shpS > 0 ? round(($shpSp / $shpS) * 100, 1) : 0,
                        'SERP ACOS' => $serpS > 0 ? round(($serpSp / $serpS) * 100, 1) : 0,
                        'Shopping CVR' => $shpC > 0 ? round(($shpU / $shpC) * 100, 1) : 0,
                        'SERP CVR' => $serpC > 0 ? round(($serpU / $serpC) * 100, 1) : 0,
                    ]);
                }

                case 'tiktokshop': {
                    // Compute totals from tiktok/utilized endpoint (matches tiktok-pricing & tiktok/utilized pages)
                    $ttCtrl = app(\App\Http\Controllers\Campaigns\TiktokAdsController::class);
                    $ttReq = \Illuminate\Http\Request::create('/tiktok/utilized/data', 'GET');
                    $ttResp = $ttCtrl->getUtilizedData($ttReq);
                    $ttRows = json_decode($ttResp->getContent(), true)['data'] ?? [];
                    $sp = 0; $c = 0; $s = 0; $u = 0;
                    foreach ($ttRows as $tr) {
                        $spend = (float) ($tr['spend'] ?? 0);
                        $outRoas = (float) ($tr['out_roas'] ?? 0);
                        $sp += $spend;
                        $c += (int) ($tr['ad_clicks'] ?? 0);
                        $u += (int) ($tr['ad_sold'] ?? 0);
                        if ($outRoas > 0 && $spend > 0) $s += $spend * $outRoas;
                    }
                    return array_merge($defaults, [
                        'clicks' => $c, 'ad_sales' => round($s, 2), 'ad_sold' => $u,
                        'KW Clicks' => $c, 'KW Sales' => round($s, 2), 'KW Sold' => $u,
                        'KW Spent' => round($sp, 2), 'Total Ad Spend' => round($sp, 2),
                        'KW ACOS' => $s > 0 ? round(($sp / $s) * 100, 1) : 0,
                        'KW CVR' => $c > 0 ? round(($u / $c) * 100, 1) : 0,
                    ]);
                }

                case 'tiktokshop2':
                    // TikTok 2: upload-based, no ads
                    return $defaults;

                case 'depop':
                    return $defaults;

                default:
                    return $defaults;
            }
        } catch (\Throwable $e) {
            \Log::error('fetchAdMetricsFromTables error: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Fetch Total Ad Spend directly from campaign/report tables (L30).
     * Amazon KW+PT: amazon_sp_campaign_reports, HL: amazon_sb_campaign_reports
     * eBay 1/2/3: ebay_*_priority_reports (KW) + ebay_*_general_reports (PMT)
     * Temu: temu_campaign_reports, Walmart: walmart_campaign_reports
     * Google/Shopify B2C: google_ads_campaigns, TikTok: tiktok_campaign_reports
     *
     * @param string $channel Normalized channel key (amazon, ebay, ebaytwo, ebaythree, temu, walmart, shopifyb2c, tiktokshop)
     * @return float Total Ad Spend for the channel
     */
    private function fetchTotalAdSpendFromTables(string $channel): float
    {
        $channel = strtolower(trim($channel));

        switch ($channel) {
            case 'amazon':
                $breakdown = $this->fetchAmazonAdSpendBreakdownFromTables();
                return round($breakdown['kw'] + $breakdown['pt'] + $breakdown['hl'], 2);

            case 'ebay':
            case 'ebaytwo':
            case 'ebaythree':
                $breakdown = $this->fetchEbayAdSpendBreakdownFromTables($channel);
                return round($breakdown['kw'] + $breakdown['pmt'], 2);

            case 'temu':
                // Use same logic as temu-decrease endpoint
                $metrics = $this->fetchAdMetricsFromTables('temu');
                return round((float) ($metrics['Total Ad Spend'] ?? 0), 2);

            case 'temu2':
                return 0.0;

            case 'topdawg':
                return 0.0;

            case 'walmart':
                $walmartSpentData = DB::table('walmart_campaign_reports')
                    ->selectRaw('campaignName, MAX(spend) as max_spend')
                    ->where('report_range', 'L30')
                    ->whereRaw("(status IS NULL OR status != 'ARCHIVED')")
                    ->whereNotNull('campaignName')
                    ->where('campaignName', '!=', '')
                    ->groupBy('campaignName')
                    ->get();
                return round($walmartSpentData->sum('max_spend') ?? 0, 2);

            case 'shopifyb2c':
                // Same logic as G-Shopping Utilized (filterGoogleShoppingChart): date range 31 days ago to 2 days ago, ENABLED SHOPPING only
                $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
                $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
                return round(
                    (float) DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $startDate)
                        ->whereDate('date', '<=', $endDate)
                        ->where('advertising_channel_type', 'SHOPPING')
                        ->where('campaign_status', 'ENABLED')
                        ->sum('metrics_cost_micros') / 1000000,
                    2
                );

            case 'tiktokshop':
                // Use same logic as fetchAdMetricsFromTables for consistency
                $metrics = $this->fetchAdMetricsFromTables('tiktokshop');
                return $metrics['Total Ad Spend'] ?? 0.0;

            case 'tiktokshop2':
                return 0.0;

            case 'depop':
                return 0.0;

            default:
                return 0.0;
        }
    }

    /**
     * Handle dynamic route parameters and return a view.
     */
    public function channel_master_index(Request $request, $first = null, $second = null)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        if ($first === "assets") {
            return redirect('home');
        }

        // return view($first, compact('mode', 'demo', 'second', 'channels'));
        return view($first . '.' . $second, [
            'mode' => $mode,
            'demo' => $demo,
        ]);
    }

    /**
     * Display All Marketplace Master view (Tabulator-based)
     */
    public function allMarketplaceMaster(Request $request)
    {
        return view('channels.all-marketplace-master');
    }


    public function getEbaytwoMasterAdsPercent(): float
    {
        $metrics = $this->fetchAdMetricsFromTables('ebaytwo');
        $totalAdSpend = (float) ($metrics['Total Ad Spend'] ?? 0);
        $m = MarketplaceDailyMetric::where('channel', 'eBay 2')->latest('date')->first();
        $l30SalesVal = (float) ($m->total_sales ?? 0);

        return $l30SalesVal > 0 ? round(($totalAdSpend / $l30SalesVal) * 100, 2) : 0.0;
    }

 
    public function getEbayMasterAdsPercent(): float
    {
        $metrics = $this->fetchAdMetricsFromTables('ebay');
        $totalAdSpend = (float) ($metrics['Total Ad Spend'] ?? 0);
        $m = MarketplaceDailyMetric::where('channel', 'eBay')->latest('date')->first();
        $l30SalesVal = (float) ($m->total_sales ?? 0);

        return $l30SalesVal > 0 ? round(($totalAdSpend / $l30SalesVal) * 100, 4) : 0.0;
    }

    /**
     * eBay 3 channel Ads% — same as getViewChannelData / all-marketplace-master for channel eBay 3.
     */
    public function getEbaythreeMasterAdsPercent(): float
    {
        $metrics = $this->fetchAdMetricsFromTables('ebaythree');
        $totalAdSpend = (float) ($metrics['Total Ad Spend'] ?? 0);
        $m = MarketplaceDailyMetric::where('channel', 'eBay 3')->latest('date')->first();
        $l30SalesVal = (float) ($m->total_sales ?? 0);

        return $l30SalesVal > 0 ? round(($totalAdSpend / $l30SalesVal) * 100, 4) : 0.0;
    }

    /**
     * Fast method: Get channel data from pre-calculated table
     * This method reads from channel_master_calculated_data table which is updated daily
     * Much faster than calculating on-the-fly
     */
    public function getViewChannelDataFast(Request $request)
    {
        try {
            // Check if we have fresh calculated data
            if (!\App\Models\ChannelMasterCalculatedData::isDataFresh()) {
                \Log::warning('Channel calculated data is not fresh, consider running: php artisan channel:calculate-data');
                // Fallback to old method if data is stale
                return $this->getViewChannelData($request);
            }
            
            // Get pagination parameters
            $page = (int) $request->input('page', 1);
            $size = (int) $request->input('size', 50);
            
            // Get data from pre-calculated table
            $query = \App\Models\ChannelMasterCalculatedData::query()
                ->orderBy('l30_sales', 'desc');
            
            // Apply type filter if needed (from frontend section filter)
            $section = $request->input('section');
            if ($section && in_array($section, ['B2C', 'B2B', 'Dropship'])) {
                $query->where('type', $section);
            }
            
            // Get total count
            $total = $query->count();
            
            // Get paginated data
            $offset = ($page - 1) * $size;
            $channels = $query->skip($offset)->take($size)->get();

            // Build channel-name -> logo / seller_link maps from channel_master so the
            // pre-calculated table doesn't need extra columns of its own.
            $logoMap = [];
            $sellerLinkMap = [];
            $aliasMap = [];
            $promotionsMap = [];
            $complianceCountMap = [];
            $hasLogo = Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasColumn('channel_master', 'seller_link');
            $hasAlias = Schema::hasColumn('channel_master', 'alias');
            $hasPromotions = Schema::hasColumn('channel_master', 'promotions');
            $hasComplianceCount = Schema::hasColumn('channel_master', 'compliance_count');

            if ($hasLogo || $hasSellerLink || $hasAlias || $hasPromotions || $hasComplianceCount) {
                $select = ['channel', 'status'];
                if ($hasLogo) $select[] = 'logo';
                if ($hasSellerLink) $select[] = 'seller_link';
                if ($hasAlias) $select[] = 'alias';
                if ($hasPromotions) $select[] = 'promotions';
                if ($hasComplianceCount) $select[] = 'compliance_count';

                // Load every channel_master row and key by a canonical name so
                // duplicate/aliased rows resolve correctly. Active rows are taken
                // first, and an empty logo/link never overwrites a real one.
                $rows = ChannelMaster::query()
                    ->orderByRaw("CASE WHEN LOWER(TRIM(status)) = 'active' THEN 0 ELSE 1 END")
                    ->orderBy('id', 'asc')
                    ->get($select);

                foreach ($rows as $r) {
                    $key = $this->canonicalChannelKey($r->channel);
                    if ($hasLogo && !empty($r->logo) && empty($logoMap[$key])) {
                        $logoMap[$key] = $r->logo;
                    }
                    if ($hasSellerLink && !empty($r->seller_link) && empty($sellerLinkMap[$key])) {
                        $sellerLinkMap[$key] = $r->seller_link;
                    }
                    if ($hasAlias && !empty($r->alias) && empty($aliasMap[$key])) {
                        $aliasMap[$key] = $r->alias;
                    }
                    if ($hasPromotions && $r->promotions !== null && !isset($promotionsMap[$key])) {
                        $promotionsMap[$key] = $r->promotions;
                    }
                    if ($hasComplianceCount && $r->compliance_count !== null && !isset($complianceCountMap[$key])) {
                        $complianceCountMap[$key] = $r->compliance_count;
                    }
                }
            }

            // Format data for frontend (match expected format)
            $formattedData = $channels->map(function($channel) use ($logoMap, $sellerLinkMap, $aliasMap, $promotionsMap, $complianceCountMap) {
                $canonicalKey = $this->canonicalChannelKey($channel->channel);
                return [
                    'Channel ' => $channel->channel,
                    'alias' => $aliasMap[$canonicalKey] ?? null,
                    'promotions' => $promotionsMap[$canonicalKey] ?? null,
                    'compliance_count' => $complianceCountMap[$canonicalKey] ?? null,
                    'logo' => $logoMap[$canonicalKey] ?? null,
                    'seller_link' => $sellerLinkMap[$canonicalKey] ?? null,
                    'sheet_link' => $channel->sheet_link,
                    'channel_percentage' => $channel->channel_percentage,
                    'type' => $channel->type,
                    'base' => $channel->base,
                    'target' => $channel->target,
                    'missing_link' => $channel->missing_link,
                    'addition_sheet' => $channel->addition_sheet,
                    
                    'L-60 Sales' => (int) $channel->l60_sales,
                    'L30 Sales' => (int) $channel->l30_sales,
                    'Y Sales' => $channel->yesterday_sales,
                    'L7 Sales' => $channel->l7_sales,
                    'Growth' => round($channel->growth, 2) . '%',
                    'L7 vs 30 pace %' => $channel->l7_vs_30_pace,
                    
                    'L60 Orders' => $channel->l60_orders,
                    'L30 Orders' => $channel->l30_orders,
                    'Qty' => $channel->total_quantity,
                    
                    'Gprofit%' => round($channel->gprofit_pct, 2) . '%',
                    'gprofitL60' => round($channel->gprofit_l60, 2) . '%',
                    'G Roi' => round($channel->g_roi, 2),
                    'G RoiL60' => round($channel->g_roi_l60, 2),
                    'Total PFT' => round($channel->total_profit, 2),
                    'N PFT' => round($channel->n_pft, 2) . '%',
                    'N ROI' => round($channel->n_roi, 2),
                    'TACOS' => round($channel->tacos_percentage, 2) . '%',
                    'cogs' => round($channel->cogs, 2),
                    
                    'Total Ad Spend' => round($channel->total_ad_spend, 2),
                    'Ads%' => round($channel->ads_percentage, 2) . '%',
                    'Clicks' => $channel->clicks,
                    'Ad Sold' => $channel->ad_sold,
                    'Ad Sales' => round($channel->ad_sales, 2),
                    'Ads CVR' => round($channel->cvr, 2),
                    'ACOS' => round($channel->acos, 2),
                    'Missing Ads' => $channel->missing_ads,
                    
                    'KW Clicks' => $channel->kw_clicks,
                    'PT Clicks' => $channel->pt_clicks,
                    'HL Clicks' => $channel->hl_clicks,
                    'PMT Clicks' => $channel->pmt_clicks,
                    'Shopping Clicks' => $channel->shopping_clicks,
                    'SERP Clicks' => $channel->serp_clicks,
                    
                    'KW Sales' => $channel->kw_sales,
                    'PT Sales' => $channel->pt_sales,
                    'HL Sales' => $channel->hl_sales,
                    'PMT Sales' => $channel->pmt_sales,
                    'Shopping Sales' => $channel->shopping_sales,
                    'SERP Sales' => $channel->serp_sales,
                    
                    'KW Sold' => $channel->kw_sold,
                    'PT Sold' => $channel->pt_sold,
                    'HL Sold' => $channel->hl_sold,
                    'PMT Sold' => $channel->pmt_sold,
                    'Shopping Sold' => $channel->shopping_sold,
                    'SERP Sold' => $channel->serp_sold,
                    
                    'KW ACOS' => $channel->kw_acos,
                    'PT ACOS' => $channel->pt_acos,
                    'HL ACOS' => $channel->hl_acos,
                    'PMT ACOS' => $channel->pmt_acos,
                    'Shopping ACOS' => $channel->shopping_acos,
                    'SERP ACOS' => $channel->serp_acos,
                    
                    'KW CVR' => $channel->kw_cvr,
                    'PT CVR' => $channel->pt_cvr,
                    'HL CVR' => $channel->hl_cvr,
                    'PMT CVR' => $channel->pmt_cvr,
                    'Shopping CVR' => $channel->shopping_cvr,
                    'SERP CVR' => $channel->serp_cvr,
                    
                    'listed_count' => $channel->listed_count,
                    'W/Ads' => $channel->w_ads,
                    'Map' => $channel->map,
                    'Miss' => $channel->miss,
                    'NMap' => $channel->nmap,
                    'Total Views' => $channel->total_views,
                    
                    'NR' => $channel->nr,
                    'Update' => $channel->update_flag,
                    'red_margin' => $channel->red_margin,
                    
                    'Account health' => $channel->account_health['data'] ?? null,
                    'Reviews' => $channel->reviews_data['data'] ?? null,
                ];
            })->toArray();

            // Map/Miss/NMap: overlay live pricing-page counts so badges match macys-pricing (etc.)
            $formattedData = $this->overlayLiveMapMissNMapOnChannelRows($formattedData);
            // Temu / Temu 2: overlay live L30/Y/L7 sales from tabulator (cached table can lag metrics sync)
            $formattedData = $this->overlayLiveTemuMetricsOnChannelRows($formattedData);
            // eBay 1/2/3: overlay L30 metrics + L60 orders (matches fetch-ebay-orders + update-marketplace-daily-metrics)
            $formattedData = $this->overlayLiveEbayMetricsOnChannelRows($formattedData);
            // Purchasing Power: overlay live L30/L60 from Shopify (matches /purchasing-power-sales)
            $formattedData = $this->overlayLivePurchasingPowerMetricsOnChannelRows($formattedData);
            // Shopify: overlay live Net Sales from shopify_raw_orders (matches /shopify Net Sales card)
            $formattedData = $this->overlayLiveShopifyDirectMetricsOnChannelRows($formattedData);
            $formattedData = $this->applyDefaultMissingLinks($formattedData);
            
            // Get summary data from cache
            $summaryData = \Cache::get('channel_master_summary_data', []);
            
            return response()->json([
                'status' => 200,
                'message' => 'Channel data fetched successfully (from pre-calculated table)',
                'data' => $formattedData,
                'last_page' => ceil($total / $size),
                'total_count' => $total,
                'inventory_value_amazon' => $summaryData['inventory_value_amazon'] ?? 0,
                'inv_at_lp' => $summaryData['inv_at_lp'] ?? 0,
                'shopify_inv_sum' => $summaryData['shopify_inv_sum'] ?? 0,
                'shopify_weighted_avg_lp' => $summaryData['shopify_weighted_avg_lp'] ?? 0,
                'inventory_by_color' => $summaryData['inventory_by_color'] ?? [],
                'stock_availability' => $summaryData['stock_availability'] ?? ['zero_stock' => 0, 'in_stock' => 0],
                'ad_spend_by_channel' => $summaryData['ad_spend_by_channel'] ?? [],
                'sales_by_channel' => $summaryData['sales_by_channel'] ?? [],
                'ad_spend_by_color_amazon' => $summaryData['ad_spend_by_color_amazon'] ?? [],
                'ad_spend_by_color_by_channel' => $summaryData['ad_spend_by_color_by_channel'] ?? [],
                'calculated_at' => $summaryData['calculated_at'] ?? null,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching fast channel data: ' . $e->getMessage());
            // Fallback to old method
            return $this->getViewChannelData($request);
        }
    }

    /**
     * Original method: Calculate channel data on-the-fly (SLOW)
     * Used as fallback when pre-calculated data is not available
     */
    public function getViewChannelData(Request $request)
    {
        // Fetch both channel and sheet_link from ChannelMaster
        $columns = ['channel', 'sheet_link', 'channel_percentage', 'type'];
        
        // Check optional columns before adding them
        if (Schema::hasColumn('channel_master', 'alias')) {
            $columns[] = 'alias';
        }
        if (Schema::hasColumn('channel_master', 'promotions')) {
            $columns[] = 'promotions';
        }
        if (Schema::hasColumn('channel_master', 'compliance_count')) {
            $columns[] = 'compliance_count';
        }
        if (Schema::hasColumn('channel_master', 'base')) {
            $columns[] = 'base';
        }
        if (Schema::hasColumn('channel_master', 'target')) {
            $columns[] = 'target';
        }
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $columns[] = 'missing_link';
        }
        if (Schema::hasColumn('channel_master', 'addition_sheet')) {
            $columns[] = 'addition_sheet';
        }
        if (Schema::hasColumn('channel_master', 'logo')) {
            $columns[] = 'logo';
        }
        if (Schema::hasColumn('channel_master', 'seller_link')) {
            $columns[] = 'seller_link';
        }
        
        $channels = ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->orderBy('type', 'asc')
            ->orderBy('id', 'asc')
            ->get($columns);

        if ($channels->isEmpty()) {
            return [
                'status'  => 200,
                'message' => 'No active channel found',
                'data'    => [],
            ];
        }

        // Get clicks data from adv_masters_data table
        $advMastersData = \App\Models\ADVMastersData::all()->keyBy('channel');

        $finalData = [];
        $l7Summaries = [];

        // ── Yesterday Sales — same source as the dot chart ────────────────────
        // The dot chart reads from channel_master_summaries (snapshot_date per channel).
        // Each daily snapshot stores l30_sales. We fetch yesterday's snapshot per channel.
        $yesterday = \Carbon\Carbon::yesterday('America/Los_Angeles')->toDateString();

        $yesterdaySummaries = \App\Models\ChannelMasterSummary::whereDate('snapshot_date', $yesterday)
            ->get(['channel', 'summary_data'])
            ->mapWithKeys(function ($row) {
                $sd = is_array($row->summary_data) ? $row->summary_data : [];
                return [strtolower(str_replace([' ', '-', '&', '/'], '', trim($row->channel))) => (float) ($sd['l30_sales'] ?? 0)];
            })
            ->toArray();

        // Fallback: if no yesterday snapshot exists, use the most-recent available snapshot
        if (empty($yesterdaySummaries)) {
            $yesterdaySummaries = \App\Models\ChannelMasterSummary::orderByDesc('snapshot_date')
                ->get(['channel', 'summary_data'])
                ->unique('channel')
                ->mapWithKeys(function ($row) {
                    $sd = is_array($row->summary_data) ? $row->summary_data : [];
                    return [strtolower(str_replace([' ', '-', '&', '/'], '', trim($row->channel))) => (float) ($sd['l30_sales'] ?? 0)];
                })
                ->toArray();
        }
        // ─────────────────────────────────────────────────────────────────────

        // Amazon Y Sales & L7 Sales — anchored to WALL-CLOCK yesterday in America/Los_Angeles.
        // Definition: if California "today" is Jun 15 (still in progress), Y Sales = full
        // Pacific Jun 14 — independent of whether today's orders have been synced yet.
        // Previously we used (latest order_date − 1 day), which slipped a day back whenever
        // the Amazon order sync lagged: with no Jun 15 rows yet, "yesterday" became Jun 13
        // instead of Jun 14, and the badge showed a too-small number (e.g. $1,358).
        //
        // Uses AmazonOrder::badgeTotalSalesByOrderDate so the formula and status filter
        // match the Amazon Daily Sales badge exactly (AMAZON_SALES_TOTAL_MODE; both
        // 'Canceled' and 'Cancelled' excluded).
        try {
            // Today PT (start of day) — passed to pacificL7WindowEndingYesterday which subtracts
            // a day internally. Y Sales uses the explicit start/end-of-day for yesterday PT.
            $todayPacific = Carbon::now('America/Los_Angeles')->startOfDay();
            $yesterdayPacific = $todayPacific->copy()->subDay();
            $yStartPacific = $yesterdayPacific->copy()->startOfDay();
            $yEndPacific   = $yesterdayPacific->copy()->endOfDay();

            $amazonYSales = AmazonOrder::badgeTotalSalesByOrderDate($yStartPacific, $yEndPacific);
            $yesterdaySummaries['amazon'] = round($amazonYSales, 2);

            [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($todayPacific);
            $amazonL7Sales = AmazonOrder::badgeTotalSalesByOrderDate($l7StartPacific, $l7EndPacific);
            $l7Summaries['amazon'] = round($amazonL7Sales, 2);
        } catch (\Throwable $e) {
            Log::warning('Amazon Y Sales calculation failed: ' . $e->getMessage());
        }

        // Temu / Temu 2 Y Sales: same relative "yesterday" as Amazon (day before latest purchase_date),
        // sum of FB price × qty for ProductMaster rows — matches L30 Sales source (marketplace_daily_metrics).
        $temuY = $this->computeTemuYSalesLikeAmazon(false);
        if ($temuY !== null) {
            $yesterdaySummaries['temu'] = $temuY;
        }
        $temu2Y = $this->computeTemuYSalesLikeAmazon(true);
        if ($temu2Y !== null) {
            $yesterdaySummaries['temu2'] = $temu2Y;
        }

        // eBay 1 / 2 / 3 Y Sales: same relative "yesterday" as Amazon (day before latest order time in Pacific).
        // Revenue = sum of line totals (matches UpdateMarketplaceDailyMetrics: items.price is line total).
        try {
            $ebayY = $this->computeEbayYSalesLikeAmazon(1);
            if ($ebayY !== null) {
                $yesterdaySummaries['ebay'] = $ebayY;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay Y Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $ebay2Y = $this->computeEbayYSalesLikeAmazon(2);
            if ($ebay2Y !== null) {
                $yesterdaySummaries['ebaytwo'] = $ebay2Y;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay 2 Y Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $ebay3Y = $this->computeEbayYSalesLikeAmazon(3);
            if ($ebay3Y !== null) {
                $yesterdaySummaries['ebaythree'] = $ebay3Y;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay 3 Y Sales calculation failed: ' . $e->getMessage());
        }

        // PLS Y Sales: actual transactions from pls_sales table for yesterday
        try {
            $yesterday = now()->subDay()->startOfDay();
            $yesterdayEnd = now()->subDay()->endOfDay();
            $plsYSales = \App\Models\PlsSale::where('order_date', '>=', $yesterday)
                ->where('order_date', '<=', $yesterdayEnd)
                ->sum('total_amount');
            $yesterdaySummaries['pls'] = round($plsYSales, 2);
        } catch (\Throwable $e) {
            Log::warning('PLS Y Sales calculation failed: ' . $e->getMessage());
        }

        // Doba Y Sales: doba_daily_data total_price (line totals), day before latest order_time — same clock as Amazon.
        try {
            $dobaY = $this->computeDobaYSalesLikeAmazon();
            if ($dobaY !== null) {
                $yesterdaySummaries['doba'] = $dobaY;
            }
        } catch (\Throwable $e) {
            Log::warning('Doba Y Sales calculation failed: ' . $e->getMessage());
        }

        // Best Buy USA Y Sales: mirakl_daily_data (unit_price × qty), day before latest order_created_at — same clock as Amazon.
        try {
            $bestbuyY = $this->computeBestBuyUsaYSalesLikeAmazon();
            if ($bestbuyY !== null) {
                $yesterdaySummaries['bestbuyusa'] = $bestbuyY;
            }
        } catch (\Throwable $e) {
            Log::warning('Best Buy USA Y Sales calculation failed: ' . $e->getMessage());
        }

        // Mirakl (Macy's, Tiendamia): same rules as Best Buy USA
        foreach (["Macy's, Inc." => 'macys', 'Tiendamia' => 'tiendamia'] as $miraklChannelName => $ysKey) {
            try {
                $miraklY = $this->computeMiraklYSalesLikeAmazon($miraklChannelName);
                if ($miraklY !== null) {
                    $yesterdaySummaries[$ysKey] = $miraklY;
                }
            } catch (\Throwable $e) {
                Log::warning('Mirakl Y Sales (' . $miraklChannelName . ') failed: ' . $e->getMessage());
            }
        }
        // Tiendamia: channel_master row "Tienda Mia" normalizes to tendamia (not tiendamia)
        if (isset($yesterdaySummaries['tiendamia'])) {
            $yesterdaySummaries['tendamia'] = $yesterdaySummaries['tiendamia'];
        } else {
            try {
                $tiendaMiaY = $this->computeMiraklYSalesLikeAmazon('Tienda Mia');
                if ($tiendaMiaY !== null) {
                    $yesterdaySummaries['tiendamia'] = $tiendaMiaY;
                    $yesterdaySummaries['tendamia'] = $tiendaMiaY;
                }
            } catch (\Throwable $e) {
                Log::warning('Mirakl Y Sales (Tienda Mia alt) failed: ' . $e->getMessage());
            }
        }

        // Shopify B2C / B2B, Wayfair, Faire, TikTok Shop (ShipHub), TikTok 2, Reverb, Mercari, TopDawg
        $extendedYs = [
            [fn () => $this->computeShopifyB2xYSalesLikeAmazon(false), 'shopifyb2c', 'Shopify B2C Y Sales'],
            [fn () => $this->computeShopifyB2xYSalesLikeAmazon(true), 'shopifyb2b', 'Shopify B2B Y Sales'],
            [fn () => $this->computeWayfairYSalesLikeAmazon(), 'wayfair', 'Wayfair Y Sales'],
            [fn () => $this->computeFaireYSalesLikeAmazon(), 'faire', 'Faire Y Sales'],
            [fn () => $this->computeTiktokShopYSalesFromShiphub(), 'tiktokshop', 'TikTok Shop Y Sales'],
            [fn () => $this->computeReverbYSalesLikeAmazon(), 'reverb', 'Reverb Y Sales'],
            [fn () => $this->computeMercariYSalesLikeAmazon(true), 'mercariwship', 'Mercari w ship Y Sales'],
            [fn () => $this->computeMercariYSalesLikeAmazon(false), 'mercariwoship', 'Mercari wo ship Y Sales'],
            [fn () => $this->computeTopDawgYSalesLikeAmazon(), 'topdawg', 'TopDawg Y Sales'],
            [fn () => $this->computeNeweggYSalesLikeAmazon(), 'newegg', 'Newegg Y Sales'],
        ];
        foreach ($extendedYs as [$fn, $key, $label]) {
            try {
                $v = $fn();
                if ($v !== null) {
                    $yesterdaySummaries[$key] = $v;
                }
            } catch (\Throwable $e) {
                Log::warning($label . ' failed: ' . $e->getMessage());
            }
        }

        // TikTok 2: API row uses "TikTok 2" → tiktok2; channel_master slug may be tiktokshop2
        try {
            $tiktok2Y = $this->computeTiktokTwoYSalesLikeAmazon();
            if ($tiktok2Y !== null) {
                $yesterdaySummaries['tiktok2'] = $tiktok2Y;
                $yesterdaySummaries['tiktokshop2'] = $tiktok2Y;
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok 2 Y Sales failed: ' . $e->getMessage());
        }

        try {
            $depopY = $this->computeDepopYSalesLikeAmazon();
            if ($depopY !== null) {
                $yesterdaySummaries['depop'] = $depopY;
            }
        } catch (\Throwable $e) {
            Log::warning('Depop Y Sales failed: ' . $e->getMessage());
        }

        try {
            $sheinY = $this->computeSheinYSalesLikeAmazon();
            if ($sheinY !== null) {
                $yesterdaySummaries['shein'] = $sheinY;
            }
        } catch (\Throwable $e) {
            Log::warning('Shein Y Sales failed: ' . $e->getMessage());
        }

        try {
            $aliexpressY = $this->computeAliexpressYSalesLikeAmazon();
            if ($aliexpressY !== null) {
                $yesterdaySummaries['aliexpress'] = $aliexpressY;
            }
        } catch (\Throwable $e) {
            Log::warning('AliExpress Y Sales failed: ' . $e->getMessage());
        }

        try {
            $purchasingPowerY = $this->computePurchasingPowerYSalesLikeAmazon();
            if ($purchasingPowerY !== null) {
                $yesterdaySummaries['purchasingpower'] = $purchasingPowerY;
            }
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power Y Sales failed: ' . $e->getMessage());
        }

        // L7 Sales: seven Pacific calendar days ending on the same "yesterday" as Y Sales (inclusive).
        $temuL7 = $this->computeTemuL7SalesLikeAmazon(false);
        if ($temuL7 !== null) {
            $l7Summaries['temu'] = $temuL7;
        }
        $temu2L7 = $this->computeTemuL7SalesLikeAmazon(true);
        if ($temu2L7 !== null) {
            $l7Summaries['temu2'] = $temu2L7;
        }
        try {
            $ebayL7 = $this->computeEbayL7SalesLikeAmazon(1);
            if ($ebayL7 !== null) {
                $l7Summaries['ebay'] = $ebayL7;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay L7 Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $ebay2L7 = $this->computeEbayL7SalesLikeAmazon(2);
            if ($ebay2L7 !== null) {
                $l7Summaries['ebaytwo'] = $ebay2L7;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay 2 L7 Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $ebay3L7 = $this->computeEbayL7SalesLikeAmazon(3);
            if ($ebay3L7 !== null) {
                $l7Summaries['ebaythree'] = $ebay3L7;
            }
        } catch (\Throwable $e) {
            Log::warning('eBay 3 L7 Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $dobaL7 = $this->computeDobaL7SalesLikeAmazon();
            if ($dobaL7 !== null) {
                $l7Summaries['doba'] = $dobaL7;
            }
        } catch (\Throwable $e) {
            Log::warning('Doba L7 Sales calculation failed: ' . $e->getMessage());
        }
        try {
            $bestbuyL7 = $this->computeBestBuyUsaL7SalesLikeAmazon();
            if ($bestbuyL7 !== null) {
                $l7Summaries['bestbuyusa'] = $bestbuyL7;
            }
        } catch (\Throwable $e) {
            Log::warning('Best Buy USA L7 Sales calculation failed: ' . $e->getMessage());
        }
        foreach (["Macy's, Inc." => 'macys', 'Tiendamia' => 'tiendamia'] as $miraklChannelName => $l7Key) {
            try {
                $miraklL7 = $this->computeMiraklL7SalesLikeAmazon($miraklChannelName);
                if ($miraklL7 !== null) {
                    $l7Summaries[$l7Key] = $miraklL7;
                }
            } catch (\Throwable $e) {
                Log::warning('Mirakl L7 Sales (' . $miraklChannelName . ') failed: ' . $e->getMessage());
            }
        }
        if (isset($l7Summaries['tiendamia'])) {
            $l7Summaries['tendamia'] = $l7Summaries['tiendamia'];
        } else {
            try {
                $tiendaMiaL7 = $this->computeMiraklL7SalesLikeAmazon('Tienda Mia');
                if ($tiendaMiaL7 !== null) {
                    $l7Summaries['tiendamia'] = $tiendaMiaL7;
                    $l7Summaries['tendamia'] = $tiendaMiaL7;
                }
            } catch (\Throwable $e) {
                Log::warning('Mirakl L7 Sales (Tienda Mia alt) failed: ' . $e->getMessage());
            }
        }

        $extendedL7 = [
            [fn () => $this->computeShopifyB2xL7SalesLikeAmazon(false), 'shopifyb2c', 'Shopify B2C L7 Sales'],
            [fn () => $this->computeShopifyB2xL7SalesLikeAmazon(true), 'shopifyb2b', 'Shopify B2B L7 Sales'],
            [fn () => $this->computeWayfairL7SalesLikeAmazon(), 'wayfair', 'Wayfair L7 Sales'],
            [fn () => $this->computeFaireL7SalesLikeAmazon(), 'faire', 'Faire L7 Sales'],
            [fn () => $this->computeTiktokShopL7SalesFromShiphub(), 'tiktokshop', 'TikTok Shop L7 Sales'],
            [fn () => $this->computeReverbL7SalesLikeAmazon(), 'reverb', 'Reverb L7 Sales'],
            [fn () => $this->computeMercariL7SalesLikeAmazon(true), 'mercariwship', 'Mercari w ship L7 Sales'],
            [fn () => $this->computeMercariL7SalesLikeAmazon(false), 'mercariwoship', 'Mercari wo ship L7 Sales'],
            [fn () => $this->computeTopDawgL7SalesLikeAmazon(), 'topdawg', 'TopDawg L7 Sales'],
            [fn () => $this->computeNeweggL7SalesLikeAmazon(), 'newegg', 'Newegg L7 Sales'],
        ];
        foreach ($extendedL7 as [$fn, $key, $label]) {
            try {
                $v = $fn();
                if ($v !== null) {
                    $l7Summaries[$key] = $v;
                }
            } catch (\Throwable $e) {
                Log::warning($label . ' failed: ' . $e->getMessage());
            }
        }

        try {
            $tiktok2L7 = $this->computeTiktokTwoL7SalesLikeAmazon();
            if ($tiktok2L7 !== null) {
                $l7Summaries['tiktok2'] = $tiktok2L7;
                $l7Summaries['tiktokshop2'] = $tiktok2L7;
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok 2 L7 Sales failed: ' . $e->getMessage());
        }

        try {
            $depopL7 = $this->computeDepopL7SalesLikeAmazon();
            if ($depopL7 !== null) {
                $l7Summaries['depop'] = $depopL7;
            }
        } catch (\Throwable $e) {
            Log::warning('Depop L7 Sales failed: ' . $e->getMessage());
        }

        try {
            $sheinL7 = $this->computeSheinL7SalesLikeAmazon();
            if ($sheinL7 !== null) {
                $l7Summaries['shein'] = $sheinL7;
            }
        } catch (\Throwable $e) {
            Log::warning('Shein L7 Sales failed: ' . $e->getMessage());
        }

        try {
            $aliexpressL7 = $this->computeAliexpressL7SalesLikeAmazon();
            if ($aliexpressL7 !== null) {
                $l7Summaries['aliexpress'] = $aliexpressL7;
            }
        } catch (\Throwable $e) {
            Log::warning('AliExpress L7 Sales failed: ' . $e->getMessage());
        }

        try {
            $purchasingPowerL7 = $this->computePurchasingPowerL7SalesLikeAmazon();
            if ($purchasingPowerL7 !== null) {
                $l7Summaries['purchasingpower'] = $purchasingPowerL7;
            }
        } catch (\Throwable $e) {
            Log::warning('Purchasing Power L7 Sales failed: ' . $e->getMessage());
        }

        // Map lowercase channel key => controller method
        $controllerMap = [
            'amazon'    => 'getAmazonChannelData',
            'amazonfba' => 'getAmazonFbaChannelData',
            'ebay'      => 'getEbayChannelData',
            'ebaytwo'   => 'getEbaytwoChannelData',
            'ebaythree' => 'getEbaythreeChannelData',
            'macys'     => 'getMacysChannelData',
            'tiendamia' => 'getTiendamiaChannelData',
            'bestbuyusa'=> 'getBestbuyUsaChannelData',
            'newegg'    => 'getNeweggChannelData',
            'reverb'    => 'getReverbChannelData',
            'doba'      => 'getDobaChannelData',
            'temu'      => 'getTemuChannelData',
            'temu2'     => 'getTemu2ChannelData',
            'walmart'   => 'getWalmartChannelData',
            'pls'       => 'getPlsChannelData',
            'wayfair'   => 'getWayfairChannelData',
            'faire'     => 'getFaireChannelData',
            'purchasingpower' => 'getPurchasingPowerChannelData',
            'shein'     => 'getSheinChannelData',
            'tiktokshop'=> 'getTiktokChannelData',
            'tiktokshop2'   => 'getTikTokTwoChannelData',
            'depop'         => 'getDepopChannelData',
            'instagramshop' => 'getInstagramChannelData',
            'aliexpress' => 'getAliexpressChannelData',
            'mercariwship' => 'getMercariWShipChannelData',
            'mercariwoship' => 'getMercariWoShipChannelData',
            'fbmarketplace' => 'getFbMarketplaceChannelData',
            'fbshop'    => 'getFbShopChannelData',
            'business5core'    => 'getBusiness5CoreChannelData',
            'topdawg'    => 'getTopDawgChannelData',
            'shopifyb2c' => 'getShopifyB2CChannelData',
            'shopifyb2b' => 'getShopifyB2BChannelData',
            // 'walmart' => 'getWalmartChannelData',
            // 'shopify' => 'getShopifyChannelData',
        ];

        foreach ($channels as $channelRow) {
            $channel = $channelRow->channel;

            // Base row - normalize type to only B2C, B2B, Dropship
            $rawType = $channelRow->type ?? '';
            $normalizedType = 'B2C'; // default
            if (strtolower(trim($rawType)) === 'b2b') {
                $normalizedType = 'B2B';
            } elseif (strtolower(trim($rawType)) === 'dropship') {
                $normalizedType = 'Dropship';
            }
            
            $row = [
                'Channel '       => ucfirst($channel),
                'alias'          => $channelRow->alias ?? null,
                'promotions'     => $channelRow->promotions ?? null,
                'compliance_count' => $channelRow->compliance_count ?? null,
                'logo'           => $channelRow->logo ?? null,
                'seller_link'    => $channelRow->seller_link ?? null,
                'Link'           => null,
                'sheet_link'     => $channelRow->sheet_link,
                'L-60 Sales'     => 0,
                'L30 Sales'      => 0,
                'L7 Sales'       => 0,
                'L7 vs 30 pace %' => null,
                'Growth'         => 0,
                'L60 Orders'     => 0,
                'L30 Orders'     => 0,
                'Gprofit%'       => 'N/A',
                'gprofitL60'     => 'N/A',
                'G Roi%'         => 'N/A',
                'G RoiL60'       => 'N/A',
                'red_margin'     => 0,
                'NR'             => 0,
                'type'           => $normalizedType,
                'listed_count'   => 0,
                'W/Ads'          => 0,
                'channel_percentage' => $channelRow->channel_percentage ?? '',
                'base' => $channelRow->base ?? 0,
                'target' => $channelRow->target ?? 0,
                'missing_link' => $channelRow->missing_link ?? '',
                'addition_sheet' => $channelRow->addition_sheet ?? '',
                // '0 Sold SKU Count' => 0,
                // 'Sold SKU Count'   => 0,
                // 'Brand Registry'   => '',
                'Update'         => 0,
                'Account health' => null,
            ];

            // Normalize channel name for lookup
            $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));

            try {
            if (isset($controllerMap[$key]) && method_exists($this, $controllerMap[$key])) {
                $method = $controllerMap[$key];
                $data = $this->$method($request)->getData(true); // call respective function
                if (!empty($data['data'])) {
                    $row = array_merge($row, $data['data'][0]);
                }
            }

            // AD CLICKS, AD SALES, AD SOLD + breakdown: fetch directly from tables for ad-enabled channels
            $adMetricsChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'temu2', 'topdawg', 'walmart', 'shopifyb2c', 'tiktokshop', 'tiktokshop2', 'depop'];
            if (in_array($key, $adMetricsChannels)) {
                $metrics = $this->fetchAdMetricsFromTables($key);
                $clicks = $metrics['clicks'];
                $adSales = $metrics['ad_sales'];
                $adSold = $metrics['ad_sold'];
                // Merge all breakdown fields into row
                $row = array_merge($row, $metrics);
            } else {
                // Fallback: adv_masters_data for channels without direct table support
                $channelKey = strtoupper($channel);
                $channelMapping = [
                    'TIKTOKSHOP' => 'TIKTOK',
                    'SHOPIFYB2C' => 'SHOPIFY',
                    'SHOPIFYB2B' => 'SHOPIFY',
                    'EBAYTWO' => 'EBAY 2',
                    'EBAYTHREE' => 'EBAY 3',
                    'FBMARKETPLACE' => 'FB CARAOUSEL',
                    'FBSHOP' => 'FB VIDEO',
                    'INSTAGRAMSHOP' => 'INSTA CARAOUSEL',
                ];
                $advKey = $channelMapping[$channelKey] ?? $channelKey;
                if (isset($advMastersData[$advKey])) {
                    $advData = $advMastersData[$advKey];
                    $clicks = $advData->clicks ?? 0;
                    $adSold = $advData->ad_sold ?? 0;
                    $adSales = $advData->ad_sales ?? 0;
                } else {
                    $clicks = 0;
                    $adSold = 0;
                    $adSales = 0;
                }
                // Set default breakdown values for non-ad-enabled channels
                $row['KW Clicks'] = 0; $row['PT Clicks'] = 0; $row['HL Clicks'] = 0; $row['PMT Clicks'] = 0; $row['Shopping Clicks'] = 0; $row['SERP Clicks'] = 0;
                $row['KW Sales'] = 0; $row['PT Sales'] = 0; $row['HL Sales'] = 0; $row['PMT Sales'] = 0; $row['Shopping Sales'] = 0; $row['SERP Sales'] = 0;
                $row['KW Sold'] = 0; $row['PT Sold'] = 0; $row['HL Sold'] = 0; $row['PMT Sold'] = 0; $row['Shopping Sold'] = 0; $row['SERP Sold'] = 0;
                $row['KW ACOS'] = 0; $row['PT ACOS'] = 0; $row['HL ACOS'] = 0; $row['PMT ACOS'] = 0; $row['Shopping ACOS'] = 0; $row['SERP ACOS'] = 0;
                $row['KW CVR'] = 0; $row['PT CVR'] = 0; $row['HL CVR'] = 0; $row['PMT CVR'] = 0; $row['Shopping CVR'] = 0; $row['SERP CVR'] = 0;
            }

            // Missing Ads: still from adv_masters_data (not in campaign tables)
            $channelKey = strtoupper($channel);
            $channelMapping = [
                'TIKTOKSHOP' => 'TIKTOK',
                'SHOPIFYB2C' => 'SHOPIFY',
                'SHOPIFYB2B' => 'SHOPIFY',
                'EBAYTWO' => 'EBAY 2',
                'EBAYTHREE' => 'EBAY 3',
                'FBMARKETPLACE' => 'FB CARAOUSEL',
                'FBSHOP' => 'FB VIDEO',
                'INSTAGRAMSHOP' => 'INSTA CARAOUSEL',
            ];
            $advKey = $channelMapping[$channelKey] ?? $channelKey;
            $missingAds = isset($advMastersData[$advKey]) ? ($advMastersData[$advKey]->missing_ads ?? 0) : 0;

            // Calculate Ads CVR and ACOS
            $cvr = $clicks > 0 ? round(($adSold / $clicks) * 100, 2) : 0;
            $totalAdSpend = (float) ($row['Total Ad Spend'] ?? 0);
            $acos = $adSales > 0 ? round(($totalAdSpend / $adSales) * 100, 2) : 0;

            // Recalculate Ads% with fresh Total Ad Spend (may have been overridden by fetchAdMetricsFromTables)
            // Skip for Reverb - it uses Ads% for Bump% instead of traditional ad spend
            if ($key !== 'reverb') {
                $l30SalesVal = (float) str_replace(['$', ',', '%'], '', $row['L30 Sales'] ?? 0);
                $adsPercentage = $l30SalesVal > 0 ? ($totalAdSpend / $l30SalesVal) * 100 : 0;
                $row['Ads%'] = round($adsPercentage, 2) . '%';
            }

            // Recalculate N PFT based on recalculated Ads% (NPFT% = GPFT% - Ads%)
            $gpftPercent = (float) str_replace(['$', ',', '%'], '', $row['Gprofit%'] ?? 0);
            $nPftRecalculated = $gpftPercent - $adsPercentage;
            $row['N PFT'] = round($nPftRecalculated, 2) . '%';

            // Recalculate N ROI based on Net Profit Amount (NROI% = (Gross Profit - Ad Spend) / COGS * 100)
            $totalPft = (float) str_replace(['$', ',', '%'], '', $row['Total PFT'] ?? 0);
            $cogs = (float) str_replace(['$', ',', '%'], '', $row['cogs'] ?? 0);
            $netProfitAmount = $totalPft - $totalAdSpend;
            $nRoiRecalculated = $cogs > 0 ? ($netProfitAmount / $cogs) * 100 : 0;
            $row['N ROI'] = round($nRoiRecalculated, 2);

            $row['clicks'] = $clicks;
            $row['ad_sold'] = $adSold;
            $row['Ad Sales'] = $adSales;
            $row['Ads CVR'] = $cvr;
            $row['ACOS'] = $acos;
            $row['Missing Ads'] = $missingAds;

            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Channel data failed for "' . $channel . '": ' . $e->getMessage(), ['exception' => $e]);
                // Keep base $row; ensure required keys exist for table
                $row['clicks'] = $row['clicks'] ?? 0;
                $row['ad_sold'] = $row['ad_sold'] ?? 0;
                $row['Ad Sales'] = $row['Ad Sales'] ?? 0;
                $row['Ads CVR'] = $row['Ads CVR'] ?? 0;
                $row['ACOS'] = $row['ACOS'] ?? 0;
                $row['Missing Ads'] = $row['Missing Ads'] ?? 0;
                if (!isset($row['KW Clicks'])) {
                    $row['KW Clicks'] = 0; $row['PT Clicks'] = 0; $row['HL Clicks'] = 0; $row['PMT Clicks'] = 0; $row['Shopping Clicks'] = 0; $row['SERP Clicks'] = 0;
                    $row['KW Sales'] = 0; $row['PT Sales'] = 0; $row['HL Sales'] = 0; $row['PMT Sales'] = 0; $row['Shopping Sales'] = 0; $row['SERP Sales'] = 0;
                    $row['KW Sold'] = 0; $row['PT Sold'] = 0; $row['HL Sold'] = 0; $row['PMT Sold'] = 0; $row['Shopping Sold'] = 0; $row['SERP Sold'] = 0;
                    $row['KW ACOS'] = 0; $row['PT ACOS'] = 0; $row['HL ACOS'] = 0; $row['PMT ACOS'] = 0; $row['Shopping ACOS'] = 0; $row['SERP ACOS'] = 0;
                    $row['KW CVR'] = 0; $row['PT CVR'] = 0; $row['HL CVR'] = 0; $row['PMT CVR'] = 0; $row['Shopping CVR'] = 0; $row['SERP CVR'] = 0;
                }
                $row['Ads%'] = $row['Ads%'] ?? '0%';
            }

            // Attach Yesterday Sales (same source as dot chart — channel_master_daily_data)
            // Key must match saveChannelDailySummaries: lowercase, no spaces/dashes/&/slashes
            $channelLookup = strtolower(str_replace([' ', '-', '&', '/'], '', trim((string) ($row['Channel '] ?? $channel))));
            $row['Y Sales'] = round($yesterdaySummaries[$channelLookup] ?? 0, 2);
            $row['L7 Sales'] = round($l7Summaries[$channelLookup] ?? 0, 2);

            // L7 vs pace: expected L7 = (trailing sales ÷ N days) × 7; % = (L7 − expected) / expected × 100.
            // N must match the rolling window used for this row's L30 Sales. Amazon B2C uses the same
            // day count as Amazon Daily Sales (DAILY_SALES_WINDOW_DAYS = 33), not 30 — dividing by 30 skewed red.
            // Reverb: L30 Sales matches pricing full-table total; use rolling L30 for pace only.
            $paceBase = $row['reverb_pace_l30_sales'] ?? $row['L30 Sales'] ?? 0;
            $l30ForPace = (float) str_replace(['$', ',', '%'], '', (string) $paceBase);
            $l7ForPace = (float) ($row['L7 Sales'] ?? 0);
            $paceDenominatorDays = 30.0;
            if ($channelLookup === 'amazon') {
                $paceDenominatorDays = (float) AmazonSalesController::DAILY_SALES_WINDOW_DAYS;
            }
            $expectedL7FromL30 = ($paceDenominatorDays > 0.0)
                ? ($l30ForPace / $paceDenominatorDays) * 7.0
                : 0.0;
            $row['L7 vs 30 pace %'] = ($expectedL7FromL30 > 0)
                ? round((($l7ForPace - $expectedL7FromL30) / $expectedL7FromL30) * 100, 2)
                : null;

            $finalData[] = $row;
        }

        // Shopify: overlay live Net Sales from shopify_raw_orders (matches /shopify Net Sales card)
        // so the "Shopify" Active Channel row Sales column reflects the same value the user sees
        // on /shopify. Applied before daily snapshot + chart aggregation so both pick it up.
        $finalData = $this->overlayLiveShopifyDirectMetricsOnChannelRows($finalData);

        // Sum of (inventory * Amazon price) for INV Val badge and TAT (save in first row for daily history)
        $inventoryValueAmazon = $this->getInventoryValueAmazon();
        if (!empty($finalData)) {
            $finalData[0]['inventory_value_amazon'] = $inventoryValueAmazon;
        }

        // Auto-save channel-wise daily summaries
        try {
            $this->saveChannelDailySummaries($finalData);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('saveChannelDailySummaries failed: ' . $e->getMessage(), ['exception' => $e]);
        }
        // Sum of (Shopify inventory * LP) for Inv@LP badge and chart + Inv / LP breakdown
        $shopifyInvLp = $this->getShopifyInvLpMetrics();
        $invAtLp = $shopifyInvLp['inv_at_lp'];
        $inventoryByColor = $this->getShopifyInventoryByColor();
        $stockAvailability = $this->getStockAvailability();
        $adSpendByChannel = $this->buildAdSpendByChannelFromRows($finalData);
        $salesByChannel = $this->buildSalesByChannelWithPercentage($finalData);
        $adSpendByColorAmazon = $this->getAdSpendByProductColorAmazonFamilyL30();
        $adSpendByColorByChannel = $this->getAdSpendByColorByChannelL30();

        $finalData = $this->overlayLiveMapMissNMapOnChannelRows($finalData);
        $finalData = $this->applyDefaultMissingLinks($finalData);

        return response()->json([
            'status'  => 200,
            'message' => 'Channel data fetched successfully',
            'data'    => $finalData,
            'inventory_value_amazon' => round($inventoryValueAmazon, 2),
            'inv_at_lp' => round($invAtLp, 2),
            'shopify_inv_sum' => $shopifyInvLp['inv_sum'],
            'shopify_weighted_avg_lp' => $shopifyInvLp['weighted_avg_lp'],
            'inventory_by_color' => $inventoryByColor,
            'stock_availability' => $stockAvailability,
            'ad_spend_by_channel' => $adSpendByChannel,
            'sales_by_channel' => $salesByChannel,
            'ad_spend_by_color_amazon' => $adSpendByColorAmazon,
            'ad_spend_by_color_by_channel' => $adSpendByColorByChannel,
        ]);
    }

    /**
     * Temu / Temu 2 Y Sales: same clock as Amazon — revenue for the calendar day before the latest
     * purchase_date in Pacific. FB price × qty matches marketplace_daily_metrics L30 rules:
     * Temu = ProductMaster-filtered rows (same as calculateTemuMetrics); Temu 2 = all temu2_daily_data rows
     * except PARENT-child SKUs (same as calculateTemu2Metrics).
     */
    private function computeTemuYSalesLikeAmazon(bool $isTemu2): ?float
    {
        if (! $isTemu2) {
            return TemuShopifySalesService::computeYSalesFromOrders();
        }

        $modelClass = Temu2DailyData::class;

        try {
            $latest = $modelClass::whereNotNull('purchase_date')->max('purchase_date');
            if (!$latest) {
                return null;
            }

            $latestPacific = Carbon::parse($latest)->timezone('America/Los_Angeles');
            $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
            $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);

                return $sku;
            };

            $normalizedPmSet = [];
            if (!$isTemu2) {
                $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
                    ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                    ->orderBy('sku', 'asc')
                    ->pluck('sku')
                    ->filter(function ($sku) {
                        return stripos($sku, 'PARENT') === false;
                    })
                    ->unique()
                    ->values()
                    ->all();

                $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
                    return [$normalizeSku($s) => true];
                })->all();
            }

            $productMastersBySku = ProductMaster::all()->keyBy('sku');
            $productMastersByNormalized = ProductMaster::all()->keyBy(function ($pm) use ($normalizeSku) {
                return $normalizeSku($pm->sku ?? '');
            });

            $rows = $modelClass::where('purchase_date', '>=', $yStartPacific)
                ->where('purchase_date', '<=', $yEndPacific)
                ->get();

            $totalYSales = 0.0;

            foreach ($rows as $row) {
                if (!$row->contribution_sku || trim((string) $row->contribution_sku) === '') {
                    continue;
                }
                if (!$isTemu2) {
                    if (!$row->order_id || trim((string) $row->order_id) === '') {
                        continue;
                    }
                    if (!isset($normalizedPmSet[$normalizeSku($row->contribution_sku ?? '')])) {
                        continue;
                    }
                }

                $pm = $productMastersBySku[$row->contribution_sku]
                    ?? $productMastersByNormalized[$normalizeSku($row->contribution_sku)]
                    ?? null;
                $parent = $pm ? $pm->parent : '';
                if ($parent && str_starts_with((string) $parent, 'PARENT')) {
                    continue;
                }

                $quantity = (int) ($row->quantity_purchased ?? 0);
                $basePrice = (float) ($row->base_price_total ?? 0);
                $total = $basePrice * $quantity;
                $fbPrice = $total < 27 ? $basePrice + 2.99 : $basePrice;

                if ($quantity > 0 && $basePrice > 0) {
                    $totalYSales += $fbPrice * $quantity;
                }
            }

            return round($totalYSales, 2);
        } catch (\Throwable $e) {
            Log::warning('computeTemuYSalesLikeAmazon failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * eBay 1 / 2 / 3 Y Sales: revenue for the Pacific calendar day before the latest order timestamp,
     * same clock as Amazon Y Sales. Line revenue matches UpdateMarketplaceDailyMetrics (item price = line total;
     * eBay 3 unit_price = line total).
     *
     * @param int $which 1 = eBay, 2 = eBay 2, 3 = eBay 3
     */
    private function computeEbayYSalesLikeAmazon(int $which): ?float
    {
        if ($which === 1) {
            $latestRaw = DB::table('ebay_orders')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED');
                })
                ->max('order_date');
        } elseif ($which === 2) {
            $latestRaw = DB::table('ebay2_orders')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED');
                })
                ->max('order_date');
        } elseif ($which === 3) {
            $latestRaw = DB::table('ebay3_daily_data')->max('creation_date');
        } else {
            return null;
        }

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        if ($which === 1) {
            $sum = (float) DB::table('ebay_orders as o')
                ->join('ebay_order_items as i', 'o.id', '=', 'i.ebay_order_id')
                ->where('o.order_date', '>=', $yStartPacific)
                ->where('o.order_date', '<=', $yEndPacific)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'CANCELLED');
                })
                ->sum('i.price');
        } elseif ($which === 2) {
            $sum = (float) DB::table('ebay2_orders as o')
                ->join('ebay2_order_items as i', 'o.id', '=', 'i.ebay2_order_id')
                ->where('o.order_date', '>=', $yStartPacific)
                ->where('o.order_date', '<=', $yEndPacific)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'CANCELLED');
                })
                ->sum('i.price');
        } else {
            $sum = (float) DB::table('ebay3_daily_data')
                ->where('creation_date', '>=', $yStartPacific)
                ->where('creation_date', '<=', $yEndPacific)
                ->sum('unit_price');
        }

        return round($sum, 2);
    }

    /**
     * Doba Y Sales: sum of doba_daily_data.total_price for the Pacific calendar day before the latest
     * non-cancelled order_time (matches UpdateMarketplaceDailyMetrics revenue and FetchDobaMetrics status filter).
     */
    private function computeDobaYSalesLikeAmazon(): ?float
    {
        $cancelled = ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'];

        $latestRaw = DB::table('doba_daily_data')
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->max('order_time');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::table('doba_daily_data')
            ->where('order_time', '>=', $yStartPacific)
            ->where('order_time', '<=', $yEndPacific)
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->sum('total_price');

        return round($sum, 2);
    }

    /**
     * Mirakl channels (Best Buy USA, Macy's, Tiendamia): unit_price × qty, day before latest order_created_at, excl. CLOSED.
     */
    private function computeMiraklYSalesLikeAmazon(string $channelName): ?float
    {
        $latestRaw = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('status', '!=', 'CLOSED')
            ->max('order_created_at');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $yStartPacific)
            ->where('order_created_at', '<=', $yEndPacific)
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Best Buy USA Y Sales (mirakl_daily_data).
     */
    private function computeBestBuyUsaYSalesLikeAmazon(): ?float
    {
        return $this->computeMiraklYSalesLikeAmazon('Best Buy USA');
    }

    /**
     * Shopify B2C / B2B: sum total_amount on Pacific day before latest order_date; excl. refunded.
     */
    private function computeShopifyB2xYSalesLikeAmazon(bool $isB2b): ?float
    {
        $table = $isB2b ? 'shopify_b2b_daily_data' : 'shopify_b2c_daily_data';

        $latestRaw = DB::table($table)
            ->where('financial_status', '!=', 'refunded')
            ->max('order_date');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::table($table)
            ->where('order_date', '>=', $yStartPacific)
            ->where('order_date', '<=', $yEndPacific)
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Wayfair: SUM(unit_price × quantity) on po_date = day before latest po_date (Pacific).
     */
    private function computeWayfairYSalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('wayfair_daily_data')->whereNotNull('po_date')->max('po_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('wayfair_daily_data')
            ->whereDate('po_date', $yDate)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Faire: wholesale else retail × quantity; day before latest order_date (matches calculateFaireMetrics).
     */
    private function computeFaireYSalesLikeAmazon(): ?float
    {
        // Sourced from shopify_raw_orders (inventory_db) so Faire's Y Sales uses the same
        // pipeline as the all-marketplace-master Faire row and /faire-tabulator page.
        // Previously this queried `faire_daily_data` (manual Excel uploads) which had a
        // different latest-order anchor and could disagree with the L30/L60 numbers.
        $faireWhere = function ($q) {
            $q->where('source_name', 'faire')
              ->orWhere('source_name', 'LIKE', '%faire%')
              ->orWhere('tags', 'LIKE', '%Faire%');
        };

        $latestRaw = DB::table('shopify_raw_orders')
            ->where($faireWhere)
            ->whereNotNull('order_date')
            ->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific   = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::table('shopify_raw_orders')
            ->where($faireWhere)
            ->where('order_date', '>=', $yStartPacific)
            ->where('order_date', '<=', $yEndPacific)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Depop: depop_sales_data — item_price × quantity (quantity defaults to 1) on calendar day before latest sale_date.
     */
    private function computeDepopYSalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('depop_sales_data')) {
            return null;
        }

        $latestRaw = DB::table('depop_sales_data')->whereNotNull('sale_date')->max('sale_date');
        if (! $latestRaw) {
            return null;
        }

        $yDate = Carbon::parse($latestRaw)->subDay()->toDateString();

        $sum = (float) DB::table('depop_sales_data')
            ->whereDate('sale_date', $yDate)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * TikTok Shop: ShipHub — sum order_total once per order on Y day (same cancel filter as calculateTikTokMetrics).
     */
    private function computeTiktokShopYSalesFromShiphub(): ?float
    {
        $latestDate = DB::connection('shiphub')->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestPacific = Carbon::parse($latestDate)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $rows = DB::connection('shiphub')
            ->table('orders as o')
            ->where('o.order_date', '>=', $yStartPacific)
            ->where('o.order_date', '<=', $yEndPacific)
            ->where('o.marketplace', '=', 'tiktok')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('o.order_status', '!=', 'Canceled')
                        ->where('o.order_status', '!=', 'Cancelled');
                })->orWhereNull('o.order_status');
            })
            ->selectRaw('COALESCE(SUM(o.order_total), 0) as revenue')
            ->value('revenue');

        return round((float) $rows, 2);
    }

    /**
     * TikTok 2: tiktok_sales_two unit_price × quantity (day before latest order_date).
     */
    private function computeTiktokTwoYSalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('tiktok_sales_two')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::table('tiktok_sales_two')
            ->where('order_date', '>=', $yStartPacific)
            ->where('order_date', '<=', $yEndPacific)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Reverb: same line revenue as /reverb-pricing (Σ quantity × amount per row).
     */
    private function computeReverbYSalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('reverb_daily_data')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('reverb_daily_data')
            ->whereDate('order_date', $yDate)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(NULLIF(product_subtotal, 0), amount, 0)), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Mercari: item_price sum on sold_date Y window; withShip = seller-paid shipping (matches L30 job split).
     */
    private function computeMercariYSalesLikeAmazon(bool $withShip): ?float
    {
        $q = DB::table('mercari_daily_data')
            ->whereNotNull('sold_date')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->whereNull('canceled_date')
            ->where(function ($q2) {
                $q2->whereNull('order_status')
                    ->orWhereRaw('LOWER(order_status) NOT LIKE ?', ['%cancel%']);
            });

        if ($withShip) {
            $q->where(function ($q3) {
                $q3->whereNull('buyer_shipping_fee')
                    ->orWhere('buyer_shipping_fee', '=', 0)
                    ->orWhere('buyer_shipping_fee', '=', '');
            });
        } else {
            $q->where('buyer_shipping_fee', '>', 0);
        }

        $latestRaw = (clone $q)->max('sold_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) (clone $q)
            ->where('sold_date', '>=', $yStartPacific)
            ->where('sold_date', '<=', $yEndPacific)
            ->selectRaw('COALESCE(SUM(item_price), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * TopDawg: sum amount on order_date = Pacific day before latest order_date.
     */
    private function computeTopDawgYSalesLikeAmazon(): ?float
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return null;
        }

        $latestRaw = DB::table('topdawg_order_metrics')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('topdawg_order_metrics')
            ->whereDate('order_date', $yDate)
            ->selectRaw('COALESCE(SUM(amount), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Shein: revenue on Pacific calendar day before latest order_processed_on (matches tabulator / aggregateSheinDailyDataLikeTabulator line revenue).
     */
    private function computeSheinYSalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return null;
        }

        $latestRaw = DB::table('shein_daily_data')->whereNotNull('order_processed_on')->max('order_processed_on');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStart = $latestPacific->copy()->subDay()->startOfDay();
        $yEnd = $latestPacific->copy()->subDay()->endOfDay();

        $sum = 0.0;
        foreach (
            DB::table('shein_daily_data')
                ->where('order_processed_on', '>=', $yStart)
                ->where('order_processed_on', '<=', $yEnd)
                ->cursor() as $row
        ) {
            $orderNum = trim((string) ($row->order_number ?? ''));
            $sellerSku = trim((string) ($row->seller_sku ?? ''));
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }
            $orderStatus = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($orderStatus, 'refund')
                || str_contains($orderStatus, 'return')
                || str_contains($orderStatus, 'cancel')
                || str_contains($orderStatus, 'closed')
                || str_contains($orderStatus, 'exchange')) {
                continue;
            }
            $quantity = (int) ($row->quantity ?? 0);
            $productPrice = (float) ($row->product_price ?? 0);
            $estRev = (float) ($row->estimated_merchandise_revenue ?? 0);
            $lineRevenue = $productPrice > 0 ? $productPrice * $quantity : ($estRev > 0 ? $estRev : 0.0);
            $sum += $lineRevenue;
        }

        return round($sum, 2);
    }

    /**
     * AliExpress: line revenue on Pacific day before latest order_date (product_total → supply_price → order_amount; same filters as calculateAliexpressMetrics).
     */
    private function computeAliexpressYSalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('aliexpress_daily_data')) {
            return null;
        }

        $latestRaw = DB::table('aliexpress_daily_data')->whereNotNull('order_date')->max('order_date');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStart = $latestPacific->copy()->subDay()->startOfDay();
        $yEnd = $latestPacific->copy()->subDay()->endOfDay();

        $sum = 0.0;
        foreach (
            DB::table('aliexpress_daily_data')
                ->where('order_date', '>=', $yStart)
                ->where('order_date', '<=', $yEnd)
                ->cursor() as $row
        ) {
            $status = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($status, 'refund')
                || str_contains($status, 'return')
                || str_contains($status, 'cancel')
                || str_contains($status, 'closed')) {
                continue;
            }
            if (empty($row->sku_code) || empty($row->order_id)) {
                continue;
            }
            $lineRevenue = (float) ($row->product_total ?? 0);
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->supply_price ?? 0);
            }
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->order_amount ?? 0);
            }
            $sum += $lineRevenue;
        }

        return round($sum, 2);
    }

    /**
     * Purchasing Power Y Sales — sourced from shopify_order_items (apicentral) so PP's Y Sales
     * uses the same pipeline as the all-marketplace-master Purchasing Power row and stays in
     * sync with the Shopify orders dashboard. Identification mirrors the shopify-orders page:
     * source_name / tags containing "purchasing power".
     *
     * Revenue = price × quantity for the Pacific calendar day before the latest order_date.
     */
    private function computePurchasingPowerYSalesLikeAmazon(): ?float
    {
        $ppWhere = function ($q) {
            $q->where('source_name', 'LIKE', '%purchasing power%')
              ->orWhere('source_name', 'LIKE', '%purchasingpower%')
              ->orWhere('tags', 'LIKE', '%Purchasing Power%')
              ->orWhere('tags', 'LIKE', '%PurchasingPower%');
        };

        $latestRaw = DB::connection('apicentral')->table('shopify_order_items')
            ->where($ppWhere)
            ->whereNotNull('order_date')
            ->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific   = $latestPacific->copy()->subDay()->endOfDay();

        $sum = (float) DB::connection('apicentral')->table('shopify_order_items')
            ->where($ppWhere)
            ->where('order_date', '>=', $yStartPacific)
            ->where('order_date', '<=', $yEndPacific)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Purchasing Power L7 Sales — same Shopify-based identification as Y Sales, summed across
     * the seven Pacific calendar days ending on Y-Sales "yesterday" (day before latest anchor).
     */
    private function computePurchasingPowerL7SalesLikeAmazon(): ?float
    {
        $ppWhere = function ($q) {
            $q->where('source_name', 'LIKE', '%purchasing power%')
              ->orWhere('source_name', 'LIKE', '%purchasingpower%')
              ->orWhere('tags', 'LIKE', '%Purchasing Power%')
              ->orWhere('tags', 'LIKE', '%PurchasingPower%');
        };

        $latestRaw = DB::connection('apicentral')->table('shopify_order_items')
            ->where($ppWhere)
            ->whereNotNull('order_date')
            ->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::connection('apicentral')->table('shopify_order_items')
            ->where($ppWhere)
            ->where('order_date', '>=', $l7StartPacific)
            ->where('order_date', '<=', $l7EndPacific)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Seven inclusive Pacific calendar days ending on Y-Sales "yesterday" (day before latest anchor).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} [start, end]
     */
    private function pacificL7WindowEndingYesterday(Carbon $latestPacific): array
    {
        $end = $latestPacific->copy()->subDay()->endOfDay();
        $start = $latestPacific->copy()->subDay()->subDays(6)->startOfDay();

        return [$start, $end];
    }

    /**
     * Temu / Temu 2 L7 Sales: same clock and revenue rules as Y Sales, 7-day window ending that yesterday.
     */
    private function computeTemuL7SalesLikeAmazon(bool $isTemu2): ?float
    {
        if (! $isTemu2) {
            return TemuShopifySalesService::computeL7SalesFromOrders();
        }

        $modelClass = Temu2DailyData::class;

        try {
            $latest = $modelClass::whereNotNull('purchase_date')->max('purchase_date');
            if (!$latest) {
                return null;
            }

            $latestPacific = Carbon::parse($latest)->timezone('America/Los_Angeles');
            [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

            $normalizeSku = function ($sku) {
                $sku = strtoupper(trim((string) $sku));
                $sku = preg_replace('/(\d+)\s*(PCS?|PIECES?)$/i', '$1PC', $sku);
                $sku = preg_replace('/\s+/', ' ', $sku);

                return $sku;
            };

            $normalizedPmSet = [];
            if (!$isTemu2) {
                $productMasterSkus = ProductMaster::orderBy('parent', 'asc')
                    ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
                    ->orderBy('sku', 'asc')
                    ->pluck('sku')
                    ->filter(function ($sku) {
                        return stripos($sku, 'PARENT') === false;
                    })
                    ->unique()
                    ->values()
                    ->all();

                $normalizedPmSet = collect($productMasterSkus)->mapWithKeys(function ($s) use ($normalizeSku) {
                    return [$normalizeSku($s) => true];
                })->all();
            }

            $productMastersBySku = ProductMaster::all()->keyBy('sku');
            $productMastersByNormalized = ProductMaster::all()->keyBy(function ($pm) use ($normalizeSku) {
                return $normalizeSku($pm->sku ?? '');
            });

            $rows = $modelClass::where('purchase_date', '>=', $l7StartPacific)
                ->where('purchase_date', '<=', $l7EndPacific)
                ->get();

            $total = 0.0;

            foreach ($rows as $row) {
                if (!$row->contribution_sku || trim((string) $row->contribution_sku) === '') {
                    continue;
                }
                if (!$isTemu2) {
                    if (!$row->order_id || trim((string) $row->order_id) === '') {
                        continue;
                    }
                    if (!isset($normalizedPmSet[$normalizeSku($row->contribution_sku ?? '')])) {
                        continue;
                    }
                }

                $pm = $productMastersBySku[$row->contribution_sku]
                    ?? $productMastersByNormalized[$normalizeSku($row->contribution_sku)]
                    ?? null;
                $parent = $pm ? $pm->parent : '';
                if ($parent && str_starts_with((string) $parent, 'PARENT')) {
                    continue;
                }

                $quantity = (int) ($row->quantity_purchased ?? 0);
                $basePrice = (float) ($row->base_price_total ?? 0);
                $sub = $basePrice * $quantity;
                $fbPrice = $sub < 27 ? $basePrice + 2.99 : $basePrice;

                if ($quantity > 0 && $basePrice > 0) {
                    $total += $fbPrice * $quantity;
                }
            }

            return round($total, 2);
        } catch (\Throwable $e) {
            Log::warning('computeTemuL7SalesLikeAmazon failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * eBay 1 / 2 / 3 L7 Sales: seven days ending Y-Sales yesterday (same revenue rules as Y Sales).
     */
    private function computeEbayL7SalesLikeAmazon(int $which): ?float
    {
        if ($which === 1) {
            $latestRaw = DB::table('ebay_orders')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED');
                })
                ->max('order_date');
        } elseif ($which === 2) {
            $latestRaw = DB::table('ebay2_orders')
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED');
                })
                ->max('order_date');
        } elseif ($which === 3) {
            $latestRaw = DB::table('ebay3_daily_data')->max('creation_date');
        } else {
            return null;
        }

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        if ($which === 1) {
            $sum = (float) DB::table('ebay_orders as o')
                ->join('ebay_order_items as i', 'o.id', '=', 'i.ebay_order_id')
                ->where('o.order_date', '>=', $l7StartPacific)
                ->where('o.order_date', '<=', $l7EndPacific)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'CANCELLED');
                })
                ->sum('i.price');
        } elseif ($which === 2) {
            $sum = (float) DB::table('ebay2_orders as o')
                ->join('ebay2_order_items as i', 'o.id', '=', 'i.ebay2_order_id')
                ->where('o.order_date', '>=', $l7StartPacific)
                ->where('o.order_date', '<=', $l7EndPacific)
                ->where(function ($q) {
                    $q->whereNull('o.status')->orWhere('o.status', '!=', 'CANCELLED');
                })
                ->sum('i.price');
        } else {
            $sum = (float) DB::table('ebay3_daily_data')
                ->where('creation_date', '>=', $l7StartPacific)
                ->where('creation_date', '<=', $l7EndPacific)
                ->sum('unit_price');
        }

        return round($sum, 2);
    }

    private function computeDobaL7SalesLikeAmazon(): ?float
    {
        $cancelled = ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'];

        $latestRaw = DB::table('doba_daily_data')
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->max('order_time');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::table('doba_daily_data')
            ->where('order_time', '>=', $l7StartPacific)
            ->where('order_time', '<=', $l7EndPacific)
            ->where(function ($q) use ($cancelled) {
                $q->whereNull('order_status')->orWhereNotIn('order_status', $cancelled);
            })
            ->sum('total_price');

        return round($sum, 2);
    }

    private function computeMiraklL7SalesLikeAmazon(string $channelName): ?float
    {
        $latestRaw = DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('status', '!=', 'CLOSED')
            ->max('order_created_at');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::table('mirakl_daily_data')
            ->where('channel_name', $channelName)
            ->where('order_created_at', '>=', $l7StartPacific)
            ->where('order_created_at', '<=', $l7EndPacific)
            ->where('status', '!=', 'CLOSED')
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeBestBuyUsaL7SalesLikeAmazon(): ?float
    {
        return $this->computeMiraklL7SalesLikeAmazon('Best Buy USA');
    }

    private function computeShopifyB2xL7SalesLikeAmazon(bool $isB2b): ?float
    {
        $table = $isB2b ? 'shopify_b2b_daily_data' : 'shopify_b2c_daily_data';

        $latestRaw = DB::table($table)
            ->where('financial_status', '!=', 'refunded')
            ->max('order_date');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::table($table)
            ->where('order_date', '>=', $l7StartPacific)
            ->where('order_date', '<=', $l7EndPacific)
            ->where('financial_status', '!=', 'refunded')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeWayfairL7SalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('wayfair_daily_data')->whereNotNull('po_date')->max('po_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $l7StartDate = $latestPacific->copy()->subDay()->subDays(6)->toDateString();
        $l7EndDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('wayfair_daily_data')
            ->where('po_date', '>=', $l7StartDate)
            ->where('po_date', '<=', $l7EndDate)
            ->where('quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeFaireL7SalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('faire_daily_data')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::table('faire_daily_data')
            ->where('order_date', '>=', $l7StartPacific)
            ->where('order_date', '<=', $l7EndPacific)
            ->where('quantity', '>', 0)
            ->selectRaw(
                'COALESCE(SUM((CASE WHEN COALESCE(wholesale_price, 0) > 0 THEN wholesale_price ELSE COALESCE(retail_price, 0) END) * quantity), 0) as revenue'
            )
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeTiktokShopL7SalesFromShiphub(): ?float
    {
        $latestDate = DB::connection('shiphub')->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        if (!$latestDate) {
            return null;
        }

        $latestPacific = Carbon::parse($latestDate)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $rows = DB::connection('shiphub')
            ->table('orders as o')
            ->where('o.order_date', '>=', $l7StartPacific)
            ->where('o.order_date', '<=', $l7EndPacific)
            ->where('o.marketplace', '=', 'tiktok')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('o.order_status', '!=', 'Canceled')
                        ->where('o.order_status', '!=', 'Cancelled');
                })->orWhereNull('o.order_status');
            })
            ->selectRaw('COALESCE(SUM(o.order_total), 0) as revenue')
            ->value('revenue');

        return round((float) $rows, 2);
    }

    private function computeTiktokTwoL7SalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('tiktok_sales_two')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) DB::table('tiktok_sales_two')
            ->where('order_date', '>=', $l7StartPacific)
            ->where('order_date', '<=', $l7EndPacific)
            ->selectRaw('COALESCE(SUM(unit_price * quantity), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeReverbL7SalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('reverb_daily_data')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $l7StartDate = $latestPacific->copy()->subDay()->subDays(6)->toDateString();
        $l7EndDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('reverb_daily_data')
            ->where('order_date', '>=', $l7StartDate)
            ->where('order_date', '<=', $l7EndDate)
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(amount, 0)), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeMercariL7SalesLikeAmazon(bool $withShip): ?float
    {
        $q = DB::table('mercari_daily_data')
            ->whereNotNull('sold_date')
            ->whereNotNull('item_id')
            ->where('item_id', '!=', '')
            ->whereNull('canceled_date')
            ->where(function ($q2) {
                $q2->whereNull('order_status')
                    ->orWhereRaw('LOWER(order_status) NOT LIKE ?', ['%cancel%']);
            });

        if ($withShip) {
            $q->where(function ($q3) {
                $q3->whereNull('buyer_shipping_fee')
                    ->orWhere('buyer_shipping_fee', '=', 0)
                    ->orWhere('buyer_shipping_fee', '=', '');
            });
        } else {
            $q->where('buyer_shipping_fee', '>', 0);
        }

        $latestRaw = (clone $q)->max('sold_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = (float) (clone $q)
            ->where('sold_date', '>=', $l7StartPacific)
            ->where('sold_date', '<=', $l7EndPacific)
            ->selectRaw('COALESCE(SUM(item_price), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeTopDawgL7SalesLikeAmazon(): ?float
    {
        if (!Schema::hasTable('topdawg_order_metrics')) {
            return null;
        }

        $latestRaw = DB::table('topdawg_order_metrics')->whereNotNull('order_date')->max('order_date');
        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $l7StartDate = $latestPacific->copy()->subDay()->subDays(6)->toDateString();
        $l7EndDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('topdawg_order_metrics')
            ->where('order_date', '>=', $l7StartDate)
            ->where('order_date', '<=', $l7EndDate)
            ->selectRaw('COALESCE(SUM(amount), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeDepopL7SalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('depop_sales_data')) {
            return null;
        }

        $latestRaw = DB::table('depop_sales_data')->whereNotNull('sale_date')->max('sale_date');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $l7StartDate = $latestPacific->copy()->subDay()->subDays(6)->toDateString();
        $l7EndDate = $latestPacific->copy()->subDay()->toDateString();

        $sum = (float) DB::table('depop_sales_data')
            ->where('sale_date', '>=', $l7StartDate)
            ->where('sale_date', '<=', $l7EndDate)
            ->selectRaw('COALESCE(SUM(item_price * GREATEST(COALESCE(NULLIF(quantity, 0), 1), 1)), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    private function computeSheinL7SalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return null;
        }

        $latestRaw = DB::table('shein_daily_data')->whereNotNull('order_processed_on')->max('order_processed_on');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7Start, $l7End] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = 0.0;
        foreach (
            DB::table('shein_daily_data')
                ->where('order_processed_on', '>=', $l7Start)
                ->where('order_processed_on', '<=', $l7End)
                ->cursor() as $row
        ) {
            $orderNum = trim((string) ($row->order_number ?? ''));
            $sellerSku = trim((string) ($row->seller_sku ?? ''));
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }
            $orderStatus = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($orderStatus, 'refund')
                || str_contains($orderStatus, 'return')
                || str_contains($orderStatus, 'cancel')
                || str_contains($orderStatus, 'closed')
                || str_contains($orderStatus, 'exchange')) {
                continue;
            }
            $quantity = (int) ($row->quantity ?? 0);
            $productPrice = (float) ($row->product_price ?? 0);
            $estRev = (float) ($row->estimated_merchandise_revenue ?? 0);
            $lineRevenue = $productPrice > 0 ? $productPrice * $quantity : ($estRev > 0 ? $estRev : 0.0);
            $sum += $lineRevenue;
        }

        return round($sum, 2);
    }

    private function computeAliexpressL7SalesLikeAmazon(): ?float
    {
        if (! Schema::hasTable('aliexpress_daily_data')) {
            return null;
        }

        $latestRaw = DB::table('aliexpress_daily_data')->whereNotNull('order_date')->max('order_date');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7Start, $l7End] = $this->pacificL7WindowEndingYesterday($latestPacific);

        $sum = 0.0;
        foreach (
            DB::table('aliexpress_daily_data')
                ->where('order_date', '>=', $l7Start)
                ->where('order_date', '<=', $l7End)
                ->cursor() as $row
        ) {
            $status = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($status, 'refund')
                || str_contains($status, 'return')
                || str_contains($status, 'cancel')
                || str_contains($status, 'closed')) {
                continue;
            }
            if (empty($row->sku_code) || empty($row->order_id)) {
                continue;
            }
            $lineRevenue = (float) ($row->product_total ?? 0);
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->supply_price ?? 0);
            }
            if ($lineRevenue <= 0) {
                $lineRevenue = (float) ($row->order_amount ?? 0);
            }
            $sum += $lineRevenue;
        }

        return round($sum, 2);
    }

    // get total inventory l30 values
    public function getViewChannelData1(Request $request)
{
    $channels = ChannelMaster::where('status', 'Active')
        ->orderBy('id', 'asc')
        ->get(['channel', 'sheet_link', 'channel_percentage']);

    if ($channels->isEmpty()) {
        return response()->json(['status' => 404, 'message' => 'No active channel found']);
    }

    $finalData = [];

    $controllerMap = [
        'amazon'    => 'getAmazonChannelData',
        'ebay'      => 'getEbayChannelData',
        'ebaytwo'   => 'getEbaytwoChannelData',
        'ebaythree' => 'getEbaythreeChannelData',
        'macys'     => 'getMacysChannelData',
        'tiendamia' => 'getTiendamiaChannelData',
        'bestbuyusa'=> 'getBestbuyUsaChannelData',
        'newegg'    => 'getNeweggChannelData',
        'reverb'    => 'getReverbChannelData',
        'doba'      => 'getDobaChannelData',
        'temu'      => 'getTemuChannelData',
        'temu2'     => 'getTemu2ChannelData',
        'walmart'   => 'getWalmartChannelData',
        'pls'       => 'getPlsChannelData',
        'wayfair'   => 'getWayfairChannelData',
        'faire'     => 'getFaireChannelData',
        'purchasingpower' => 'getPurchasingPowerChannelData',
        'shein'     => 'getSheinChannelData',
        'tiktokshop'=> 'getTiktokChannelData',
        'tiktokshop2'   => 'getTikTokTwoChannelData',
        'depop'         => 'getDepopChannelData',
        'instagramshop' => 'getInstagramChannelData',
        'aliexpress' => 'getAliexpressChannelData',
        'mercariwship' => 'getMercariWShipChannelData',
        'mercariwoship' => 'getMercariWoShipChannelData',
        'fbmarketplace' => 'getFbMarketplaceChannelData',
        'fbshop'    => 'getFbShopChannelData',
        'business5core'    => 'getBusiness5CoreChannelData',
        'topdawg'    => 'getTopDawgChannelData',
        'shopifyb2c' => 'getShopifyB2CChannelData',
        'shopifyb2b' => 'getShopifyB2BChannelData',
    ];

    foreach ($channels as $channelRow) {
        $channel = $channelRow->channel;

        $row = [
            'Channel '       => ucfirst($channel),
            'Link'           => null,
            'sheet_link'     => $channelRow->sheet_link,
            'L-60 Sales'     => 0,
            'L30 Sales'      => 0,
            'Growth'         => 0,
            'L60 Orders'     => 0,
            'L30 Orders'     => 0,
            'Gprofit%'       => 'N/A',
            'gprofitL60'     => 'N/A',
            'G Roi%'         => 'N/A',
            'G RoiL60'       => 'N/A',
            'red_margin'     => 0,
            'NR'             => 0,
            'type'           => '',
            'listed_count'   => 0,
            'W/Ads'          => 0,
            'channel_percentage' => $channelRow->channel_percentage ?? '',
            'Update'         => 0,
            'Account health' => null,
        ];

        $key = strtolower(str_replace([' ', '-', '&', '/'], '', trim($channel)));

        if (isset($controllerMap[$key]) && method_exists($this, $controllerMap[$key])) {
            $method = $controllerMap[$key];
            $data = $this->$method($request)->getData(true);
            if (!empty($data['data'])) {
                $row = array_merge($row, $data['data'][0]);
            }
        }

        $finalData[] = $row;
    }

    // ✅ Calculate total L30 Sales
    $totalL30Sales = collect($finalData)->sum(function ($item) {
        return (float) str_replace(',', '', $item['L30 Sales']);
    });

    return response()->json([
        'status'  => 200,
        'message' => 'Channel sales data fetched successfully',
        'data'    => $totalL30Sales,
    ]);
}



    public function getAmazonChannelData(Request $request)
    {
        $result = [];

        // Same rolling window as Amazon daily sales (Pacific, through yesterday)
        $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
        $endToday = $yesterdayPacific->copy()->endOfDay();
        $amazonWindowDays = AmazonSalesController::DAILY_SALES_WINDOW_DAYS;
        $startAmazonWindow = $yesterdayPacific->copy()->subDays($amazonWindowDays - 1)->startOfDay();

        $activeAmazonOrders = function ($q) {
            $q->where(function ($w) {
                $w->whereNull('o.status')
                    ->orWhereNotIn('o.status', ['Canceled', 'Cancelled']);
            });
        };

        // Sales + order count (same as Amazon Daily Sales: AMAZON_SALES_TOTAL_MODE + DAILY_SALES_WINDOW_DAYS, Pacific through yesterday)
        $l30SalesFromOrders = AmazonOrder::badgeTotalSalesByOrderDate($startAmazonWindow, $endToday);
        $l30OrdersFromOrders = (int) DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $startAmazonWindow)
            ->where('o.order_date', '<=', $endToday)
            ->where($activeAmazonOrders)
            ->count(DB::raw('DISTINCT o.amazon_order_id'));

        // Line quantities only (item join)
        $qtyAgg = DB::table('amazon_orders as o')
            ->join('amazon_order_items as i', 'o.id', '=', 'i.amazon_order_id')
            ->where('o.order_date', '>=', $startAmazonWindow)
            ->where('o.order_date', '<=', $endToday)
            ->where($activeAmazonOrders)
            ->selectRaw('COALESCE(SUM(i.quantity), 0) as total_qty')
            ->first();

        $totalQuantityFromOrders = (int) ($qtyAgg->total_qty ?? 0);

        // Get other metrics from marketplace_daily_metrics (PFT%, ROI, TACOS, ad spend, etc.)
        $metrics = MarketplaceDailyMetric::where('channel', 'Amazon')->latest('date')->first();
        
        // Calculate L60 data (days 31-60) using date-based filtering
        // L60 period: from 60 days ago to 30 days ago (previous 30-day period)
        $sixtyDaysAgoPacific = $yesterdayPacific->copy()->subDays(59)->startOfDay();
        $thirtyDaysAgoPacific = $yesterdayPacific->copy()->subDays(30)->endOfDay();
        
        // L60 Sales (previous 30-day period: days 31-60)
        $l60Sales = AmazonOrder::badgeTotalSalesByOrderDate($sixtyDaysAgoPacific, $thirtyDaysAgoPacific);
        $l60Orders = (int) DB::table('amazon_orders as o')
            ->where('o.order_date', '>=', $sixtyDaysAgoPacific)
            ->where('o.order_date', '<=', $thirtyDaysAgoPacific)
            ->where($activeAmazonOrders)
            ->count(DB::raw('DISTINCT o.amazon_order_id'));
        
        // DISABLED: L60 data from ShipHub - now using date-based calculation from amazon_orders
        /*
        $latestDate = null;
        try {
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'amazon')
                ->max('order_date');
        } catch (\Throwable $e) {
            Log::warning('ShipHub connection failed in getAmazonChannelData: ' . $e->getMessage());
        }

        if ($latestDate) {
            try {
                $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
                $l60StartDate = $latestDateCarbon->copy()->subDays(59)->startOfDay();
                $l60EndDate = $latestDateCarbon->copy()->subDays(30)->endOfDay();

                $l60OrderItems = DB::connection('shiphub')
                    ->table('orders as o')
                    ->join('order_items as i', 'o.id', '=', 'i.order_id')
                    ->whereBetween('o.order_date', [$l60StartDate, $l60EndDate])
                    ->where('o.marketplace', '=', 'amazon')
                    ->where(function($query) {
                        $query->where('o.order_status', '!=', 'Canceled')
                              ->where('o.order_status', '!=', 'Cancelled')
                              ->orWhereNull('o.order_status');
                    })
                    ->select(
                        DB::raw('COUNT(i.id) as order_count'),
                        DB::raw('SUM(i.unit_price) as total_sales')
                    )
                    ->first();

                $l60Orders = $l60OrderItems->order_count ?? 0;
                $l60Sales = $l60OrderItems->total_sales ?? 0;
            } catch (\Throwable $e) {
                Log::warning('ShipHub L60 query failed: ' . $e->getMessage());
                $l60Orders = 0;
                $l60Sales = 0;
            }
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
        }
        */

        // Prefer order-based L30 (from amazon_orders); fallback to metrics when no orders
        $l30Sales = $l30OrdersFromOrders > 0 ? $l30SalesFromOrders : ($metrics?->total_sales ?? 0);
        $l30Orders = $l30OrdersFromOrders > 0 ? $l30OrdersFromOrders : ($metrics?->total_orders ?? 0);
        $totalQuantity = $totalQuantityFromOrders > 0 ? $totalQuantityFromOrders : ($metrics?->total_quantity ?? 0);
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;
        
        // Get ad spend from marketplace_daily_metrics (pre-calculated from UpdateMarketplaceDailyMetrics)
        $kwSpent = $metrics?->kw_spent ?? 0;
        $ptSpent = $metrics?->pmt_spent ?? 0; // Note: stored as pmt_spent in metrics table
        $hlSpent = $metrics?->hl_spent ?? 0;
        $totalAdSpend = round($kwSpent + $ptSpent + $hlSpent, 2);
        
        // Calculate growth: ((L30 - L60) / L60) * 100
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage (still needs calculation if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Amazon')->first();

        // Live Amazon Map/Miss/NMap (matches amazon-tabulator-view backend), with a
        // fallback to the stored amazon_channel_summary_data inside the live method.
        $mapMissCounts = $this->getAmazonLiveMapMissNMapCounts();

        $result[] = [
            'Channel '   => 'Amazon',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 0),
            'PT Spent'   => round($ptSpent, 0),
            'HL Spent'   => round($hlSpent, 0),
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData?->type ?? '',
            'W/Ads'      => $channelData?->w_ads ?? 0,
            'NR'         => $channelData?->nr ?? 0,
            'Update'     => $channelData?->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Fetch Amazon FBA channel data.
     * FBA campaigns end with 'FBA' or 'FBA PT', separate from regular Amazon KW/PT campaigns.
     */
    
    public function getAmazonFbaChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table for Amazon FBA
        $metrics = MarketplaceDailyMetric::where('channel', 'Amazon FBA')->latest('date')->first();

        // Get L60 data from ShipHub for Amazon FBA (30-59 days ago range)
        $latestDate = null;
        try {
            $latestDate = DB::connection('shiphub')
                ->table('orders')
                ->where('marketplace', '=', 'amazon-fba')
                ->max('order_date');
        } catch (\Throwable $e) {
            Log::warning('ShipHub connection failed in getAmazonFbaChannelData: ' . $e->getMessage());
        }

        if ($latestDate) {
            try {
                $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
                $l60StartDate = $latestDateCarbon->copy()->subDays(59)->startOfDay();
                $l60EndDate = $latestDateCarbon->copy()->subDays(30)->endOfDay();

                $l60OrderItems = DB::connection('shiphub')
                    ->table('orders as o')
                    ->join('order_items as i', 'o.id', '=', 'i.order_id')
                    ->whereBetween('o.order_date', [$l60StartDate, $l60EndDate])
                    ->where('o.marketplace', '=', 'amazon-fba')
                    ->where(function($query) {
                        $query->where('o.order_status', '!=', 'Canceled')
                              ->where('o.order_status', '!=', 'Cancelled')
                              ->orWhereNull('o.order_status');
                    })
                    ->select(
                        DB::raw('COUNT(i.id) as order_count'),
                        DB::raw('SUM(i.unit_price) as total_sales')
                    )
                    ->first();

                $l60Orders = $l60OrderItems->order_count ?? 0;
                $l60Sales = $l60OrderItems->total_sales ?? 0;
            } catch (\Throwable $e) {
                Log::warning('ShipHub L60 query failed for Amazon FBA: ' . $e->getMessage());
                $l60Orders = 0;
                $l60Sales = 0;
            }
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
        }

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;

        // FBA KW + PT spend: fetch from tables using FBA-specific filters
        try {
            $adSpendBreakdown = $this->fetchAmazonFbaAdSpendBreakdownFromTables();
            $kwSpent = $adSpendBreakdown['kw'];
            $ptSpent = $adSpendBreakdown['pt'];
        } catch (\Throwable $e) {
            Log::error('fetchAmazonFbaAdSpendBreakdownFromTables failed: ' . $e->getMessage());
            $kwSpent = $ptSpent = 0;
        }
        $totalAdSpend = round($kwSpent + $ptSpent, 2);

        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::whereRaw("LOWER(REPLACE(channel, ' ', '')) = 'amazonfba'")->first();

        // Get Map and Miss counts
        $mapMissCounts = $this->getMapAndMissCounts('amazonfba');

        $result[] = [
            'Channel '   => 'Amazon FBA',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => round($ptSpent, 2),
            'HL Spent'   => 0, // FBA doesn't have HL (Headline) ads separate
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2),
            'TACOS'      => round($tacosPercentage, 2),
            'AD CLICKS'  => 0,
            'AD SALES'   => 0,
            'AD SOLD'    => 0,
            'ACOS'       => 0,
            'AD CVR'     => 0,
            'Link'       => null,
            'sheet_link' => $channelData->sheet_link ?? null,
            'missing_link' => $channelData->missing_link ?? null,
            'type'       => $channelData->type ?? 'B2C',
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Amazon FBA channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Amazon
     * This counts SKUs with INV > 0 that are not listed on Amazon and are not marked as NRL
     */
    private function getAmazonMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = AmazonDataView::whereIn('sku', $skus)->get()->keyBy('sku');
        $amazonListed = \App\Models\ProductStockMapping::pluck('inventory_amazon_product', 'sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $mappingValue = $amazonListed[$sku] ?? null;
            $listed = null;
            if ($mappingValue !== null) {
                $normalized = strtolower(trim($mappingValue));
                $listed = $normalized !== '' && $normalized !== 'not listed' ? 'Listed' : 'Not Listed';
            } else {
                $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
                if (is_bool($listedValue)) {
                    $listed = $listedValue ? 'Listed' : 'Not Listed';
                } else {
                    $listed = $listedValue;
                }
            }

            // Read NRL field from amazon_data_view - "REQ" means RL, "NRL" means NRL
            $nrlValue = $status['NRL'] ?? 'REQ';
            $nrReq = ($nrlValue === 'NRL') ? 'NR' : 'REQ';

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    /**
     * Calculate Missing Listing count for eBay
     * This counts SKUs with INV > 0 that are not listed on eBay and are not marked as NRL
     */
    private function getEbayMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = \App\Models\EbayDataView::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // NR/REQ logic - read from NRL field
            $nrlValue = $status['NRL'] ?? null;
            $nrReq = 'REQ'; // Default
            if ($nrlValue === 'NRL') {
                $nrReq = 'NRL';
            }

            // Read listed field
            $listedValue = $status['Listed'] ?? $status['listed'] ?? null;
            $listed = null;
            if (is_bool($listedValue)) {
                $listed = $listedValue ? 'Listed' : 'Pending';
            } else if (is_string($listedValue)) {
                $listed = $listedValue;
            }

            // Count as pending (missing) if nr_req is not NRL AND listed is not Listed
            if ($nrReq !== 'NRL' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }


    public function getEbayChannelData(Request $request)
    {
        $result = [];

        // L30: marketplace_daily_metrics (app:update-marketplace-daily-metrics + period l30 orders)
        $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay');

        $l60 = EbayChannelMetricsService::summarizeL60Orders(1);
        $l60Orders = $l60['orders'];
        $l60Sales = $l60['sales'];
        $totalProfitL60 = $l60['total_profit'];
        $totalCogsL60 = $l60['total_cogs'];

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;

        // NOTE: a previous version of this method overwrote $l30Orders with
        //   $this->getEbayTabulatorL30UnitsForCvr('ebay')
        // so that the legacy CVR formula (L30 Orders / Total Views) on
        // /all-marketplace-master would secretly compute units/views and match
        // the ebay-tabulator-view CVR. /all-marketplace-master's CVR is now
        // Qty / Total Views directly (see updateSummaryStats on the blade), so
        // that hack is unnecessary and actively wrong — it caused the L30
        // Orders column to display unit counts (e.g. 2,579) instead of real
        // order counts (1,327), making "Orders > Qty" appear in the table even
        // though one order can never have fewer units than itself. $l30Orders
        // is left at marketplace_daily_metrics.total_orders, which is the
        // ground-truth eBay order count.

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay KW Ads & PMT Ads pages)
        $ebayBreakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebay');
        $kwSpent = $ebayBreakdown['kw'];
        $pmtSpent = $ebayBreakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'eBay')->first();

        // Map/Miss/NMap from live eBay tabulator rules (includes <=3 tolerance for Map/NMap)
        $mapMissCounts = $this->getEbayLiveMapMissCountsFromTabulator($request);

        $result[] = [
            'Channel '   => 'eBay',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay channel data fetched successfully',
            'data' => $result,
        ]);
    }


    public function getEbaytwoChannelData(Request $request)
    {
        $result = [];

        $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay 2');

        $l60 = EbayChannelMetricsService::summarizeL60Orders(2);
        $l60Orders = $l60['orders'];
        $l60Sales = $l60['sales'];
        $totalProfitL60 = $l60['total_profit'];
        $totalCogsL60 = $l60['total_cogs'];

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;

        // Same removal as eBay 1 — see getEbayChannelData() comment. Keeping
        // $l30Orders as marketplace_daily_metrics.total_orders so the master
        // page's "L30 Orders" column shows real order counts, not units.

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay 2 KW Ads & PMT Ads pages)
        $ebay2Breakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebaytwo');
        $kwSpent = $ebay2Breakdown['kw'];
        $pmtSpent = $ebay2Breakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;

        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayTwo')->first();

        $mapMissCounts = $this->getEbay2LiveMapMissCountsFromTabulator($request);

        $result[] = [
            'Channel '   => 'EbayTwo',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay2 channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get eBay Two Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getEbayTwoMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = EbayTwoListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // eBay Two uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getEbaythreeChannelData(Request $request)
    {
        $result = [];

        $metrics = EbayChannelMetricsService::latestDailyMetrics('eBay 3');

        $l60 = EbayChannelMetricsService::summarizeEbay3L60();
        $l60Orders = $l60['orders'];
        $l60Sales = $l60['sales'];
        $totalProfitL60 = $l60['total_profit'];
        $totalCogsL60 = $l60['total_cogs'];

        $l30Sales = $metrics?->total_sales ?? 0;
        $l30Orders = $metrics?->total_orders ?? 0;
        $totalQuantity = $metrics?->total_quantity ?? 0;
        $totalProfit = $metrics?->total_pft ?? 0;
        $totalCogs = $metrics?->total_cogs ?? 0;
        $gProfitPct = $metrics?->pft_percentage ?? 0;
        $gRoi = $metrics?->roi_percentage ?? 0;
        $tacosPercentage = $metrics?->tacos_percentage ?? 0;
        $nPft = $metrics?->n_pft ?? 0;
        $nRoi = $metrics?->n_roi ?? 0;

        // Same removal as eBay 1 — see getEbayChannelData() comment. Keeping
        // $l30Orders as marketplace_daily_metrics.total_orders so the master
        // page's "L30 Orders" column shows real order counts, not units.

        // KW/PMT Spend: fetch directly from tables (same logic as Ebay 3 KW Ads & PMT Ads pages)
        $ebay3Breakdown = $this->fetchEbayAdSpendBreakdownFromTables('ebaythree');
        $kwSpent = $ebay3Breakdown['kw'];
        $pmtSpent = $ebay3Breakdown['pmt'];
        $totalAdSpend = $kwSpent + $pmtSpent;

        // Calculate growth: ((L30 - L60) / L60) * 100
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'EbayThree')->first();

        // eBay3 Map/Miss/NMap: compute from same tree payload as ebay3 tabulator view.
        $mapMissCounts = $this->getEbay3LiveMapMissCountsFromTabulator($request);

        $result[] = [
            'Channel '   => 'EbayThree',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'KW Spent'   => round($kwSpent, 2),
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => round($pmtSpent, 2),
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0, 2),
            'TACOS %'    => round($tacosPercentage, 2),
            'N PFT'      => round($nPft, 2),
            'N ROI'      => round($nRoi, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'eBay three channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Keep all-marketplace-master eBay3 Map/Miss/NMap aligned with ebay3 tabulator rules.
     */
    private function getEbay3LiveMapMissCountsFromTabulator(Request $request): array
    {
        try {
            $response = app(MarketPlaceEbayThreeController::class)->getViewEbay3DataTabulator($request);
            $payload = json_decode($response->getContent(), true);
            $tree = $payload['data'] ?? [];
            if (!is_array($tree)) {
                return $this->getMapAndMissCounts('ebay3');
            }

            $rows = [];
            $walk = function ($node) use (&$rows, &$walk) {
                if (!is_array($node)) return;
                $sku = strtoupper(trim((string)($node['(Child) sku'] ?? '')));
                if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                    $rows[] = $node;
                }
                if (!empty($node['_children']) && is_array($node['_children'])) {
                    foreach ($node['_children'] as $child) {
                        $walk($child);
                    }
                }
            };
            foreach ($tree as $root) {
                $walk($root);
            }

            $missing = 0;
            $map = 0;
            $nmap = 0;
            $views = 0;
            $eps = 1e-9;

            foreach ($rows as $row) {
                $itemId = $row['eBay_item_id'] ?? null;
                $hasItem = !($itemId === null || trim((string)$itemId) === '');
                $nrReq = strtoupper(trim((string)($row['nr_req'] ?? 'REQ')));

                $views += (float)($row['views'] ?? 0);

                $inv = $this->parseEbay3InvForMapMiss((string)($row['INV'] ?? '0'));
                $eStock = (float)($row['eBay Stock'] ?? ($row['E Stock'] ?? 0));
                if (abs($eStock) < $eps) {
                    $eStock = 0.0;
                }

                // Missing L: in stock (INV>0) but not listed (no item id); exclude NR — same as ebay3 tabulator badge
                if ($inv > 0 && !$hasItem && $nrReq !== 'NR') {
                    $missing++;
                }

                // Map / N Map — same rule as ebay2/ebay3 tabulator badges (listed item, REQ, INV>0)
                if ($nrReq === 'REQ' && $hasItem && $inv > 0) {
                    if ($eStock > 0) {
                        if (abs($inv - $eStock) <= 3.0 + $eps) {
                            $map++;
                        } else {
                            $nmap++;
                        }
                    } elseif ($inv > 3) {
                        $nmap++;
                    }
                }
            }

            return [
                'map' => $map,
                'miss' => $missing,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay3 live map/miss fallback used: ' . $e->getMessage());
            return $this->getMapAndMissCounts('ebay3');
        }
    }

    /**
     * Match ebay_tabulator_view.js missing/N Map checks for eBay_item_id: !itemId is true for 0
     * (number) but the string "0" is still truthy, like Tabulator/JS.
     */
    private function ebayTabulatorRowHasListingItemId($raw): bool
    {
        if ($raw === null) {
            return false;
        }
        if (is_int($raw) || is_float($raw)) {
            return abs((float) $raw) >= 1e-9;
        }
        if (is_string($raw)) {
            return trim($raw) !== '';
        }
        if (is_bool($raw)) {
            return $raw;
        }

        return trim((string) $raw) !== '';
    }

    /**
     * Keep all-marketplace-master eBay Map/Miss/NMap aligned with the ebay-tabulator-view badges
     * (Missing L / Missing M), counted over the full dataset like the tabulator badges (allData),
     * not the default "E Stock > 0" view filter.
     *   Missing L (miss): not listed (no item_id), nr_req != 'NR', INV > 0, non-parent.
     *   Map / N Map (Missing M): listed, REQ, INV > 0, eBay Stock > 0; mapped within the same
     *   /map-issues tolerance (abs gap > 3 units when 3% of INV < 3, else rounded % > 3 = N Map).
     */
    private function getEbayLiveMapMissCountsFromTabulator(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\EbayController::class)->getViewEbayData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (!is_array($rows)) {
                return $this->getMapAndMissCounts('ebay');
            }

            $missing = 0;
            $map = 0;
            $nmap = 0;
            $views = 0;

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $parent = trim((string) ($row['Parent'] ?? ''));
                $isParentSummary = (($row['is_parent_summary'] ?? false) === true)
                    || ($parent !== '' && stripos($parent, 'PARENT') === 0);
                if ($isParentSummary) {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                $eStockRaw = $row['eBay Stock'] ?? ($row['E Stock'] ?? 0);
                $eStock = is_numeric($eStockRaw) ? (float) $eStockRaw : 0.0;
                $rawItemId = $row['eBay_item_id'] ?? null;
                $hasItem = $this->ebayTabulatorRowHasListingItemId($rawItemId);
                $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? '')));
                $isReq = ($nrReq === 'REQ');

                // Views: traffic to live listings (E Stock > 0, REQ) — unchanged scope.
                if ($eStock > 0 && $isReq) {
                    $views += (float) ($row['views'] ?? 0);
                }

                // Both Missing L and Missing M are REQ only (nr_req can also be NRL / LATER / NR) and INV > 0.
                if (! $isReq || $inv <= 0) {
                    continue;
                }

                if (! $hasItem) {
                    // Missing L: in stock but not listed on eBay.
                    $missing++;
                    continue;
                }

                // Listed: Map vs N Map / Missing M — same rule as /map-issues. Both sides must
                // have stock (eBay Stock > 0); otherwise the row is neither Map nor N Map.
                if ($eStock <= 0) {
                    continue;
                }
                $diff = abs($inv - $eStock);
                if ($inv * 0.03 < 3) {
                    $isNotMap = $diff > 3;
                } else {
                    $isNotMap = round(($diff / $inv) * 100) > 3;
                }
                if ($isNotMap) {
                    $nmap++;
                } else {
                    $map++;
                }
            }

            return [
                'map' => $map,
                'miss' => $missing,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay live map/miss fallback used: ' . $e->getMessage());
            return $this->getMapAndMissCounts('ebay');
        }
    }

    /**
     * Keep all-marketplace-master eBay 2 Map/Miss/NMap aligned with the ebay2-tabulator-view badges
     * (Missing L / Missing M), counted over the full dataset like the tabulator badges.
     *   Missing L (miss): not listed (no item_id), REQ, INV > 0, non-parent.
     *   Map / N Map (Missing M): listed, REQ, INV > 0, eBay Stock > 0; mapped within the same
     *   /map-issues tolerance (abs gap > 3 units when 3% of INV < 3, else rounded % > 3 = N Map).
     */
    private function getEbay2LiveMapMissCountsFromTabulator(Request $request): array
    {
        try {
            $response = app(\App\Http\Controllers\MarketPlace\EbayTwoController::class)->getViewEbayData($request);
            $payload = json_decode($response->getContent(), true);
            $rows = $payload['data'] ?? [];
            if (! is_array($rows)) {
                return $this->getMapAndMissCounts('ebay2');
            }

            $missing = 0;
            $map = 0;
            $nmap = 0;
            $views = 0;

            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                if (! is_array($row)) {
                    continue;
                }

                $parent = trim((string) ($row['Parent'] ?? ''));
                $isParentSummary = (($row['is_parent_summary'] ?? false) === true)
                    || ($parent !== '' && stripos($parent, 'PARENT') === 0);
                if ($isParentSummary) {
                    continue;
                }

                $inv = (float) ($row['INV'] ?? 0);
                $eStockRaw = $row['E Stock'] ?? ($row['eBay Stock'] ?? 0);
                $eStock = is_numeric($eStockRaw) ? (float) $eStockRaw : 0.0;
                $rawItemId = $row['eBay_item_id'] ?? null;
                $hasItem = $this->ebayTabulatorRowHasListingItemId($rawItemId);
                $nrReq = strtoupper(trim((string) ($row['nr_req'] ?? '')));
                $isReq = ($nrReq === 'REQ');

                // Views: traffic to live listings (E Stock > 0, REQ) — unchanged scope.
                if ($eStock > 0 && $isReq) {
                    $views += (float) ($row['views'] ?? 0);
                }

                // Both Missing L and Missing M are REQ only (nr_req can also be NRL / LATER / NR) and INV > 0.
                if (! $isReq || $inv <= 0) {
                    continue;
                }

                if (! $hasItem) {
                    // Missing L: in stock but not listed on eBay.
                    $missing++;
                    continue;
                }

                // Listed: Map vs N Map / Missing M — same rule as /map-issues. Both sides must
                // have stock (eBay Stock > 0); otherwise the row is neither Map nor N Map.
                if ($eStock <= 0) {
                    continue;
                }
                $diff = abs($inv - $eStock);
                if ($inv * 0.03 < 3) {
                    $isNotMap = $diff > 3;
                } else {
                    $isNotMap = round(($diff / $inv) * 100) > 3;
                }
                if ($isNotMap) {
                    $nmap++;
                } else {
                    $map++;
                }
            }

            return [
                'map' => $map,
                'miss' => $missing,
                'nmap' => $nmap,
                'total_views' => $views,
            ];
        } catch (\Throwable $e) {
            Log::warning('eBay 2 live map/miss fallback: ' . $e->getMessage());

            return $this->getMapAndMissCounts('ebay2');
        }
    }

    private function parseEbay3InvForMapMiss(string $raw): float
    {
        $v = trim(str_replace(',', '', $raw));
        if ($v === '' || $v === '-' || strtoupper($v) === 'N/A') {
            return 0.0;
        }
        return is_numeric($v) ? (float)$v : 0.0;
    }

    /**
     * Get eBay Three Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getEbayThreeMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = EbayThreeListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // eBay Three uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMacysChannelData(Request $request)
    {
        $result = [];

        // Use MacysSalesController to get live data from MiraklDailyData (same as /macys/daily-sales page)
        $macysSalesCtrl = app(\App\Http\Controllers\Sales\MacysSalesController::class);
        $salesRequest = Request::create('/macys/daily-sales-data', 'GET');
        $salesResponse = $macysSalesCtrl->getData($salesRequest);
        $salesData = json_decode($salesResponse->getContent(), true);

        // Calculate totals from sales data
        $l30Sales = 0;
        $l30Orders = count($salesData);
        $totalQuantity = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $totalWeightedPrice = 0;
        $totalQuantityForPrice = 0;

        foreach ($salesData as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $saleAmount = (float) ($row['sale_amount'] ?? 0);
            $pft = (float) ($row['pft'] ?? 0);
            $cogs = (float) ($row['cogs'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);

            $l30Sales += $saleAmount;
            $totalQuantity += $quantity;
            $totalProfit += $pft;
            $totalCogs += $cogs;
            
            if ($quantity > 0 && $unitPrice > 0) {
                $totalWeightedPrice += $unitPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }
        }

        // Calculate percentages
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;

        // L60 = previous 30-day period (days 31–60) from mirakl_daily_data, same filter (!= CLOSED)
        // as before so existing numbers don't shift. PFT/COGS now also computed from those rows
        // so gprofitL60 / G RoiL60 are real (previously hardcoded to 0).
        $l60Summary = $this->computeMacysL60SummaryFromMirakl();
        $l60Sales = $l60Summary['sales'];
        $l60Orders = $l60Summary['orders'];

        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $gprofitL60 = $l60Sales > 0 ? ($l60Summary['pft'] / $l60Sales) * 100 : 0;
        $gRoiL60 = $l60Summary['cogs'] > 0 ? ($l60Summary['pft'] / $l60Summary['cogs']) * 100 : 0;

        // N PFT = same as Gprofit% for Macys (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Macys (no ads)
        $nRoi = $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Macys')->first();

        // Map / Miss / NMap: same live rules as macys-pricing badges (with <=3 tolerance for map)
        $mapMissCounts = $this->getMacysLiveMapMissNMapFromPricingData();

        $result[] = [
            'Channel '   => 'Macys',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Macys channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Macy L60 sales/orders/PFT/COGS from mirakl_daily_data, days 31–60 window (same as
     * pre-existing L60 logic — date-range query, CLOSED-only status filter — so L60 Sales / L60 Orders
     * stay identical to before). PFT/COGS use MacysSalesController L30 math so gprofitL60 and G RoiL60
     * actually populate (they were hardcoded to 0 before).
     *
     * @return array{sales: float, orders: int, qty: int, pft: float, cogs: float}
     */
    private function computeMacysL60SummaryFromMirakl(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $sixtyDaysAgo = Carbon::now()->subDays(60);

        $orders = \App\Models\MiraklDailyData::where('channel_name', "Macy's, Inc.")
            ->where('status', '!=', 'CLOSED')
            ->whereBetween('order_created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->get();

        if ($orders->isEmpty()) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $skus = $orders->pluck('sku')->filter()->unique()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Macys')->first();
        $margin = ($marketplaceData ? $marketplaceData->percentage : 76) / 100;

        $sales = 0.0;
        $orderCount = 0;
        $qty = 0;
        $pft = 0.0;
        $cogs = 0.0;

        foreach ($orders as $order) {
            if (! $order->sku || $order->sku === '') {
                continue;
            }

            $orderCount++;
            $quantity = (float) ($order->quantity ?? 1);
            $unitPrice = (float) ($order->unit_price ?? 0);
            $saleAmount = $unitPrice * $quantity;

            $qty += (int) $quantity;
            $sales += $saleAmount;

            $lp = 0.0;
            $ship = 0.0;
            $weightAct = 0.0;
            if (isset($productMasters[$order->sku])) {
                $pm = $productMasters[$order->sku];
                $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower($k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
                $ship = isset($values['ship']) ? (float) $values['ship'] : (isset($pm->ship) ? (float) $pm->ship : 0.0);
                if (isset($values['wt_act'])) {
                    $weightAct = (float) $values['wt_act'];
                }
            }

            $tWeight = $weightAct * $quantity;
            if ((int) $quantity === 1) {
                $shipCost = $ship;
            } elseif ($quantity > 1 && $tWeight < 20) {
                $shipCost = $ship / $quantity;
            } else {
                $shipCost = $ship;
            }

            $cogs += $lp * $quantity;
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $pft += $pftEach * $quantity;
        }

        return [
            'sales' => $sales,
            'orders' => $orderCount,
            'qty' => $qty,
            'pft' => $pft,
            'cogs' => $cogs,
        ];
    }

    private function getMacysMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = MacysListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Macy's uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    /**
     * Pacific calendar windows for Reverb daily order rows (same anchor as computeReverbL7SalesLikeAmazon).
     */
    private function getReverbDailyDataPacificWindows(): ?array
    {
        $latestRaw = DB::table('reverb_daily_data')->whereNotNull('order_date')->max('order_date');
        if (! $latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        // Use latest date (today) instead of yesterday to match sales badge
        $l30EndDate = $latestPacific->toDateString();  // Today (not yesterday)
        $l30StartDate = $latestPacific->copy()->subDays(29)->toDateString();  // 30 days including today

        // L60 column = "prior 30 days" (days 31-60), matching Amazon/Temu/Walmart/etc.
        // Growth = (L30 - L60) / L60 * 100 then makes sense across all channels.
        $prior30End = Carbon::parse($l30StartDate)->subDay()->toDateString();
        $prior30Start = Carbon::parse($prior30End)->subDays(29)->toDateString();

        return [
            'l30_start' => $l30StartDate,
            'l30_end' => $l30EndDate,
            'l60_start' => $prior30Start,
            'l60_end' => $prior30End,
            'prior30_start' => $prior30Start,
            'prior30_end' => $prior30End,
        ];
    }

    /**
     * Full-table totals from reverb_daily_data — same query semantics as ReverbController::reverbDailyDataTotalsJson().
     *
     * @return array{sum_quantity: int, sum_qty_x_amount: float}|null
     */
    private function getReverbDailyDataFullTableTotals(): ?array
    {
        if (! Schema::hasTable('reverb_daily_data')) {
            return null;
        }

        $agg = DB::table('reverb_daily_data')
            ->selectRaw('COALESCE(SUM(quantity), 0) as sum_quantity')
            ->selectRaw('COALESCE(SUM(quantity * COALESCE(amount, 0)), 0) as sum_qty_x_amount')
            ->first();

        return [
            'sum_quantity' => (int) ($agg->sum_quantity ?? 0),
            'sum_qty_x_amount' => (float) ($agg->sum_qty_x_amount ?? 0),
        ];
    }

    /**
     * Group reverb_daily_data by SKU: revenue = Σ(quantity × COALESCE(amount, 0)) like /reverb-pricing;
     * GPFT ord–style profit = revenue − qty × (LP + Ship) from product_master.
     *
     * @param  \Illuminate\Database\Query\Builder  $baseQuery  query on reverb_daily_data with any date filters
     * @param  \Illuminate\Support\Collection<string,\App\Models\ProductMaster>  $productMastersByUpperSku
     * @return array{qty: int, sales: float, profit: float, cogs: float}
     */
    private function aggregateReverbDailyProfitGroupedQuery($baseQuery, $productMastersByUpperSku): array
    {
        // Get Reverb marketplace percentage (net revenue after fees) - matching frontend
        $mpRow = \App\Models\MarketplacePercentage::where('marketplace', 'Reverb')->first();
        $percentage = $mpRow !== null ? (float) ($mpRow->percentage ?? 85) : 85.0;
        if ($percentage <= 0) {
            $percentage = 85.0;
        }
        $margin = $percentage / 100.0;

        // Get individual rows instead of grouping by SKU (matching frontend badge calculation)
        $rows = (clone $baseQuery)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select('sku', 'quantity', 'product_subtotal', 'amount', 'bump_fee')
            ->get();

        $totalQty = 0;
        $totalRev = 0.0;
        $totalProfit = 0.0;
        $totalCogs = 0.0;
        $totalBumpFees = 0.0;

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) $row->sku));
            $qty = max(1, (int) ($row->quantity ?? 1));
            
            // Revenue: prefer product_subtotal, fallback to amount (matching ReverbSalesController line 124)
            $productSubtotal = (float) ($row->product_subtotal ?? 0);
            $amount = (float) ($row->amount ?? 0);
            $lineTotal = $productSubtotal > 0 ? $productSubtotal : $amount;
            
            if ($qty <= 0 && $lineTotal <= 0) {
                continue;
            }
            
            // Unit price (matching ReverbSalesController line 125)
            $unitPrice = $lineTotal > 0 ? $lineTotal / $qty : 0;

            $lp = 0.0;
            $ship = 0.0;
            if (isset($productMastersByUpperSku[$sku])) {
                $pm = $productMastersByUpperSku[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp = isset($values['lp']) ? (float) $values['lp'] : (float) ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : (float) ($pm->ship ?? 0);
            }

            // Calculate PFT per unit, then total (matching ReverbSalesController lines 134, 140)
            $pftEach = ($unitPrice * $margin) - $lp - $ship;
            $tPft = $pftEach * $qty;
            
            // COGS (matching ReverbSalesController line 143)
            $lineCogs = $lp * $qty;
            
            // Bump Fees (matching frontend badge calculation)
            $bumpFee = (float) ($row->bump_fee ?? 0);

            $totalQty += $qty;
            $totalRev += $lineTotal;
            $totalCogs += $lineCogs;
            $totalProfit += $tPft;
            $totalBumpFees += $bumpFee;
        }

        return [
            'qty' => $totalQty,
            'sales' => $totalRev,
            'profit' => $totalProfit,
            'cogs' => $totalCogs,
            'bump_fees' => $totalBumpFees,
        ];
    }

    /**
     * Aggregate reverb_daily_data for a calendar date range (Pacific windows from getReverbDailyDataPacificWindows).
     *
     * @param  \Illuminate\Support\Collection<string,\App\Models\ProductMaster>  $productMastersByUpperSku
     * @return array{qty: int, sales: float, profit: float, cogs: float}
     */
    private function aggregateReverbDailyProfitForWindow(
        string $startDate,
        string $endDate,
        $productMastersByUpperSku
    ): array {
        $baseQuery = DB::table('reverb_daily_data')
            ->whereNotNull('order_date')
            ->whereBetween('order_date', [$startDate, $endDate])
            // Match frontend filters: exclude empty SKU/order_number
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('order_number')
            ->where('order_number', '!=', '')
            // Exclude cancelled/refunded orders (case-insensitive, matching frontend)
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%cancel%'])
            ->whereRaw('LOWER(COALESCE(status, "")) NOT LIKE ?', ['%refund%']);

        return $this->aggregateReverbDailyProfitGroupedQuery($baseQuery, $productMastersByUpperSku);
    }

    public function getReverbChannelData(Request $request)
    {
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $l30Sales = 0.0;
        $l60Sales = 0.0;
        $l30Orders = 0;
        $l60Orders = 0;
        $totalQuantity = 0;
        $totalProfit = 0.0;
        $totalProfitL60 = 0.0;
        $totalCogs = 0.0;
        $totalCogsL60 = 0.0;
        $growth = 0.0;
        $reverbPaceL30Sales = null;

        $useDailyData = Schema::hasTable('reverb_daily_data');
        $windows = $useDailyData ? $this->getReverbDailyDataPacificWindows() : null;

        if ($useDailyData && $windows !== null) {
            $fullTotals = $this->getReverbDailyDataFullTableTotals() ?? ['sum_quantity' => 0, 'sum_qty_x_amount' => 0.0];

            $l30 = $this->aggregateReverbDailyProfitForWindow(
                $windows['l30_start'],
                $windows['l30_end'],
                $productMasters
            );
            $l60 = $this->aggregateReverbDailyProfitForWindow(
                $windows['l60_start'],
                $windows['l60_end'],
                $productMasters
            );

            // Master "Sales" / Orders: use L30 window (last 30 days only, matching sales badge)
            $l30Sales = $l30['sales'];  // L30 sales from windowed calculation
            $l30Orders = $l30['qty'];   // L30 orders from windowed calculation
            $totalQuantity = $l30Orders;

            $l60Sales = $l60['sales'];
            $l60Orders = $l60['qty'];

            // GPFT ord–style margin on L30 revenue (matching sales badge calculation)
            $totalProfit = $l30['profit'];  // Use L30-specific profit
            $totalCogs = $l30['cogs'];      // Use L30-specific COGS

            $totalProfitL60 = $l60['profit'];
            $totalCogsL60 = $l60['cogs'];

            // Standard growth formula (consistent with Amazon/Temu/Walmart/etc.):
            //   L60 column above is now days 31-60 (prior 30), so this is the proper month-over-month comparison.
            $growth = $l60Sales > 0
                ? (($l30Sales - $l60Sales) / $l60Sales) * 100
                : ($l30Sales > 0 ? 100.0 : 0.0);

            $reverbPaceL30Sales = (int) round($l30['sales']);
        } else {
            // Fallback: reverb_products r_l30 × price (legacy) when daily table missing or empty
            $query = ReverbProduct::where('sku', 'not like', '%Parent%');

            $l30Orders = (int) $query->sum('r_l30');
            $l60Orders = (int) (clone $query)->sum('r_l60');
            $totalQuantity = $l30Orders;

            $l30Sales = (float) ((clone $query)->selectRaw('SUM(r_l30 * price) as total')->value('total') ?? 0);
            $l60Sales = (float) ((clone $query)->selectRaw('SUM(r_l60 * price) as total')->value('total') ?? 0);

            $percentage = ChannelMaster::where('channel', 'Reverb')->value('channel_percentage') ?? 100;
            $percentage = $percentage / 100;

            $ebayRows = $query->get(['sku', 'price', 'r_l30', 'r_l60']);

            foreach ($ebayRows as $row) {
                $sku = strtoupper($row->sku);
                $price = (float) $row->price;
                $unitsL30 = (int) $row->r_l30;
                $unitsL60 = (int) $row->r_l60;

                $soldAmount = $unitsL30 * $price;
                if ($soldAmount <= 0) {
                    continue;
                }

                $lp = 0.0;
                $ship = 0.0;
                if (isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                    $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
                }

                $profitPerUnit = ($price * $percentage) - $lp - $ship;
                $totalProfit += $profitPerUnit * $unitsL30;
                $totalProfitL60 += $profitPerUnit * $unitsL60;
                $totalCogs += ($unitsL30 * ($lp + $ship));
                $totalCogsL60 += ($unitsL60 * ($lp + $ship));
            }

            $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
            
            // Initialize $l30 array for fallback path (for consistency with daily data path)
            $l30 = [
                'sales' => $l30Sales,
                'qty' => $l30Orders,
                'profit' => $totalProfit,
                'cogs' => $totalCogs,
                'bump_fees' => 0, // Fallback path doesn't have bump_fee data
            ];
        }

        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        // No Reverb ads on master: N ROI = G ROI (same as Macys / Tiendamia; N ROI = GROI - TCOS when spend > 0).
        $nRoi = $gRoi;
        
        // Calculate Bump % (matching frontend Bump badge: Bump Fees / Sales × 100)
        $totalBumpFees = $l30['bump_fees'] ?? 0;
        $bumpPercentage = $l30Sales > 0 ? ($totalBumpFees / $l30Sales) * 100 : 0;

        $channelData = ChannelMaster::where('channel', 'Reverb')->first();
        $mapMissCounts = $this->getReverbLiveMapMissNMapFromPricingData($request);

        $result[] = [
            'Channel '   => 'Reverb',
            'L-60 Sales' => (int) round($l60Sales),
            'L30 Sales'  => (int) round($l30Sales),
            'reverb_pace_l30_sales' => $reverbPaceL30Sales,
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => (int) $totalQuantity,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 0),
            'G RoiL60'      => round($gRoiL60, 0),
            'Total PFT'  => round($totalProfit, 2),
            'N ROI'      => round($nRoi, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'Ads%'       => round($bumpPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('Reverb'),
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Reverb channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Reverb Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NRL
     */
    private function getReverbMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = ReverbListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // Reverb uses rl_nrl (RL/NRL)
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getDobaChannelData(Request $request)
    {
        $result = [];

        // L30 metrics from app:update-marketplace-daily-metrics
        $latestMetric = MarketplaceDailyMetric::where('channel', 'Doba')
            ->orderBy('date', 'desc')
            ->first();

        // L60: compute directly from doba_daily_data (period = 'l60'), same rules as calculateDobaMetrics.
        // Previous logic looked up marketplace_daily_metrics for "today - 60d", which almost never exists
        // (command runs for today only) and even when present means "L30 ending 60d ago" rather than today's L60.
        $l60Summary = $this->computeDobaL60SummaryFromDailyData();

        // Current metrics
        $l30Sales = $latestMetric ? $latestMetric->l30_sales : 0;
        $l30Orders = $latestMetric ? $latestMetric->total_orders : 0;
        $totalQuantity = $latestMetric ? $latestMetric->total_quantity : 0;
        $totalCogs = $latestMetric ? $latestMetric->total_cogs : 0;
        $totalPft = $latestMetric ? $latestMetric->total_pft : 0;
        $gProfitPct = $latestMetric ? $latestMetric->pft_percentage : 0;
        $gRoi = $latestMetric ? $latestMetric->roi_percentage : 0;
        $nPftPct = $latestMetric ? $latestMetric->n_pft : 0;
        $nRoi = $latestMetric ? $latestMetric->n_roi : 0;

        // L60 metrics (direct from doba_daily_data)
        $l60Sales = $l60Summary['sales'];
        $l60Orders = $l60Summary['orders'];
        $gprofitL60 = $l60Sales > 0 ? ($l60Summary['pft'] / $l60Sales) * 100 : 0;
        $gRoiL60 = $l60Summary['cogs'] > 0 ? ($l60Summary['pft'] / $l60Summary['cogs']) * 100 : 0;

        // Growth calculation
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Doba')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('doba');

        $result[] = [
            'Channel '   => 'Doba',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60'   => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'      => round($gRoiL60, 1),
            'Total PFT'  => round($totalPft, 2),
            'N PFT'      => round($nPftPct, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Doba channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Doba L60 sales/orders/PFT/COGS from doba_daily_data (period = l60).
     *
     * Mirrors the L60 badge on /doba/daily-sales (DobaSalesController::getData
     * + the page's updateSummary JS):
     *   1. Build the "active L30 SKU set" — distinct SKUs from rows where
     *      period = 'l30' AND sku and order_no are both non-empty (this matches
     *      the skip-condition in the doba page's updateSummary).
     *   2. Sum total_price for every period = 'l60' row whose SKU is in that
     *      active set. No order_status filter — the doba page badge does not
     *      apply one.
     *
     * Restricting L60 to the active L30 SKU set is what makes this match the
     * badge ($46,109.47 in the user's snapshot); without it /all-marketplace-master
     * was inflating L60 by ~2x with L60-only SKUs that have no L30 activity.
     *
     * @return array{sales: float, orders: int, qty: int, pft: float, cogs: float}
     */
    private function computeDobaL60SummaryFromDailyData(): array
    {
        $activeSkus = \App\Models\DobaDailyData::whereRaw('LOWER(period) = ?', ['l30'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('order_no')
            ->where('order_no', '!=', '')
            ->pluck('sku')
            ->map(fn ($s) => strtolower(trim((string) $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($activeSkus)) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $activeSkuSet = array_flip($activeSkus);

        $orders = \App\Models\DobaDailyData::whereRaw('LOWER(period) = ?', ['l60'])
            ->get();

        if ($orders->isEmpty()) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $rawSkus = $orders->pluck('sku')->filter()->unique()->values()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $rawSkus)->get()->keyBy('sku');

        $margin = 0.95;
        $sales = 0.0;
        $orderCount = 0;
        $qty = 0;
        $pft = 0.0;
        $cogs = 0.0;

        foreach ($orders as $order) {
            $skuKey = strtolower(trim((string) ($order->sku ?? '')));
            if ($skuKey === '' || ! isset($activeSkuSet[$skuKey])) {
                continue;
            }

            $orderCount++;
            $quantity = (int) ($order->quantity ?? 1);
            $itemPrice = (float) ($order->item_price ?? 0);
            $totalPrice = (float) ($order->total_price ?? 0);

            $qty += $quantity;
            $sales += $totalPrice;

            $lp = 0.0;
            $ship = 0.0;
            if (isset($productMasters[$order->sku])) {
                $pm = $productMasters[$order->sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['lp'])) {
                    $lp = (float) $values['lp'];
                }
                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                }
            }

            $cogs += $lp * $quantity;

            $shipCost = $quantity > 0 ? ($quantity === 1 ? $ship : $ship / $quantity) : $ship;

            if (strtolower($order->order_type ?? '') === 'pickup with a prepaid label') {
                $pftEach = ($itemPrice * $margin) - $lp;
            } else {
                $pftEach = ($itemPrice * $margin) - $shipCost - $lp;
            }
            $pft += $pftEach * $quantity;
        }

        return [
            'sales' => $sales,
            'orders' => $orderCount,
            'qty' => $qty,
            'pft' => $pft,
            'cogs' => $cogs,
        ];
    }

    private function getDobaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = DobaListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    /**
     * Derive today's L60 (sales + orders) for a Temu/Temu 2 channel from historical L30 snapshots.
     *
     * L30 snapshot taken on day X represents sales from days (X−30)..X.
     * "Today's L60" is days 31..60 ago = sales from a 30-day window ending ~30 days ago,
     * which is exactly what the L30 snapshot from ~30 days back recorded. Using it as the
     * L60 source means the value updates daily without the manual `*_daily_data_l60` upload
     * (which was being truncated and refilled by hand, and went stale).
     *
     * Picks the snapshot whose date is closest to (today − 30 days), within ±10 days, and
     * with l30_sales > 0 so a partial/zero upload doesn't poison the result. Returns null
     * when no usable snapshot exists so callers can fall back to the legacy static table.
     *
     * @param  string  $channelKey  Normalized channel key as stored in channel_master_daily_data (e.g. 'temu', 'temu2').
     * @return array{sales: float, orders: int, snapshot_date: string}|null
     */
    private function deriveTemuL60FromHistoricalL30(string $channelKey): ?array
    {
        try {
            $targetDate = now('America/Los_Angeles')->subDays(30)->toDateString();
            $earliest   = now('America/Los_Angeles')->subDays(40)->toDateString();
            $latest     = now('America/Los_Angeles')->subDays(20)->toDateString();

            $candidates = \App\Models\ChannelMasterSummary::where('channel', $channelKey)
                ->whereBetween('snapshot_date', [$earliest, $latest])
                ->orderBy('snapshot_date', 'desc')
                ->get(['snapshot_date', 'summary_data']);

            if ($candidates->isEmpty()) {
                return null;
            }

            $best = null;
            $bestDelta = PHP_INT_MAX;
            foreach ($candidates as $row) {
                $sd = is_array($row->summary_data) ? $row->summary_data : (array) $row->summary_data;
                $sales = (float) ($sd['l30_sales'] ?? 0);
                if ($sales <= 0) {
                    continue;
                }
                $delta = abs(Carbon::parse($row->snapshot_date)->diffInDays(Carbon::parse($targetDate)));
                if ($delta < $bestDelta) {
                    $best = $row;
                    $bestDelta = $delta;
                }
            }

            if (!$best) {
                return null;
            }

            $sd = is_array($best->summary_data) ? $best->summary_data : (array) $best->summary_data;
            return [
                'sales'         => (float) ($sd['l30_sales'] ?? 0),
                'orders'        => (int) ($sd['l30_orders'] ?? 0),
                'snapshot_date' => Carbon::parse($best->snapshot_date)->toDateString(),
            ];
        } catch (\Throwable $e) {
            Log::warning('deriveTemuL60FromHistoricalL30 failed: ' . $e->getMessage(), ['channel' => $channelKey]);
            return null;
        }
    }

    public function getTemuChannelData(Request $request)
    {
        $result = [];

        // L30 / L60 from the temu_orders table (Temu API order-wise data).
        $l30Start = Carbon::now()->subDays(30)->startOfDay();
        $l30End = Carbon::now()->endOfDay();
        [$l60Start, $l60End] = TemuShopifySalesService::channelMasterL60Window();
        $l30 = TemuShopifySalesService::computeMetricsFromOrders($l30Start, $l30End);
        $l60 = TemuShopifySalesService::computeMetricsFromOrders($l60Start, $l60End);

        $l30Sales = $l30['sales'];
        $l30Orders = $l30['orders'];
        $totalQuantity = $l30['qty'];
        $totalProfit = $l30['pft'];
        $totalCogs = $l30['cogs'];
        $l60Sales = $l60['sales'];
        $l60Orders = $l60['orders'];

        $gProfitPct = $l30Sales > 0 ? round(($totalProfit / $l30Sales) * 100, 2) : 0.0;
        $gRoi = $totalCogs > 0 ? round(($totalProfit / $totalCogs) * 100, 2) : 0.0;
        $gprofitL60 = $l60Sales > 0 ? round(($l60['pft'] / $l60Sales) * 100, 2) : 0.0;
        $gRoiL60 = $l60['cogs'] > 0 ? round(($l60['pft'] / $l60['cogs']) * 100, 2) : 0.0;

        $temuAdMetrics = $this->fetchAdMetricsFromTables('temu');
        $totalAdSpend = (float) ($temuAdMetrics['Total Ad Spend'] ?? $this->fetchTotalAdSpendFromTables('temu'));
        $tacosPercentage = $l30Sales > 0 ? round(($totalAdSpend / $l30Sales) * 100, 2) : 0.0;
        $adsPercentage = isset($temuAdMetrics['Ads%'])
            ? (float) $temuAdMetrics['Ads%']
            : $tacosPercentage;
        $nPft = round($gProfitPct - $tacosPercentage, 2);
        $netProfit = $totalProfit - $totalAdSpend;
        $nRoi = $totalCogs > 0 ? round(($netProfit / $totalCogs) * 100, 2) : 0.0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $channelData = ChannelMaster::where('channel', 'Temu')->first();
        $mapMissCounts = $this->getTemuLiveMapMissNMapFromDecreaseData(false);

        $result[] = [
            'Channel '   => 'Temu',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => $totalAdSpend,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'TACOS %'    => round($tacosPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Temu channel data fetched successfully (from shopify_order_items)',
            'data' => $result,
        ]);
    }

    /**
     * Temu 2 channel data (from marketplace_daily_metrics, channel = 'Temu 2').
     */
    public function getTemu2ChannelData(Request $request)
    {
        $result = [];
        $metrics = MarketplaceDailyMetric::where('channel', 'Temu 2')->latest('date')->first();

        if (!$metrics) {
            $channelData = ChannelMaster::where('channel', 'Temu 2')->first();
            $mapMissCounts = $this->getTemuLiveMapMissNMapFromDecreaseData(true);
            $result[] = [
                'Channel '   => 'Temu 2',
                'L-60 Sales' => 0,
                'L30 Sales'  => 0,
                'Growth'     => '0%',
                'L60 Orders' => 0,
                'L30 Orders' => 0,
                'Qty'        => 0,
                'Gprofit%'   => '0%',
                'gprofitL60' => '0%',
                'G Roi'      => 0,
                'G RoiL60'   => 0,
                'Total PFT'  => 0,
                'N PFT'      => '0%',
                'N ROI'      => '0%',
                'KW Spent'   => 0,
                'PT Spent'   => 0,
                'HL Spent'   => 0,
                'PMT Spent'  => 0,
                'Shopping Spent' => 0,
                'SERP Spent' => 0,
                'Total Ad Spend' => 0,
                'Ads%'       => '0%',
                'TACOS %'    => '0%',
                'type'       => $channelData->type ?? '',
                'W/Ads'      => $channelData->w_ads ?? 0,
                'NR'         => $channelData->nr ?? 0,
                'Update'     => $channelData->update ?? 0,
                'cogs'       => 0,
                'Map'        => $mapMissCounts['map'],
                'Miss'       => $mapMissCounts['miss'],
                'NMap'       => $mapMissCounts['nmap'],
                'Total Views' => $mapMissCounts['total_views'] ?? 0,
                ...$this->getChannelHealthAndReviewsStub(),
            ];
            return response()->json([
                'status' => 200,
                'message' => 'Temu 2 channel data (no metrics yet – run app:update-marketplace-daily-metrics)',
                'data' => $result,
            ]);
        }

        // L60: uploaded temu2_daily_data_l60 first, else historical snapshot.
        $l60Resolved = $this->resolveTemuL60SalesAndOrders(true);
        $l60Sales = $l60Resolved['sales'];
        $l60Orders = $l60Resolved['orders'];

        // L30 sales/orders/qty: live from tabulator (same as /temu2-tabulator badge), not stale metrics cache.
        $liveSales = $this->getTemuLiveSalesSummaryFromTabulator(true);
        $l30Sales = $liveSales ? ($liveSales['total_revenue'] ?? 0) : ($metrics->total_sales ?? 0);
        $l30Orders = $liveSales ? ($liveSales['total_orders'] ?? 0) : ($metrics->total_orders ?? 0);
        $totalQuantity = $liveSales ? ($liveSales['total_quantity'] ?? 0) : ($metrics->total_quantity ?? 0);
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? $gProfitPct;
        $nRoi = $metrics->n_roi ?? $gRoi;
        $totalAdSpend = $this->fetchTotalAdSpendFromTables('temu2');

        // Growth = ((L30 - L60) / L60) * 100.
        // Final fallback only if neither historical snapshot nor static file gave us a value.
        if ($l60Sales <= 0 && $l30Sales > 0) {
            $l60Sales  = $l30Sales;
            $l60Orders = $l30Orders;
        }
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        $gprofitL60 = 0;
        $gRoiL60 = 0;
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        $channelData = ChannelMaster::where('channel', 'Temu 2')->first();
        $mapMissCounts = $this->getTemuLiveMapMissNMapFromDecreaseData(true);

        $result[] = [
            'Channel '   => 'Temu 2',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => $totalAdSpend,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 2) . '%',
            'TACOS %'    => round($tacosPercentage, 2) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Temu 2 channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Temu
     * This counts SKUs with INV > 0 that are not listed and are not marked as NR
     */
    private function getTemuMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = \App\Models\TemuListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }


    public function getWalmartChannelData(Request $request)
    {
        $result = [];

        // Use walmart_daily_data table (same as Sales page) instead of ShipHub
        $latestDate = \App\Models\WalmartDailyData::max('order_date');

        if (!$latestDate) {
            return response()->json([
                'status' => 200,
                'message' => 'No Walmart data found in walmart_daily_data',
                'data' => [[
                    'Channel ' => 'Walmart',
                    'L-60 Sales' => 0,
                    'L30 Sales' => 0,
                    'Growth' => '0%',
                    'L60 Orders' => 0,
                    'L30 Orders' => 0,
                    'Gprofit%' => '0%',
                    'gprofitL60' => '0%',
                    'G Roi' => 0,
                    'G RoiL60' => 0,
                    'N PFT' => '0%',
                ]]
            ]);
        }

        $latestDateCarbon = \Carbon\Carbon::parse($latestDate);
        // Get 32 days from latest order date
        $l30EndDate = $latestDateCarbon->endOfDay(); // Latest date in DB
        $l30StartDate = $latestDateCarbon->copy()->subDays(30)->startOfDay(); // 32 days before latest
        // L60: 32-day period ending the day before L30 starts
        $l60EndDate = $l30StartDate->copy()->subDay()->endOfDay(); // Day before L30 starts
        $l60StartDate = $l60EndDate->copy()->subDays(30)->startOfDay(); // 31 days before that

        // Get Walmart marketing percentage (default 80%)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Walmart')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 80;
        $margin = $percentage / 100; // convert % to fraction

        // Get L30 order items from walmart_daily_data (same as Sales page)
        $l30Orders = \App\Models\WalmartDailyData::where('period', 'l30')
            ->whereBetween('order_date', [$l30StartDate, $l30EndDate])
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->select([
                DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                'sku',
                'quantity',
                'unit_price as price',
            ])
            ->get();

        // Get L60 order items from walmart_daily_data
        $l60Orders = \App\Models\WalmartDailyData::where('period', 'l30')
            ->whereBetween('order_date', [$l60StartDate, $l60EndDate])
            ->where('fulfillment_option', 'DELIVERY')
            ->where('status', '!=', 'Cancelled')
            ->select([
                DB::raw("COALESCE(customer_order_id, purchase_order_id, CONCAT('WM-', id)) as order_id"),
                'sku',
                'quantity',
                'unit_price as price',
            ])
            ->get();

        // Get unique SKUs
        $skus = $l30Orders->pluck('sku')->merge($l60Orders->pluck('sku'))->filter()->unique()->values()->toArray();

        // Load product masters (lp, ship) keyed by SKU (UPPERCASE)
        $productMasters = ProductMaster::whereIn('sku', $skus)
            ->get()
            ->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

        // Process L30 data
        $l30Sales = 0;
        $l30OrderCount = 0;
        $l30TotalQuantity = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $l30OrderIds = [];

        foreach ($l30Orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = floatval($order->quantity ?? 1);
            $unitPrice = floatval($order->price ?? 0);
            
            $saleAmount = $unitPrice * $quantity;
            $l30TotalQuantity += $quantity;

            // Get ProductMaster data
            $pm = $productMasters->get($sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        if (strtolower($key) === 'lp') $lp = floatval($value);
                        elseif (strtolower($key) === 'ship') $ship = floatval($value);
                        elseif (strtolower($key) === 'weight_act') $weightAct = floatval($value);
                    }
                }
                
                if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
                if ($ship === 0 && isset($pm->ship)) $ship = floatval($pm->ship);
            }

            // Calculate ship cost
            $tWeight = $weightAct * $quantity;
            $shipCost = ($quantity == 1) ? $ship : (($quantity > 1 && $tWeight < 20) ? ($ship / $quantity) : $ship);

            // Calculate profit
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $profit = $pftEach * $quantity;
            $cogs = $lp * $quantity;

            $l30Sales += $saleAmount;
            $totalProfit += $profit;
            $totalCogs += $cogs;
            $l30OrderIds[$order->order_id] = true;
        }
        $l30OrderCount = count($l30OrderIds);

        // Process L60 data
        $l60Sales = 0;
        $l60OrderCount = 0;
        $totalProfitL60 = 0;
        $totalCogsL60 = 0;
        $l60OrderIds = [];

        foreach ($l60Orders as $order) {
            $sku = strtoupper(trim($order->sku ?? ''));
            $quantity = floatval($order->quantity ?? 1);
            $unitPrice = floatval($order->price ?? 0);
            
            $saleAmount = $unitPrice * $quantity;

            // Get ProductMaster data
            $pm = $productMasters->get($sku);
            $lp = 0;
            $ship = 0;
            $weightAct = 0;
            
            if ($pm) {
                $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        if (strtolower($key) === 'lp') $lp = floatval($value);
                        elseif (strtolower($key) === 'ship') $ship = floatval($value);
                        elseif (strtolower($key) === 'weight_act') $weightAct = floatval($value);
                    }
                }
                
                if ($lp === 0 && isset($pm->lp)) $lp = floatval($pm->lp);
                if ($ship === 0 && isset($pm->ship)) $ship = floatval($pm->ship);
            }

            // Calculate ship cost
            $tWeight = $weightAct * $quantity;
            $shipCost = ($quantity == 1) ? $ship : (($quantity > 1 && $tWeight < 20) ? ($ship / $quantity) : $ship);

            // Calculate profit
            $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
            $profit = $pftEach * $quantity;
            $cogs = $lp * $quantity;

            $l60Sales += $saleAmount;
            $totalProfitL60 += $profit;
            $totalCogsL60 += $cogs;
            $l60OrderIds[$order->order_id] = true;
        }
        $l60OrderCount = count($l60OrderIds);

        // Total Ad Spend: fetch directly from walmart_campaign_reports table
        $walmartSpent = $this->fetchTotalAdSpendFromTables('walmart');
        
        // Calculate percentages
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;
        
        // Calculate TACOS %: (Walmart Spent / Total Sales) * 100
        $tacosPercentage = $l30Sales > 0 ? ($walmartSpent / $l30Sales) * 100 : 0;
        
        // Calculate N PFT: GPFT % - TACOS %
        $nPft = $gProfitPct - $tacosPercentage;
        
        // Calculate N ROI: ROI % - TACOS %
        $nRoi = $gRoi - $tacosPercentage;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Walmart')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('walmart');

        $result[] = [
            'Channel '   => 'Walmart',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60OrderCount,
            'L30 Orders' => $l30OrderCount,
            'Qty'        => intval($l30TotalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'Walmart Spent' => round($walmartSpent, 2),
            'KW Spent'   => round($walmartSpent, 2), // Walmart KW ad spend
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => round($walmartSpent, 2),
            'TACOS %'    => round($tacosPercentage, 2) . '%',
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];
        
        return response()->json([
            'status' => 200,
            'message' => 'Walmart channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getWalmartMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = WalmartListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Walmart uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getTiendamiaChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated from MiraklDailyData)
        $metrics = MarketplaceDailyMetric::where('channel', 'Tiendamia')->latest('date')->first();

        // Get L60 data from TiendamiaProduct for comparison
        // L60 Sales = previous 30-day period (days 31-60) from mirakl_daily_data
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $sixtyDaysAgo  = Carbon::now()->subDays(60);
        $l60Agg = \App\Models\MiraklDailyData::where('channel_name', 'Tiendamia')
            ->where('status', '!=', 'CLOSED')
            ->whereBetween('order_created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(unit_price * quantity), 0) as total_sales')
            ->first();
        $l60Orders = (int) ($l60Agg->order_count ?? 0);
        $l60Sales  = (float) ($l60Agg->total_sales ?? 0);

        // Use MarketplaceDailyMetric data
        $l30Sales = $metrics->total_sales ?? $metrics->l30_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;

        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // L60 profit percentage (calculate from L60 data if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = same as Gprofit% for Tiendamia (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Tiendamia (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiendamia')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('tiendamia');

        $result[] = [
            'Channel '   => 'Tiendamia',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Tiendamia channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Tiendamia
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getTiendamiaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = TiendamiaListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getBestbuyUsaChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Best Buy USA')->latest('date')->first();

        // L60 Sales = previous 30-day period (days 31-60) from mirakl_daily_data,
        // matching Amazon/eBay/Walmart convention. Same filters as the L30 metrics calc.
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $sixtyDaysAgo  = Carbon::now()->subDays(60);
        $l60Agg = \App\Models\MiraklDailyData::where('channel_name', 'Best Buy USA')
            ->where('status', '!=', 'CLOSED')
            ->whereBetween('order_created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(unit_price * quantity), 0) as total_sales')
            ->first();
        $l60Orders = (int) ($l60Agg->order_count ?? 0);
        $l60Sales  = (float) ($l60Agg->total_sales ?? 0);

        // Use MarketplaceDailyMetric data
        $l30Sales = $metrics->total_sales ?? $metrics->l30_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;

        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // L60 profit percentage (calculate from L60 data if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = same as Gprofit% for Best Buy (no ads)
        $nPft = $gProfitPct;

        // N ROI = same as G ROI for Best Buy (no ads)
        $nRoi = $metrics->n_roi ?? $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'BestBuy USA')->first();

        // Live counts from bestbuy-pricing data (same as MISSING / N Map badges + Map column tolerance)
        $mapMissCounts = $this->getBestbuyLiveMapMissNMapFromPricingData($request);

        $result[] = [
            'Channel '   => 'BestBuy USA',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Bestbuy USA channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Newegg channel data for /all-marketplace-master.
     *
     * Computed directly from newegg_orders / newegg_order_items (populated by
     * `php artisan newegg:orders --save`). Margin comes from marketplace_percentages
     * (Neweggb2c): margin = percentage - ad_updates. Voided orders are excluded.
     */
    public function getNeweggChannelData(Request $request)
    {
        $result = [];

        $mp = MarketplacePercentage::where('marketplace', 'Neweggb2c')->first();
        $percentage = $mp ? (float) $mp->percentage : 85;
        $adUpdates  = $mp ? (float) $mp->ad_updates : 0;
        $margin     = $percentage - $adUpdates;
        $factor     = $margin > 0 ? $margin / 100 : 0.85;

        // Product master costs keyed by normalized SKU (NBSP / space tolerant).
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return ShopifySku::normalizeSkuForShopifyLookup($item->sku);
        });

        $now = Carbon::now();
        $l30 = $this->computeNeweggWindow($now->copy()->subDays(30), $now, $productMasters, $factor);
        $l60 = $this->computeNeweggWindow($now->copy()->subDays(60), $now->copy()->subDays(30), $productMasters, $factor);
        $l7  = $this->computeNeweggWindow($now->copy()->subDays(7), $now, $productMasters, $factor);
        $y   = $this->computeNeweggWindow($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), $productMasters, $factor);

        $growth     = $l60['sales'] > 0 ? (($l30['sales'] - $l60['sales']) / $l60['sales']) * 100 : 0;
        $gProfitPct = $l30['sales'] > 0 ? ($l30['profit'] / $l30['sales']) * 100 : 0;
        $gRoi       = $l30['cogs'] > 0 ? ($l30['profit'] / $l30['cogs']) * 100 : 0;
        $gprofitL60 = $l60['sales'] > 0 ? ($l60['profit'] / $l60['sales']) * 100 : 0;
        $gRoiL60    = $l60['cogs'] > 0 ? ($l60['profit'] / $l60['cogs']) * 100 : 0;

        $channelData = ChannelMaster::where('channel', 'Newegg')->first();

        $result[] = [
            'Channel '   => 'Newegg',
            'L-60 Sales' => round($l60['sales']),
            'L30 Sales'  => round($l30['sales']),
            'L7 Sales'   => round($l7['sales']),
            'Y Sales'    => round($y['sales']),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60['orders'],
            'L30 Orders' => $l30['orders'],
            'Qty'        => intval($l30['qty']),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($l30['profit'], 2),
            'N PFT'      => round($gProfitPct, 2) . '%',
            'N ROI'      => round($gRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? 'B2C',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($l30['cogs'], 2),
            'sheet_link' => $channelData->sheet_link ?? null,
            'missing_link' => $channelData->missing_link ?? null,
            'Map' => 0,
            'Miss' => 0,
            'NMap' => 0,
            'Total Views' => 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Newegg channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Aggregate Newegg sales/profit for a date window from newegg_orders + items.
     * Excludes voided orders (order_status 4).
     *
     * @return array{sales:float,orders:int,qty:float,profit:float,cogs:float}
     */
    private function computeNeweggWindow($from, $to, $productMasters, float $factor): array
    {
        $orders = \App\Models\NeweggOrder::with('items')
            ->whereBetween('order_date', [$from, $to])
            ->where(function ($q) {
                $q->whereNull('order_status')->orWhere('order_status', '!=', 4);
            })
            ->get();

        $sales = 0.0; $qty = 0.0; $profit = 0.0; $cogs = 0.0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $quantity  = (float) ($item->ordered_qty ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                [$lp, $ship] = $this->neweggItemCosts($item->seller_part_number, $productMasters);

                $sales  += $unitPrice * $quantity;
                $qty    += $quantity;
                $profit += (($unitPrice * $factor) - $lp - $ship) * $quantity;
                $cogs   += $lp * $quantity;
            }
        }

        return [
            'sales'  => $sales,
            'orders' => $orders->count(),
            'qty'    => $qty,
            'profit' => $profit,
            'cogs'   => $cogs,
        ];
    }

    /**
     * LP + Ship for a Newegg SKU from ProductMaster.
     *
     * @return array{0:float,1:float} [lp, ship]
     */
    private function neweggItemCosts(?string $sku, $productMasters): array
    {
        if (!$sku) {
            return [0.0, 0.0];
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        $pm = $productMasters[$norm] ?? null;
        if (!$pm) {
            return [0.0, 0.0];
        }

        $values = is_array($pm->Values)
            ? $pm->Values
            : (is_string($pm->Values) ? (json_decode($pm->Values, true) ?: []) : []);

        $lp   = isset($values['lp']) ? (float) $values['lp'] : (float) ($pm->lp ?? 0);
        $ship = isset($values['ship']) ? (float) $values['ship'] : (float) ($pm->ship ?? 0);

        return [$lp, $ship];
    }

    /**
     * Newegg Y Sales for /all-marketplace-master.
     *
     * Same clock as Amazon: revenue (unit_price × ordered_qty) for the Pacific calendar day
     * before the latest newegg_orders.order_date. Voided orders (order_status 4) are excluded.
     */
    private function computeNeweggYSalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('newegg_orders')
            ->whereNotNull('order_date')
            ->where(function ($q) {
                $q->whereNull('order_status')->orWhere('order_status', '!=', 4);
            })
            ->max('order_date');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        $yStartPacific = $latestPacific->copy()->subDay()->startOfDay();
        $yEndPacific = $latestPacific->copy()->subDay()->endOfDay();

        return $this->sumNeweggRevenueBetween($yStartPacific, $yEndPacific);
    }

    /**
     * Newegg L7 Sales for /all-marketplace-master.
     *
     * Seven-day window ending on the Y-Sales "yesterday" (day before latest order_date),
     * revenue = unit_price × ordered_qty, voided orders excluded.
     */
    private function computeNeweggL7SalesLikeAmazon(): ?float
    {
        $latestRaw = DB::table('newegg_orders')
            ->whereNotNull('order_date')
            ->where(function ($q) {
                $q->whereNull('order_status')->orWhere('order_status', '!=', 4);
            })
            ->max('order_date');

        if (!$latestRaw) {
            return null;
        }

        $latestPacific = Carbon::parse($latestRaw)->timezone('America/Los_Angeles');
        [$l7StartPacific, $l7EndPacific] = $this->pacificL7WindowEndingYesterday($latestPacific);

        return $this->sumNeweggRevenueBetween($l7StartPacific, $l7EndPacific);
    }

    /**
     * Sum Newegg order-item revenue (unit_price × ordered_qty) for a date window.
     * Voided orders (order_status 4) are excluded.
     */
    private function sumNeweggRevenueBetween($from, $to): float
    {
        $sum = (float) DB::table('newegg_orders as o')
            ->join('newegg_order_items as i', 'o.order_number', '=', 'i.order_number')
            ->where('o.order_date', '>=', $from)
            ->where('o.order_date', '<=', $to)
            ->where(function ($q) {
                $q->whereNull('o.order_status')->orWhere('o.order_status', '!=', 4);
            })
            ->selectRaw('COALESCE(SUM(i.unit_price * i.ordered_qty), 0) as revenue')
            ->value('revenue');

        return round($sum, 2);
    }

    /**
     * Calculate Missing Listing count for BestBuy USA
     * This counts SKUs with INV > 0 that are not listed and are not marked as NR
     */
    private function getBestbuyUsaMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = \App\Models\BestbuyUSAListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if nr_req is not NR AND listed is not Listed
            if ($nrReq !== 'NR' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    public function getPlsChannelData(Request $request)
    {
        $result = [];

        // Use actual sales data from pls_sales table (last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        $sixtyDaysAgo = now()->subDays(60);

        // L30 Sales: Last 30 days actual transactions
        $l30SalesData = \App\Models\PlsSale::where('order_date', '>=', $thirtyDaysAgo)
            ->selectRaw('COUNT(DISTINCT order_number) as orders, SUM(quantity) as qty, SUM(total_amount) as sales')
            ->first();
        
        $l30Orders = (int) ($l30SalesData->orders ?? 0);
        $totalQuantity = (int) ($l30SalesData->qty ?? 0);
        $l30Sales = (float) ($l30SalesData->sales ?? 0);

        // L60 Sales: 60-30 days ago actual transactions
        $l60SalesData = \App\Models\PlsSale::where('order_date', '>=', $sixtyDaysAgo)
            ->where('order_date', '<', $thirtyDaysAgo)
            ->selectRaw('COUNT(DISTINCT order_number) as orders, SUM(total_amount) as sales')
            ->first();
        
        $l60Orders = (int) ($l60SalesData->orders ?? 0);
        $l60Sales = (float) ($l60SalesData->sales ?? 0);

        // Y Sales: Yesterday's actual transactions
        $yesterday = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();
        $ySales = \App\Models\PlsSale::where('order_date', '>=', $yesterday)
            ->where('order_date', '<=', $yesterdayEnd)
            ->sum('total_amount');

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get PLS marketplace percentage from marketplace_percentages table
        $percentage = MarketplacePercentage::where('marketplace', 'LIKE', '%PLS%')->value('percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by normalized SKU (NBSP / space tolerant)
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return ShopifySku::normalizeSkuForShopifyLookup($item->sku);
        });

        // Get PLS products with current prices and L30 sales volume
        $plsProducts = \App\Models\PLSProduct::all()->keyBy(function ($item) {
            return ShopifySku::normalizeSkuForShopifyLookup($item->sku);
        });

        // Calculate weighted GPFT and ROI using CURRENT PRICES (same as Pricing page)
        $totalSales   = 0;
        $totalProfit  = 0;
        $totalCogs    = 0;

        foreach ($plsProducts as $plsProduct) {
            $skuNorm   = ShopifySku::normalizeSkuForShopifyLookup($plsProduct->sku);
            $price     = (float) $plsProduct->price;
            $plsL30    = (int) $plsProduct->p_l30;

            if ($plsL30 <= 0 || $price <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$skuNorm])) {
                $pm = $productMasters[$skuNorm];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Calculate weighted by sales volume (same as Pricing page formula)
            $sales = $price * $plsL30;
            $profit = (($price * $percentage) - $lp - $ship) * $plsL30;
            $cogs = $lp * $plsL30;

            $totalSales += $sales;
            $totalProfit += $profit;
            $totalCogs += $cogs;
        }

        // Calculate profit percentages (weighted average)
        $gProfitPct = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;
        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $nPft = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'PLS')->first();

        // Get Map and Miss counts from PLS pricing page data (live calculation - same as pls-pricing badges)
        $mapMissCounts = $this->getPlsLiveMapMissNMapFromPricingData();

        $result[] = [
            'Channel '   => 'PLS',
            'L-60 Sales' => round($l60Sales),
            'L30 Sales'  => round($l30Sales),
            'Y Sales'    => round($ySales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60'   => '0%',  // L60 profit not calculated from actual sales yet
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'      => 0,  // L60 ROI not calculated from actual sales yet
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($gRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'sheet_link' => $channelData->sheet_link ?? null,
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('PLS'),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'PLS channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for PLS
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getPlsMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusByNorm = [];
        foreach (PlsListingStatus::whereIn('sku', $skus)->get() as $row) {
            $key = ShopifySku::normalizeSkuForShopifyLookup($row->sku);
            if ($key !== '' && ! isset($statusByNorm[$key])) {
                $statusByNorm[$key] = $row;
            }
        }

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $skuNorm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData->get($sku)?->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) {
                continue;
            }

            $statusRow = $statusByNorm[$skuNorm] ?? null;
            $status = $statusRow?->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }


    public function getWayfairChannelData(Request $request)
    {
        $result = [];

        // Get Wayfair data from wayfair_daily_data table (period = 'l30')
        $wayfairData = WayfairDailyData::where('period', 'l30')
            ->where('sku', 'not like', '%Parent%')
            ->get();

        // Calculate L30 metrics
        $l30Orders = $wayfairData->count(); // Total number of orders
        $totalQuantity = $wayfairData->sum('quantity');
        $l30Sales = $wayfairData->sum(function($item) {
            return $item->unit_price * $item->quantity;
        });

        // L60 Sales = previous 30-day period (days 31-60) from wayfair_daily_data,
        // matching Amazon/eBay/Walmart convention. Filter by po_date so we only count days 31-60.
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $sixtyDaysAgo  = Carbon::now()->subDays(60);
        $l60Agg = WayfairDailyData::where('sku', 'not like', '%Parent%')
            ->whereBetween('po_date', [$sixtyDaysAgo->toDateString(), $thirtyDaysAgo->toDateString()])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(unit_price * quantity), 0) as total_sales')
            ->first();
        $l60Orders = (int) ($l60Agg->order_count ?? 0);
        $l60Sales  = (float) ($l60Agg->total_sales ?? 0);

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get Wayfair marketplace percentage from marketplace_percentages table
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Wayfair')->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $percentageFraction = $percentage / 100; // convert % to fraction

        // Load product masters (lp only, no ship) keyed by SKU
        $skus = $wayfairData->pluck('sku')->unique()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit (WITHOUT ship cost per new Wayfair formula)
        $totalProfit = 0;
        $totalProfitL60 = 0;
        $totalCogs = 0;
        $totalCogsL60 = 0;

        foreach ($wayfairData as $order) {
            $sku = strtoupper($order->sku);
            $unitPrice = (float) $order->unit_price;
            $quantity = (int) $order->quantity;

            if ($quantity <= 0) {
                continue;
            }

            $lp = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                // Get LP from Values or direct property
                foreach ($values as $k => $v) {
                    if (strtolower($k) === "lp") {
                        $lp = floatval($v);
                        break;
                    }
                }
                if ($lp === 0 && isset($pm->lp)) {
                    $lp = floatval($pm->lp);
                }
            }

            // Wayfair Profit Formula: (unit_price * percentage) - lp (NO ship cost)
            $profitPerUnit = ($unitPrice * $percentageFraction) - $lp;
            $profitTotal = $profitPerUnit * $quantity;

            $totalProfit += $profitTotal;
            $totalCogs += ($quantity * $lp);
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // N ROI = same as G ROI for Wayfair (no ads)
        $nRoi = $gRoi;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Wayfair')->first();

        // Get Map and Miss counts from wayfair-pricing (live — same as pricing page badges)
        $mapMissCounts = $this->getWayfairLiveMapMissNMapFromPricingData();

        $result[] = [
            'Channel '   => 'Wayfair',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'sheet_link' => $channelData->sheet_link ?? null,
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('Wayfair'),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getWayfairMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = WayfairListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            // Wayfair uses rl_nrl instead of nr_req
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    /**
     * Aggregate Faire sales/profit for a date window straight from Shopify
     * (shopify_raw_orders). Same identification logic as the faire-tabulator
     * page so the two stay in sync.
     *
     * @return array{sales:float, orders:int, qty:int, pft:float, cogs:float}
     */
    private function computeFaireMetricsFromShopify(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $rows = DB::table('shopify_raw_orders')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('source_name', 'faire')
                  ->orWhere('source_name', 'LIKE', '%faire%')
                  ->orWhere('tags', 'LIKE', '%Faire%');
            })
            ->get(['order_number', 'sku', 'quantity', 'price']);

        if ($rows->isEmpty()) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $skus = $rows->pluck('sku')->filter()->unique()->values()->toArray();
        $productMasters = !empty($skus)
            ? ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku')
            : collect();

        $totalSales = 0.0;
        $totalQty   = 0;
        $totalPft   = 0.0;
        $totalCogs  = 0.0;
        $orderSet   = [];

        foreach ($rows as $r) {
            $sku      = $r->sku;
            $price    = (float) ($r->price ?? 0);
            $quantity = (int)   ($r->quantity ?? 0);
            if ($quantity <= 0) continue;

            $lp = 0.0;
            if ($sku !== null && $sku !== '' && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            $totalSales += $price * $quantity;
            $totalQty   += $quantity;
            $totalCogs  += $lp * $quantity;
            // Same profit formula as the tabulator: Faire retains 25% commission.
            $totalPft   += (($price * 0.75) - $lp) * $quantity;
            if (!empty($r->order_number)) {
                $orderSet[$r->order_number] = true;
            }
        }

        return [
            'sales'  => round($totalSales, 2),
            'orders' => count($orderSet),
            'qty'    => $totalQty,
            'pft'    => round($totalPft, 2),
            'cogs'   => round($totalCogs, 2),
        ];
    }

    public function getFaireChannelData(Request $request)
    {
        $result = [];

        // L30 and L60 are computed directly from shopify_raw_orders (Faire source) —
        // same data source the /faire-tabulator page uses — so the two pages match.
        // Previously this read from marketplace_daily_metrics which in turn was sourced
        // from faire_daily_data (manual Excel uploads), drifting out of sync.
        $pst       = 'America/Los_Angeles';
        $todayPst  = Carbon::now($pst);
        $l30Start  = $todayPst->copy()->subDays(29)->startOfDay();
        $l30End    = $todayPst->copy()->endOfDay();
        $l60Start  = $todayPst->copy()->subDays(59)->startOfDay();
        $l60End    = $todayPst->copy()->subDays(30)->endOfDay();

        $l30 = $this->computeFaireMetricsFromShopify($l30Start, $l30End);
        $l60 = $this->computeFaireMetricsFromShopify($l60Start, $l60End);

        $l30Sales      = $l30['sales'];
        $l30Orders     = $l30['orders'];
        $totalQuantity = $l30['qty'];
        $totalProfit   = $l30['pft'];
        $totalCogs     = $l30['cogs'];

        $l60Sales  = $l60['sales'];
        $l60Orders = $l60['orders'];

        $gProfitPct = $l30Sales > 0 ? round(($totalProfit / $l30Sales) * 100, 2) : 0.0;
        $gRoi       = $totalCogs > 0 ? round(($totalProfit / $totalCogs) * 100, 2) : 0.0;
        // Faire has no ad spend in this pipeline → N PFT% = G PFT%, N ROI = G ROI.
        $nPftPct = $gProfitPct;
        $nRoi    = $gRoi;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // L60 profit % derived the same way over the L60 totals for consistency.
        $gprofitL60 = $l60Sales > 0 ? round(($l60['pft'] / $l60Sales) * 100, 2) : 0.0;
        $gRoiL60    = $l60['cogs'] > 0 ? round(($l60['pft'] / $l60['cogs']) * 100, 2) : 0.0;

        $channelData = ChannelMaster::where('channel', 'Faire')->first();
        $mapMissCounts = $this->getFaireLiveMapMissNMapFromPricingData($request);

        $result[] = [
            'Channel '   => 'Faire',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => $totalQuantity,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPftPct, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'Ads%'       => '0%',
            'TACOS %'    => '0%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('Faire'),
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Faire channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Aggregate Purchasing Power sales/profit for a date window straight from Shopify
     * (apicentral.shopify_order_items). Identification mirrors the shopify-orders page
     * so the all-marketplace-master row stays in sync with the Shopify dashboard.
     *
     * Profit per line = (price × pct) − LP, where pct comes from
     * marketplace_percentages.marketplace = 'Purchase' (default 65%).
     * Note: Ship is intentionally excluded from PP profit (matches /purchasing-power-pricing).
     *
     * @return array{sales:float, orders:int, qty:int, pft:float, cogs:float}
     */
    private function computePurchasingPowerMetricsFromShopify(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $rows = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('source_name', 'LIKE', '%purchasing power%')
                  ->orWhere('source_name', 'LIKE', '%purchasingpower%')
                  ->orWhere('tags', 'LIKE', '%Purchasing Power%')
                  ->orWhere('tags', 'LIKE', '%PurchasingPower%');
            })
            ->get(['order_number', 'sku', 'quantity', 'price']);

        if ($rows->isEmpty()) {
            return ['sales' => 0.0, 'orders' => 0, 'qty' => 0, 'pft' => 0.0, 'cogs' => 0.0];
        }

        $marketplaceData = MarketplacePercentage::where('marketplace', 'Purchase')->first();
        $pct = (($marketplaceData ? (float) ($marketplaceData->percentage ?? 65) : 65) / 100);

        $skus = $rows->pluck('sku')->filter()->unique()->values()->toArray();
        $productMasters = !empty($skus)
            ? ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku')
            : collect();

        $totalSales = 0.0;
        $totalQty   = 0;
        $totalPft   = 0.0;
        $totalCogs  = 0.0;
        $orderSet   = [];

        foreach ($rows as $r) {
            $sku      = $r->sku;
            $price    = (float) ($r->price ?? 0);
            $quantity = (int)   ($r->quantity ?? 0);
            if ($quantity <= 0) continue;

            $lp = 0.0;
            if ($sku !== null && $sku !== '' && isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (is_array($values)) {
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp') {
                            $lp = (float) $v;
                            break;
                        }
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }
            }

            $totalSales += $price * $quantity;
            $totalQty   += $quantity;
            $totalCogs  += $lp * $quantity;
            // Ship intentionally excluded to match /purchasing-power-pricing.
            $totalPft   += (($price * $pct) - $lp) * $quantity;
            if (!empty($r->order_number)) {
                $orderSet[$r->order_number] = true;
            }
        }

        return [
            'sales'  => round($totalSales, 2),
            'orders' => count($orderSet),
            'qty'    => $totalQty,
            'pft'    => round($totalPft, 2),
            'cogs'   => round($totalCogs, 2),
        ];
    }

    public function getPurchasingPowerChannelData(Request $request)
    {
        $result = [];

        // L30 and L60 are computed directly from shopify_order_items (Purchasing Power source) —
        // same data source the shopify-orders page uses — so the two pages match. Previously this
        // read from marketplace_daily_metrics which was sourced from purchasing_power_sales
        // (manual Excel uploads), drifting out of sync with live Shopify orders.
        $pst       = 'America/Los_Angeles';
        $todayPst  = Carbon::now($pst);
        $l30Start  = $todayPst->copy()->subDays(29)->startOfDay();
        $l30End    = $todayPst->copy()->endOfDay();
        $l60Start  = $todayPst->copy()->subDays(59)->startOfDay();
        $l60End    = $todayPst->copy()->subDays(30)->endOfDay();

        $l30 = $this->computePurchasingPowerMetricsFromShopify($l30Start, $l30End);
        $l60 = $this->computePurchasingPowerMetricsFromShopify($l60Start, $l60End);

        $l30Sales      = $l30['sales'];
        $l30Orders     = $l30['orders'];
        $totalQuantity = $l30['qty'];
        $totalProfit   = $l30['pft'];
        $totalCogs     = $l30['cogs'];

        $l60Sales  = $l60['sales'];
        $l60Orders = $l60['orders'];

        $gProfitPct = $l30Sales > 0 ? round(($totalProfit / $l30Sales) * 100, 2) : 0.0;
        $gRoi       = $totalCogs > 0 ? round(($totalProfit / $totalCogs) * 100, 2) : 0.0;
        // Purchasing Power has no ad spend in this pipeline → N PFT% = G PFT%, N ROI = G ROI.
        $nPftPct = $gProfitPct;
        $nRoi    = $gRoi;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $gprofitL60 = $l60Sales > 0 ? round(($l60['pft'] / $l60Sales) * 100, 2) : 0.0;
        $gRoiL60    = $l60['cogs'] > 0 ? round(($l60['pft'] / $l60['cogs']) * 100, 2) : 0.0;

        $channelData = ChannelMaster::where('channel', 'Purchasing Power')->first();
        $mapMissCounts = $this->getPurchasingPowerLiveMapMissNMapFromPricingData($request);

        $result[] = [
            'Channel '   => 'Purchasing Power',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => $totalQuantity,
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPftPct, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'Ads%'       => '0%',
            'TACOS %'    => '0%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Purchasing Power channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Load product_master rows keyed by UPPER(TRIM(sku)) for the given normalized keys (chunked for large lists).
     *
     * @param  list<string>  $normalizedSkuKeys
     * @return array<string, ProductMaster>
     */
    private function productMastersByNormalizedSkus(array $normalizedSkuKeys): array
    {
        $normalizedSkuKeys = array_values(array_unique(array_filter(array_map(
            static fn ($k) => strtoupper(trim((string) $k)),
            $normalizedSkuKeys
        ))));
        if ($normalizedSkuKeys === []) {
            return [];
        }

        $map = [];
        foreach (array_chunk($normalizedSkuKeys, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $batch = ProductMaster::query()
                ->whereNull('deleted_at')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $chunk)
                ->get();
            foreach ($batch as $pm) {
                $map[strtoupper(trim((string) $pm->sku))] = $pm;
            }
        }

        return $map;
    }

    /**
     * Approximate L30 revenue from shein_sheet_data when daily table is empty (SKU × l30 × price).
     */
    private function aggregateSheinSheetSalesFallback(): ?array
    {
        if (! Schema::hasTable((new SheinSheetData)->getTable())) {
            return null;
        }

        $hasShopifyPrice = Schema::hasColumn((new SheinSheetData)->getTable(), 'shopify_price');
        $priceExpr = $hasShopifyPrice
            ? 'COALESCE(NULLIF(price, 0), NULLIF(shopify_price, 0), 0)'
            : 'COALESCE(NULLIF(price, 0), 0)';

        $totalSales = (float) (SheinSheetData::query()
            ->where('sku', 'not like', '%Parent%')
            ->selectRaw("SUM(COALESCE(l30, 0) * ({$priceExpr})) as t")
            ->value('t') ?? 0);

        if ($totalSales <= 0) {
            return null;
        }

        $qtySum = (int) (SheinSheetData::query()
            ->where('sku', 'not like', '%Parent%')
            ->sum('l30') ?? 0);

        return [
            'total_orders' => 0,
            'total_quantity' => $qtySum,
            'total_sales' => $totalSales,
            'total_cogs' => 0.0,
            'total_pft' => 0.0,
            'pft_percentage' => 0.0,
            'roi_percentage' => 0.0,
            'avg_price' => $qtySum > 0 ? $totalSales / $qtySum : 0.0,
        ];
    }

    /**
     * Shein sales / orders / PFT — same rules as Shein Daily Data tabulator (shein_tabulator_view updateSummary)
     * and /shein/daily-data (marketplace_margin_decimal from marketplace_percentages).
     * Uses a cursor + scoped product_master load so large uploads do not OOM (which previously yielded an empty row).
     */
    private function aggregateSheinDailyDataLikeTabulator(): ?array
    {
        if (! SheinDailyData::query()->exists()) {
            return null;
        }

        $pctRow = MarketplacePercentage::where('marketplace', 'Shein')->first();
        $marginPct = 100.0;
        if ($pctRow && $pctRow->percentage !== null && $pctRow->percentage !== '') {
            $marginPct = (float) $pctRow->percentage;
        }
        $margin = $marginPct / 100.0;

        $skuKeys = SheinDailyData::query()
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->selectRaw('UPPER(TRIM(seller_sku)) as k')
            ->groupBy(DB::raw('UPPER(TRIM(seller_sku))'))
            ->pluck('k')
            ->filter()
            ->values()
            ->all();

        $productMasters = $this->productMastersByNormalizedSkus($skuKeys);

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;

        foreach (SheinDailyData::query()->orderBy('id')->cursor() as $row) {
            $orderNum = trim((string) ($row->order_number ?? ''));
            $sellerSku = trim((string) ($row->seller_sku ?? ''));
            // Tabulator requires order_number; some exports only populate seller_sku — include those for master totals.
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }

            $orderStatus = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($orderStatus, 'refund')
                || str_contains($orderStatus, 'returned')
                || str_contains($orderStatus, 'cancelled')) {
                continue;
            }

            $quantity = (int) ($row->quantity ?? 0);
            $productPrice = (float) ($row->product_price ?? 0);
            $estRev = (float) ($row->estimated_merchandise_revenue ?? 0);
            $lineRevenue = $productPrice > 0 ? $productPrice * $quantity : ($estRev > 0 ? $estRev : 0.0);
            $unitPriceForPft = $productPrice > 0 ? $productPrice : ($quantity > 0 && $estRev > 0 ? $estRev / $quantity : ($estRev > 0 ? $estRev : 0.0));

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $lineRevenue;

            if ($quantity > 0 && $unitPriceForPft > 0) {
                $totalWeightedPrice += $unitPriceForPft * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $skuKey = strtoupper(trim((string) ($row->seller_sku ?? '')));
            $lp = 0.0;
            $ship = 0.0;
            if ($skuKey !== '' && isset($productMasters[$skuKey])) {
                $pm = $productMasters[$skuKey];
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }

                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = (float) $pm->ship;
                }
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            $pft = ($unitPriceForPft * $margin - $lp - $ship) * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0.0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
        ];
    }

    /**
     * Shein L60 sales / orders — same logic as L30 but from shein_daily_data_l60 table
     */
    private function aggregateSheinDailyDataL60LikeTabulator(): ?array
    {
        if (! Schema::hasTable('shein_daily_data_l60')) {
            return null;
        }

        if (! \App\Models\SheinDailyDataL60::query()->exists()) {
            return null;
        }

        $pctRow = MarketplacePercentage::where('marketplace', 'Shein')->first();
        $marginPct = 100.0;
        if ($pctRow && $pctRow->percentage !== null && $pctRow->percentage !== '') {
            $marginPct = (float) $pctRow->percentage;
        }
        $margin = $marginPct / 100.0;

        $skuKeys = \App\Models\SheinDailyDataL60::query()
            ->whereNotNull('seller_sku')
            ->where('seller_sku', '!=', '')
            ->selectRaw('UPPER(TRIM(seller_sku)) as k')
            ->groupBy(DB::raw('UPPER(TRIM(seller_sku))'))
            ->pluck('k')
            ->filter()
            ->values()
            ->all();

        $productMasters = $this->productMastersByNormalizedSkus($skuKeys);

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;

        foreach (\App\Models\SheinDailyDataL60::query()->orderBy('id')->cursor() as $row) {
            $orderNum = trim((string) ($row->order_number ?? ''));
            $sellerSku = trim((string) ($row->seller_sku ?? ''));
            if ($orderNum === '' && $sellerSku === '') {
                continue;
            }

            $orderStatus = strtolower((string) ($row->order_status ?? ''));
            if (str_contains($orderStatus, 'refund')
                || str_contains($orderStatus, 'returned')
                || str_contains($orderStatus, 'cancelled')
                || str_contains($orderStatus, 'closed')
                || str_contains($orderStatus, 'exchange')) {
                continue;
            }

            $quantity = (int) ($row->quantity ?? 0);
            $productPrice = (float) ($row->product_price ?? 0);
            $estRev = (float) ($row->estimated_merchandise_revenue ?? 0);
            $lineRevenue = $productPrice > 0 ? $productPrice * $quantity : ($estRev > 0 ? $estRev : 0.0);
            $unitPriceForPft = $productPrice > 0 ? $productPrice : ($quantity > 0 && $estRev > 0 ? $estRev / $quantity : ($estRev > 0 ? $estRev : 0.0));

            $totalOrders++;
            $totalQuantity += $quantity;
            $totalRevenue += $lineRevenue;

            if ($quantity > 0 && $unitPriceForPft > 0) {
                $totalWeightedPrice += $unitPriceForPft * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $skuKey = strtoupper(trim((string) ($row->seller_sku ?? '')));
            $lp = 0.0;
            $ship = 0.0;
            if ($skuKey !== '' && isset($productMasters[$skuKey])) {
                $pm = $productMasters[$skuKey];
                $values = is_array($pm->Values)
                    ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                foreach ($values as $k => $v) {
                    if (strtolower((string) $k) === 'lp') {
                        $lp = (float) $v;
                        break;
                    }
                }
                if ($lp === 0.0 && isset($pm->lp)) {
                    $lp = (float) $pm->lp;
                }

                if (isset($values['ship'])) {
                    $ship = (float) $values['ship'];
                } elseif (isset($pm->ship)) {
                    $ship = (float) $pm->ship;
                }
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            $pft = ($unitPriceForPft * $margin - $lp - $ship) * $quantity;
            $totalPft += $pft;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0.0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0.0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_quantity' => $totalQuantity,
            'total_sales' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_pft' => $totalPft,
            'pft_percentage' => $pftPercentage,
            'roi_percentage' => $roiPercentage,
            'avg_price' => $avgPrice,
        ];
    }

    public function getSheinChannelData(Request $request)
    {
        $result = [];

        // L30 + L60 from Shopify Sen Shp (same source as /shein-tabulator).
        // Old paths (shein_daily_data + shein_daily_data_l60 + sheet fallback) are kept as fallback
        // only when the Shopify pull is empty, so legacy uploads don't disappear.
        [$l30Start, $l30End] = SheinShopifySalesService::tabulatorL30Window();
        $shopifyL30 = SheinShopifySalesService::computeChannelSummary($l30Start, $l30End);

        $metrics = MarketplaceDailyMetric::where('channel', 'Shein')->latest('date')->first();
        $shopifyHasTotals = $shopifyL30['total_orders'] > 0 || $shopifyL30['total_sales'] > 0.00001;

        if ($shopifyHasTotals) {
            $l30Sales = $shopifyL30['total_sales'];
            $l30Orders = $shopifyL30['total_orders'];
            $totalQuantity = $shopifyL30['total_quantity'];
            $totalProfit = $shopifyL30['total_pft'];
            $totalCogs = $shopifyL30['total_cogs'];
            $gProfitPct = $shopifyL30['pft_percentage'];
            $gRoi = $shopifyL30['roi_percentage'];
            $nPftValue = $totalProfit;
            $nRoi = $gRoi;
        } else {
            // Legacy fallback chain: shein_daily_data → marketplace_daily_metrics → shein sheet.
            $agg = $this->aggregateSheinDailyDataLikeTabulator();
            $sheetAgg = $agg === null ? $this->aggregateSheinSheetSalesFallback() : null;
            $aggLooksEmpty = $agg !== null
                && $agg['total_orders'] === 0
                && ($agg['total_sales'] ?? 0) <= 0.00001
                && SheinDailyData::query()->exists();
            $metricsHasTotals = $metrics && (
                (float) ($metrics->total_sales ?? 0) > 0.00001
                || (int) ($metrics->total_orders ?? 0) > 0
            );

            if ($agg !== null && ! $aggLooksEmpty) {
                $l30Sales = $agg['total_sales'];
                $l30Orders = $agg['total_orders'];
                $totalQuantity = $agg['total_quantity'];
                $totalProfit = $agg['total_pft'];
                $totalCogs = $agg['total_cogs'];
                $gProfitPct = $agg['pft_percentage'];
                $gRoi = $agg['roi_percentage'];
                $nPftValue = $totalProfit;
                $nRoi = $gRoi;
            } elseif ($aggLooksEmpty && $metricsHasTotals) {
                $l30Sales = (float) ($metrics->total_sales ?? 0);
                $l30Orders = (int) ($metrics->total_orders ?? 0);
                $totalQuantity = (int) ($metrics->total_quantity ?? 0);
                $totalProfit = (float) ($metrics->total_pft ?? 0);
                $totalCogs = (float) ($metrics->total_cogs ?? 0);
                $gProfitPct = (float) ($metrics->pft_percentage ?? 0);
                $gRoi = (float) ($metrics->roi_percentage ?? 0);
                $nPftValue = (float) ($metrics->n_pft ?? $totalProfit);
                $nRoi = (float) ($metrics->n_roi ?? $gRoi);
            } elseif ($sheetAgg !== null) {
                $l30Sales = $sheetAgg['total_sales'];
                $l30Orders = $sheetAgg['total_orders'];
                $totalQuantity = $sheetAgg['total_quantity'];
                $totalProfit = $sheetAgg['total_pft'];
                $totalCogs = $sheetAgg['total_cogs'];
                $gProfitPct = $sheetAgg['pft_percentage'];
                $gRoi = $sheetAgg['roi_percentage'];
                $nPftValue = $totalProfit;
                $nRoi = $gRoi;
            } else {
                $l30Sales = (float) ($metrics?->total_sales ?? 0);
                $l30Orders = (int) ($metrics?->total_orders ?? 0);
                $totalQuantity = (int) ($metrics?->total_quantity ?? 0);
                $totalProfit = (float) ($metrics?->total_pft ?? 0);
                $totalCogs = (float) ($metrics?->total_cogs ?? 0);
                $gProfitPct = (float) ($metrics?->pft_percentage ?? 0);
                $gRoi = (float) ($metrics?->roi_percentage ?? 0);
                $nPftValue = (float) ($metrics?->n_pft ?? $totalProfit);
                $nRoi = (float) ($metrics?->n_roi ?? $gRoi);
            }
        }

        // L60 from Shopify Sen Shp (days 31–60 PST). Fallback to legacy shein_daily_data_l60
        // when Shopify returns nothing in that window.
        [$l60Start, $l60End] = SheinShopifySalesService::channelMasterL60Window();
        $shopifyL60 = SheinShopifySalesService::computeChannelSummary($l60Start, $l60End);

        if ($shopifyL60['total_orders'] > 0 || $shopifyL60['total_sales'] > 0.00001) {
            $l60Orders = $shopifyL60['total_orders'];
            $l60Sales = $shopifyL60['total_sales'];
            $gprofitL60 = $shopifyL60['pft_percentage'];
            $gRoiL60 = $shopifyL60['roi_percentage'];
        } else {
            $l60Agg = $this->aggregateSheinDailyDataL60LikeTabulator();
            $l60Orders = 0;
            $l60Sales = 0;
            $gprofitL60 = 0;
            $gRoiL60 = 0;
            if ($l60Agg !== null) {
                $l60Orders = $l60Agg['total_orders'];
                $l60Sales = $l60Agg['total_sales'];
                $gprofitL60 = $l60Agg['pft_percentage'];
                $gRoiL60 = $l60Agg['roi_percentage'];
            }
        }
        
        // Calculate growth: (L30 - L60) / L60 * 100 when L60 > 0
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : ($l30Sales > 0 ? 100.0 : 0);
        
        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shein')->first();

        $mapMissCounts = $this->getSheinLiveMapMissNMapFromPricingData();

        $result[] = [
            'Channel '   => 'Shein',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => (int) round($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('Shein'),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shein channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Shein Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NR
     */
    private function getSheinMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = SheinListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // Shein uses nr_req (REQ/NR)
            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getTiktokChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated from ShipHub)
        // Try both 'TikTok' and 'Tiktok Shop' channel names
        $metrics = MarketplaceDailyMetric::where('channel', 'TikTok')
            ->orWhere('channel', 'Tiktok Shop')
            ->latest('date')
            ->first();
        
        // Get L60 data from ShipHub (33-66 days ago range)
        $latestDate = DB::connection('shiphub')
            ->table('orders')
            ->where('marketplace', '=', 'tiktok')
            ->max('order_date');

        if ($latestDate) {
            // Use California timezone for consistent date calculations
            $latestDateCarbon = \Carbon\Carbon::parse($latestDate, 'America/Los_Angeles');
            // L60 widened to 62 calendar days, anchored to day boundaries.
            // It is the prior period that sits directly before the 32-day L30 window:
            //   L30 = [latestDate-31 .. latestDate]   (32 days)
            //   L60 = [latestDate-93 .. latestDate-32] (62 days, contiguous and non-overlapping)
            $l60StartDate = $latestDateCarbon->copy()->subDays(93)->startOfDay(); // 62 days total
            $l60EndDate   = $latestDateCarbon->copy()->subDays(32)->endOfDay();
            
            // L60 sales & order count for TikTok come from `orders.order_total`. The previous
            // query summed `order_items.unit_price`, but ShipHub stores TikTok revenue only on
            // `orders.order_total` — `order_items.unit_price` is 0 for every TikTok item, which
            // made L60 sales always $0. We also need DISTINCT orders (not items) for the count.
            $l60Stats = DB::connection('shiphub')
                ->table('orders')
                ->whereBetween('order_date', [$l60StartDate, $l60EndDate])
                ->where('marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('order_status', '!=', 'Canceled')
                          ->where('order_status', '!=', 'Cancelled')
                          ->orWhereNull('order_status');
                })
                ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(order_total), 0) as total_sales')
                ->first();

            $l60Orders = (int) ($l60Stats->order_count ?? 0);
            $l60Sales  = (float) ($l60Stats->total_sales ?? 0);
            
            // Get L30 data from ShipHub (last 32 calendar days, California time).
            // startOfDay() avoids dropping orders whose timestamp is earlier than latestDate's
            // time-of-day on the start boundary day.
            $l30StartDate = $latestDateCarbon->copy()->subDays(31)->startOfDay(); // 32 days total (31 previous days + today)
            $l30EndDate = $latestDateCarbon->endOfDay();
            
            $l30OrderItems = DB::connection('shiphub')
                ->table('orders as o')
                ->join('order_items as i', 'o.id', '=', 'i.order_id')
                ->whereBetween('o.order_date', [$l30StartDate, $l30EndDate])
                ->where('o.marketplace', '=', 'tiktok')
                ->where(function($query) {
                    $query->where('o.order_status', '!=', 'Canceled')
                          ->where('o.order_status', '!=', 'Cancelled')
                          ->orWhereNull('o.order_status');
                })
                ->select([
                    'o.marketplace_order_id as order_id',
                    'o.order_total as total_amount',
                    'i.sku',
                    'i.quantity_ordered as quantity',
                ])
                ->get();
            
            // Calculate L30 metrics from ShipHub
            $l30Orders = 0;
            $l30Sales = 0;
            $totalQuantity = 0;
            $totalProfit = 0;
            $totalCogs = 0;
            
            // Load ProductMasters with UPPERCASE keys (EXACT SAME as TikTokSalesController)
            $skus = $l30OrderItems->pluck('sku')->filter()->unique()->values()->toArray();
            $productMasters = \App\Models\ProductMaster::whereIn('sku', $skus)
                ->get()
                ->keyBy(function ($item) {
                    return strtoupper($item->sku);
                });
            $orderIds = [];
            
            // Group items by order
            $orderGroups = [];
            foreach ($l30OrderItems as $item) {
                $orderId = $item->order_id ?? 'unknown';
                if (!isset($orderGroups[$orderId])) {
                    $orderGroups[$orderId] = [
                        'order_total' => (float) ($item->total_amount ?? 0),
                        'items' => []
                    ];
                }
                $orderGroups[$orderId]['items'][] = $item;
            }
            
            // Process each order
            foreach ($orderGroups as $orderId => $orderData) {
                $orderTotal = $orderData['order_total'];
                $items = $orderData['items'];
                $itemCount = count($items);
                $pricePerItem = $itemCount > 0 ? $orderTotal / $itemCount : $orderTotal;
                
                $orderIds[$orderId] = true;
                
                // Process items - use same rounding methodology as TikTokSalesController to match sales page
                foreach ($items as $item) {
                    $sku = trim($item->sku ?? '');
                    $quantity = (float) ($item->quantity ?? 1);
                    
                    // Sum rounded distributed prices per item (same as sales page)
                    $totalPrice = $pricePerItem; // Distributed price per item
                    $saleAmount = round($totalPrice, 2); // Round each item's price
                    $l30Sales += $saleAmount;
                    
                    // Use UPPERCASE for lookup (EXACT SAME as TikTokSalesController)
                    $pm = $productMasters->get(strtoupper($sku));
                    
                    // EXACT SAME LOGIC as TikTokSalesController for LP, Ship, Weight Act
                    $lp = 0;
                    $ship = 0;
                    $weightAct = 0;
                    
                    if ($sku && $pm) {
                        $values = is_array($pm->Values) ? $pm->Values : 
                                (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                        
                        // Get LP
                        if (is_array($values)) {
                            foreach ($values as $k => $v) {
                                if (strtolower($k) === "lp") {
                                    $lp = floatval($v);
                                    break;
                                }
                            }
                        }
                        if ($lp === 0 && isset($pm->lp)) {
                            $lp = floatval($pm->lp);
                        }
                        
                        // Get Ship
                        if (is_array($values) && isset($values['ship'])) {
                            $ship = floatval($values['ship']);
                        } elseif (isset($pm->ship)) {
                            $ship = floatval($pm->ship);
                        }
                        
                        // Get Weight Act
                        if (is_array($values) && isset($values['wt_act'])) {
                            $weightAct = floatval($values['wt_act']);
                        }
                    }
                    
                    // Ship Cost calculation (EXACT SAME as TikTokSalesController)
                    $tWeight = $weightAct * $quantity;
                    if ($quantity == 1) {
                        $shipCost = $ship;
                    } elseif ($quantity > 1 && $tWeight < 20) {
                        $shipCost = $ship / $quantity;
                    } else {
                        $shipCost = $ship;
                    }
                    
                    $unitPrice = $quantity > 0 ? $pricePerItem / $quantity : 0;
                    $cogs = $lp * $quantity;
                    $pftEach = ($unitPrice * 0.80) - $lp - $shipCost; // 80% margin for TikTok
                    $profit = $pftEach * $quantity;
                    
                    $totalQuantity += $quantity;
                    $totalCogs += $cogs;
                    $totalProfit += $profit;
                }
            }
            
            $l30Orders = count($orderIds);
            
            // Calculate percentages
            $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
            $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
            $nPft = $gProfitPct; // TikTok has no ads
            $nRoi = $gRoi;
            
            // Debug log
            Log::info('TikTok Channel Data Calculation', [
                'l30_sales' => $l30Sales,
                'total_profit' => $totalProfit,
                'total_cogs' => $totalCogs,
                'g_profit_pct' => $gProfitPct,
                'g_roi' => $gRoi,
                'l30_orders' => $l30Orders,
                'order_count' => count($orderGroups)
            ]);
        } else {
            $l60Orders = 0;
            $l60Sales = 0;
            $l30Orders = 0;
            $l30Sales = 0;
            $totalQuantity = 0;
            $totalProfit = 0;
            $totalCogs = 0;
            $gProfitPct = 0;
            $gRoi = 0;
            $nPft = 0;
            $nRoi = 0;
        }
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // L60 profit percentage (calculated if needed)
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Tiktok Shop')->first();

        // Live Map / Miss / NMap from tiktok-pricing (same tolerance as Reverb)
        $mapMissCounts = $this->getTiktokLiveMapMissNMapFromPricingData($request);

        // Total Ad Spend: fetch directly from tiktok_campaign_reports table
        $tiktokAdSpend = $this->fetchTotalAdSpendFromTables('tiktokshop');
        $adsPct = $l30Sales > 0 ? ($tiktokAdSpend / $l30Sales) * 100 : 0;
        $netProfit = $totalProfit - $tiktokAdSpend;
        $nPftWithAds = $gProfitPct - $adsPct;
        $nRoiWithAds = $totalCogs > 0 ? ($netProfit / $totalCogs) * 100 : 0;

        $result[] = [
            'Channel '   => 'Tiktok Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2),
            'gprofitL60' => round($gprofitL60, 2),
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPftWithAds, 2),
            'N ROI'      => round($nRoiWithAds, 2),
            'Ads%'       => round($adsPct, 2),
            'TikTok Ad Spend' => round($tiktokAdSpend, 2),
            'KW Spent'   => round($tiktokAdSpend, 2), // TikTok KW ad spend
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => round($tiktokAdSpend, 2),
            'type'       => $channelData->type ?? 'B2C',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            'base'       => $channelData->base ?? 0,
            'sheet_link' => $channelData->sheet_link ?? '',
            'ra'         => $channelData->ra ?? 0,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Tiktok channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getTikTokTwoChannelData(Request $request)
    {
        $result = [];

        $metrics = MarketplaceDailyMetric::where('channel', 'TikTok 2')->latest('date')->first();

        $latestOrderDate = TiktokSalesTwo::whereNotNull('order_date')->max('order_date');
        $l60Orders = 0;
        $l60Sales = 0;
        $l30Orders = 0;
        $l30Sales = 0;
        $totalQuantity = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $gProfitPct = 0;
        $gRoi = 0;
        $nPft = 0;
        $nRoi = 0;

        if ($latestOrderDate) {
            $latestCarbon = \Carbon\Carbon::parse($latestOrderDate);
            $l60StartDate = $latestCarbon->copy()->subDays(59)->startOfDay();
            $l60EndDate = $latestCarbon->copy()->subDays(30)->endOfDay();
            $l30StartDate = $latestCarbon->copy()->subDays(29)->startOfDay();
            $l30EndDate = $latestCarbon->copy()->endOfDay();

            $l60Rows = TiktokSalesTwo::whereBetween('order_date', [$l60StartDate, $l60EndDate])->get();
            $l60Orders = $l60Rows->pluck('order_id')->unique()->count();
            $l60Sales = $l60Rows->sum(function ($r) {
                return (float) $r->unit_price * (int) ($r->quantity ?: 1);
            });

            $l30Rows = TiktokSalesTwo::whereBetween('order_date', [$l30StartDate, $l30EndDate])->get();
            $productMasters = \App\Models\ProductMaster::all()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            $margin = 0.80;
            $orderIds = [];
            foreach ($l30Rows as $row) {
                $orderIds[$row->order_id] = true;
                $quantity = (float) ($row->quantity ?: 1);
                $unitPrice = (float) $row->unit_price;
                $l30Sales += $unitPrice * $quantity;

                $sku = strtoupper($row->seller_sku ?? '');
                $lp = 0;
                $ship = 0;
                $weightAct = 0;
                $pm = $productMasters->get($sku);
                if ($sku && $pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            if (strtolower($k) === 'lp') {
                                $lp = floatval($v);
                                break;
                            }
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    if (is_array($values) && isset($values['ship'])) {
                        $ship = floatval($values['ship']);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                    if (is_array($values) && isset($values['wt_act'])) {
                        $weightAct = floatval($values['wt_act']);
                    }
                }
                $tWeight = $weightAct * $quantity;
                if ($quantity == 1) {
                    $shipCost = $ship;
                } elseif ($quantity > 1 && $tWeight < 20) {
                    $shipCost = $ship / $quantity;
                } else {
                    $shipCost = $ship;
                }
                $cogs = $lp * $quantity;
                $pftEach = ($unitPrice * $margin) - $lp - $shipCost;
                $totalQuantity += $quantity;
                $totalCogs += $cogs;
                $totalProfit += $pftEach * $quantity;
            }
            $l30Orders = count($orderIds);
            $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
            $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
            $nPft = $gProfitPct;
            $nRoi = $gRoi;
        }

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        $mapMissCounts = $this->getTiktok2LiveMapMissNMapFromPricingData($request);
        $channelData = ChannelMaster::whereIn('channel', ['TikTok 2', 'Tiktok Shop 2'])->first();

        $result[] = [
            'Channel '   => 'TikTok 2',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2),
            'gprofitL60' => 0,
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => 0,
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2),
            'N ROI'      => round($nRoi, 2),
            'Ads%'       => 0,
            'TikTok Ad Spend' => 0,
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => optional($channelData)->type ?? 'B2C',
            'W/Ads'      => optional($channelData)->w_ads ?? 0,
            'NR'         => optional($channelData)->nr ?? 0,
            'Update'     => optional($channelData)->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            'base'       => optional($channelData)->base ?? 0,
            'sheet_link' => optional($channelData)->sheet_link ?? '',
            'ra'         => optional($channelData)->ra ?? 0,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'TikTok 2 channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getDepopChannelData(Request $request)
    {
        $result = [];
        $margin = 0.87; // Depop margin
        $latestSaleDate = DepopSalesData::whereNotNull('sale_date')->max('sale_date');
        $l60Orders = 0;
        $l60Sales = 0;
        $l30Orders = 0;
        $l30Sales = 0;
        $totalQuantity = 0;
        $totalProfit = 0;
        $totalCogs = 0;
        $gProfitPct = 0;
        $gRoi = 0;

        if ($latestSaleDate) {
            $latestCarbon = \Carbon\Carbon::parse($latestSaleDate);
            $l30Start = $latestCarbon->copy()->subDays(29)->format('Y-m-d');
            $l30End = $latestCarbon->format('Y-m-d');
            $l60Start = $latestCarbon->copy()->subDays(59)->format('Y-m-d');
            $l60End = $latestCarbon->copy()->subDays(30)->format('Y-m-d');

            $l60Rows = DepopSalesData::whereBetween('sale_date', [$l60Start, $l60End])->get();
            $l60Orders = $l60Rows->count();
            $l60Sales = $l60Rows->sum(function ($r) {
                return (float) $r->item_price * (int) ($r->quantity ?: 1);
            });

            // Get product masters for COGS lookup
            $productMasters = \App\Models\ProductMaster::all()->keyBy(function ($item) {
                return strtoupper($item->sku);
            });

            $rows = DepopSalesData::whereBetween('sale_date', [$l30Start, $l30End])->get();
            
            foreach ($rows as $row) {
                $quantity = (int) ($row->quantity ?: 1);
                $unitPrice = (float) $row->item_price;
                $revenue = $unitPrice * $quantity;
                $l30Sales += $revenue;
                $l30Orders++;
                $totalQuantity += $quantity;

                // Try to lookup COGS from Product Master using sku_code first
                $sku = strtoupper($row->sku_code ?? '');
                $lp = 0;
                $ship = 0;
                
                $pm = $productMasters->get($sku);
                if ($sku && $pm) {
                    $values = is_array($pm->Values) ? $pm->Values : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    if (is_array($values)) {
                        foreach ($values as $k => $v) {
                            if (strtolower($k) === 'lp') {
                                $lp = floatval($v);
                                break;
                            }
                        }
                    }
                    if ($lp === 0 && isset($pm->lp)) {
                        $lp = floatval($pm->lp);
                    }
                    if (is_array($values) && isset($values['ship'])) {
                        $ship = floatval($values['ship']);
                    } elseif (isset($pm->ship)) {
                        $ship = floatval($pm->ship);
                    }
                }
                
                // If no SKU/COGS found, estimate COGS from actual Depop costs
                // Depop margin of 87% means: Profit = Revenue - Depop Fee - Shipping - COGS
                // So: COGS = Revenue - Profit - Depop Fee - Shipping
                // Where Profit = Revenue * 87% (but this includes all costs)
                // Better approach: Use actual fees from Depop data
                $depopFee = (float) ($row->depop_fee ?? 0);
                $uspsCost = (float) ($row->usps_cost ?? 0);
                
                if ($lp == 0) {
                    // Estimate COGS: Assume 13% of revenue goes to COGS when not found
                    // This is derived from 87% margin, meaning 13% for COGS approximately
                    $lp = $revenue * 0.13 / $quantity;
                }
                
                if ($ship == 0 && $uspsCost > 0) {
                    $ship = $uspsCost;
                }
                
                // Calculate COGS and profit
                // Revenue = Item Price
                // Costs = Depop Fee + USPS Cost + COGS(LP)
                // Profit = Revenue - All Costs
                $cogs = $lp * $quantity;
                $totalShipping = $ship > 0 ? $ship : $uspsCost;
                $totalFees = $depopFee;
                
                // Profit = Revenue - COGS - Shipping - Fees
                $profit = $revenue - $cogs - $totalShipping - $totalFees;
                
                $totalCogs += $cogs;
                $totalProfit += $profit;
            }
            
            $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
            $gRoi = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        }

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        $mapMissCounts = $this->getMapAndMissCounts('depop');
        $channelData = ChannelMaster::where('channel', 'Depop')->first();

        // N PFT and N ROI are same as G values since there's no ad spend
        $nPft = $gProfitPct;
        $nRoi = $gRoi;

        $result[] = [
            'Channel '   => 'Depop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => 0,
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => 0,
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'Ads%'       => 0,
            'TikTok Ad Spend' => 0,
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => optional($channelData)->type ?? 'B2C',
            'W/Ads'      => optional($channelData)->w_ads ?? 0,
            'NR'         => optional($channelData)->nr ?? 0,
            'Update'     => optional($channelData)->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'base'       => optional($channelData)->base ?? 0,
            'sheet_link' => optional($channelData)->sheet_link ?? '',
            'ra'         => optional($channelData)->ra ?? 0,
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Depop channel data fetched successfully',
            'data' => $result,
        ]);
    }

    private function getTiktokShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = TiktokShopListingStatus::whereIn('sku', $skus)->orderBy('updated_at', 'desc')->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) $status = json_decode($status, true);

            $nrReq = isset($status['nr_req']) ? $status['nr_req'] : 'REQ';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Count as missing listing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getInstagramChannelData(Request $request)
    {
        $result = [];

        $query = InstagramShopSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('i_l30');
        $l60Orders = $query->sum('i_l60');
        $totalQuantity = $l30Orders; // i_l30 is already units sold (quantity)

        $l30Sales  = (clone $query)->selectRaw('SUM(i_l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(i_l60 * price) as total')->value('total') ?? 0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Instagram Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'i_l30','i_l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->i_l30;
            $unitsL60  = (int) $row->i_l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Instagram Shop')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('instagram');

        $result[] = [
            'Channel '   => 'Instagram Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'Total PFT'  => round($totalProfit, 2),
            'N ROI'      => round($gRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Instagram Shop
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getInstagramShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = InstagramShopListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    /**
     * AliExpress sales / orders / PFT totals — same rules as AliExpress Daily Data tabulator
     * (aliexpress_tabulator_view updateSummary + /aliexpress/daily-data line revenue).
     */
    private function aggregateAliexpressDailyDataLikeTabulator(): ?array
    {
        return app(AliexpressController::class)->aggregateOrderRowsForChannelMaster('l30');
    }

    /**
     * AliExpress L60 — same rules as L30, from aliexpress_daily_data_l60 upload.
     */
    private function aggregateAliexpressDailyDataL60LikeTabulator(): ?array
    {
        return app(AliexpressController::class)->aggregateOrderRowsForChannelMaster('l60');
    }

    public function getAliexpressChannelData(Request $request)
    {
        $result = [];

        $agg = $this->aggregateAliexpressDailyDataLikeTabulator();
        $metrics = null;
        if ($agg === null) {
            $metrics = MarketplaceDailyMetric::where('channel', 'AliExpress')->latest('date')->first();
        }

        $l60Agg = $this->aggregateAliexpressDailyDataL60LikeTabulator();
        if ($l60Agg !== null) {
            $l60Orders = (int) ($l60Agg['total_orders'] ?? 0);
            $l60Sales = (float) ($l60Agg['total_sales'] ?? 0);
            $gprofitL60 = (float) ($l60Agg['pft_percentage'] ?? 0);
            $gRoiL60 = (float) ($l60Agg['roi_percentage'] ?? 0);
        } else {
            $query = AliExpressSheetData::where('sku', 'not like', '%Parent%');
            $l60Orders = (int) $query->sum('aliexpress_l60');
            $l60Sales = (float) ((clone $query)->selectRaw('SUM(aliexpress_l60 * price) as total')->value('total') ?? 0);
            $gprofitL60 = 0;
            $gRoiL60 = 0;
        }

        if ($agg !== null) {
            $l30Sales = $agg['total_sales'];
            $l30Orders = $agg['total_orders'];
            $totalQuantity = $agg['total_quantity'];
            $totalProfit = $agg['total_pft'];
            $totalCogs = $agg['total_cogs'];
            $gProfitPct = $agg['pft_percentage'];
            $gRoi = $agg['roi_percentage'];
            $nRoi = $gRoi;
        } else {
            $l30Sales = (float) ($metrics?->total_sales ?? 0);
            $l30Orders = (int) ($metrics?->total_orders ?? 0);
            $totalQuantity = (int) ($metrics?->total_quantity ?? 0);
            $totalProfit = (float) ($metrics?->total_pft ?? 0);
            $totalCogs = (float) ($metrics?->total_cogs ?? 0);
            $gProfitPct = (float) ($metrics?->pft_percentage ?? 0);
            $gRoi = (float) ($metrics?->roi_percentage ?? 0);
            $nRoi = (float) ($metrics?->n_roi ?? $gRoi);
        }
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Aliexpress')->first();

        $mapMissCounts = $this->getAliexpressLiveMapMissNMapFromPricingData();

        $result[] = [
            'Channel '   => 'Aliexpress',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => (int) round($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('Aliexpress'),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Aliexpress channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get AliExpress Missing Listing count
     * Missing Listing = SKUs with INV > 0, not PARENT, not Listed, and not NRL
     */
    private function getAliexpressMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = AliexpressListingStatus::whereIn('sku', $skus)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = isset($shopifyData[$sku]) ? $shopifyData[$sku]->inv : 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = isset($statusData[$sku]) ? $statusData[$sku]->value : null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // AliExpress uses rl_nrl (RL/NRL)
            $rlNrl = isset($status['rl_nrl']) ? $status['rl_nrl'] : 'RL';
            $listed = isset($status['listed']) ? $status['listed'] : null;

            // Missing Listing: Not Listed AND not marked as NRL
            if ($listed !== 'Listed' && $rlNrl !== 'NRL') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMercariWShipChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Mercari With Ship')->latest('date')->first();

        // Get L60 data from sheet data for comparison
        $query = MercariWShipSheetdata::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari w ship')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('mercari_w_ship');

        $result[] = [
            'Channel '   => 'Mercari w ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Mercari w ship channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Mercari w Ship
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getMercariWShipMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = MercariWShipListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getMercariWoShipChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Mercari Without Ship')->latest('date')->first();

        // Get L60 data from sheet data for comparison
        $query = MercariWoShipSheetdata::where('sku', 'not like', '%Parent%');
        $l60Orders = $query->sum('l60');
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Mercari wo ship')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('mercari_wo_ship');

        $result[] = [
            'Channel '   => 'Mercari wo ship',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Mercari wo ship channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for Mercari w/o Ship
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getMercariWoShipMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = MercariWoShipListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getFbMarketplaceChannelData(Request $request)
    {
        $result = [];

        $query = FbMarketplaceSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'FB Marketplace')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'FB Marketplace')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('fb_marketplace');

        $result[] = [
            'Channel '   => 'FB Marketplace',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'Total PFT'  => round($totalProfit, 2),
            'N ROI'      => round($gRoi, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get Missing Listing count for FB Marketplace
     * Missing Listing = SKUs where INV > 0, not PARENT, not Listed, and not NR
     */
    private function getFBMarketplaceMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = FBMarketplaceListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingListingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            // Count as missing if not Listed and not NR
            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingListingCount++;
            }
        }

        return $missingListingCount;
    }

    public function getFbShopChannelData(Request $request)
    {
        $result = [];

        $query = FbShopSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'FB Shop')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'FB Shop')->first();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('fb_shop');

        $result[] = [
            'Channel '   => 'FB Shop',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    public function getBusiness5CoreChannelData(Request $request)
    {
        $result = [];

        $query = BusinessFiveCoreSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');
        $totalQuantity = $l30Orders; // l30 is already units sold (quantity)

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        // Get eBay marketing percentage
        $percentage = ChannelMaster::where('channel', 'Business 5Core')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100; // convert % to fraction

        // Load product masters (lp, ship) keyed by SKU
        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        // Calculate total profit
        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;


        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];

                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            // Profit per unit
            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;

            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        // --- FIX: Calculate total LP only for SKUs in eBayMetrics ---
        $ebaySkus   = $ebayRows->pluck('sku')->map(fn($s) => strtoupper($s))->toArray();
        $ebayPMs    = ProductMaster::whereIn('sku', $ebaySkus)->get();

        $totalLpValue = 0;
        foreach ($ebayPMs as $pm) {
            $values = is_array($pm->Values) ? $pm->Values :
                    (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

            $lp = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
            $totalLpValue += $lp;
        }

        // Use L30 Sales for denominator
        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;

        // $gRoi       = $totalLpValue > 0 ? ($totalProfit / $totalLpValue) : 0;
        // $gRoiL60    = $totalLpValue > 0 ? ($totalProfitL60 / $totalLpValue) : 0;

        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;

        // N PFT = (Sum of PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Business 5Core')->first();

        // Get Missing Listing count
        $missingListingCount = $this->getBusiness5CoreMissingListingCount();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('business5core');

        $result[] = [
            'Channel '   => 'Business 5Core',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N ROI'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'wayfair channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * TopDawg channel data. Prefer marketplace_daily_metrics (from app:update-marketplace-daily-metrics / topdawg_order_metrics), else fall back to TopDawgSheetdata.
     * Sheet link default: /topdawg/sales-dashboard — see migration ensure_topdawg_sales_dashboard_sheet_link.
     */
    public function getTopDawgChannelData(Request $request)
    {
        $result = [];
        $metrics = MarketplaceDailyMetric::where('channel', 'TopDawg')->latest('date')->first();

        if ($metrics) {
            $l60Orders = 0;
            $l60Sales = 0;
            $l30Sales = $metrics->total_sales ?? 0;
            $l30Orders = $metrics->total_orders ?? 0;
            $totalQuantity = $metrics->total_quantity ?? 0;
            $totalProfit = $metrics->total_pft ?? 0;
            $totalCogs = $metrics->total_cogs ?? 0;
            $gProfitPct = $metrics->pft_percentage ?? 0;
            $gRoi = $metrics->roi_percentage ?? 0;
            $tacosPercentage = $metrics->tacos_percentage ?? 0;
            $nPft = $metrics->n_pft ?? $gProfitPct;
            $nRoi = $metrics->n_roi ?? $gRoi;
            $totalAdSpend = $this->fetchTotalAdSpendFromTables('topdawg');
            $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
            $gprofitL60 = 0;
            $gRoiL60 = 0;
            $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

            $channelData = ChannelMaster::where('channel', 'TopDawg')->first();
            $mapMissCounts = $this->getMapAndMissCounts('topdawg');

            $result[] = [
                'Channel '   => 'TopDawg',
                'L-60 Sales' => intval($l60Sales),
                'L30 Sales'  => intval($l30Sales),
                'Growth'     => round($growth, 2) . '%',
                'L60 Orders' => $l60Orders,
                'L30 Orders' => $l30Orders,
                'Qty'        => intval($totalQuantity),
                'Gprofit%'   => round($gProfitPct, 2) . '%',
                'gprofitL60' => round($gprofitL60, 2) . '%',
                'G Roi'      => round($gRoi, 2),
                'G RoiL60'   => round($gRoiL60, 2),
                'Total PFT'  => round($totalProfit, 2),
                'N PFT'      => round($nPft, 2) . '%',
                'N ROI'      => round($nRoi, 2),
                'KW Spent'   => $totalAdSpend,
                'PT Spent'   => 0,
                'HL Spent'   => 0,
                'PMT Spent'  => 0,
                'Shopping Spent' => 0,
                'SERP Spent' => 0,
                'Total Ad Spend' => $totalAdSpend,
                'Ads%'       => round($adsPercentage, 2) . '%',
                'TACOS %'    => round($tacosPercentage, 2) . '%',
                'type'       => $channelData->type ?? '',
                'W/Ads'      => $channelData->w_ads ?? 0,
                'NR'         => $channelData->nr ?? 0,
                'Update'     => $channelData->update ?? 0,
                'cogs'       => round($totalCogs, 2),
                'Map'        => $mapMissCounts['map'],
                'Miss'       => $mapMissCounts['miss'],
                'NMap'       => $mapMissCounts['nmap'],
                'Total Views' => $mapMissCounts['total_views'] ?? 0,
                'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('TopDawg'),
                ...$this->getChannelHealthAndReviewsStub(),
            ];

            return response()->json([
                'status' => 200,
                'message' => 'TopDawg channel data (from marketplace daily metrics)',
                'data' => $result,
            ]);
        }

        // Fallback: TopDawgSheetdata (legacy)
        $query = TopDawgSheetdata::where('sku', 'not like', '%Parent%');

        $l30Orders = $query->sum('l30');
        $l60Orders = $query->sum('l60');
        $totalQuantity = $l30Orders;

        $l30Sales  = (clone $query)->selectRaw('SUM(l30 * price) as total')->value('total') ?? 0;
        $l60Sales  = (clone $query)->selectRaw('SUM(l60 * price) as total')->value('total') ?? 0;

        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;

        $percentage = ChannelMaster::where('channel', 'TopDawg')->value('channel_percentage') ?? 100;
        $percentage = $percentage / 100;

        $productMasters = ProductMaster::all()->keyBy(function ($item) {
            return strtoupper($item->sku);
        });

        $ebayRows     = $query->get(['sku', 'price', 'l30','l60']);
        $totalProfit  = 0;
        $totalProfitL60  = 0;
        $totalCogs       = 0;
        $totalCogsL60    = 0;

        foreach ($ebayRows as $row) {
            $sku       = strtoupper($row->sku);
            $price     = (float) $row->price;
            $unitsL30  = (int) $row->l30;
            $unitsL60  = (int) $row->l60;

            $soldAmount = $unitsL30 * $price;
            if ($soldAmount <= 0) {
                continue;
            }

            $lp   = 0;
            $ship = 0;

            if (isset($productMasters[$sku])) {
                $pm = $productMasters[$sku];
                $values = is_array($pm->Values) ? $pm->Values :
                        (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                $lp   = isset($values['lp']) ? (float) $values['lp'] : ($pm->lp ?? 0);
                $ship = isset($values['ship']) ? (float) $values['ship'] : ($pm->ship ?? 0);
            }

            $profitPerUnit = ($price * $percentage) - $lp - $ship;
            $profitTotal   = $profitPerUnit * $unitsL30;
            $profitTotalL60   = $profitPerUnit * $unitsL60;

            $totalProfit += $profitTotal;
            $totalProfitL60 += $profitTotalL60;
            $totalCogs    += ($unitsL30 * $lp);
            $totalCogsL60 += ($unitsL60 * $lp);
        }

        $gProfitPct = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;
        $gprofitL60 = $l60Sales > 0 ? ($totalProfitL60 / $l60Sales) * 100 : 0;
        $gRoi    = $totalCogs > 0 ? ($totalProfit / $totalCogs) * 100 : 0;
        $gRoiL60 = $totalCogsL60 > 0 ? ($totalProfitL60 / $totalCogsL60) * 100 : 0;
        $nPft = $l30Sales > 0 ? ($totalProfit / $l30Sales) * 100 : 0;

        $channelData = ChannelMaster::where('channel', 'TopDawg')->first();
        $mapMissCounts = $this->getMapAndMissCounts('topdawg');

        $result[] = [
            'Channel '   => 'TopDawg',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 2) . '%',
            'gprofitL60' => round($gprofitL60, 2) . '%',
            'G Roi'      => round($gRoi, 2),
            'G RoiL60'   => round($gRoiL60, 2),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 2) . '%',
            'N ROI'      => round($nPft, 2),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'Ads%'       => '0%',
            'TACOS %'    => '0%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            'missing_link' => $channelData->missing_link ?? $this->defaultMissingLinkForChannel('TopDawg'),
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'TopDawg channel data (legacy sheet data)',
            'data' => $result,
        ]);
    }

    public function getShopifyB2CChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Shopify B2C')->latest('date')->first();

        // L60 = prior 30-day period (days 31-60) computed from shopify_b2c_daily_data.
        // Previously this was hard-coded to 0; the table actually keeps an `l60` period bucket
        // plus a real `order_date` column, so we filter by date and sum `total_amount` the same
        // way calculateShopifyB2CMetrics() sums L30. Refunded orders are excluded to match L30.
        $pst = 'America/Los_Angeles';
        $todayPst = Carbon::now($pst)->startOfDay();
        $l60Start = $todayPst->copy()->subDays(59);
        $l60End   = $todayPst->copy()->subDays(30)->endOfDay();
        $l60Rows = \App\Models\ShopifyB2CDailyData::whereBetween('order_date', [$l60Start, $l60End])
            ->where('financial_status', '!=', 'refunded')
            ->get(['order_id', 'total_amount']);
        $l60Orders = $l60Rows->pluck('order_id')->filter()->unique()->count();
        $l60Sales  = (float) $l60Rows->sum(function ($r) {
            return (float) ($r->total_amount ?? 0);
        });

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $tacosPercentage = $metrics->tacos_percentage ?? 0;
        $nPft = $metrics->n_pft ?? $gProfitPct;
        $nRoi = $metrics->n_roi ?? $gRoi;
        $googleSpent = $metrics->kw_spent ?? 0; // Google Ads spend is stored in kw_spent field

        // Total Ad Spend: fetch directly from google_ads_campaigns table
        // Shopping ads (advertising_channel_type = 'SHOPPING')
        $shoppingAdSpend = $this->fetchTotalAdSpendFromTables('shopifyb2c');
        
        // SERP/Search ads (advertising_channel_type = 'SEARCH') - include ENABLED and PAUSED campaigns
        $endDate = Carbon::now()->subDays(2)->format('Y-m-d');
        $startDate = Carbon::now()->subDays(31)->format('Y-m-d');
        $serpAdSpend = round(
            (float) DB::table('google_ads_campaigns')
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->where('advertising_channel_type', 'SEARCH')
                ->whereIn('campaign_status', ['ENABLED', 'PAUSED'])
                ->sum('metrics_cost_micros') / 1000000,
            2
        );
        
        $totalAdSpend = round($shoppingAdSpend + $serpAdSpend, 2);
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // Calculate Ads %
        $adsPercentage = $l30Sales > 0 ? ($totalAdSpend / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2C')->first();

        // Calculate Missing Listing count for Shopify B2C
        $missingListingCount = $this->getShopifyB2CMissingListingCount();

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('shopify_b2c');

        $result[] = [
            'Channel '   => 'Shopify B2C',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => $shoppingAdSpend, // Google Shopping spend (SHOPPING type)
            'SERP Spent' => $serpAdSpend, // Google Search spend (SEARCH type)
            'Total Ad Spend' => $totalAdSpend,
            'Ads%'       => round($adsPercentage, 1) . '%',
            'TACOS %'    => round($tacosPercentage, 1) . '%',
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map'        => $mapMissCounts['map'],
            'Miss'       => $mapMissCounts['miss'],
            'NMap'       => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
            'Inv at LP'  => $this->getInvAtLpShopify(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shopify B2C channel data fetched successfully',
            'data' => $result,
        ]);
    }

    /**
     * Calculate Missing Listing count for Shopify B2C
     * This counts SKUs with INV > 0 that are not listed and are not marked as NRL
     */
    private function getShopifyB2CMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = \App\Models\ShopifyB2CListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $pendingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $inv = $shopifyData[$sku]->inv ?? 0;
            $isParent = stripos($sku, 'PARENT') !== false;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            // RL/NRL logic - support both new 'rl_nrl' and old 'nr_req'
            $rlNrl = $status['rl_nrl'] ?? null;
            if (empty($rlNrl) && isset($status['nr_req'])) {
                $rlNrl = ($status['nr_req'] === 'REQ') ? 'RL' : 'NRL';
            }
            if (empty($rlNrl)) $rlNrl = 'RL'; // default

            $listed = $status['listed'] ?? null;

            // Count as pending (missing) if rl_nrl is not NRL AND listed is not Listed
            if ($rlNrl !== 'NRL' && $listed !== 'Listed') {
                $pendingCount++;
            }
        }

        return $pendingCount;
    }

    public function getShopifyB2BChannelData(Request $request)
    {
        $result = [];

        // Get metrics from marketplace_daily_metrics table (pre-calculated)
        $metrics = MarketplaceDailyMetric::where('channel', 'Shopify B2B')->latest('date')->first();
        
        // L60 will be 0 until we have historical data with proper dates
        $l60Orders = 0;
        $l60Sales = 0;

        $l30Sales = $metrics->total_sales ?? 0;
        $l30Orders = $metrics->total_orders ?? 0;
        $totalQuantity = $metrics->total_quantity ?? 0;
        $totalProfit = $metrics->total_pft ?? 0;
        $totalCogs = $metrics->total_cogs ?? 0;
        $gProfitPct = $metrics->pft_percentage ?? 0;
        $gRoi = $metrics->roi_percentage ?? 0;
        $nPftValue = $metrics->n_pft ?? $totalProfit;
        $nRoi = $metrics->n_roi ?? $gRoi;
        
        // Calculate growth
        $growth = $l60Sales > 0 ? (($l30Sales - $l60Sales) / $l60Sales) * 100 : 0;
        
        // L60 profit percentage
        $gprofitL60 = 0;
        $gRoiL60 = 0;

        // N PFT = (Sum of N PFT / Sum of L30 Sales) * 100
        $nPft = $l30Sales > 0 ? ($nPftValue / $l30Sales) * 100 : 0;

        // Channel data
        $channelData = ChannelMaster::where('channel', 'Shopify B2B')->first();

        // Get Missing Listing count
        $missingListingCount = 0; // Shopify B2B doesn't have listing status tracking

        // Get Map and Miss counts from amazon_channel_summary_data table
        $mapMissCounts = $this->getMapAndMissCounts('shopify_b2b');

        $result[] = [
            'Channel '   => 'Shopify B2B',
            'L-60 Sales' => intval($l60Sales),
            'L30 Sales'  => intval($l30Sales),
            'Growth'     => round($growth, 2) . '%',
            'L60 Orders' => $l60Orders,
            'L30 Orders' => $l30Orders,
            'Qty'        => intval($totalQuantity),
            'Gprofit%'   => round($gProfitPct, 1) . '%',
            'gprofitL60' => round($gprofitL60, 1) . '%',
            'G Roi'      => round($gRoi, 1),
            'G RoiL60'   => round($gRoiL60, 1),
            'Total PFT'  => round($totalProfit, 2),
            'N PFT'      => round($nPft, 1) . '%',
            'N ROI'      => round($nRoi, 1),
            'KW Spent'   => 0,
            'PT Spent'   => 0,
            'HL Spent'   => 0,
            'PMT Spent'  => 0,
            'Shopping Spent' => 0,
            'SERP Spent' => 0,
            'Total Ad Spend' => 0,
            'type'       => $channelData->type ?? '',
            'W/Ads'      => $channelData->w_ads ?? 0,
            'NR'         => $channelData->nr ?? 0,
            'Update'     => $channelData->update ?? 0,
            'cogs'       => round($totalCogs, 2),
            'Map' => $mapMissCounts['map'],
            'Miss' => $mapMissCounts['miss'],
            'NMap' => $mapMissCounts['nmap'],
            'Total Views' => $mapMissCounts['total_views'] ?? 0,
            ...$this->getChannelHealthAndReviewsStub(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'Shopify B2B channel data fetched successfully',
            'data' => $result,
        ]);
    }
    


    /**
     * Store a newly created channel in storage.
     */
    public function store(Request $request)
    {
        // Validate Request Data
        $validatedData = $request->validate([
            'channel' => 'required|string',
            'alias' => 'nullable|string|max:190',
            'promotions' => 'nullable|numeric',
            'compliance_count' => 'nullable|integer',
            'sheet_link' => 'nullable|url',
            'addition_sheet' => 'nullable|url',
            'type' => 'nullable|string',
            'channel_percentage' => 'nullable|numeric',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg,webp|max:2048',
            'seller_link' => 'nullable|url|max:1000',
            // "Update" flag chosen on /all-marketplace-master edit modal. Constrained
            // to the two known values so we never persist arbitrary strings to a
            // column that the table renders verbatim.
            'update' => 'nullable|in:A,S',
            // 'status' => 'required|in:Active,In Active,To Onboard,In Progress',
            // 'executive' => 'nullable|string',
            // 'b_link' => 'nullable|string',
            // 's_link' => 'nullable|string',
            // 'user_id' => 'nullable|string',
            // 'action_req' => 'nullable|string',
        ]);
        // Save Data to Database
        try {
            if (!Schema::hasColumn('channel_master', 'addition_sheet')) {
                unset($validatedData['addition_sheet']);
            }

            // alias column may not exist yet (pre-migration); strip if missing
            if (!Schema::hasColumn('channel_master', 'alias')) {
                unset($validatedData['alias']);
            }

            // promotions column may not exist yet (pre-migration); strip if missing
            if (!Schema::hasColumn('channel_master', 'promotions')) {
                unset($validatedData['promotions']);
            }

            // compliance_count column may not exist yet (pre-migration); strip if missing
            if (!Schema::hasColumn('channel_master', 'compliance_count')) {
                unset($validatedData['compliance_count']);
            }

            // Logo column may not exist yet (pre-migration); strip if missing
            if (!Schema::hasColumn('channel_master', 'logo')) {
                unset($validatedData['logo']);
            }

            // seller_link column may not exist yet (pre-migration); strip if missing
            if (!Schema::hasColumn('channel_master', 'seller_link')) {
                unset($validatedData['seller_link']);
            }

            // Treat empty `update` as NULL so we don't store an empty string
            if (array_key_exists('update', $validatedData) && $validatedData['update'] === '') {
                $validatedData['update'] = null;
            }

            // Handle logo upload (saved under storage/app/public/channel-logos/)
            if ($request->hasFile('logo') && Schema::hasColumn('channel_master', 'logo')) {
                $validatedData['logo'] = $this->storeChannelLogo($request->file('logo'));
            }

            // Set default status to 'Active' if not provided
            if (!isset($validatedData['status'])) {
                $validatedData['status'] = 'Active';
            }
            $channel = ChannelMaster::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'channel saved successfully',
                'data' => $channel
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving channel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save channel. Please try again.'
            ], 500);
        }
    }

    /**
     * Persist an uploaded channel logo and return the public-relative path
     * (e.g. "channel-logos/1716743521_abc.png") suitable to combine with
     * asset('storage/...') on the frontend.
     */
    /**
     * Normalise a channel name to a canonical key so duplicate / aliased names
     * (e.g. "TikTok 2" vs "Tiktok Shop 2") resolve to the same logo / seller link.
     */
    private function canonicalChannelKey(?string $name): string
    {
        $key = strtolower(trim((string) $name));
        $key = preg_replace('/\s+/', ' ', $key);

        $aliases = [
            'tiktok shop 2' => 'tiktok 2',
            'depop.com'     => 'depop',
        ];

        return $aliases[$key] ?? $key;
    }

    /**
     * Return every channel_master name that is an alias of the given channel
     * (lower-cased, used for case/space-insensitive lookups).
     */
    private function channelAliasNames(?string $name): array
    {
        $key = strtolower(trim((string) $name));
        $key = preg_replace('/\s+/', ' ', $key);

        $groups = [
            ['tiktok 2', 'tiktok shop 2'],
        ];

        foreach ($groups as $group) {
            if (in_array($key, $group, true)) {
                return $group;
            }
        }

        return [$key];
    }

    private function storeChannelLogo(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png';
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $file->storeAs('public/channel-logos', $filename);

        return 'channel-logos/' . $filename;
    }

    /**
     * Store a update channel in storage.
     */
    public function update(Request $request)
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg,webp|max:2048',
            'seller_link' => 'nullable|url|max:1000',
            // Allow blank (clears the flag) or one of the two supported tags.
            'update' => 'nullable|in:A,S',
        ]);

        $originalChannel = $request->input('original_channel');
        $updatedChannel = $request->input('channel');
        $alias = $request->input('alias');
        $promotions = $request->input('promotions');
        $complianceCount = $request->input('compliance_count');
        $sheetUrl = $request->input('sheet_url');
        $type = $request->input('type');
        $channelPercentage = $request->input('channel_percentage');
        $base = $request->input('base');
        $target = $request->input('target');
        $missingLink = $request->input('missing_link');
        $additionSheet = $request->input('addition_sheet');
        $sellerLink = $request->input('seller_link');
        $updateFlag = $request->input('update');

        // Prefer the ACTIVE row when duplicate channel names exist, and resolve
        // known aliases (e.g. "TikTok 2" stored as "Tiktok Shop 2") so the logo
        // is saved to the row actually displayed on the page.
        $lookupNames = $this->channelAliasNames($originalChannel);
        $channel = ChannelMaster::query()
            ->whereIn(DB::raw('LOWER(TRIM(channel))'), $lookupNames)
            ->orderByRaw("CASE WHEN LOWER(TRIM(status)) = 'active' THEN 0 ELSE 1 END")
            ->orderBy('id', 'asc')
            ->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found']);
        }

        // Renaming a channel is restricted to a small allow-list of users. Any
        // other user keeps the original name even if a different value was posted.
        $channelRenameAllowed = [
            'support@5core.com',
            'president@5core.com',
            'software5@5core.com',
        ];
        $currentEmail = strtolower(trim((string) (auth()->user()->email ?? '')));
        $isRename = $updatedChannel !== null
            && mb_strtolower(trim((string) $updatedChannel)) !== mb_strtolower(trim((string) $channel->channel));
        if ($isRename && !in_array($currentEmail, $channelRenameAllowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit the channel name.',
            ], 403);
        }

        $channel->channel = $updatedChannel;
        $channel->sheet_link = $sheetUrl;
        $channel->type = $type;
        $channel->channel_percentage = $channelPercentage;
        $channel->base = $base;
        // Target is no longer editable from the UI; only touch it when the
        // request actually sends the field so existing values aren't wiped.
        if ($request->has('target')) {
            $channel->target = $target;
        }
        
        // Save alias if column exists (treat blank as NULL)
        if (Schema::hasColumn('channel_master', 'alias') && $request->has('alias')) {
            $channel->alias = ($alias !== '' && $alias !== null) ? $alias : null;
        }

        // Save manual Promotions % if column exists (treat blank as NULL)
        if (Schema::hasColumn('channel_master', 'promotions') && $request->has('promotions')) {
            $channel->promotions = ($promotions !== '' && $promotions !== null && is_numeric($promotions))
                ? $promotions
                : null;
        }

        // Save manual Compliance Count if column exists (treat blank as NULL)
        if (Schema::hasColumn('channel_master', 'compliance_count') && $request->has('compliance_count')) {
            $channel->compliance_count = ($complianceCount !== '' && $complianceCount !== null && is_numeric($complianceCount))
                ? (int) $complianceCount
                : null;
        }

        // Save missing_link if column exists
        if (Schema::hasColumn('channel_master', 'missing_link')) {
            $channel->missing_link = $missingLink;
        }
        // Addition Sheet is no longer editable from the UI; only touch it when
        // the request actually sends the field so existing values aren't wiped.
        if (Schema::hasColumn('channel_master', 'addition_sheet') && $request->has('addition_sheet')) {
            $channel->addition_sheet = $additionSheet;
        }

        // Save seller_link (the field is always sent from the form, including empty
        // string when the user clears it; treat blank as NULL)
        if (Schema::hasColumn('channel_master', 'seller_link') && $request->has('seller_link')) {
            $channel->seller_link = ($sellerLink !== '' && $sellerLink !== null) ? $sellerLink : null;
        }

        // Persist the "Update" tag (A/S/clear). Only touch the column when the form
        // actually sent the field so older callers that don't post it don't wipe it.
        if (Schema::hasColumn('channel_master', 'update') && $request->has('update')) {
            $channel->update = ($updateFlag === 'A' || $updateFlag === 'S') ? $updateFlag : null;
        }

        // Handle logo upload (replace existing if a new file is provided)
        if ($request->hasFile('logo') && Schema::hasColumn('channel_master', 'logo')) {
            $oldLogo = $channel->logo;
            $channel->logo = $this->storeChannelLogo($request->file('logo'));

            if ($oldLogo) {
                Storage::delete('public/' . ltrim($oldLogo, '/'));
            }
        }
        
        $channel->save();

        MarketplacePercentage::updateOrCreate(
            ['marketplace' => $updatedChannel],
            ['percentage' => number_format((float)$channelPercentage, 2, '.', '')]
        );

        // The page renders out of channel_master_calculated_data (see
        // getViewChannelDataFast). That table is only rebuilt by the hourly
        // channel:calculate-data run, so without this push-through the user would
        // save A/S, reload, and still see the stale cached value for up to an hour.
        // Mirror the freshly saved fields into the cache row for this channel so
        // the next table.setData() reload shows them immediately.
        try {
            $cachePayload = [
                'sheet_link'     => $sheetUrl,
                'type'           => $type,
                'missing_link'   => $missingLink,
            ];
            // Only mirror Target / Addition Sheet into the cache when the form
            // actually sent them (both fields were removed from the UI).
            if ($request->has('target')) {
                $cachePayload['target'] = is_numeric($target) ? (float) $target : null;
            }
            if ($request->has('addition_sheet')) {
                $cachePayload['addition_sheet'] = $additionSheet;
            }
            if (Schema::hasColumn('channel_master_calculated_data', 'update_flag')) {
                $cachePayload['update_flag'] = ($updateFlag === 'A' || $updateFlag === 'S') ? $updateFlag : null;
            }
            // Original lookup is by channel name; if the user also renamed the
            // channel, the cache row keyed on the old name needs to move with it.
            $cacheQuery = \App\Models\ChannelMasterCalculatedData::query()
                ->where('channel', $originalChannel);
            if ($updatedChannel !== $originalChannel) {
                $cachePayload['channel'] = $updatedChannel;
            }
            $cacheQuery->update($cachePayload);
        } catch (\Throwable $e) {
            // Non-fatal: the next channel:calculate-data run will reconcile.
            Log::warning('Channel cache sync after update failed: ' . $e->getMessage(), [
                'channel' => $originalChannel,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Update only channel name and Ad Type (for ADV Masters Edit).
     * Prefer update: lookup by original_channel (case-insensitive). If not found, lookup by new channel (avoid duplicates).
     * Create only when neither exists.
     */
    public function updateNameAndType(Request $request)
    {
        $request->validate([
            'original_channel' => 'required|string',
            'channel' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
        ]);

        $original = trim($request->input('original_channel'));
        $newName = trim($request->input('channel'));
        $type = $request->filled('type') ? trim($request->input('type')) : null;

        $channel = ChannelMaster::whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($original)])->first();

        if (!$channel) {
            $channel = ChannelMaster::whereRaw('LOWER(TRIM(channel)) = ?', [strtolower($newName)])->first();
        }

        if ($channel) {
            $channel->channel = $newName;
            $channel->type = $type;
            $channel->save();
            return response()->json(['success' => true, 'message' => 'Channel updated successfully']);
        }

        ChannelMaster::create([
            'channel' => $newName,
            'type' => $type,
            'status' => 'Active',
        ]);
        return response()->json(['success' => true, 'message' => 'Channel created successfully']);
    }

    public function getChannelCounts()
    {
        // Fetch counts from the database
        $totalChannels = DB::table('channel_master')->count();
        $activeChannels = DB::table('channel_master')->where('status', 'Active')->count();
        $inactiveChannels = DB::table('channel_master')->where('status', 'In Active')->count();
    
        return response()->json([
            'success' => true,
            'totalChannels' => $totalChannels,
            'activeChannels' => $activeChannels,
            'inactiveChannels' => $inactiveChannels,
        ]);
    }

    public function destroy(Request $request)
    {
        // Delete channel from database
    }

    public function sendToGoogleSheet(Request $request)
    {

        $channel = $request->input('channel');
        $checked = $request->input('checked');

        Log::info('Received update-checkbox request', [
            'channel' => $channel,
            'checked' => $checked,
        ]);

        // Log for debugging
        Log::info("Updating GSheet for channel: $channel, checked: " . ($checked ? 'true' : 'false'));

        $url = 'https://script.google.com/macros/s/AKfycbzhlu7KV3dx3PS-9XPFBI9FMgI0JZIAgsuZY48Lchr_60gkSmx1hNAukKwFGZXgPwid/exec'; 

        $response = Http::post($url, [
            'channel' => $channel,
            'checked' => $checked
        ]);

        if ($response->successful()) {
            Log::info("Google Sheet updated successfully");
            return response()->json(['success' => true, 'message' => 'Updated GSheet']);
        } else {
            Log::error('Failed to send to GSheet:', [$response->body()]);
            return response()->json(['success' => false, 'message' => 'Failed to update GSheet'], 500);
        }
    }

    public function updateExecutive(Request $request)
    {
        $channel = trim($request->input('channel'));
        $exec = trim($request->input('exec'));

        $spreadsheetId = '13ZjGtJvSkiLHin2VnkBD-hrGimSRD7duVjILfkoJ2TA';
        $url = 'https://script.google.com/macros/s/AKfycbzYct_htZ_z89S36bPMDdjdDy6s1Nrzm79No6N2PqPriyrwXF1plIschk1c4cDnPYQ5/exec'; // Your Apps Script doPost URL

        $payload = [
            'channel' => $channel,
            'exec' => $exec,
            'action' => 'update_exec'
        ];

        $response = Http::post($url, $payload);

        if ($response->successful()) {
            return response()->json(['message' => 'Executive updated successfully.']);
        } else {
            return response()->json(['message' => 'Failed to update.'], 500);
        }
    }


    public function updateSheetLink(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'sheet_link' => 'nullable|url',
        ]);

        ChannelMaster::updateOrCreate(
            ['channel' => $request->channel], // search by channel
            ['sheet_link' => $request->sheet_link] // update or insert
        );

        return response()->json(['status' => 'success']);
    }

    public function toggleCheckboxFlag(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'field' => 'required|in:nr,w_ads,update',
            'value' => 'required|boolean'
        ]);

        $channelName = trim($request->channel);
        $field = $request->field;
        $value = $request->value;

        $channel = ChannelMaster::whereRaw('LOWER(channel) = ?', [strtolower($channelName)])->first();

        if ($channel) {
            $channel->$field = $value;
            $channel->save();
            return response()->json(['success' => true, 'message' => 'Channel updated.']);
        }

        // Channel not found — insert new row
        $newChannel = new ChannelMaster();
        $newChannel->channel = $channelName;
        $newChannel->$field = $value;
        $newChannel->save();

        return response()->json(['success' => true, 'message' => 'New channel inserted and updated.']);
    }


    public function updateType(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'type' => 'nullable|string'
        ]);

        $channelName = trim($request->input('channel'));
        $type = $request->input('type');

        $channel = ChannelMaster::where('channel', $channelName)->first();

        if (!$channel) {
            // If not found, create new
            $channel = new ChannelMaster();
            $channel->channel = $channelName;
        }

        $channel->type = $type;
        $channel->save();

        return response()->json([
            'success' => true,
            'message' => 'Type updated successfully.'
        ]);
    }

    public function updatePercentage(Request $request)
    {
        $request->validate([
            'channel' => 'required|string',
            'channel_percentage' => 'nullable|numeric'
        ]);

        $channelName = trim($request->input('channel'));
        $channelPercentage = $request->input('channel_percentage');

        $channel = ChannelMaster::where('channel', $channelName)->first();

        if (!$channel) {
            // If not found, create new
            $channel = new ChannelMaster();
            $channel->channel = $channelName;
        }

        $channel->channel_percentage = $channelPercentage;
        $channel->save();

        return response()->json([
            'success' => true,
            'message' => 'Channel percentage updated successfully.'
        ]);
    }


    private function getListedCount($channel)
    {
        $channel = strtolower(trim($channel));

        try {
            switch ($channel) {
                case 'amazon':
                    return app(ListingAmazonController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'ebay':
                    return app(ListingEbayController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'temu':
                    return app(ListingTemuController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'doba':
                    return app(ListingDobaController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'macys':
                    return app(ListingMacysController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'walmart':
                    return app(ListingWalmartController::class)->getNrReqCount()['Listed'] ?? 0;
                
                case 'wayfair':
                    return app(ListingWayfairController::class)->getNrReqCount()['Listed'] ?? 0;
                
                case 'ebay 3':
                    return app(ListingEbayThreeController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shopify b2c':
                    return app(ListingShopifyB2CController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'reverb':
                    return app(ListingReverbController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'aliexpress':
                    return app(ListingAliexpressController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shein':
                    return app(ListingSheinController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'tiktok shop':
                    return app(ListingTiktokShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'shopify wholesale/ds':
                    return app(ListingShopifyWholesaleController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'faire':
                    return app(ListingFaireController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'ebay 2':
                    return app(ListingEbayTwoController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'mercari w ship':
                    return app(ListingMercariWShipController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'newegg b2c':
                    return app(ListingNeweggB2CController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'fb marketplace':
                    return app(ListingFBMarketplaceController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'syncee':
                    return app(ListingSynceeController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'auto ds':
                    return app(ListingAutoDSController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'mercari w/o ship':
                    return app(ListingMercariWoShipController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'business 5core':
                    return app(ListingBusiness5CoreController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'zendrop':
                    return app(ListingZendropController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'poshmark':
                    return app(ListingPoshmarkController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'appscenic':
                    return app(ListingAppscenicController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'tiendamia':
                    return app(ListingTiendamiaController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'spocket':
                    return app(ListingSpocketController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'offerup':
                    return app(ListingOfferupController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'newegg b2b':
                    return app(ListingNeweggB2BController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'fb shop':
                    return app(ListingFBShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'instagram shop':
                    return app(ListingInstagramShopController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'Yamibuy':
                    return app(ListingYamibuyController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'bestbuy usa':
                    return app(ListingBestbuyUSAController::class)->getNrReqCount()['Listed'] ?? 0;

                case 'sw gear exchange':
                    return app(ListingSWGearExchangeController::class)->getNrReqCount()['Listed'] ?? 0;

                default:
                    return 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }
    }


    // public function getSalesTrendData()
    // {
    //     $today = now();
    //     $l30Start = $today->copy()->subDays(30);
    //     $l60Start = $today->copy()->subDays(60);

    //     // Get daily sales for last 60 days
    //     $salesData = DB::connection('apicentral')
    //         ->table('shopify_order_items')
    //         ->select(
    //             DB::raw('DATE(order_date) as date'),
    //             DB::raw('SUM(quantity * price) as total_sales')
    //         )
    //         ->where('order_date', '>=', $l60Start)
    //         ->groupBy(DB::raw('DATE(order_date)'))
    //         ->orderBy('date', 'asc')
    //         ->get();

    //     // Split into two datasets (L30 & L60)
    //     $l30Data = [];
    //     $l60Data = [];

    //     foreach ($salesData as $row) {
    //         $date = Carbon::parse($row->date)->format('Y-m-d');
    //         if ($row->date >= $l30Start->toDateString()) {
    //             $l30Data[$date] = $row->total_sales;
    //         } else {
    //             $l60Data[$date] = $row->total_sales;
    //         }
    //     }

    //     // Prepare consistent date series
    //     $period = new \DatePeriod(
    //         $l60Start,
    //         new \DateInterval('P1D'),
    //         $today
    //     );

    //     $chartData = [];
    //     foreach ($period as $date) {
    //         $formatted = $date->format('Y-m-d');
    //         $chartData[] = [
    //             'date' => $formatted,
    //             'l30_sales' => $l30Data[$formatted] ?? 0,
    //             'l60_sales' => $l60Data[$formatted] ?? 0,
    //         ];
    //     }

    //      // Calculate GPROFIT using Shopify order items + Product Master
    //     $orderItems = DB::connection('apicentral')
    //         ->table('shopify_order_items')
    //         ->select('sku', 'quantity', 'price', 'order_date')
    //         ->where('order_date', '>=', $l60Start)
    //         ->get();

    //     if ($orderItems->isEmpty()) {
    //         foreach ($chartData as &$row) {
    //             $row['gprofit'] = 0;
    //         }
    //         return response()->json(['chartData' => $chartData]);
    //     }

    //     // Load product_master LP & SHIP
    //     $productMasters = ProductMaster::all()->keyBy(fn($item) => strtoupper($item->sku));

    //     $totalSalesL30 = 0;
    //     $totalProfitL30 = 0;

    //     foreach ($orderItems as $item) {
    //         $sku = strtoupper(trim($item->sku));
    //         $price = (float) $item->price;
    //         $qty = (int) $item->quantity;

    //         // Only count L30 for profit (recent 30 days)
    //         if ($item->order_date < $l30Start->toDateString()) {
    //             continue;
    //         }

    //         $lp = 0;
    //         $ship = 0;

    //         if (isset($productMasters[$sku])) {
    //             $pm = $productMasters[$sku];
    //             $values = is_array($pm->Values)
    //                 ? $pm->Values
    //                 : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);

    //             $lp = $values['lp'] ?? $pm->lp ?? 0;
    //             $ship = $values['ship'] ?? $pm->ship ?? 0;
    //         }

    //         $sales = $qty * $price;
    //         $profit = ($price - $lp - $ship) * $qty;

    //         $totalSalesL30 += $sales;
    //         $totalProfitL30 += $profit;
    //     }

    //     $gProfitPct = $totalSalesL30 > 0 ? ($totalProfitL30 / $totalSalesL30) * 100 : 0;

    //     // Add GProfit% (flat line or future extension: date-wise)
    //     foreach ($chartData as &$row) {
    //         $row['gprofit'] = round($gProfitPct, 2);
    //     }

    //     return response()->json([
    //         'chartData' => $chartData,
    //         'summary' => [
    //             'total_sales_l30' => round($totalSalesL30, 2),
    //             'total_profit_l30' => round($totalProfitL30, 2),
    //             'gprofit' => round($gProfitPct, 2),
    //         ],
    //     ]);

    //     // return response()->json(['chartData' => $chartData]);
    // }


    public function getSalesTrendData()
    {
        $today = now();
        $l30Start = $today->copy()->subDays(30);
        $l60Start = $today->copy()->subDays(60);

        // Get daily sales for last 60 days
        $salesData = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select(
                DB::raw('DATE(order_date) as date'),
                DB::raw('SUM(quantity * price) as total_sales')
            )
            ->where('order_date', '>=', $l60Start)
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date', 'asc')
            ->get();

        // Split into two datasets (L30 & L60)
        $l30Data = [];
        $l60Data = [];
        foreach ($salesData as $row) {
            $date = Carbon::parse($row->date)->format('Y-m-d');
            if ($row->date >= $l30Start->toDateString()) {
                $l30Data[$date] = $row->total_sales;
            } else {
                $l60Data[$date] = $row->total_sales;
            }
        }

        // Prepare consistent date series
        $period = new \DatePeriod(
            $l60Start,
            new \DateInterval('P1D'),
            $today
        );

        $chartData = [];
        foreach ($period as $date) {
            $formatted = $date->format('Y-m-d');
            $chartData[$formatted] = [
                'date' => $formatted,
                'l30_sales' => $l30Data[$formatted] ?? 0,
                'l60_sales' => $l60Data[$formatted] ?? 0,
                'gprofit' => 0, // initialize
            ];
        }

        // Load product_master LP & SHIP
        $productMasters = ProductMaster::all()->keyBy(fn($item) => strtoupper($item->sku));

        // Get order items for last 30 days (L30)
        $orderItems = DB::connection('apicentral')
            ->table('shopify_order_items')
            ->select('sku', 'quantity', 'price', 'order_date')
            ->where('order_date', '>=', $l30Start)
            ->get();

        if ($orderItems->isNotEmpty()) {
            $dailySales = [];
            $dailyProfit = [];

            foreach ($orderItems as $item) {
                $sku = strtoupper(trim($item->sku));
                $date = Carbon::parse($item->order_date)->format('Y-m-d');
                $qty = (int) $item->quantity;
                $price = (float) $item->price;

                $lp = 0;
                $ship = 0;
                if (isset($productMasters[$sku])) {
                    $pm = $productMasters[$sku];
                    $values = is_array($pm->Values)
                        ? $pm->Values
                        : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                    $lp = $values['lp'] ?? $pm->lp ?? 0;
                    $ship = $values['ship'] ?? $pm->ship ?? 0;
                }

                $sales = $qty * $price;
                $profit = ($price - $lp - $ship) * $qty;

                $dailySales[$date] = ($dailySales[$date] ?? 0) + $sales;
                $dailyProfit[$date] = ($dailyProfit[$date] ?? 0) + $profit;
            }

            // Assign GProfit per day
            foreach ($chartData as $date => &$row) {
                $sales = $dailySales[$date] ?? 0;
                $profit = $dailyProfit[$date] ?? 0;
                $row['gprofit'] = $sales > 0 ? round(($profit / $sales) * 100, 2) : 0;
            }
        }

        // Convert chartData to indexed array for JSON
        $chartData = array_values($chartData);

        // Optional: summary for total L30
        $totalSalesL30 = array_sum($dailySales ?? []);
        $totalProfitL30 = array_sum($dailyProfit ?? []);
        $totalGProfit = $totalSalesL30 > 0 ? ($totalProfitL30 / $totalSalesL30) * 100 : 0;

        return response()->json([
            'chartData' => $chartData,
            'summary' => [
                'total_sales_l30' => round($totalSalesL30, 2),
                'total_profit_l30' => round($totalProfitL30, 2),
                'gprofit' => round($totalGProfit, 2),
            ],
        ]);
    }

    /**
     * Get Dashboard Metrics: Total Sales, Profit Margin, and ROI
     * Uses the same data source and calculation as channel-masters page
     */
    public function getDashboardMetrics(Request $request)
    {
        try {
            // Get the same channel data that channel-masters page uses
            $response = $this->getViewChannelData($request);
            $data = $response->getData(true);

            if ($data['status'] !== 200 || empty($data['data'])) {
                Log::warning('getDashboardMetrics: No channel data found');
                return $this->getFallbackMetrics();
            }

            $channelData = $data['data'];
            
            // Parse numbers helper
            $parseNumber = function($value) {
                if (is_null($value)) return 0;
                if (is_numeric($value)) return floatval($value);
                // Remove commas and other formatting
                $cleaned = str_replace([',', '$', '%'], '', (string)$value);
                return floatval($cleaned) ?: 0;
            };

            // Calculate totals same way as updateAllTotals in channel-masters.blade.php
            $totalL30Sales = 0;
            $totalPft = 0;
            $totalCogs = 0;
            $dataFound = false;

            foreach ($channelData as $row) {
                $l30Sales = $parseNumber($row['L30 Sales'] ?? $row['l30_sales'] ?? 0);
                $gprofitPercent = $parseNumber($row['Gprofit%'] ?? $row['gprofit_percentage'] ?? 0);
                $cogs = $parseNumber($row['cogs'] ?? 0);

                // Convert % → absolute profit amount for this row
                $profitAmount = ($gprofitPercent / 100) * $l30Sales;

                $totalPft += $profitAmount;
                $totalL30Sales += $l30Sales;
                $totalCogs += $cogs;
                
                if ($l30Sales > 0) {
                    $dataFound = true;
                }
            }

            // Calculate metrics same way
            $gProfit = $totalL30Sales !== 0 ? ($totalPft / $totalL30Sales) * 100 : 0;
            $gRoi = $totalCogs !== 0 ? ($totalPft / $totalCogs) * 100 : 0;

            // Ensure valid numbers
            if (!is_finite($gProfit)) $gProfit = 0;
            if (!is_finite($gRoi)) $gRoi = 0;

            Log::info('Dashboard Metrics calculated from channel data', [
                'channels_count' => count($channelData),
                'total_sales' => $totalL30Sales,
                'profit_amount' => $totalPft,
                'profit_margin' => $gProfit,
                'roi' => $gRoi,
                'data_found' => $dataFound,
            ]);

            // Always return the actual calculated data from channels (even if zeros)
            // Don't use fallback data - return what we actually calculated
            $shopifyInvLp = $this->getShopifyInvLpMetrics();

            return response()->json([
                'status' => 200,
                'data' => [
                    'total_sales' => round($totalL30Sales, 2),
                    'profit_margin' => round($gProfit, 2),
                    'roi' => round($gRoi, 2),
                    'total_profit' => round($totalPft, 2),
                    'total_cogs' => round($totalCogs, 2),
                    'currency' => 'USD',
                    'period' => 'L30 (Last 30 Days)',
                    'data_found' => $dataFound,
                    'channels_count' => count($channelData),
                    'shopify_inv_sum' => $shopifyInvLp['inv_sum'],
                    'shopify_weighted_avg_lp' => $shopifyInvLp['weighted_avg_lp'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('getDashboardMetrics error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return $this->getFallbackMetrics();
        }
    }

    /**
     * Get Faire Missing Listing count
     */
    private function getFaireMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = FaireListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get FB Shop Missing Listing count
     */
    private function getFBShopMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = FBShopListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get Business 5Core Missing Listing count
     */
    private function getBusiness5CoreMissingListingCount()
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->toArray();

        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $statusData = Business5CoreListingStatus::whereIn('sku', $skus)->get()->keyBy('sku');

        $missingCount = 0;

        foreach ($productMasters as $item) {
            $sku = trim($item->sku);
            $isParent = stripos($sku, 'PARENT') !== false;
            $inv = $shopifyData[$sku]->inv ?? 0;

            if ($isParent || floatval($inv) <= 0) continue;

            $status = $statusData[$sku]->value ?? null;
            if (is_string($status)) {
                $status = json_decode($status, true);
            }

            $nrReq = $status['nr_req'] ?? 'REQ';
            $listed = $status['listed'] ?? null;

            if ($listed !== 'Listed' && $nrReq !== 'NR') {
                $missingCount++;
            }
        }

        return $missingCount;
    }

    /**
     * Get Stock Mapping not matching stats for all channels
     * Returns array with channel key => not matching count
     */
    private function getStockMappingStats()
    {
        static $cachedStats = null;
        
        if ($cachedStats !== null) {
            return $cachedStats;
        }

        $data = collect();
        ProductStockMapping::chunk(500, function ($items) use (&$data) {
            foreach ($items as $item) {
                if (!$data->has($item->sku)) {
                    $item->inventory_pls = null;
                    $item->inventory_business5core = null;
                    $data->put($item->sku, $item);
                }
            }
        });

        $skus = $data->pluck('sku')->filter()->unique()->toArray();

        $marketplaceModels = [
            'amazon' => AmazonListingStatus::class,
            'walmart' => WalmartListingStatus::class,
            'reverb' => ReverbListingStatus::class,
            'shein' => SheinListingStatus::class,
            'doba' => DobaListingStatus::class,
            'temu' => TemuListingStatus::class,
            'macy' => MacysListingStatus::class,
            'ebay1' => EbayListingStatus::class,
            'ebay2' => EbayTwoListingStatus::class,
            'ebay3' => EbayThreeListingStatus::class,
            'bestbuy' => BestbuyUSAListingStatus::class,
            'tiendamia' => TiendamiaListingStatus::class,
            'pls' => PlsListingStatus::class,
            'business5core' => Business5CoreListingStatus::class,
        ];

        foreach ($marketplaceModels as $key => $model) {
            $inventoryField = 'inventory_' . $key;
            $model::whereIn('sku', $skus)->chunk(500, function ($allListings) use (&$data, $inventoryField) {
                foreach ($allListings as $listing) {
                    $sku = $listing->sku ?? '';
                    $sku = str_replace("\u{00A0}", ' ', $sku);
                    $sku = trim(preg_replace('/\s+/', ' ', $sku));
                    if (isset($data[$sku])) {
                        $inventory = \Illuminate\Support\Arr::get($listing->value ?? [], 'inventory', null);
                        if ($inventory === 'Not Listed' || $inventory === 'NRL') {
                            $data[$sku]->$inventoryField = $inventory;
                        } elseif (is_numeric($inventory)) {
                            $data[$sku]->$inventoryField = (int)$inventory;
                        }
                    }
                }
            });
        }

        foreach ($data as $item) {
            if (!isset($item->inventory_pls) || $item->inventory_pls === null) {
                $item->inventory_pls = $item->inventory_shopify ?? 0;
            }
            if (!isset($item->inventory_business5core) || $item->inventory_business5core === null) {
                $item->inventory_business5core = $item->inventory_shopify ?? 0;
            }
        }

        $platforms = ['amazon', 'walmart', 'reverb', 'shein', 'doba', 'temu', 'macy', 'ebay1', 'ebay2', 'ebay3', 'bestbuy', 'tiendamia', 'pls', 'business5core'];

        $info = [];
        foreach ($platforms as $platform) {
            $info[$platform] = 0;
        }

        foreach ($data as $item) {
            $shopifyInventoryRaw = $item->inventory_shopify ?? 0;
            $shopifyInventory = is_numeric($shopifyInventoryRaw) ? (int)$shopifyInventoryRaw : 0;
            if ($shopifyInventory < 0) $shopifyInventory = 0;

            foreach ($platforms as $platform) {
                $fieldName = 'inventory_' . $platform;
                $platformInventoryRaw = $item->$fieldName ?? null;
                $platformInventory = is_numeric($platformInventoryRaw) ? (int)$platformInventoryRaw : 0;

                if (in_array($platformInventoryRaw, ['Not Listed', 'NRL'], true)) continue;
                if ($platformInventory === 0 && $shopifyInventory === 0) continue;

                $difference = abs($platformInventory - $shopifyInventory);
                if ($difference > 3) {
                    $info[$platform]++;
                }
            }
        }

        $cachedStats = $info;
        return $cachedStats;
    }

    /**
     * Return fallback/sample data
     */
    private function getFallbackMetrics()
    {
        $shopifyInvLp = $this->getShopifyInvLpMetrics();

        return response()->json([
            'status' => 200,
            'data' => [
                'total_sales' => 56200,
                'profit_margin' => 75.65,
                'roi' => 68.5,
                'total_profit' => 42500,
                'total_cogs' => 13700,
                'currency' => 'USD',
                'period' => 'L30 (Last 30 Days)',
                'data_found' => false,
                'channels_count' => 0,
                'shopify_inv_sum' => $shopifyInvLp['inv_sum'],
                'shopify_weighted_avg_lp' => $shopifyInvLp['weighted_avg_lp'],
            ],
            'message' => 'Using sample data',
        ], 200);
    }

    /**
     * Get list of campaign names currently in Google Sheet (L30 data only)
     * This ensures we only sum data that exists in the current Google Sheet
     */
    private function getCurrentGoogleSheetCampaigns()
    {
        try {
            $url = "https://script.google.com/macros/s/AKfycbxWwC98yCcPDcXjXfKpbE0dMC74L0YfF0fx2HdG_i3G7BzSjuhD8H9X98byGQymFNbx/exec";
            
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            
            if (!$response->ok()) {
                Log::warning('Failed to fetch current Google Sheet data for totals calculation');
                return [];
            }
            
            $json = $response->json();
            
            // Get L30 data only
            if (!isset($json['L30']['data'])) {
                return [];
            }
            
            // Extract unique campaign names from current Google Sheet
            $campaignNames = [];
            foreach ($json['L30']['data'] as $row) {
                $campaignName = $row['campaign_name'] ?? null;
                if ($campaignName && !empty(trim($campaignName))) {
                    $campaignNames[] = trim($campaignName);
                }
            }
            
            return array_unique($campaignNames);
        } catch (\Exception $e) {
            Log::error('Error fetching current Google Sheet campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Channel history for modal: same inclusive Pacific dates as Amazon Daily Sales (through yesterday).
     */
    public function getChannelHistory($channel)
    {
        try {
            $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
            $windowDays = AmazonSalesController::DAILY_SALES_WINDOW_DAYS;
            $start = $yesterdayPacific->copy()->subDays($windowDays - 1)->toDateString();
            $end = $yesterdayPacific->toDateString();

            $history = \App\Models\ChannelMasterSummary::where('channel', $channel)
                ->whereBetween('snapshot_date', [$start, $end])
                ->orderBy('snapshot_date', 'desc')
                ->get();
            
            return response()->json([
                'status' => 200,
                'message' => 'History fetched successfully',
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching channel history: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching history',
            ], 500);
        }
    }

    /**
     * Get channel metric chart data from ChannelMasterSummary snapshots
     */
    public function getChannelMetricChartData(Request $request)
    {
        try {
            $channel = strtolower(str_replace([' ', '-', '&', '/'], '', trim($request->input('channel', ''))));
            $metric = $request->input('metric', 'l30_sales');
            $days = intval($request->input('days', 32));

            if (!$channel) {
                return response()->json(['success' => false, 'message' => 'Channel is required'], 400);
            }

            // Map frontend metric names to summary_data keys
            $metricMap = [
                'l60_sales' => 'l60_sales',
                'l60_orders' => 'l60_orders',
                'l30_sales' => 'l30_sales',
                // Yesterday's sales (snapshot was added later; older days will lack this key
                // and are skipped below so the chart only shows real Y Sales history).
                'y_sales' => 'y_sales',
                'l30_orders' => 'l30_orders',
                'qty' => 'total_quantity',
                'gprofit' => 'gprofit_percent',
                'groi' => 'groi_percent',
                'ads_pct' => 'tcos_percent',
                'pft' => null,       // computed: net profit $ = (gprofit%/100)*l30_sales - total_ad_spend
                'npft' => 'npft_percent',
                'nroi' => null,      // computed: npft / tcos * 100
                'missing_l' => 'miss_count',
                'nmap' => 'nmap_count',
                // Snapshot stores Map under 'map_count'; without this entry the chart
                // would look up summary_data['map'] (does not exist) and graph zeros.
                'map' => 'map_count',
                'ad_spend' => 'total_ad_spend',
                'clicks' => 'clicks',
                'ad_sales' => null,  // uses ad_sales with l30_sales ratio fallback
                'ad_sold' => null,   // uses ad_sold with clicks × cvr ratio fallback
                'acos' => null,      // computed: (ad_spend / ad_sales) * 100
                'ads_cvr' => null,   // computed: (ad_sold / clicks) * 100
                'cvr' => null,      // computed: (total_quantity / total_views) * 100 — units-based, matches /temu-decrease
                'total_views' => 'total_views',
                'inv_at_lp' => 'inv_at_lp',
                'tat' => null,  // computed: inventory_value_amazon / total l30_sales (all only)
            ];

            $metricKey = $metricMap[$metric] ?? $metric;
            $isAll = ($channel === 'all');

            if ($metric === 'tat' && !$isAll) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Metrics that should be averaged (percentages) vs summed (counts/amounts)
            $avgMetrics = ['gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'acos', 'ads_cvr', 'cvr'];
            $shouldAvg = in_array($metric, $avgMetrics);

            // Determine date range. Today's California snapshot IS included so the chart's
            // last point matches the live table value (which is also "today, in progress").
            // The day-over-day dot color (see getChannelMetricDotTrends) still uses only
            // completed PT days, so an in-progress today doesn't flatten that signal.
            $query = \App\Models\ChannelMasterSummary::orderBy('snapshot_date', 'asc');

            if (!$isAll) {
                $query->where('channel', $channel);
            }

            if ($days > 0) {
                $startDate = now('America/Los_Angeles')->subDays($days)->toDateString();
                $query->where('snapshot_date', '>=', $startDate);
            }

            $history = $query->get();

            if ($history->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Group by snapshot_date for "all" aggregation.
            // Snapshots are saved with America/Los_Angeles dates (see saveChannelDailySummaries),
            // so we parse and format in that zone to ensure the chart's X-axis labels (Jun 14,
            // Jun 15, …) are California/Pacific dates regardless of the app/server timezone.
            $grouped = $history->groupBy(function ($row) {
                return Carbon::parse($row->snapshot_date, 'America/Los_Angeles')->format('Y-m-d');
            })->sortKeys();

            $chartData = [];
            foreach ($grouped as $dateKey => $rows) {
                $date = Carbon::parse($dateKey, 'America/Los_Angeles')->format('M d');

                if ($isAll) {
                    // Aggregate across all channels for this date
                    $totalVal = 0;
                    $totalSpend = 0;
                    $totalSales = 0;
                    $totalAdSales = 0;
                    $totalAdSold = 0;
                    $totalClicks = 0;
                    $totalCogs = 0;
                    $totalPft = 0;
                    $totalNpft = 0;
                    $totalTcos = 0;
                    $totalInvAmazon = 0;
                    $totalQtyCvr = 0;
                    $totalViewsCvr = 0;
                    $count = 0;
                    // For metrics whose snapshot key was added later (e.g. y_sales), track whether
                    // any of this day's per-channel snapshots actually carries the field. If none
                    // do, skip the day so the chart doesn't render a flat-zero history.
                    $hasMetricData = false;

                    foreach ($rows as $row) {
                        $sd = $row->summary_data ?? [];
                        $count++;
                        if ($metricKey !== null && is_array($sd) && array_key_exists($metricKey, $sd)) {
                            $hasMetricData = true;
                        }

                        $channelL30Sales = floatval($sd['l30_sales'] ?? 0);
                        $channelAdSpend = floatval($sd['total_ad_spend'] ?? 0);
                        $channelGprofit = floatval($sd['gprofit_percent'] ?? 0);

                        $channelCogs = floatval($sd['cogs'] ?? 0);
                        // Gross profit $ from gprofit%; net NPFT $ subtracts ad spend (matches badge)
                        $channelPft = ($channelGprofit / 100) * $channelL30Sales;

                        if ($metric === 'acos' || $metric === 'ad_sales') {
                            $totalSpend += $channelAdSpend;
                            $adSales = floatval($sd['ad_sales'] ?? 0);
                            $totalAdSales += $adSales > 0 ? $adSales : ($channelL30Sales * 0.5);
                        } elseif ($metric === 'ads_cvr' || $metric === 'ad_sold') {
                            $totalAdSold += floatval($sd['ad_sold'] ?? 0);
                            $totalClicks += floatval($sd['clicks'] ?? 0);
                        } elseif ($metric === 'cvr') {
                            // Units-based listing CVR — matches /temu-decrease (qty / views).
                            // Falls back to l30_orders for snapshots saved before total_quantity
                            // was persisted, so older days don't suddenly read zero on the chart.
                            $qtyForCvr = floatval($sd['total_quantity'] ?? 0);
                            if ($qtyForCvr <= 0) {
                                $qtyForCvr = floatval($sd['l30_orders'] ?? 0);
                            }
                            $totalQtyCvr += $qtyForCvr;
                            $totalViewsCvr += floatval($sd['total_views'] ?? 0);
                        } elseif ($metric === 'gprofit' || $metric === 'npft' || $metric === 'pft') {
                            $totalPft += $channelPft;
                            $totalSales += $channelL30Sales;
                            $totalSpend += $channelAdSpend;
                        } elseif ($metric === 'groi' || $metric === 'nroi') {
                            $totalPft += $channelPft;
                            $totalCogs += $channelCogs;
                            $totalSpend += $channelAdSpend;
                            $totalSales += $channelL30Sales;
                        } elseif ($metric === 'ads_pct') {
                            $totalSpend += $channelAdSpend;
                            $totalSales += $channelL30Sales;
                        } elseif ($metric === 'tat') {
                            $totalInvAmazon += floatval($sd['inventory_value_amazon'] ?? 0);
                            $totalSales += $channelL30Sales;
                        } elseif ($shouldAvg) {
                            $totalVal += floatval($sd[$metricKey] ?? 0);
                        } else {
                            $totalVal += floatval($sd[$metricKey] ?? 0);
                        }
                    }

                    if ($metric === 'tat') {
                        $value = $totalSales > 0 ? round($totalInvAmazon / $totalSales, 2) : 0;
                    } elseif ($metric === 'acos') {
                        $value = $totalAdSales > 0 ? round(($totalSpend / $totalAdSales) * 100, 1) : 0;
                    } elseif ($metric === 'ad_sales') {
                        $value = round($totalAdSales, 2);
                    } elseif ($metric === 'ads_cvr') {
                        $value = $totalClicks > 0 ? round(($totalAdSold / $totalClicks) * 100, 1) : 0;
                    } elseif ($metric === 'cvr') {
                        // Units-based: Σ qty / Σ views — matches /temu-decrease badge formula.
                        // 2 decimals: rolling-window CVR moves <0.05% per day, so 1-decimal
                        // rounding collapsed multiple consecutive days into the same value
                        // and the trend looked flat for ~3 days at a time.
                        $value = $totalViewsCvr > 0 ? round(($totalQtyCvr / $totalViewsCvr) * 100, 2) : 0;
                    } elseif ($metric === 'ad_sold') {
                        $value = round($totalAdSold);
                    } elseif ($metric === 'gprofit') {
                        // Weighted avg: total profit / total sales * 100
                        $value = $totalSales > 0 ? round(($totalPft / $totalSales) * 100, 1) : 0;
                    } elseif ($metric === 'pft') {
                        // Net profit $ (gross from G%×sales, minus ad spend)
                        $value = round($totalPft - $totalSpend, 2);
                    } elseif ($metric === 'npft') {
                        // N PFT = G PFT% - Ads%
                        $gpft = $totalSales > 0 ? ($totalPft / $totalSales) * 100 : 0;
                        $adsPct = $totalSales > 0 ? ($totalSpend / $totalSales) * 100 : 0;
                        $value = round($gpft - $adsPct, 1);
                    } elseif ($metric === 'groi') {
                        // G ROI = total profit / total cogs * 100
                        $value = $totalCogs > 0 ? round(($totalPft / $totalCogs) * 100, 1) : 0;
                    } elseif ($metric === 'nroi') {
                        // N ROI must match the page badge formula:
                        //   (Σ gross profit $ − Σ ad spend $) / Σ COGS × 100
                        // Previously this used GROI% − TCOS%, which divides ad spend by sales
                        // instead of by cogs, so chart history did not match the badge value.
                        $value = $totalCogs > 0 ? round((($totalPft - $totalSpend) / $totalCogs) * 100, 1) : 0;
                    } elseif ($metric === 'ads_pct') {
                        $value = $totalSales > 0 ? round(($totalSpend / $totalSales) * 100, 1) : 0;
                    } elseif ($metric === 'y_sales') {
                        // Skip days that pre-date the y_sales snapshot field so we don't
                        // draw a long flat-zero line before real history begins.
                        if (!$hasMetricData) continue;
                        $value = round($totalVal, 2);
                    } elseif ($shouldAvg && $count > 0) {
                        $value = round($totalVal / $count, 1);
                    } else {
                        $value = round($totalVal, 2);
                    }
                } else {
                    // Single channel
                    $row = $rows->first();
                    $summaryData = $row->summary_data ?? [];

                    if ($metric === 'acos') {
                        // Always use ratio approach: ad_spend / (l30_sales × ratio)
                        // This gives consistent trend because ratio is fixed from latest date
                        $spend = floatval($summaryData['total_ad_spend'] ?? 0);
                        $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
                        $adSales = $l30Sales * $this->getAdSalesRatio($channel);
                        $value = $adSales > 0 ? round(($spend / $adSales) * 100, 1) : 0;
                    } elseif ($metric === 'ad_sales') {
                        $adSales = floatval($summaryData['ad_sales'] ?? 0);
                        if ($adSales <= 0) {
                            $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
                            $adSales = $l30Sales * $this->getAdSalesRatio($channel);
                        }
                        $value = round($adSales, 2);
                    } elseif ($metric === 'ad_sold') {
                        $adSold = floatval($summaryData['ad_sold'] ?? 0);
                        if ($adSold <= 0) {
                            $clicks = floatval($summaryData['clicks'] ?? 0);
                            $adSold = $clicks * $this->getAdCvrRatio($channel);
                        }
                        $value = round($adSold);
                    } elseif ($metric === 'ads_cvr') {
                        $adSold = floatval($summaryData['ad_sold'] ?? 0);
                        $clicks = floatval($summaryData['clicks'] ?? 0);
                        if ($adSold <= 0 && $clicks > 0) {
                            $adSold = $clicks * $this->getAdCvrRatio($channel);
                        }
                        $value = $clicks > 0 ? round(($adSold / $clicks) * 100, 1) : 0;
                    } elseif ($metric === 'cvr') {
                        // Units-based: qty / views — matches /temu-decrease badge formula.
                        // Falls back to l30_orders for older snapshots that pre-date the
                        // total_quantity field, otherwise the chart would graph zero for those days.
                        $qty = floatval($summaryData['total_quantity'] ?? 0);
                        if ($qty <= 0) {
                            $qty = floatval($summaryData['l30_orders'] ?? 0);
                        }
                        $views = floatval($summaryData['total_views'] ?? 0);
                        // 2 decimals: rolling-window CVR moves <0.05% per day, so 1-decimal
                        // rounding collapsed multiple consecutive days into the same value
                        // and the trend looked flat for ~3 days at a time.
                        $value = $views > 0 ? round(($qty / $views) * 100, 2) : 0;
                    } elseif ($metric === 'pft') {
                        $gprofitPercent = floatval($summaryData['gprofit_percent'] ?? 0);
                        $sales = floatval($summaryData['l30_sales'] ?? 0);
                        $adSpend = floatval($summaryData['total_ad_spend'] ?? 0);
                        $value = round(($gprofitPercent / 100) * $sales - $adSpend, 2);
                    } elseif ($metric === 'nroi') {
                        // N ROI = GROI% - TCOS%
                        $groi = floatval($summaryData['groi_percent'] ?? 0);
                        $tcos = floatval($summaryData['tcos_percent'] ?? 0);
                        $value = round($groi - $tcos, 1);
                    } else {
                        $value = floatval($summaryData[$metricKey] ?? 0);
                    }
                }

                $chartData[] = [
                    'date' => $date,
                    'value' => $value,
                ];
            }

            // For single-channel: show exact DB values (no scaling) so graph matches table data.
            // For "all" channels: scale to match badge total if needed.
            // Never scale listing CVR: it is a ratio (Σ qty / Σ views); uniform scaling would distort history.
            if (!empty($chartData) && $isAll && $metric !== 'cvr') {
                $tableRef = $this->getAllChannelsTableReference($metric);
                if ($tableRef !== null && $tableRef != 0) {
                    $chartLatest = (float) end($chartData)['value'];
                    if ($chartLatest != 0 && abs($chartLatest - $tableRef) > 0.01) {
                        $sf = $tableRef / $chartLatest;
                        foreach ($chartData as &$pt) {
                            $pt['value'] = round($pt['value'] * $sf, 2);
                        }
                        unset($pt);
                    }
                }
            }

            // Do NOT interpolate channel-metric chart: values are exact snapshots from DB.
            // Interpolation would replace same-value points (e.g. 88630, 88630) with smoothed
            // values and make the graph show incorrect numbers vs table.

            return response()->json([
                'success' => true,
                'data' => $chartData,
            ]);

        } catch (\Exception $e) {
            \Log::error('getChannelMetricChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Return last-two values per channel per metric for table dot colors (red/green/gray).
     * Uses the same source and methodology as the chart: ChannelMasterSummary only, normalized channel key,
     * same metricMap and value extraction as getChannelMetricChartData (single-channel).
     */
    public function getChannelMetricDotTrends(Request $request)
    {
        try {
            $channelsParam = $request->input('channels', '');
            // Same normalization as chart and table save: normalized key (temu, amazon, etc.)
            $channelKeys = array_filter(array_map(function ($c) {
                return strtolower(str_replace([' ', '-', '&', '/'], '', trim($c)));
            }, explode(',', $channelsParam)));

            if (empty($channelKeys)) {
                return response()->json(['success' => true, 'channels' => (object)[]]);
            }

            // Same metricMap as getChannelMetricChartData so value extraction matches chart exactly
            $metricMap = [
                'l60_sales' => 'l60_sales',
                'l60_orders' => 'l60_orders',
                'l30_sales' => 'l30_sales',
                'y_sales' => 'y_sales',
                'l30_orders' => 'l30_orders',
                'qty' => 'total_quantity',
                'gprofit' => 'gprofit_percent',
                'groi' => 'groi_percent',
                'ads_pct' => 'tcos_percent',
                'npft' => 'npft_percent',
                'missing_l' => 'miss_count',
                'nmap' => 'nmap_count',
                // Snapshot stores Map under 'map_count' (see metricMap in getChannelMetricChartData).
                'map' => 'map_count',
                'ad_spend' => 'total_ad_spend',
                'clicks' => 'clicks',
                'total_views' => 'total_views',
                'inv_at_lp' => 'inv_at_lp',
            ];
            $metrics = ['missing_l', 'nmap', 'l60_sales', 'l60_orders', 'l30_sales', 'y_sales', 'ad_spend', 'l30_orders', 'qty', 'gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'clicks', 'ad_sales', 'ad_sold', 'acos', 'ads_cvr', 'cvr', 'total_views', 'inv_at_lp'];
            $out = [];

            // Today's snapshot is included in the comparison: saveChannelDailySummaries
            // overwrites today's row on every page load, so it always reflects the
            // freshest values (including post-upload metrics like total_ad_spend that
            // come from a single L30/L7 file). The previous behaviour excluded today
            // to avoid "today partial vs yesterday complete" red herrings, but that
            // also meant the dot wouldn't move at all after an upload until *tomorrow's*
            // snapshot replaced today as the latest.
            //
            // For the "older" baseline, walk back the snapshot history per metric until
            // we find a value that is meaningfully different from today's value, instead
            // of blindly using yesterday's snapshot. This is required for channels
            // (e.g. eBay 1, eBay 2) whose L30 sales / qty / profit come from a once-a-day
            // marketplace_daily_metrics cron — between cron runs the source value is
            // frozen, so today's saved snapshot can equal yesterday's, which used to make
            // the trend dot grey for the entire afternoon/evening even though the metric
            // is genuinely trending vs the last day it actually changed. We pull a
            // 30-snapshot window (a month is plenty: any longer flat run is itself a
            // meaningful "no trend") and pick the most-recent prior snapshot whose value
            // differs from today's.
            $snapshotWindow = 30;
            foreach ($channelKeys as $channel) {
                foreach ($metrics as $metric) {
                    $out[$channel][$metric] = [null, null];
                }

                // Same source as chart: ChannelMasterSummary. Same key: normalized channel (table saves with this key in saveChannelDailySummaries).
                $cmsRows = \App\Models\ChannelMasterSummary::where('channel', $channel)
                    ->orderBy('snapshot_date', 'desc')
                    ->take($snapshotWindow)
                    ->get();

                if ($cmsRows->count() >= 2) {
                    $newerSd = $cmsRows->get(0)->summary_data ?? [];
                    foreach ($metrics as $metric) {
                        $v2 = $this->getMetricValueFromSummaryData($channel, $metric, $newerSd, $metricMap);
                        if ($v2 === null) continue;

                        // Walk back until we find a snapshot whose metric value differs
                        // from $v2 (within rounding tolerance). If every prior snapshot
                        // matches exactly, fall back to the immediate prior value so we
                        // still emit a [v, v] pair (which renders as a grey "no change"
                        // dot — same as before for genuinely-flat metrics).
                        $v1 = $this->getMetricValueFromSummaryData(
                            $channel,
                            $metric,
                            $cmsRows->get(1)->summary_data ?? [],
                            $metricMap
                        );
                        for ($i = 1; $i < $cmsRows->count(); $i++) {
                            $candidate = $this->getMetricValueFromSummaryData(
                                $channel,
                                $metric,
                                $cmsRows->get($i)->summary_data ?? [],
                                $metricMap
                            );
                            if ($candidate === null) continue;
                            // Use the same equality check the frontend uses (===) but with
                            // a tiny epsilon to ignore float-rounding noise from snapshot
                            // round-trips (e.g. 7.51 → "7.51" → 7.51).
                            if (abs((float)$candidate - (float)$v2) > 0.0001) {
                                $v1 = $candidate;
                                break;
                            }
                        }

                        $out[$channel][$metric] = [$v1, $v2];
                    }
                } elseif ($cmsRows->count() === 1) {
                    $sd = $cmsRows->get(0)->summary_data ?? [];
                    foreach ($metrics as $metric) {
                        $v = $this->getMetricValueFromSummaryData($channel, $metric, $sd, $metricMap);
                        $out[$channel][$metric] = $v !== null ? [$v, $v] : [null, null];
                    }
                }
            }

            return response()->json(['success' => true, 'channels' => $out]);
        } catch (\Exception $e) {
            \Log::error('getChannelMetricDotTrends error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching dot trends'], 500);
        }
    }

    /**
     * Extract a single metric value from a MarketplaceDailyMetric row (for dot trends).
     */
    private function getMetricValueFromMdm(string $metric, $mdm): ?float
    {
        if (!$mdm) return null;
        if ($metric === 'ad_spend') {
            $kw = (float) ($mdm->kw_spent ?? 0);
            $pmt = (float) ($mdm->pmt_spent ?? 0);
            $hl = (float) ($mdm->hl_spent ?? 0);
            $total = $kw + $pmt + $hl;
            return $total;
        }
        return match ($metric) {
            'l30_sales' => $mdm->total_sales !== null ? (float) $mdm->total_sales : null,
            'l30_orders' => $mdm->total_orders !== null ? (float) $mdm->total_orders : null,
            'qty' => $mdm->total_quantity !== null ? (float) $mdm->total_quantity : null,
            'gprofit' => $mdm->pft_percentage !== null ? (float) $mdm->pft_percentage : null,
            'groi' => $mdm->roi_percentage !== null ? (float) $mdm->roi_percentage : null,
            'ads_pct' => $mdm->tacos_percentage !== null ? (float) $mdm->tacos_percentage : null,
            'npft' => $mdm->n_pft !== null ? (float) $mdm->n_pft : null,
            'nroi' => $mdm->n_roi !== null ? (float) $mdm->n_roi : null,
            default => null,
        };
    }

    /**
     * Extract a single metric value from ChannelMasterSummary summary_data (same logic as chart).
     */
    private function getMetricValueFromSummaryData(string $channel, string $metric, array $summaryData, array $metricMap): ?float
    {
        $metricKey = $metricMap[$metric] ?? $metric;

        if ($metric === 'tat') {
            return null;
        }
        if ($metric === 'acos') {
            $spend = floatval($summaryData['total_ad_spend'] ?? 0);
            $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
            $adSales = $l30Sales * $this->getAdSalesRatio($channel);
            return $adSales > 0 ? round(($spend / $adSales) * 100, 1) : null;
        }
        if ($metric === 'ad_sales') {
            $adSales = floatval($summaryData['ad_sales'] ?? 0);
            if ($adSales <= 0) {
                $l30Sales = floatval($summaryData['l30_sales'] ?? 0);
                $adSales = $l30Sales * $this->getAdSalesRatio($channel);
            }
            return round($adSales, 2);
        }
        if ($metric === 'ad_sold') {
            $adSold = floatval($summaryData['ad_sold'] ?? 0);
            if ($adSold <= 0) {
                $clicks = floatval($summaryData['clicks'] ?? 0);
                $adSold = $clicks * $this->getAdCvrRatio($channel);
            }
            return round($adSold);
        }
        if ($metric === 'ads_cvr') {
            $adSold = floatval($summaryData['ad_sold'] ?? 0);
            $clicks = floatval($summaryData['clicks'] ?? 0);
            if ($adSold <= 0 && $clicks > 0) {
                $adSold = $clicks * $this->getAdCvrRatio($channel);
            }
            // 2 decimals: must match the chart endpoint's precision (round to 2) so the
            // table dot doesn't show grey while the chart shows a green/red trend point
            // for the same two snapshots.
            return $clicks > 0 ? round(($adSold / $clicks) * 100, 2) : null;
        }
        if ($metric === 'cvr') {
            // Units-based: qty / views — matches /temu-decrease and the chart endpoint.
            // Falls back to l30_orders for older snapshots that pre-date the
            // total_quantity field, otherwise the dot would compare against zero.
            $qty = floatval($summaryData['total_quantity'] ?? 0);
            if ($qty <= 0) {
                $qty = floatval($summaryData['l30_orders'] ?? 0);
            }
            $views = floatval($summaryData['total_views'] ?? 0);
            // 2 decimals: must match the chart endpoint's precision (round to 2). At
            // 1 decimal, consecutive days like 5.22% and 5.24% both round to 5.2 →
            // v1 === v2 → grey dot, even though the chart shows the CVR moved.
            return $views > 0 ? round(($qty / $views) * 100, 2) : null;
        }
        if ($metric === 'nroi') {
            $groi = floatval($summaryData['groi_percent'] ?? 0);
            $tcos = floatval($summaryData['tcos_percent'] ?? 0);
            return round($groi - $tcos, 1);
        }

        return array_key_exists($metricKey, $summaryData) ? floatval($summaryData[$metricKey]) : null;
    }

    /**
     * Get the "table reference value" for ALL channels combined.
     * Sums the latest marketplace_daily_metrics value across every channel.
     * Used when channel='all' to scale charts to match the badge totals.
     */
    private function getAllChannelsTableReference(string $metric): ?float
    {
        // Listing CVR = Σ qty / Σ views — not representable from MDM sums; chart uses raw snapshots. No scale ref.
        if ($metric === 'cvr') {
            return null;
        }

        // Metrics that are averaged (percentages) — cannot simply sum
        $avgMetrics = ['gprofit', 'groi', 'ads_pct', 'npft', 'nroi', 'acos', 'ads_cvr'];
        if (in_array($metric, $avgMetrics)) {
            // For averaged metrics, get weighted average across channels
            $allMdm = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
                ->groupBy('channel')
                ->get();

            // GROI and NROI use COGS as the denominator, so a single sales-weighted average can't
            // represent them — different channels have different cogs/sales ratios. Compute these
            // directly from the underlying totals to match the page badge formulas exactly:
            //     GROI = (Σ total_pft) / (Σ total_cogs) × 100
            //     NROI = (Σ total_pft − Σ ad spend) / (Σ total_cogs) × 100
            // Per-channel ad spend is derived from tacos_percentage × total_sales / 100 because
            // marketplace_daily_metrics has no total_ad_spend column.
            if ($metric === 'groi' || $metric === 'nroi') {
                $sumPft = 0.0;
                $sumSpend = 0.0;
                $sumCogs = 0.0;
                foreach ($allMdm as $row) {
                    $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                        ->where('date', $row->max_date)->first();
                    if (!$mdm) continue;
                    $sumPft   += (float) ($mdm->total_pft ?? 0);
                    $sumSpend += ((float) ($mdm->tacos_percentage ?? 0) / 100.0) * (float) ($mdm->total_sales ?? 0);
                    $sumCogs  += (float) ($mdm->total_cogs ?? 0);
                }
                if ($sumCogs <= 0) return null;
                if ($metric === 'groi') {
                    return round(($sumPft / $sumCogs) * 100, 2);
                }
                return round((($sumPft - $sumSpend) / $sumCogs) * 100, 2);
            }

            $totalSales = 0;
            $weightedSum = 0;
            $count = 0;
            foreach ($allMdm as $row) {
                $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                    ->where('date', $row->max_date)->first();
                if (!$mdm) continue;

                $sales = (float) ($mdm->total_sales ?? 0);
                $val = match($metric) {
                    'gprofit' => (float) ($mdm->pft_percentage ?? 0),
                    'ads_pct' => (float) ($mdm->tacos_percentage ?? 0),
                    // NPFT% must be derived from pft_percentage − tacos_percentage. The raw `n_pft`
                    // column is unreliable for blending: some channels (Amazon, eBay, Temu, etc.)
                    // store it as a percent, while others (TikTok, Mercari, several Shopify variants)
                    // store it as a dollar amount. Sales-weighting that mixed-unit column produces
                    // a huge nonsense reference (~460) that would then rescale the chart points to
                    // match it — which is the “wrong history” the badge click was showing.
                    'npft' => (float) ($mdm->pft_percentage ?? 0) - (float) ($mdm->tacos_percentage ?? 0),
                    // groi/nroi handled above (they need cogs as denominator, not sales).
                    default => null,
                };
                if ($val === null) continue;
                // Weight by sales for meaningful average
                $totalSales += $sales;
                $weightedSum += $val * $sales;
                $count++;
            }
            if ($metric === 'acos' || $metric === 'ads_cvr') {
                // For ad metrics, compute from totals
                $liveTotal = ['Total Ad Spend' => 0, 'clicks' => 0, 'ad_sales' => 0, 'ad_sold' => 0];
                $adChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'temu2', 'topdawg', 'walmart', 'shopifyb2c', 'tiktokshop'];
                foreach ($adChannels as $ch) {
                    $live = $this->fetchAdMetricsFromTables($ch);
                    $liveTotal['Total Ad Spend'] += (float) ($live['Total Ad Spend'] ?? 0);
                    $liveTotal['clicks'] += (float) ($live['clicks'] ?? 0);
                    $liveTotal['ad_sales'] += (float) ($live['ad_sales'] ?? 0);
                    $liveTotal['ad_sold'] += (float) ($live['ad_sold'] ?? 0);
                }
                return match($metric) {
                    'acos' => $liveTotal['ad_sales'] > 0 ? round(($liveTotal['Total Ad Spend'] / $liveTotal['ad_sales']) * 100, 2) : null,
                    'ads_cvr' => $liveTotal['clicks'] > 0 ? round(($liveTotal['ad_sold'] / $liveTotal['clicks']) * 100, 2) : null,
                    default => null,
                };
            }
            return $totalSales > 0 ? round($weightedSum / $totalSales, 2) : ($count > 0 ? round($weightedSum / $count, 2) : null);
        }

        // Inv@LP: from Shopify inventory × ProductMaster LP (not in marketplace_daily_metrics)
        if ($metric === 'inv_at_lp') {
            return $this->getInvAtLpShopify();
        }

        // TAT: inventory_value_amazon / total L30 sales (all channels)
        if ($metric === 'tat') {
            $inv = $this->getInventoryValueAmazon();
            $allMdm = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
                ->groupBy('channel')
                ->get();
            $totalSales = 0;
            foreach ($allMdm as $row) {
                $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                    ->where('date', $row->max_date)->first();
                if ($mdm) {
                    $totalSales += (float) ($mdm->total_sales ?? 0);
                }
            }
            return $totalSales > 0 ? round($inv / $totalSales, 2) : null;
        }

        // For summable metrics (counts, amounts): sum across all channels
        $allMdm = MarketplaceDailyMetric::selectRaw('channel, MAX(date) as max_date')
            ->groupBy('channel')
            ->get();

        $total = 0;
        foreach ($allMdm as $row) {
            $mdm = MarketplaceDailyMetric::where('channel', $row->channel)
                ->where('date', $row->max_date)->first();
            if (!$mdm) continue;

            $val = match($metric) {
                'l30_sales' => (float) ($mdm->total_sales ?? 0),
                'l30_orders' => (float) ($mdm->total_orders ?? 0),
                'qty' => (float) ($mdm->total_quantity ?? 0),
                'pft' => (float) ($mdm->total_sales ?? 0)
                    * ((float) ($mdm->pft_percentage ?? 0) - (float) ($mdm->tacos_percentage ?? 0))
                    / 100,
                default => null,
            };
            if ($val !== null) $total += $val;
        }

        // For ad metrics, sum from live campaign tables
        if (in_array($metric, ['ad_spend', 'clicks', 'ad_sales', 'ad_sold'])) {
            $adChannels = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'temu2', 'topdawg', 'walmart', 'shopifyb2c', 'tiktokshop'];
            $total = 0;
            foreach ($adChannels as $ch) {
                $live = $this->fetchAdMetricsFromTables($ch);
                $total += match($metric) {
                    'ad_spend' => (float) ($live['Total Ad Spend'] ?? 0),
                    'clicks' => (float) ($live['clicks'] ?? 0),
                    'ad_sales' => (float) ($live['ad_sales'] ?? 0),
                    'ad_sold' => (float) ($live['ad_sold'] ?? 0),
                    default => 0,
                };
            }
        }

        return $total > 0 ? round($total, 2) : null;
    }

    /**
     * Get the "table reference value" for a given channel and metric.
     * This returns the exact value that the table displays, ensuring charts match.
     * Table sources: marketplace_daily_metrics for most metrics, fetchAdMetricsFromTables for ad metrics.
     */
    private function getTableReferenceValue(string $channel, string $metric): ?float
    {
        static $mdmCache = [];
        static $liveCache = [];

        // Get marketplace_daily_metrics for this channel
        $mdmKey = strtolower($channel);
        if (!isset($mdmCache[$mdmKey])) {
            $channelMap = [
                'amazon' => 'Amazon', 'amazonfba' => 'Amazon FBA',
                'ebay' => 'eBay', 'ebaytwo' => 'eBay 2', 'ebaythree' => 'eBay 3',
                'shopifyb2c' => 'Shopify B2C', 'temu' => 'Temu',
            'temu2' => 'Temu 2', 'walmart' => 'Walmart',
                'tiktokshop' => 'TikTok Shop',
                'tiktokshop2' => 'TikTok 2',
                'depop' => 'Depop',
            ];
            $mdmChannel = $channelMap[$mdmKey] ?? null;
            $mdmCache[$mdmKey] = $mdmChannel
                ? MarketplaceDailyMetric::where('channel', $mdmChannel)->orderBy('date', 'desc')->first()
                : null;
        }
        $mdm = $mdmCache[$mdmKey];

        // For ad metrics, use fetchAdMetricsFromTables (same as table)
        if (in_array($metric, ['ad_spend', 'clicks', 'ad_sales', 'ad_sold', 'acos', 'ads_cvr'])) {
            if (!isset($liveCache[$mdmKey])) {
                $liveCache[$mdmKey] = $this->fetchAdMetricsFromTables($mdmKey);
            }
            $live = $liveCache[$mdmKey];

            return match($metric) {
                'ad_spend' => (float) ($live['Total Ad Spend'] ?? 0),
                'clicks' => (float) ($live['clicks'] ?? 0),
                'ad_sales' => (float) ($live['ad_sales'] ?? 0),
                'ad_sold' => (float) ($live['ad_sold'] ?? 0),
                'acos' => ($live['ad_sales'] ?? 0) > 0
                    ? round(((float)($live['Total Ad Spend'] ?? 0) / (float)$live['ad_sales']) * 100, 2)
                    : null,
                'ads_cvr' => ($live['clicks'] ?? 0) > 0
                    ? round(((float)($live['ad_sold'] ?? 0) / (float)$live['clicks']) * 100, 2)
                    : null,
                default => null,
            };
        }

        if (!$mdm) return null;

        // Map metric to marketplace_daily_metrics field
        return match($metric) {
            'l30_sales' => (float) $mdm->total_sales,
            'l30_orders' => (float) $mdm->total_orders,
            'qty' => (float) $mdm->total_quantity,
            'gprofit' => (float) $mdm->pft_percentage,
            'groi' => (float) $mdm->roi_percentage,
            'ads_pct' => (float) $mdm->tacos_percentage,
            // `n_pft` column has inconsistent units across channels (% vs $). Derive NPFT%
            // from the reliable pft_percentage / tacos_percentage columns instead so single-channel
            // charts don’t get rescaled to a wrong reference.
            'npft' => (float) ($mdm->pft_percentage ?? 0) - (float) ($mdm->tacos_percentage ?? 0),
            'nroi' => $mdm->n_roi !== null ? (float) $mdm->n_roi : null,
            'pft' => round(
                (float) ($mdm->total_sales ?? 0)
                    * ((float) ($mdm->pft_percentage ?? 0) - (float) ($mdm->tacos_percentage ?? 0))
                    / 100,
                2
            ),
            'missing_l' => null, // not critical for matching
            'nmap' => null,
            default => null,
        };
    }

    /**
     * Smooth out consecutive duplicate values in chart data via linear interpolation.
     * Finds "transition points" where the value actually changes, then linearly
     * interpolates between them so there are no flat plateaus.
     * Preserves the first point, last point, and all transition points exactly.
     */
    private function interpolateChartData(array $chartData): array
    {
        $n = count($chartData);
        if ($n <= 2) return $chartData;

        // Step 1: Find transition indices (where value differs from previous value)
        // Index 0 is always a transition. Any index where value != value[i-1] is a transition.
        $transitions = [0]; // always include first point
        for ($i = 1; $i < $n; $i++) {
            if (abs((float) $chartData[$i]['value'] - (float) $chartData[$i - 1]['value']) > 0.001) {
                $transitions[] = $i;
            }
        }
        // Always include last point if not already there
        if (end($transitions) !== $n - 1) {
            $transitions[] = $n - 1;
        }

        // Handle end-of-series duplicates: if the last transition and the forced last point
        // have the same value, remove the earlier one so interpolation spans from further back
        $tCount = count($transitions);
        if ($tCount >= 3) {
            $lastIdx = $transitions[$tCount - 1];
            $prevIdx = $transitions[$tCount - 2];
            $lastVal = (float) $chartData[$lastIdx]['value'];
            $prevVal = (float) $chartData[$prevIdx]['value'];
            if (abs($lastVal - $prevVal) < 0.001) {
                // Remove the second-to-last transition so interpolation bridges from further back
                array_splice($transitions, $tCount - 2, 1);
            }
        }

        // If transitions cover most points, no smoothing needed
        if (count($transitions) >= $n - 1) return $chartData;

        // Step 2: Linearly interpolate between consecutive transition points
        for ($t = 0; $t < count($transitions) - 1; $t++) {
            $fromIdx = $transitions[$t];
            $toIdx = $transitions[$t + 1];
            $gap = $toIdx - $fromIdx;

            if ($gap <= 1) continue; // adjacent transitions, nothing to fill

            $fromVal = (float) $chartData[$fromIdx]['value'];
            $toVal = (float) $chartData[$toIdx]['value'];

            for ($k = $fromIdx + 1; $k < $toIdx; $k++) {
                $frac = ($k - $fromIdx) / $gap;
                $chartData[$k]['value'] = round($fromVal + ($toVal - $fromVal) * $frac, 2);
            }
        }

        return $chartData;
    }

    /**
     * Get ad_sales / l30_sales ratio from the latest ChannelMasterSummary row with ad_sales data.
     * Used as fallback when ad_sales is not available for historical dates.
     */
    private function getAdSalesRatio($channel)
    {
        static $cache = [];
        if (isset($cache[$channel])) return $cache[$channel];

        $row = \App\Models\ChannelMasterSummary::where('channel', $channel)
            ->orderBy('snapshot_date', 'desc')
            ->first();
        if ($row) {
            $sd = $row->summary_data ?? [];
            $adSales = floatval($sd['ad_sales'] ?? 0);
            $l30Sales = floatval($sd['l30_sales'] ?? 0);
            if ($adSales > 0 && $l30Sales > 0) {
                $cache[$channel] = $adSales / $l30Sales;
                return $cache[$channel];
            }
        }
        $cache[$channel] = 0.5; // default 50% fallback
        return $cache[$channel];
    }

    /**
     * Get ad_sold / clicks ratio (CVR) from the latest ChannelMasterSummary row.
     * Used as fallback when ad_sold is not available for historical dates.
     */
    private function getAdCvrRatio($channel)
    {
        static $cache = [];
        if (isset($cache[$channel])) return $cache[$channel];

        $row = \App\Models\ChannelMasterSummary::where('channel', $channel)
            ->orderBy('snapshot_date', 'desc')
            ->first();
        if ($row) {
            $sd = $row->summary_data ?? [];
            $adSold = floatval($sd['ad_sold'] ?? 0);
            $clicks = floatval($sd['clicks'] ?? 0);
            if ($adSold > 0 && $clicks > 0) {
                $cache[$channel] = $adSold / $clicks;
                return $cache[$channel];
            }
        }
        $cache[$channel] = 0.04; // default 4% CVR fallback
        return $cache[$channel];
    }

    /**
     * Get channel name as it appears in marketplace_daily_metrics table
     */
    private function getChannelNameForMetrics($normalizedChannelName)
    {
        $mapping = [
            'amazon' => 'Amazon',
            'amazonfba' => 'Amazon FBA',
            'ebay' => 'eBay',
            'ebaytwo' => 'eBay 2',
            'ebaythree' => 'eBay 3',
            'walmart' => 'Walmart',
            'temu' => 'Temu',
            'temu2' => 'Temu 2',
            'macys' => 'Macys',
            'tiendamia' => 'Tiendamia',
            'bestbuyusa' => 'Best Buy USA',
            'reverb' => 'Reverb',
            'doba' => 'Doba',
            'pls' => 'PLS',
            'wayfair' => 'Wayfair',
            'faire' => 'Faire',
            'purchasingpower' => 'Purchasing Power',
            'shein' => 'Shein',
            'tiktokshop' => 'TikTok',
            'tiktokshop2' => 'TikTok 2',
            'depop' => 'Depop',
            'instagramshop' => 'Instagram Shop',
            'aliexpress' => 'AliExpress',
            'mercariwship' => 'Mercari With Ship',
            'mercariwoship' => 'Mercari Without Ship',
            'fbmarketplace' => 'FB Marketplace',
            'fbshop' => 'FB Shop',
            'business5core' => 'Business 5Core',
            'topdawg' => 'TopDawg',
            'shopifyb2c' => 'Shopify B2C',
            'shopifyb2b' => 'Shopify B2B',
        ];
        
        return $mapping[$normalizedChannelName] ?? ucfirst($normalizedChannelName);
    }

    /**
     * Save channel-wise daily summaries for historical tracking
     */
    private function saveChannelDailySummaries($channelData)
    {
        try {
            // Use California/Pacific timezone for date storage
            $today = now('America/Los_Angeles')->toDateString();
            
            foreach ($channelData as $row) {
                $channelName = strtolower(str_replace([' ', '-', '&', '/'], '', trim($row['Channel '] ?? '')));
                
                if (!$channelName) continue;
                
                // Calculate NPFT% and TCOS%
                $gprofitPercent = floatval($row['Gprofit%'] ?? 0);
                $adSpend = floatval($row['Total Ad Spend'] ?? 0);
                $l30Sales = floatval($row['L30 Sales'] ?? 0);
                $tcosPercent = $l30Sales > 0 ? ($adSpend / $l30Sales * 100) : 0;
                $npftPercent = $gprofitPercent - $tcosPercent;
                
                // Get actual total_quantity from marketplace_daily_metrics (for units sold)
                $channelNameForMetrics = $this->getChannelNameForMetrics($channelName);
                $metrics = \App\Models\MarketplaceDailyMetric::where('channel', $channelNameForMetrics)
                    ->latest('date')
                    ->first();
                $totalQuantity = $metrics ? ($metrics->total_quantity ?? $metrics->total_orders ?? 0) : 0;

                // Fallback: channels computed directly (no marketplace_daily_metrics row,
                // e.g. Newegg/PLS) carry their units in the page row's "Qty". Without this
                // the qty trend dot/chart would always read 0 even though the column shows
                // the correct number.
                if (!$totalQuantity) {
                    $totalQuantity = floatval($row['Qty'] ?? 0);
                }
                
                // Prepare summary data
                $summaryData = [
                    // Sales & Orders
                    'l60_sales' => floatval($row['L-60 Sales'] ?? 0),
                    'l30_sales' => $l30Sales,
                    // Persist yesterday's sales so the Y Sales badge can render a per-day trend.
                    // Older snapshots (before this field was added) won't have this key — the
                    // chart endpoint filters those days out so the trend isn't a flat-zero line.
                    'y_sales' => floatval($row['Y Sales'] ?? 0),
                    'l7_sales' => floatval($row['L7 Sales'] ?? 0),
                    'l7_vs_30_pace_pct' => $row['L7 vs 30 pace %'] !== null ? floatval($row['L7 vs 30 pace %']) : null,
                    'l60_orders' => floatval($row['L60 Orders'] ?? 0),
                    'l30_orders' => floatval($row['L30 Orders'] ?? 0),
                    'total_quantity' => floatval($totalQuantity), // Total quantity (units sold) from marketplace_daily_metrics
                    'total_views' => floatval($row['Total Views'] ?? 0),
                    'growth' => floatval($row['Growth'] ?? 0),
                    'clicks' => intval($row['clicks'] ?? 0),
                    
                    // Profit & ROI Metrics
                    'gprofit_percent' => $gprofitPercent,
                    'gprofit_l60' => floatval($row['gprofitL60'] ?? 0),
                    'groi_percent' => floatval($row['G Roi'] ?? 0),
                    'groi_l60' => floatval($row['G RoiL60'] ?? 0),
                    'npft_percent' => round($npftPercent, 2),
                    'nroi_percent' => floatval($row['N ROI'] ?? 0),
                    'tcos_percent' => round($tcosPercent, 2),
                    'total_ad_spend' => $adSpend,
                    'ad_sales' => floatval($row['Ad Sales'] ?? 0),
                    'ad_sold' => intval($row['ad_sold'] ?? 0),
                    'ads_cvr' => floatval($row['Ads CVR'] ?? 0),
                    'cogs' => floatval($row['cogs'] ?? 0),
                    'total_pft' => floatval($row['Total PFT'] ?? 0),
                    
                    // Counts
                    'missing_listing' => intval($row['Missing Listing'] ?? 0),
                    'stock_mapping' => intval($row['Stock Mapping'] ?? 0),
                    'map_count' => intval($row['Map'] ?? 0),
                    'miss_count' => intval($row['Miss'] ?? 0),
                    'nmap_count' => intval($row['NMap'] ?? 0),
                    'nr_count' => intval($row['NR'] ?? 0),
                    'listed_count' => intval($row['listed_count'] ?? 0),
                    
                    // Config
                    'channel_percentage' => floatval($row['channel_percentage'] ?? 0),
                    'base' => floatval($row['base'] ?? 0),
                    'target' => floatval($row['target'] ?? 0),
                    'type' => $row['type'] ?? 'B2C',
                    'w_ads' => intval($row['W/Ads'] ?? 0),
                    'update' => intval($row['Update'] ?? 0),
                    
                    // Inv@LP (Shopify inv * PM LP) — only non-zero for Shopify B2C
                    'inv_at_lp' => floatval($row['Inv at LP'] ?? 0),
                    // INV Val (for TAT = inv / sales) — only first channel has it
                    'inventory_value_amazon' => floatval($row['inventory_value_amazon'] ?? 0),
                    
                    // Metadata
                    'calculated_at' => now()->toDateTimeString(),
                ];
                
                // Save or update
                \App\Models\ChannelMasterSummary::updateOrCreate(
                    [
                        'channel' => $channelName,
                        'snapshot_date' => $today
                    ],
                    [
                        'summary_data' => $summaryData,
                        'notes' => 'Auto-saved channel master snapshot',
                    ]
                );
            }
            
            Log::info("Channel Master daily summaries saved for {$today}", [
                'channels_count' => count($channelData),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saving channel master daily summaries: ' . $e->getMessage());
        }
    }

    /**
     * Get clicks breakdown (PT, KW, HL) for a channel
     */
    public function getClicksBreakdown(Request $request)
    {
        try {
            $channel = $request->input('channel');
            
            if (!$channel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Channel name is required'
                ], 400);
            }
            
            // Get channel name and convert to uppercase
            $channelUpper = strtoupper(trim($channel));
            
            \Log::info("Clicks breakdown requested", ['channel' => $channelUpper]);
            
            // Determine related channel patterns based on main channel
            $suffixPatterns = [];
            if ($channelUpper === 'AMAZON') {
                $suffixPatterns = ['AMZ PT', 'AMZ KW', 'AMZ HL'];
            } elseif ($channelUpper === 'EBAY') {
                $suffixPatterns = ['EB KW', 'EB PMT'];
            } elseif ($channelUpper === 'EBAY 2' || $channelUpper === 'EBAYTWO') {
                $suffixPatterns = ['EB PMT2'];
            } elseif ($channelUpper === 'EBAY 3' || $channelUpper === 'EBAYTHREE') {
                $suffixPatterns = ['EB KW3', 'EB PMT3'];
            } elseif ($channelUpper === 'WALMART') {
                $suffixPatterns = [];
            }
            
            \Log::info("Searching for patterns", ['patterns' => $suffixPatterns]);
            
            // Get parent channel's L30 sales for TACOS calculation
            $parentChannel = \App\Models\ADVMastersData::where('channel', $channelUpper)->first();
            $parentL30Sales = $parentChannel ? ($parentChannel->l30_sales ?? 0) : 0;
            
            \Log::info("Parent channel L30 sales", [
                'channel' => $channelUpper,
                'l30_sales' => $parentL30Sales
            ]);
            
            // Find all related channels from adv_masters_datas table
            $breakdown = [];
            $total = 0;
            
            foreach ($suffixPatterns as $pattern) {
                // Try both exact match and case-insensitive match
                $relatedChannel = \App\Models\ADVMastersData::where('channel', $pattern)
                    ->orWhereRaw('UPPER(channel) = ?', [strtoupper($pattern)])
                    ->first();
                
                \Log::info("Pattern search", [
                    'pattern' => $pattern,
                    'found' => $relatedChannel ? 'yes' : 'no',
                    'found_channel' => $relatedChannel ? $relatedChannel->channel : null
                ]);
                
                if ($relatedChannel) {
                    // Extract clean type name from channel pattern
                    $type = $pattern;
                    if (strpos($pattern, 'PMT') !== false) {
                        $type = str_replace(['EB ', 'EB'], '', $pattern);
                    } elseif (strpos($pattern, 'KW') !== false) {
                        $type = str_replace(['EB ', 'EB', 'AMZ ', 'AMZ'], '', $pattern);
                    } elseif (strpos($pattern, 'PT') !== false || strpos($pattern, 'HL') !== false) {
                        $type = str_replace(['AMZ ', 'AMZ'], '', $pattern);
                    }
                    
                    // Get values from database
                    $clicks = $relatedChannel->clicks ?? 0;
                    $adSold = $relatedChannel->ad_sold ?? 0;
                    $adSales = $relatedChannel->ad_sales ?? 0;
                    $spent = $relatedChannel->spent ?? 0;
                    
                    // Calculate metrics using formulas:
                    // CVR = (ads sold / clicks) * 100
                    $cvr = $clicks > 0 ? round(($adSold / $clicks) * 100, 2) : 0;
                    
                    // ACOS = (spent / ad_sales) * 100
                    $acos = $adSales > 0 ? round(($spent / $adSales) * 100, 2) : 0;
                    
                    // TACOS = (spent / parent_total_sales) * 100
                    // Use parent channel's L30 sales for all sub-channels
                    $tacos = $parentL30Sales > 0 ? round(($spent / $parentL30Sales) * 100, 2) : 0;
                    
                    $breakdown[] = [
                        'type' => $type,
                        'channel' => $relatedChannel->channel,
                        'spent' => $spent,
                        'clicks' => $clicks,
                        'ad_sales' => $adSales,
                        'acos' => $acos,
                        'tacos' => $tacos,
                        'ad_sold' => $adSold,
                        'cvr' => $cvr,
                        'missing_ads' => $relatedChannel->missing_ads ?? 0
                    ];
                    $total += $clicks;
                }
            }
            
            \Log::info("Clicks breakdown results", [
                'channel' => $channelUpper,
                'found_count' => count($breakdown),
                'total_clicks' => $total
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $breakdown,
                'total' => $total,
                'parent_l30_sales' => $parentL30Sales
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching clicks breakdown: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clicks breakdown'
            ], 500);
        }
    }

    /**
     * Get daily ad metrics chart data for breakdown columns (KW, PT, HL, PMT, SHOPPING, SERP)
     * Returns last 30 days of daily data for the specified channel and metric type.
     * Daily data is only available for: Amazon, eBay 1/2/3, Google Ads (Shopify B2C)
     */
    public function getAdBreakdownChartData(Request $request)
    {
        try {
            $channel = strtolower(trim($request->input('channel', '')));
            $adType = strtolower(trim($request->input('ad_type', ''))); // kw, pt, hl, pmt, shopping, serp
            $metric = strtolower(trim($request->input('metric', 'spend'))); // spend, clicks, sales, sold

            if (!$channel || !$adType) {
                return response()->json(['success' => false, 'message' => 'Channel and ad_type are required'], 400);
            }

            // ==================================================================================
            // RATIO-BASED APPROACH: Use ChannelMasterSummary historical totals + today's
            // breakdown ratios from live campaign tables (same source as the table display).
            // This ensures chart values match the table exactly.
            // ==================================================================================
            $metricsChannelMap = [
                'amazon' => 'Amazon', 'amazonfba' => 'Amazon FBA',
                'ebay' => 'eBay', 'ebaytwo' => 'eBay 2', 'ebaythree' => 'eBay 3',
                'shopifyb2c' => 'Shopify B2C', 'temu' => 'Temu',
            'temu2' => 'Temu 2', 'walmart' => 'Walmart',
            ];
            $metricsChannel = $metricsChannelMap[$channel] ?? null;
            // ChannelMasterSummary uses the frontend channel name directly (lowercase)
            $summaryChannel = $channel;

            // Fetch live metrics from campaign tables (same source as table display)
            $liveMetrics = $this->fetchAdMetricsFromTables($channel);

            // Map ad_type to column name prefix
            $adTypePrefix = match($adType) {
                'kw' => 'KW', 'pt' => 'PT', 'hl' => 'HL',
                'pmt' => 'PMT', 'shopping' => 'Shopping', 'serp' => 'SERP',
                default => 'KW',
            };

            // Extract live values for THIS ad type
            $adTypeSpend = (float) ($liveMetrics["{$adTypePrefix} Spent"] ?? 0);
            $adTypeClicks = (float) ($liveMetrics["{$adTypePrefix} Clicks"] ?? 0);
            $adTypeSales = (float) ($liveMetrics["{$adTypePrefix} Sales"] ?? 0);
            $adTypeSold = (float) ($liveMetrics["{$adTypePrefix} Sold"] ?? 0);

            // Extract totals from live metrics
            $totalSpend = (float) ($liveMetrics['Total Ad Spend'] ?? 0);
            $totalClicks = (float) ($liveMetrics['clicks'] ?? 0);
            $totalSales = (float) ($liveMetrics['ad_sales'] ?? 0);
            $totalSold = (float) ($liveMetrics['ad_sold'] ?? 0);

            if ($metricsChannel && ($adTypeSpend > 0 || $adTypeClicks > 0 || $adTypeSales > 0)) {
                    // Calculate ratios from live campaign data
                    $ratios = [];
                    $ratios['spend'] = $totalSpend > 0 ? $adTypeSpend / $totalSpend : 0;
                    $ratios['clicks'] = $totalClicks > 0 ? $adTypeClicks / $totalClicks : 0;
                    $ratios['sales'] = $totalSales > 0 ? $adTypeSales / $totalSales : 0;
                    $ratios['sold'] = $totalSold > 0 ? $adTypeSold / $totalSold : 0;

                    // Map metric to ChannelMasterSummary field
                    $summaryFieldMap = [
                        'spend' => 'total_ad_spend',
                        'clicks' => 'clicks',
                        'sales' => 'ad_sales',
                        'sold' => 'ad_sold',
                    ];

                    // For spend, clicks, sales, sold — use ratio × historical total
                    // For acos, cvr — compute from ratio-derived spend/sales or sold/clicks
                    $inputStartDate = $request->input('start_date');
                    $chartEndDate = Carbon::today();
                    $chartStartDate = $inputStartDate ? Carbon::parse($inputStartDate) : $chartEndDate->copy()->subDays(30);

                    $history = \App\Models\ChannelMasterSummary::where('channel', $summaryChannel)
                        ->whereDate('snapshot_date', '>=', $chartStartDate->format('Y-m-d'))
                        ->whereDate('snapshot_date', '<=', $chartEndDate->format('Y-m-d'))
                        ->orderBy('snapshot_date', 'asc')
                        ->get(['snapshot_date', 'summary_data']);

                    if ($history->isNotEmpty()) {
                        // Check if the required summary field has sufficient data
                        // (some metrics like ad_sales/ad_sold were added recently and may be null for older dates)
                        $requiredFields = match($metric) {
                            'spend' => ['total_ad_spend'],
                            'clicks' => ['clicks'],
                            'sales' => ['ad_sales'],
                            'sold' => ['ad_sold'],
                            'acos' => ['total_ad_spend', 'ad_sales'],
                            'cvr' => ['ad_sold', 'clicks'],
                            default => [],
                        };

                        // Count how many rows have non-null/non-zero values for required fields
                        $validCount = 0;
                        foreach ($history as $row) {
                            $data = is_string($row->summary_data) ? json_decode($row->summary_data, true) : $row->summary_data;
                            $allFieldsPresent = true;
                            foreach ($requiredFields as $field) {
                                if (empty($data[$field]) || (float)($data[$field]) == 0) {
                                    $allFieldsPresent = false;
                                    break;
                                }
                            }
                            if ($allFieldsPresent) $validCount++;
                        }

                        // Only use ratio approach if at least 50% of rows have valid data
                        // BGT, SBGT, SBID are not in ChannelMasterSummary; use fallback
                        $hasEnoughData = $validCount >= (count($history) * 0.5) && !in_array($metric, ['bgt', 'sbgt', 'sbid']);

                        if ($hasEnoughData) {
                            $chartData = [];
                            foreach ($history as $row) {
                                $data = is_string($row->summary_data) ? json_decode($row->summary_data, true) : $row->summary_data;

                                if (in_array($metric, ['spend', 'clicks', 'sales', 'sold'])) {
                                    $summaryField = $summaryFieldMap[$metric];
                                    $totalValue = (float) ($data[$summaryField] ?? 0);
                                    $ratio = $ratios[$metric] ?? 0;
                                    $value = round($totalValue * $ratio, 2);
                                } elseif ($metric === 'acos') {
                                    // ACOS = (Spend / Sales) * 100
                                    $histSpend = (float) ($data['total_ad_spend'] ?? 0);
                                    $histSales = (float) ($data['ad_sales'] ?? 0);
                                    $adSpend = $histSpend * ($ratios['spend'] ?? 0);
                                    $adSales = $histSales * ($ratios['sales'] ?? 0);
                                    $value = $adSales > 0 ? round(($adSpend / $adSales) * 100, 1) : 0;
                                } elseif ($metric === 'cvr') {
                                    // CVR = (Sold / Clicks) * 100
                                    $histSold = (float) ($data['ad_sold'] ?? 0);
                                    $histClicks = (float) ($data['clicks'] ?? 0);
                                    $adSold = $histSold * ($ratios['sold'] ?? 0);
                                    $adClicks = $histClicks * ($ratios['clicks'] ?? 0);
                                    $value = $adClicks > 0 ? round(($adSold / $adClicks) * 100, 1) : 0;
                                } else {
                                    $value = 0;
                                }

                                $chartData[] = [
                                    'date' => Carbon::parse($row->snapshot_date)->format('M d'),
                                    'value' => $value,
                                ];
                            }

                            // Scale ratio-based data to match marketplace_daily_metrics reference value
                            // (handles cases where ChannelMasterSummary total includes extra campaign types)
                            if (count($chartData) > 0 && in_array($metric, ['spend', 'clicks', 'sales', 'sold'])) {
                                $refValue = null;
                                if ($metric === 'spend') {
                                    $refValue = $adTypeSpend;
                                } elseif ($metric === 'clicks') {
                                    $refValue = $adTypeClicks;
                                } elseif ($metric === 'sales') {
                                    $refValue = $adTypeSales;
                                } elseif ($metric === 'sold') {
                                    $refValue = $adTypeSold;
                                }
                                if ($refValue !== null && $refValue > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refValue) > 0.5) {
                                        $sf = $refValue / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 2); }
                                        unset($p);
                                    }
                                }
                            } elseif (count($chartData) > 0 && $metric === 'acos') {
                                // Recalculate ACOS reference from live campaign data
                                $refAcos = $adTypeSales > 0 ? round(($adTypeSpend / $adTypeSales) * 100, 1) : 0;
                                if ($refAcos > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refAcos) > 0.1) {
                                        $sf = $refAcos / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 1); }
                                        unset($p);
                                    }
                                }
                            } elseif (count($chartData) > 0 && $metric === 'cvr') {
                                $refCvr = $adTypeClicks > 0 ? round(($adTypeSold / $adTypeClicks) * 100, 1) : 0;
                                if ($refCvr > 0) {
                                    $lastVal = end($chartData)['value'];
                                    if ($lastVal > 0 && abs($lastVal - $refCvr) > 0.1) {
                                        $sf = $refCvr / $lastVal;
                                        foreach ($chartData as &$p) { $p['value'] = round($p['value'] * $sf, 1); }
                                        unset($p);
                                    }
                                }
                            }

                            // Return ratio-based data (with interpolation)
                            if (count($chartData) > 0) {
                                $chartData = $this->interpolateChartData($chartData);
                                return response()->json([
                                    'success' => true,
                                    'channel' => $channel,
                                    'ad_type' => $adType,
                                    'metric' => $metric,
                                    'data' => $chartData
                                ]);
                            }
                        }
                        // If not enough data, fall through to rolling approach below
                    }
            }

            // ==================================================================================
            // FALLBACK: Rolling 30-day calculation from daily campaign reports
            // Used when ChannelMasterSummary or marketplace_daily_metrics data is unavailable
            // ==================================================================================
            // Get date filter from request or use defaults
            $inputStartDate = $request->input('start_date');
            $inputEndDate = $request->input('end_date');
            $isLifetime = empty($inputStartDate);
            
            // Rolling 30-day calculation: For each date, show sum of last 30 days
            $chartEndDate = $inputEndDate ? Carbon::parse($inputEndDate) : Carbon::today()->subDays(2);
            if (!$isLifetime) {
                $chartStartDate = Carbon::parse($inputStartDate);
            } else {
                // Lifetime placeholder — will be recalculated after data is fetched
                $chartStartDate = $chartEndDate->copy()->subYears(3);
            }
            // Need data from 30 days before chart start for rolling calc
            $dataStartDate = $chartStartDate->copy()->subDays(30);
            
            // Fetch ALL daily data for extended range
            $dailyData = [];
            
            // Determine column to sum based on metric
            // Use 7-day attribution (sales7d, purchases7d) to match table and Amazon dashboard
            $spendCol = 'spend';
            $clicksCol = 'clicks';
            $salesCol = 'sales7d';
            $soldCol = 'purchases7d';

            // Fetch daily data based on channel and ad type
            if ($channel === 'amazon' || $channel === 'amazonfba') {
                $isFba = $channel === 'amazonfba';
                
                if ($adType === 'kw') {
                    $query = DB::table('amazon_sp_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                    
                    if ($isFba) {
                        $query->whereRaw("campaignName LIKE '%FBA'")->whereRaw("campaignName NOT LIKE '%FBA PT%'")->whereRaw("campaignName NOT LIKE '%FBA PT.%'");
                    } else {
                        $query->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'");
                    }
                    
                    $valueCol = match($metric) {
                        'clicks' => $clicksCol, 'sales' => $salesCol, 'sold', 'cvr' => $soldCol,
                        'bgt' => 'COALESCE(campaignBudgetAmount, 0)',
                        'sbid' => 'CAST(NULLIF(TRIM(COALESCE(sbid, \'\')), \'\') AS DECIMAL(10,2))',
                        default => $spendCol
                    };
                    $aggFunc = $metric === 'sbid' ? 'AVG' : 'SUM';
                    $rows = $query->selectRaw("report_date_range as date, {$aggFunc}({$valueCol}) as val")->groupBy('report_date_range')->get();
                    
                } elseif ($adType === 'pt') {
                    $query = DB::table('amazon_sp_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                    
                    if ($isFba) {
                        $query->where(fn($q) => $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'"));
                    } else {
                        $query->where(fn($q) => $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'"))
                              ->whereRaw("campaignName NOT LIKE '%FBA PT%'")->whereRaw("campaignName NOT LIKE '%FBA PT.%'");
                    }
                    
                    $valueCol = match($metric) {
                        'clicks' => $clicksCol, 'sales' => $salesCol, 'sold', 'cvr' => $soldCol,
                        'bgt' => 'COALESCE(campaignBudgetAmount, 0)',
                        default => $spendCol
                    };
                    $rows = $query->selectRaw("report_date_range as date, SUM({$valueCol}) as val")->groupBy('report_date_range')->get();
                    
                } elseif ($adType === 'hl' && !$isFba) {
                    $valueCol = match($metric) { 'clicks' => 'clicks', 'sales' => 'sales', 'sold', 'cvr' => 'purchases', default => 'cost' };
                    $rows = DB::table('amazon_sb_campaign_reports')
                        ->whereNotNull('report_date_range')
                        ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                        ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                        ->selectRaw("report_date_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_date_range')
                        ->get();
                } else {
                    $rows = collect();
                }
                
            } elseif (in_array($channel, ['ebay', 'ebaytwo', 'ebaythree'])) {
                $kwTable = match ($channel) { 'ebay' => 'ebay_priority_reports', 'ebaytwo' => 'ebay_2_priority_reports', 'ebaythree' => 'ebay_3_priority_reports', default => null };
                $pmtTable = match ($channel) { 'ebay' => 'ebay_general_reports', 'ebaytwo' => 'ebay_2_general_reports', 'ebaythree' => 'ebay_3_general_reports', default => null };
                
                $excludeRanges = ['L90', 'L60', 'L30', 'L15', 'L7', 'L1'];
                
                if ($adType === 'kw' && $kwTable) {
                    $valueCol = match($metric) {
                        'clicks' => 'cpc_clicks',
                        'sales' => 'REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")',
                        'sold', 'cvr' => 'cpc_attributed_sales',
                        default => 'REPLACE(REPLACE(cpc_ad_fees_payout_currency, "USD ", ""), ",", "")'
                    };
                    $rows = DB::table($kwTable)
                        ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_range', $excludeRanges)
                        ->selectRaw("report_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_range')
                        ->get();
                } elseif ($adType === 'pmt' && $pmtTable) {
                    $valueCol = match($metric) {
                        'clicks' => 'clicks',
                        'sales' => 'REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")',
                        'sold', 'cvr' => 'sales',
                        default => 'REPLACE(REPLACE(ad_fees, "USD ", ""), ",", "")'
                    };
                    $rows = DB::table($pmtTable)
                        ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                        ->whereNotIn('report_range', $excludeRanges)
                        ->selectRaw("report_range as date, SUM({$valueCol}) as val")
                        ->groupBy('report_range')
                        ->get();
                } else {
                    $rows = collect();
                }
                
            } elseif ($channel === 'shopifyb2c' && in_array($adType, ['shopping', 'serp'])) {
                $channelType = $adType === 'shopping' ? 'SHOPPING' : 'SEARCH';
                $statusFilter = $adType === 'shopping' ? ['ENABLED'] : ['ENABLED', 'PAUSED'];
                
                $valueCol = match($metric) {
                    'clicks' => 'metrics_clicks',
                    'sales' => 'ga4_ad_sales',
                    'sold', 'cvr' => 'ga4_sold_units',
                    default => 'metrics_cost_micros'
                };
                
                $rows = DB::table('google_ads_campaigns')
                    ->whereDate('date', '>=', $dataStartDate->format('Y-m-d'))
                    ->whereDate('date', '<=', $chartEndDate->format('Y-m-d'))
                    ->where('advertising_channel_type', $channelType)
                    ->whereIn('campaign_status', $statusFilter)
                    ->selectRaw("date, SUM({$valueCol}) as val")
                    ->groupBy('date')
                    ->get();
                
                // Convert micros to dollars for spend and acos (both use metrics_cost_micros)
                if ($metric === 'spend' || $metric === 'acos') {
                    $rows = $rows->map(function($row) {
                        $row->val = $row->val / 1000000;
                        return $row;
                    });
                }
            } else {
                $rows = collect();
            }

            // Build daily data lookup
            foreach ($rows as $row) {
                $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                $dailyData[$dateKey] = (float) $row->val;
            }

            // For ACOS, CVR, and SBGT, we need additional data
            $dailyData2 = [];
            if (in_array($metric, ['acos', 'cvr', 'sbgt'])) {
                // ACOS and SBGT need spend and sales, CVR needs sold and clicks
                // We already have one metric, fetch the other
                $metric2 = in_array($metric, ['acos', 'sbgt']) ? 'sales' : 'clicks';
                $rows2 = collect();
                
                // Re-fetch with the second metric
                if ($channel === 'amazon' || $channel === 'amazonfba') {
                    $isFba = $channel === 'amazonfba';
                    $valueCol2 = in_array($metric, ['acos', 'sbgt']) ? $salesCol : $clicksCol;
                    
                    if ($adType === 'kw') {
                        $query2 = DB::table('amazon_sp_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                        if ($isFba) {
                            $query2->whereRaw("campaignName LIKE '%FBA'")->whereRaw("campaignName NOT LIKE '%FBA PT%'");
                        } else {
                            $query2->whereRaw("campaignName NOT REGEXP '(PT\\.?$|FBA$)'");
                        }
                        $rows2 = $query2->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")->groupBy('report_date_range')->get();
                    } elseif ($adType === 'pt') {
                        $query2 = DB::table('amazon_sp_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')");
                        if ($isFba) {
                            $query2->where(fn($q) => $q->whereRaw("campaignName LIKE '%FBA PT'")->orWhereRaw("campaignName LIKE '%FBA PT.'"));
                        } else {
                            $query2->where(fn($q) => $q->whereRaw("campaignName LIKE '%PT'")->orWhereRaw("campaignName LIKE '%PT.'"))
                                  ->whereRaw("campaignName NOT LIKE '%FBA PT%'");
                        }
                        $rows2 = $query2->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")->groupBy('report_date_range')->get();
                    } elseif ($adType === 'hl' && !$isFba) {
                        $valueCol2 = in_array($metric, ['acos', 'sbgt']) ? 'sales' : 'clicks';
                        $rows2 = DB::table('amazon_sb_campaign_reports')
                            ->whereNotNull('report_date_range')
                            ->whereBetween('report_date_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_date_range', ['L60', 'L30', 'L15', 'L7', 'L1'])
                            ->whereRaw("(campaignStatus IS NULL OR campaignStatus != 'ARCHIVED')")
                            ->selectRaw("report_date_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_date_range')
                            ->get();
                    }
                } elseif (in_array($channel, ['ebay', 'ebaytwo', 'ebaythree'])) {
                    $kwTable = match ($channel) { 'ebay' => 'ebay_priority_reports', 'ebaytwo' => 'ebay_2_priority_reports', 'ebaythree' => 'ebay_3_priority_reports', default => null };
                    $pmtTable = match ($channel) { 'ebay' => 'ebay_general_reports', 'ebaytwo' => 'ebay_2_general_reports', 'ebaythree' => 'ebay_3_general_reports', default => null };
                    $excludeRanges = ['L90', 'L60', 'L30', 'L15', 'L7', 'L1'];
                    
                    if ($adType === 'kw' && $kwTable) {
                        $valueCol2 = in_array($metric, ['acos', 'sbgt'])
                            ? 'REPLACE(REPLACE(cpc_sale_amount_payout_currency, "USD ", ""), ",", "")'
                            : 'cpc_clicks';
                        $rows2 = DB::table($kwTable)
                            ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_range', $excludeRanges)
                            ->selectRaw("report_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_range')
                            ->get();
                    } elseif ($adType === 'pmt' && $pmtTable) {
                        $valueCol2 = in_array($metric, ['acos', 'sbgt'])
                            ? 'REPLACE(REPLACE(sale_amount, "USD ", ""), ",", "")'
                            : 'clicks';
                        $rows2 = DB::table($pmtTable)
                            ->whereBetween('report_range', [$dataStartDate->format('Y-m-d'), $chartEndDate->format('Y-m-d')])
                            ->whereNotIn('report_range', $excludeRanges)
                            ->selectRaw("report_range as date, SUM({$valueCol2}) as val")
                            ->groupBy('report_range')
                            ->get();
                    }
                } elseif ($channel === 'shopifyb2c' && in_array($adType, ['shopping', 'serp'])) {
                    $channelType = $adType === 'shopping' ? 'SHOPPING' : 'SEARCH';
                    $statusFilter = $adType === 'shopping' ? ['ENABLED'] : ['ENABLED', 'PAUSED'];
                    $valueCol2 = in_array($metric, ['acos', 'sbgt']) ? 'ga4_ad_sales' : 'metrics_clicks';
                    
                    $rows2 = DB::table('google_ads_campaigns')
                        ->whereDate('date', '>=', $dataStartDate->format('Y-m-d'))
                        ->whereDate('date', '<=', $chartEndDate->format('Y-m-d'))
                        ->where('advertising_channel_type', $channelType)
                        ->whereIn('campaign_status', $statusFilter)
                        ->selectRaw("date, SUM({$valueCol2}) as val")
                        ->groupBy('date')
                        ->get();
                }
                
                foreach ($rows2 as $row) {
                    $dateKey = Carbon::parse($row->date)->format('Y-m-d');
                    $dailyData2[$dateKey] = (float) $row->val;
                }
            }

            // For Lifetime: set chart start to the earliest date with actual data
            if ($isLifetime) {
                $allDates = array_merge(array_keys($dailyData), array_keys($dailyData2 ?? []));
                if (!empty($allDates)) {
                    sort($allDates);
                    $chartStartDate = Carbon::parse($allDates[0]);
                } else {
                    $chartStartDate = $chartEndDate->copy();
                }
            }

            // Calculate rolling 30-day values for each chart date
            $chartData = [];
            $currentDate = $chartStartDate->copy();
            while ($currentDate <= $chartEndDate) {
                $rolling30Sum = 0;
                $rolling30Sum2 = 0;
                
                // Sum last 30 days including current date
                for ($i = 0; $i < 30; $i++) {
                    $lookupDate = $currentDate->copy()->subDays($i)->format('Y-m-d');
                    if (isset($dailyData[$lookupDate])) {
                        $rolling30Sum += $dailyData[$lookupDate];
                    }
                    if (isset($dailyData2[$lookupDate])) {
                        $rolling30Sum2 += $dailyData2[$lookupDate];
                    }
                }
                
                // Calculate final value based on metric type
                if ($metric === 'acos') {
                    // ACOS = (Spend / Sales) * 100
                    // dailyData has spend, dailyData2 has sales
                    $value = $rolling30Sum2 > 0 ? round(($rolling30Sum / $rolling30Sum2) * 100, 1) : 0;
                } elseif ($metric === 'cvr') {
                    // CVR = (Sold / Clicks) * 100
                    // dailyData has sold, dailyData2 has clicks
                    $value = $rolling30Sum2 > 0 ? round(($rolling30Sum / $rolling30Sum2) * 100, 1) : 0;
                } elseif ($metric === 'sbid') {
                    // SBID: rolling 30-day average of daily avg sbids
                    $sbidCount = 0;
                    $sbidSum = 0;
                    for ($i = 0; $i < 30; $i++) {
                        $lookupDate = $currentDate->copy()->subDays($i)->format('Y-m-d');
                        if (isset($dailyData[$lookupDate]) && (float) $dailyData[$lookupDate] > 0) {
                            $sbidSum += (float) $dailyData[$lookupDate];
                            $sbidCount++;
                        }
                    }
                    $value = $sbidCount > 0 ? round($sbidSum / $sbidCount, 2) : 0;
                } elseif ($metric === 'sbgt') {
                    // SBGT from ACOS (spend/sales*100): <20 → 10, [20,30) → 5, ≥30 → 3 (matches AutoUpdateAmazonBgtKw / tabulator)
                    // dailyData has spend, dailyData2 has sales
                    $acos = $rolling30Sum2 > 0 ? ($rolling30Sum / $rolling30Sum2) * 100 : 0;
                    $value = match (true) {
                        $acos < 20 => 10,
                        $acos < 30 => 5,
                        default => 3,
                    };
                } else {
                    $value = round($rolling30Sum, 2);
                }
                
                $chartData[] = [
                    'date' => $currentDate->format('M d'),
                    'value' => $value
                ];
                $currentDate->addDay();
            }

            // ==================================================================================
            // SCALE rolling data so the latest value matches the table (live campaign data)
            // This preserves the trend shape while ensuring absolute values are correct
            // Uses the same fetchAdMetricsFromTables() source as the table display
            // ==================================================================================
            if (!empty($chartData) && ($adTypeSpend > 0 || $adTypeClicks > 0 || $adTypeSales > 0)) {
                    $correctValue = null;
                    if ($metric === 'spend') {
                        $correctValue = $adTypeSpend;
                    } elseif ($metric === 'clicks') {
                        $correctValue = $adTypeClicks;
                    } elseif ($metric === 'sales') {
                        $correctValue = $adTypeSales;
                    } elseif ($metric === 'sold') {
                        $correctValue = $adTypeSold;
                    } elseif ($metric === 'acos') {
                        $correctValue = $adTypeSales > 0 ? round(($adTypeSpend / $adTypeSales) * 100, 1) : null;
                    } elseif ($metric === 'cvr') {
                        $correctValue = $adTypeClicks > 0 ? round(($adTypeSold / $adTypeClicks) * 100, 1) : null;
                    }

                    // Apply scale factor if we have a valid reference value
                    if ($correctValue !== null && $correctValue > 0) {
                        $rollingLatest = end($chartData)['value'];
                        if ($rollingLatest > 0 && abs($rollingLatest - $correctValue) > 0.01) {
                            $scaleFactor = $correctValue / $rollingLatest;
                            foreach ($chartData as &$point) {
                                $point['value'] = round($point['value'] * $scaleFactor, 2);
                            }
                            unset($point);
                        }
                    }
            }

            // Smooth out consecutive duplicate values
            $chartData = $this->interpolateChartData($chartData);

            return response()->json([
                'success' => true,
                'channel' => $channel,
                'ad_type' => $adType,
                'metric' => $metric,
                'data' => $chartData
            ]);

        } catch (\Exception $e) {
            \Log::error('getAdBreakdownChartData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error fetching chart data'], 500);
        }
    }

    /**
     * Archive a channel (soft delete by setting status to 'Inactive')
     */
    public function archiveChannel(Request $request)
    {
        try {
            $channelName = $request->input('channel');
            
            if (!$channelName) {
                return response()->json(['success' => false, 'message' => 'Channel name is required'], 400);
            }

            // Find the channel
            $channel = ChannelMaster::where('channel', $channelName)->first();
            
            if (!$channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
            }

            // Archive by setting status to Inactive
            $channel->status = 'Inactive';
            $channel->save();

            \Log::info('Channel archived: ' . $channelName);

            return response()->json([
                'success' => true, 
                'message' => 'Channel archived successfully',
                'channel' => $channelName
            ]);

        } catch (\Exception $e) {
            \Log::error('Archive channel error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error archiving channel: ' . $e->getMessage()], 500);
        }
    }

}
