<?php

namespace App\Console\Commands;

use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\TikTokOrderPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Read-only. TikTok 1 orders imported while the import sent lines without a Shopify
 * variant_id created Shopify orders that never decremented inventory. This lists those
 * lines and the quantity per SKU so the stock can be corrected on purpose.
 * It never writes to Shopify.
 */
class ReportTikTokUntrackedShopifyLines extends Command
{
    protected $signature = 'tiktok:report-untracked-shopify-lines
        {--since=2026-09-29 : Only orders pushed to Shopify on/after this date}
        {--limit=0 : Max Shopify orders to check (0 = all)}
        {--csv= : Also write the per-SKU totals to this CSV file}';

    protected $description = 'Read-only: list TikTok 1 Shopify orders whose lines have no variant (no stock deduction) with qty per SKU';

    public function handle(TikTokOrderPushService $push): int
    {
        @set_time_limit(0);

        $config = $push->shopifyConfig();
        $host = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($host === '' || $token === '') {
            $this->error('Shopify credentials are not configured.');

            return self::FAILURE;
        }

        $since = (string) $this->option('since');
        $ids = TiktokOrder::query()
            ->whereNotNull('shopify_order_id')
            ->where('shopify_order_id', '!=', '')
            ->where('pushed_to_shopify_at', '>=', $since)
            ->select('shopify_order_id')
            ->distinct()
            ->orderBy('shopify_order_id')
            ->pluck('shopify_order_id')
            ->map(static fn ($id) => trim((string) $id))
            ->unique()
            ->values()
            ->all();

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }
        $this->info('Checking '.count($ids).' Shopify order(s) pushed since '.$since.' ...');

        $bySku = [];
        $affectedOrders = 0;
        $failed = 0;

        foreach ($ids as $i => $shopifyId) {
            $order = $this->fetchOrder($host, $token, $shopifyId);
            if ($order === null) {
                $failed++;
                $this->warn("  could not read Shopify order {$shopifyId}");

                continue;
            }
            if (! empty($order['cancelled_at'])) {
                continue;
            }

            $hit = false;
            foreach ($order['line_items'] ?? [] as $line) {
                if (! empty($line['variant_id'])) {
                    continue;
                }
                $sku = trim((string) ($line['sku'] ?? ''));
                if ($sku === '') {
                    $sku = '(no sku) '.trim((string) ($line['title'] ?? ''));
                }
                $qty = (int) ($line['quantity'] ?? 0);
                $bySku[$sku] = ($bySku[$sku] ?? 0) + $qty;
                $hit = true;
                $this->line(sprintf('  %s  %s  qty %d', (string) ($order['name'] ?? $shopifyId), $sku, $qty));
            }
            if ($hit) {
                $affectedOrders++;
            }

            if (($i + 1) % 50 === 0) {
                $this->line('  ... '.($i + 1).' / '.count($ids));
            }
            usleep(550000);
        }

        ksort($bySku);
        $this->newLine();
        $this->info("Orders with untracked lines: {$affectedOrders}; unreadable: {$failed}");
        $rows = [];
        foreach ($bySku as $sku => $qty) {
            $rows[] = [$sku, $qty];
        }
        if ($rows !== []) {
            $this->table(['SKU', 'Qty not deducted on Shopify'], $rows);
        }

        $csv = trim((string) $this->option('csv'));
        if ($csv !== '') {
            $fh = @fopen($csv, 'wb');
            if ($fh) {
                fputcsv($fh, ['sku', 'qty_not_deducted']);
                foreach ($rows as $row) {
                    fputcsv($fh, $row);
                }
                fclose($fh);
                $this->info('CSV written: '.$csv);
            } else {
                $this->error('Could not write '.$csv);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOrder(string $host, string $token, string $shopifyId): ?array
    {
        $url = 'https://'.$host.'/admin/api/2024-01/orders/'.rawurlencode($shopifyId).'.json';
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $res = Http::withHeaders(['X-Shopify-Access-Token' => $token])
                    ->timeout(30)
                    ->get($url, ['fields' => 'id,name,cancelled_at,line_items']);
            } catch (\Throwable $e) {
                return null;
            }
            if ($res->status() === 429) {
                sleep(max(2, $attempt * 2));

                continue;
            }

            return $res->successful() && is_array($res->json('order')) ? $res->json('order') : null;
        }

        return null;
    }
}
