<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\EbayOrder;
use App\Models\EbayOrderItem;
use Illuminate\Support\Facades\Log;

class FetchEbayOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-ebay-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch eBay orders and insert into database';

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

        $this->info('Fetching orders for L30 and L60...');

        $l30Orders = $this->fetchOrders($token, $dateRanges['l30']);
        $l60Orders = $this->fetchOrders($token, $dateRanges['l60']);

        $this->info("Fetched " . count($l30Orders) . " L30 orders and " . count($l60Orders) . " L60 orders");

        $this->insertOrders($l30Orders, 'l30');
        $this->insertOrders($l60Orders, 'l60');

        $this->info('✅ eBay Orders inserted');
    }

    private function dateRanges()
    {
        $today = Carbon::today();

        return [
            'l30' => [
                'start' => $today->copy()->subDays(30),  // 30 days ago
                'end' => $today->copy()->subDay(),       // yesterday
            ],
            'l60' => [
                'start' => $today->copy()->subDays(60),  // 60 days ago
                'end' => $today->copy()->subDays(31),    // 31 days ago
            ],
        ];
    }

    private function getToken()
    {
        $id = env('EBAY_APP_ID');
        $secret = env('EBAY_CERT_ID');
        $rtoken = env('EBAY_REFRESH_TOKEN');

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
            Log::channel('daily')->error('EBAY TOKEN EXCEPTION', [
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
            $orderRecord = EbayOrder::updateOrCreate(
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
                EbayOrderItem::updateOrCreate(
                    ['ebay_order_id' => $orderRecord->id, 'item_id' => $item['legacyItemId']],
                    [
                        'sku' => $item['sku'] ?? null,
                        'quantity' => $item['quantity'],
                        'price' => $item['lineItemCost']['value'] ?? 0,
                        'currency' => $item['lineItemCost']['currency'] ?? 'USD',
                        'raw_data' => json_encode($item),
                    ]
                );
            }
        }
    }
}