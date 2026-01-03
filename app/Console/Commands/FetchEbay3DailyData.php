<?php

namespace App\Console\Commands;

use App\Models\Ebay3DailyData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchEbay3DailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ebay3:daily {--days=30 : Number of days to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store raw order data from eBay 3 account';

    protected $baseUrl = 'https://api.ebay.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $startTime = microtime(true);

        $this->info("Fetching eBay 3 Daily Orders Data (Last {$days} days)...");

        // Get access token
        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('Failed to get access token');
            return 1;
        }

        $this->info('Access token received. Fetching orders...');

        // Calculate date boundaries
        $now = Carbon::now();
        $cutoffDate = $now->copy()->subDays($days);
        $l30Start = $now->copy()->subDays(30);

        // Fetch and store orders
        $this->fetchAndStoreOrders($token, $cutoffDate, $l30Start);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("eBay 3 daily data fetched and stored successfully in {$elapsed} seconds.");

        return 0;
    }

    /**
     * Get OAuth access token
     */
    protected function getAccessToken(): ?string
    {
        $clientId = env('EBAY_3_APP_ID');
        $clientSecret = env('EBAY_3_CERT_ID');
        $refreshToken = env('EBAY_3_REFRESH_TOKEN');

        if (!$clientId || !$clientSecret || !$refreshToken) {
            $this->error('eBay 3 credentials missing in .env');
            return null;
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(30)
            ->connectTimeout(15)
            ->retry(2, 1000)
            ->post($this->baseUrl . '/identity/v1/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('Failed to get eBay 3 access token: ' . $response->body());
        $this->error('Token error: ' . $response->body());
        return null;
    }

    /**
     * Fetch all orders and store raw data
     */
    protected function fetchAndStoreOrders(string $token, Carbon $cutoffDate, Carbon $l30Start): void
    {
        $totalOrders = 0;
        $totalLines = 0;
        $bulkData = [];
        $offset = 0;
        $limit = 50;

        // eBay uses ISO 8601 format for dates (without timezone offset in filter)
        $startDate = $cutoffDate->format('Y-m-d\TH:i:s.000\Z');

        do {
            $this->info("  Fetching orders (offset: {$offset})...");

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get($this->baseUrl . '/sell/fulfillment/v1/order', [
                    'filter' => "creationdate:[{$startDate}..]",
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if (!$response->successful()) {
                $this->error('Order fetch failed: ' . $response->body());
                Log::error('eBay 3 order fetch failed', ['response' => $response->body()]);
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $totalCount = $data['total'] ?? 0;

            if (empty($orders)) {
                $this->info("  No more orders found.");
                break;
            }

            $totalOrders += count($orders);

            foreach ($orders as $order) {
                $orderData = $this->parseOrderData($order, $l30Start);
                if (!empty($orderData)) {
                    $bulkData = array_merge($bulkData, $orderData);
                    $totalLines += count($orderData);
                }
            }

            // Bulk insert in chunks of 100
            if (count($bulkData) >= 100) {
                $this->bulkUpsertOrders($bulkData);
                $bulkData = [];
            }

            $this->info("  Processed {$totalOrders} orders ({$totalLines} line items)...");

            $offset += $limit;

        } while ($offset < $totalCount);

        // Insert remaining orders
        if (!empty($bulkData)) {
            $this->bulkUpsertOrders($bulkData);
        }

        $this->info("Fetched {$totalOrders} total orders, stored {$totalLines} line items.");
    }

    /**
     * Parse order data into flat array for each line item
     */
    protected function parseOrderData(array $order, Carbon $l30Start): array
    {
        $lines = [];

        $orderId = $order['orderId'] ?? null;
        $legacyOrderId = $order['legacyOrderId'] ?? null;
        $creationDate = isset($order['creationDate']) ? Carbon::parse($order['creationDate']) : null;
        $lastModifiedDate = isset($order['lastModifiedDate']) ? Carbon::parse($order['lastModifiedDate']) : null;

        // Determine period based on creation date
        $period = 'l60';
        if ($creationDate && $creationDate->gte($l30Start)) {
            $period = 'l30';
        }

        // Order level info
        $orderFulfillmentStatus = $order['orderFulfillmentStatus'] ?? null;
        $orderPaymentStatus = $order['orderPaymentStatus'] ?? null;
        $salesRecordReference = $order['salesRecordReference'] ?? null;
        $sellerId = $order['sellerId'] ?? null;

        // Buyer info
        $buyer = $order['buyer'] ?? [];
        $buyerUsername = $buyer['username'] ?? null;
        $buyerEmail = $buyer['buyerRegistrationAddress']['email'] ?? null;

        // Shipping address
        $fulfillmentStartInstructions = $order['fulfillmentStartInstructions'][0] ?? [];
        $shippingStep = $fulfillmentStartInstructions['shippingStep'] ?? [];
        $shipTo = $shippingStep['shipTo'] ?? [];
        $contactAddress = $shipTo['contactAddress'] ?? [];
        $primaryPhone = $shipTo['primaryPhone']['phoneNumber'] ?? null;

        $shipToName = $shipTo['fullName'] ?? null;
        $address1 = $contactAddress['addressLine1'] ?? null;
        $address2 = $contactAddress['addressLine2'] ?? null;
        $city = $contactAddress['city'] ?? null;
        $state = $contactAddress['stateOrProvince'] ?? null;
        $postalCode = $contactAddress['postalCode'] ?? null;
        $country = $contactAddress['countryCode'] ?? null;

        // Fulfillment instructions type
        $fulfillmentType = $fulfillmentStartInstructions['fulfillmentInstructionsType'] ?? null;

        // Pricing summary
        $pricingSummary = $order['pricingSummary'] ?? [];
        $totalPrice = isset($pricingSummary['total']['value']) ? (float) $pricingSummary['total']['value'] : null;
        $totalFee = isset($pricingSummary['totalMarketplaceFee']['value']) ? (float) $pricingSummary['totalMarketplaceFee']['value'] : null;

        // Cancel status
        $cancelStatus = $order['cancelStatus']['cancelState'] ?? null;
        $cancelRequests = $order['cancelStatus']['cancelRequests'] ?? [];
        $cancelReason = !empty($cancelRequests) ? ($cancelRequests[0]['cancelReason'] ?? null) : null;

        // Parse line items
        $lineItems = $order['lineItems'] ?? [];

        foreach ($lineItems as $lineItem) {
            $lineItemId = $lineItem['lineItemId'] ?? null;
            $sku = $lineItem['sku'] ?? null;
            $legacyItemId = $lineItem['legacyItemId'] ?? null;
            $legacyVariationId = $lineItem['legacyVariationId'] ?? null;
            $title = $lineItem['title'] ?? null;
            $quantity = (int) ($lineItem['quantity'] ?? 1);
            $lineItemFulfillmentStatus = $lineItem['lineItemFulfillmentStatus'] ?? null;

            // Line item pricing
            $unitPrice = isset($lineItem['lineItemCost']['value']) ? (float) $lineItem['lineItemCost']['value'] : null;
            $currency = $lineItem['lineItemCost']['currency'] ?? 'USD';
            $lineItemCost = $unitPrice;
            
            // Delivery cost
            $deliveryCost = isset($lineItem['deliveryCost']['shippingCost']['value']) 
                ? (float) $lineItem['deliveryCost']['shippingCost']['value'] : null;
            
            // Tax
            $taxDetails = $lineItem['lineItemFulfillmentInstructions']['taxDetails'] ?? [];
            $taxAmount = 0;
            if (!empty($taxDetails)) {
                foreach ($taxDetails as $tax) {
                    $taxAmount += (float) ($tax['amount']['value'] ?? 0);
                }
            }

            // eBay collect and remit tax
            $ebayCollectTax = 0;
            $ebayTaxes = $lineItem['ebayCollectAndRemitTaxes'] ?? [];
            foreach ($ebayTaxes as $tax) {
                $ebayCollectTax += (float) ($tax['amount']['value'] ?? 0);
            }

            // Discounts
            $discountAmount = 0;
            $appliedPromotions = $lineItem['appliedPromotions'] ?? [];
            foreach ($appliedPromotions as $promo) {
                $discountAmount += (float) ($promo['discountAmount']['value'] ?? 0);
            }

            // Refund
            $refundAmount = null;
            $refunds = $lineItem['refunds'] ?? [];
            if (!empty($refunds)) {
                $refundAmount = 0;
                foreach ($refunds as $refund) {
                    $refundAmount += (float) ($refund['amount']['value'] ?? 0);
                }
            }

            // Shipping info from fulfillment hrefs
            $shippingCarrier = null;
            $shippingService = null;
            $trackingNumber = null;
            $shippedDate = null;
            $actualDeliveryDate = null;

            // Try to get from fulfillments if available
            $fulfillments = $order['fulfillmentHrefs'] ?? [];
            // Note: Full fulfillment details would require additional API call

            if (!$orderId || !$lineItemId) {
                continue;
            }

            $lines[] = [
                'order_id' => $orderId,
                'legacy_order_id' => $legacyOrderId,
                'creation_date' => $creationDate?->toDateTimeString(),
                'last_modified_date' => $lastModifiedDate?->toDateTimeString(),
                'order_fulfillment_status' => $orderFulfillmentStatus,
                'order_payment_status' => $orderPaymentStatus,
                'sales_record_reference' => $salesRecordReference,
                'period' => $period,
                'line_item_id' => $lineItemId,
                'sku' => $sku,
                'legacy_item_id' => $legacyItemId,
                'legacy_variation_id' => $legacyVariationId,
                'title' => $title ? substr($title, 0, 500) : null,
                'quantity' => $quantity,
                'line_item_fulfillment_status' => $lineItemFulfillmentStatus,
                'unit_price' => $unitPrice,
                'currency' => $currency,
                'line_item_cost' => $lineItemCost,
                'shipping_cost' => $deliveryCost,
                'tax_amount' => $taxAmount > 0 ? $taxAmount : null,
                'discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                'ebay_collect_and_remit_tax' => $ebayCollectTax > 0 ? $ebayCollectTax : null,
                'total_price' => $totalPrice,
                'total_fee' => $totalFee,
                'total_marketplace_fee' => $totalFee,
                'buyer_username' => $buyerUsername,
                'buyer_email' => $buyerEmail ? substr($buyerEmail, 0, 255) : null,
                'ship_to_name' => $shipToName ? substr($shipToName, 0, 200) : null,
                'shipping_address1' => $address1 ? substr($address1, 0, 255) : null,
                'shipping_address2' => $address2 ? substr($address2, 0, 255) : null,
                'shipping_city' => $city ? substr($city, 0, 100) : null,
                'shipping_state' => $state ? substr($state, 0, 50) : null,
                'shipping_postal_code' => $postalCode,
                'shipping_country' => $country ? substr($country, 0, 10) : null,
                'shipping_phone' => $primaryPhone ? substr($primaryPhone, 0, 50) : null,
                'fulfillment_instructions_type' => $fulfillmentType,
                'shipping_carrier' => $shippingCarrier,
                'shipping_service' => $shippingService,
                'tracking_number' => $trackingNumber,
                'shipped_date' => $shippedDate,
                'actual_delivery_date' => $actualDeliveryDate,
                'cancel_status' => $cancelStatus,
                'cancel_reason' => $cancelReason ? substr($cancelReason, 0, 255) : null,
                'refund_amount' => $refundAmount,
                'seller_id' => $sellerId,
                'line_item_json' => json_encode($lineItem),
                'order_json' => json_encode($order),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return $lines;
    }

    /**
     * Bulk upsert orders
     */
    protected function bulkUpsertOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        try {
            Ebay3DailyData::upsert(
                $orders,
                ['order_id', 'line_item_id'],
                [
                    'legacy_order_id', 'creation_date', 'last_modified_date',
                    'order_fulfillment_status', 'order_payment_status', 'sales_record_reference',
                    'period', 'sku', 'legacy_item_id', 'legacy_variation_id', 'title',
                    'quantity', 'line_item_fulfillment_status', 'unit_price', 'currency',
                    'line_item_cost', 'shipping_cost', 'tax_amount', 'discount_amount',
                    'ebay_collect_and_remit_tax', 'total_price', 'total_fee',
                    'total_marketplace_fee', 'buyer_username', 'buyer_email',
                    'ship_to_name', 'shipping_address1', 'shipping_address2',
                    'shipping_city', 'shipping_state', 'shipping_postal_code',
                    'shipping_country', 'shipping_phone', 'fulfillment_instructions_type',
                    'shipping_carrier', 'shipping_service', 'tracking_number',
                    'shipped_date', 'actual_delivery_date', 'cancel_status',
                    'cancel_reason', 'refund_amount', 'seller_id',
                    'line_item_json', 'order_json', 'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to upsert eBay 3 orders: ' . $e->getMessage());
            $this->error('Upsert failed: ' . $e->getMessage());
        }
    }
}
