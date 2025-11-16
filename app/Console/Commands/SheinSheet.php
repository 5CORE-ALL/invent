<?php

namespace App\Console\Commands;


use App\Http\Controllers\ApiController;
use App\Models\SheinSheetData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SheinSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:shein-sheet';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Shein Product Sheet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ApiController();
        $sheet = $controller->fetchDataFromSheinMasterGoogleSheet();
        $rows = collect($sheet->getData()->data ?? []);

        foreach ($rows as $row) {
            $sku = trim($row->{'(Child) sku'} ?? '');
            if (!$sku) continue;

            SheinSheetData::updateOrCreate(
                ['sku' => $sku],
                [
                    'price'     => $this->toDecimalOrNull($row->{'Main Price'} ?? null),
                    'roi'       => $this->toDecimalOrNull($row->{'ROI'} ?? null),
                    'l30'       => $this->toIntOrNull($row->{'Shein L30'} ?? null),
                    'buy_link'  => trim($row->{'BLink'} ?? ''),
                    's_link'    => trim($row->{'SLink'} ?? ''), 
                    'views_clicks' => $this->toIntOrNull($row->{'Views/clicks'} ?? null),
                    'lmp'       => $this->toDecimalOrNull($row->{'LMP'} ?? null),
                    'link1'    => trim($row->{'Link1'} ?? ''),
                    'link2'    => trim($row->{'Link2'} ?? ''),
                    'link3'    => trim($row->{'Link3'} ?? ''),
                    'link4'    => trim($row->{'Link4'} ?? ''),
                    'link5'    => trim($row->{'Link5'} ?? ''),
                    
                ]
            );
        }

        $this->info('Shein sheet synced successfully!');

        // Fetch L30 and L60 from Shopify for Shein SKUs
        $this->fetchShopifySheinData();
    }

    private function fetchShopifySheinData()
    {
        $this->info('Fetching Shopify data for Shein L30/L60...');

        $now = Carbon::now();
        $sixtyDaysAgo = $now->copy()->subDays(60);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $baseUrl = "https://" . env('SHOPIFY_STORE_URL') . "/admin/api/2024-10/orders.json";
        $params = [
            'status' => 'any',
            'created_at_min' => $sixtyDaysAgo->toISOString(),
            'created_at_max' => $now->toISOString(),
            'limit' => 250 
        ];

        $allOrders = [];
        $page = 1;

        do {
            $url = $baseUrl . '?' . http_build_query($params);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_PASSWORD')
            ])->get($url);

            if ($response->failed()) {
                $this->error('Failed to fetch Shopify orders: ' . $response->body());
                return;
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            $allOrders = array_merge($allOrders, $orders);

            $this->info("Fetched page {$page}: " . count($orders) . " orders");

            // Check for next page
            $linkHeader = $response->header('Link');
            $nextUrl = $this->getNextPageUrl($linkHeader);

            if ($nextUrl) {
                // Extract query params from next URL
                $parsedUrl = parse_url($nextUrl);
                parse_str($parsedUrl['query'], $params);
                $page++;
            } else {
                break;
            }
        } while (true);

        $this->info('Total orders fetched: ' . count($allOrders));

        $skuCountsL60 = [];
        $skuCountsL30 = [];
        $skuPrices = [];
        $totalItems = 0;
        $sheinItems = 0;
        $sheinOrders = 0;
        $totalOrders = count($allOrders);

        foreach ($allOrders as $order) {
            $createdAt = Carbon::parse($order['created_at']);
            $sourceName = $order['source_name'] ?? null;
            
            // Only process Shein orders
            if (strtolower($sourceName) === 'shein') {
                $sheinOrders++;
                
                foreach ($order['line_items'] as $item) {
                    $sku = $item['sku'] ?? null;
                    $totalItems++;
                    
                    if ($sku) {
                        $sheinItems++;
                        $quantity = $item['current_quantity'] ?? 0;
                        $price = $item['price'] ?? 0;

                        if ($createdAt >= $sixtyDaysAgo) {
                            $skuCountsL60[$sku] = ($skuCountsL60[$sku] ?? 0) + $quantity;
                        }

                        if ($createdAt >= $thirtyDaysAgo) {
                            $skuCountsL30[$sku] = ($skuCountsL30[$sku] ?? 0) + $quantity;
                        }

                        // Track the latest price for each SKU
                        if (!isset($skuPrices[$sku]) || $createdAt > $skuPrices[$sku]['date']) {
                            $skuPrices[$sku] = [
                                'price' => $price,
                                'date' => $createdAt
                            ];
                        }
                    }
                }
            }
        }

        $this->info("Total orders processed: {$totalOrders}");
        $this->info("Shein orders found: {$sheinOrders}");
        $this->info("Non-Shein orders filtered: " . ($totalOrders - $sheinOrders));
        $this->info("Total line items in Shein orders: {$totalItems}");
        $this->info("Shein items with SKUs: {$sheinItems}");

        // Update SheinSheetData with the counts and prices - for SKUs that have prices
        foreach ($skuPrices as $sku => $priceData) {
            $updateData = [
                'shopify_price' => $priceData['price'],
            ];

            // Only add L30/L60 if they exist for this SKU
            if (isset($skuCountsL60[$sku])) {
                $updateData['shopify_sheinl60'] = $skuCountsL60[$sku];
                $updateData['shopify_sheinl30'] = $skuCountsL30[$sku] ?? 0;
            }

            SheinSheetData::where('sku', $sku)->update($updateData);
        }

        // Update L30/L60 for all SKUs that have sales data but no prices
        foreach ($skuCountsL60 as $sku => $l60) {
            if (!isset($skuPrices[$sku])) {
                $updateData = [
                    'shopify_sheinl60' => $l60,
                    'shopify_sheinl30' => $skuCountsL30[$sku] ?? 0,
                ];
                SheinSheetData::where('sku', $sku)->update($updateData);
            }
        }

        $this->info('Shopify Shein data updated successfully!');
    }

    private function getNextPageUrl($linkHeader)
    {
        if (!$linkHeader) {
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
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private function toIntOrNull($value)
    {
        return is_numeric($value) ? (int)$value : null;
    }
}
