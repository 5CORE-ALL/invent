<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Jobs\UpdateDobaSPriceJob;
use App\Models\ChannelMaster;
use App\Models\DobaDailyData;
use App\Models\DobaDataView;
use App\Models\DobaWithoutShipDataView;
use App\Models\DobaListingStatus;
use App\Models\DobaMetric;
use App\Models\MarketplacePercentage;
use App\Models\ShopifySku;
use App\Models\ProductMaster; // Add this at the top with other use statements
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Services\ChannelPromoPricingService;
use App\Services\DobaApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log; // Ensure you import Log for logging
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class DobaController extends Controller
{
    /** Normalized match for doba_daily_data.order_type (case-insensitive in SQL). */
    private const DOBA_DAILY_ORDER_TYPE_PICKUP_PREPAID_LABEL = 'pickup with a prepaid label';

    /** Seller-console edit URL: https://seller.doba.com/ds/goods/save?goodsId=...&catId=... */
    private const DOBA_SELLER_GOODS_SAVE_URL = 'https://seller.doba.com/ds/goods/save';

    protected $apiController;

    /**
     * Build Doba seller edit URL from goodsId + catId.
     */
    private function buildDobaSellerLink(?string $goodsId, ?string $catId): string
    {
        $goodsId = trim((string) $goodsId);
        $catId = trim((string) $catId);
        if ($goodsId === '' || $catId === '') {
            return '';
        }

        return self::DOBA_SELLER_GOODS_SAVE_URL
            . '?goodsId=' . rawurlencode($goodsId)
            . '&catId=' . rawurlencode($catId);
    }

    /**
     * Extract goodsId / catId from a seller.doba.com goods/save URL.
     *
     * @return array{0:?string,1:?string}
     */
    private function parseDobaSellerLinkIds(?string $url): array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return [null, null];
        }

        $goodsId = null;
        $catId = null;
        if (preg_match('/[?&]goodsId=([^&]+)/i', $url, $m)) {
            $goodsId = rawurldecode($m[1]);
        }
        if (preg_match('/[?&]catId=([^&]+)/i', $url, $m)) {
            $catId = rawurldecode($m[1]);
        }

        return [
            ($goodsId !== null && $goodsId !== '') ? $goodsId : null,
            ($catId !== null && $catId !== '') ? $catId : null,
        ];
    }

    /**
     * Persist goods_id / cat_id on doba_metrics when known (from link save or API).
     */
    private function persistDobaGoodsCatIds(string $sku, ?string $goodsId, ?string $catId): void
    {
        $goodsId = trim((string) $goodsId);
        $catId = trim((string) $catId);
        if ($goodsId === '' || $catId === '') {
            return;
        }

        $metric = DobaMetric::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])
            ->first();
        if (! $metric) {
            return;
        }

        $metric->goods_id = $goodsId;
        $metric->cat_id = $catId;
        $metric->save();
    }

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function updatePrice(Request $request)
    {
        $sku = $request["sku"];
        $price = $request["price"];

        $result = UpdateDobaSPriceJob::dispatch($sku, $price)->delay(now()->addMinutes(3));

        return response()->json(['status' => 200]);
    }

    public function dobaView(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage from MarketplacePercentage table (consistent with other marketplaces)
        $marketplaceData = MarketplacePercentage::where('marketplace', 'Doba')->first();
        
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;
        $adUpdates = $marketplaceData ? $marketplaceData->ad_updates : 0;

        return view("market-places.doba-analytics", [
            "mode" => $mode,
            "demo" => $demo,
            "dobaPercentage" => $percentage,
        ]);
    }



    public function dobaPricingCVR(Request $request)
    {
        $mode = $request->query("mode");
        $demo = $request->query("demo");

        // Get percentage directly from database (no cache)
        $marketplaceData = MarketplacePercentage::where("marketplace", "Doba")->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view("market-places.doba_pricing_cvr", [
            "mode" => $mode,
            "demo" => $demo,
            "dobaPercentage" => $percentage,
        ]);
    }

    public function getViewdobaData(Request $request)
    {
        return $this->buildViewDobaListingData($request, false);
    }

    /**
     * Same grid as /doba-data-view but all doba_daily_data aggregates (S L30, L30/L60/L7 averages and sold quantities)
     * are limited to order_type "Pickup with a prepaid label".
     */
    public function getViewDobaDataWithoutShip(Request $request)
    {
        return $this->buildViewDobaListingData($request, true);
    }

    /**
     * Build /doba-tabulator (and withoutship) listing rows.
     *
     * Sold quantities and averages always come from doba_daily_data (same source as /doba/daily-sales):
     * - regular page: excludes order_type "Pickup with a prepaid label"
     * - withoutship page: only that order_type
     */
    private function buildViewDobaListingData(Request $request, bool $onlyPickupPrepaidLabelFromDaily): \Illuminate\Http\JsonResponse
    {
        // Normalized SKU matcher (case/whitespace-insensitive), same approach as other marketplaces
        $normalizeSku = static fn ($s) => strtoupper(trim((string) $s));

        // 1. Show ALL product_master SKUs (same as other marketplaces); Doba data is overlaid where it matches
        $productMasters = ProductMaster::whereNotNull('sku')
            ->where('sku', '!=', '')
            ->orderBy("parent", "asc")
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy("sku", "asc")
            ->get();

        // 2. SKU list (actual product_master SKUs; Shopify lookup normalizes internally)
        $skus = $productMasters
            ->pluck("sku")
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 3. Related Models — keyed by NORMALIZED sku so overlay works despite case/whitespace differences
        $shopifyData = ShopifySku::mapByProductSkus($skus);
        $dobaMetrics = DobaMetric::all()->keyBy(fn ($m) => $normalizeSku($m->sku));
        // NR lives in the shared doba_data_view table on both pages, but SPRICE
        // (and related SPFT/SROI/S_SELF_PICK/PUSH_STATUS) is stored separately
        // for the "without ship" page so prices set there don't overwrite the
        // regular Doba (with ship) page's prices.
        $dobaDataValues = DobaDataView::all()
            ->keyBy(fn ($r) => $normalizeSku($r->sku))
            ->map(fn ($r) => $r->value);
        $nrValues = $dobaDataValues;
        $spriceValues = $onlyPickupPrepaidLabelFromDaily
            ? DobaWithoutShipDataView::all()
                ->keyBy(fn ($r) => $normalizeSku($r->sku))
                ->map(fn ($r) => $r->value)
            : $dobaDataValues;

        // Buyer / Seller links stored per SKU in doba_listing_statuses.value JSON
        $linkValues = DobaListingStatus::all()
            ->keyBy(fn ($r) => $normalizeSku($r->sku))
            ->map(fn ($r) => $r->value);

        // Fetch Amazon prices for comparison
        $amazonPrices = AmazonDatasheet::select('sku', 'price')->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku))
            ->map(fn ($r) => $r->price);

        // STD PRC (STANDARD_PRICE) from amazon_data_view — same store as /amazon-tabulator-view SP
        $amazonStandardPrices = AmazonDataView::all()
            ->keyBy(fn ($r) => $normalizeSku($r->sku))
            ->map(function ($r) {
                $val = is_array($r->value) ? $r->value : (json_decode((string) $r->value, true) ?: []);
                $std = $val['STANDARD_PRICE'] ?? null;

                return (is_numeric($std) && floatval($std) > 0) ? round(floatval($std), 2) : 0;
            });

        $applyDailyFilters = function ($query) use ($onlyPickupPrepaidLabelFromDaily) {
            $query->where(function ($q) {
                $q->whereNotIn('order_status', ['Canceled', 'Cancelled', 'CANCELED', 'CANCELLED', 'canceled', 'cancelled'])
                    ->orWhereNull('order_status');
            });
            if ($onlyPickupPrepaidLabelFromDaily) {
                $query->whereRaw(
                    'LOWER(TRIM(COALESCE(order_type, ?))) = ?',
                    ['', self::DOBA_DAILY_ORDER_TYPE_PICKUP_PREPAID_LABEL]
                );
            } else {
                // /doba-tabulator: daily-sales orders minus pickup + prepaid label
                // (those belong on /doba-tabulator-withoutship).
                $query->whereRaw(
                    'LOWER(TRIM(COALESCE(order_type, ?))) <> ?',
                    ['', self::DOBA_DAILY_ORDER_TYPE_PICKUP_PREPAID_LABEL]
                );
            }
        };

        // 5. Aggregate S L30 from doba_daily_data - sum quantity by SKU for L30 period, excluding cancelled orders
        $dobaDailyL30 = DB::table('doba_daily_data')
            ->select(
                'sku',
                DB::raw('SUM(quantity) as s_l30_count')
            )
            ->whereRaw("LOWER(period) = 'l30'")
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        // Calculate L30 Average Price from doba_daily_data
        $l30AvgPrice = DB::table('doba_daily_data')
            ->select(
                'sku',
                DB::raw('SUM(total_price) as total_sales'),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereRaw("LOWER(period) = 'l30'")
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        // Calculate L60 Average Price from doba_daily_data
        $l60AvgPrice = DB::table('doba_daily_data')
            ->select(
                'sku',
                DB::raw('SUM(total_price) as total_sales'),
                DB::raw('SUM(quantity) as total_quantity')
            )
            ->whereRaw("LOWER(period) = 'l60'")
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        // Sold qty with order_time from 45 days through 15 days before yesterday (excludes most recent 15 days)
        $yesterday = Carbon::yesterday();
        $l45End = $yesterday->copy()->subDays(15)->endOfDay();
        $l45Start = $yesterday->copy()->subDays(45)->startOfDay();
        $dobaDailyL45 = DB::table('doba_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->whereBetween('order_time', [$l45Start, $l45End])
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        $l7End = $yesterday->copy()->endOfDay();
        $l7Start = $yesterday->copy()->subDays(6)->startOfDay();
        $l7prevEnd = $yesterday->copy()->subDays(7)->endOfDay();
        $l7prevStart = $yesterday->copy()->subDays(13)->startOfDay();

        $dailyL7 = DB::table('doba_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->whereBetween('order_time', [$l7Start, $l7End])
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        $dailyL7Prev = DB::table('doba_daily_data')
            ->select('sku', DB::raw('SUM(quantity) as total_quantity'))
            ->whereBetween('order_time', [$l7prevStart, $l7prevEnd])
            ->tap($applyDailyFilters)
            ->groupBy('sku')
            ->get()
            ->keyBy(fn ($r) => $normalizeSku($r->sku));

        // 6. Get marketplace percentage (no cache)
        $percentage = (MarketplacePercentage::where("marketplace", "Doba")->value("percentage") ?? 100) / 100;

        // PRMT%/CPN%/DSC%/Appr/Push Prc — doba / doba_withoutship_promo_pricing (site-specific)
        $promoChannel = $onlyPickupPrepaidLabelFromDaily ? 'doba_withoutship' : 'doba';
        $promoMap = app(ChannelPromoPricingService::class)->mapForSkus($promoChannel, $skus);

        // 7. Build Result
        $result = [];

        foreach ($productMasters as $pm) {
            $sku = strtoupper($pm->sku);
            $normSku = $normalizeSku($pm->sku);
            $parent = $pm->parent;
            $shopify = $shopifyData->get($pm->sku);
            $dobaMetric = $dobaMetrics[$normSku] ?? null;

            $row = [];
            $row["Parent"] = $parent;
            $row["(Child) sku"] = $pm->sku;

            // INV from Doba inventory (doba_metrics.inventory)
            $row["INV"] = (int) ($dobaMetric->inventory ?? 0);
            // Shopify inventory shown as a separate column
            $row["shopify_inv"] = (int) ($shopify->inv ?? 0);
            // Missing = SKU not listed on Doba (no doba_metrics record), same idea as is_missing_amazon
            $row["is_missing_doba"] = $dobaMetric ? false : true;
            // L30 (overall) still from Shopify
            $row["L30"] = $shopify->quantity ?? 0;

            // Doba sold quantities from doba_daily_data (same source as /doba/daily-sales),
            // filtered by order_type via $applyDailyFilters above.
            $l30Daily = $dobaDailyL30[$normSku] ?? null;
            $l60Daily = $l60AvgPrice[$normSku] ?? null;
            $row["doba L30"] = $l30Daily ? (int) $l30Daily->s_l30_count : 0;
            $row["doba L60"] = (int) ($l60Daily?->total_quantity ?? 0);
            $row["quantity_l7"] = (int) ($dailyL7[$normSku]->total_quantity ?? 0);
            $row["quantity_l7_prev"] = (int) ($dailyL7Prev[$normSku]->total_quantity ?? 0);
            $row['doba L45'] = (int) ($dobaDailyL45[$normSku]->total_quantity ?? 0);
            $listPrice = floatval($dobaMetric->anticipated_income ?? 0);
            $selfPickMetric = floatval($dobaMetric->self_pick_price ?? 0);
            $row['doba_item_id'] = $dobaMetric->item_id ?? null;
            $row['self_pick_price'] = $selfPickMetric;

            // Without-ship: main column + all margin math use self_pick only; listing (anticipated) is never used in formulas
            if ($onlyPickupPrepaidLabelFromDaily) {
                $row['doba_list_price'] = $listPrice;
                $row['doba Price'] = $selfPickMetric;
            } else {
                $row['doba Price'] = $listPrice;
            }
            $row['msrp'] = $dobaMetric->msrp ?? 0;
            $row['map'] = $dobaMetric->map ?? 0;
            
            // Amazon Price for comparison
            $row['amazon_price'] = isset($amazonPrices[$normSku]) ? floatval($amazonPrices[$normSku]) : 0;
            // STD PRC — amazon_data_view.STANDARD_PRICE (same as /price-increase STD PRC)
            $row['standard_price'] = isset($amazonStandardPrices[$normSku])
                ? floatval($amazonStandardPrices[$normSku])
                : 0;

            // S L30 from doba_daily_data (excluding cancelled orders)
            $sL30Data = $dobaDailyL30[$normSku] ?? null;
            $row["s_l30"] = $sL30Data ? (int) $sL30Data->s_l30_count : 0;

            // Calculate L30 Average Price
            $l30AvgData = $l30AvgPrice[$normSku] ?? null;
            $row["l30_avg_price"] = 0;
            if ($l30AvgData && $l30AvgData->total_quantity > 0) {
                $row["l30_avg_price"] = round($l30AvgData->total_sales / $l30AvgData->total_quantity, 2);
            }

            // Calculate L60 Average Price
            $l60AvgData = $l60AvgPrice[$normSku] ?? null;
            $row["l60_avg_price"] = 0;
            if ($l60AvgData && $l60AvgData->total_quantity > 0) {
                $row["l60_avg_price"] = round($l60AvgData->total_sales / $l60AvgData->total_quantity, 2);
            }

            // Values: LP & Ship
            $values = is_array($pm->Values)
                ? $pm->Values
                : (is_string($pm->Values)
                    ? json_decode($pm->Values, true)
                    : []);
            $lp = 0;
            foreach ($values as $k => $v) {
                if (strtolower($k) === "lp") {
                    $lp = floatval($v);
                    break;
                }
            }
            if ($lp === 0 && isset($pm->lp)) {
                $lp = floatval($pm->lp);
            }
            $ship = isset($values["ship"])
                ? floatval($values["ship"])
                : (isset($pm->ship)
                    ? floatval($pm->ship)
                    : 0);

            // Without-ship page: still show product SHIP in the grid, but omit it from PFT/ROI totals
            $shipInFormula = $onlyPickupPrepaidLabelFromDaily ? 0.0 : $ship;

            // Price for PFT/ROI: without-ship = self pick only (never anticipated/list price)
            $price = $onlyPickupPrepaidLabelFromDaily
                ? $selfPickMetric
                : floatval($row["doba Price"] ?? 0);
            $units_ordered_l30 = floatval($row["doba L30"] ?? 0);

            $row["Total_pft"] = round(
                ($price * $percentage - $lp - $shipInFormula) * $units_ordered_l30,
                2
            );
            $row["T_Sale_l30"] = round($price * $units_ordered_l30, 2);
            $row["PFT_percentage"] = round(
                $price > 0
                    ? (($price * $percentage - $lp - $shipInFormula) / $price) * 100
                    : 0,
                2
            );
            $row["ROI_percentage"] = round(
                $lp > 0
                    ? (($price * $percentage - $lp - $shipInFormula) / $lp) * 100
                    : 0,
                2
            );
            $row["T_COGS"] = round($lp * $units_ordered_l30, 2);

            $row["percentage"] = $percentage;
            $row["LP_productmaster"] = $lp;
            $row["Ship_productmaster"] = $ship;

            // NR & Hide

            $row['NR'] = null;
            $row['SPRICE'] = null;
            $row['SPFT'] = null;
            $row['SROI'] = null;
            $row['S_SELF_PICK'] = null;
            $row['PROMO'] = null;
            $row['PROMO_PCT'] = null;
            $row['PROMO_PU'] = null;
            $row['PUSH_STATUS'] = null;
            $row['PUSH_STATUS_UPDATED_AT'] = null;
            $row['Listed'] = null;
            $row['Live'] = null;
            $row['APlus'] = null;

            // NR / Listed / Live / APlus always come from the shared doba_data_view
            // table (these are not "with ship" vs "without ship" specific).
            if (isset($nrValues[$normSku])) {
                $raw = $nrValues[$normSku];

                if (!is_array($raw)) {
                    $raw = json_decode($raw, true);
                }

                if (is_array($raw)) {
                    $row['NR'] = $raw['NR'] ?? null;
                    $row['Listed'] = isset($raw['Listed']) ? filter_var($raw['Listed'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['Live'] = isset($raw['Live']) ? filter_var($raw['Live'], FILTER_VALIDATE_BOOLEAN) : null;
                    $row['APlus'] = isset($raw['APlus']) ? filter_var($raw['APlus'], FILTER_VALIDATE_BOOLEAN) : null;
                }
            }

            // SPRICE / SPFT / SROI / S_SELF_PICK / PUSH_STATUS come from the
            // page-specific table: doba_data_view (with ship) vs
            // doba_withoutship_data_view (without ship).
            if (isset($spriceValues[$normSku])) {
                $rawSprice = $spriceValues[$normSku];

                if (!is_array($rawSprice)) {
                    $rawSprice = json_decode($rawSprice, true);
                }

                if (is_array($rawSprice)) {
                    $row['SPRICE'] = $rawSprice['SPRICE'] ?? null;
                    $row['SPFT'] = $rawSprice['SPFT'] ?? null;
                    $row['SROI'] = $rawSprice['SROI'] ?? null;
                    $row['S_SELF_PICK'] = $rawSprice['S_SELF_PICK'] ?? null;
                    $row['PROMO'] = $rawSprice['PROMO'] ?? null;
                    $row['PROMO_PCT'] = $rawSprice['PROMO_PCT'] ?? null;
                    $row['PROMO_PU'] = $rawSprice['PROMO_PU'] ?? null;
                    $row['PUSH_STATUS'] = $rawSprice['PUSH_STATUS'] ?? null;
                    $row['PUSH_STATUS_UPDATED_AT'] = $rawSprice['PUSH_STATUS_UPDATED_AT'] ?? null;
                }
            }

            // Buyer / Seller links — Seller is auto-built from goodsId+catId when available:
            // https://seller.doba.com/ds/goods/save?goodsId={goodsId}&catId={catId}
            $bLink = '';
            $storedSellerLink = '';
            if (isset($linkValues[$normSku])) {
                $linkRaw = $linkValues[$normSku];
                if (!is_array($linkRaw)) {
                    $linkRaw = json_decode($linkRaw, true);
                }
                if (is_array($linkRaw)) {
                    $bLink = $linkRaw['buyer_link'] ?? '';
                    $storedSellerLink = trim((string) ($linkRaw['seller_link'] ?? ''));
                }
            }

            $goodsId = trim((string) ($dobaMetric->goods_id ?? ''));
            $catId = trim((string) ($dobaMetric->cat_id ?? ''));
            if (($goodsId === '' || $catId === '') && $storedSellerLink !== '') {
                [$parsedGoodsId, $parsedCatId] = $this->parseDobaSellerLinkIds($storedSellerLink);
                if ($goodsId === '' && $parsedGoodsId) {
                    $goodsId = $parsedGoodsId;
                }
                if ($catId === '' && $parsedCatId) {
                    $catId = $parsedCatId;
                }
            }

            $autoSellerLink = $this->buildDobaSellerLink($goodsId, $catId);
            $sLink = $autoSellerLink !== '' ? $autoSellerLink : $storedSellerLink;

            $row['doba_goods_id'] = $goodsId !== '' ? $goodsId : null;
            $row['doba_cat_id'] = $catId !== '' ? $catId : null;
            $row['B Link'] = $bLink;
            $row['S Link'] = $sLink;
            $row['raw_data'] = ['B Link' => $bLink, 'S Link' => $sLink];

            // Image
            $row["image_path"] =
                $shopify->image_src ??
                ($values["image_path"] ?? ($pm->image_path ?? null));

            // CVR% = Doba L30 ÷ OV L30 (same as CVR filter on this page)
            $ovL30 = (float) ($row['L30'] ?? 0);
            $dobaL30 = (float) ($row['doba L30'] ?? 0);
            $row['CVR%'] = $ovL30 > 0 ? round(($dobaL30 / $ovL30) * 100, 2) : 0;
            $row = app(ChannelPromoPricingService::class)->applyToRow($row, $promoMap, (string) $pm->sku);

            $result[] = (object) $row;
        }

        return response()->json([
            "message" => "doba Data Fetched Successfully",
            "data" => $result,
            "status" => 200,
        ]);
    }

    /**
     * Save buyer / seller links for a SKU into doba_listing_statuses.value JSON.
     * Empty strings clear the link (URL validation only applies to non-empty values).
     */
    public function saveLinks(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku) {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }

        $buyerLink = trim((string) $request->input('buyer_link', ''));
        $sellerLink = trim((string) $request->input('seller_link', ''));

        foreach (['buyer_link' => $buyerLink, 'seller_link' => $sellerLink] as $field => $val) {
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
                return response()->json(['success' => false, 'message' => 'Invalid URL for ' . $field], 422);
            }
        }

        $status = DobaListingStatus::firstOrNew(['sku' => $sku]);
        $existing = is_array($status->value)
            ? $status->value
            : (json_decode($status->value, true) ?: []);

        // Prefer canonical auto URL when the pasted seller link carries goodsId+catId.
        if ($sellerLink !== '') {
            [$goodsId, $catId] = $this->parseDobaSellerLinkIds($sellerLink);
            $autoSellerLink = $this->buildDobaSellerLink($goodsId, $catId);
            if ($autoSellerLink !== '') {
                $sellerLink = $autoSellerLink;
                $this->persistDobaGoodsCatIds($sku, $goodsId, $catId);
            }
        }

        $existing['buyer_link'] = $buyerLink !== '' ? $buyerLink : null;
        $existing['seller_link'] = $sellerLink !== '' ? $sellerLink : null;

        $status->value = $existing;
        $status->save();

        return response()->json([
            'success' => true,
            'buyer_link' => $existing['buyer_link'],
            'seller_link' => $existing['seller_link'],
        ]);
    }

    public function updateAlldobaSkus(Request $request)
    {
        try {
            $percent = $request->input("percent");

            if (!is_numeric($percent) || $percent < 0 || $percent > 100) {
                return response()->json(
                    [
                        "status" => 400,
                        "message" =>
                        "Invalid percentage value. Must be between 0 and 100.",
                    ],
                    400
                );
            }

            // Update database
            MarketplacePercentage::updateOrCreate(
                ["marketplace" => "Doba"],
                ["percentage" => $percent]
            );

            return response()->json([
                "status" => 200,
                "message" => "Percentage updated successfully",
                "data" => [
                    "marketplace" => "Doba",
                    "percentage" => $percent,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "status" => 500,
                    "message" => "Error updating percentage",
                    "error" => $e->getMessage(),
                ],
                500
            );
        }
    }

    // Save NR value for a SKU
    public function saveNrToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        $nrInput = $request->input('nr'); // This could be string or JSON string

        if (!$sku || !$nrInput) {
            return response()->json(['error' => 'SKU and NR are required.'], 400);
        }

        // Normalize NR Input
        $nrValue = null;

        // If NR is a JSON string, decode it
        if (is_string($nrInput)) {
            $decoded = json_decode($nrInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['NR'])) {
                $nrValue = $decoded['NR'];
            } else {
                $nrValue = $nrInput;
            }
        } elseif (is_array($nrInput) && isset($nrInput['NR'])) {
            $nrValue = $nrInput['NR'];
        }

        // Fetch or create the record
        $dobaDataView = DobaDataView::firstOrNew(['sku' => $sku]);

        // Decode existing JSON value
        $existing = is_array($dobaDataView->value)
            ? $dobaDataView->value
            : (json_decode($dobaDataView->value, true) ?: []);

        // Update NR in existing data
        $existing['NR'] = $nrValue;

        // Save merged data
        $dobaDataView->value = $existing;
        $dobaDataView->save();

        return response()->json(['success' => true, 'data' => $dobaDataView]);
    }


    public function saveSpriceToDatabase(Request $request)
    {
        return $this->persistSpriceRow($request, DobaDataView::class);
    }

    /**
     * Save Promo % into doba_data_view value JSON (with-ship page).
     * PROMO_PCT is the discount percentage (e.g. 5). Legacy PROMO $ amounts are ignored for display.
     */
    public function savePromoToDatabase(Request $request)
    {
        $sku = $request->input('sku');
        if (!$sku || !$request->has('promo')) {
            return response()->json(['error' => 'SKU and promo are required.'], 400);
        }

        $dataView = DobaDataView::firstOrNew(['sku' => $sku]);
        $existing = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        $pct = $request->input('promo');
        $merged = array_merge($existing, [
            'PROMO_PCT' => $pct,
            // Keep PROMO in sync as % going forward (replaces legacy dollar values)
            'PROMO' => $pct,
        ]);

        $dataView->value = $merged;
        $dataView->save();

        return response()->json(['message' => 'Promo saved successfully.']);
    }

    /**
     * Save SPRICE row coming from the "Doba without ship" (pickup / prepaid
     * label) page. Stored in its own table so it does not collide with the
     * regular Doba (with ship) page.
     */
    public function saveSpriceWithoutShipToDatabase(Request $request)
    {
        return $this->persistSpriceRow($request, DobaWithoutShipDataView::class);
    }

    /**
     * Shared persistence for both SPRICE save endpoints. The model class
     * decides which table the row lives in.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function persistSpriceRow(Request $request, string $modelClass)
    {
        $sku = $request->input('sku');
        $spriceData = $request->only(['sprice', 'spft_percent', 'sroi_percent', 's_self_pick', 'push_status']);

        if (!$sku || !isset($spriceData['sprice'])) {
            return response()->json(['error' => 'SKU and sprice are required.'], 400);
        }

        $dataView = $modelClass::firstOrNew(['sku' => $sku]);

        // Decode value column safely
        $existing = is_array($dataView->value)
            ? $dataView->value
            : (json_decode($dataView->value, true) ?: []);

        // Merge new sprice data
        $merged = array_merge($existing, [
            'SPRICE' => $spriceData['sprice'],
            'SPFT' => $spriceData['spft_percent'],
            'SROI' => $spriceData['sroi_percent'],
            'S_SELF_PICK' => $spriceData['s_self_pick'] ?? null,
            'PUSH_STATUS' => $spriceData['push_status'] ?? null,
            'PUSH_STATUS_UPDATED_AT' => isset($spriceData['push_status']) ? now()->format('Y-m-d H:i:s') : ($existing['PUSH_STATUS_UPDATED_AT'] ?? null),
        ]);

        $dataView->value = $merged;
        $dataView->save();

        return response()->json(['message' => 'Data saved successfully.']);
    }

    /**
     * Push price to Doba API.
     *
     * mode=full (default): push listing price (+ optional self pick).
     * mode=pickup: push only selfPickAnticipatedIncome (prepaid / pickup price).
     */
    public function pushPriceToDoba(Request $request)
    {
        $sku = $request->input('sku');
        $price = $request->input('price');
        $selfPickPrice = $request->input('self_pick_price'); // Optional for full; required for pickup
        $mode = strtolower((string) $request->input('mode', 'full'));
        $isPickupOnly = $mode === 'pickup';

        if (!$sku) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'SKU is required.']]
            ], 400);
        }

        if ($isPickupOnly) {
            if ($selfPickPrice === null || $selfPickPrice === '' || floatval($selfPickPrice) <= 0) {
                return response()->json([
                    'success' => false,
                    'errors' => [['message' => 'Pickup price (self_pick_price) is required.']]
                ], 400);
            }
            $price = null; // do not change listing anticipatedIncome
        } elseif (!$price) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'SKU and price are required.']]
            ], 400);
        }

        // Get the item_id from DobaMetric table
        $dobaMetric = DobaMetric::where('sku', $sku)->first();

        if (!$dobaMetric || !$dobaMetric->item_id) {
            return response()->json([
                'success' => false,
                'errors' => [['message' => 'Item ID not found for this SKU. Please run Doba metrics fetch first.']]
            ], 404);
        }

        $itemId = $dobaMetric->item_id;

        try {
            $dobaApiService = new DobaApiService();

            // Only call Price API (Sale API disabled - requires special permission)
            $priceResult = $dobaApiService->updateItemPrice($itemId, $price, $selfPickPrice);

            // Check for errors
            if (isset($priceResult['errors'])) {
                Log::warning('Doba price push failed', [
                    'sku' => $sku,
                    'item_id' => $itemId,
                    'mode' => $mode,
                    'price' => $price,
                    'self_pick_price' => $selfPickPrice,
                    'error' => $priceResult['errors']
                ]);

                return response()->json([
                    'success' => false,
                    'errors' => [['message' => 'Price update: ' . $priceResult['errors']]],
                    'data' => [
                        'mode' => $mode,
                        'request_payload' => [
                            'itemNo' => $itemId,
                            'anticipatedIncome' => $price,
                            'selfPickAnticipatedIncome' => $selfPickPrice,
                        ],
                        'price_update' => $priceResult['debug'] ?? $priceResult,
                    ]
                ], 400);
            }

            // Success — update local DobaMetric to match what we pushed
            if (!$isPickupOnly) {
                $dobaMetric->anticipated_income = $price;
            }
            if ($selfPickPrice !== null && $selfPickPrice !== '') {
                $dobaMetric->self_pick_price = $selfPickPrice;
            }
            $dobaMetric->save();

            Log::info('Doba price push successful', [
                'sku' => $sku,
                'item_id' => $itemId,
                'mode' => $mode,
                'price' => $price,
                'self_pick_price' => $selfPickPrice
            ]);

            return response()->json([
                'success' => true,
                'message' => $isPickupOnly
                    ? 'Pickup price pushed to Doba successfully'
                    : 'Price pushed to Doba successfully',
                'data' => [
                    'mode' => $mode,
                    'request_payload' => [
                        'itemNo' => $itemId,
                        'anticipatedIncome' => $price,
                        'selfPickAnticipatedIncome' => $selfPickPrice,
                    ],
                    'price_update' => $priceResult
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Doba price push exception', [
                'sku' => $sku,
                'item_id' => $itemId,
                'mode' => $mode,
                'price' => $price,
                'self_pick_price' => $selfPickPrice,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'errors' => [['message' => 'API Exception: ' . $e->getMessage()]]
            ], 500);
        }
    }

    public function saveLowProfit(Request $request)
    {
        $count = $request->input('count');

        $channel = ChannelMaster::where('channel', 'Doba')->first();

        if (!$channel) {
            return response()->json(['success' => false, 'message' => 'Channel not found'], 404);
        }

        $channel->red_margin = $count;
        $channel->save();

        return response()->json(['success' => true]);
    }

    public function updateListedLive(Request $request)
    {
        $request->validate([
            'sku'   => 'required|string',
            'field' => 'required|in:Listed,Live',
            'value' => 'required|boolean' // validate as boolean
        ]);

        // Find or create the product without overwriting existing value
        $product = DobaDataView::firstOrCreate(
            ['sku' => $request->sku],
            ['value' => []]
        );

        // Decode current value (ensure it's an array)
        $currentValue = is_array($product->value)
            ? $product->value
            : (json_decode($product->value, true) ?? []);

        // Store as actual boolean
        $currentValue[$request->field] = filter_var($request->value, FILTER_VALIDATE_BOOLEAN);

        // Save back to DB
        $product->value = $currentValue;
        $product->save();

        return response()->json(['success' => true]);
    }

    public function importDobaAnalytics(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathName());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Clean headers
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            unset($rows[0]);

            $allSkus = [];
            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $allSkus[] = $row[0];
                }
            }

            $existingSkus = ProductMaster::whereIn('sku', $allSkus)
                ->pluck('sku')
                ->toArray();

            $existingSkus = array_flip($existingSkus);

            $importCount = 0;
            foreach ($rows as $index => $row) {
                if (empty($row[0])) { // Check if SKU is empty
                    continue;
                }

                // Ensure row has same number of elements as headers
                $rowData = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
                $data = array_combine($headers, $rowData);

                if (!isset($data['sku']) || empty($data['sku'])) {
                    continue;
                }

                // Only import SKUs that exist in product_masters (in-memory check)
                if (!isset($existingSkus[$data['sku']])) {
                    continue;
                }

                // Prepare values array
                $values = [];

                // Handle boolean fields
                if (isset($data['listed'])) {
                    $values['Listed'] = filter_var($data['listed'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($data['live'])) {
                    $values['Live'] = filter_var($data['live'], FILTER_VALIDATE_BOOLEAN);
                }

                // Update or create record
                DobaDataView::updateOrCreate(
                    ['sku' => $data['sku']],
                    ['value' => $values]
                );

                $importCount++;
            }

            return back()->with('success', "Successfully imported $importCount records!");
        } catch (\Exception $e) {
            return back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function exportDobaAnalytics()
    {
        $dobaData = DobaDataView::all();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Data Rows
        $rowIndex = 2;
        foreach ($dobaData as $data) {
            $values = is_array($data->value)
                ? $data->value
                : (json_decode($data->value, true) ?? []);

            $sheet->fromArray([
                $data->sku,
                isset($values['Listed']) ? ($values['Listed'] ? 'TRUE' : 'FALSE') : 'FALSE',
                isset($values['Live']) ? ($values['Live'] ? 'TRUE' : 'FALSE') : 'FALSE',
            ], NULL, 'A' . $rowIndex);

            $rowIndex++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Doba_Analytics_Export_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function downloadSample()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Row
        $headers = ['SKU', 'Listed', 'Live'];
        $sheet->fromArray($headers, NULL, 'A1');

        // Sample Data
        $sampleData = [
            ['SKU001', 'TRUE', 'FALSE'],
            ['SKU002', 'FALSE', 'TRUE'],
            ['SKU003', 'TRUE', 'TRUE'],
        ];

        $sheet->fromArray($sampleData, NULL, 'A2');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(10);

        // Output Download
        $fileName = 'Doba_Analytics_Sample.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function dobaTabulatorView(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        // Get percentage directly from database (no cache)
        $marketplaceData = MarketplacePercentage::where("marketplace", "Doba")->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view('market-places.doba_tabulator_view', [
            'mode' => $mode,
            'demo' => $demo,
            'dobaPercentage' => $percentage,
        ]);
    }

    public function dobaTabulatorViewWithoutShip(Request $request)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');

        $marketplaceData = MarketplacePercentage::where("marketplace", "Doba")->first();
        $percentage = $marketplaceData ? $marketplaceData->percentage : 100;

        return view('market-places.doba_withoutship_tabulator_view', [
            'mode' => $mode,
            'demo' => $demo,
            'dobaPercentage' => $percentage,
        ]);
    }

    /**
     * L30 summary badges for /doba-tabulator.
     * Same source as /doba/daily-sales (doba_daily_data period L30), excluding
     * cancelled orders and order_type "Pickup with a prepaid label".
     */
    public function getDobaSummaryMetrics()
    {
        return $this->buildDobaDailySalesSummaryMetrics(false);
    }

    /**
     * L30 summary badges for /doba_withoutship.
     * Same source as /doba/daily-sales, counting ONLY order_type
     * "Pickup with a prepaid label" (ship omitted from PFT, matching daily-sales).
     */
    public function getDobaSummaryMetricsWithoutShip()
    {
        return $this->buildDobaDailySalesSummaryMetrics(true);
    }

    /**
     * Aggregate L30 sales / PFT / ROI from doba_daily_data.
     *
     * @param  bool  $onlyPickupPrepaidLabel  true = pickup only (withoutship); false = exclude pickup (regular)
     */
    private function buildDobaDailySalesSummaryMetrics(bool $onlyPickupPrepaidLabel): \Illuminate\Http\JsonResponse
    {
        $ordersQuery = DobaDailyData::whereRaw('LOWER(period) = ?', ['l30'])
            ->where(function ($q) {
                $q->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
                    ->orWhereNull('order_status');
            });

        if ($onlyPickupPrepaidLabel) {
            $ordersQuery->whereRaw(
                'LOWER(TRIM(COALESCE(order_type, ?))) = ?',
                ['', self::DOBA_DAILY_ORDER_TYPE_PICKUP_PREPAID_LABEL]
            );
        } else {
            $ordersQuery->whereRaw(
                'LOWER(TRIM(COALESCE(order_type, ?))) <> ?',
                ['', self::DOBA_DAILY_ORDER_TYPE_PICKUP_PREPAID_LABEL]
            );
        }

        $orders = $ordersQuery->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => $onlyPickupPrepaidLabel
                    ? 'No Pickup with a prepaid label metrics found for Doba'
                    : 'No metrics found for Doba',
            ], 404);
        }

        $skus = $orders->pluck('sku')->filter()->unique()->values()->toArray();
        $productMasters = ProductMaster::whereIn('sku', $skus)->get()->keyBy('sku');
        $margin = 0.95;

        $totalOrders = 0;
        $totalQuantity = 0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;
        $totalPft = 0.0;
        $totalWeightedPrice = 0.0;
        $totalQuantityForPrice = 0;

        foreach ($orders as $order) {
            if (!$order->sku || $order->sku === '') {
                continue;
            }

            $totalOrders++;
            $quantity = (int) ($order->quantity ?? 1);
            $itemPrice = (float) ($order->item_price ?? 0);
            $totalPrice = (float) ($order->total_price ?? 0);

            $totalQuantity += $quantity;
            $totalRevenue += $totalPrice;

            if ($quantity > 0 && $itemPrice > 0) {
                $totalWeightedPrice += $itemPrice * $quantity;
                $totalQuantityForPrice += $quantity;
            }

            $lp = 0.0;
            $ship = 0.0;
            if (isset($productMasters[$order->sku])) {
                $pm = $productMasters[$order->sku];
                $values = is_array($pm->Values) ? $pm->Values
                    : (is_string($pm->Values) ? json_decode($pm->Values, true) : []);
                if (isset($values['lp'])) {
                    $lp = floatval($values['lp']);
                }
                if (isset($values['ship'])) {
                    $ship = floatval($values['ship']);
                }
            }

            $cogs = $lp * $quantity;
            $totalCogs += $cogs;

            // Match DobaSalesController: pickup prepaid omits ship from PFT.
            if ($onlyPickupPrepaidLabel) {
                $pftEach = ($itemPrice * $margin) - $lp;
            } else {
                $pftEach = ($itemPrice * $margin) - $ship - $lp;
            }
            $totalPft += $pftEach * $quantity;
        }

        $avgPrice = $totalQuantityForPrice > 0 ? $totalWeightedPrice / $totalQuantityForPrice : 0;
        $pftPercentage = $totalRevenue > 0 ? ($totalPft / $totalRevenue) * 100 : 0;
        $roiPercentage = $totalCogs > 0 ? ($totalPft / $totalCogs) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'date' => Carbon::today()->toDateString(),
                'total_orders' => $totalOrders,
                'total_quantity' => $totalQuantity,
                'total_sales' => $totalRevenue,
                'total_cogs' => $totalCogs,
                'total_pft' => $totalPft,
                'pft_percentage' => round($pftPercentage, 1),
                'roi_percentage' => round($roiPercentage, 1),
                'avg_price' => $avgPrice,
            ]
        ]);
    }
}
