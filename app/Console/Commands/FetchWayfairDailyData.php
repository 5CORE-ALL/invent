<?php

namespace App\Console\Commands;

use App\Models\WayfairDailyData;
use App\Services\WayfairApiService;
use App\Services\WayfairDailyOrderFetchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FetchWayfairDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wayfair:daily {--days=60 : Number of days to fetch} {--no-sleep : Do not pause between Wayfair pages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all Wayfair purchase orders raw data and store daily sales';

    protected $authUrl = 'https://sso.auth.wayfair.com/oauth/token';
    protected $graphqlUrl = 'https://api.wayfair.com/v1/graphql';

    protected $clientId;
    protected $clientSecret;
    protected $audience;
    protected $grantType = 'client_credentials';

    public function __construct()
    {
        parent::__construct();

        $this->clientId = config('services.wayfair.client_id');
        $this->clientSecret = config('services.wayfair.client_secret');
        $this->audience = config('services.wayfair.audience');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $days = (int) $this->option('days');
        
        $this->info("Fetching Wayfair Daily Orders Data (Last {$days} days)...");
        
        $cutoffDate = Carbon::today()->subDays($days);
        $this->info("Cutoff date: {$cutoffDate->toDateString()}");

        if (! $this->clientId || ! $this->clientSecret) {
            $this->error('WAYFAIR_CLIENT_ID and WAYFAIR_CLIENT_SECRET must be set in .env');

            return 1;
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return 1;
        }

        $this->info("Access token received. Fetching dropship purchase orders from {$cutoffDate->toDateString()}...");

        $this->fetchAndStoreOrders($token, $cutoffDate);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Wayfair daily data fetched and stored successfully in {$duration} seconds.");
        
        return 0;
    }

    /**
     * Get access token from Wayfair API (via shared service: retries + 90s timeout).
     */
    private function getAccessToken(): ?string
    {
        try {
            $this->info('Requesting Wayfair OAuth token (may take up to 90s per attempt)...');

            return app(WayfairApiService::class)->getAccessTokenWithScope(null);
        } catch (\Throwable $e) {
            $this->error('Failed to retrieve access token: '.$e->getMessage());
            $this->line('If this persists: confirm the server can reach https://sso.auth.wayfair.com (firewall/DNS), or raise WAYFAIR_HTTP_TIMEOUT in .env.');

            return null;
        }
    }

    /**
     * Fetch POs from the dropship API (fromDate) and upsert daily-sales rows.
     */
    protected function fetchAndStoreOrders(string $token, Carbon $cutoffDate): void
    {
        $result = app(WayfairDailyOrderFetchService::class)
            ->fetchPurchaseOrders($cutoffDate, $token, ! $this->option('no-sleep'));

        if (! empty($result['errors'])) {
            $this->error('Wayfair GraphQL errors: '.json_encode($result['errors']));
        }

        $purchaseOrders = $result['orders'] ?? [];
        $this->info('Source: '.($result['source'] ?? 'unknown').', POs: '.count($purchaseOrders));

        $insertedProducts = 0;
        $bulkData = [];

        foreach ($purchaseOrders as $po) {
            $products = $po['products'] ?? [];
            foreach ($products as $product) {
                $orderData = $this->parseOrderData($po, $product);
                if ($orderData) {
                    $bulkData[] = $orderData;
                    $insertedProducts++;
                }
            }

            if (count($bulkData) >= 100) {
                $this->bulkUpsertOrders($bulkData);
                $bulkData = [];
            }
        }

        if (! empty($bulkData)) {
            $this->bulkUpsertOrders($bulkData);
        }

        $this->info('Fetched '.count($purchaseOrders).' POs, stored '.$insertedProducts.' products.');
    }

    /**
     * Parse order data from API response
     */
    protected function parseOrderData(array $po, array $product): ?array
    {
        $poDate = $po['poDate'] ?? null;
        if (!$poDate) return null;

        $sku = $product['partNumber'] ?? null;
        if (!$sku) return null;

        // Calculate period based on PO date
        $poDateCarbon = Carbon::parse($poDate);
        $today = Carbon::today();
        $daysDiff = $today->diffInDays($poDateCarbon);
        $period = $daysDiff <= 30 ? 'l30' : 'l60';

        // Get shipping info
        $shippingInfo = $po['shippingInfo'] ?? [];
        $shipTo = $po['shipTo'] ?? [];
        $warehouse = $po['warehouse'] ?? [];
        $event = $product['event'] ?? [];

        $quantity = (int) ($product['quantity'] ?? 1);
        $unitPrice = (float) ($product['price'] ?? 0);
        $totalPrice = $unitPrice * $quantity;

        return [
            'po_number' => $po['poNumber'] ?? null,
            'po_date' => $poDateCarbon->toDateString(),
            'period' => $period,
            'status' => 'open',
            'sku' => $sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'estimated_ship_date' => isset($po['estimatedShipDate']) ? Carbon::parse($po['estimatedShipDate'])->toDateString() : null,
            
            // Customer info (use shipTo for delivery address)
            'customer_name' => $shipTo['name'] ?? $po['customerName'] ?? null,
            'customer_address1' => $shipTo['address1'] ?? $po['customerAddress1'] ?? null,
            'customer_address2' => $shipTo['address2'] ?? $po['customerAddress2'] ?? null,
            'customer_city' => $shipTo['city'] ?? $po['customerCity'] ?? null,
            'customer_state' => $shipTo['state'] ?? $po['customerState'] ?? null,
            'customer_postal_code' => $shipTo['postalCode'] ?? $po['customerPostalCode'] ?? null,
            'customer_country' => $shipTo['country'] ?? null,
            'customer_phone' => $shipTo['phoneNumber'] ?? null,
            
            // Shipping info
            'ship_speed' => $shippingInfo['shipSpeed'] ?? null,
            'carrier_code' => $shippingInfo['carrierCode'] ?? null,
            
            // Warehouse info
            'warehouse_id' => $warehouse['id'] ?? null,
            'warehouse_name' => $warehouse['name'] ?? null,
            
            // Event info
            'event_id' => $event['id'] ?? null,
            'event_type' => $event['type'] ?? null,
            'event_name' => $event['name'] ?? null,
            
            'packing_slip_url' => $po['packingSlipUrl'] ?? null,
            'raw_payload' => json_encode($po),
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function keepStoredWayfairTracking(array $order, mixed $existingPayload): array
    {
        if (is_string($existingPayload)) {
            $decoded = json_decode($existingPayload, true);
            $existingPayload = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($existingPayload)) {
            return $order;
        }
        $tn = trim((string) ($existingPayload['tracking_number'] ?? ''));
        if ($tn === '') {
            return $order;
        }
        $payload = json_decode((string) ($order['raw_payload'] ?? ''), true);
        if (! is_array($payload)) {
            $payload = [];
        }
        if (trim((string) ($payload['tracking_number'] ?? '')) === '') {
            $payload['tracking_number'] = $tn;
            $carrier = trim((string) ($existingPayload['tracking_company'] ?? ''));
            if ($carrier !== '') {
                $payload['tracking_company'] = $carrier;
            }
            $order['raw_payload'] = json_encode($payload);
        }

        return $order;
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
                    $existing = DB::table('wayfair_daily_data')
                        ->where('po_number', $order['po_number'])
                        ->where('sku', $order['sku'])
                        ->first(['raw_payload']);
                    $order = $this->keepStoredWayfairTracking($order, $existing->raw_payload ?? null);
                    $exists = $existing !== null;
                    if (! $exists && empty($order['id'])) {
                        $order['id'] = ((int) DB::table('wayfair_daily_data')->max('id')) + 1;
                    }
                    DB::table('wayfair_daily_data')
                        ->updateOrInsert(
                            [
                                'po_number' => $order['po_number'],
                                'sku' => $order['sku']
                            ],
                            $order
                        );
                }
            });
        } catch (\Exception $e) {
            $this->error('Error bulk upserting orders: ' . $e->getMessage());
            // Fallback to individual inserts
            foreach ($orders as $order) {
                try {
                    WayfairDailyData::updateOrCreate(
                        [
                            'po_number' => $order['po_number'],
                            'sku' => $order['sku']
                        ],
                        $order
                    );
                } catch (\Exception $e) {
                    $this->warn('Failed to insert order ' . ($order['po_number'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }
        }
    }
}
