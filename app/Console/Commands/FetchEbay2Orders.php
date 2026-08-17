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
            return self::FAILURE;
        }

        $dateRanges = $this->dateRanges();

        $this->info('Fetching eBay 2 orders for L30 and L60...');

        $l30 = $this->fetchOrders($token, $dateRanges['l30']);
        if (! $l30['ok']) {
            $this->error('L30 fetch failed — leaving existing ebay2_orders untouched');
            return self::FAILURE;
        }

        $l60 = $this->fetchOrders($token, $dateRanges['l60']);
        if (! $l60['ok']) {
            $this->error('L60 fetch failed — leaving existing ebay2_orders untouched');
            return self::FAILURE;
        }

        if (count($l30['orders']) === 0) {
            $this->error('L30 fetch returned 0 orders — leaving existing ebay2_orders untouched');
            return self::FAILURE;
        }

        $this->info('Fetched '.count($l30['orders']).' L30 orders and '.count($l60['orders']).' L60 orders');

        $keepIds = [];
        $this->insertOrders($l30['orders'], 'l30', $keepIds);
        $this->insertOrders($l60['orders'], 'l60', $keepIds);

        if ($keepIds !== []) {
            $staleIds = Ebay2Order::whereIn('period', ['l30', 'l60'])
                ->whereNotIn('ebay_order_id', $keepIds)
                ->pluck('id');
            if ($staleIds->isNotEmpty()) {
                Ebay2OrderItem::whereIn('ebay2_order_id', $staleIds)->delete();
                Ebay2Order::whereIn('id', $staleIds)->delete();
                $this->info('Removed '.$staleIds->count().' orders that aged out of L30/L60');
            }
        }

        $this->info('✅ eBay 2 Orders upserted');

        return self::SUCCESS;
    }

    private function dateRanges()
    {
        // Aligned with app:fetch-ebay-orders (Pacific, L30 end = now, L60 end = end of day 30 ago)
        $tz = 'America/Los_Angeles';
        $today = Carbon::now($tz)->startOfDay();
        $now = Carbon::now($tz);

        return [
            'l30' => [
                'start' => $today->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy(),
            ],
            'l60' => [
                'start' => $today->copy()->subDays(60)->startOfDay(),
                'end' => $today->copy()->subDays(30)->endOfDay(),
            ],
        ];
    }

    private function getToken()
    {
        // eBay 2 credentials (separate from eBay 1)
        $id = config('services.ebay2.app_id');
        $secret = config('services.ebay2.cert_id');
        $rtoken = config('services.ebay2.refresh_token');

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

    /**
     * @return array{ok: bool, orders: array<int, array<string, mixed>>}
     */
    private function fetchOrders($token, $range): array
    {
        $orders = [];
        $from = $range['start']->copy()->utc()->format('Y-m-d\TH:i:s.000\Z');
        $to = $range['end']->copy()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $url = "https://api.ebay.com/sell/fulfillment/v1/order?filter=creationdate:[{$from}..{$to}]&limit=200";

        do {
            $r = Http::withToken($token)->timeout(60)->get($url);
            if ($r->failed()) {
                $this->error('Failed to fetch orders: '.$r->body());

                return ['ok' => false, 'orders' => $orders];
            }

            $orders = array_merge($orders, $r['orders'] ?? []);
            $url = $r['next'] ?? null;
        } while ($url);

        return ['ok' => true, 'orders' => $orders];
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     * @param  array<int, string>  $keepIds
     */
    private function insertOrders($orders, $period, array &$keepIds)
    {
        foreach ($orders as $order) {
            // "Total sales (includes taxes)" = pricingSummary.total (buyer-paid, after
            // discounts) + eBay collect-and-remit tax (reported per line item, not in
            // pricingSummary). The Fulfillment API has no top-level `total`, so compute it.
            $baseTotal = (float) ($order['pricingSummary']['total']['value'] ?? 0);
            $carTax = 0.0;
            foreach ($order['lineItems'] ?? [] as $li) {
                foreach ($li['ebayCollectAndRemitTaxes'] ?? [] as $t) {
                    $carTax += (float) ($t['amount']['value'] ?? 0);
                }
            }
            $orderTotal = round($baseTotal + $carTax, 2);

            // Insert order
            $orderId = (string) ($order['orderId'] ?? '');
            if ($orderId === '') {
                continue;
            }
            $keepIds[] = $orderId;

            $orderRecord = Ebay2Order::updateOrCreate(
                ['ebay_order_id' => $orderId],
                [
                    'order_date' => Carbon::parse($order['creationDate']),
                    'status' => $order['orderFulfillmentStatus'],
                    'total_amount' => $orderTotal,
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
