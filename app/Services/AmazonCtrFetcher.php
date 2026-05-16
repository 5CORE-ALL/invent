<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fetches per-SKU **organic** click-through-rate data from Amazon SP-API
 * (GET_BRAND_ANALYTICS_SEARCH_CATALOG_PERFORMANCE_REPORT) and persists
 * snapshots into the `amazon_ctr_metrics` table with source='organic'.
 *
 * Paid (Ads) CTR is **not** handled here — that data is already fetched
 * daily by `app:amazon-sp-campaign-reports` into `amazon_sp_campaign_reports`,
 * and HeroImageController derives per-SKU paid CTR directly from that table
 * via campaign-name matching. Use that command for paid CTR; this one is
 * dedicated to organic search funnel data.
 *
 * The Brand Analytics report flow is async (POST → poll → download), so this
 * service is called from an artisan command, not a web request.
 */
class AmazonCtrFetcher
{
    /** First 8 polls happen every 5s (covers the typical 30-60s Brand Analytics turnaround) */
    private const POLL_FAST_INTERVAL_SECONDS = 5;

    private const POLL_FAST_ATTEMPTS = 8;

    /** Backoff polls every 30s once the report sits in queue for a while */
    private const POLL_SLOW_INTERVAL_SECONDS = 30;

    /** 8 × 5s + 112 × 30s = ~56 minutes. Brand Analytics can legitimately
     *  sit IN_QUEUE for 30+ minutes when Amazon is backlogged, so cap high. */
    private const POLL_MAX_ATTEMPTS = 120;

    /** Optional progress callback set by the artisan command for live status */
    private mixed $progress = null;

    /** Bind a progress reporter — called with a short human-readable status string */
    public function onProgress(?callable $cb): self
    {
        $this->progress = $cb;

        return $this;
    }

    private function emit(string $msg): void
    {
        if (is_callable($this->progress)) {
            ($this->progress)($msg);
        }
    }

    // ============================================================
    // Organic CTR — SP-API Brand Analytics Search Catalog Performance
    // ============================================================

