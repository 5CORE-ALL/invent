<?php

namespace App\Services;

use App\Models\TikTokProduct;
use App\Models\TiktokCampaignReport;
use App\Models\TiktokGmvAd;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull L30 / L1 shop product performance from TikTok Shop API and store
 * it on tiktok_gmv_ads keyed by real SKU (tiktok_products.product_id → sku).
 * Syncs missing products first when analytics IDs are not in tiktok_products.
 */
class TikTokGmvAdsSyncService
{
    public const CACHE_KEY = 'tiktok_gmv_ads_sync_v1';

    public function __construct(private TikTokShopService $shop)
    {
    }

    /**
     * @return array{synced: bool, skipped: bool, reason?: string, l30_rows: int, l1_rows: int, unmapped: int, shop_gmv_l30: float, shop_orders_l30: int}
     */
    public function syncIfStale(int $ttlMinutes = 120): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && (int) ($cached['l30_rows'] ?? 0) > 0) {
            return array_merge($cached, ['synced' => false, 'skipped' => true]);
        }

        return $this->sync();
    }

    /**
     * @return array{synced: bool, skipped: bool, l30_rows: int, l1_rows: int, unmapped: int, shop_gmv_l30: float, shop_orders_l30: int}
     */
    public function sync(): array
    {
        $this->ensureGmvColumns();

        if (! $this->shop->isAuthenticated()) {
            $this->shop->refreshAccessToken();
        } else {
            try {
                $this->shop->refreshAccessToken();
            } catch (\Throwable $e) {
                Log::warning('TikTok GMV sync token refresh skipped: '.$e->getMessage());
            }
        }

        $tz = 'America/Los_Angeles';
        $today = Carbon::now($tz);
        $l30Start = $today->copy()->subDays(30)->toDateString();
        $l30End = $today->toDateString();
        $l1Start = $today->copy()->subDay()->toDateString();
        $l1End = $today->toDateString();

        $productMap = $this->productIdToSkuMap();
        $l30Products = $this->fetchAllProductPerformance($l30Start, $l30End);
        $missingIds = [];
        foreach ($l30Products as $row) {
            $pid = (string) ($row['id'] ?? '');
            if ($pid !== '' && ! isset($productMap[$pid])) {
                $missingIds[$pid] = true;
            }
        }
        if ($missingIds !== []) {
            $this->backfillMissingProducts(array_keys($missingIds));
            $productMap = $this->productIdToSkuMap();
        }

        $l30Rows = $this->persistRange('L30', $l30Products, $productMap);
        $l1Products = $this->fetchAllProductPerformance($l1Start, $l1End);
        $l1Rows = $this->persistRange('L1', $l1Products, $productMap);

        $this->overlayCampaignReportMetrics($l30Rows, 'L30');
        $this->overlayCampaignReportMetrics($l1Rows, 'L1');

        $shopGmv = 0.0;
        $shopOrders = 0;
        try {
            $shopPerf = $this->shopShopPerformance($l30Start, $l30End);
            $interval = $shopPerf['performance']['intervals'][0] ?? [];
            $shopGmv = (float) ($interval['gmv']['amount'] ?? 0);
            $shopOrders = (int) ($interval['sku_orders'] ?? $interval['orders'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('TikTok GMV shop performance failed: '.$e->getMessage());
        }

        $unmapped = 0;
        foreach ($l30Products as $row) {
            $pid = (string) ($row['id'] ?? '');
            if ($pid !== '' && ! isset($productMap[$pid])) {
                $unmapped++;
            }
        }

        $result = [
            'synced' => true,
            'skipped' => false,
            'l30_rows' => count($l30Rows),
            'l1_rows' => count($l1Rows),
            'unmapped' => $unmapped,
            'shop_gmv_l30' => round($shopGmv, 2),
            'shop_orders_l30' => $shopOrders,
            'synced_at' => now()->toDateTimeString(),
        ];
        Cache::put(self::CACHE_KEY, $result, now()->addHours(2));

        Log::info('TikTok GMV ads sync finished', $result);

        return $result;
    }

    /**
     * @return array<string, string> product_id => sku
     */
    private function productIdToSkuMap(): array
    {
        $map = [];
        if (! Schema::hasTable('tiktok_products')) {
            return $map;
        }
        TikTokProduct::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['product_id', 'sku_id', 'sku'])
            ->each(function (TikTokProduct $p) use (&$map) {
                $sku = trim((string) $p->sku);
                if ($sku === '') {
                    return;
                }
                $pid = trim((string) ($p->product_id ?? ''));
                if ($pid !== '') {
                    $map[$pid] = $sku;
                }
                $sid = trim((string) ($p->sku_id ?? ''));
                if ($sid !== '') {
                    $map[$sid] = $sku;
                }
            });

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllProductPerformance(string $start, string $end): array
    {
        $all = [];
        $pageToken = '';
        for ($i = 0; $i < 40; $i++) {
            $resp = $this->shop->getProductAnalytics($start, $end);
            if (! is_array($resp)) {
                break;
            }
            // First page uses the public helper; later pages need the token.
            if ($i === 0) {
                $products = $resp['products'] ?? [];
                foreach ($products as $p) {
                    $all[] = $p;
                }
                $pageToken = (string) ($resp['next_page_token'] ?? '');
            }
            if ($pageToken === '') {
                break;
            }
            $next = $this->shopProductPerformancePage($start, $end, $pageToken);
            if (! is_array($next)) {
                break;
            }
            foreach ($next['products'] ?? [] as $p) {
                $all[] = $p;
            }
            $pageToken = (string) ($next['next_page_token'] ?? '');
            if ($pageToken === '') {
                break;
            }
        }

        return $all;
    }

    private function shopProductPerformancePage(string $start, string $end, string $pageToken): ?array
    {
        try {
            $ref = new \ReflectionClass($this->shop);
            $clientP = $ref->getProperty('client');
            $clientP->setAccessible(true);
            $client = $clientP->getValue($this->shop);
            $tokenP = $ref->getProperty('accessToken');
            $tokenP->setAccessible(true);
            $token = Cache::get('tiktok_access_token') ?: $tokenP->getValue($this->shop);
            $client->setAccessToken($token);
            $ensure = $ref->getMethod('ensureShopCipher');
            $ensure->setAccessible(true);
            $ensure->invoke($this->shop);
            $client->useVersion('202405');

            return $client->Analytics->getShopProductPerformanceList([
                'start_date_ge' => $start,
                'end_date_lt' => $end,
                'page_size' => 50,
                'page_token' => $pageToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('TikTok product performance page failed: '.$e->getMessage());

            return null;
        }
    }

    private function shopShopPerformance(string $start, string $end): array
    {
        $ref = new \ReflectionClass($this->shop);
        $clientP = $ref->getProperty('client');
        $clientP->setAccessible(true);
        $client = $clientP->getValue($this->shop);
        $tokenP = $ref->getProperty('accessToken');
        $tokenP->setAccessible(true);
        $token = Cache::get('tiktok_access_token') ?: $tokenP->getValue($this->shop);
        $client->setAccessToken($token);
        $ensure = $ref->getMethod('ensureShopCipher');
        $ensure->setAccessible(true);
        $ensure->invoke($this->shop);
        $client->useVersion('202405');

        return $client->Analytics->getShopPerformance([
            'start_date_ge' => $start,
            'end_date_lt' => $end,
        ]) ?? [];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @param  array<string, string>  $productMap
     * @return array<string, array{ad_sold: int, ad_sales: float, spend: float}>
     */
    private function persistRange(string $range, array $products, array $productMap): array
    {
        $bySku = [];
        foreach ($products as $row) {
            $pid = (string) ($row['id'] ?? '');
            $sku = $productMap[$pid] ?? '';
            if ($sku === '') {
                continue;
            }
            $key = strtoupper(trim($sku));
            if (! isset($bySku[$key])) {
                $bySku[$key] = [
                    'sku' => $sku,
                    'product_id' => $pid,
                    'ad_sold' => 0,
                    'ad_sales' => 0.0,
                    'spend' => 0.0,
                ];
            }
            $bySku[$key]['ad_sold'] += (int) ($row['units_sold'] ?? $row['orders'] ?? 0);
            $gmv = $row['gmv']['amount'] ?? $row['gmv'] ?? 0;
            $bySku[$key]['ad_sales'] += (float) $gmv;
        }

        foreach ($bySku as $row) {
            $attrs = [
                'sku' => $row['sku'],
                'ad_sold' => $row['ad_sold'],
                'ad_sales' => round($row['ad_sales'], 2),
                'spend' => round($row['spend'], 2),
                'status' => 'active',
                'approval' => 'Pending',
            ];
            $query = ['sku' => $row['sku']];
            if (Schema::hasColumn('tiktok_gmv_ads', 'report_range')) {
                $query['report_range'] = $range;
                $attrs['report_range'] = $range;
            }
            if (Schema::hasColumn('tiktok_gmv_ads', 'product_id')) {
                $attrs['product_id'] = $row['product_id'];
            }
            TiktokGmvAd::updateOrCreate($query, $attrs);
        }

        return $bySku;
    }

    /**
     * Fill empty campaign-report revenue/orders for matching SKUs so ads columns are not all zero.
     *
     * @param  array<string, array{ad_sold: int, ad_sales: float, spend: float}>  $bySku
     */
    private function overlayCampaignReportMetrics(array $bySku, string $range): void
    {
        if ($bySku === [] || ! Schema::hasTable('tiktok_campaign_reports')) {
            return;
        }
        foreach ($bySku as $skuKey => $row) {
            try {
                TiktokCampaignReport::query()
                    ->where('report_range', $range)
                    ->whereRaw('UPPER(TRIM(campaign_name)) = ?', [$skuKey])
                    ->where(function ($q) {
                        $q->whereNull('gross_revenue')->orWhere('gross_revenue', 0);
                    })
                    ->update([
                        'gross_revenue' => $row['ad_sales'],
                        'sku_orders' => $row['ad_sold'],
                    ]);
            } catch (\Throwable $e) {
                Log::warning('TikTok campaign report overlay failed for '.$skuKey.': '.$e->getMessage());
            }
        }
    }

    /**
     * @param  list<string>  $productIds
     */
    private function backfillMissingProducts(array $productIds): void
    {
        if ($productIds === []) {
            return;
        }
        Log::info('TikTok GMV sync: fetching missing products', ['count' => count($productIds)]);
        foreach (array_slice($productIds, 0, 80) as $productId) {
            try {
                $detail = $this->shop->getProductDetails([$productId]);
                $data = is_array($detail) ? ($detail['data'] ?? $detail) : [];
                $sku = trim((string) ($data['skus'][0]['seller_sku'] ?? $data['seller_sku'] ?? $data['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $skuId = (string) ($data['skus'][0]['id'] ?? $data['sku_id'] ?? '');
                TikTokProduct::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => (string) $productId,
                        'sku_id' => $skuId !== '' ? $skuId : null,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('TikTok GMV missing product fetch failed: '.$e->getMessage(), ['product_id' => $productId]);
            }
        }
    }

    private function ensureGmvColumns(): void
    {
        if (! Schema::hasTable('tiktok_gmv_ads')) {
            return;
        }
        try {
            if (! Schema::hasColumn('tiktok_gmv_ads', 'report_range')) {
                \Illuminate\Support\Facades\DB::statement(
                    "ALTER TABLE tiktok_gmv_ads ADD COLUMN report_range VARCHAR(8) NULL DEFAULT NULL AFTER sku"
                );
            }
            if (! Schema::hasColumn('tiktok_gmv_ads', 'product_id')) {
                \Illuminate\Support\Facades\DB::statement(
                    "ALTER TABLE tiktok_gmv_ads ADD COLUMN product_id VARCHAR(64) NULL DEFAULT NULL AFTER report_range"
                );
            }
            $col = \Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM tiktok_gmv_ads WHERE Field = 'id'");
            $extra = strtolower((string) ($col->Extra ?? ''));
            if (! str_contains($extra, 'auto_increment')) {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE tiktok_gmv_ads MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('TikTok GMV ads column ensure failed: '.$e->getMessage());
        }
    }
}
