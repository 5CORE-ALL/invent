<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ShopifyApiInventoryController;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncShopifyLiveInventory extends Command
{
    protected $signature = 'shopify:sync-live-inventory {--sku= : Sync a single SKU only}';
    protected $description = 'Sync live on_hand/available/committed from Shopify (Ohio location only)';

    public function handle()
    {
        set_time_limit(0);

        $sku = $this->option('sku');

        if ($sku) {
            $this->syncSingleSku($sku);
            return;
        }

        $controller = new ShopifyApiInventoryController();
        $success = $controller->syncLiveInventoryToDb();

        if ($success) {
            $this->info('Successfully synced live Shopify inventory (Ohio)');
        } else {
            $this->error('Failed to sync live Shopify inventory');
        }
    }

    protected function shopifyGet(string $url, array $params, string $token): \Illuminate\Http\Client\Response
    {
        $maxRetries = 5;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $res = Http::withHeaders(['X-Shopify-Access-Token' => $token])
                ->get($url, $params);
            if ($res->status() === 429) {
                $wait = (int) ($res->header('Retry-After') ?? 10);
                $this->warn("  Rate limited (429) — waiting {$wait}s before retry {$attempt}/{$maxRetries}...");
                sleep($wait + 1);
                continue;
            }
            return $res;
        }
        return $res;
    }

    protected function syncSingleSku(string $sku)
    {
        $shopifyDomain = config('services.shopify.store_url');
        $token = config('services.shopify.password');

        $this->info("Looking up SKU: {$sku}");

        // Find the variant_id from DB
        $row = ShopifySku::whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper(trim($sku))])->first();
        if (!$row || !$row->variant_id) {
            $this->error("SKU not found in DB or missing variant_id: {$sku}");
            return;
        }

        sleep(3); // avoid rate limit
        // Get inventory_item_id from variant
        $variantRes = $this->shopifyGet(
            "https://{$shopifyDomain}/admin/api/2025-01/variants/{$row->variant_id}.json",
            [], $token
        );

        if (!$variantRes->successful()) {
            $this->error("Failed to fetch variant: " . $variantRes->body());
            return;
        }

        $inventoryItemId = $variantRes->json('variant.inventory_item_id');
        if (!$inventoryItemId) {
            $this->error("No inventory_item_id found for variant");
            return;
        }

        // Get Ohio location ID (cached 24h to avoid repeated API calls)
        $locationId = \Cache::remember('shopify_ohio_location_id', 86400, function () use ($shopifyDomain, $token) {
            sleep(3);
            $locRes = $this->shopifyGet("https://{$shopifyDomain}/admin/api/2025-01/locations.json", [], $token);
            if ($locRes->successful()) {
                foreach ($locRes->json('locations') ?? [] as $loc) {
                    if (stripos($loc['name'], 'Ohio') !== false) {
                        return $loc['id'];
                    }
                }
            }
            return null;
        });

        if (!$locationId) {
            $this->error("Ohio location not found in Shopify");
            return;
        }

        $this->info("Ohio location ID: {$locationId}");

        sleep(2);
        $invRes = $this->shopifyGet(
            "https://{$shopifyDomain}/admin/api/2025-01/inventory_levels.json",
            ['inventory_item_ids' => $inventoryItemId, 'location_ids' => $locationId],
            $token
        );

        if (!$invRes->successful()) {
            $this->error("Failed to fetch inventory levels: " . $invRes->body());
            return;
        }

        $levels = $invRes->json('inventory_levels') ?? [];
        $available = $levels[0]['available'] ?? 0;

        sleep(2);
        $ordersRes = $this->shopifyGet(
            "https://{$shopifyDomain}/admin/api/2025-01/orders.json",
            ['status' => 'open', 'fulfillment_status' => 'unfulfilled', 'limit' => 250],
            $token
        );

        $committed = 0;
        if ($ordersRes->successful()) {
            foreach ($ordersRes->json('orders') ?? [] as $order) {
                foreach ($order['line_items'] as $item) {
                    if (strtoupper(trim($item['sku'] ?? '')) === strtoupper(trim($sku))) {
                        $committed += (int) $item['quantity'];
                    }
                }
            }
        }

        $onHand = $available + $committed;

        ShopifySku::where('sku', $row->sku)->update([
            'available_to_sell' => $available,
            'committed'         => $committed,
            'on_hand'           => $onHand,
            'updated_at'        => now(),
        ]);

        $this->info("Updated SKU: {$sku}");
        $this->info("  available_to_sell = {$available}");
        $this->info("  committed         = {$committed}");
        $this->info("  on_hand           = {$onHand}");
    }
}
