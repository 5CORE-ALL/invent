<?php

namespace App\Console\Commands;

use App\Models\AmazonTodayAdSpend;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls today's (Pacific day so far) Amazon ad spend for Sponsored Products and Sponsored Brands
 * so /all-marketplace-master can show T Ads% = T Spend ÷ T Total Sales.
 *
 * The regular campaign report jobs only fetch finished days (L1 = yesterday). This one asks the
 * same Amazon Ads reporting API for a single-day report ending today. Amazon reports lag by a
 * few hours, so the figure is "spend reported so far today" and grows through the day.
 */
class AmazonPullTodayAdSpend extends Command
{
    protected $signature = 'amazon:pull-today-ad-spend {--date= : Pacific YYYY-MM-DD (default today)} {--timeout=1500 : Max seconds to wait per report}';

    protected $description = "Pull today's Amazon SP + SB ad spend for the T Ads% column on /all-marketplace-master";

    private const REPORTS = [
        'sp_spend' => ['SPONSORED_PRODUCTS', 'spCampaigns'],
        'sb_spend' => ['SPONSORED_BRANDS', 'sbCampaigns'],
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('amazon_today_ad_spend')) {
            $this->error('amazon_today_ad_spend table is missing. Run php artisan migrate.');

            return self::FAILURE;
        }

        $profileId = config('services.amazon_ads.profile_ids');
        if (empty($profileId)) {
            $this->error('AMAZON_ADS_PROFILE_IDS is not set in environment.');

            return self::FAILURE;
        }

        $date = (string) ($this->option('date') ?: AmazonTodayAdSpend::pacificToday());
        $timeout = max(60, (int) $this->option('timeout'));

        $spend = [];
        foreach (self::REPORTS as $column => [$adProduct, $reportTypeId]) {
            $value = $this->pullSpend($profileId, $adProduct, $reportTypeId, $date, $timeout);
            if ($value === null) {
                $this->warn("{$adProduct}: report not available, keeping the previously saved value.");

                continue;
            }
            $spend[$column] = $value;
            $this->info(sprintf('%s spend on %s: $%s', $adProduct, $date, number_format($value, 2)));
        }

        if ($spend === []) {
            $this->error('No Amazon ad spend could be pulled.');

            return self::FAILURE;
        }

        $row = AmazonTodayAdSpend::query()->firstOrNew(['spend_date' => $date]);
        $sp = $spend['sp_spend'] ?? (float) ($row->sp_spend ?? 0);
        $sb = $spend['sb_spend'] ?? (float) ($row->sb_spend ?? 0);
        $row->fill([
            'sp_spend' => round($sp, 2),
            'sb_spend' => round($sb, 2),
            'total_spend' => round($sp + $sb, 2),
            'pulled_at' => now(),
        ])->save();

        $this->info(sprintf('Saved T Spend for %s: $%s', $date, number_format($sp + $sb, 2)));

        return self::SUCCESS;
    }

    /**
     * Total campaign cost for one day, or null when the report could not be produced.
     */
    private function pullSpend(string $profileId, string $adProduct, string $reportTypeId, string $date, int $timeoutSeconds): ?float
    {
        $token = $this->accessToken();
        if ($token === null) {
            return null;
        }

        $response = Http::timeout(30)
            ->withToken($token)
            ->withHeaders([
                'Amazon-Advertising-API-Scope' => $profileId,
                'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                'Content-Type' => 'application/vnd.createasyncreportrequest.v3+json',
            ])
            ->post('https://advertising-api.amazon.com/reporting/reports', [
                'name' => "{$adProduct}_today_{$date}_spend",
                'startDate' => $date,
                'endDate' => $date,
                'configuration' => [
                    'adProduct' => $adProduct,
                    'groupBy' => ['campaign'],
                    'reportTypeId' => $reportTypeId,
                    'columns' => $this->columnsForThisRun(),
                    'format' => 'GZIP_JSON',
                    'timeUnit' => 'DAILY',
                ],
            ]);

        if ($response->status() === 425) {
            // Same request already submitted (e.g. a previous run that timed out): reuse that report.
            $this->warn("{$adProduct}: duplicate report request, reusing the existing report.");
            $reportId = trim(str_replace('The Request is a duplicate of : ', '', (string) $response->json('detail')));
        } elseif ($response->ok()) {
            $reportId = (string) $response->json('reportId');
        } else {
            $this->error("{$adProduct}: report request failed: ".$response->body());

            return null;
        }

        if ($reportId === '') {
            $this->error("{$adProduct}: no report id returned.");

            return null;
        }

        $location = $this->waitForReport($profileId, $reportId, $timeoutSeconds, $adProduct);
        if ($location === null) {
            return null;
        }

        $download = Http::timeout(60)->retry(3, 3000)->withoutVerifying()->get($location);
        if (! $download->ok()) {
            $this->error("{$adProduct}: report download failed.");

            return null;
        }

        $json = @gzdecode($download->body());
        if ($json === false || $json === '') {
            $this->error("{$adProduct}: report file could not be decoded.");

            return null;
        }

        $rows = json_decode($json, true);
        if (! is_array($rows)) {
            return null;
        }

        // One value per campaign even if Amazon repeats a row.
        $byCampaign = [];
        foreach ($rows as $r) {
            $id = (string) ($r['campaignId'] ?? '');
            if ($id === '') {
                continue;
            }
            $byCampaign[$id] = max($byCampaign[$id] ?? 0.0, (float) ($r['cost'] ?? 0));
        }

        return round(array_sum($byCampaign), 2);
    }

    /**
     * Amazon answers an identical report request with "duplicate of <old report id>" and that old
     * report holds stale numbers. The same single-day request is made every hour, so the optional
     * columns rotate per run to make each request unique while "campaignId" + "cost" stay constant.
     *
     * @return list<string>
     */
    private function columnsForThisRun(): array
    {
        $optional = ['impressions', 'clicks', 'campaignStatus', 'campaignBudgetAmount', 'campaignBudgetCurrencyCode'];
        $mask = ((int) floor(time() / 3600) % 31) + 1; // 1..31, a different subset every hour
        $columns = ['campaignId', 'campaignName', 'cost'];
        foreach ($optional as $i => $name) {
            if ($mask & (1 << $i)) {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    private function waitForReport(string $profileId, string $reportId, int $timeoutSeconds, string $label): ?string
    {
        $deadline = Carbon::now()->addSeconds($timeoutSeconds);

        while (Carbon::now()->lt($deadline)) {
            sleep(30);

            $token = $this->accessToken();
            if ($token === null) {
                continue;
            }

            $status = Http::timeout(60)
                ->retry(3, 3000)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Amazon-Advertising-API-ClientId' => config('services.amazon_ads.client_id'),
                    'Amazon-Advertising-API-Scope' => $profileId,
                    'Content-Type' => 'application/vnd.getasyncreportresponse.v3+json',
                ])
                ->get("https://advertising-api.amazon.com/reporting/reports/{$reportId}");

            if (! $status->successful()) {
                $this->warn("{$label}: report status check failed: ".$status->status());

                continue;
            }

            $state = $status['status'] ?? 'UNKNOWN';
            if ($state === 'COMPLETED') {
                return $status['location'] ?? $status['url'] ?? null;
            }
            if ($state === 'FAILED') {
                $this->error("{$label}: Amazon report generation failed.");

                return null;
            }
        }

        $this->error("{$label}: report not ready after {$timeoutSeconds} seconds.");

        return null;
    }

    private function accessToken(): ?string
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
            $this->error('Amazon Ads token fetch failed: '.$res->body());

            return null;
        }

        return (string) $res['access_token'];
    }
}
