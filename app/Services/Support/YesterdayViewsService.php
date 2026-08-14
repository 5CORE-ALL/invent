<?php

namespace App\Services\Support;

use App\Models\ChannelYesterdayView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 1-day Pacific listing views for Yesterday CVR.
 * CVR = yesterday qty ÷ yesterday views × 100.
 * Does not use L30 sessions / product_clicks.
 */
class YesterdayViewsService
{
    private const TZ = 'America/Los_Angeles';

    /**
     * @return array<string, int> canonical channel key => yesterday views
     */
    public function viewsByChannel(?string $date = null): array
    {
        $date = $date ?: Carbon::yesterday(self::TZ)->toDateString();
        $map = $this->sumStoredMarketplaceL1();

        if (Schema::hasTable('channel_yesterday_views')) {
            foreach (ChannelYesterdayView::query()->whereDate('snapshot_date', $date)->get() as $row) {
                $key = $this->key((string) $row->channel);
                $stored = (int) $row->views;
                if ($stored > 0) {
                    $map[$key] = $stored;
                }
            }
        }

        foreach (['shopify' => fn () => $this->shopifyViews($date), 'ebay' => fn () => $this->ebayViews('ebay_sku_daily_data', $date), 'ebay2' => fn () => $this->ebayViews('ebay2_sku_daily_data', $date)] as $channel => $fn) {
            if (($map[$channel] ?? 0) > 0) {
                continue;
            }
            try {
                $views = $fn();
            } catch (\Throwable $e) {
                Log::warning("Yesterday views live fill failed for {$channel}: ".$e->getMessage());
                continue;
            }
            if ($views === null || $views <= 0) {
                continue;
            }
            $this->store($channel, $date, $views, $channel === 'shopify' ? 'shopifyql' : 'sku_daily_delta');
            $map[$channel] = $views;
        }

        return $map;
    }

