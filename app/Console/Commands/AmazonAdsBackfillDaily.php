<?php

namespace App\Console\Commands;

use App\Models\AmazonSbCampaignReport;
use App\Models\AmazonSpCampaignReport;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Backfill missing DAILY campaign report rows (report_date_range = YYYY-MM-DD) for SP and SB.
 *
 * Amazon only serves roughly the last 95 days of daily reporting, so anything older cannot be recovered.
 * One DAILY report is requested per contiguous run of missing days (max 31 days per request); each row
 * carries its own `date`, which becomes the row's report_date_range. Existing days are skipped unless
 * --force is passed. Bid / note columns on existing rows are never touched.
 */
class AmazonAdsBackfillDaily extends Command
{
    protected $signature = 'app:amazon-ads-backfill-daily
        {--type=all : sp, sb or all}
        {--from= : First day to fill (Y-m-d). Default: 95 days ago}
        {--to= : Last day to fill (Y-m-d). Default: yesterday}
        {--force : Re-download days that already have rows (upserts, never deletes)}
        {--dry-run : Only print which days / windows would be requested}';

    protected $description = 'Backfill missing daily Amazon Ads campaign report rows (SP / SB) from the Reporting API (max ~95 days back)';

    private const MAX_WINDOW_DAYS = 31;
    private const MAX_LOOKBACK_DAYS = 95;
    private const POLL_SECONDS = 30;
    private const TIMEOUT_SECONDS = 3600;

    /** @var array<string, array{model: class-string<Model>, table: string, adProduct: string, reportTypeId: string}> */
    private const TYPES = [
        'sp' => [
            'model' => AmazonSpCampaignReport::class,
            'table' => 'amazon_sp_campaign_reports',
            'adProduct' => 'SPONSORED_PRODUCTS',
            'reportTypeId' => 'spCampaigns',
        ],
        'sb' => [
            'model' => AmazonSbCampaignReport::class,
            'table' => 'amazon_sb_campaign_reports',
            'adProduct' => 'SPONSORED_BRANDS',
            'reportTypeId' => 'sbCampaigns',
        ],
    ];

