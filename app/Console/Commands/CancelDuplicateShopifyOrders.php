<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MarketplaceShopifyStores;
use App\Services\MarketplaceManager\ShopifyDuplicateOrderPlanner;
use App\Services\MarketplaceManager\ShopifyOrderCreateClaim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds marketplace orders that were created twice on Shopify (same order-ref tag) and
 * cancels the extra unfulfilled copy with restock, keeping the fulfilled / linked one.
 * Dry run unless --cancel is given.
 */
class CancelDuplicateShopifyOrders extends Command
{
    protected $signature = 'mm:cancel-duplicate-shopify-orders
        {--days=30 : Look at Shopify orders created in the last N days}
        {--store= : Only this Shopify store key (main, 5core, business, prolightsounds)}
        {--cancel : Cancel and restock the duplicates (default is a dry run)}';

    protected $description = 'Cancel + restock duplicate Shopify copies of one marketplace order';

    private const API_VERSION = '2025-01';

    /** Tables holding shopify_order_id that are not marketplace order links. */
    private const SKIP_TABLES = ['shopify_orders', 'reverb_sync_logs', ShopifyOrderCreateClaim::TABLE];

    /** @var list<string>|null */
    private ?array $linkTables = null;

    public function handle(): int
    {
        $days = max(1, min(365, (int) $this->option('days')));
        $cancel = (bool) $this->option('cancel');
        $since = now()->subDays($days)->toIso8601String();

        $found = 0;
        $cancelled = 0;
        $review = 0;

        foreach ($this->stores() as $config) {
            $this->line('Store '.$config['store_key'].' ('.$config['store_url'].'), orders since '.$since);
            $orders = $this->fetchOrders($config, $since);
            if ($orders === null) {
                $this->error('  Could not read orders from this store.');

                continue;
            }

            $groups = [];
            foreach ($orders as $order) {
                if (! empty($order['cancelled_at'])) {
                    continue;
                }
                foreach (ShopifyDuplicateOrderPlanner::groupKeys($order) as $key) {
                    $groups[$key][(string) $order['id']] = $order;
                }
            }

            $handled = [];
            foreach ($groups as $key => $members) {
                $members = array_diff_key($members, $handled);
                if (count($members) < 2) {
                    continue;
                }

                $plan = ShopifyDuplicateOrderPlanner::plan(array_values($members), $this->referencedIds(array_keys($members)));
                $names = implode(', ', array_map(fn ($o) => (string) ($o['name'] ?? $o['id']), $members));

                if ($plan['review'] !== null) {
                    $review++;
                    $this->warn("  REVIEW {$key}: {$names} — {$plan['review']}");
                    Log::warning('mm:cancel-duplicate-shopify-orders: manual review', [
                        'store' => $config['store_key'], 'key' => $key, 'orders' => $names, 'reason' => $plan['review'],
                    ]);

                    continue;
                }
                if ($plan['keeper'] === null || $plan['cancel'] === []) {
                    continue;
                }

                $found++;
                $keeperName = (string) ($members[$plan['keeper']]['name'] ?? $plan['keeper']);
                foreach ($plan['cancel'] as $dupId) {
                    $dupName = (string) ($members[$dupId]['name'] ?? $dupId);
                    if (! $cancel) {
                        $this->line("  DUPLICATE {$key}: would cancel {$dupName}, keep {$keeperName}");

                        continue;
                    }

                    $error = $this->cancelOrder($config, $dupId);
                    if ($error !== null) {
                        $this->error("  {$key}: cancel {$dupName} failed — {$error}");

                        continue;
                    }
                    $moved = $this->repointLocalRows($dupId, $plan['keeper']);
                    ShopifyOrderCreateClaim::repoint($dupId, $plan['keeper']);
                    $cancelled++;
                    $this->info("  {$key}: cancelled + restocked {$dupName}, kept {$keeperName} ({$moved} local rows repointed)");
                    Log::info('mm:cancel-duplicate-shopify-orders: cancelled duplicate', [
                        'store' => $config['store_key'], 'key' => $key,
                        'cancelled' => $dupName, 'cancelled_id' => $dupId,
                        'kept' => $keeperName, 'kept_id' => $plan['keeper'], 'local_rows_repointed' => $moved,
                    ]);
                }
                $handled += array_fill_keys(array_keys($members), true);
            }
        }

        $this->info(($cancel ? "Cancelled {$cancelled}" : "Found {$found}").' duplicate group(s); '.$review.' need manual review.'
            .($cancel ? '' : ' Dry run — add --cancel to cancel and restock.'));

        return self::SUCCESS;
    }

