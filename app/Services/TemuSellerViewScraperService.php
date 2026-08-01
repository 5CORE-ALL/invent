<?php

namespace App\Services;

use App\Models\TemuViewData;
use App\Support\TemuGoodsIdHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Scrape Temu Seller Center product analytics (page clicks / impressions)
 * into temu_view_data. Temu OpenAPI has no organic Views endpoint — Seller
 * Center XHR (cookie session) is the only automated path besides Excel upload.
 *
 * Cookies must come from a logged-in seller.temu.com / agentseller.temu.com session.
 * Endpoints are configurable because Temu rotates internal paths frequently.
 */
class TemuSellerViewScraperService
{
    /** Common click field names seen in Seller Center / ERP captures */
    private const CLICK_KEYS = [
        'productClicks', 'product_clicks', 'clickCnt', 'clkCnt', 'goodsClickCnt',
        'clickCount', 'clicks', 'goodsClicks', 'skuClickCnt', 'detailClickCnt',
    ];

    private const IMPR_KEYS = [
        'productImpressions', 'product_impressions', 'imprCnt', 'impressions',
        'exposeCnt', 'exposureCnt', 'goodsImprCnt', 'impressionCount',
    ];

    private const VISITOR_CLICK_KEYS = [
        'visitorClicks', 'visitor_clicks', 'uvClick', 'clickUv', 'visitorClickCnt',
    ];

    private const VISITOR_IMPR_KEYS = [
        'visitorImpressions', 'visitor_impressions', 'uvImpr', 'exposeUv', 'visitorImprCnt',
    ];

    private const GOODS_ID_KEYS = [
        'goodsId', 'goods_id', 'spuId', 'productId', 'goodsID',
    ];

    private const GOODS_NAME_KEYS = [
        'goodsName', 'goods_name', 'productName', 'title', 'spuName',
    ];