    /**
     * L7 listing views for the 7-day dashboard (same marketplace columns as L1, L7 field).
     *
     * @return array<string, int>
     */
    public function viewsByChannelL7(): array
    {
        $sources = [
            'amazon' => ['amazon_datsheets', 'sessions_l7'],
            'ebay' => ['ebay_metrics', 'l7_views'],
            'ebay2' => ['ebay_2_metrics', 'l7_views'],
            'ebay3' => ['ebay_3_metrics', 'l7_views'],
            'shopify' => ['shopify_skus', 'views_l7'],
            'temu' => ['temu_metrics', 'product_clicks_l7'],
            'temu2' => ['temu2_metrics', 'product_clicks_l7'],
            'walmart' => ['walmart_pricing_sales', 'page_views_l7'],
            'doba' => ['doba_sheet_data', 'views_l7'],
            'wayfair' => ['wayfair_product_sheets', 'views_l7'],
            'reverb' => ['reverb_products', 'views_l7'],
            'tiktok' => ['tiktok_products', 'views_l7'],
            'tiktok2' => ['tiktok_products_two', 'views_l7'],
        ];

        $map = [];
        foreach ($sources as $channel => [$table, $l7Col]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $l7Col)) {
                continue;
            }
            $l7 = (int) DB::table($table)->sum($l7Col);
            if ($l7 > 0) {
                $map[$channel] = $l7;
            }
        }

        return $map;
    }

    /**
     * Sum L1 columns on marketplace tables. If L1 is still 0, use L7 ÷ 7
     * (eBay l7_views and Amazon sessions_l7 are already filled by the APIs).
     *
     * @return array<string, int>
     */
    private function sumStoredMarketplaceL1(): array
    {
        $sources = [
            'amazon' => ['amazon_datsheets', 'sessions_l1', 'sessions_l7'],
            'ebay' => ['ebay_metrics', 'l1_views', 'l7_views'],
            'ebay2' => ['ebay_2_metrics', 'l1_views', 'l7_views'],
            'ebay3' => ['ebay_3_metrics', 'l1_views', 'l7_views'],
            'shopify' => ['shopify_skus', 'views_l1', 'views_l7'],
            'temu' => ['temu_metrics', 'product_clicks_l1', 'product_clicks_l7'],
            'temu2' => ['temu2_metrics', 'product_clicks_l1', 'product_clicks_l7'],
            'walmart' => ['walmart_pricing_sales', 'page_views_l1', 'page_views_l7'],
            'doba' => ['doba_sheet_data', 'views_l1', 'views_l7'],
            'wayfair' => ['wayfair_product_sheets', 'views_l1', 'views_l7'],
            'reverb' => ['reverb_products', 'views_l1', 'views_l7'],
            'tiktok' => ['tiktok_products', 'views_l1', 'views_l7'],
            'tiktok2' => ['tiktok_products_two', 'views_l1', 'views_l7'],
        ];

        $map = [];
        foreach ($sources as $channel => [$table, $l1Col, $l7Col]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $l1 = 0;
            if (Schema::hasColumn($table, $l1Col)) {
                $l1 = (int) DB::table($table)->sum($l1Col);
            }
            if ($l1 <= 0 && Schema::hasColumn($table, $l7Col)) {
                $l7 = (int) DB::table($table)->sum($l7Col);
                $l1 = (int) round($l7 / 7);
            }
            if ($l1 <= 0) {
                foreach (['product_clicks_l30', 'product_clicks', 'views'] as $l30Col) {
                    if (! Schema::hasColumn($table, $l30Col)) {
                        continue;
                    }
                    $l30 = (int) DB::table($table)->sum($l30Col);
                    if ($l30 > 0) {
                        $l1 = (int) round($l30 / 30);
                        break;
                    }
                }
            }
            if ($l1 > 0) {
                $map[$channel] = $l1;
            }
        }

        // Temu 2 L30 page uses temu2_view_data.product_clicks, not the thinner metrics L30.
        if (Schema::hasTable('temu2_view_data') && Schema::hasColumn('temu2_view_data', 'product_clicks')) {
            $clicks = (int) DB::table('temu2_view_data')->sum('product_clicks');
            $est = (int) round($clicks / 30);
            if ($est > ($map['temu2'] ?? 0)) {
                $map['temu2'] = $est;
            }
        }

        return $map;
    }

    /**
     * Pull from APIs / daily snapshots and persist.
     *
     * @return array<string, array{views: int, source: string}>
     */
    public function collect(?string $date = null, bool $includeAmazon = true): array
    {
        $date = $date ?: Carbon::yesterday(self::TZ)->toDateString();
        $out = [];

        try {
            $windows = app(MarketplaceViewWindowStore::class)->storeAll($date, $this, $includeAmazon);
            foreach ($windows as $channel => $row) {
                $out[$channel] = [
                    'views' => (int) $row['l1'],
                    'l7_views' => (int) $row['l7'],
                    'source' => $row['source'],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Marketplace L1/L7 view store failed: '.$e->getMessage());
        }

        return $out;
    }

    public function shopifyViewsPublic(string $date): ?int
    {
        return $this->shopifyViews($date);
    }

    /**
     * @return array<string, int>|null path => sessions
     */
    public function shopifyViewsByPath(string $since, string $until): ?array
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version', '2025-01');
        if (empty($storeUrl) || empty($accessToken)) {
            return null;
        }

        $query = "FROM sessions SHOW sessions"
            ." WHERE landing_page_type = 'Product'"
            ." SINCE {$since} UNTIL {$until}"
            .' GROUP BY landing_page_path'
            .' ORDER BY sessions DESC'
            .' LIMIT 5000';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(
            "https://{$storeUrl}/admin/api/{$apiVersion}/graphql.json",
            [
                'query' => 'query($q: String!) { shopifyqlQuery(query: $q) { tableData { rows } parseErrors } }',
                'variables' => ['q' => $query],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('ShopifyQL HTTP '.$response->status());
        }

        $payload = $response->json();
        $parseErrors = $payload['data']['shopifyqlQuery']['parseErrors'] ?? [];
        if (! empty($parseErrors)) {
            throw new \RuntimeException('ShopifyQL parse errors: '.implode('; ', (array) $parseErrors));
        }

        $byPath = [];
        foreach (($payload['data']['shopifyqlQuery']['tableData']['rows'] ?? []) as $row) {
            $path = $this->normalizeShopifyPath((string) ($row['landing_page_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $byPath[$path] = ($byPath[$path] ?? 0) + (int) ($row['sessions'] ?? 0);
        }

        return $byPath;
    }

    /**
     * @return array<string, int>|null ASIN => sessions
     */
    public function amazonSessionsByAsin(string $date): ?array
    {
        $accessToken = $this->amazonAccessToken();
        $marketplaceId = config('services.amazon_sp.marketplace_id');
        if (! $accessToken || empty($marketplaceId)) {
            return null;
        }

        $day = Carbon::parse($date, self::TZ);
        $start = $day->copy()->startOfDay()->utc()->toIso8601ZuluString();
        $end = $day->copy()->endOfDay()->utc()->toIso8601ZuluString();

        $create = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->timeout(60)->post('https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports', [
            'reportType' => 'GET_SALES_AND_TRAFFIC_REPORT',
            'marketplaceIds' => [$marketplaceId],
            'dataStartTime' => $start,
            'dataEndTime' => $end,
            'reportOptions' => ['asinGranularity' => 'CHILD'],
        ]);

        $reportId = $create['reportId'] ?? null;
        if (! $reportId) {
            throw new \RuntimeException('Amazon L1 report create failed: '.$create->body());
        }

        $status = null;
        for ($i = 0; $i < 40; $i++) {
            sleep(15);
            $status = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
            ])->timeout(30)->get("https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports/{$reportId}");
            $processing = $status['processingStatus'] ?? 'UNKNOWN';
            if ($processing === 'DONE') {
                break;
            }
            if (in_array($processing, ['CANCELLED', 'FATAL'], true)) {
                throw new \RuntimeException("Amazon L1 report {$processing}");
            }
        }
        if (($status['processingStatus'] ?? '') !== 'DONE') {
            throw new \RuntimeException('Amazon L1 report timed out');
        }

        $documentId = $status['reportDocumentId'] ?? null;
        if (! $documentId) {
            throw new \RuntimeException('Amazon L1 report missing document');
        }

        $doc = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
        ])->timeout(30)->get("https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/documents/{$documentId}");
        $url = $doc['url'] ?? null;
        if (! $url) {
            throw new \RuntimeException('Amazon L1 report missing download URL');
        }

        $rows = $this->downloadGzRows($url);
        if ($rows === [] || empty($rows[0])) {
            return [];
        }

        $decoded = json_decode($rows[0], true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Amazon L1 report JSON decode failed');
        }

        $byAsin = [];
        foreach (($decoded['salesAndTrafficByAsin'] ?? []) as $asinRow) {
            $asin = (string) ($asinRow['childAsin'] ?? '');
            if ($asin === '') {
                continue;
            }
            $byAsin[$asin] = (int) ($asinRow['trafficByAsin']['sessions'] ?? 0);
        }

        return $byAsin;
    }

    private function normalizeShopifyPath(string $urlOrPath): string
    {
        $raw = trim($urlOrPath);
        if ($raw === '') {
            return '';
        }
        $path = parse_url($raw, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            $path = str_starts_with($raw, '/') ? $raw : '/'.$raw;
        }
        $path = strtolower(rtrim((string) $path, '/'));
        if (preg_match('#(/products/[^/]+)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }

    public function store(string $channel, string $date, int $views, string $source): void
    {
        if (! Schema::hasTable('channel_yesterday_views')) {
            return;
        }

        ChannelYesterdayView::updateOrCreate(
            ['channel' => $this->key($channel), 'snapshot_date' => $date],
            ['views' => max(0, $views), 'source' => $source]
        );
    }

    private function shopifyViews(string $date): ?int
    {
        $storeUrl = config('services.shopify.store_url');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version', '2025-01');
        if (empty($storeUrl) || empty($accessToken)) {
            return null;
        }

        $query = "FROM sessions SHOW sessions"
            ." WHERE landing_page_type = 'Product'"
            ." SINCE {$date} UNTIL {$date}"
            .' GROUP BY landing_page_path'
            .' ORDER BY sessions DESC'
            .' LIMIT 5000';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post(
            "https://{$storeUrl}/admin/api/{$apiVersion}/graphql.json",
            [
                'query' => 'query($q: String!) { shopifyqlQuery(query: $q) { tableData { rows } parseErrors } }',
                'variables' => ['q' => $query],
            ]
        );

        if (! $response->successful()) {
            throw new \RuntimeException('ShopifyQL HTTP '.$response->status());
        }

        $payload = $response->json();
        $parseErrors = $payload['data']['shopifyqlQuery']['parseErrors'] ?? [];
        if (! empty($parseErrors)) {
            throw new \RuntimeException('ShopifyQL parse errors: '.implode('; ', (array) $parseErrors));
        }
        if (! empty($payload['errors'])) {
            $msgs = array_map(fn ($e) => $e['message'] ?? json_encode($e), $payload['errors']);
            throw new \RuntimeException('ShopifyQL errors: '.implode('; ', $msgs));
        }

        $sum = 0;
        foreach (($payload['data']['shopifyqlQuery']['tableData']['rows'] ?? []) as $row) {
            $sum += (int) ($row['sessions'] ?? 0);
        }

        return $sum;
    }

    private function ebayViews(string $table, string $date): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $prev = Carbon::parse($date, self::TZ)->subDay()->toDateString();
        $rows = DB::table($table)
            ->whereIn('record_date', [$prev, $date])
            ->get(['sku', 'record_date', 'daily_data']);

        $bySku = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) $row->sku));
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $day = Carbon::parse($row->record_date)->toDateString();
            $bySku[$sku][$day] = $this->extractViews($row->daily_data ?? null);
        }

        $sum = 0;
        foreach ($bySku as $days) {
            if (! isset($days[$date], $days[$prev])) {
                continue;
            }
            $sum += max(0, (int) $days[$date] - (int) $days[$prev]);
        }

        return $sum;
    }

    private function amazonViews(string $date): ?int
    {
        $byAsin = $this->amazonSessionsByAsin($date);

        return $byAsin === null ? null : array_sum($byAsin);
    }

    private function amazonAccessToken(): ?string
    {
        $res = Http::asForm()->timeout(30)->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => config('services.amazon_sp.refresh_token'),
            'client_id' => config('services.amazon_sp.client_id'),
            'client_secret' => config('services.amazon_sp.client_secret'),
        ]);

        return $res['access_token'] ?? null;
    }

    /**
     * @return list<string>
     */
    private function downloadGzRows(string $url): array
    {
        $response = Http::timeout(120)->get($url);
        if (! $response->ok()) {
            throw new \RuntimeException('Amazon L1 document download failed');
        }

        $gzPath = storage_path('app/temp_yviews_'.uniqid().'.gz');
        $extractedPath = storage_path('app/extracted_yviews_'.uniqid().'.txt');
        file_put_contents($gzPath, $response->body());

        $gz = gzopen($gzPath, 'rb');
        $out = fopen($extractedPath, 'wb');
        if ($gz && $out) {
            while (! gzeof($gz)) {
                fwrite($out, gzread($gz, 4096));
            }
        }
        if ($out) {
            fclose($out);
        }
        if ($gz) {
            gzclose($gz);
        }

        $content = is_file($extractedPath) ? (string) file_get_contents($extractedPath) : '';
        @unlink($gzPath);
        @unlink($extractedPath);

        return $content === '' ? [] : explode("\n", trim($content));
    }

    private function extractViews(mixed $dailyData): int
    {
        if ($dailyData === null || $dailyData === '') {
            return 0;
        }
        $decoded = is_string($dailyData)
            ? (json_decode($dailyData, true) ?: [])
            : (is_array($dailyData) ? $dailyData : []);

        return (int) ($decoded['views'] ?? 0);
    }

    private function key(string $name): string
    {
        $k = preg_replace('/[^a-z0-9]/', '', strtolower($name));

        return match ($k) {
            'ebaytwo', 'ebay2' => 'ebay2',
            'ebaythree', 'ebay3' => 'ebay3',
            'shopifyb2c', 'shopify' => 'shopify',
            default => $k,
        };
    }
}
