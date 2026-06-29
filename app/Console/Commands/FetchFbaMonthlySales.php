<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\FbaTable;
use App\Models\FbaPrice;
use App\Models\FbaMonthlySale;

class FetchFbaMonthlySales extends Command
{
    protected $signature = 'app:fetch-fba-monthly-sales
        {--year= : Year to fetch (default current year)}
        {--source=both : source of SKUs: inventory|prices|both}
        {--sku= : Fetch a single SKU only (for testing or one-off refresh)}
        {--debug : show debug info}';

    protected $description = 'Fetch monthly sales (Jan-Dec) per SKU and insert/update fba_monthly_sales';

    public function handle()
    {
        $this->info('📈 Starting monthly sales fetch...');

        $year = intval($this->option('year') ?: date('Y'));
        $source = $this->option('source') ?: 'both';

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            $this->error('❌ Could not obtain access token.');
            return 1;
        }

        $endpoint = config('services.amazon_sp.endpoint');
        $marketplace = config('services.amazon_sp.marketplace_id');

        // Collect SKUs
        $singleSku = trim((string) $this->option('sku'));
        if ($singleSku !== '') {
            $skus = [$singleSku];
            $this->info("ℹ️ Single-SKU mode: {$singleSku}");
        } else {
            $skus = collect();

            if (in_array($source, ['inventory','both'])) {
                $skus = $skus->merge(FbaTable::pluck('seller_sku')->filter()->unique());
            }

            if (in_array($source, ['prices','both'])) {
                $skus = $skus->merge(FbaPrice::pluck('seller_sku')->filter()->unique());
            }

            $skus = $skus->unique()->values()->all();
        }

        if (empty($skus)) {
            $this->warn('⚠️ No SKUs found to fetch.');
            return 0;
        }

        $this->info("ℹ️ SKUs to process: " . count($skus));

        // interval: full year in ISO with timezone offset (use -07:00 like your example; adjust if needed)
        $start = "{$year}-01-01T00:00:00-07:00";
        $end = "{$year}-12-31T23:59:59-07:00";
        $interval = "{$start}--{$end}";

        // L30 interval: last 30 days
        $start30 = date('Y-m-d\TH:i:s-07:00', strtotime('-30 days'));
        $end30 = date('Y-m-d\TH:i:s-07:00');
        $interval30 = "{$start30}--{$end30}";

        // L60 interval: last 60 days
        $start60 = date('Y-m-d\TH:i:s-07:00', strtotime('-60 days'));
        $end60 = date('Y-m-d\TH:i:s-07:00');
        $interval60 = "{$start60}--{$end60}";

        // Iterate SKUs
        foreach ($skus as $sku) {
            try {
                $encodedSku = rawurlencode($sku);
                $url = "{$endpoint}/sales/v1/orderMetrics?marketplaceIds={$marketplace}&interval={$interval}&granularity=Month&sku={$encodedSku}";

                $res = Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->get($url);

                // Log the full API response for debugging
                Log::info('API Response for SKU ' . $sku . ': ' . json_encode($res->json()));

                if ($res->failed()) {
                    $this->warn("⚠️ API failed for SKU {$sku}: " . $res->status());
                    if ($this->option('debug')) $this->line($res->body());
                    continue;
                }

                $payload = $res->json()['payload'] ?? null;
                if (!$payload || !is_array($payload) || count($payload) === 0) {
                    $this->warn("ℹ️ No metrics returned for SKU {$sku} (may be zero or restricted)");
                    $rolling = $this->fetchRollingMetrics($accessToken, $endpoint, $marketplace, $encodedSku, $interval30, $interval60);
                    FbaMonthlySale::updateOrCreate(
                        ['seller_sku' => $sku, 'year' => $year],
                        array_merge(['total_units' => 0], $rolling)
                    );
                    $this->syncRollingMetricsToAllYears($sku, $rolling);
                    continue;
                }

                $months = array_fill(1, 12, 0);
                $totalUnits = 0;
                $totalRevenue = 0.0; // will be used for weighted avg

                foreach ($payload as $period) {
                    // defensive parsing: period may include 'interval' and metrics keys
                    $intervalStr = $period['interval'] ?? null;
                    $unitCount = intval($period['unitCount'] ?? 0);

                    $orderedProductSales = $this->periodSalesAmount($period);

                    // some APIs include SKU+ASIN in the payload; try to capture asin:
                    $asin = $period['asin'] ?? null;

                    if ($intervalStr) {
                        
                        $parts = explode('--', $intervalStr);
                        $startPart = $parts[0] ?? null;
                        if ($startPart) {
                            $monthNum = intval(date('n', strtotime($startPart))); // 1..12
                            if ($monthNum >=1 && $monthNum <= 12) {
                                $months[$monthNum] += $unitCount;
                                $totalUnits += $unitCount;
                                $totalRevenue += $orderedProductSales;
                            }
                        }
                    } else {
                        // fallback: if there's a 'month' field
                        if (!empty($period['month'])) {
                            $m = intval($period['month']);
                            if ($m >=1 && $m <=12) {
                                $months[$m] += $unitCount;
                                $totalUnits += $unitCount;
                                $totalRevenue += $orderedProductSales;
                            }
                        }
                    }
                }

                // compute average price (weighted)
                $avgPrice = null;
                if ($totalUnits > 0 && $totalRevenue > 0) {
                    $avgPrice = round($totalRevenue / $totalUnits, 2);
                } else {
                    // fallback: use current price from fba_prices table if available
                    $price = \App\Models\FbaPrice::where('seller_sku', $sku)->value('price');
                    $avgPrice = $price ? floatval($price) : null;
                }

                $rolling = $this->fetchRollingMetrics($accessToken, $endpoint, $marketplace, $encodedSku, $interval30, $interval60);
                $l30Units = $rolling['l30_units'];
                $l30Revenue = $rolling['l30_revenue'];
                $l60Units = $rolling['l60_units'];
                $l60Revenue = $rolling['l60_revenue'];

                // map to keys jan..dec
                $dataToSave = [
                    'asin' => $asin,
                    'year' => $year,
                    'jan' => $months[1] ?? 0,
                    'feb' => $months[2] ?? 0,
                    'mar' => $months[3] ?? 0,
                    'apr' => $months[4] ?? 0,
                    'may' => $months[5] ?? 0,
                    'jun' => $months[6] ?? 0,
                    'jul' => $months[7] ?? 0,
                    'aug' => $months[8] ?? 0,
                    'sep' => $months[9] ?? 0,
                    'oct' => $months[10] ?? 0,
                    'nov' => $months[11] ?? 0,
                    'dec' => $months[12] ?? 0,
                    'total_units' => $totalUnits,
                    'avg_price' => $avgPrice,
                    'l30_units' => $l30Units,
                    'l30_revenue' => $l30Revenue,
                    'l60_units' => $l60Units,
                    'l60_revenue' => $l60Revenue
                ];

                FbaMonthlySale::updateOrCreate(
                    ['seller_sku' => $sku, 'year' => $year],
                    $dataToSave
                );
                $this->syncRollingMetricsToAllYears($sku, $rolling);

                $this->info("✅ Saved monthly metrics for {$sku} (year: {$year}, units: {$totalUnits}, L30: {$l30Units})");

            } catch (\Throwable $e) {
                Log::error("Error for SKU {$sku}: " . $e->getMessage());
                $this->error("❌ Error processing SKU {$sku}");
            }

            // small delay to avoid throttling
            sleep(1);
        }