    public function handle(): int
    {
        $profileId = config('services.amazon_ads.profile_ids');
        if (empty($profileId)) {
            $this->error('AMAZON_ADS_PROFILE_IDS is not set in environment.');

            return 1;
        }

        $type = strtolower((string) $this->option('type'));
        $types = $type === 'all' ? array_keys(self::TYPES) : (isset(self::TYPES[$type]) ? [$type] : []);
        if ($types === []) {
            $this->error('--type must be sp, sb or all.');

            return 1;
        }

        $yesterday = now()->subDay()->startOfDay();
        $earliest = now()->subDays(self::MAX_LOOKBACK_DAYS)->startOfDay();
        try {
            $from = $this->option('from') ? \Carbon\Carbon::parse((string) $this->option('from'))->startOfDay() : $earliest->copy();
            $to = $this->option('to') ? \Carbon\Carbon::parse((string) $this->option('to'))->startOfDay() : $yesterday->copy();
        } catch (\Throwable $e) {
            $this->error('Invalid --from / --to date.');

            return 1;
        }
        if ($from->lt($earliest)) {
            $this->warn("--from {$from->toDateString()} is older than Amazon's ~".self::MAX_LOOKBACK_DAYS."-day limit; using {$earliest->toDateString()}.");
            $from = $earliest->copy();
        }
        if ($to->gt($yesterday)) {
            $to = $yesterday->copy();
        }
        if ($from->gt($to)) {
            $this->error('Nothing to do: --from is after --to.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        // Plan every window first, then submit all of them, then poll (much faster than one report at a time).
        $jobs = [];
        foreach ($types as $key) {
            $cfg = self::TYPES[$key];
            $days = $this->daysToFetch($cfg['table'], $from->copy(), $to->copy(), $force);
            $windows = $this->windows($days);
            $this->info(strtoupper($key).': '.count($days).' day(s) to fetch in '.count($windows).' request(s).');
            foreach ($windows as [$start, $end]) {
                $this->line("  {$key} {$start} -> {$end}");
                $jobs[] = ['key' => $key, 'cfg' => $cfg, 'start' => $start, 'end' => $end, 'reportId' => null, 'done' => false];
            }
        }

        if ($dryRun || $jobs === []) {
            $this->info($dryRun ? 'Dry run only; nothing requested.' : 'No missing days. Nothing to do.');

            return 0;
        }

        foreach ($jobs as $i => $job) {
            $jobs[$i]['reportId'] = $this->requestReport((string) $profileId, $job['cfg'], $job['start'], $job['end']);
            if ($jobs[$i]['reportId'] === null) {
                $jobs[$i]['done'] = true;
                $this->error("Could not request {$job['key']} {$job['start']} -> {$job['end']}.");
            } else {
                $this->info("Requested {$job['key']} {$job['start']} -> {$job['end']} (report {$jobs[$i]['reportId']}).");
            }
        }

        $deadline = now()->addSeconds(self::TIMEOUT_SECONDS);
        while (now()->lt($deadline)) {
            $pending = 0;
            foreach ($jobs as $i => $job) {
                if ($job['done']) {
                    continue;
                }
                $status = $this->reportStatus((string) $profileId, (string) $job['reportId']);
                if ($status['status'] === 'COMPLETED' && $status['url']) {
                    $this->storeReport((string) $profileId, $job, $status['url']);
                    $jobs[$i]['done'] = true;
                } elseif ($status['status'] === 'FAILED') {
                    $this->error("Report {$job['reportId']} ({$job['key']} {$job['start']} -> {$job['end']}) failed.");
                    $jobs[$i]['done'] = true;
                } else {
                    $pending++;
                }
            }
            if ($pending === 0) {
                break;
            }
            $this->line("Waiting on {$pending} report(s)...");
            sleep(self::POLL_SECONDS);
        }

        foreach ($jobs as $job) {
            if (! $job['done']) {
                $this->error("Timed out waiting for report {$job['reportId']} ({$job['key']} {$job['start']} -> {$job['end']}); rerun to retry.");
            }
        }

        $this->info('Backfill finished.');

        return 0;
    }

    /**
     * @return list<string> Y-m-d days to request
     */
    private function daysToFetch(string $table, \Carbon\Carbon $from, \Carbon\Carbon $to, bool $force): array
    {
        $have = [];
        if (! $force) {
            $have = DB::table($table)
                ->whereRaw('CHAR_LENGTH(report_date_range) = 10')
                ->whereBetween('report_date_range', [$from->toDateString(), $to->toDateString()])
                ->distinct()
                ->pluck('report_date_range')
                ->flip()
                ->all();
        }
        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $ymd = $d->toDateString();
            if (! isset($have[$ymd])) {
                $days[] = $ymd;
            }
        }

        return $days;
    }

    /**
     * Group sorted days into contiguous runs of at most MAX_WINDOW_DAYS.
     *
     * @param  list<string>  $days
     * @return list<array{0: string, 1: string}>
     */
    private function windows(array $days): array
    {
        $out = [];
        $start = null;
        $prev = null;
        $len = 0;
        foreach ($days as $day) {
            $contiguous = $prev !== null && date('Y-m-d', strtotime($prev.' +1 day')) === $day;
            if ($start === null || ! $contiguous || $len >= self::MAX_WINDOW_DAYS) {
                if ($start !== null) {
                    $out[] = [$start, $prev];
                }
                $start = $day;
                $len = 0;
            }
            $prev = $day;
            $len++;
        }
        if ($start !== null) {
            $out[] = [$start, $prev];
        }

        return $out;
    }

    private function headers(string $profileId, string $contentType): ?array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return null;
        }

