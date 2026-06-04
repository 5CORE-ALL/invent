<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Shopify orders from the Shopify Admin API and upsert them
 * into inventory_db.shopify_raw_orders (the main inventory database).
 *
 * Every field is stored — including discount_codes, discount_amount, net_sales,
 * fulfillment/tracking details, customer info, and shipping address.
 *
 * Usage examples:
 *   php artisan shopify:sync-orders               # last 30 days (default)
 *   php artisan shopify:sync-orders --days=365    # last year
 *   php artisan shopify:sync-orders --from=2025-01-01 --to=2025-12-31
 *   php artisan shopify:sync-orders --batch=50    # DB upsert batch size
 */
class SyncShopifyOrdersToInventory extends Command
{
    protected $signature = 'shopify:sync-orders
                            {--days=30          : Number of days back to fetch when no --from is given}
                            {--from=            : Start date YYYY-MM-DD (overrides --days)}
                            {--to=              : End date YYYY-MM-DD   (default: today)}
                            {--batch=100        : Number of rows per DB upsert batch}';

    protected $description = 'Sync Shopify orders (with discounts, tags, tracking) into inventory_db.shopify_raw_orders';

    // ── counters ──────────────────────────────────────────────────────────
    private int $fetched  = 0;
    private int $inserted = 0;
    private int $updated  = 0;
    private int $skipped  = 0;   // line items skipped (no SKU / PARENT SKU)

    // ── Shopify credentials ────────────────────────────────────────────────
    private string $storeUrl;
    private string $accessToken;
    private string $apiVersion = '2024-10';

