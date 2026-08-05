<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Channels\ChannelMasterController;
use App\Http\Controllers\Controller;
use App\Models\ChannelMasterCalculatedData;
use App\Models\PricingErrorsFixCalculatedData;
use App\Services\PricingErrorsFixCvrCacheBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Pricing Errors Fix — unified Tabulator across all marketplace channels.
 *
 * Cache filled from /price-increase CVR bulk (not channel pricing tabulators).
 * Page reads pricing_errors_fix_calculated_data (instant).
 */
class PricingErrorsFixController extends Controller
{
    /** @var array<string, float> */
    private array $adsPctCache = [];

    /** @var array<string, float> */
    private array $takeHomeCache = [];

    public function index(Request $request): View
    {
        $channels = [];
        foreach ($this->channelRegistry() as $key => $cfg) {
            $channels[] = ['key' => $key, 'label' => $cfg['label']];
        }

        $lastCalc = null;
        $initialRows = [];
        try {
            if (Schema::hasTable('pricing_errors_fix_calculated_data')
                && PricingErrorsFixCalculatedData::hasData()) {
                $lastCalc = PricingErrorsFixCalculatedData::lastCalculatedAt();
                // Embed cache in HTML — no Ajax "load" on first paint
                $initialRows = $this->fetchCacheRows(
                    array_keys($this->channelRegistry()),
                    filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('PricingErrorsFix index cache embed failed: '.$e->getMessage());
        }

        return view('market-places.pricing_errors_fix_view', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
            'channels' => $channels,
            'cache_calculated_at' => $lastCalc,
            'initial_rows' => $initialRows,
        ]);
    }

    /**
     * Aggregated rows: one row per SKU × channel.
     * Default = from pre-calculated table (instant).
     * Optional ?live=1 = live channel fan-out (slow).
     * Optional ?channel=amazon,ebay filters to a subset.
     */
    public function dataJson(Request $request): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        try {
            $live = filter_var($request->query('live', false), FILTER_VALIDATE_BOOLEAN);
            if (! $live && Schema::hasTable('pricing_errors_fix_calculated_data')
                && PricingErrorsFixCalculatedData::hasData()) {
                return $this->dataJsonFromCache($request);
            }

            // Empty-cache fallback: /price-increase CVR bulk (not channel pricing pages)
            $listedOnly = filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN);
            $wanted = $this->wantedChannelKeys($request);
            $built = app(PricingErrorsFixCvrCacheBuilder::class)->build($wanted, null, $listedOnly);

            return response()->json([
                'data' => $built['rows'],
                'meta' => [
                    'total' => count($built['rows']),
                    'channels' => $wanted,
                    'errors' => $built['errors'],
                    'source' => 'cvr-price-increase',
                    'calculated_at' => null,
                ],
            ])->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
        } catch (\Throwable $e) {
            Log::error('PricingErrorsFix dataJson: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch pricing errors data', 'message' => $e->getMessage()], 500);
        }
    }

    private function dataJsonFromCache(Request $request): JsonResponse
    {
        $wanted = $this->wantedChannelKeys($request);
        $listedOnly = filter_var($request->query('listed_only', true), FILTER_VALIDATE_BOOLEAN);
        $out = $this->fetchCacheRows($wanted, $listedOnly);

        return response()->json([
            'data' => $out,
            'meta' => [
                'total' => count($out),
                'channels' => $wanted,
                'errors' => [],
                'source' => 'cache',
                'calculated_at' => PricingErrorsFixCalculatedData::lastCalculatedAt(),
            ],
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Plain DB read from pricing_errors_fix_calculated_data (no joins).
     *
     * @param  array<int, string>  $wantedKeys
     * @return array<int, array<string, mixed>>
     */
    private function fetchCacheRows(array $wantedKeys, bool $listedOnly = true): array
    {
        $registry = $this->channelRegistry();
        $mps = [];
        foreach ($wantedKeys as $key) {
            if (isset($registry[$key])) {
                $mps[] = $registry[$key]['marketplace'];
            }
        }

        $q = \Illuminate\Support\Facades\DB::table('pricing_errors_fix_calculated_data')
            ->select([
                'sku', 'marketplace', 'pull_key', 'channel_label', 'parent',
                'inv', 'ov_l30', 'dil', 'price', 'groi', 'nroi', 'gpft', 'npft',
                'sprice', 'sroi', 'sgpft', 'snroi', 'snpft', 'success',
                'lp', 'ship', 'margin', 'ads_pct',
            ]);

        if ($mps !== []) {
            $q->whereIn('marketplace', $mps);
        }
        if ($listedOnly) {
            $q->where(function ($w) {
                $w->where('price', '>', 0)->orWhere('sprice', '>', 0);
            });
        }

        $out = [];
        foreach ($q->get() as $r) {
            $price = $r->price !== null ? (float) $r->price : 0.0;
            $sprice = $r->sprice !== null ? (float) $r->sprice : 0.0;
            $out[] = [
                'id' => $r->marketplace.'|'.$r->sku,
                'channel' => $r->channel_label,
                'channel_key' => $r->marketplace,
                'pull_key' => $r->pull_key ?: $r->marketplace,
                'marketplace' => $r->marketplace,
                'image_path' => null,
                'parent' => $r->parent,
                'sku' => $r->sku,
                'inv' => (float) $r->inv,
                'ov_l30' => (float) $r->ov_l30,
                'dil' => $r->dil !== null ? (float) $r->dil : null,
                'price' => $price > 0 ? round($price, 2) : null,
                'groi' => $r->groi !== null ? (float) $r->groi : null,
                'nroi' => $r->nroi !== null ? (float) $r->nroi : null,
                'gpft' => $r->gpft !== null ? (float) $r->gpft : null,
                'npft' => $r->npft !== null ? (float) $r->npft : null,
                'sprice' => $sprice > 0 ? round($sprice, 2) : null,
                'sroi' => $r->sroi !== null ? (float) $r->sroi : null,
                'sgpft' => $r->sgpft !== null ? (float) $r->sgpft : null,
                'snroi' => $r->snroi !== null ? (float) $r->snroi : null,
                'snpft' => $r->snpft !== null ? (float) $r->snpft : null,
                'success' => $r->success,
                'lp' => (float) $r->lp,
                'ship' => (float) $r->ship,
                'margin' => (float) $r->margin,
                'ads_pct' => (float) $r->ads_pct,
                '_selected' => false,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function wantedChannelKeys(Request $request): array
    {
        $registry = $this->channelRegistry();
        $filterRaw = trim((string) $request->query('channel', ''));
        if ($filterRaw === '') {
            return array_keys($registry);
        }

        return array_values(array_filter(
            array_map('strtolower', array_map('trim', explode(',', $filterRaw))),
            fn ($k) => isset($registry[$k])
        ));
    }

    /**
     * Build normalized rows from live channel controllers (used by artisan command + ?live=1).
     *
     * @param  array<int, string>  $wanted
     * @return array{rows: array<int, array<string, mixed>>, errors: array<string, string>, channels: array<int, string>}
     */
    public function buildNormalizedRows(array $wanted, Request $request, bool $listedOnly = true): array
    {
        $registry = $this->channelRegistry();
        $out = [];
        $errors = [];

        foreach ($wanted as $key) {
            if (! isset($registry[$key])) {
                continue;
            }
            $cfg = $registry[$key];
            try {
                $rows = $this->fetchChannelRows($cfg, $request);
                foreach ($rows as $raw) {
                    if (! is_array($raw) && ! is_object($raw)) {
                        continue;
                    }
                    $row = $this->normalizeRow($raw, $cfg, $key);
                    if ($row === null) {
                        continue;
                    }
                    if ($listedOnly && (float) ($row['price'] ?? 0) <= 0 && (float) ($row['sprice'] ?? 0) <= 0) {
                        continue;
                    }
                    $out[] = $row;
                }
            } catch (\Throwable $e) {
                Log::warning('PricingErrorsFix channel failed: '.$key.' — '.$e->getMessage());
                $errors[$key] = $e->getMessage();
            }
        }

        return ['rows' => $out, 'errors' => $errors, 'channels' => $wanted];
    }

    /** @return array<string, array{label:string,marketplace:string,price_keys:array<int,string>,fetch:callable}> */
    public function publicChannelRegistry(): array
    {
        return $this->channelRegistry();
    }

    /**
     * @return array{groi:?float,nroi:?float,gpft:?float,npft:?float,sroi:?float,sgpft:?float,snroi:?float,snpft:?float}
     */
    public function publicComputeMetrics(
        ?float $price,
        ?float $sprice,
        float $lp,
        float $ship,
        float $margin,
        float $adsPct
    ): array {
        return $this->computeChannelMetrics($price, $sprice, $lp, $ship, $margin, $adsPct);
    }

    /**
     * After SPRICE save/push — patch cache for that SKU×marketplace (queued, non-blocking).
     */
    public static function queueSkuRefresh(string $sku, string $marketplace, ?float $sprice = null): void
    {
        $sku = trim($sku);
        $marketplace = strtolower(trim($marketplace));
        if ($sku === '' || $marketplace === '') {
            return;
        }

        // Normalize aliases used by CVR save
        $aliases = [
            'ebay' => 'ebay',
            'ebay1' => 'ebay',
            'ebaytwo' => 'ebay2',
            'ebaythree' => 'ebay3',
            'tiktokshop2' => 'tiktok2',
            'bestbuyusa' => 'bestbuy',
            'macys' => 'macy',
            'purchasingpower' => 'ppower',
            'purchase' => 'ppower',
        ];
        $channel = $aliases[$marketplace] ?? $marketplace;

        try {
            $params = [
                '--sku' => $sku,
                '--channel' => $channel,
            ];
            if ($sprice !== null) {
                $params['--sprice'] = (string) $sprice;
            }
            // Prefer queue; fall back to sync in background-ish call
            try {
                Artisan::queue('pricing-errors:calculate-data', $params);
            } catch (\Throwable $e) {
                Artisan::call('pricing-errors:calculate-data', $params);
            }
        } catch (\Throwable $e) {
            Log::warning('PricingErrorsFix queueSkuRefresh failed', [
                'sku' => $sku,
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Channel list exposed for the UI filter dropdown.
     */
    public function channelsJson(): JsonResponse
    {
        $list = [];
        foreach ($this->channelRegistry() as $key => $cfg) {
            $list[] = [
                'key' => $key,
                'label' => $cfg['label'],
                'marketplace' => $cfg['marketplace'],
            ];
        }

        return response()->json($list);
    }

    /**
     * @return array<string, array{label:string,marketplace:string,price_keys:array<int,string>,fetch:callable}>
     */
    private function channelRegistry(): array
    {
        return [
            'amazon' => [
                'label' => 'Amazon',
                'marketplace' => 'amazon',
                'price_keys' => ['price', 'A Price', 'amazon_price'],
                'fetch' => fn (Request $r) => app(OverallAmazonController::class)->amazonDataJson($r),
            ],
            'ebay' => [
                'label' => 'eBay 1',
                'marketplace' => 'ebay1',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayController::class)->ebayDataJson($r),
            ],
            'ebay2' => [
                'label' => 'eBay 2',
                'marketplace' => 'ebay2',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayTwoController::class)->getViewEbayData($r),
            ],
            'ebay3' => [
                'label' => 'eBay 3',
                'marketplace' => 'ebay3',
                'price_keys' => ['eBay Price', 'ebay_price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(EbayThreeController::class)->ebay3DataJson($r),
            ],
            'temu' => [
                'label' => 'Temu',
                'marketplace' => 'temu',
                'price_keys' => ['price', 'Price', 'temu_price', 'T Price'],
                'fetch' => fn (Request $r) => app(TemuController::class)->getTemuDecreaseData($r),
            ],
            'temu2' => [
                'label' => 'Temu 2',
                'marketplace' => 'temu2',
                'price_keys' => ['price', 'Price', 'temu_price', 'T Price'],
                'fetch' => fn (Request $r) => app(TemuController::class)->getTemu2DecreaseData($r),
            ],
            'doba' => [
                'label' => 'Doba',
                'marketplace' => 'doba',
                'price_keys' => ['Doba Price', 'price', 'Price', 'doba_price'],
                'fetch' => fn (Request $r) => app(DobaController::class)->getViewdobaData($r),
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'marketplace' => 'tiktok',
                'price_keys' => ['price', 'Price', 'TT Price', 'tiktok_price'],
                'fetch' => fn (Request $r) => app(TikTokPricingController::class)->tiktokDataJson($r),
            ],
            'tiktok2' => [
                'label' => 'TikTok 2',
                'marketplace' => 'tiktok2',
                'price_keys' => ['price', 'Price', 'TT Price', 'tiktok_price'],
                'fetch' => fn (Request $r) => app(TikTokPricingController::class)->tiktok2DataJson($r),
            ],
            'bestbuy' => [
                'label' => 'Best Buy',
                'marketplace' => 'bestbuy',
                'price_keys' => ['price', 'Price', 'BB Price', 'bestbuy_price'],
                'fetch' => fn (Request $r) => app(BestBuyPricingController::class)->bestbuyDataJson($r),
            ],
            'macy' => [
                'label' => "Macy's",
                'marketplace' => 'macy',
                'price_keys' => ['price', 'Price', 'Macy Price', 'macy_price'],
                'fetch' => fn (Request $r) => app(MacyController::class)->macysDataJson($r),
            ],
            'reverb' => [
                'label' => 'Reverb',
                'marketplace' => 'reverb',
                'price_keys' => ['price', 'Price', 'Reverb Price', 'reverb_price'],
                'fetch' => fn (Request $r) => app(ReverbController::class)->reverbDataJson($r),
            ],
            'topdawg' => [
                'label' => 'TopDawg',
                'marketplace' => 'topdawg',
                'price_keys' => ['TD Price', 'price', 'Price'],
                'fetch' => fn (Request $r) => app(TopDawgPricingController::class)->dataJson($r),
            ],
            'walmart' => [
                'label' => 'Walmart',
                'marketplace' => 'walmart',
                'price_keys' => ['price', 'Price', 'Walmart Price', 'walmart_price'],
                'fetch' => fn (Request $r) => app(WalmartControllerMarket::class)->walmartDataJson($r),
            ],
            'sb2c' => [
                'label' => 'Shopify B2C',
                'marketplace' => 'sb2c',
                'price_keys' => ['price', 'Price', 'Shopify Price'],
                'fetch' => fn (Request $r) => app(Shopifyb2cController::class)->shopifyB2cDataJson(),
            ],
            'sb2b' => [
                'label' => 'Shopify B2B',
                'marketplace' => 'sb2b',
                'price_keys' => ['price', 'Price', 'Shopify Price'],
                'fetch' => fn (Request $r) => app(Shopifyb2bController::class)->shopifyB2bDataJson(),
            ],
            'ppower' => [
                'label' => 'Purchasing Power',
                'marketplace' => 'ppower',
                'price_keys' => ['price', 'Price', 'PP Price'],
                'fetch' => fn (Request $r) => app(PurchasingPowerController::class)->dataJson($r),
            ],
            'tiendamia' => [
                'label' => 'Tiendamia',
                'marketplace' => 'tiendamia',
                'price_keys' => ['price', 'Price'],
                'fetch' => fn (Request $r) => app(TiendamiaPricingController::class)->tiendamiaDataJson($r),
            ],
            'pls' => [
                'label' => 'PLS',
                'marketplace' => 'pls',
                'price_keys' => ['price', 'Price'],
                'fetch' => fn (Request $r) => app(PlsController::class)->pricingDataJson($r),
            ],
            'wayfair' => [
                'label' => 'Wayfair',
                'marketplace' => 'wayfair',
                'price_keys' => ['price', 'Price', 'Wayfair Price'],
                'fetch' => fn (Request $r) => app(WayfairController::class)->getWayfairPricingData($r),
            ],
            'shein' => [
                'label' => 'Shein',
                'marketplace' => 'shein',
                'price_keys' => ['price', 'Price', 'Shein Price'],
                'fetch' => fn (Request $r) => app(SheinController::class)->getSheinPricingData($r),
            ],
            'faire' => [
                'label' => 'Faire',
                'marketplace' => 'faire',
                'price_keys' => ['price', 'Price', 'Faire Price'],
                'fetch' => fn (Request $r) => app(FaireController::class)->getViewFaireData($r),
            ],
            'aliexpress' => [
                'label' => 'AliExpress',
                'marketplace' => 'aliexpress',
                'price_keys' => ['price', 'Price', 'AE Price'],
                'fetch' => fn (Request $r) => app(AliexpressController::class)->getViewAliexpressData($r),
            ],
        ];
    }

    /**
     * @param  array{fetch:callable}  $cfg
     * @return array<int, array<string, mixed>|object>
     */
    private function fetchChannelRows(array $cfg, Request $request): array
    {
        $response = ($cfg['fetch'])($request);
        if ($response instanceof JsonResponse) {
            $payload = json_decode($response->getContent(), true);
        } elseif (is_array($response)) {
            $payload = $response;
        } else {
            return [];
        }

        if (! is_array($payload)) {
            return [];
        }

        // Common shapes: [ {...} ], { data: [...] }, { data: { data: [...] } }
        if (isset($payload['data']) && is_array($payload['data'])) {
            $inner = $payload['data'];
            if (isset($inner['data']) && is_array($inner['data'])) {
                return array_values($inner['data']);
            }
            // Associative list of rows vs keyed meta
            if ($this->looksLikeRowList($inner)) {
                return array_values($inner);
            }
        }

        if ($this->looksLikeRowList($payload)) {
            return array_values($payload);
        }

        return [];
    }

    private function looksLikeRowList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        $first = reset($arr);
        if (! is_array($first) && ! is_object($first)) {
            return false;
        }
        // Reject meta-only objects
        if (is_array($first) && isset($first['error']) && count($first) <= 2) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|object  $raw
     * @param  array{label:string,marketplace:string,price_keys:array<int,string>}  $cfg
     * @return array<string, mixed>|null
     */
    private function normalizeRow($raw, array $cfg, string $pullKey = ''): ?array
    {
        $r = is_object($raw) ? (array) $raw : $raw;
        if (! is_array($r)) {
            return null;
        }

        $sku = (string) $this->pick($r, ['(Child) sku', 'sku', 'SKU', 'child_sku', 'Child sku'], '');
        $sku = trim($sku);
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return null;
        }
        if (! empty($r['is_parent_summary'])) {
            return null;
        }

        $parent = $this->pick($r, ['Parent', 'parent', 'parent_sku'], null);
        $inv = $this->toFloat($this->pick($r, ['INV', 'inv', 'inventory', 'Inventory'], 0));
        $ovL30 = $this->toFloat($this->pick($r, ['L30', 'OV L30', 'ov_l30', 'overall_l30', 'quantity'], 0));
        $dil = $this->pick($r, ['Dil', 'Dil%', 'dil', 'dil_percent', 'dil%'], null);
        if ($dil === null || $dil === '') {
            $dil = $inv > 0 ? round(($ovL30 / $inv) * 100, 0) : 0;
        } else {
            $dil = $this->toFloat($dil);
        }

        $price = $this->extractChannelPrice($r, $cfg['price_keys'] ?? []);
        $sprice = $this->nullableFloat($this->pick($r, ['SPRICE', 'sprice', 'SPrice'], null));

        $success = $this->pick($r, ['SPRICE_STATUS', 'PUSH_STATUS', 'push_status', 'success', 'Success'], null);
        if (is_string($success)) {
            $success = trim($success);
            if ($success === '') {
                $success = null;
            }
        }

        $image = $this->pick($r, ['image_path', 'Image', 'image', 'image_src'], null);
        $lp = $this->toFloat($this->pick($r, ['LP_productmaster', 'lp', 'LP'], 0));
        $ship = $this->toFloat($this->pick($r, ['Ship_productmaster', 'ship', 'Ship', 'temu_ship'], 0));
        $rowMargin = $this->toFloat($this->pick($r, ['percentage', 'margin'], 0));

        // Channel-wise take-home + Ads% — same formulas as amazon/ebay/temu tabulators
        $marketplace = (string) $cfg['marketplace'];
        $margin = $this->resolveTakeHome($marketplace, $rowMargin);
        $adsPct = $this->resolveAdsPercent($marketplace);

        // When SPRICE empty, channels default SPRICE = price for suggested columns
        $spriceForCalc = ($sprice !== null && $sprice > 0) ? $sprice : ($price > 0 ? $price : null);

        $calc = $this->computeChannelMetrics(
            $price > 0 ? $price : null,
            $spriceForCalc,
            $lp,
            $ship,
            $margin,
            $adsPct
        );

        return [
            'id' => $cfg['marketplace'].'|'.$sku,
            'channel' => $cfg['label'],
            'channel_key' => $cfg['marketplace'],
            'pull_key' => $pullKey !== '' ? $pullKey : $cfg['marketplace'],
            'marketplace' => $cfg['marketplace'],
            'image_path' => $image,
            'parent' => $parent,
            'sku' => $sku,
            'inv' => $inv,
            'ov_l30' => $ovL30,
            'dil' => $dil,
            'price' => $price > 0 ? round($price, 2) : null,
            'groi' => $calc['groi'],
            'nroi' => $calc['nroi'],
            'gpft' => $calc['gpft'],
            'npft' => $calc['npft'],
            'sprice' => $sprice !== null && $sprice > 0 ? round($sprice, 2) : null,
            // Sroi = SGROI (gross); Snroi = SROI (net after Ads%)
            'sroi' => $calc['sroi'],
            'sgpft' => $calc['sgpft'],
            'snroi' => $calc['snroi'],
            'snpft' => $calc['snpft'],
            'success' => $success,
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            'ads_pct' => $adsPct,
            '_selected' => false,
        ];
    }

    /**
     * Channel take-home decimal used in GPFT/GROI/S* formulas.
     * Amazon always 0.80 (matches OverallAmazonController / amazon-tabulator-view).
     */
    private function resolveTakeHome(string $marketplace, float $rowMargin): float
    {
        $mp = strtolower(trim($marketplace));
        if ($mp === 'amazon') {
            return 0.80;
        }

        $m = $rowMargin;
        if ($m > 1) {
            $m = $m / 100;
        }
        if ($m > 0 && $m <= 1) {
            return $m;
        }

        if (isset($this->takeHomeCache[$mp])) {
            return $this->takeHomeCache[$mp];
        }

        $defaults = [
            'ebay' => 0.85, 'ebay1' => 0.85, 'ebay2' => 0.85, 'ebay3' => 0.85,
            'temu' => 0.87, 'temu2' => 0.87,
            'doba' => 0.90,
            'tiktok' => 0.85, 'tiktok2' => 0.85,
            'walmart' => 0.85,
            'bestbuy' => 0.85, 'macy' => 0.85, 'reverb' => 0.85,
            'topdawg' => 0.85, 'sb2c' => 0.85, 'sb2b' => 0.85,
            'ppower' => 0.85, 'shein' => 0.85, 'faire' => 0.85,
            'aliexpress' => 0.85, 'wayfair' => 0.85, 'tiendamia' => 0.85, 'pls' => 0.85,
        ];

        return $this->takeHomeCache[$mp] = $defaults[$mp] ?? 0.80;
    }

    /**
     * Channel Ads% (TACOS) — used for NPFT = GPFT − Ads% and net ROI.
     */
    private function resolveAdsPercent(string $marketplace): float
    {
        $mp = strtolower(trim($marketplace));
        if (isset($this->adsPctCache[$mp])) {
            return $this->adsPctCache[$mp];
        }

        // Channels with no ads deduction (same as CVR master / channel tabulators)
        $noAds = [
            'doba', 'bestbuy', 'macy', 'topdawg', 'shein', 'faire', 'aliexpress',
            'ppower', 'sb2c', 'sb2b', 'temu2', 'wayfair', 'tiendamia', 'pls', 'reverb',
        ];
        if (in_array($mp, $noAds, true)) {
            return $this->adsPctCache[$mp] = 0.0;
        }

        try {
            if (in_array($mp, ['ebay', 'ebay1', 'ebay2', 'ebay3'], true)) {
                $ads = (float) app(ChannelMasterController::class)->getEbayMasterAdsPercent();

                return $this->adsPctCache[$mp] = $ads;
            }
        } catch (\Throwable $e) {
            Log::debug('PricingErrorsFix ebay ads: '.$e->getMessage());
        }

        $names = match ($mp) {
            'amazon' => ['Amazon'],
            'temu' => ['Temu'],
            'tiktok' => ['TikTok', 'Tiktok', 'TikTokShop'],
            'tiktok2' => ['TikTok 2', 'Tiktok2', 'TikTokShop2'],
            'walmart' => ['Walmart'],
            default => [ucfirst($mp)],
        };

        $ads = 0.0;
        foreach ($names as $name) {
            $v = ChannelMasterCalculatedData::where('channel', $name)->value('ads_percentage');
            if ($v === null) {
                $v = ChannelMasterCalculatedData::where('channel', 'like', $name.'%')->value('ads_percentage');
            }
            if ($v !== null && is_numeric($v)) {
                $ads = (float) $v;
                break;
            }
        }

        return $this->adsPctCache[$mp] = $ads;
    }

    /**
     * Same formulas as amazon-tabulator-view / OverallAmazonController:
     *   GPFT%  = ((price × margin − ship − lp) / price) × 100
     *   GROI%  = ((price × margin − ship − lp) / lp) × 100
     *   NPFT%  = GPFT% − Ads%
     *   NROI%  = (gross$ − price×Ads%/100) / lp × 100
     * Suggested columns use SPRICE the same way (SGPFT / Sroi=SGROI / Snpft / Snroi).
     *
     * @return array{groi:?float,nroi:?float,gpft:?float,npft:?float,sroi:?float,sgpft:?float,snroi:?float,snpft:?float}
     */
    private function computeChannelMetrics(
        ?float $price,
        ?float $sprice,
        float $lp,
        float $ship,
        float $margin,
        float $adsPct
    ): array {
        $out = [
            'groi' => null, 'nroi' => null, 'gpft' => null, 'npft' => null,
            'sroi' => null, 'sgpft' => null, 'snroi' => null, 'snpft' => null,
        ];

        if (! ($margin > 0 && $margin <= 1) || ! ($lp > 0)) {
            return $out;
        }

        if ($price !== null && $price > 0) {
            $gross = ($price * $margin) - $ship - $lp;
            $out['gpft'] = round(($gross / $price) * 100, 2);
            $out['groi'] = round(($gross / $lp) * 100, 2);
            $out['npft'] = round($out['gpft'] - $adsPct, 2);
            $adSpend = $price * ($adsPct / 100);
            $out['nroi'] = round((($gross - $adSpend) / $lp) * 100, 2);
        }

        if ($sprice !== null && $sprice > 0) {
            $gross = ($sprice * $margin) - $ship - $lp;
            $out['sgpft'] = round(($gross / $sprice) * 100, 2);
            $out['sroi'] = round(($gross / $lp) * 100, 2); // SGROI (gross)
            $out['snpft'] = round($out['sgpft'] - $adsPct, 2); // SPFT / Spft%
            $adSpend = $sprice * ($adsPct / 100);
            $out['snroi'] = round((($gross - $adSpend) / $lp) * 100, 2); // SROI (net)
        }

        return $out;
    }

    /**
     * Resolve listing price from channel-specific keys, then any *price* field
     * (excluding SPRICE / LMP / STANDARD / FBA / yesterday helpers).
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $preferredKeys
     */
    private function extractChannelPrice(array $row, array $preferredKeys): float
    {
        $defaults = [
            'price', 'Price', 'Prc', 'PRC',
            'eBay Price', 'ebay_price', 'Ebay Price',
            'TD Price', 'temu_price', 'Temu Price', 'T Price', 'fb_price', 'FB Price', 'base_price',
            'Doba Price', 'doba_price',
            'TT Price', 'tiktok_price', 'BB Price', 'bestbuy_price',
            'Macy Price', 'macy_price', 'Reverb Price', 'reverb_price',
            'Walmart Price', 'walmart_price', 'Shopify Price', 'PP Price',
            'Wayfair Price', 'Shein Price', 'Faire Price', 'AE Price', 'A Price', 'amazon_price',
        ];
        $keys = array_values(array_unique(array_merge($preferredKeys, $defaults)));
        $direct = $this->toFloat($this->pick($row, $keys, 0));
        if ($direct > 0) {
            return $direct;
        }

        $skip = [
            'sprice', 's_price', 'standard_price', 'lmp_price', 'fba_price',
            'price_yesterday', 'price_lmpa', 'a_price', 'e_price', 'e2_price',
            'temu1_price', 'temu1_base_price', 'recommended_base_price',
            'has_custom_sprice', 'sprice_status',
        ];
        foreach ($row as $k => $v) {
            $lk = strtolower(trim((string) $k));
            if ($lk === '' || ! str_contains($lk, 'price')) {
                continue;
            }
            if (in_array($lk, $skip, true)) {
                continue;
            }
            if (str_contains($lk, 'sprice') || str_contains($lk, 'lmp') || str_contains($lk, 'standard')) {
                continue;
            }
            $n = $this->toFloat($v);
            if ($n > 0) {
                return $n;
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     * @param  mixed  $default
     * @return mixed
     */
    private function pick(array $row, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        // Case-insensitive fallback
        $lowerMap = [];
        foreach ($row as $k => $v) {
            $lowerMap[strtolower((string) $k)] = $v;
        }
        foreach ($keys as $key) {
            $lk = strtolower($key);
            if (array_key_exists($lk, $lowerMap) && $lowerMap[$lk] !== null && $lowerMap[$lk] !== '') {
                return $lowerMap[$lk];
            }
        }

        return $default;
    }

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_string($value)) {
            $value = str_replace([',', '%', '$'], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace([',', '%', '$'], '', $value);
        }
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
