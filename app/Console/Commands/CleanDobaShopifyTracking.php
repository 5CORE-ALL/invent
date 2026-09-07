<?php

namespace App\Console\Commands;

use App\Models\DobaDailyData;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Support\DobaTrackingNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Strip carrier suffixes from Doba Shopify tracking (1Z…(UPS) → 1Z…).
 *
 *   php artisan doba:clean-shopify-tracking
 */
class CleanDobaShopifyTracking extends Command
{
    protected $signature = 'doba:clean-shopify-tracking
        {--limit=0 : Max Shopify orders to inspect (0 = all)}
        {--sleep-ms=250 : Pause between Shopify API calls}';

    protected $description = 'Remove carrier names from Doba tracking numbers already on Shopify';

    public function handle(VeeqoShopifyFulfillmentService $fulfillment): int
    {
        $ids = $this->dobaShopifyOrderIds();
        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }

        $total = count($ids);
        $this->info("Doba Shopify orders to inspect: {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }

        $sleepMs = max(0, (int) $this->option('sleep-ms'));
        $updated = 0;
        $clean = 0;
        $failed = 0;

        foreach ($ids as $shopifyOrderId) {
            $result = $fulfillment->cleanExistingDobaShopifyTracking($shopifyOrderId);
            if (! empty($result['updated'])) {
                $updated++;
                $this->line("  #{$shopifyOrderId} ".$result['message']);
                $this->syncLocalDobaTracking($shopifyOrderId, (string) ($result['tracking'] ?? ''));
            } elseif (! empty($result['success'])) {
                $clean++;
            } else {
                $failed++;
                $this->warn("  #{$shopifyOrderId} ".$result['message']);
            }
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->info("Updated {$updated}, already clean {$clean}, failed {$failed}.");

        return $failed > 0 && $updated === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function dobaShopifyOrderIds(): array
    {
        $ids = [];

        if (Schema::hasTable('doba_daily_data') && Schema::hasColumn('doba_daily_data', 'shopify_order_id')) {
            $q = DobaDailyData::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '');
            if (Schema::hasColumn('doba_daily_data', 'tracking_number')) {
                $q->where(function ($inner) {
                    $inner->where('tracking_number', 'like', '%(%')
                        ->orWhere('tracking_number', 'like', '%UPS%')
                        ->orWhere('tracking_number', 'like', '% %');
                });
            }
            foreach ($q->pluck('shopify_order_id') as $id) {
                $id = preg_replace('/\D+/', '', (string) $id) ?: trim((string) $id);
                if ($id !== '') {
                    $ids[$id] = $id;
                }
            }
        }

        if (Schema::hasTable('shopify_raw_orders')) {
            $query = DB::table('shopify_raw_orders')->whereNotNull('order_id')->where('order_id', '>', 0);
            $query->where(function ($q) {
                if (Schema::hasColumn('shopify_raw_orders', 'source_name')) {
                    $q->orWhere('source_name', 'like', '%doba%')
                        ->orWhere('source_name', '145019994113');
                }
                if (Schema::hasColumn('shopify_raw_orders', 'tags')) {
                    $q->orWhere('tags', 'like', '%doba%');
                }
                if (Schema::hasColumn('shopify_raw_orders', 'raw_payload')) {
                    $q->orWhere('raw_payload', 'like', '%Doba Order%');
                }
            });
            if (Schema::hasColumn('shopify_raw_orders', 'tracking_number')) {
                $query->where(function ($q) {
                    $q->where('tracking_number', 'like', '%(%')
                        ->orWhere('tracking_number', 'like', '%UPS%');
                });
            }
            foreach ($query->pluck('order_id') as $id) {
                $id = preg_replace('/\D+/', '', (string) $id);
                if ($id !== '') {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    protected function syncLocalDobaTracking(string $shopifyOrderId, string $tracking): void
    {
        $tracking = DobaTrackingNumber::sanitize($tracking);
        if ($tracking === '' || ! Schema::hasTable('doba_daily_data') || ! Schema::hasColumn('doba_daily_data', 'tracking_number')) {
            return;
        }

        $sid = preg_replace('/\D+/', '', $shopifyOrderId);
        DobaDailyData::query()
            ->where(function ($q) use ($shopifyOrderId, $sid) {
                $q->where('shopify_order_id', $shopifyOrderId);
                if ($sid !== '') {
                    $q->orWhere('shopify_order_id', $sid)
                        ->orWhere('shopify_order_id', 'like', '%'.$sid);
                }
            })
            ->update(['tracking_number' => $tracking]);
    }
}