    public function handle(): int
    {
        $this->storeUrl    = $this->normalizeUrl(config('shopify.store_url', ''));
        $this->accessToken = trim((string) config('shopify.access_token', ''));

        if (empty($this->storeUrl) || empty($this->accessToken)) {
            $this->error('Missing SHOPIFY_STORE_URL or SHOPIFY_ACCESS_TOKEN in .env / config/shopify.php');
            return self::FAILURE;
        }

        $fromDate = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->subDays((int) $this->option('days'))->startOfDay();

        $toDate = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("  Shopify → inventory_db.shopify_raw_orders sync");
        $this->info("  Store : {$this->storeUrl}");
        $this->info("  Range : {$fromDate->format('Y-m-d')} → {$toDate->format('Y-m-d')}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!$this->verifyApi()) {
            return self::FAILURE;
        }

        $batchSize = max(1, (int) $this->option('batch'));

        try {
            $this->fetchAndStore($fromDate, $toDate, $batchSize);
        } catch (\Throwable $e) {
            Log::error('SyncShopifyOrdersToInventory failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('Fatal error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->printSummary($fromDate, $toDate);
        return self::SUCCESS;
    }

    // ── API helpers ────────────────────────────────────────────────────────

    private function normalizeUrl(?string $url): string
    {
        if (empty($url)) return '';
        $url = preg_replace('#^https?://#i', '', trim($url));
        return rtrim($url, '/');
    }

    private function httpClient()
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type'           => 'application/json',
        ])->timeout(120);
    }

    private function verifyApi(): bool
    {
        $this->line('Verifying Shopify API connection…');
        try {
            $resp = $this->httpClient()->get(
                "https://{$this->storeUrl}/admin/api/{$this->apiVersion}/orders.json",
                ['limit' => 1, 'status' => 'any']
            );
            if ($resp->successful()) {
                $this->info('✅ API connection OK');
                return true;
            }
            $this->error('❌ API error ' . $resp->status() . ': ' . $resp->body());
        } catch (\Throwable $e) {
            $this->error('❌ Connection failed: ' . $e->getMessage());
        }
        return false;
    }

    private function getNextPageInfo($response): ?string
    {
        $link = $response->header('Link');
        if ($link && preg_match('/<[^>]*[?&]page_info=([^>&]+)[^>]*>;\s*rel=["\']?next["\']?/', $link, $m)) {
            return $m[1];
        }
        return null;
    }

    // ── Fetch loop ─────────────────────────────────────────────────────────

    private function fetchAndStore(Carbon $from, Carbon $to, int $batchSize): void
    {
        $pageInfo  = null;
        $page      = 0;
        $batch     = [];

        do {
            $page++;

            $params = $pageInfo
                ? ['limit' => 250, 'page_info' => $pageInfo]
                : [
                    'limit'          => 250,
                    'status'         => 'any',
                    'created_at_min' => $from->toIso8601String(),
                    'created_at_max' => $to->toIso8601String(),
                ];

            $resp = $this->callWithRetry(
                "https://{$this->storeUrl}/admin/api/{$this->apiVersion}/orders.json",
                $params
            );

            if (!$resp) break;

            $orders = $resp->json()['orders'] ?? [];
            $this->fetched += count($orders);

            $this->line(sprintf(
                '  Page %d | %d orders (total fetched: %d)',
                $page, count($orders), $this->fetched
            ));

            foreach ($orders as $order) {
                $rows = $this->buildRows($order);
                foreach ($rows as $row) {
                    $batch[] = $row;
                    if (count($batch) >= $batchSize) {
                        $this->upsertBatch($batch);
                        $batch = [];
                    }
                }
            }

            $pageInfo = $this->getNextPageInfo($resp);
            if ($pageInfo) usleep(600_000); // 0.6 s — stay within 2 req/s

        } while ($pageInfo);

        // Flush remaining
        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    private function callWithRetry(string $url, array $params, int $maxTries = 5)
    {
        $wait = 1;
        for ($try = 1; $try <= $maxTries; $try++) {
            try {
                $resp = $this->httpClient()->retry(1, 500)->get($url, $params);

                if ($resp->status() === 429) {
                    $this->warn("  Rate-limit (429) — waiting {$wait}s…");
                    sleep($wait);
                    $wait = min($wait * 2, 16);
                    continue;
                }

                if (!$resp->successful()) {
                    $this->error("  HTTP {$resp->status()} on try {$try}: " . substr($resp->body(), 0, 200));
                    if ($try === $maxTries) return null;
                    sleep($wait);
                    $wait *= 2;
                    continue;
                }

                return $resp;

            } catch (\Throwable $e) {
                $this->warn("  Exception try {$try}: " . $e->getMessage());
                if ($try === $maxTries) throw $e;
                sleep($wait);
                $wait *= 2;
            }
        }
        return null;
    }

    // ── Order → rows ───────────────────────────────────────────────────────

    private function buildRows(array $order): array
    {
        $customer    = $order['customer']         ?? [];
        $shipping    = $order['shipping_address'] ?? [];
        $fulfillment = $order['fulfillments'][0]  ?? [];

        $customerName = trim(
            ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')
        );

        // Shipping address (single-line)
        $shippingAddress = implode(', ', array_filter([
            $shipping['address1'] ?? null,
            $shipping['address2'] ?? null,
        ]));

        // Tracking (first fulfillment)
        $trackingCompany = $fulfillment['tracking_company'] ?? null;
        $trackingNumber  = null;
        $trackingUrl     = null;
        if (!empty($fulfillment['tracking_numbers'])) {
            $trackingNumber = implode(', ', (array) $fulfillment['tracking_numbers']);
        } elseif (!empty($fulfillment['tracking_number'])) {
            $trackingNumber = $fulfillment['tracking_number'];
        }
        if (!empty($fulfillment['tracking_urls'])) {
            $trackingUrl = is_array($fulfillment['tracking_urls'])
                ? ($fulfillment['tracking_urls'][0] ?? null)
                : $fulfillment['tracking_urls'];
        } elseif (!empty($fulfillment['tracking_url'])) {
            $trackingUrl = $fulfillment['tracking_url'];
        }

        // Order-level discount codes (comma-separated labels)
        $discountCodes = collect($order['discount_codes'] ?? [])
            ->pluck('code')
            ->filter()
            ->implode(', ');

        // Order-level totals (what the customer actually paid for the whole order)
        // current_total_price = final paid amount (after all discounts + shipping)
        // subtotal_price      = all line items total before shipping/taxes
        $orderTotal    = round((float) ($order['current_total_price'] ?? $order['total_price'] ?? 0), 2);
        $orderSubtotal = round((float) ($order['subtotal_price'] ?? 0), 2);

        $rows = [];
        foreach ($order['line_items'] ?? [] as $line) {
            $sku = trim((string) ($line['sku'] ?? ''));

            // Skip blank SKUs and bundle-parent placeholders
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                $this->skipped++;
                continue;
            }

            $price    = round((float) ($line['price']    ?? 0), 2);
            $quantity = (int)         ($line['quantity'] ?? 0);

            // Per-line discount (sum of all discount_allocations on this line item)
            $discountAmount = 0.0;
            foreach ($line['discount_allocations'] ?? [] as $alloc) {
                $discountAmount += (float) ($alloc['amount'] ?? 0);
            }
            $discountAmount = round($discountAmount, 2);

            $totalAmount = round($price * $quantity, 2);
            $netSales    = round($totalAmount - $discountAmount, 2);

            $rows[] = [
                // keys used for upsert match
                'order_id'           => (int) $order['id'],
                'line_item_id'       => (int) $line['id'],

                // order fields
                'order_number'       => $order['name']               ?? null,
                'product_id'         => $line['product_id']          ?? null,
                'variant_id'         => $line['variant_id']          ?? null,
                'order_date'         => isset($order['created_at'])
                    ? Carbon::parse($order['created_at'])->format('Y-m-d')
                    : null,
                'financial_status'   => $order['financial_status']   ?? null,
                'fulfillment_status' => $order['fulfillment_status'] ?? null,

                // line item fields
                'sku'                => $sku,
                'product_title'      => $line['title']               ?? null,
                'quantity'           => $quantity,
                'price'              => $price,
                'total_amount'       => $totalAmount,

                // discount (per-line)
                'discount_codes'     => $discountCodes ?: null,
                'discount_amount'    => $discountAmount,
                'net_sales'          => $netSales,

                // order-level totals — same for every line item of this order
                'order_total'        => $orderTotal,     // = what Shopify shows as "Total" (e.g. $73.58)
                'order_subtotal'     => $orderSubtotal,  // = subtotal before shipping

                // customer
                'customer_name'      => $customerName  ?: null,
                'customer_email'     => $customer['email']           ?? null,

                // shipping
                'shipping_city'      => $shipping['city']            ?? null,
                'shipping_country'   => $shipping['country']         ?? null,

                // tracking
                'tracking_company'   => $trackingCompany,
                'tracking_number'    => $trackingNumber,
                'tracking_url'       => $trackingUrl,

                // source / meta
                'source_name'        => $order['source_name']        ?? null,
                'tags'               => $order['tags']               ?? null,

                'created_at'         => Carbon::now(),
                'updated_at'         => Carbon::now(),
            ];
        }

        return $rows;
    }

    // ── Upsert batch into apicentral DB ────────────────────────────────────

    private function upsertBatch(array $batch): void
    {
        if (empty($batch)) return;

        // Columns to UPDATE when a duplicate (order_id + line_item_id) is found
        $updateCols = [
            'order_number', 'product_id', 'variant_id', 'order_date',
            'financial_status', 'fulfillment_status',
            'sku', 'product_title', 'quantity', 'price', 'total_amount',
            'discount_codes', 'discount_amount', 'net_sales',
            'order_total', 'order_subtotal',
            'customer_name', 'customer_email',
            'shipping_city', 'shipping_country',
            'tracking_company', 'tracking_number', 'tracking_url',
            'source_name', 'tags',
            'updated_at',
        ];

        // Insert into inventory_db (default connection)
        DB::table('shopify_raw_orders')
            ->upsert($batch, ['order_id', 'line_item_id'], $updateCols);

        $this->inserted += count($batch);
        $this->line(sprintf('    ↳ Upserted batch of %d rows (running total: %d)', count($batch), $this->inserted));
    }

    // ── Summary ────────────────────────────────────────────────────────────

    private function printSummary(Carbon $from, Carbon $to): void
    {
        $total = DB::table('shopify_raw_orders')->count();
        $min   = DB::table('shopify_raw_orders')->min('order_date');
        $max   = DB::table('shopify_raw_orders')->max('order_date');

        $this->info('');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ✅  Sync complete');
        $this->info("  Date range synced : {$from->format('Y-m-d')} → {$to->format('Y-m-d')}");
        $this->info("  Orders fetched    : {$this->fetched}");
        $this->info("  Rows upserted     : {$this->inserted}");
        $this->info("  Line items skipped: {$this->skipped}  (blank SKU / PARENT)");
        $this->info('  ─────────────────────────────────────────────');
        $this->info("  Total rows in DB  : {$total}");
        $this->info("  DB date range     : {$min} → {$max}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        Log::info('SyncShopifyOrdersToInventory completed', [
            'fetched'  => $this->fetched,
            'upserted' => $this->inserted,
            'skipped'  => $this->skipped,
            'db_total' => $total,
        ]);
    }
}
