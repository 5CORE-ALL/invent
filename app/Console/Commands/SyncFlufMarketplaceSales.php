<?php

namespace App\Console\Commands;

use App\Services\Support\FlufMarketplaceSalesSync;
use Illuminate\Console\Command;

class SyncFlufMarketplaceSales extends Command
{
    protected $signature = 'fluf:sync-sales
                            {channel=all : vinted, depop or all}
                            {--days=60 : How many days of Shopify orders to read}
                            {--replace-uploads : Also delete CSV-uploaded rows dated on/after the first Shopify sale (they would double count)}
                            {--check-duplicates : Only report sales entered twice on the sheet, change nothing}
                            {--inspect : Only list how Fluf/Vinted/Depop orders look in Shopify, change nothing}
                            {--find= : Only look up an order. Searches buyer name, email, SKU, order number and tags in shopify_raw_orders and both sheets, e.g. --find="neikae"}
                            {--summary : Only print orders/lines/qty per month from shopify_raw_orders vs the sheet, change nothing}';

    protected $description = 'Copy Vinted and Depop sales (Shopify orders created by Fluf) into /vinted/sheet and /depop/sheet.';

    public function handle(FlufMarketplaceSalesSync $sync): int
    {
        $days = max(1, (int) $this->option('days'));

        if ($this->option('inspect')) {
            $rows = $sync->inspect($days);
            if ($rows === []) {
                $this->warn('No Shopify order lines mention fluf, vinted or depop in source_name or tags in the last '.$days.' days.');
                $this->line('Run php artisan shopify:sync-orders --days='.$days.' first if the orders are missing.');

                return self::SUCCESS;
            }
            foreach ($rows as $r) {
                $this->line(sprintf(
                    '[%s] orders=%d lines=%d %s..%s | source_name="%s" | tags="%s"',
                    $r['channel'], $r['orders'], $r['lines'], $r['first'], $r['last'], $r['source_name'], $r['tags']
                ));
            }

            return self::SUCCESS;
        }

        $arg = strtolower(trim((string) $this->argument('channel')));
        $channels = $arg === 'all' ? array_keys(FlufMarketplaceSalesSync::CHANNELS) : [$arg];

        if ($this->option('find') !== null && trim((string) $this->option('find')) !== '') {
            $term = trim((string) $this->option('find'));
            $like = '%'.$term.'%';

            $this->info('== shopify_raw_orders matching "'.$term.'" ==');
            $raw = \Illuminate\Support\Facades\DB::table('shopify_raw_orders')
                ->where(function ($q) use ($like) {
                    foreach (['customer_name', 'customer_email', 'sku', 'order_number', 'tags', 'product_title'] as $col) {
                        $q->orWhere($col, 'like', $like);
                    }
                })
                ->orderByDesc('order_date')
                ->limit(50)
                ->get(['order_number', 'order_date', 'customer_name', 'sku', 'quantity', 'net_sales', 'financial_status', 'source_name', 'tags']);
            if ($raw->isEmpty()) {
                $this->warn('Not in shopify_raw_orders. Run: php artisan shopify:sync-orders --from=2026-04-01');
            }
            foreach ($raw as $r) {
                $this->line(sprintf('%s | %s | %s | %s x%d | $%s | %s | channel=%s | tags="%s"',
                    $r->order_number, $r->order_date, $r->customer_name, $r->sku, $r->quantity, $r->net_sales,
                    $r->financial_status, FlufMarketplaceSalesSync::channelFor($r->source_name, $r->tags) ?? 'none', $r->tags));
            }

            foreach (FlufMarketplaceSalesSync::CHANNELS as $key => $cfg) {
                $this->info('== '.$cfg['label'].' sheet ('.$cfg['table'].') matching "'.$term.'" ==');
                $rows = \Illuminate\Support\Facades\DB::table($cfg['table'])
                    ->where(function ($q) use ($like) {
                        foreach (['buyer', 'sku_code', 'description', 'shopify_order_number'] as $col) {
                            $q->orWhere($col, 'like', $like);
                        }
                    })
                    ->orderByDesc('sale_date')
                    ->limit(50)
                    ->get(['id', 'sale_date', 'buyer', 'sku_code', 'quantity', 'item_price', 'source', 'shopify_order_number']);
                if ($rows->isEmpty()) {
                    $this->line('  none');
                }
                foreach ($rows as $r) {
                    $this->line(sprintf('  #%d %s | %s | %s x%d | $%s | %s | %s',
                        $r->id, $r->sale_date, $r->buyer, $r->sku_code, $r->quantity, $r->item_price, $r->source, $r->shopify_order_number));
                }
            }

            return self::SUCCESS;
        }

        if ($this->option('summary')) {
            foreach ($channels as $channel) {
                $rows = $sync->summary($channel, $days);
                $this->info('== '.$channel.' (last '.$days.' days, shopify_raw_orders vs sheet) ==');
                if ($rows === []) {
                    $this->warn('No matching Shopify order lines.');

                    continue;
                }
                $tot = ['orders' => 0, 'lines' => 0, 'qty' => 0, 'not_a_sale' => 0, 'sheet_rows' => 0];
                foreach ($rows as $r) {
                    $this->line(sprintf('%s  orders=%d lines=%d qty=%d refunded/cancelled_lines=%d | sheet rows=%d',
                        $r['month'], $r['orders'], $r['lines'], $r['qty'], $r['not_a_sale'], $r['sheet_rows']));
                    foreach ($tot as $k => $_) {
                        $tot[$k] += $r[$k];
                    }
                }
                $this->line(sprintf('TOTAL     orders=%d lines=%d qty=%d refunded/cancelled_lines=%d | sheet rows=%d',
                    $tot['orders'], $tot['lines'], $tot['qty'], $tot['not_a_sale'], $tot['sheet_rows']));
            }

            return self::SUCCESS;
        }

        if ($this->option('check-duplicates')) {
            foreach ($channels as $channel) {
                $r = $sync->duplicateReport($channel);
                $this->info('== '.$r['label'].' ==');
                if (isset($r['error'])) {
                    $this->error($r['error']);

                    continue;
                }
                $this->line('Shopify rows: '.$r['shopify_rows'].' | uploaded rows: '.$r['upload_rows']);
                foreach ([
                    'upload_vs_shopify' => 'Uploaded row has the same date + SKU + qty as a Shopify row',
                    'shopify_vs_shopify' => 'Two Shopify orders with the same date + SKU + qty + price',
                    'upload_vs_upload' => 'Uploaded rows repeated (same date + buyer + SKU + price)',
                ] as $key => $title) {
                    $list = $r[$key];
                    $this->line($title.': '.count($list));
                    foreach (array_slice($list, 0, 15) as $line) {
                        $this->line('   '.$line);
                    }
                    if (count($list) > 15) {
                        $this->line('   … and '.(count($list) - 15).' more');
                    }
                }
            }

            return self::SUCCESS;
        }
        $failed = 0;
        foreach ($channels as $channel) {
            try {
                $result = $sync->sync($channel, $days, (bool) $this->option('replace-uploads'));
                $this->info($result['message']);
            } catch (\Throwable $e) {
                $failed++;
                $this->error($channel.': '.$e->getMessage());
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
