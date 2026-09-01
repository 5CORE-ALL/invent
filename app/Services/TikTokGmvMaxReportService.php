<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Last-N-day GMV Max cost from TikTok Marketing API (Ads Manager).
 * Shop product analytics does not include ad spend.
 */
class TikTokGmvMaxReportService
{
    /**
     * @return array{rows: array<string, float>, skipped: bool, reason?: string, pages: int}
     */
    public function fetchItemSpend(string $startDate, string $endDate): array
    {
        $token = trim((string) config('services.tiktok_ads.access_token'));
        $advertiserId = trim((string) config('services.tiktok_ads.advertiser_id'));
        $storeId = trim((string) config('services.tiktok_ads.store_id'));

        if ($token === '' || $advertiserId === '' || $storeId === '') {
            return [
                'rows' => [],
                'skipped' => true,
                'reason' => 'Set TIKTOK_ADS_ACCESS_TOKEN, TIKTOK_ADS_ADVERTISER_ID, and TIKTOK_SHOP_ID (or TIKTOK_ADS_STORE_ID)',
                'pages' => 0,
            ];
        }

        $metricsSets = [
            ['cost', 'orders', 'gross_revenue'],
            ['gmv_max_cost', 'gmv_max_orders', 'gmv_max_gross_revenue'],
        ];

        $lastError = null;
        foreach ($metricsSets as $metrics) {
            $got = $this->pageReport($token, $advertiserId, $storeId, $startDate, $endDate, $metrics);
            if (($got['ok'] ?? false) === true) {
                return [
                    'rows' => $got['rows'],
                    'skipped' => false,
                    'pages' => $got['pages'],
                ];
            }
            $lastError = $got['error'] ?? 'unknown';
        }

        Log::warning('TikTok GMV Max report failed', ['error' => $lastError]);

        return [
            'rows' => [],
            'skipped' => true,
            'reason' => (string) $lastError,
            'pages' => 0,
        ];
    }

    /**
     * @param  list<string>  $metrics
     * @return array{ok: bool, rows?: array<string, float>, pages?: int, error?: string}
     */
    private function pageReport(string $token, string $advertiserId, string $storeId, string $start, string $end, array $metrics): array
    {
        $rows = [];
        $pages = 0;

        for ($page = 1; $page <= 40; $page++) {
            $pages++;
            try {
                $resp = Http::timeout(45)
                    ->acceptJson()
                    ->withHeaders(['Access-Token' => $token])
                    ->get('https://business-api.tiktok.com/open_api/v1.3/gmv_max/report/get/', [
                        'advertiser_id' => $advertiserId,
                        'store_ids' => json_encode([$storeId]),
                        'dimensions' => json_encode(['item_id']),
                        'metrics' => json_encode($metrics),
                        'start_date' => $start,
                        'end_date' => $end,
                        'page' => $page,
                        'page_size' => 100,
                    ]);
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }

            $json = $resp->json();
            if (! is_array($json)) {
                return ['ok' => false, 'error' => 'GMV Max report returned non-JSON (HTTP '.$resp->status().')'];
            }

            $code = (int) ($json['code'] ?? -1);
            if ($code !== 0) {
                return ['ok' => false, 'error' => (string) ($json['message'] ?? 'code '.$code)];
            }

            $list = $json['data']['list'] ?? $json['data']['report_list'] ?? [];
            if (! is_array($list)) {
                $list = [];
            }
            foreach ($list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $dims = is_array($row['dimensions'] ?? null) ? $row['dimensions'] : $row;
                $mets = is_array($row['metrics'] ?? null) ? $row['metrics'] : $row;
                $itemId = (string) ($dims['item_id'] ?? $dims['item_group_id'] ?? $row['item_id'] ?? '');
                if ($itemId === '') {
                    continue;
                }
                $spend = $this->money($mets, ['cost', 'gmv_max_cost', 'net_cost', 'gmv_max_net_cost', 'spend']);
                if ($spend <= 0) {
                    continue;
                }
                $rows[$itemId] = ($rows[$itemId] ?? 0) + $spend;
            }

            $pageInfo = $json['data']['page_info'] ?? [];
            $totalPage = (int) ($pageInfo['total_page'] ?? $pageInfo['total_pages'] ?? 1);
            if ($page >= $totalPage || $list === []) {
                break;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'pages' => $pages];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function money(array $row, array $keys): float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $v = $row[$key];
            if (is_array($v)) {
                $v = $v['amount'] ?? $v['value'] ?? 0;
            }
            $n = (float) $v;
            if ($n > 0) {
                return $n;
            }
        }

        return 0.0;
    }
}
