<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmazonSpApiService;
use App\Models\FbaShipment;
use Illuminate\Support\Facades\Log;

class SyncFbaShipmentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fba:sync-shipment-status {--sku= : Sync specific SKU only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch FBA shipment status from Amazon API and update database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting FBA shipment status sync...');
        
        try {
            $service = app(AmazonSpApiService::class);
            $specificSku = $this->option('sku');
            
            $updated = 0;
            $created = 0;
            $skipped = 0;
            $errors = 0;
            
            // Use date range to fix Amazon's pagination bug
            // Without date filter OR with only LastUpdatedAfter, Amazon returns same shipments on every page
            // Using a proper date range (both After and Before) fixes this
            $lastUpdatedAfter = '2024-01-01T00:00:00Z';  // From January 2024
            $lastUpdatedBefore = now()->addDay()->toIso8601String(); // Until tomorrow
            
            // Process active shipments with streaming callback
            $this->info('Fetching and processing active shipments...');
            $activeStatuses = ['WORKING', 'SHIPPED', 'IN_TRANSIT', 'DELIVERED', 'CHECKED_IN', 'RECEIVING'];
            
            $callback = function($shipments) use ($service, $specificSku, &$updated, &$created, &$skipped, &$errors) {
                $this->processShipmentBatch($shipments, $service, $specificSku, $updated, $created, $skipped, $errors);
            };
            
            try {
                $service->getFbaShipmentsStreaming($activeStatuses, null, $lastUpdatedAfter, $lastUpdatedBefore, $callback);
                $this->info('✓ Active shipments processing completed');
            } catch (\Exception $e) {
                $this->error('Active shipments processing stopped: ' . $e->getMessage());
                $this->warn('Continuing with closed shipments...');
            }
            
            // Process closed/cancelled shipments with streaming callback
            $this->newLine();
            $this->info('Fetching and processing closed/cancelled shipments...');
            $closedStatuses = ['CLOSED', 'CANCELLED', 'DELETED', 'ERROR'];
            
            try {
                $service->getFbaShipmentsStreaming($closedStatuses, null, $lastUpdatedAfter, $lastUpdatedBefore, $callback);
                $this->info('✓ Closed shipments processing completed');
            } catch (\Exception $e) {
                $this->error('Closed shipments processing stopped: ' . $e->getMessage());
            }
            
            $this->newLine();
            $this->info('Sync completed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Updated Records', $updated],
                    ['Created Records', $created],
                    ['Errors', $errors],
                    ['Skipped', $skipped],
                ]
            );
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            Log::error('FBA Sync: Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
    
    /**
     * Process a batch of shipments from one API page
     */
    private function processShipmentBatch($shipments, $service, $specificSku, &$updated, &$created, &$skipped, &$errors)
    {
        // Filter for 2025 shipments only
        $shipments2025 = array_filter($shipments, function($shipment) {
            $name = $shipment['ShipmentName'] ?? '';
            return preg_match('/2025/', $name);
        });
        
        if (empty($shipments2025)) {
            return;
        }
        
        foreach ($shipments2025 as $shipment) {
            $shipmentId = $shipment['ShipmentId'] ?? null;
            $shipmentStatus = $shipment['ShipmentStatus'] ?? null;
            $shipmentName = $shipment['ShipmentName'] ?? null;
            $destinationFC = $shipment['DestinationFulfillmentCenterId'] ?? null;
            
            if (!$shipmentId) {
                continue;
            }
            
            $this->line("Processing: {$shipmentName} ({$shipmentId})");
            
            try {
                $itemsResponse = $this->getShipmentItems($service, $shipmentId);
                
                if (!isset($itemsResponse['payload']['ItemData'])) {
                    $this->warn("  No items found");
                    continue;
                }
                
                $items = $itemsResponse['payload']['ItemData'];
                
                foreach ($items as $item) {
                    $sku = $item['SellerSKU'] ?? null;
                    $quantityShipped = $item['QuantityShipped'] ?? 0;
                    $quantityReceived = $item['QuantityReceived'] ?? 0;
                    
                    if (!$sku) {
                        continue;
                    }
                    
                    // If specific SKU requested, filter
                    if ($specificSku && stripos($sku, $specificSku) === false) {
                        continue;
                    }
                    
                    // Map API status to your status codes
                    $statusCode = $this->mapShipmentStatus($shipmentStatus);
                    
                    // Set flags based on status
                    $fbaSend = in_array($shipmentStatus, ['SHIPPED', 'IN_TRANSIT', 'DELIVERED', 'RECEIVING']);
                    $listed = in_array($shipmentStatus, ['CLOSED', 'RECEIVING']);
                    $live = in_array($shipmentStatus, ['CLOSED', 'RECEIVING']);
                    $done = ($shipmentStatus === 'CLOSED' && $quantityReceived > 0);
                    
                    try {
                        // Use updateOrCreate to handle both insert and update
                        $fbaShipment = FbaShipment::updateOrCreate(
                            [
                                'shipment_id' => $shipmentId,
                                'sku' => $sku
                            ],
                            [
                                'shipment_status' => $shipmentStatus,
                                'status_code' => $statusCode,
                                'shipment_name' => $shipmentName,
                                'destination_fc' => $destinationFC,
                                'quantity_shipped' => $quantityShipped,
                                'quantity_received' => $quantityReceived,
                                'fba_send' => $fbaSend,
                                'listed' => $listed,
                                'live' => $live,
                                'done' => $done,
                                'last_api_sync' => now()
                            ]
                        );
                        
                        if ($fbaShipment->wasRecentlyCreated) {
                            $created++;
                        } else {
                            $updated++;
                        }
                        
                        $this->info("  ✓ {$sku}: {$shipmentStatus}, Shipped={$quantityShipped}, Received={$quantityReceived}");
                    } catch (\Exception $dbError) {
                        $this->error("  ✗ DB Error for {$sku}: " . $dbError->getMessage());
                        $errors++;
                        Log::error('FBA Sync: Database error', [
                            'shipment_id' => $shipmentId,
                            'sku' => $sku,
                            'error' => $dbError->getMessage()
                        ]);
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  Error: " . $e->getMessage());
                Log::error('FBA Sync: Error processing shipment', [
                    'shipment_id' => $shipmentId,
                    'error' => $e->getMessage()
                ]);
                $errors++;
                $skipped++;
            }
        }
    }
    
    /**
     * Get items for a specific shipment with retry on 403
     */
    private function getShipmentItems($service, $shipmentId)
    {
        $maxRetries = 2;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                // Get fresh access token for each attempt
                $accessToken = $service->getAccessToken();
                $url = "https://sellingpartnerapi-na.amazon.com/fba/inbound/v0/shipments/{$shipmentId}/items";
                
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->get($url);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                // If 403, clear cache and retry
                if ($response->status() === 403 && $attempt < $maxRetries) {
                    $this->warn("  Got 403 for shipment items, clearing token and retrying (attempt {$attempt}/{$maxRetries})...");
                    \Illuminate\Support\Facades\Cache::forget('amazon_spapi_access_token');
                    sleep(1); // Wait 1 second before retry
                    continue;
                }
                
                throw new \Exception('Failed to get shipment items: ' . $response->status());
                
            } catch (\Exception $e) {
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                $this->warn("  Error getting shipment items (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());
                sleep(1);
            }
        }
        
        throw new \Exception('Failed to get shipment items after ' . $maxRetries . ' attempts');
    }
    
    /**
     * Map Amazon shipment status to internal status codes
     */
    private function mapShipmentStatus($status)
    {
        $statusMap = [
            'WORKING' => '10',          // In preparation
            'READY_TO_SHIP' => '20',    // Ready to ship
            'SHIPPED' => '30',          // Shipped
            'IN_TRANSIT' => '35',       // In transit
            'DELIVERED' => '40',        // Delivered to FC
            'CHECKED_IN' => '42',       // Checked in at FC
            'RECEIVING' => '44',        // Being received
            'CLOSED' => '50',           // Fully received
            'CANCELLED' => '90',        // Cancelled
            'DELETED' => '91',          // Deleted
            'ERROR' => '99',            // Error
        ];
        
        return $statusMap[$status] ?? '44'; // Default to 44 (receiving)
    }
}





