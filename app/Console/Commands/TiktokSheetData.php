<?php

namespace App\Console\Commands;

use App\Models\TiktokSheet;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiktokSheetData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:tiktok-sheet-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok product sheet data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = 'https://script.google.com/macros/s/AKfycbzRCPSyZt1sRoEODQhg6YjvPyyMTNeXfW_z--xS3QQ1dLEnKYLm70l5ut4q8VzsuQaD/exec';

        try {
            $response = Http::timeout(120)->get($url);

            if (! $response->successful()) {
                $this->error('Failed to fetch data. Status: '.$response->status());

                return;
            }

            $rows = collect($response->json());   // ✅ Direct array parsing
            $this->info('Rows count: '.$rows->count());

        } catch (\Exception $e) {
            $this->error('Exception while fetching data: '.$e->getMessage());

            return;
        }

        foreach ($rows as $row) {

            $sku = trim($row['childSku'] ?? '');
            if (! $sku) {
                continue;
            }

            TiktokSheet::updateOrCreate(
                ['sku' => $sku],
                [
                    'price' => $this->toDecimalOrNull($row['price'] ?? null),
                    'l30' => $this->toIntOrNull($row['l30'] ?? null),
                    'l60' => $this->toIntOrNull($row['l60'] ?? null),
                    'views' => $this->toDecimalOrNull($row['views'] ?? null),
                ]
            );
        }

        $this->info('✅ TikTok sheet synced successfully!');

        $this->fetchShopifyTiktokData();
    }

    private function fetchShopifyTiktokData()
    {
        $this->info('Fetching Shopify TikTok L30/L60 (robust mode)...');

        // Use UTC everywhere for consistent comparisons
        $now = Carbon::now('UTC');
        $sixtyDaysAgo = $now->copy()->subDays(60);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $skuCountsL60 = [];
        $skuCountsL30 = [];
        $skuPrices = []; // ['SKU' => ['price' => float, 'date' => Carbon]]

        $baseUrl = 'https://'.env('SHOPIFY_STORE_URL').'/admin/api/2024-10/orders.json';

        // initial params: use created_at_min to restrict to last 60 days (server side)
        $params = [
            'status' => 'any',
            'limit' => 250,
            'created_at_min' => $sixtyDaysAgo->toIsoString(),
            'created_at_max' => $now->toIsoString(),
        ];

        // Start with first URL
        $nextUrl = $baseUrl.'?'.http_build_query($params);
        $page = 1;

        $totalOrdersSeen = 0;
        $tiktokOrdersSeen = 0;

        while ($nextUrl) {
            $this->info("Requesting page {$page} → {$nextUrl}");

            // perform request with retry/backoff
            $response = $this->shopifyGetWithRetry($nextUrl, 5);

            if (! $response) {
                $this->error("Failed all retries for {$nextUrl}");
                break;
            }

            // handle rate-limit status just in case
            if ($response->status() == 429) {
                $this->warn("Rate limited on page {$page}; sleeping 2s and retrying");
                sleep(2);

                continue;
            }

            if ($response->failed()) {
                $this->error('Shopify request failed: '.$response->body());
                break;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];

            $this->info("Page {$page} fetched: ".count($orders).' orders');
            $totalOrdersSeen += count($orders);

            foreach ($orders as $order) {
                // parse created_at in UTC
                try {
                    $createdAt = Carbon::parse($order['created_at'])->setTimezone('UTC');
                } catch (\Exception $e) {
                    // skip malformed dates
                    $this->warn('Skipping order with invalid date: '.($order['id'] ?? 'unknown'));

                    continue;
                }

                // More robust TikTok detection:
                // - source_name contains 'tiktok'
                // - OR app_id matches env TIKTOK_APP_ID (if set)
                // - OR source_identifier contains 'tiktok'
                // - OR tags contain 'tiktok'
                $sourceName = strtolower($order['source_name'] ?? '');
                $sourceIdentifier = strtolower($order['source_identifier'] ?? '');
                $appId = $order['app_id'] ?? null;
                $envTiktokAppId = env('TIKTOK_APP_ID');
                
                // Handle tags - can be array or string
                $tagsRaw = $order['tags'] ?? null;
                $tags = '';
                if (is_array($tagsRaw)) {
                    $tags = strtolower(implode(' ', $tagsRaw));
                } elseif (is_string($tagsRaw)) {
                    $tags = strtolower($tagsRaw);
                }

                $isTiktok = false;
                if ($sourceName && str_contains($sourceName, 'tiktok')) {
                    $isTiktok = true;
                } elseif ($sourceIdentifier && str_contains($sourceIdentifier, 'tiktok')) {
                    $isTiktok = true;
                } elseif ($tags && str_contains($tags, 'tiktok')) {
                    $isTiktok = true;
                } elseif ($envTiktokAppId && $appId && (string) $appId === (string) $envTiktokAppId) {
                    $isTiktok = true;
                }

                if (! $isTiktok) {
                    continue;
                }

                $tiktokOrdersSeen++;

                // iterate line items
                foreach ($order['line_items'] as $item) {
                    $sku = trim($item['sku'] ?? '');
                    if (! $sku) {
                        continue;
                    }

                    // Normalize SKU: trim, normalize spaces (multiple spaces -> single space), uppercase
                    $sku = preg_replace('/\s+/', ' ', trim($sku));
                    $sku = strtoupper($sku);

                    // Prefer `quantity` (official) then fallback to `current_quantity`
                    $quantity = 0;
                    if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                        $quantity = (int) $item['quantity'];
                    } elseif (isset($item['current_quantity']) && is_numeric($item['current_quantity'])) {
                        $quantity = (int) $item['current_quantity'];
                    }

                    // Skip if quantity is 0 or negative
                    if ($quantity <= 0) {
                        continue;
                    }

                    // price might be string — cast to float
                    $price = isset($item['price']) ? (float) $item['price'] : null;

                    if ($price === null || $price <= 0) {
                        continue;
                    }

                    // Count L60 / L30 based on createdAt (both in UTC)
                    // Note: Orders in last 30 days should be counted in both L30 and L60
                    if ($createdAt->greaterThanOrEqualTo($sixtyDaysAgo)) {
                        $skuCountsL60[$sku] = ($skuCountsL60[$sku] ?? 0) + $quantity;
                    }

                    if ($createdAt->greaterThanOrEqualTo($thirtyDaysAgo)) {
                        $skuCountsL30[$sku] = ($skuCountsL30[$sku] ?? 0) + $quantity;
                    }

                    // Track latest price by createdAt (prefer most recent)
                    if ($price !== null && $price > 0) {
                        if (! isset($skuPrices[$sku]) || $createdAt->greaterThan($skuPrices[$sku]['date'])) {
                            $skuPrices[$sku] = [
                                'price' => $price,
                                'date' => $createdAt,
                            ];
                        }
                    }
                } // end line_items
            } // end orders

            // Next page via Link header
            $linkHeader = $response->header('Link');
            $nextUrl = $this->getNextPageUrl($linkHeader);

            // throttle to respect 2 req/sec limit
            usleep(600000); // 0.6s

            $page++;
        } // end while

        $this->info("Total orders scanned: {$totalOrdersSeen}");
        $this->info("Total TikTok orders scanned: {$tiktokOrdersSeen}");
        $this->info('Unique SKUs L60: '.count($skuCountsL60));
        $this->info('Unique SKUs L30: '.count($skuCountsL30));
        $this->info('SKUs with price found: '.count($skuPrices));
        
        // ---------- LOG SKU-WISE DATA ----------
        $this->info('Logging SKU-wise Shopify TikTok data...');
        
        // Merge all SKUs
        $allSkus = array_unique(array_merge(array_keys($skuCountsL60), array_keys($skuCountsL30), array_keys($skuPrices)));
        
        // Sort SKUs for better readability
        sort($allSkus);
        
        // Prepare log data
        $logData = [];
        $logData[] = "=== Shopify TikTok SKU-wise Orders Data (L30/L60) ===";
        $logData[] = "Date: ".Carbon::now()->toDateTimeString();
        $logData[] = "Total Orders Scanned: {$totalOrdersSeen}";
        $logData[] = "Total TikTok Orders: {$tiktokOrdersSeen}";
        $logData[] = "Total Unique SKUs: ".count($allSkus);
        $logData[] = "";
        $logData[] = "SKU | L30 | L60 | Price";
        $logData[] = str_repeat('-', 80);
        
        foreach ($allSkus as $sku) {
            $l30 = $skuCountsL30[$sku] ?? 0;
            $l60 = $skuCountsL60[$sku] ?? 0;
            $price = isset($skuPrices[$sku]) ? $skuPrices[$sku]['price'] : 'N/A';
            
            $logData[] = sprintf("%-40s | %5d | %5d | %s", $sku, $l30, $l60, $price);
            
            // Also output to console for important SKUs (non-zero counts)
            if ($l30 > 0 || $l60 > 0) {
                $this->line("SKU: {$sku} | L30: {$l30} | L60: {$l60} | Price: {$price}");
            }
        }
        
        $logData[] = "";
        $logData[] = "=== End of SKU Data ===";
        
        // Write to log file
        $logMessage = implode("\n", $logData);
        Log::info($logMessage);
        
        // Also write to a dedicated file for easier access
        $logFile = storage_path('logs/shopify_tiktok_sku_data.log');
        file_put_contents($logFile, $logMessage."\n\n", FILE_APPEND);
        
        $this->info("SKU data logged to: {$logFile}");
        
        // Debug: Check specific SKU if mentioned
        $debugSku = 'SP 12120 8OHMS';
        $normalizedDebugSku = strtoupper(preg_replace('/\s+/', ' ', trim($debugSku)));
        if (isset($skuCountsL30[$normalizedDebugSku])) {
            $this->info("DEBUG: Found SKU '{$debugSku}' (normalized: '{$normalizedDebugSku}') with L30: ".($skuCountsL30[$normalizedDebugSku] ?? 0).", L60: ".($skuCountsL60[$normalizedDebugSku] ?? 0));
        } else {
            $this->warn("DEBUG: SKU '{$debugSku}' (normalized: '{$normalizedDebugSku}') NOT found in counts");
        }

        // ---------- DB UPDATES ----------
        // Merge all SKUs that need to be updated (from counts or prices)
        $allSkusToUpdate = array_unique(array_merge(array_keys($skuCountsL60), array_keys($skuCountsL30), array_keys($skuPrices)));
        
        // Get all SKUs from database and create a normalized map (normalized SKU -> actual DB SKU)
        $allDbSkus = TiktokSheet::pluck('sku')->toArray();
        $skuNormalizedMap = [];
        foreach ($allDbSkus as $dbSku) {
            $normalized = strtoupper(preg_replace('/\s+/', ' ', trim($dbSku)));
            $skuNormalizedMap[$normalized] = $dbSku; // Store original SKU for exact update
        }
        
        $updatedCount = 0;
        $notFoundSkus = [];
        
        foreach ($allSkusToUpdate as $sku) {
            $updateData = [];
            
            // Update price if available
            if (isset($skuPrices[$sku])) {
                $updateData['shopify_tiktok_price'] = $skuPrices[$sku]['price'];
            }
            
            // Update L60 count (always set, even if 0)
            $updateData['shopify_tiktokl60'] = $skuCountsL60[$sku] ?? 0;
            
            // Update L30 count (always set, even if 0)
            $updateData['shopify_tiktokl30'] = $skuCountsL30[$sku] ?? 0;
            
            // Normalize SKU for matching (already normalized in processing, but ensure consistency)
            $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
            
            // Find matching DB SKU using normalized map
            if (isset($skuNormalizedMap[$normalizedSku])) {
                // Use exact DB SKU for update
                $dbSku = $skuNormalizedMap[$normalizedSku];
                $affected = TiktokSheet::where('sku', $dbSku)->update($updateData);
                
                if ($affected > 0) {
                    $updatedCount++;
                }
            } else {
                // SKU not found in database - log for debugging
                $notFoundSkus[] = $sku;
            }
        }
        
        if (!empty($notFoundSkus)) {
            $this->warn('SKUs not found in database (may need to be added first): '.implode(', ', array_slice($notFoundSkus, 0, 10)));
            if (count($notFoundSkus) > 10) {
                $this->warn('... and '. (count($notFoundSkus) - 10) .' more');
            }
        }

        $this->info("Updated {$updatedCount} SKUs with Shopify TikTok data.");
        $this->info('✅ Shopify TikTok L30/L60 sync completed.');
    }

    private function shopifyGetWithRetry(string $url, int $maxAttempts = 5)
    {
        $attempt = 0;
        $delay = 500000; // microseconds initial delay (0.5s)

        while ($attempt < $maxAttempts) {
            try {
                $attempt++;
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                ])->get($url);

                // If 429 — wait and retry
                if ($response->status() == 429) {
                    usleep($delay * 3); // longer backoff on 429
                    $delay *= 2;

                    continue;
                }

                // For 500-range errors, do exponential backoff and retry
                if ($response->serverError()) {
                    usleep($delay);
                    $delay *= 2;

                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                // network/timeout issues — backoff and retry
                usleep($delay);
                $delay *= 2;

                continue;
            }
        }

        return null;
    }

    private function getNextPageUrl($linkHeader)
    {
        if (! $linkHeader) {
            return null;
        }

        // Link header format: <https://...>; rel="next", <https://...>; rel="previous"
        $links = explode(',', $linkHeader);
        foreach ($links as $link) {
            if (strpos($link, 'rel="next"') !== false) {
                preg_match('/<([^>]+)>/', $link, $matches);

                return $matches[1] ?? null;
            }
        }

        return null;
    }

    private function toDecimalOrNull($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (string) $value; // Keep as string to preserve exact decimal representation
    }

    private function toIntOrNull($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = str_replace(',', '', $value);

        return is_numeric($value) ? (int) $value : null;
    }

}