    /**
     * @return array{ok: bool, imported: int, skipped: int, deleted: int, endpoint?: string, message: string, samples?: array}
     */
    public function scrape(?string $cookieOverride = null, int $days = 30, bool $replace = true): array
    {
        if (! Schema::hasTable('temu_view_data')) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'deleted' => 0, 'message' => 'temu_view_data table missing — run migrations'];
        }

        $cookie = trim((string) ($cookieOverride ?: config('services.temu.seller_cookie', '')));
        if ($cookie === '') {
            return [
                'ok' => false,
                'imported' => 0,
                'skipped' => 0,
                'deleted' => 0,
                'message' => 'Missing Seller Center cookie. Paste cookie from browser (seller.temu.com / agentseller.temu.com) or set TEMU_SELLER_COOKIE.',
            ];
        }

        $days = max(1, min(60, $days));
        $end = Carbon::yesterday()->endOfDay();
        $start = Carbon::yesterday()->subDays($days - 1)->startOfDay();

        $endpoints = $this->resolveEndpoints();
        $lastError = 'No endpoints configured';

        foreach ($endpoints as $endpoint) {
            try {
                $payload = $this->buildRequestPayload($endpoint, $start, $end);
                $response = $this->httpClient($cookie, $endpoint['base'])
                    ->post($endpoint['url'], $payload);

                if (! $response->successful()) {
                    $lastError = "HTTP {$response->status()} on {$endpoint['url']}";
                    Log::warning('TemuSellerViewScraper: HTTP fail', [
                        'url' => $endpoint['url'],
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500),
                    ]);
                    continue;
                }

                $json = $response->json();
                if (! is_array($json)) {
                    $lastError = "Non-JSON response from {$endpoint['url']}";
                    continue;
                }

                if ($this->looksLikeAuthFailure($json, $response->body())) {
                    $lastError = 'Seller session expired / unauthorized — refresh cookie from browser';
                    continue;
                }

                $rows = $this->extractRows($json);
                if (empty($rows)) {
                    $lastError = "Parsed 0 goods rows from {$endpoint['url']} (endpoint may have changed)";
                    Log::info('TemuSellerViewScraper: empty parse', [
                        'url' => $endpoint['url'],
                        'top_keys' => array_keys($json),
                    ]);
                    continue;
                }

                $persist = $this->persistRows($rows, $start->toDateString(), $replace);

                return [
                    'ok' => true,
                    'imported' => $persist['imported'],
                    'skipped' => $persist['skipped'],
                    'deleted' => $persist['deleted'],
                    'endpoint' => $endpoint['url'],
                    'message' => "Scraped {$persist['imported']} goods from Seller Center ({$endpoint['label']})",
                    'samples' => array_slice($rows, 0, 3),
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('TemuSellerViewScraper: '.$e->getMessage(), ['url' => $endpoint['url'] ?? null]);
            }
        }

        return [
            'ok' => false,
            'imported' => 0,
            'skipped' => 0,
            'deleted' => 0,
            'message' => $lastError.' — Tip: open Seller Center Product Analytics → DevTools Network → copy response JSON → use Import JSON.',
        ];
    }

    /**
     * Import a pasted Seller Center Network JSON response into temu_view_data.
     *
     * @return array{ok: bool, imported: int, skipped: int, deleted: int, message: string, samples?: array}
     */
    public function importJson(string $rawJson, ?string $date = null, bool $replace = true): array
    {
        if (! Schema::hasTable('temu_view_data')) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'deleted' => 0, 'message' => 'temu_view_data table missing'];
        }

        $json = json_decode($rawJson, true);
        if (! is_array($json)) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'deleted' => 0, 'message' => 'Invalid JSON'];
        }

        $rows = $this->extractRows($json);
        if (empty($rows)) {
            return [
                'ok' => false,
                'imported' => 0,
                'skipped' => 0,
                'deleted' => 0,
                'message' => 'No goodsId + clicks rows found in JSON. Paste the Product Analytics list response.',
            ];
        }

        $asOf = $date ?: Carbon::yesterday()->toDateString();
        $persist = $this->persistRows($rows, $asOf, $replace);

        return [
            'ok' => true,
            'imported' => $persist['imported'],
            'skipped' => $persist['skipped'],
            'deleted' => $persist['deleted'],
            'message' => "Imported {$persist['imported']} goods from pasted JSON",
            'samples' => array_slice($rows, 0, 3),
        ];
    }

    /**
     * Probe endpoints and return raw status (no DB write).
     *
     * @return array<int, array{url: string, status: int|null, ok: bool, row_count: int, error?: string}>
     */
    public function probe(?string $cookieOverride = null, int $days = 30): array
    {
        $cookie = trim((string) ($cookieOverride ?: config('services.temu.seller_cookie', '')));
        $results = [];
        if ($cookie === '') {
            return [['url' => '-', 'status' => null, 'ok' => false, 'row_count' => 0, 'error' => 'No cookie']];
        }

        $end = Carbon::yesterday()->endOfDay();
        $start = Carbon::yesterday()->subDays(max(1, min(60, $days)) - 1)->startOfDay();

        foreach ($this->resolveEndpoints() as $endpoint) {
            try {
                $response = $this->httpClient($cookie, $endpoint['base'])
                    ->post($endpoint['url'], $this->buildRequestPayload($endpoint, $start, $end));
                $json = $response->json();
                $rows = is_array($json) ? $this->extractRows($json) : [];
                $results[] = [
                    'url' => $endpoint['url'],
                    'status' => $response->status(),
                    'ok' => $response->successful() && count($rows) > 0,
                    'row_count' => count($rows),
                    'error' => $response->successful()
                        ? (count($rows) ? null : '0 rows parsed')
                        : substr($response->body(), 0, 200),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'url' => $endpoint['url'],
                    'status' => null,
                    'ok' => false,
                    'row_count' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<int, array{goods_id: string, goods_name: ?string, product_clicks: int, product_impressions: int, visitor_clicks: int, visitor_impressions: int, ctr: float}>  $rows
     * @return array{imported: int, skipped: int, deleted: int}
     */
    public function persistRows(array $rows, string $date, bool $replace = true): array
    {
        $deleted = 0;
        if ($replace) {
            $deleted = TemuViewData::query()->delete();
        }

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $goodsId = TemuGoodsIdHelper::normalizeKey($row['goods_id'] ?? null);
            if ($goodsId === null || $goodsId === '') {
                $skipped++;
                continue;
            }

            TemuViewData::updateOrCreate(
                ['date' => $date, 'goods_id' => $goodsId],
                [
                    'goods_name' => $row['goods_name'] ?? null,
                    'product_impressions' => (int) ($row['product_impressions'] ?? 0),
                    'visitor_impressions' => (int) ($row['visitor_impressions'] ?? 0),
                    'product_clicks' => (int) ($row['product_clicks'] ?? 0),
                    'visitor_clicks' => (int) ($row['visitor_clicks'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                ]
            );
            $imported++;
        }

        return compact('imported', 'skipped', 'deleted');
    }

    /**
     * Walk arbitrary JSON and collect goods rows with click metrics.
     *
     * @return array<int, array{goods_id: string, goods_name: ?string, product_clicks: int, product_impressions: int, visitor_clicks: int, visitor_impressions: int, ctr: float}>
     */
    public function extractRows(array $json): array
    {
        $found = [];
        $this->walk($json, $found);

        // Dedupe by goods_id (keep max clicks)
        $byGoods = [];
        foreach ($found as $row) {
            $gid = $row['goods_id'];
            if (! isset($byGoods[$gid]) || $row['product_clicks'] > $byGoods[$gid]['product_clicks']) {
                $byGoods[$gid] = $row;
            }
        }

        return array_values($byGoods);
    }

    private function walk($node, array &$found): void
    {
        if (! is_array($node)) {
            return;
        }

        if ($this->isAssoc($node) && $this->looksLikeGoodsMetricRow($node)) {
            $goodsId = $this->firstString($node, self::GOODS_ID_KEYS);
            $goodsId = TemuGoodsIdHelper::normalizeKey($goodsId);
            if ($goodsId) {
                $clicks = $this->firstInt($node, self::CLICK_KEYS);
                $impr = $this->firstInt($node, self::IMPR_KEYS);
                $vClicks = $this->firstInt($node, self::VISITOR_CLICK_KEYS);
                $vImpr = $this->firstInt($node, self::VISITOR_IMPR_KEYS);
                $ctr = 0.0;
                if (isset($node['ctr']) || isset($node['CTR']) || isset($node['clickRate'])) {
                    $raw = $node['ctr'] ?? $node['CTR'] ?? $node['clickRate'];
                    $ctr = (float) str_replace('%', '', (string) $raw);
                    if ($ctr > 0 && $ctr <= 1) {
                        $ctr *= 100;
                    }
                } elseif ($impr > 0) {
                    $ctr = round(($clicks / $impr) * 100, 2);
                }

                $found[] = [
                    'goods_id' => $goodsId,
                    'goods_name' => $this->firstString($node, self::GOODS_NAME_KEYS),
                    'product_clicks' => $clicks,
                    'product_impressions' => $impr,
                    'visitor_clicks' => $vClicks,
                    'visitor_impressions' => $vImpr,
                    'ctr' => $ctr,
                ];
            }
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->walk($child, $found);
            }
        }
    }

    private function looksLikeGoodsMetricRow(array $node): bool
    {
        $hasGoods = $this->firstString($node, self::GOODS_ID_KEYS) !== null;
        $hasMetric = $this->firstInt($node, array_merge(self::CLICK_KEYS, self::IMPR_KEYS)) > 0
            || $this->hasAnyKey($node, array_merge(self::CLICK_KEYS, self::IMPR_KEYS));

        return $hasGoods && $hasMetric;
    }

    private function hasAnyKey(array $node, array $keys): bool
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $node)) {
                return true;
            }
        }

        return false;
    }

    private function firstString(array $node, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($node[$k]) && $node[$k] !== '' && $node[$k] !== null) {
                return (string) $node[$k];
            }
        }

        return null;
    }

    private function firstInt(array $node, array $keys): int
    {
        foreach ($keys as $k) {
            if (isset($node[$k]) && is_numeric($node[$k])) {
                return (int) $node[$k];
            }
        }

        return 0;
    }

    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function looksLikeAuthFailure(array $json, string $body): bool
    {
        $code = $json['error_code'] ?? $json['errorCode'] ?? $json['code'] ?? null;
        $msg = strtolower((string) ($json['error_msg'] ?? $json['errorMsg'] ?? $json['message'] ?? ''));
        if (in_array($code, [401, 403, '401', '403', 40001, 70001], true)) {
            return true;
        }
        if (str_contains($msg, 'login') || str_contains($msg, 'auth') || str_contains($msg, 'token')) {
            return true;
        }
        if (str_contains(strtolower($body), 'login') && str_contains(strtolower($body), 'redirect')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, array{label: string, base: string, url: string, body: string}>
     */
    private function resolveEndpoints(): array
    {
        $custom = config('services.temu.seller_view_endpoints');
        if (is_string($custom) && trim($custom) !== '') {
            $decoded = json_decode($custom, true);
            if (is_array($decoded) && $decoded !== []) {
                return array_map(function ($e) {
                    return [
                        'label' => $e['label'] ?? 'custom',
                        'base' => $e['base'] ?? 'https://seller.temu.com',
                        'url' => $e['url'],
                        'body' => $e['body'] ?? 'default',
                    ];
                }, $decoded);
            }
        }

        $sellerBase = rtrim((string) config('services.temu.seller_base_url', 'https://seller.temu.com'), '/');
        $agentBase = rtrim((string) config('services.temu.agentseller_base_url', 'https://agentseller.temu.com'), '/');

        // Candidate internal paths (Temu rotates these — override via TEMU_SELLER_VIEW_ENDPOINTS JSON).
        return [
            [
                'label' => 'seller-flow-goods',
                'base' => $sellerBase,
                'url' => $sellerBase.'/api/flow/analysis/goods/list',
                'body' => 'default',
            ],
            [
                'label' => 'seller-goods-analysis',
                'base' => $sellerBase,
                'url' => $sellerBase.'/api/goods/analysis/list',
                'body' => 'default',
            ],
            [
                'label' => 'agentseller-sales-goods',
                'base' => $agentBase,
                'url' => $agentBase.'/api/seller/data/goods/list',
                'body' => 'default',
            ],
            [
                'label' => 'agentseller-magnus-goods',
                'base' => $agentBase,
                'url' => $agentBase.'/api/kiana/magnus/goods/queryGoodsDetailList',
                'body' => 'magnus',
            ],
        ];
    }

    private function buildRequestPayload(array $endpoint, Carbon $start, Carbon $end): array
    {
        $startMs = (int) ($start->timestamp * 1000);
        $endMs = (int) ($end->timestamp * 1000);

        if (($endpoint['body'] ?? '') === 'magnus') {
            return [
                'pageNo' => 1,
                'pageSize' => 100,
                'startTime' => $startMs,
                'endTime' => $endMs,
            ];
        }

        return [
            'pageNum' => 1,
            'pageNo' => 1,
            'pageSize' => 100,
            'startTime' => $startMs,
            'endTime' => $endMs,
            'startTs' => $startMs,
            'endTs' => $endMs,
            'beginTime' => $start->timestamp,
            'finishTime' => $end->timestamp,
            'timeDimension' => 1,
            'queryType' => 1,
        ];
    }

    private function httpClient(string $cookie, string $base)
    {
        $headers = [
            'Cookie' => $cookie,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/plain, */*',
            'Origin' => $base,
            'Referer' => rtrim($base, '/').'/',
            'User-Agent' => config('services.temu.seller_user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'),
            'mallid' => (string) config('services.temu.seller_mall_id', ''),
        ];

        $anti = trim((string) config('services.temu.seller_anti_content', ''));
        if ($anti !== '') {
            $headers['anti-content'] = $anti;
        }

        $req = Http::withHeaders(array_filter($headers))
            ->timeout(60)
            ->retry(1, 500);

        if (config('filesystems.default') === 'local') {
            $req = $req->withoutVerifying();
        }

        return $req;
    }
}
