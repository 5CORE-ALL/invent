<?php

namespace App\Console\Commands;

use App\Models\LinkedProductData;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncLinkedProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-linked-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto balance linked products by dilution % and available qty and update Shopify + DB with shopify APIs';

    private $shopifyDomain;
    private $shopifyApiKey;
    private $shopifyPassword;

    public function __construct()
    {
        parent::__construct();
        $this->shopifyDomain  = env('SHOPIFY_5CORE_DOMAIN');
        $this->shopifyApiKey  = env('SHOPIFY_5CORE_API_KEY');
        $this->shopifyPassword = env('SHOPIFY_5CORE_PASSWORD');
    }


    /**
     * Execute the console command.
     */

    // public function handle()
    // {
    //     // Normalize SKUs consistently (trim spaces, collapse multiple spaces)
    //     $normalizeSku = fn($sku) => trim(preg_replace('/\s+/', ' ', $sku));

    //     // Step 1: Get all products grouped by group_id
    //     $groups = ProductMaster::whereNotNull('group_id')->get()->groupBy('group_id');

    //     // Step 2: Fetch ShopifySku data for all SKUs (correct L30 source)
    //     $allSkus = $groups->flatten(1)->pluck('sku')->map($normalizeSku)->unique()->values()->toArray();

    //     $shopifyData = ShopifySku::whereIn('sku', $allSkus)
    //         ->get()
    //         ->keyBy(fn($item) => $normalizeSku($item->sku));

    //     foreach ($groups as $groupId => $products) {
    //         $this->info("Processing Group {$groupId}");

    //         // Prepare pack data
    //         $packData = [];
    //         $totalPieces = 0;
    //         $totalL30 = 0;

    //         foreach ($products as $product) {
    //             $sku = $normalizeSku($product->sku);
    //             $availableQty = $this->getShopifyAvailableQty($sku, $normalizeSku);
    //             $l30 = isset($shopifyData[$sku]) ? (int)$shopifyData[$sku]->quantity : 0;

    //             // Detect pack size from SKU name (e.g., '1Pc', '2Pcs')
    //             if (preg_match('/(\d+)\s*P(?:c|cs)/i', $product->sku, $matches)) {
    //                 $packSize = (int)$matches[1];
    //             } else {
    //                 $packSize = 1;
    //             }

    //             $product->calc_available_qty = $availableQty;
    //             $product->calc_l30 = $l30;
    //             $product->dil = $availableQty > 0 ? round(($l30 / $availableQty) * 100, 2) : 0;

    //             $this->info("SKU {$sku}: Avl={$availableQty}, L30={$l30}, Dil={$product->dil}");

    //             $packData[$sku] = [
    //                 'product' => $product,
    //                 'packSize' => $packSize,
    //                 'l30' => $l30,
    //             ];

    //             $totalPieces += $availableQty * $packSize;
    //             $totalL30 += $l30;
    //         }   

    //         if ($totalPieces == 0) {
    //             $this->warn("Group {$groupId} has no stock, skipping.");
    //             continue;
    //         }

    //         $this->info("Total pieces: {$totalPieces}, Total L30: {$totalL30}");

    //         // Step 5: Redistribute pieces proportional to L30
    //         $allocations = [];
    //         $remainders = [];
    //         $assignedPieces = 0;

    //         foreach ($packData as $sku => $data) {
    //             $ideal = ($totalL30 > 0 && $data['l30'] > 0)
    //                 ? ($data['l30'] / $totalL30) * $totalPieces
    //                 : $totalPieces / count($packData);

    //             $allocations[$sku] = (int) floor($ideal);
    //             $remainders[$sku] = $ideal - $allocations[$sku];
    //             $assignedPieces += $allocations[$sku];
    //         }

    //         // Handle leftover pieces: send to 1Pc SKU
    //         $leftover = $totalPieces - $assignedPieces;
    //         if ($leftover > 0) {
    //             $onePcSku = null;
    //             foreach ($packData as $sku => $data) {
    //                 if ($data['packSize'] === 1) {
    //                     $onePcSku = $sku;
    //                     break;
    //                 }
    //             }

    //             if ($onePcSku) {
    //                 $allocations[$onePcSku] += $leftover;
    //             } else {
    //                 // fallback: distribute to largest remainder
    //                 arsort($remainders);
    //                 foreach (array_keys($remainders) as $sku) {
    //                     if ($leftover <= 0) break;
    //                     $allocations[$sku]++;
    //                     $leftover--;
    //                 }
    //             }
    //         }

    //         // Convert back to pack quantities and update Shopify
    //         foreach ($packData as $sku => $data) {
    //             $oldQty = $data['product']->calc_available_qty;
    //             $oldDil = $data['product']->dil;

    //             $pieces = $allocations[$sku];
    //             $newQty = (int) floor($pieces / $data['packSize']);
    //             $newDil = $newQty > 0
    //                 ? round(($data['l30'] / ($newQty * $data['packSize'])) * 100, 2)
    //                 : 0;

    //             $this->info("SKU {$sku} old_qty={$oldQty}, new_qty={$newQty}");

    //             LinkedProductData::create([
    //                 'group_id' => $groupId,
    //                 'sku'      => $sku,
    //                 'old_qty'  => $oldQty,
    //                 'new_qty'  => $newQty,
    //                 'old_dil'  => $oldDil,
    //                 'new_dil'  => $newDil,
    //             ]);

    //             $this->updateShopifyQty($sku, $newQty, $normalizeSku);
    //         }
    //     }

    //     return Command::SUCCESS;
    // }

    // private function getShopifyAvailableQty($sku, $normalizeSku)
    // {
    //     $inventoryItemId = null;
    //     $pageInfo = null;

    //     do {
    //         $queryParams = ['limit' => 250];
    //         if ($pageInfo) $queryParams['page_info'] = $pageInfo;

    //         $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

    //         if (!$response->successful()) return 0;

    //         $products = $response->json('products');
    //         foreach ($products as $product) {
    //             foreach ($product['variants'] as $variant) {
    //                 $variantSku = $normalizeSku($variant['sku'] ?? '');
    //                 if ($variantSku === $sku) {
    //                     $inventoryItemId = $variant['inventory_item_id'];
    //                     break 2;
    //                 }
    //             }
    //         }

    //         $linkHeader = $response->header('Link');
    //         $pageInfo = null;
    //         if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
    //             $pageInfo = $matches[1];
    //         }
    //     } while (!$inventoryItemId && $pageInfo);

    //     if (!$inventoryItemId) return 0;

    //     $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //         ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
    //             'inventory_item_ids' => $inventoryItemId
    //         ]);

    //     return collect($invLevelResponse->json('inventory_levels') ?? [])->sum('available');
    // }

    // private function updateShopifyQty($sku, $newQty, $normalizeSku)
    // {
    //     $inventoryItemId = null;
    //     $pageInfo = null;

    //     do {
    //         $queryParams = ['limit' => 250];
    //         if ($pageInfo) $queryParams['page_info'] = $pageInfo;

    //         $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //             ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

    //         if (!$response->successful()) return;

    //         $products = $response->json('products');
    //         foreach ($products as $product) {
    //             foreach ($product['variants'] as $variant) {
    //                 $variantSku = $normalizeSku($variant['sku'] ?? '');
    //                 if ($variantSku === $sku) {
    //                     $inventoryItemId = $variant['inventory_item_id'];
    //                     break 2;
    //                 }
    //             }
    //         }

    //         $linkHeader = $response->header('Link');
    //         $pageInfo = null;
    //         if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
    //             $pageInfo = $matches[1];
    //         }
    //     } while (!$inventoryItemId && $pageInfo);

    //     if (!$inventoryItemId) return;

    //     $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //         ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
    //             'inventory_item_ids' => $inventoryItemId
    //         ]);

    //     $locationId = $invLevelResponse->json('inventory_levels.0.location_id') ?? null;
    //     if (!$locationId) return;

    //     Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
    //         ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/set.json", [
    //             'location_id'       => $locationId,
    //             'inventory_item_id' => $inventoryItemId,
    //             'available'         => $newQty,
    //         ]);
    // }


    public function handle()
    {
        $normalizeSku = fn($sku) => trim(preg_replace('/\s+/', ' ', $sku));

        $groups = ProductMaster::whereNotNull('group_id')->get()->groupBy('group_id');

        $allSkus = $groups->flatten(1)->pluck('sku')->map($normalizeSku)->unique()->values()->toArray();

        $shopifyData = ShopifySku::whereIn('sku', $allSkus)
            ->get()
            ->keyBy(fn($item) => $normalizeSku($item->sku));

        foreach ($groups as $groupId => $products) {
            $this->info("Processing Group {$groupId}");

            $packData = [];
            $totalPieces = 0;
            $totalL30 = 0;

            foreach ($products as $product) {
                $sku = $normalizeSku($product->sku);
                $availableQty = $this->getShopifyAvailableQty($sku, $normalizeSku);
                $l30 = isset($shopifyData[$sku]) ? (int)$shopifyData[$sku]->quantity : 0;

                // detect pack size
                if (preg_match('/(\d+)\s*P(?:c|cs)/i', $product->sku, $matches)) {
                    $packSize = (int)$matches[1];
                } else {
                    $packSize = 1;
                }

                $product->calc_available_qty = $availableQty;
                $product->calc_l30 = $l30;
                $product->dil = $availableQty > 0 ? round(($l30 / $availableQty) * 100, 2) : 0;

                $this->info("SKU {$sku}: Avl={$availableQty}, L30={$l30}, Dil={$product->dil}");

                $packData[$sku] = [
                    'product'  => $product,
                    'packSize' => $packSize,
                    'l30'      => $l30,
                ];

                $totalPieces += $availableQty * $packSize;
                $totalL30 += $l30;
            }

            if ($totalPieces == 0) {
                $this->warn("Group {$groupId} has no stock, skipping.");
                continue;
            }

            $this->info("Total pieces: {$totalPieces}, Total L30: {$totalL30}");

            
            // Distribute by packs (not raw pieces)
           
            $allocations = [];
            $remainders = [];
            $assignedPieces = 0;

            foreach ($packData as $sku => $data) {
                $idealPieces = ($totalL30 > 0 && $data['l30'] > 0)
                    ? ($data['l30'] / $totalL30) * $totalPieces
                    : $totalPieces / count($packData);

                $idealPacks = $idealPieces / $data['packSize'];
                $allocations[$sku] = (int) floor($idealPacks);
                $remainders[$sku]  = $idealPacks - $allocations[$sku];

                $assignedPieces += $allocations[$sku] * $data['packSize'];
            }

            // Handle leftover pieces: always assign to smallest pack SKU
            $leftover = $totalPieces - $assignedPieces;
            if ($leftover > 0) {
                $smallestSku = collect($packData)->sortBy('packSize')->keys()->first();
                $smallestPack = $packData[$smallestSku]['packSize'];

                // add full packs
                $extraPacks = intdiv($leftover, $smallestPack);
                $allocations[$smallestSku] += $extraPacks;

                // if remainder still exists, add one more pack
                $remainder = $leftover % $smallestPack;
                if ($remainder > 0) {
                    $allocations[$smallestSku] += 1;
                }
            }

           
            // Update DB + Shopify
           
            foreach ($packData as $sku => $data) {
                $oldQty = $data['product']->calc_available_qty;
                $oldDil = $data['product']->dil;

                $newQty = $allocations[$sku];
                $newDil = $newQty > 0
                    ? round(($data['l30'] / ($newQty * $data['packSize'])) * 100, 2)
                    : 0;

                $this->info("SKU {$sku} old_qty={$oldQty}, new_qty={$newQty}");

                LinkedProductData::create([
                    'group_id' => $groupId,
                    'sku'      => $sku,
                    'old_qty'  => $oldQty,
                    'new_qty'  => $newQty,
                    'old_dil'  => $oldDil,
                    'new_dil'  => $newDil,
                ]);

                $this->updateShopifyQty($sku, $newQty, $normalizeSku);
            }
        }

        return Command::SUCCESS;
    }

    private function getShopifyAvailableQty($sku, $normalizeSku)
    {
        $inventoryItemId = null;
        $pageInfo = null;

        do {
            $queryParams = ['limit' => 250];
            if ($pageInfo) $queryParams['page_info'] = $pageInfo;

            $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

            if (!$response->successful()) return 0;

            $products = $response->json('products');
            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    $variantSku = $normalizeSku($variant['sku'] ?? '');
                    if ($variantSku === $sku) {
                        $inventoryItemId = $variant['inventory_item_id'];
                        break 2;
                    }
                }
            }

            $linkHeader = $response->header('Link');
            $pageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
                $pageInfo = $matches[1];
            }
        } while (!$inventoryItemId && $pageInfo);

        if (!$inventoryItemId) return 0;

        $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                'inventory_item_ids' => $inventoryItemId
            ]);

        return collect($invLevelResponse->json('inventory_levels') ?? [])->sum('available');
    }

    private function updateShopifyQty($sku, $newQty, $normalizeSku)
    {
        $inventoryItemId = null;
        $pageInfo = null;

        do {
            $queryParams = ['limit' => 250];
            if ($pageInfo) $queryParams['page_info'] = $pageInfo;

            $response = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
                ->get("https://{$this->shopifyDomain}/admin/api/2025-01/products.json", $queryParams);

            if (!$response->successful()) return;

            $products = $response->json('products');
            foreach ($products as $product) {
                foreach ($product['variants'] as $variant) {
                    $variantSku = $normalizeSku($variant['sku'] ?? '');
                    if ($variantSku === $sku) {
                        $inventoryItemId = $variant['inventory_item_id'];
                        break 2;
                    }
                }
            }

            $linkHeader = $response->header('Link');
            $pageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+page_info=([^&>]+)[^>]*>; rel="next"/', $linkHeader, $matches)) {
                $pageInfo = $matches[1];
            }
        } while (!$inventoryItemId && $pageInfo);

        if (!$inventoryItemId) return;

        $invLevelResponse = Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->get("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels.json", [
                'inventory_item_ids' => $inventoryItemId
            ]);

        $locationId = $invLevelResponse->json('inventory_levels.0.location_id') ?? null;
        if (!$locationId) return;

        Http::withBasicAuth($this->shopifyApiKey, $this->shopifyPassword)
            ->post("https://{$this->shopifyDomain}/admin/api/2025-01/inventory_levels/set.json", [
                'location_id'       => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available'         => $newQty,
            ]);
    }

    
}
