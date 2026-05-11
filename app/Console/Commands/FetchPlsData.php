<?php

namespace App\Console\Commands;

use App\Models\PLSProduct;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPlsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-pls-data';

    protected $description = 'Fetch PLS sales data (L30/L60) for ALL catalog products - truncates and refreshes pls_products table';

    public function handle()
    {
        $shopUrl  = config('services.prolightsounds.domain');
        $token    = config('services.prolightsounds.password') ?? config('services.prolightsounds.access_token');
        $version  = "2025-01";

        // Validate credentials
        if (empty($shopUrl) || empty($token)) {
            $this->error("ProLightSounds Shopify credentials not configured in .env file");
            Log::error("ProLightSounds Shopify credentials missing");
            return 1;
        }

        $this->info("Step 1: Loading ALL products from catalog...");
        
        // Load ALL SKUs from catalog (including those without sales)
        $catalogProducts = DB::table('shopify_catalog_variants')
            ->where('store', 'pls')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->select('sku', DB::raw('MAX(price) as price'))
            ->groupBy('sku')
            ->get()
            ->keyBy('sku')
            ->map(function($item) {
                return [
                    'price' => $item->price ?? 0,
                    'l30' => 0,
                    'l60' => 0,
                ];
            })
            ->toArray();

        $this->info("Loaded " . count($catalogProducts) . " SKUs from catalog (ALL products including those without sales)");

        $this->info("Step 2: Fetching sales data for L30/L60 calculation...");

        // --- Date ranges ---
        $now = Carbon::now('America/New_York');

        $startL30 = $now->copy()->subMonth()->startOfMonth();
        $endL30   = $now->copy()->subMonth()->endOfMonth();

        $startL60 = $now->copy()->subMonths(2)->startOfMonth();
        $endL60   = $now->copy()->subMonths(2)->endOfMonth();

        // Fetch orders only from last 90 days (to cover L30 + L60 safely)
        $createdAtMin = $now->copy()->subMonths(3)->toIso8601String();

        $requestBase = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json',
        ]);

        if (config('filesystems.default') === 'local' || env('FILESYSTEM_DRIVER') === 'local') {
            $requestBase = $requestBase->withoutVerifying();
        }

        $domain = preg_replace('#^https?://#', '', $shopUrl);
        $domain = rtrim($domain, '/');
        
        $url = "https://{$domain}/admin/api/{$version}/orders.json";
        $pageInfo = null;

        do {
            $queryParams = [
                'status' => 'any',
                'limit' => 250,
                'created_at_min' => $createdAtMin,
            ];

            if ($pageInfo) {
                $queryParams['page_info'] = $pageInfo;
            }

            $response = $requestBase->timeout(120)->retry(2, 500)->get($url, $queryParams);

            if ($response->failed()) {
                $this->error("Shopify API Error: " . $response->body());
                Log::error("ProLightSounds Shopify API Error: " . $response->body());
                break;
            }

            $orders = $response->json()['orders'] ?? [];

            foreach ($orders as $order) {
                $createdAt = Carbon::parse($order['created_at'], 'America/New_York');

                foreach ($order['line_items'] as $item) {
                    $sku = $item['sku'] ?? null;
                    if (!$sku) continue;

                    // Update sales data for catalog products
                    if (isset($catalogProducts[$sku])) {
                        if ($createdAt->between($startL30, $endL30)) {
                            $catalogProducts[$sku]['l30'] += $item['quantity'] ?? 0;
                        }

                        if ($createdAt->between($startL60, $endL60)) {
                            $catalogProducts[$sku]['l60'] += $item['quantity'] ?? 0;
                        }
                    }
                }
            }

            // Pagination
            $pageInfo = $this->getNextPageInfo($response);
            
            if ($pageInfo) {
                usleep(500000); // Rate limiting
            }
        } while ($pageInfo);

        $this->info("Step 3: Truncating and refreshing pls_products table...");
        PLSProduct::truncate();
        
        $this->info("Step 4: Inserting ALL catalog products (with sales data overlayed)...");
        $insertData = [];
        foreach ($catalogProducts as $sku => $data) {
            $insertData[] = [
                'sku'        => $sku,
                'price'      => $data['price'],
                'p_l30'      => $data['l30'],
                'p_l60'      => $data['l60'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert for better performance
        if (!empty($insertData)) {
            // Insert in chunks of 500 to avoid query size limits
            foreach (array_chunk($insertData, 500) as $chunk) {
                PLSProduct::insert($chunk);
            }
        }

        $this->info("✅ ProLightSounds products synced into pls_products table!");
        $this->info("📦 Total products: " . count($catalogProducts));
        $this->info("📊 Products with L30 sales: " . count(array_filter($catalogProducts, fn($p) => $p['l30'] > 0)));
        $this->info("📊 Products with L60 sales: " . count(array_filter($catalogProducts, fn($p) => $p['l60'] > 0)));
    }

    private function getNextPageInfo($response): ?string
    {
        if ($response->hasHeader('Link') && str_contains($response->header('Link'), 'rel="next"')) {
            $links = explode(',', $response->header('Link'));
            foreach ($links as $link) {
                if (str_contains($link, 'rel="next"')) {
                    preg_match('/<(.*)>; rel="next"/', $link, $matches);
                    if (!empty($matches[1])) {
                        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);
                        return $query['page_info'] ?? null;
                    }
                }
            }
        }
        return null;
    }
}
