<?php

namespace App\Console\Commands;

use App\Models\DobaMetric;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchDobaMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-doba-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Calculating Doba Metrics from doba_daily_data...");
        
        // Calculate L30/L60 quantities from daily data
        $this->getQuantity();
        
        $this->info("Doba metrics updated successfully!");
        return;

        // Skip API call for now due to IP whitelist
        /*
        $this->info("Fetching Doba Metrics...");

        $page = 1;

        do {
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withHeaders([
                'appKey' => env('DOBA_APP_KEY'),
                'signType' => 'rsa2',
                'timestamp' => $timestamp,
                'sign' => $sign,
                'Content-Type' => 'application/json',
            ])->get('https://openapi.doba.com/api/goods/detail', [
                'pageNumber' => $page,
                'pageSize' => 100
            ]);
        
            if (!$response->ok()) {
                $this->error("API Failed: " . $response->body());
                return;
            }

            $data = $response['businessData']['data']['dsGoodsDetailResultVOS'];
            if (empty($data)) break;
            foreach ($data as $product) {
                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;

                    if (!$item) continue;

                    DobaMetric::updateOrCreate(
                        ['sku' => $sku['skuCode']],
                        [
                            'item_id' => $item['itemNo'],
                            'anticipated_income' => $item['anticipatedIncome'],
                        ]
                    );
                }
            }
            $page++;
        } while (count($data) === 100);

        $this->getQuantity();
        $this->info("Done.");
        */
    }

    private function getQuantity()
    {
        $this->info("Calculating Doba L30/L60 from doba_daily_data..."); 
        
        $today = Carbon::today();
        $l30Start = $today->copy()->subDays(30);
        $l60Start = $today->copy()->subDays(60);
        
        $this->info("L30 range: {$l30Start->toDateString()} to {$today->toDateString()}");
        $this->info("L60 range: {$l60Start->toDateString()} to {$l30Start->copy()->subDay()->toDateString()}");
        
        // Aggregate L30 data from doba_daily_data
        $l30Data = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->where('order_time', '>=', $l30Start)
            ->where('order_time', '<=', $today)
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        // Aggregate L60 data from doba_daily_data
        $l60Data = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->where('order_time', '>=', $l60Start)
            ->where('order_time', '<', $l30Start)
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        $this->info("Found L30 data for " . $l30Data->count() . " SKUs");
        $this->info("Found L60 data for " . $l60Data->count() . " SKUs");
        
        // Get all unique SKUs from both periods
        $allSkus = $l30Data->keys()->merge($l60Data->keys())->unique();
        
        // Update doba_metrics with aggregated quantities and order counts
        foreach ($allSkus as $sku) {
            $l30Qty = $l30Data->get($sku)->total_quantity ?? 0;
            $l60Qty = $l60Data->get($sku)->total_quantity ?? 0;
            $l30Count = $l30Data->get($sku)->order_count ?? 0;
            $l60Count = $l60Data->get($sku)->order_count ?? 0;
            
            DobaMetric::updateOrCreate(
                ['sku' => $sku],
                [
                    'quantity_l30' => (int) $l30Qty,
                    'quantity_l60' => (int) $l60Qty,
                    'order_count_l30' => (int) $l30Count,
                    'order_count_l60' => (int) $l60Count,
                ]
            );
        }
        
        $this->info("Updated doba_metrics for {$allSkus->count()} SKUs");
    }

    private function generateSignature($content)
    {
        $privateKeyFormatted = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap( env('DOBA_PRIVATE_KEY'), 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $private_key = openssl_pkey_get_private($privateKeyFormatted);
		if (!$private_key) {
			throw new Exception("Invalid private key.");
		}
        openssl_sign($content, $signature, $private_key, OPENSSL_ALGO_SHA256);
        
		$sign = base64_encode($signature); 
        return $sign;
    }

    private function getContent($timestamp)
    {
        $appKey = env('DOBA_APP_KEY');
		$contentForSign = "appKey={$appKey}&signType=rsa2&timestamp={$timestamp}";
		return $contentForSign;
    }

    private function getMillisecond()
    {
		list($s1, $s2) = explode(' ', microtime());
        return intval((float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000));
    }

    private function getDateRanges(): array
    {
        // Yesterday (e.g., if today is June 30 → yesterday is June 29)
        $yesterday = Carbon::yesterday();

        // L30: Last 30 days (from 30 days before yesterday → to yesterday)
        $l30_end = $yesterday->copy();                   // June 29
        $l30_start = $l30_end->copy()->subDays(29);      // May 31

        // L60: Month before L30 (30 days before L30 start → day before L30 start)
        $l60_end = $l30_start->copy()->subDay();         // May 30
        $l60_start = $l60_end->copy()->subDays(29);      // May 1

        return [
            'l30' => [
                'begin' => $l30_start->format('Y-m-d\TH:i:sP'), // e.g., 2025-05-31T00:00:00+05:30
                'end'   => $l30_end->format('Y-m-d\TH:i:sP'),   // e.g., 2025-06-29T00:00:00+05:30
            ],
            'l60' => [
                'begin' => $l60_start->format('Y-m-d\TH:i:sP'), // e.g., 2025-05-01T00:00:00+05:30
                'end'   => $l60_end->format('Y-m-d\TH:i:sP'),   // e.g., 2025-05-30T00:00:00+05:30
            ],
        ];
    }

}
