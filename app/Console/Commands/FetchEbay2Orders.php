<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\Ebay2Order;
use App\Models\Ebay2OrderItem;
use Illuminate\Support\Facades\Log;

class FetchEbay2Orders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-ebay2-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch eBay 2 orders and insert into database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = $this->getToken();
        if (! $token) {
            $this->error('Token error');
            return;
        }

        $dateRanges = $this->dateRanges();

        $this->info('Fetching eBay 2 orders for L30 and L60...');

        $l30Orders = $this->fetchOrders($token, $dateRanges['l30']);
        $l60Orders = $this->fetchOrders($token, $dateRanges['l60']);

        $this->info("Fetched " . count($l30Orders) . " L30 orders and " . count($l60Orders) . " L60 orders");

        // Delete old period data before inserting new
        $deletedL30 = Ebay2Order::where('period', 'l30')->delete();
        $this->info("🗑️  Deleted {$deletedL30} old L30 orders");
        
        $deletedL60 = Ebay2Order::where('period', 'l60')->delete();
        $this->info("🗑️  Deleted {$deletedL60} old L60 orders");

        $this->insertOrders($l30Orders, 'l30');
        $this->insertOrders($l60Orders, 'l60');

        $this->info('✅ eBay 2 Orders inserted');
    }

    private function dateRanges()
    {
        $today = Carbon::today();

        return [
            'l30' => [
                'start' => $today->copy()->subDays(29),
                'end' => $today->copy()->subDay(),
            ],
            'l60' => [
                'start' => $today->copy()->subDays(59),
                'end' => $today->copy()->subDays(30),
            ],
        ];
    }

    private function getToken()
    {
        // eBay 2 credentials (separate from eBay 1)
        $id = env('EBAY2_APP_ID');
        $secret = env('EBAY2_CERT_ID');
        $rtoken = env('EBAY2_REFRESH_TOKEN');

        // If EBAY2 credentials not set, show error
        if (!$id || !$secret || !$rtoken) {
            $this->error('❌ eBay 2 credentials not configured. Please set EBAY2_APP_ID, EBAY2_CERT_ID, EBAY2_REFRESH_TOKEN in .env');
            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($id, $secret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $rtoken,
                ]);

            if (! $response->successful()) {
                $this->error('❌ TOKEN FAILED: '.json_encode($response->json()));
                return null;
            }

            return $response->json()['access_token'] ?? null;

        } catch (\Throwable $e) {
            Log::channel('daily')->error('EBAY2 TOKEN EXCEPTION', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchOrders($token, $range)
    {
        $orders = [];
        $from = $range['start']->format('Y-m-d\TH:i:s.000\Z');
        $to = $range['end']->format('Y-m-d\TH:i:s.000\Z');

        $url = "https://api.ebay.com/sell/fulfillment/v1/order?filter=creationdate:[{$from}..{$to}]&limit=200";

        do {
            $r = Http::withToken($token)->get($url);
            if ($r->failed()) {
                $this->error('Failed to fetch orders: ' . $r->body());
                break;
            }

            $orders = array_merge($orders, $r['orders'] ?? []);
            $url = $r['next'] ?? null;
        } while ($url);

        return $orders;
    }

    private function insertOrders($orders, $period)
    {
        foreach ($orders as $order) {
            // Insert order
            $orderRecord = Ebay2Order::updateOrCreate(
                ['ebay_order_id' => $order['orderId']],
                [
                    'order_date' => Carbon::parse($order['creationDate']),
                    'status' => $order['orderFulfillmentStatus'],
                    'total_amount' => $order['total'] ?? 0,
                    'currency' => $order['totalCurrency'] ?? 'USD',
                    'period' => $period,
                    'raw_data' => json_encode($order),
                ]
            );

            // Insert order items
            foreach ($order['lineItems'] ?? [] as $item) {
                Ebay2OrderItem::updateOrCreate(
                    ['ebay2_order_id' => $orderRecord->id, 'item_id' => $item['legacyItemId']],
                    [
                        'sku' => $item['sku'] ?? null,
                        'quantity' => $item['quantity'],
                        'price' => $item['lineItemCost']['value'] ?? 0,
                        'title' => $item['title'] ?? null,
                        'raw_data' => json_encode($item),
                    ]
                );
            }
        }
    }
}
