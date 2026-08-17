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
            return self::FAILURE;
        }

        $dateRanges = $this->dateRanges();

        $this->info('Fetching orders for L30 and L60...');

        $l30 = $this->fetchOrders($token, $dateRanges['l30']);
        if (! $l30['ok']) {
            $this->error('L30 fetch failed — leaving existing ebay_orders untouched');
            return self::FAILURE;
        }

        $l60 = $this->fetchOrders($token, $dateRanges['l60']);
        if (! $l60['ok']) {
            $this->error('L60 fetch failed — leaving existing ebay_orders untouched');
            return self::FAILURE;
        }

        if (count($l30['orders']) === 0) {
            $this->error('L30 fetch returned 0 orders — leaving existing ebay_orders untouched');
            return self::FAILURE;
        }

        $this->info('Fetched '.count($l30['orders']).' L30 orders and '.count($l60['orders']).' L60 orders');

        $keepIds = [];
        $this->insertOrders($l30['orders'], 'l30', $keepIds);
        $this->insertOrders($l60['orders'], 'l60', $keepIds);

        if ($keepIds !== []) {
            $staleIds = EbayOrder::whereIn('period', ['l30', 'l60'])
                ->whereNotIn('ebay_order_id', $keepIds)
                ->pluck('id');
            if ($staleIds->isNotEmpty()) {
                EbayOrderItem::whereIn('ebay_order_id', $staleIds)->delete();
                EbayOrder::whereIn('id', $staleIds)->delete();
                $this->info('Removed '.$staleIds->count().' orders that aged out of L30/L60');
            }
        }

        $this->info('✅ eBay Orders upserted');

        return self::SUCCESS;
    }

    private function dateRanges()
    {
        // Match eBay order timestamps (same as fetch-ebay2-orders).
        // API rejects end dates in the future — L30 end is "now", not endOfDay().
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
        $id = config('services.ebay.app_id');
        $secret = config('services.ebay.cert_id');
        $rtoken = config('services.ebay.refresh_token');

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
            // pricingSummary). Mirrors the Seller Hub Total sales figure.
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

            $orderRecord = EbayOrder::updateOrCreate(
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