        return [
            'Authorization' => 'Bearer '.$token,
            'Amazon-Advertising-API-Scope' => $profileId,
            'Amazon-Advertising-API-ClientId' => (string) config('services.amazon_ads.client_id'),
            'Content-Type' => $contentType,
        ];
    }

    private function requestReport(string $profileId, array $cfg, string $start, string $end, int $attempt = 0): ?string
    {
        $headers = $this->headers($profileId, 'application/vnd.createasyncreportrequest.v3+json');
        if ($headers === null) {
            return null;
        }

        $response = Http::timeout(30)->withHeaders($headers)->post('https://advertising-api.amazon.com/reporting/reports', [
            'name' => "{$cfg['adProduct']}_backfill_{$start}_{$end}",
            'startDate' => $start,
            'endDate' => $end,
            'configuration' => [
                'adProduct' => $cfg['adProduct'],
                'groupBy' => ['campaign'],
                'reportTypeId' => $cfg['reportTypeId'],
                'columns' => $this->columns($cfg['reportTypeId']),
                'format' => 'GZIP_JSON',
                'timeUnit' => 'DAILY',
            ],
        ]);

        if ($response->status() === 425) {
            // Duplicate request: Amazon returns the existing report id in the detail text.
            $detail = (string) $response->json('detail');
            $id = trim(str_replace('The Request is a duplicate of : ', '', $detail));

            return $id !== '' ? $id : null;
        }
        if ($response->status() === 429 && $attempt < 5) {
            $this->warn('Rate limited; waiting 60s...');
            sleep(60);

            return $this->requestReport($profileId, $cfg, $start, $end, $attempt + 1);
        }
        if (! $response->ok()) {
            $this->error("Request failed ({$response->status()}): ".$response->body());

            return null;
        }

        return $response->json('reportId');
    }

    /**
     * @return array{status: string, url: ?string}
     */
    private function reportStatus(string $profileId, string $reportId): array
    {
        $headers = $this->headers($profileId, 'application/vnd.getasyncreportresponse.v3+json');
        if ($headers === null) {
            return ['status' => 'UNKNOWN', 'url' => null];
        }
        $res = Http::timeout(60)->retry(3, 3000)->withHeaders($headers)
            ->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");
        if (! $res->successful()) {
            return ['status' => 'UNKNOWN', 'url' => null];
        }

        return [
            'status' => (string) ($res['status'] ?? 'UNKNOWN'),
            'url' => $res['location'] ?? $res['url'] ?? null,
        ];
    }

    private function storeReport(string $profileId, array $job, string $url): void
    {
        $label = "{$job['key']} {$job['start']} -> {$job['end']}";
        $response = Http::timeout(120)->retry(3, 3000)->withoutVerifying()->get($url);
        if (! $response->ok()) {
            $this->error("[$label] Download failed.");

            return;
        }
        $json = gzdecode($response->body());
        $rows = $json ? json_decode($json, true) : null;
        if (! is_array($rows) || $rows === []) {
            $this->warn("[$label] No rows returned (no activity in this window).");

            return;
        }

        /** @var Model $model */
        $model = new $job['cfg']['model']();
        $fillable = array_flip($model->getFillable());
        $stored = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $cid = $row['campaignId'] ?? null;
            $date = $row['date'] ?? null;
            if (empty($cid) || ! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $skipped++;

                continue;
            }
            $row = $job['key'] === 'sp' ? $this->normalizeSp($row) : $this->normalizeSb($row);
            $payload = array_intersect_key($row, $fillable);
            $payload['profile_id'] = $profileId;
            $payload['ad_type'] = $job['cfg']['adProduct'];
            $payload['report_date_range'] = $date;
            $payload['campaign_id'] = $cid;
            if (isset($fillable['date'])) {
                $payload['date'] = $date;
            }
            try {
                $job['cfg']['model']::updateOrCreate(
                    ['campaign_id' => $cid, 'profile_id' => $profileId, 'report_date_range' => $date],
                    $payload
                );
                $stored++;
            } catch (\Throwable $e) {
                $skipped++;
                $this->warn("[$label] Row error: ".$e->getMessage());
            }
        }
        $this->info("[$label] Stored {$stored} row(s), skipped {$skipped}.");
        DB::connection()->disconnect();
    }

    private function normalizeSp(array $row): array
    {
        $cost = isset($row['cost']) ? round((float) $row['cost'], 4) : (isset($row['spend']) ? round((float) $row['spend'], 4) : null);
        $row['cost'] = $cost;
        $row['spend'] = isset($row['spend']) ? round((float) $row['spend'], 4) : $cost;
        $row['clicks'] = isset($row['clicks']) ? (int) round((float) $row['clicks']) : 0;
        if (empty($row['costPerClick']) && $row['clicks'] > 0 && $cost !== null) {
            $row['costPerClick'] = round($cost / $row['clicks'], 4);
        }

        return $row;
    }

    private function normalizeSb(array $row): array
    {
        $status = $row['campaignStatus'] ?? $row['state'] ?? null;
        $row['campaignStatus'] = ($status === null || $status === '') ? 'ENABLED' : strtoupper(trim((string) $status));

        return $row;
    }

    /**
     * Same metric lists as the daily SP / SB report commands, plus `date`.
     *
     * @return list<string>
     */
    private function columns(string $reportTypeId): array
    {
        if ($reportTypeId === 'spCampaigns') {
            return [
                'date', 'impressions', 'clicks', 'cost', 'spend', 'purchases1d', 'purchases7d',
                'purchases14d', 'purchases30d', 'sales1d', 'sales7d', 'sales14d', 'sales30d',
                'unitsSoldClicks1d', 'unitsSoldClicks7d', 'unitsSoldClicks14d', 'unitsSoldClicks30d',
                'attributedSalesSameSku1d', 'attributedSalesSameSku7d', 'attributedSalesSameSku14d', 'attributedSalesSameSku30d',
                'unitsSoldSameSku1d', 'unitsSoldSameSku7d', 'unitsSoldSameSku14d', 'unitsSoldSameSku30d',
                'clickThroughRate', 'costPerClick', 'qualifiedBorrows', 'addToList',
                'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode',
                'royaltyQualifiedBorrows', 'purchasesSameSku1d', 'purchasesSameSku7d', 'purchasesSameSku14d',
                'purchasesSameSku30d', 'kindleEditionNormalizedPagesRead14d', 'kindleEditionNormalizedPagesRoyalties14d',
                'campaignBiddingStrategy', 'campaignStatus',
            ];
        }

        return [
            'date', 'addToCart', 'addToCartClicks', 'addToCartRate', 'addToList', 'addToListFromClicks', 'qualifiedBorrows', 'qualifiedBorrowsFromClicks',
            'royaltyQualifiedBorrows', 'royaltyQualifiedBorrowsFromClicks', 'impressions', 'clicks', 'cost', 'sales', 'salesClicks', 'purchases', 'purchasesClicks',
            'brandedSearches', 'brandedSearchesClicks', 'newToBrandSales', 'newToBrandSalesClicks', 'newToBrandPurchases', 'newToBrandPurchasesClicks',
            'campaignBudgetType', 'videoCompleteViews', 'videoUnmutes', 'viewabilityRate', 'viewClickThroughRate',
            'detailPageViews', 'detailPageViewsClicks', 'eCPAddToCart', 'newToBrandDetailPageViewRate', 'newToBrandDetailPageViews',
            'newToBrandDetailPageViewsClicks', 'newToBrandECPDetailPageView', 'newToBrandPurchasesPercentage', 'newToBrandUnitsSold', 'newToBrandUnitsSoldClicks',
            'newToBrandUnitsSoldPercentage', 'unitsSold', 'unitsSoldClicks', 'topOfSearchImpressionShare', 'newToBrandPurchasesRate',
            'campaignId', 'campaignName', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode', 'newToBrandSalesPercentage',
            'campaignStatus', 'salesPromoted', 'video5SecondViewRate', 'video5SecondViews', 'videoFirstQuartileViews', 'videoMidpointViews',
            'videoThirdQuartileViews', 'viewableImpressions',
        ];
    }

    private function getAccessToken(): ?string
    {
        $clientId = config('services.amazon_ads.client_id');
        $clientSecret = config('services.amazon_ads.client_secret');
        $refreshToken = config('services.amazon_ads.refresh_token');
        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            $this->error('Amazon Ads credentials are not set in environment.');

            return null;
        }
        $res = Http::timeout(15)->asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        if (! $res->successful() || empty($res['access_token'])) {
            $this->error('Token fetch failed: '.$res->body());

            return null;
        }

        return (string) $res['access_token'];
    }
}
