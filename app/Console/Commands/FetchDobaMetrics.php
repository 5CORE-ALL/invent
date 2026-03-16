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
    protected $description = 'Fetch Doba metrics including prices, L30/L60/L7/L7Prev quantities and order counts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Fetching Doba Metrics from API...");
        
        // First fetch prices from API
        $this->fetchPricesFromApi();
        
        // Then calculate L30/L60/L7/L7Prev quantities from daily data
        $this->getQuantity();
        
        $this->info("Doba metrics updated successfully!");
        return;
    }

    /**
     * Fetch prices and item IDs from Doba API
     */
    private function fetchPricesFromApi()
    {
        $this->info("Fetching Doba prices from API...");

        $page = 1;
        $totalUpdated = 0;

        do {
            $timestamp = $this->getMillisecond();
            $getContent = $this->getContent($timestamp);
            $sign = $this->generateSignature($getContent);
            
            $response = Http::withoutVerifying()->withHeaders([
                'appKey' => config('services.doba.app_key'),
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

            $responseData = $response->json();
            $data = $responseData['businessData']['data']['dsGoodsDetailResultVOS'] ?? [];
            
            if (empty($data)) {
                $this->info("No more data at page {$page}");
                break;
            }
            
            foreach ($data as $product) {
                foreach ($product['skus'] as $sku) {
                    $item = $sku['stocks'][0] ?? null;

                    if (!$item) continue;

                    // Handle empty strings for numeric fields
                    $selfPickPrice = !empty($item['selfPickAnticipatedIncome']) ? $item['selfPickAnticipatedIncome'] : null;
                    $msrp = !empty($sku['msrp']) ? $sku['msrp'] : null;
                    $map = !empty($sku['map']) ? $sku['map'] : null;

                    DobaMetric::updateOrCreate(
                        ['sku' => $sku['skuCode']],
                        [
                            'item_id' => $item['itemNo'],
                            'anticipated_income' => $item['anticipatedIncome'],
                            'self_pick_price' => $selfPickPrice,
                            'msrp' => $msrp,
                            'map' => $map,
                        ]
                    );
                    $totalUpdated++;
                }
            }
            
            $this->info("Page {$page}: Processed " . count($data) . " products");
            $page++;
        } while (count($data) === 100);

        $this->info("Updated prices for {$totalUpdated} SKUs from API");
    }

    private function getQuantity()
    {
        $this->info("Calculating Doba L30/L60/L7/L7Prev from doba_daily_data..."); 
        
        // Calculate date ranges for L7 and L7 Prev
        $yesterday = Carbon::yesterday();
        
        // L7: Last 7 days (yesterday back to 6 days ago)
        $l7_end = $yesterday->copy()->endOfDay();
        $l7_start = $yesterday->copy()->subDays(6)->startOfDay();
        
        // L7 Prev: Previous 7 days (8 days ago back to 14 days ago)
        $l7prev_end = $yesterday->copy()->subDays(7)->endOfDay();
        $l7prev_start = $yesterday->copy()->subDays(13)->startOfDay();
        
        $this->info("L7: {$l7_start->format('Y-m-d')} to {$l7_end->format('Y-m-d')}");
        $this->info("L7 Prev: {$l7prev_start->format('Y-m-d')} to {$l7prev_end->format('Y-m-d')}");
        
        // Aggregate L30 data from doba_daily_data using period column (set by doba:daily command)
        $l30Data = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->whereRaw("LOWER(period) = 'l30'")
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        // Aggregate L60 data from doba_daily_data using period column
        $l60Data = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->whereRaw("LOWER(period) = 'l60'")
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        // Aggregate L7 data from doba_daily_data using date range
        $l7Data = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->whereBetween('order_time', [$l7_start, $l7_end])
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        // Aggregate L7 Prev data from doba_daily_data using date range
        $l7prevData = DB::table('doba_daily_data')
            ->select('sku', 
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('COUNT(DISTINCT order_no) as order_count'))
            ->whereNotNull('sku')
            ->whereBetween('order_time', [$l7prev_start, $l7prev_end])
            ->whereNotIn('order_status', ['Cancelled', 'Canceled', 'cancelled', 'canceled', 'CANCELLED', 'CANCELED'])
            ->groupBy('sku')
            ->get()
            ->keyBy('sku');
        
        $this->info("Found L30 data for " . $l30Data->count() . " SKUs");
        $this->info("Found L60 data for " . $l60Data->count() . " SKUs");
        $this->info("Found L7 data for " . $l7Data->count() . " SKUs");
        $this->info("Found L7 Prev data for " . $l7prevData->count() . " SKUs");
        
        // Get all unique SKUs from all periods
        $allSkus = $l30Data->keys()
            ->merge($l60Data->keys())
            ->merge($l7Data->keys())
            ->merge($l7prevData->keys())
            ->unique();
        
        // Update doba_metrics with aggregated quantities and order counts
        foreach ($allSkus as $sku) {
            $l30Qty = $l30Data->get($sku)?->total_quantity ?? 0;
            $l60Qty = $l60Data->get($sku)?->total_quantity ?? 0;
            $l30Count = $l30Data->get($sku)?->order_count ?? 0;
            $l60Count = $l60Data->get($sku)?->order_count ?? 0;
            $l7Qty = $l7Data->get($sku)?->total_quantity ?? 0;
            $l7prevQty = $l7prevData->get($sku)?->total_quantity ?? 0;
            $l7Count = $l7Data->get($sku)?->order_count ?? 0;
            $l7prevCount = $l7prevData->get($sku)?->order_count ?? 0;
            
            DobaMetric::updateOrCreate(
                ['sku' => $sku],
                [
                    'quantity_l30' => (int) $l30Qty,
                    'quantity_l60' => (int) $l60Qty,
                    'order_count_l30' => (int) $l30Count,
                    'order_count_l60' => (int) $l60Count,
                    'quantity_l7' => (int) $l7Qty,
                    'quantity_l7_prev' => (int) $l7prevQty,
                    'order_count_l7' => (int) $l7Count,
                    'order_count_l7_prev' => (int) $l7prevCount,
                    // Don't overwrite anticipated_income - it comes from the API in fetchPricesFromApi()
                ]
            );
        }
        
        $this->info("Updated doba_metrics for {$allSkus->count()} SKUs");
    }

    private function generateSignature($content)
    {
        $privateKeyFormatted = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap( config('services.doba.private_key'), 64, "\n", true) .
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
        $appKey = config('services.doba.app_key');
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