        $this->info('🏁 Done fetching monthly sales.');
        return 0;
    }

    private function periodSalesAmount(array $period): float
    {
        foreach (['orderedProductSales', 'totalSales'] as $key) {
            if (!empty($period[$key]['amount'])) {
                return floatval($period[$key]['amount']);
            }
            if (!empty($period[$key]) && is_numeric($period[$key])) {
                return floatval($period[$key]);
            }
        }

        return 0.0;
    }

    private function fetchRollingMetrics(
        string $accessToken,
        string $endpoint,
        string $marketplace,
        string $encodedSku,
        string $interval30,
        string $interval60
    ): array {
        $l30Units = 0;
        $l30Revenue = 0.0;
        $l60Units = 0;
        $l60Revenue = 0.0;

        try {
            $url30 = "{$endpoint}/sales/v1/orderMetrics?marketplaceIds={$marketplace}&interval={$interval30}&granularity=Total&sku={$encodedSku}";
            $res30 = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url30);
            if ($res30->successful()) {
                $payload30 = $res30->json()['payload'] ?? [];
                if (!empty($payload30) && is_array($payload30)) {
                    $l30Units = intval($payload30[0]['unitCount'] ?? 0);
                    $l30Revenue = $this->periodSalesAmount($payload30[0]);
                }
            }
            sleep(1);
        } catch (\Throwable $e) {
            Log::error("L30 fetch error for SKU {$encodedSku}: " . $e->getMessage());
        }

        try {
            $url60 = "{$endpoint}/sales/v1/orderMetrics?marketplaceIds={$marketplace}&interval={$interval60}&granularity=Total&sku={$encodedSku}";
            $res60 = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get($url60);
            if ($res60->successful()) {
                $payload60 = $res60->json()['payload'] ?? [];
                if (!empty($payload60) && is_array($payload60)) {
                    $l60Units = intval($payload60[0]['unitCount'] ?? 0);
                    $l60Revenue = $this->periodSalesAmount($payload60[0]);
                }
            }
            sleep(1);
        } catch (\Throwable $e) {
            Log::error("L60 fetch error for SKU {$encodedSku}: " . $e->getMessage());
        }

        return [
            'l30_units' => $l30Units,
            'l30_revenue' => $l30Revenue,
            'l60_units' => $l60Units,
            'l60_revenue' => $l60Revenue,
        ];
    }

    private function syncRollingMetricsToAllYears(string $sku, array $rolling): void
    {
        FbaMonthlySale::where('seller_sku', $sku)->update($rolling);
    }

    private function getAccessToken()
    {
        try {
            $res = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => config('services.amazon_sp.refresh_token'),
                'client_id' => config('services.amazon_sp.client_id'),
                'client_secret' => config('services.amazon_sp.client_secret'),
            ]);

            if ($res->failed()) {
                Log::error('Access token request failed', $res->json());
                return null;
            }

            return $res->json()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Access token error: ' . $e->getMessage());
            return null;
        }
    }
}
