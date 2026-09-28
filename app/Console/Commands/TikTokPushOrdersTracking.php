<?php

namespace App\Console\Commands;

use App\Models\TiktokOrder;
use App\Services\MarketplaceManager\TikTokOrderPushService;
use App\Services\MarketplaceManager\TikTokOrderSyncService;
use App\Services\MarketplaceManager\TikTokTrackingSyncService;
use Illuminate\Console\Command;

class TikTokPushOrdersTracking extends Command
{
    protected $signature = 'tiktok:push-orders-tracking
        {--days=90 : Orders created in the last N days}
        {--limit=500 : Maximum TikTok orders in this run}';

    protected $description = 'Create missing TikTok orders on the 5-core Shopify store, then push tracking to TikTok';

    public function handle(TikTokOrderPushService $push, TikTokTrackingSyncService $tracking): int
    {
        @set_time_limit(0);
        $push->deferInventorySync = true;

        $config = $push->shopifyConfig();
        $store = trim((string) ($config['store_url'] ?? ''));
        if ($store === '' || trim((string) ($config['token'] ?? '')) === '') {
            $this->error('Shopify credentials for the 5-core store are missing.');

            return self::FAILURE;
        }
        $this->info('Shopify store: '.$store);

        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $rows = TiktokOrder::query()
            ->where('order_created_at', '>=', now()->subDays($days))
            ->orderByDesc('order_created_at')
            ->orderByDesc('id')
            ->limit(8000)
            ->get();

        $orders = [];
        foreach ($rows as $row) {
            $id = trim((string) $row->order_id);
            if ($id === '' || isset($orders[$id])) {
                continue;
            }
            $status = TikTokOrderSyncService::normalizeOrderStatus((string) ($row->order_status ?? ''));
            if (in_array($status, ['CANCELLED', 'CANCELED'], true)) {
                continue;
            }
            $orders[$id] = $row;
            if (count($orders) >= $limit) {
                break;
            }
        }

        $this->info('TikTok orders to process: '.count($orders));

        $created = 0;
        $already = 0;
        $shopifyFailed = 0;
        $trackingPushed = 0;
        $trackingSkipped = 0;
        $n = 0;

        foreach ($orders as $orderId => $line) {
            $n++;
            $this->line("[{$n}/".count($orders)."] {$orderId}");
            $hadShopify = trim((string) ($line->shopify_order_id ?? '')) !== '';
            $shopifyId = $hadShopify ? (string) $line->shopify_order_id : (string) ($push->importToShopify($line) ?? '');
            if ($shopifyId === '') {
                $shopifyFailed++;
                $this->error('  Shopify failed: '.($push->lastFailureReason ?: 'no order id'));

                continue;
            }
            if ($hadShopify) {
                $already++;
                $this->line('  Shopify already #'.$shopifyId);
            } else {
                $created++;
                $note = $push->lastDuplicateLinkMessage ? ' linked existing' : ' created';
                $this->info('  Shopify #'.$shopifyId.' ('.$note.')');
            }

            $fresh = $line->fresh() ?? $line;
            $fresh->shopify_order_id = $shopifyId;
            $result = $tracking->pushTrackingForOrder($fresh);
            $message = (string) ($result['message'] ?? '');
            if (! empty($result['success']) && empty($result['skipped'])) {
                $trackingPushed++;
                $this->info('  TikTok: '.$message);
            } else {
                $trackingSkipped++;
                $this->warn('  TikTok: '.($message !== '' ? $message : 'not pushed'));
            }
        }

        $this->newLine();
        $this->info("Done. Shopify created {$created}, already there {$already}, failed {$shopifyFailed}.");
        $this->info("TikTok tracking pushed {$trackingPushed}, not pushed {$trackingSkipped}.");
        $this->line('In Shopify, search the tag tiktok- and the TikTok order id. Run this command again for the next batch.');

        return ($created === 0 && $already === 0 && $shopifyFailed > 0) ? self::FAILURE : self::SUCCESS;
    }
}