    /**
     * Fetch one WEEK / MONTH / QUARTER of organic search engagement per ASIN
     * from Brand Analytics, and upsert into amazon_ctr_metrics with source='organic'.
     *
     * @param  string  $period  WEEK | MONTH | QUARTER
     * @return array{rows_upserted: int, report_id: ?string, start_date: string, end_date: string}
     */
    public function fetchOrganicCtr(string $period = 'WEEK', ?string $existingReportId = null): array
    {
        $period = strtoupper($period);
        if (! in_array($period, ['WEEK', 'MONTH', 'QUARTER'], true)) {
            throw new \InvalidArgumentException('period must be WEEK, MONTH, or QUARTER');
        }

        // Default to the most recent completed period.
        [$start, $end] = $this->lastCompletePeriod($period);

        $accessToken = $this->getSpApiAccessToken();
        $endpoint = (string) config('services.amazon_sp_b2.endpoint', 'https://sellingpartnerapi-na.amazon.com');
        $marketplaceId = (string) config('services.amazon_sp_b2.marketplace_id', 'ATVPDKIKX0DER');

        if ($existingReportId !== null && trim($existingReportId) !== '') {
            // Resume mode — skip the create call and poll the supplied report id.
            $reportId = trim($existingReportId);
            $this->emit("Resuming existing reportId={$reportId}");
        } else {
            $this->emit("Requesting Brand Analytics report for {$period} ({$start} → {$end})…");

            $createResp = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(60)
                ->post($endpoint.'/reports/2021-06-30/reports', [
                    'reportType' => 'GET_BRAND_ANALYTICS_SEARCH_CATALOG_PERFORMANCE_REPORT',
                    'marketplaceIds' => [$marketplaceId],
                    'dataStartTime' => $start.'T00:00:00Z',
                    'dataEndTime' => $end.'T23:59:59Z',
                    'reportOptions' => [
                        'reportPeriod' => $period,
                    ],
                ])
                ->throw()
                ->json();

            $reportId = $createResp['reportId'] ?? null;
            if (! $reportId) {
                throw new \RuntimeException('SP-API Brand Analytics: create report response missing reportId. Body='.json_encode($createResp));
            }

            $this->emit("Report queued at Amazon. reportId={$reportId}");
        }

        $documentId = $this->pollSpApiReport($endpoint, $reportId, $accessToken);
        $this->emit('Report ready, downloading…');

        $downloadUrl = $this->getSpApiDocumentUrl($endpoint, $documentId, $accessToken);
        $payload = $this->downloadGzippedJson($downloadUrl);

        $rowCount = is_array($payload['dataByAsin'] ?? null) ? count($payload['dataByAsin']) : 0;
        $this->emit("Parsed {$rowCount} ASIN rows, upserting into amazon_ctr_metrics…");

        $count = $this->upsertOrganicRows($payload, $start, $end, $period);

        return [
            'rows_upserted' => $count,
            'report_id' => $reportId,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    /**
     * Fire-and-forget: request the report and return the reportId immediately
     * without polling. Use `fetchOrganicCtr($period, $reportId)` later to resume.
     *
     * @return array{report_id: string, start_date: string, end_date: string}
     */
    public function requestOrganicReport(string $period = 'WEEK'): array
    {
        $period = strtoupper($period);
        if (! in_array($period, ['WEEK', 'MONTH', 'QUARTER'], true)) {
            throw new \InvalidArgumentException('period must be WEEK, MONTH, or QUARTER');
        }

        [$start, $end] = $this->lastCompletePeriod($period);

        $accessToken = $this->getSpApiAccessToken();
        $endpoint = (string) config('services.amazon_sp_b2.endpoint', 'https://sellingpartnerapi-na.amazon.com');
        $marketplaceId = (string) config('services.amazon_sp_b2.marketplace_id', 'ATVPDKIKX0DER');

        $createResp = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(60)
            ->post($endpoint.'/reports/2021-06-30/reports', [
                'reportType' => 'GET_BRAND_ANALYTICS_SEARCH_CATALOG_PERFORMANCE_REPORT',
                'marketplaceIds' => [$marketplaceId],
                'dataStartTime' => $start.'T00:00:00Z',
                'dataEndTime' => $end.'T23:59:59Z',
                'reportOptions' => ['reportPeriod' => $period],
            ])
            ->throw()
            ->json();

        $reportId = $createResp['reportId'] ?? null;
        if (! $reportId) {
            throw new \RuntimeException('SP-API Brand Analytics: missing reportId in create response.');
        }

        return ['report_id' => (string) $reportId, 'start_date' => $start, 'end_date' => $end];
    }

    private function pollSpApiReport(string $endpoint, string $reportId, string $accessToken): string
    {
        $startedAt = microtime(true);
        $lastStatus = '';
        $stuckInQueueWarned = false;

        for ($attempt = 1; $attempt <= self::POLL_MAX_ATTEMPTS; $attempt++) {
            // Fast polling for the first 40 s, then back off to 30s intervals.
            $sleep = $attempt <= self::POLL_FAST_ATTEMPTS
                ? self::POLL_FAST_INTERVAL_SECONDS
                : self::POLL_SLOW_INTERVAL_SECONDS;
            sleep($sleep);

            $resp = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->get($endpoint.'/reports/2021-06-30/reports/'.$reportId)
                ->throw()
                ->json();

            $status = strtoupper((string) ($resp['processingStatus'] ?? ''));
            $elapsed = (int) round(microtime(true) - $startedAt);
            $lastStatus = $status;

            if ($status === 'DONE') {
                $documentId = $resp['reportDocumentId'] ?? null;
                if (! is_string($documentId) || $documentId === '') {
                    throw new \RuntimeException('SP-API: report DONE but missing reportDocumentId.');
                }
                $this->emit("Report DONE in {$elapsed}s (attempt {$attempt})");

                return $documentId;
            }

            if (in_array($status, ['FATAL', 'CANCELLED'], true)) {
                throw new \RuntimeException(
                    'SP-API: Brand Analytics report '.$status.' — '.json_encode($resp).
                    ' This often means your SP-API app is missing the Brand Analytics role,'.
                    ' or the seller account is not a Brand Registry brand owner.'
                );
            }

            // Heuristic warning: a report stuck IN_QUEUE for >5 min almost
            // always means a permissions/role problem rather than a backlog.
            if (! $stuckInQueueWarned && $status === 'IN_QUEUE' && $elapsed > 300) {
                $this->emit(
                    'Heads up: report has been IN_QUEUE for '.$elapsed.'s without moving to IN_PROGRESS. '.
                    "This usually means the SP-API app doesn't have the Brand Analytics role enabled,".
                    ' or the account is not a Brand Registry brand owner for this marketplace.'.
                    " Continuing to poll; if it stays IN_QUEUE, that's the cause."
                );
                $stuckInQueueWarned = true;
            }

            $this->emit("Polling… attempt {$attempt}/".self::POLL_MAX_ATTEMPTS.", status={$status}, elapsed={$elapsed}s");
            Log::info('SP-API Brand Analytics report still processing', [
                'reportId' => $reportId,
                'attempt' => $attempt,
                'status' => $status,
                'elapsed_s' => $elapsed,
            ]);
        }

        $hint = $lastStatus === 'IN_QUEUE'
            ? ' The report never left IN_QUEUE — almost certainly a Brand Analytics permissions issue.'.
              ' Enable the "Brand Analytics" role on your SP-API app in Seller Central → Develop apps → Edit'.
              ' (then re-authorize), and verify the seller account owns the brand in Brand Registry.'
            : ' Last observed status: '.$lastStatus.'. Try resuming again — Amazon may simply be backlogged.';

        throw new \RuntimeException(
            'SP-API: Brand Analytics report did not complete within '.self::POLL_MAX_ATTEMPTS.' polls (~56 min).'.$hint.
            ' Resume later with: php artisan amazon:fetch-ctr-organic --report-id='.$reportId
        );
    }

    private function getSpApiDocumentUrl(string $endpoint, string $documentId, string $accessToken): string
    {
        $resp = Http::withHeaders([
            'x-amz-access-token' => $accessToken,
            'Accept' => 'application/json',
        ])
            ->timeout(30)
            ->get($endpoint.'/reports/2021-06-30/documents/'.$documentId)
            ->throw()
            ->json();

        $url = $resp['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new \RuntimeException('SP-API: report document response missing url.');
        }

        return $url;
    }

    /**
     * Brand Analytics returns a JSON document of shape:
     *   { "reportSpecification": {...}, "dataByAsin": [ {asin, impressionData, clickData, ...}, ... ] }
     *
     * @param  array<string, mixed>  $payload
     */
    private function upsertOrganicRows(array $payload, string $start, string $end, string $period): int
    {
        $rows = $payload['dataByAsin'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return 0;
        }

        // Build ASIN → SKU lookup so the hero page (keyed by SKU) can join easily.
        $asinToSku = $this->buildAsinToSkuMap(array_filter(array_map(
            fn ($r) => is_array($r) ? ($r['asin'] ?? null) : null,
            $rows
        )));

        $count = 0;
        $now = now();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $asin = trim((string) ($row['asin'] ?? ''));
            if ($asin === '') {
                continue;
            }

            $sku = $asinToSku[$asin] ?? $asin; // fall back to ASIN if no SKU mapping exists

            $impressions = (int) ($row['impressionData']['impressionCount'] ?? 0);
            $clicks = (int) ($row['clickData']['clickCount'] ?? 0);

            // Amazon returns clickRate as a fraction (0.0345) — normalize to percent.
            $rawCtr = $row['clickData']['clickRate'] ?? null;
            $ctr = is_numeric($rawCtr) ? round(((float) $rawCtr) * 100, 4) : $this->normalizeCtr(null, $impressions, $clicks);

            DB::table('amazon_ctr_metrics')->updateOrInsert(
                [
                    'sku' => $sku,
                    'source' => 'organic',
                    'period_start' => $start,
                    'period_end' => $end,
                ],
                [
                    'asin' => $asin,
                    'period_label' => $period,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $ctr,
                    'cart_adds' => isset($row['cartAddData']['cartAddCount']) ? (int) $row['cartAddData']['cartAddCount'] : null,
                    'purchases' => isset($row['purchaseData']['purchaseCount']) ? (int) $row['purchaseData']['purchaseCount'] : null,
                    'spend' => null,
                    'sales' => null,
                    'raw' => json_encode($row),
                    'fetched_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $count++;
        }

        return $count;
    }

    // ============================================================
    // Shared helpers
    // ============================================================

    /**
     * Use the dedicated B2 credentials (same block hero push uses), with
     * automatic fallback to the shared SPAPI_* values via services config.
     */
    private function getSpApiAccessToken(): string
    {
        $clientId = (string) config('services.amazon_sp_b2.client_id');
        $clientSecret = (string) config('services.amazon_sp_b2.client_secret');
        $refreshToken = (string) config('services.amazon_sp_b2.refresh_token');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new \RuntimeException('SP-API credentials missing — set SPAPIB2_* (or SPAPI_*) in .env');
        }

        $resp = Http::asForm()
            ->timeout(30)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if (! $resp->successful()) {
            $body = $resp->json() ?? [];
            throw new \RuntimeException(
                'SP-API LWA token error: '.($body['error'] ?? '').' — '.($body['error_description'] ?? $resp->body())
            );
        }

        $token = $resp->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('SP-API LWA token response missing access_token');
        }

        return $token;
    }

    /**
     * Build ASIN → SKU map from amazon_metrics for the supplied ASIN list.
     *
     * @param  array<int, string>  $asins
     * @return array<string, string>
     */
    private function buildAsinToSkuMap(array $asins): array
    {
        $asins = array_values(array_unique(array_filter(array_map('strval', $asins), fn ($v) => $v !== '')));
        if ($asins === [] || ! Schema::hasTable('amazon_metrics')) {
            return [];
        }

        return DB::table('amazon_metrics')
            ->whereIn('asin', $asins)
            ->whereNotNull('sku')
            ->pluck('sku', 'asin')
            ->toArray();
    }

    /**
     * Decode and parse the GZIP-encoded JSON payload Amazon returns at the
     * report download URL. Tolerates both gzipped + plain JSON.
     */
    private function downloadGzippedJson(string $url): array
    {
        $resp = Http::timeout(120)->withOptions(['decode_content' => true])->get($url);

        if (! $resp->successful()) {
            throw new \RuntimeException('Failed to download Amazon report ('.$resp->status().')');
        }

        $body = $resp->body();
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Some endpoints return raw gzipped bytes that Guzzle didn't unzip.
        $maybe = @gzdecode($body);
        if (is_string($maybe)) {
            $decoded = json_decode($maybe, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Amazon report payload was not JSON (first 200 chars): '.substr($body, 0, 200));
    }

    /**
     * Normalize various CTR formats: Amazon Ads returns clickThroughRate as a
     * percent (3.45), Brand Analytics returns clickRate as a fraction (0.0345).
     * If no value is given, compute clicks / impressions × 100.
     */
    private function normalizeCtr(mixed $value, int $impressions, int $clicks): ?float
    {
        if (is_numeric($value)) {
            $n = (float) $value;
            // Treat anything ≤ 1 as a fraction.
            return round($n <= 1 ? $n * 100 : $n, 4);
        }
        if ($impressions <= 0) {
            return null;
        }

        return round(($clicks / $impressions) * 100, 4);
    }

    /**
     * @return array{0: string, 1: string} [start, end] as YYYY-MM-DD for the
     * most recently completed period of the requested grain.
     */
    private function lastCompletePeriod(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'WEEK' => [
                $now->copy()->subWeek()->startOfWeek()->toDateString(),
                $now->copy()->subWeek()->endOfWeek()->toDateString(),
            ],
            'MONTH' => [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'QUARTER' => [
                $now->copy()->subQuarter()->startOfQuarter()->toDateString(),
                $now->copy()->subQuarter()->endOfQuarter()->toDateString(),
            ],
            default => throw new \InvalidArgumentException('Unsupported period '.$period),
        };
    }
}
