<?php

namespace App\Console\Commands;

use App\Http\Controllers\Channels\OrderFulfillmentController;
use App\Models\OrderFulfillmentTracking;
use App\Services\MarketplaceManager\MarketplaceShopifyStores;
use App\Services\MarketplaceManager\MarketplaceTrackingOwnership;
use App\Services\MarketplaceManager\ShopifyDuplicateOrderPlanner;
use App\Services\MarketplaceManager\ShopifyGraphqlFulfiller;
use App\Services\MarketplaceManager\VeeqoShopifyFulfillmentService;
use App\Services\MarketplaceManager\WayfairTrackingSyncService;
use App\Services\OrderFulfillment\OrderFulfillmentShopifyPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Starts from Shopify: every open, unfulfilled marketplace order gets the tracking the Order
 * Fulfillment page has for it (looked up first when the page has none yet) and is fulfilled.
 *
 * order-fulfillment:push-tracking works from our tracking rows over the REST API, which other
 * jobs keep at its rate limit, and stops at the first answer it believes ("done", "already has
 * tracking", linked to another copy) — so some Shopify orders stayed unfulfilled for good. This
 * sweep reads Shopify's own list and writes through GraphQL, which has its own budget.
 */
class ReconcileShopifyFulfillmentCommand extends Command
{
    protected $signature = 'order-fulfillment:reconcile-shopify
        {--days=14 : Shopify orders created in the last N days}
        {--limit=300 : Most orders to fulfil per run}
        {--lookups=80 : Most orders to look tracking up for per run}
        {--budget=1500 : Seconds this run may spend}
        {--store= : Only this Shopify store key}
        {--order= : Only this Shopify order name (348462) or marketplace order id}
        {--dry-run : Report only; nothing is written to Shopify}';

    protected $description = 'Fetch missing tracking and fulfil unfulfilled Shopify marketplace orders (GraphQL)';

    public function handle(VeeqoShopifyFulfillmentService $labels, ShopifyGraphqlFulfiller $shopify): int
    {
        @set_time_limit(0);
        if (! Schema::hasTable('order_fulfillment_trackings')) {
            $this->error('order_fulfillment_trackings table missing.');

            return self::FAILURE;
        }
        OrderFulfillmentShopifyPushService::ensureColumns();

        $days = max(1, min(60, (int) $this->option('days')));
        $limit = max(1, (int) $this->option('limit'));
        $maxLookups = max(0, (int) $this->option('lookups'));
        $deadline = microtime(true) + max(60, (int) $this->option('budget'));
        $dryRun = (bool) $this->option('dry-run');
        $onlyOrder = ltrim(trim((string) $this->option('order')), '#');
        $verbose = $dryRun || $onlyOrder !== '' || $this->getOutput()->isVerbose();

        $counts = [];
        $problems = [];
        $attempted = 0;

        foreach (MarketplaceShopifyStores::configs((string) $this->option('store')) as $config) {
            $orders = $shopify->unfulfilledOrders($config, now()->subDays($days));
            if ($orders === null) {
                $this->error('Could not list Shopify orders for '.$config['store_url']);

                continue;
            }
            $this->line($config['store_key'].': '.count($orders).' open unfulfilled orders in the last '.$days.' days');

            $work = [];
            foreach ($orders as $order) {
                $refs = ShopifyDuplicateOrderPlanner::orderRefs($order);
                if ($refs === [] || ($onlyOrder !== '' && ! $this->matchesFilter($order, $refs, $onlyOrder))) {
                    continue;
                }
                $work[] = ['order' => $order, 'refs' => $refs];
            }

            $report = function (array $order, array $result) use (&$counts, &$problems, $verbose): void {
                $counts[$result['outcome']] = ($counts[$result['outcome']] ?? 0) + 1;
                if (! in_array($result['outcome'], ['fulfilled', 'already_on_shopify', 'would_fulfil'], true)) {
                    $problems[] = [(string) ($order['name'] ?: $order['id']), $result['slug'], $result['ref'], $result['tracking'], $result['outcome'], mb_strimwidth($result['message'], 0, 80, '…')];
                }
                if ($verbose) {
                    $this->line(sprintf('  %s %s-%s %s → %s %s', $order['name'] ?: $order['id'], $result['slug'], $result['ref'], $result['tracking'], $result['outcome'], $result['message']));
                }
            };

            // Pass 1 uses the tracking the page already has.
            $needLookup = [];
            foreach ($work as $item) {
                if ($attempted >= $limit || microtime(true) >= $deadline) {
                    break;
                }
                $result = $this->reconcileOrder($labels, $shopify, $config, $item['order'], $item['refs'], $dryRun);
                if ($result['outcome'] === 'no_tracking_in_app') {
                    $needLookup[] = ['item' => $item, 'result' => $result];

                    continue;
                }
                $attempted += $result['attempted'] ? 1 : 0;
                $report($item['order'], $result);
            }

            // Pass 2: fetch tracking (Veeqo, marketplace API, 4Seller) for the rest, then fulfil what was found.
            $found = [];
            $looked = 0;
            if ($needLookup !== [] && ! $dryRun && $maxLookups > 0 && microtime(true) < $deadline) {
                $needLookup = $this->prefetchWayfairLabels($needLookup, $deadline);
                $batch = array_slice($needLookup, 0, $maxLookups);
                $looked = count($batch);
                $found = $this->lookUpTracking(array_map(fn ($n) => $n['result'], $batch), $deadline);
                $this->line('  Looked up tracking for '.$looked.' order(s) with none on the page: '.count($found).' found.');
            }
            foreach ($needLookup as $i => $n) {
                $result = $n['result'];
                if (isset($found[strtolower($result['slug'].'|'.$result['ref'])]) && $attempted < $limit && microtime(true) < $deadline) {
                    $result = $this->reconcileOrder($labels, $shopify, $config, $n['item']['order'], $n['item']['refs'], false);
                    $attempted += $result['attempted'] ? 1 : 0;
                } elseif ($dryRun) {
                    $result['message'] = 'A real run looks tracking up first.';
                } elseif ($i < $looked) {
                    $result['message'] = 'No tracking found in Veeqo, the marketplace or 4Seller yet.';
                } else {
                    $result['message'] = 'Lookup comes in the next run.';
                }
                $report($n['item']['order'], $result);
            }
        }

        if ($problems !== [] && ! $verbose) {
            $this->table(['Shopify', 'Marketplace', 'Order', 'Tracking', 'Result', 'Detail'], array_slice($problems, 0, 80));
        }
        ksort($counts);
        $summary = implode(', ', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($counts), $counts));
        $this->info(($dryRun ? 'DRY RUN: ' : 'OK: ').($summary !== '' ? $summary : 'nothing to do').'.');
        Log::info('order-fulfillment:reconcile-shopify', ['counts' => $counts, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }

    /**
     * Wayfair ships on Wayfair-generated labels, which only Wayfair's label events know about.
     * Fetch them for all waiting Wayfair POs at once and move the hits to the front of the queue.
     *
     * @param  list<array{item: array, result: array}>  $needLookup
     * @return list<array{item: array, result: array}>
     */
    private function prefetchWayfairLabels(array $needLookup, float $deadline): array
    {
        $pos = [];
        foreach ($needLookup as $n) {
            if ($n['result']['slug'] === 'wayfair') {
                $pos[] = $n['result']['ref'];
            }
        }
        if ($pos === []) {
            return $needLookup;
        }
        try {
            $hits = app(WayfairTrackingSyncService::class)->prefetchLabelTracking($pos, min($deadline, microtime(true) + 180));
        } catch (\Throwable $e) {
            Log::warning('order-fulfillment:reconcile-shopify: Wayfair label fetch failed', ['error' => $e->getMessage()]);

            return $needLookup;
        }
        $this->line('  Wayfair labels: '.count($hits).' of '.count($pos).' PO(s) have tracking.');

        usort($needLookup, fn ($a, $b) => (int) isset($hits[strtoupper($b['result']['ref'])]) <=> (int) isset($hits[strtoupper($a['result']['ref'])]));

        return $needLookup;
    }

    /**
     * @param  list<array{slug: string, ref: string}>  $items
     * @return array<string, true>
     */
    private function lookUpTracking(array $items, float $deadline): array
    {
        if ($items === []) {
            return [];
        }
        try {
            return app(OrderFulfillmentController::class)->resolveTrackingNow(
                array_map(fn ($w) => ['mm_slug' => $w['slug'], 'order_id' => $w['ref']], $items),
                (int) max(20, min(900, $deadline - microtime(true) - 60))
            );
        } catch (\Throwable $e) {
            Log::warning('order-fulfillment:reconcile-shopify: tracking lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array{store_url: string, token: string, store_key: string}  $config
     * @param  array{id: string, name: string, tags: string}  $order
     * @param  list<array{slug: string, ref: string}>  $refs
     * @return array{outcome: string, message: string, slug: string, ref: string, tracking: string, attempted: bool}
     */
    private function reconcileOrder(VeeqoShopifyFulfillmentService $labels, ShopifyGraphqlFulfiller $shopify, array $config, array $order, array $refs, bool $dryRun): array
    {
        $shopifyId = (string) $order['id'];
        $out = ['outcome' => 'no_tracking_in_app', 'message' => '', 'slug' => $refs[0]['slug'], 'ref' => $refs[0]['ref'], 'tracking' => '', 'attempted' => false];

        $rows = collect();
        foreach ($refs as $ref) {
            if (in_array($ref['slug'], OrderFulfillmentShopifyPushService::EXCLUDED_SLUGS, true)) {
                $out['outcome'] = 'excluded_marketplace';
                $out['slug'] = $ref['slug'];

                return $out;
            }
            $rows = OrderFulfillmentTracking::query()
                ->where('mm_slug', $ref['slug'])
                ->whereIn('order_id', [$ref['ref'], '#'.$ref['ref']])
                ->whereNotNull('tracking_number')
                ->where('tracking_number', '!=', '')
                ->whereIn('source', OrderFulfillmentShopifyPushService::PUSHABLE_SOURCES)
                ->get();
            if ($rows->isNotEmpty()) {
                $out['slug'] = $ref['slug'];
                $out['ref'] = $ref['ref'];
                break;
            }
        }
        if ($rows->isEmpty()) {
            return $out;
        }
        $slug = $out['slug'];

        if ($labels->autoFulfillBlocked($slug)) {
            $out['outcome'] = 'auto_fulfill_off';
            $out['message'] = 'Automatic Shopify fulfillment is turned off for '.$slug.'.';

            return $out;
        }

        // Tracking → SKUs it covers. A manual number wins over a looked-up one for the same line.
        $byTracking = [];
        $carriers = [];
        foreach ($rows->sortByDesc(fn ($r) => $r->source === 'manual' ? 1 : 0) as $row) {
            $tn = strtoupper((string) preg_replace('/\s+/', '', (string) $row->tracking_number));
            $byTracking[$tn][] = trim((string) $row->sku);
            $carriers[$tn] ??= trim((string) ($row->carrier ?? ''));
        }
        $out['tracking'] = implode(' ', array_keys($byTracking));

        $first = $rows->first();
        $localId = preg_match('/^'.preg_quote($slug, '/').'-(\d+)(?:-|$)/', (string) $first->row_key, $m) ? (int) $m[1] : 0;
        $ctx = null;
        if ($localId > 0) {
            try {
                $ctx = $labels->contextForMarketplaceOrder($slug, $localId);
            } catch (\Throwable $e) {
                $ctx = null;
            }
        }

        $linked = trim((string) ($ctx['shopify_order_id'] ?? ''));
        if ($linked !== '' && $linked !== $shopifyId && ctype_digit($linked)) {
            if (in_array($shopify->orderState($config, $linked), ['open', 'fulfilled'], true)) {
                $out['outcome'] = 'duplicate_copy';
                $out['message'] = 'Marketplace order is linked to Shopify '.$linked.'; this copy is left for mm:cancel-duplicate-shopify-orders.';

                return $out;
            }
        }

        $ownership = app(MarketplaceTrackingOwnership::class);
        foreach (array_keys($byTracking) as $tn) {
            if ($ownership->isWrongFor($tn, $slug, $out['ref'], $shopifyId)) {
                $out['outcome'] = 'tracking_belongs_elsewhere';
                $out['message'] = $tn.' belongs to another marketplace order.';

                return $out;
            }
        }

        if ($dryRun) {
            $out['outcome'] = 'would_fulfil';
            $out['message'] = 'with '.$out['tracking'];

            return $out;
        }

        $out['attempted'] = true;
        $single = count($byTracking) === 1;
        $statuses = [];
        $messages = [];
        foreach ($byTracking as $tn => $skus) {
            $carrier = $carriers[$tn] !== '' ? $carriers[$tn] : 'Other';
            $res = $shopify->fulfil($config, $shopifyId, $tn, $carrier, $single ? null : array_values(array_filter($skus)));
            $statuses[] = $res['status'];
            $messages[] = $res['message'];
            if (in_array($res['status'], ['fulfilled', 'already'], true)) {
                $this->markRowsFulfilled($rows->filter(fn ($r) => strtoupper((string) preg_replace('/\s+/', '', (string) $r->tracking_number)) === $tn), $shopifyId, $res['message']);
                if ($localId > 0) {
                    try {
                        $labels->persistTrackingOntoMarketplaceOrder($slug, $localId, $shopifyId, $tn, $carrier);
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        $out['message'] = implode(' | ', array_unique($messages));
        $out['outcome'] = match (true) {
            in_array('fulfilled', $statuses, true) => 'fulfilled',
            in_array('already', $statuses, true) => 'already_on_shopify',
            default => $statuses[0] ?? 'error',
        };

        return $out;
    }

    private function markRowsFulfilled($rows, string $shopifyId, string $message): void
    {
        foreach ($rows as $row) {
            $row->shopify_order_id = $shopifyId;
            $row->shopify_fulfilled_at = now();
            $row->shopify_push_checked_at = now();
            $row->shopify_next_try_at = null;
            $row->shopify_push_message = mb_substr('reconcile: '.$message, 0, 255);
            $row->save();
        }
    }

    /**
     * @param  array{id: string, name: string}  $order
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