    /**
     * @return list<array{store_url: string, token: string, store_key: string}>
     */
    private function stores(): array
    {
        return MarketplaceShopifyStores::configs((string) $this->option('store'));
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @return list<array<string, mixed>>|null
     */
    private function fetchOrders(array $config, string $since): ?array
    {
        $base = 'https://'.$config['store_url'].'/admin/api/'.self::API_VERSION.'/orders.json';
        $fields = 'id,name,tags,created_at,cancelled_at,fulfillment_status,source_identifier,line_items';
        $query = ['status' => 'any', 'created_at_min' => $since, 'limit' => 250, 'fields' => $fields];
        $orders = [];

        for ($page = 0; $page < 400; $page++) {
            $response = null;
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $response = Http::withHeaders(['X-Shopify-Access-Token' => $config['token']])
                    ->timeout(60)
                    ->get($base, $query);
                if ($response->status() !== 429) {
                    break;
                }
                sleep(max(2, (int) ($response->header('Retry-After') ?: 2)));
            }
            if ($response === null || ! $response->successful()) {
                Log::warning('mm:cancel-duplicate-shopify-orders: order list failed', [
                    'store' => $config['store_url'] ?? '', 'status' => $response?->status(),
                    'body' => mb_substr((string) $response?->body(), 0, 300),
                ]);

                return $orders === [] ? null : $orders;
            }

            foreach ((array) $response->json('orders', []) as $order) {
                if (is_array($order) && ! empty($order['id'])) {
                    $orders[] = $order;
                }
            }

            if (! preg_match('/<([^>]+)>;\s*rel="next"/', (string) $response->header('Link'), $m)) {
                break;
            }
            parse_str((string) parse_url($m[1], PHP_URL_QUERY), $next);
            $query = ['limit' => 250, 'fields' => $fields, 'page_info' => (string) ($next['page_info'] ?? '')];
            usleep(500000);
        }

        return $orders;
    }

    /**
     * Cancel without refund (the marketplace handles the money) and put the stock back.
     *
     * @param  array{store_url: string, token: string}  $config
     */
    private function cancelOrder(array $config, string $orderId): ?string
    {
        $mutation = <<<'GQL'
mutation cancelDuplicate($orderId: ID!) {
  orderCancel(orderId: $orderId, reason: OTHER, refund: false, restock: true, notifyCustomer: false,
    staffNote: "Duplicate Shopify copy of one marketplace order (mm:cancel-duplicate-shopify-orders)") {
    job { id }
    orderCancelUserErrors { field message code }
  }
}
GQL;

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $config['token'],
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://'.$config['store_url'].'/admin/api/'.self::API_VERSION.'/graphql.json', [
                'query' => $mutation,
                'variables' => ['orderId' => 'gid://shopify/Order/'.$orderId],
            ]);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        if (! $response->successful()) {
            return 'HTTP '.$response->status().': '.mb_substr((string) $response->body(), 0, 200);
        }
        $errors = array_merge(
            (array) $response->json('errors', []),
            (array) $response->json('data.orderCancel.orderCancelUserErrors', [])
        );
        if ($errors !== []) {
            return mb_substr(json_encode($errors) ?: 'Shopify error', 0, 300);
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, true>
     */
    private function referencedIds(array $ids): array
    {
        $out = [];
        foreach ($this->linkTables() as $table) {
            try {
                foreach (DB::table($table)->whereIn('shopify_order_id', $ids)->distinct()->pluck('shopify_order_id') as $id) {
                    $out[(string) $id] = true;
                }
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }

    private function repointLocalRows(string $fromId, string $toId): int
    {
        $moved = 0;
        foreach ($this->linkTables() as $table) {
            try {
                $moved += DB::table($table)->where('shopify_order_id', $fromId)->update(['shopify_order_id' => $toId]);
            } catch (\Throwable $e) {
                Log::warning('mm:cancel-duplicate-shopify-orders: repoint failed', [
                    'table' => $table, 'from' => $fromId, 'to' => $toId, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $moved;
    }

    /**
     * @return list<string>
     */
    private function linkTables(): array
    {
        if ($this->linkTables !== null) {
            return $this->linkTables;
        }

        try {
            $tables = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('column_name', 'shopify_order_id')
                ->selectRaw('TABLE_NAME as t')
                ->pluck('t')
                ->map(fn ($t) => (string) $t)
                ->all();
        } catch (\Throwable $e) {
            $tables = [];
        }

        return $this->linkTables = array_values(array_filter(
            array_unique($tables),
            fn ($t) => ! in_array($t, self::SKIP_TABLES, true) && ! str_ends_with($t, '_logs')
        ));
    }
}
