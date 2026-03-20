<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\ReverbDailyData;
use Carbon\Carbon;

class FetchReverbDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:daily {--days=60 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Reverb orders raw data and store daily sales';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $days = (int) $this->option('days');
        
        $this->info("Fetching Reverb Daily Orders Data (Last {$days} days)...");
        
        // Use California timezone for date calculations
        $cutoffDate = Carbon::now('America/Los_Angeles')->subDays($days)->startOfDay();
        $californiaToday = Carbon::now('America/Los_Angeles')->format('Y-m-d H:i:s T');
        
        $this->info("California current time: {$californiaToday}");
        $this->info("Cutoff date: {$cutoffDate->toDateString()}");
        
        $this->fetchAndStoreOrders($cutoffDate);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Reverb daily data fetched and stored successfully in {$duration} seconds.");
    }

    /**
     * Fetch all orders from Reverb API and store raw data
     */
    protected function fetchAndStoreOrders(Carbon $cutoffDate): void
    {
        $url = 'https://api.reverb.com/api/my/orders/selling/all';
        $pageCount = 0;
        $totalOrders = 0;
        $insertedOrders = 0;
        $bulkOrders = [];
        $reachedCutoff = false;

        do {
            $pageCount++;
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . config('services.reverb.token'),
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch orders on page ' . $pageCount . ': ' . $response->body());
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderData = $this->parseOrderData($order);
                
                if (!$orderData) continue;
                
                // Check if order is older than cutoff date
                $orderDate = Carbon::parse($orderData['order_date']);
                if ($orderDate->lt($cutoffDate)) {
                    $reachedCutoff = true;
                    $this->info("Reached cutoff date at page {$pageCount}");
                    break;
                }

                $bulkOrders[] = $orderData;
                $insertedOrders++;
            }

            // Bulk insert in chunks of 100
            if (count($bulkOrders) >= 100) {
                $this->bulkUpsertOrders($bulkOrders);
                $bulkOrders = [];
            }

            $url = $data['_links']['next']['href'] ?? null;
            $this->info("  Processed page {$pageCount} ({$totalOrders} orders fetched, {$insertedOrders} within date range)...");

        } while ($url && !$reachedCutoff);

        // Insert remaining orders
        if (!empty($bulkOrders)) {
            $this->bulkUpsertOrders($bulkOrders);
        }

        $this->info("Fetched {$totalOrders} total orders, stored {$insertedOrders} orders within date range from {$pageCount} pages.");
    }

    /**
     * Parse order data from API response
     */
    protected function parseOrderData(array $order): ?array
    {
        $paidAt = $order['paid_at'] ?? null;
        $createdAt = $order['created_at'] ?? null;
        $shippedAt = $order['shipped_at'] ?? null;
        
        $orderDate = $paidAt ?? $createdAt;
        if (!$orderDate) return null;

        // Calculate period based on order date (using California timezone)
        $orderDateCarbon = Carbon::parse($orderDate, 'America/Los_Angeles');
        $today = Carbon::now('America/Los_Angeles')->startOfDay();
        $daysDiff = $today->diffInDays($orderDateCarbon);
        $period = $daysDiff <= 30 ? 'l30' : 'l60';

        // Get shipping address info
        $shippingAddress = $order['shipping_address'] ?? [];
        
        // Get price amounts
        $amountProduct = $order['amount_product'] ?? [];
        $unitPrice = isset($amountProduct['amount']) ? (float) $amountProduct['amount'] : null;
        
        $amountProductSubtotal = $order['amount_product_subtotal'] ?? [];
        $productSubtotal = isset($amountProductSubtotal['amount']) ? (float) $amountProductSubtotal['amount'] : null;
        
        $total = $order['total'] ?? [];
        $amount = isset($total['amount']) ? (float) $total['amount'] : null;
        
        $shipping = $order['shipping'] ?? [];
        $shippingAmount = isset($shipping['amount']) ? (float) $shipping['amount'] : null;
        
        $amountTax = $order['amount_tax'] ?? [];
        $taxAmount = isset($amountTax['amount']) ? (float) $amountTax['amount'] : null;
        
        $taxRate = $order['tax_rate'] ?? null;
        
        // Get fee amounts
        $sellingFee = $order['selling_fee'] ?? [];
        $sellingFeeAmount = isset($sellingFee['amount']) ? (float) $sellingFee['amount'] : null;
        
        $bumpFee = $order['bump_fee'] ?? [];
        $bumpFeeAmount = isset($bumpFee['amount']) ? (float) $bumpFee['amount'] : null;
        
        $directCheckoutFee = $order['direct_checkout_fee'] ?? [];
        $directCheckoutFeeAmount = isset($directCheckoutFee['amount']) ? (float) $directCheckoutFee['amount'] : null;
        
        $directCheckoutPayout = $order['direct_checkout_payout'] ?? [];
        $payoutAmount = isset($directCheckoutPayout['amount']) ? (float) $directCheckoutPayout['amount'] : null;

        return [
            'order_number' => $order['order_number'] ?? null,
            'order_date' => Carbon::parse($orderDate)->toDateString(),
            'period' => $period,
            'status' => $order['status'] ?? null,
            'sku' => $order['sku'] ?? null,
            'display_sku' => $order['listing']['sku'] ?? $order['title'] ?? null,
            'title' => $order['title'] ?? null,
            'quantity' => $order['quantity'] ?? 1,
            
            // Price fields
            'unit_price' => $unitPrice,
            'product_subtotal' => $productSubtotal,
            'amount' => $amount,
            'shipping_amount' => $shippingAmount,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            
            // Fee fields
            'selling_fee' => $sellingFeeAmount,
            'bump_fee' => $bumpFeeAmount,
            'direct_checkout_fee' => $directCheckoutFeeAmount,
            'payout_amount' => $payoutAmount,
            
            // Buyer info
            'buyer_id' => $order['buyer_id'] ?? null,
            'buyer_name' => $order['buyer_name'] ?? $shippingAddress['name'] ?? null,
            'buyer_email' => $order['buyer_email'] ?? null,
            
            // Shipping address
            'shipping_address' => $shippingAddress['street_address'] ?? null,
            'shipping_city' => $shippingAddress['locality'] ?? null,
            'shipping_state' => $shippingAddress['region'] ?? null,
            'shipping_country' => $shippingAddress['country_code'] ?? null,
            'shipping_postal_code' => $shippingAddress['postal_code'] ?? null,
            'buyer_phone' => $shippingAddress['phone'] ?? null,
            
            // Order details
            'payment_method' => $order['payment_method'] ?? null,
            'order_type' => $order['order_type'] ?? null,
            'order_source' => $order['order_source'] ?? null,
            'shipping_method' => $order['shipping_method'] ?? null,
            'shipment_status' => $order['shipment_status'] ?? null,
            'order_bundle_id' => $order['order_bundle_id'] ?? null,
            'product_id' => $order['product_id'] ?? null,
            'remaining_inventory' => $order['remaining_listing_inventory'] ?? null,
            'local_pickup' => $order['local_pickup'] ?? false,
            
            // Timestamps
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : null,
            'shipped_at' => $shippedAt ? Carbon::parse($shippedAt) : null,
            'created_at_api' => $createdAt ? Carbon::parse($createdAt) : null,
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * Bulk upsert orders using database transaction
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            DB::transaction(function () use ($orders) {
                foreach ($orders as $order) {
                    DB::table('reverb_daily_data')
                        ->updateOrInsert(
                            ['order_number' => $order['order_number']],
                            $order
                        );
                }
            });
        } catch (\Exception $e) {
            $this->error('Error bulk upserting orders: ' . $e->getMessage());
            // Fallback to individual inserts
            foreach ($orders as $order) {
                try {
                    ReverbDailyData::updateOrCreate(
                        ['order_number' => $order['order_number']],
                        $order
                    );
                } catch (\Exception $e) {
                    $this->warn('Failed to insert order ' . ($order['order_number'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }
}
