<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\WayfairDailyData;
use Carbon\Carbon;

class FetchWayfairDailyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wayfair:daily {--days=60 : Number of days to fetch}';

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

        $token = $this->getAccessToken();
        if (!$token) {
            $this->error("Failed to retrieve access token.");
            return 1;
        }

        $this->info("Access token received. Fetching all purchase orders...");
        
        $this->fetchAndStoreOrders($token, $cutoffDate);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("Wayfair daily data fetched and stored successfully in {$duration} seconds.");
        
        return 0;
    }

    /**
     * Get access token from Wayfair API
     */
    private function getAccessToken()
    {
        $response = Http::asForm()->post($this->authUrl, [
            'grant_type' => $this->grantType,
            'audience' => $this->audience,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        return $response->successful() ? ($response->json()['access_token'] ?? null) : null;
    }

    /**
     * Fetch all orders from Wayfair API and store raw data
     * Note: Wayfair API returns orders oldest-first and doesn't support date filtering
     * So we fetch ALL orders and store them (period calculated based on date)
     */
    protected function fetchAndStoreOrders(string $token, Carbon $cutoffDate): void
    {
        $limit = 100;
        $offset = 0;
        $totalOrders = 0;
        $insertedProducts = 0;
        $bulkData = [];

        do {
            $purchaseOrders = $this->fetchPurchaseOrders($token, $limit, $offset);

            if (empty($purchaseOrders)) {
                break;
            }

            $totalOrders += count($purchaseOrders);

            foreach ($purchaseOrders as $po) {
                $products = $po['products'] ?? [];
                foreach ($products as $product) {
                    $orderData = $this->parseOrderData($po, $product);
                    if ($orderData) {
                        $bulkData[] = $orderData;
                        $insertedProducts++;
                    }
                }
            }

            // Bulk insert in chunks of 100
            if (count($bulkData) >= 100) {
                $this->bulkUpsertOrders($bulkData);
                $bulkData = [];
            }

            $offset += $limit;
            $this->info("  Processed offset {$offset} ({$totalOrders} POs fetched, {$insertedProducts} products)...");

        } while (!empty($purchaseOrders));

        // Insert remaining orders
        if (!empty($bulkData)) {
            $this->bulkUpsertOrders($bulkData);
        }

        $this->info("Fetched {$totalOrders} total POs, stored {$insertedProducts} products.");
    }

    /**
     * Fetch purchase orders from Wayfair GraphQL API
     */
    private function fetchPurchaseOrders(string $token, int $limit, int $offset): array
    {
        $query = <<<'GRAPHQL'
        query GetPurchaseOrders($limit: Int!, $offset: Int!) {
            purchaseOrders(
                limit: $limit,
                offset: $offset
            ) {
                poNumber
                poDate
                estimatedShipDate
                customerName
                customerAddress1
                customerAddress2
                customerCity
                customerState
                customerPostalCode
                shippingInfo {
                    shipSpeed
                    carrierCode
                }
                packingSlipUrl
                warehouse {
                    id
                    name
                }
                products {
                    partNumber
                    quantity
                    price
                    event {
                        id
                        type
                        name
                    }
                }
                shipTo {
                    name
                    address1
                    address2
                    city
                    state
                    country
                    postalCode
                    phoneNumber
                }
            }
        }
        GRAPHQL;

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->post($this->graphqlUrl, [
                'query' => $query,
                'variables' => [
                    'limit' => $limit,
                    'offset' => $offset,
                ]
            ]);

        if (!$response->successful()) {
            $this->error("Wayfair API Error: " . $response->body());
            return [];
        }

        return $response->json()['data']['purchaseOrders'] ?? [];
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
