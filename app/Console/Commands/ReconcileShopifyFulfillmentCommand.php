<?php

namespace App\Console\Commands;

use App\Models\OrderFulfillmentTracking;
use App\Services\MarketplaceManager\MarketplaceShopifyStores;
use App\Services\MarketplaceManager\ShopifyDuplicateOrderPlanner;
use App\Services\MarketplaceManager\ShopifyRestClient;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Starts from Shopify: every open, unfulfilled marketplace order is matched to the tracking the
 * Order Fulfillment page has for it and fulfilled with that number.
 *
 * order-fulfillment:push-tracking works from our tracking rows and stops at the first answer it
 * believes ("done", "already has tracking", linked to another copy), so a Shopify order it got
 * wrong once stayed unfulfilled for good. This sweep checks Shopify's own list instead.
 */
class ReconcileShopifyFulfillmentCommand extends Command
{
    protected $signature = 'order-fulfillment:reconcile-shopify
        {--days=14 : Shopify orders created in the last N days}
        {--limit=250 : Most orders to fulfil per run}
        {--budget=1500 : Seconds this run may spend}
        {--store= : Only this Shopify store key}
        {--order= : Only this Shopify order name (#348462) or marketplace order id}
        {--dry-run : Report only; nothing is written to Shopify}';

    protected $description = 'Fulfil unfulfilled Shopify marketplace orders that already have tracking on /order-fulfillment';

    private const API_VERSION = '2025-01';

    public function handle(VeeqoShopifyFulfillmentService $labels): int
    {
        @set_time_limit(0);
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            $this->error('order_fulfillment_trackings table missing.');

            return self::FAILURE;
        }
        OrderFulfillmentShopifyPushService::ensureColumns();

        $days = max(1, min(60, (int) $this->option('days')));
        $limit = max(1, (int) $this->option('limit'));
        $deadline = microtime(true) + max(60, (int) $this->option('budget'));
        $dryRun = (bool) $this->option('dry-run');
        $onlyOrder = ltrim(trim((string) $this->option('order')), '#');
        $since = now()->subDays($days)->toIso8601String();

        $counts = [];
        $problems = [];
        $attempted = 0;

        foreach (MarketplaceShopifyStores::configs((string) $this->option('store')) as $config) {
            $orders = $this->unfulfilledOrders($config, $since);
            if ($orders === null) {
                $this->error('Could not list Shopify orders for '.$config['store_url']);

                continue;
            }
            $this->line($config['store_key'].': '.count($orders).' open unfulfilled orders since '.$since);

            foreach ($orders as $order) {
                if ($attempted >= $limit || microtime(true) >= $deadline) {
                    break 2;
                }
                $refs = ShopifyDuplicateOrderPlanner::orderRefs($order);
                if ($onlyOrder !== '' && ! $this->matchesFilter($order, $refs, $onlyOrder)) {
                    continue;
                }
                if ($refs === []) {
                    continue;
                }

                $result = $this->reconcileOrder($labels, $config, $order, $refs, $dryRun);
                $counts[$result['outcome']] = ($counts[$result['outcome']] ?? 0) + 1;
                if ($result['attempted']) {
                    $attempted++;
                }
                if ($result['outcome'] !== 'fulfilled' && $result['outcome'] !== 'no_tracking_in_app') {
                    $problems[] = [(string) ($order['name'] ?? $order['id']), $result['slug'], $result['ref'], $result['tracking'], $result['outcome'], mb_strimwidth($result['message'], 0, 80, '…')];
                }
                if ($dryRun || $onlyOrder !== '') {
                    $this->line(sprintf('  %s %s-%s %s → %s %s', $order['name'] ?? $order['id'], $result['slug'], $result['ref'], $result['tracking'], $result['outcome'], $result['message']));
                }
            }
        }

        if ($problems !== [] && ! $dryRun && $onlyOrder === '') {
            $this->table(['Shopify', 'Marketplace', 'Order', 'Tracking', 'Result', 'Detail'], array_slice($problems, 0, 60));
        }
        ksort($counts);
        $summary = implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($counts), $counts));
        $this->info(($dryRun ? 'DRY RUN: ' : 'OK: ').($summary !== '' ? $summary : 'nothing to do').'.');
        Log::info('order-fulfillment:reconcile-shopify', ['counts' => $counts, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    /**
     * @param  array{store_url: string, token: string, store_key: string}  $config
     * @param  array<string, mixed>  $order
     * @param  list<array{slug: string, ref: string}>  $refs
     * @return array{outcome: string, message: string, slug: string, ref: string, tracking: string, attempted: bool}
     */
    private function reconcileOrder(VeeqoShopifyFulfillmentService $labels, array $config, array $order, array $refs, bool $dryRun): array
    {
        $shopifyId = (string) $order['id'];
        $first = $refs[0];
        $out = ['outcome' => 'no_tracking_in_app', 'message' => '', 'slug' => $first['slug'], 'ref' => $first['ref'], 'tracking' => '', 'attempted' => false];

        $row = null;
        foreach ($refs as $ref) {
            if (in_array($ref['slug'], OrderFulfillmentShopifyPushService::EXCLUDED_SLUGS, true)) {
                $out['outcome'] = 'excluded_marketplace';
                $out['slug'] = $ref['slug'];

                return $out;
            }
            $row = OrderFulfillmentTracking::query()
                ->where('mm_slug', $ref['slug'])
                ->whereIn('order_id', [$ref['ref'], '#'.$ref['ref']])
                ->whereNotNull('tracking_number')
                ->where('tracking_number', '!=', '')
                ->whereIn('source', OrderFulfillmentShopifyPushService::PUSHABLE_SOURCES)
                ->orderByRaw("source = 'manual' DESC")
                ->orderByDesc('checked_at')
                ->first();
            if ($row !== null) {
                $out['slug'] = $ref['slug'];
                $out['ref'] = $ref['ref'];
                break;
            }
        }
        if ($row === null) {
            return $out;
        }

        $tracking = strtoupper(trim((string) $row->tracking_number));
        $carrier = trim((string) ($row->carrier ?? ''));
        $out['tracking'] = $tracking;
        $slug = $out['slug'];
        $localId = preg_match('/^'.preg_quote($slug, '/').'-(\d+)(?:-|$)/', (string) $row->row_key, $m) ? (int) $m[1] : 0;

        $ctx = null;
        if ($localId > 0) {
            try {
                $ctx = $labels->contextForMarketplaceOrder($slug, $localId);
            } catch (\Throwable $e) {
                $ctx = null;
            }
        }

        $linked = trim((string) ($ctx['shopify_order_id'] ?? ''));
        if ($linked !== '' && $linked !== $shopifyId && ! str_starts_with($linked, 'manual')) {
            $other = $this->linkedCopyState($config, $linked);
            if ($other === 'open') {
                $out['outcome'] = 'duplicate_copy';
                $out['message'] = 'Marketplace order is linked to Shopify '.$linked.'; this copy is left for mm:cancel-duplicate-shopify-orders.';

                return $out;
            }
        }

        if ($dryRun) {
            $out['outcome'] = 'would_fulfil';
            $out['message'] = 'with '.$tracking.($carrier !== '' ? ' ('.$carrier.')' : '');

            return $out;
        }

        $out['attempted'] = true;
        $marketplaceIds = is_array($ctx['marketplace_order_ids'] ?? null) && $ctx['marketplace_order_ids'] !== []
            ? $ctx['marketplace_order_ids']
            : [$out['ref']];
        try {
            $result = $labels->fulfillShopifyFromLabels(
                $shopifyId,
                $config,
                (array) ($ctx['refs'] ?? [$out['ref']]),
                ['tracking' => $tracking, 'carrier' => $carrier !== '' ? $carrier : 'Other'],
                (string) ($row->sku ?: ($ctx['sku'] ?? '')),
                $marketplaceIds,
                $slug
            );
        } catch (\Throwable $e) {
            $out['outcome'] = 'error';
            $out['message'] = $e->getMessage();

            return $out;
        }

        $action = (string) ($result['action'] ?? '');
        $out['message'] = (string) ($result['message'] ?? '');
        if (! in_array($action, ['shopify_fulfilled', 'already_on_shopify'], true)) {
            $out['outcome'] = $action !== '' ? $action : 'failed';

            return $out;
        }

        // Shopify listed this order as unfulfilled, so "already" means another line still waits.
        $out['outcome'] = $action === 'shopify_fulfilled' ? 'fulfilled' : 'already_on_shopify';
        $row->shopify_order_id = $shopifyId;
        $row->shopify_fulfilled_at = now();
        $row->shopify_push_checked_at = now();
        $row->shopify_next_try_at = null;
        $row->shopify_push_message = mb_substr('reconcile: '.$out['message'], 0, 255);
        $row->save();
        if ($localId > 0) {
            try {
                $labels->persistTrackingOntoMarketplaceOrder($slug, $localId, $shopifyId, (string) ($result['tracking'] ?? $tracking), (string) ($result['carrier'] ?? $carrier));
            } catch (\Throwable $e) {
            }
        }

        return $out;
    }

    /**
     * 'open' when the linked Shopify copy exists and is not cancelled.
     *
     * @param  array{store_url: string, token: string}  $config
     */
    private function linkedCopyState(array $config, string $shopifyId): string
    {
        if (! ctype_digit($shopifyId)) {
            return 'unknown';
        }
        try {
            $res = ShopifyRestClient::request('GET', 'https://'.$config['store_url'].'/admin/api/'.self::API_VERSION.'/orders/'.$shopifyId.'.json', $config['token']);
        } catch (\Throwable $e) {
            return 'unknown';
        }
        if ($res->status() === 404) {
            return 'missing';
        }
        if (! $res->successful()) {
            return 'unknown';
        }

        return empty($res->json('order.cancelled_at')) ? 'open' : 'cancelled';
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @return list<array<string, mixed>>|null
     */
    private function unfulfilledOrders(array $config, string $since): ?array
    {
        $base = 'https://'.$config['store_url'].'/admin/api/'.self::API_VERSION.'/orders.json';
        $fields = 'id,name,tags,created_at,cancelled_at,fulfillment_status';
        $query = ['status' => 'open', 'fulfillment_status' => 'unfulfilled', 'created_at_min' => $since, 'limit' => 250, 'fields' => $fields];
        $orders = [];

        for ($page = 0; $page < 100; $page++) {
            try {
                $response = ShopifyRestClient::request('GET', $base, $config['token'], $query, 60, 6);
            } catch (\Throwable $e) {
                return $orders === [] ? null : $orders;
            }
            if (! $response->successful()) {
                return $orders === [] ? null : $orders;
            }
            foreach ((array) $response->json('orders', []) as $order) {
                if (is_array($order) && ! empty($order['id']) && empty($order['cancelled_at'])) {
                    $orders[] = $order;
                }
            }
            if (! preg_match('/<([^>]+)>;\s*rel="next"/', (string) $response->header('Link'), $m)) {
                break;
            }
            parse_str((string) parse_url($m[1], PHP_URL_QUERY), $next);
            $query = ['limit' => 250, 'fields' => $fields, 'page_info' => (string) ($next['page_info'] ?? '')];
        }

        // Oldest first: those have waited longest.
        usort($orders, fn ($a, $b) => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));

        return $orders;
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  list<array{slug: string, ref: string}>  $refs
     */
    private function matchesFilter(array $order, array $refs, string $filter): bool
    {
        if (strcasecmp(ltrim((string) ($order['name'] ?? ''), '#'), $filter) === 0 || (string) $order['id'] === $filter) {
            return true;
        }
        foreach ($refs as $ref) {
            if (strcasecmp($ref['ref'], $filter) === 0) {
                return true;
            }
        }

        return false;
    }
}
