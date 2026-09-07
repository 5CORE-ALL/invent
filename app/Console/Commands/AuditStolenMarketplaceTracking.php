<?php

namespace App\Console\Commands;

use App\Services\ShopifyStoreSelector;
use App\Services\VeeqoApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Find Shopify marketplace copies (last N days) whose tracking belongs to another order.
 *
 *   php artisan marketplace:audit-stolen-tracking --days=15
 */
class AuditStolenMarketplaceTracking extends Command
{
    protected $signature = 'marketplace:audit-stolen-tracking
        {--days=15 : Look back this many days}
        {--skip-veeqo : Do not confirm owners via Veeqo}
        {--json= : Optional path to write JSON}';

    protected $description = 'List Shopify orders whose tracking was copied from a different marketplace/Veeqo order';

    public function handle(ShopifyStoreSelector $stores, VeeqoApiService $veeqo): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);
        $this->info("Auditing Shopify orders created since {$since->toDateTimeString()} ({$days} days).");

        $useVeeqo = ! $this->option('skip-veeqo') && $veeqo->isConfigured();
        if ($useVeeqo) {
            $this->line('Veeqo confirmation is on.');
        }

        $orders = [];
        foreach ($this->storeConfigs($stores) as $config) {
            $storeUrl = (string) $config['store_url'];
            $this->line("  Fetching {$storeUrl} …");
            $chunk = $this->fetchShopifyOrders($config, $since);
            $this->line('    '.count($chunk).' orders');
            foreach ($chunk as $order) {
                $order['_store'] = $storeUrl;
                $orders[] = $order;
            }
        }

        $this->info('Shopify orders fetched: '.count($orders));

        $byTracking = [];
        foreach ($orders as $order) {
            foreach ($this->trackingsFromOrder($order) as $tn) {
                $byTracking[$tn][] = $order;
            }
        }

        $this->line('Indexing local Amazon / Shopify / Doba rows…');
        $amazonRows = Schema::hasTable('amazon_orders')
            ? DB::table('amazon_orders')->get(['amazon_order_id', 'shopify_order_id', 'order_date', 'status'])
            : collect();
        $rawByTracking = [];
        if (Schema::hasTable('shopify_raw_orders') && $byTracking !== []) {
            foreach (array_chunk(array_keys($byTracking), 400) as $chunk) {
                $rows = DB::table('shopify_raw_orders')
                    ->whereIn('tracking_number', $chunk)
                    ->get(['order_id', 'order_number', 'source_name', 'order_date', 'sku', 'tracking_number']);
                foreach ($rows as $row) {
                    $tn = $this->normalizeTracking((string) $row->tracking_number);
                    $rawByTracking[$tn][] = $row;
                }
            }
        }
        $dobaByKey = [];
        if (Schema::hasTable('doba_daily_data')) {
            $dobaRows = DB::table('doba_daily_data')
                ->get(['order_no', 'platform_order_no', 'order_type', 'tracking_number', 'shopify_order_id']);
            foreach ($dobaRows as $row) {
                foreach ([(string) $row->order_no, (string) $row->platform_order_no, (string) $row->shopify_order_id] as $key) {
                    if ($key !== '') {
                        $dobaByKey[$key] = $row;
                    }
                }
            }
        }

        $hits = [];
        foreach ($orders as $order) {
            $trackings = $this->trackingsFromOrder($order);
            if ($trackings === []) {
                continue;
            }
            $channel = $this->channelOf($order);
            $name = ltrim((string) ($order['name'] ?? ''), '#');
            $shopifyId = (string) ($order['id'] ?? '');
            $reasons = [];
            $owners = [];

            foreach ($trackings as $tn) {
                foreach ($byTracking[$tn] ?? [] as $other) {
                    if ((string) ($other['id'] ?? '') === $shopifyId) {
                        continue;
                    }
                    $reasons[] = 'same_tracking_on_other_shopify_order';
                    $owners[] = [
                        'where' => 'shopify',
                        'store' => (string) ($other['_store'] ?? ''),
                        'shopify_name' => (string) ($other['name'] ?? ''),
                        'shopify_id' => (string) ($other['id'] ?? ''),
                        'channel' => $this->channelOf($other),
                        'created_at' => (string) ($other['created_at'] ?? ''),
                    ];
                }
                foreach ($rawByTracking[$tn] ?? [] as $local) {
                    if ((string) $local->order_id === $shopifyId) {
                        continue;
                    }
                    $reasons[] = 'same_tracking_on_other_shopify_order';
                    $owners[] = [
                        'where' => 'shopify_raw_orders',
                        'shopify_name' => (string) $local->order_number,
                        'shopify_id' => (string) $local->order_id,
                        'channel' => (string) $local->source_name,
                        'created_at' => (string) $local->order_date,
                        'sku' => (string) $local->sku,
                    ];
                }
            }

            $marketplaceCopy = in_array($channel, [
                'doba', 'temu', 'temu2', 'ebay', 'ebay1', 'ebay2', 'ebay3',
                'tiktok', 'tiktok2', 'newegg', 'aliexpress', 'shein',
                'walmart', 'faire', 'reverb',
            ], true);
            if ($marketplaceCopy && strlen($name) >= 6 && $this->isCollisionProneName($name)) {
                $matched = 0;
                foreach ($amazonRows as $amz) {
                    $amzId = (string) $amz->amazon_order_id;
                    if ($amzId === '' || $amzId === $name) {
                        continue;
                    }
                    if (! $this->amazonSegmentContainsName($amzId, $name)) {
                        continue;
                    }
                    $reasons[] = 'shopify_number_collides_with_amazon_order';
                    $owners[] = [
                        'where' => 'amazon_orders',
                        'amazon_order_id' => $amzId,
                        'amazon_shopify_order_id' => (string) $amz->shopify_order_id,
                        'created_at' => (string) $amz->order_date,
                        'status' => (string) $amz->status,
                    ];
                    $matched++;
                    if ($matched >= 5) {
                        break;
                    }
                }
            }

            if ($useVeeqo && $this->isCollisionProneName($name) && ! in_array($channel, ['amazon', 'web', 'pos', 'shopify'], true)) {
                foreach ($trackings as $tn) {
                    $veeqoHit = $this->veeqoOwnerForShopifyName($veeqo, $name, $tn);
                    if ($veeqoHit === null) {
                        continue;
                    }
                    $reasons[] = 'veeqo_tracking_belongs_to_other_order';
                    $owners[] = $veeqoHit;
                }
            }

            $dobaMeta = $this->dobaMeta($order);
            if ($channel === 'doba' || $dobaMeta['order_no'] !== '') {
                $channel = 'doba';
                $doba = $dobaByKey[$dobaMeta['order_no']] ?? $dobaByKey[$shopifyId] ?? null;
                if ($doba) {
                    $dobaTn = $this->normalizeTracking((string) $doba->tracking_number);
                    $ours = $this->normalizeTracking($trackings[0] ?? '');
                    if ($dobaTn !== '' && $ours !== '' && $dobaTn !== $ours) {
                        $reasons[] = 'doba_tracking_mismatch';
                    }
                    $owners[] = [
                        'where' => 'doba_daily_data',
                        'doba_order_no' => (string) $doba->order_no,
                        'doba_order_type' => (string) $doba->order_type,
                        'doba_tracking' => (string) $doba->tracking_number,
                    ];
                }
                $borrowed = in_array('same_tracking_on_other_shopify_order', $reasons, true)
                    || in_array('veeqo_tracking_belongs_to_other_order', $reasons, true)
                    || in_array('shopify_number_collides_with_amazon_order', $reasons, true)
                    || in_array('doba_tracking_mismatch', $reasons, true);
                if (! $dobaMeta['prepaid'] && $borrowed) {
                    $reasons[] = 'doba_not_prepaid_but_shopify_has_tracking';
                }
            }

            $reasons = array_values(array_unique($reasons));
            $confirmed = array_intersect($reasons, [
                'same_tracking_on_other_shopify_order',
                'veeqo_tracking_belongs_to_other_order',
                'doba_tracking_mismatch',
                'shopify_number_collides_with_amazon_order',
            ]);
            if ($confirmed === []) {
                continue;
            }

            $hits[] = [
                'channel' => $channel,
                'store' => (string) ($order['_store'] ?? ''),
                'shopify_name' => (string) ($order['name'] ?? ''),
                'shopify_id' => $shopifyId,
                'created_at' => (string) ($order['created_at'] ?? ''),
                'sku' => implode(' | ', $this->skusFromOrder($order)),
                'tracking' => implode(' | ', $trackings),
                'carrier' => $this->carrierFromOrder($order),
                'source_name' => (string) ($order['source_name'] ?? ''),
                'doba_order_no' => $dobaMeta['order_no'],
                'prepaid' => $dobaMeta['prepaid'],
                'reasons' => $reasons,
                'likely_real_owner' => $this->uniqueOwners($owners),
            ];
        }

        $this->newLine();
        $this->info('Orders with stolen / collided tracking: '.count($hits));
        $byChannel = [];
        foreach ($hits as $hit) {
            $byChannel[$hit['channel']] = ($byChannel[$hit['channel']] ?? 0) + 1;
        }
        foreach ($byChannel as $ch => $n) {
            $this->line("  {$ch}: {$n}");
        }

        $this->newLine();
        foreach ($hits as $hit) {
            $this->line(str_repeat('-', 88));
            $this->line(sprintf(
                '%s  %s  id=%s  %s',
                strtoupper((string) $hit['channel']),
                $hit['shopify_name'],
                $hit['shopify_id'],
                $hit['created_at']
            ));
            $this->line('  SKU: '.$hit['sku']);
            $this->line('  tracking: '.$hit['tracking'].($hit['carrier'] !== '' ? ' ('.$hit['carrier'].')' : ''));
            if ($hit['doba_order_no'] !== '') {
                $this->line('  Doba: '.$hit['doba_order_no'].($hit['prepaid'] ? ' (prepaid)' : ' (not prepaid)'));
            }
            $this->line('  reasons: '.implode(', ', $hit['reasons']));
            foreach ($hit['likely_real_owner'] as $owner) {
                $this->line('  owner: '.json_encode($owner, JSON_UNESCAPED_SLASHES));
            }
        }

        $jsonPath = trim((string) $this->option('json'));
        if ($jsonPath !== '') {
            file_put_contents($jsonPath, json_encode($hits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Wrote '.$jsonPath);
        } else {
            $default = storage_path('app/wrong_tracking_audit.json');
            file_put_contents($default, json_encode($hits, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Wrote '.$default);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{store_url: string, token: string, store_key: string}>
     */
    protected function storeConfigs(ShopifyStoreSelector $stores): array
    {
        $out = [];
        foreach (['main', '5core', 'business', 'prolightsounds'] as $key) {
            $config = $stores->getConfigForStore($key);
            $url = strtolower(trim((string) ($config['store_url'] ?? '')));
            $token = trim((string) ($config['token'] ?? ''));
            if ($url === '' || $token === '' || isset($out[$url])) {
                continue;
            }
            $out[$url] = $config;
        }

        return array_values($out);
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return list<array<string, mixed>>
     */
    protected function fetchShopifyOrders(array $config, \DateTimeInterface $since): array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $search = 'created_at:>='.$since->format('Y-m-d').' fulfillment_status:fulfilled';
        $gql = <<<'GQL'
query Page($cursor: String, $search: String!) {
  orders(first: 80, after: $cursor, query: $search, sortKey: CREATED_AT) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        name
        createdAt
        tags
        sourceName
        displayFulfillmentStatus
        customAttributes { key value }
        fulfillments { status trackingInfo { number company } }
        lineItems(first: 20) { edges { node { sku } } }
      }
    }
  }
}
GQL;
        $out = [];
        $cursor = null;
        for ($page = 0; $page < 80; $page++) {
            $response = null;
            for ($attempt = 1; $attempt <= 8; $attempt++) {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(60)
                    ->post("https://{$storeUrl}/admin/api/2025-01/graphql.json", [
                        'query' => $gql,
                        'variables' => ['cursor' => $cursor, 'search' => $search],
                    ]);
                if ($response->status() !== 429) {
                    break;
                }
                $wait = (int) ($response->header('Retry-After') ?: (3 * $attempt));
                $this->warn("  Shopify 429 — waiting {$wait}s");
                sleep(max(2, min(30, $wait)));
            }
            if ($response === null || ! $response->successful()) {
                $this->warn('  Shopify GraphQL HTTP '.($response ? $response->status() : 'null')." on {$storeUrl}");
                break;
            }
            $payload = $response->json();
            if (! empty($payload['errors'])) {
                $this->warn('  Shopify GraphQL error: '.json_encode($payload['errors']));
                break;
            }
            $conn = $payload['data']['orders'] ?? [];
            $edges = $conn['edges'] ?? [];
            if (! is_array($edges) || $edges === []) {
                break;
            }
            foreach ($edges as $edge) {
                $node = $edge['node'] ?? null;
                if (is_array($node)) {
                    $out[] = $this->graphOrderToRest($node);
                }
            }
            if (empty($conn['pageInfo']['hasNextPage'])) {
                break;
            }
            $cursor = $conn['pageInfo']['endCursor'] ?? null;
            if ($cursor === null || $cursor === '') {
                break;
            }
            usleep(200000);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function graphOrderToRest(array $node): array
    {
        $gid = (string) ($node['id'] ?? '');
        $id = preg_match('/(\d+)$/', $gid, $m) ? $m[1] : $gid;
        $attrs = [];
        foreach ($node['customAttributes'] ?? [] as $attr) {
            if (is_array($attr)) {
                $attrs[] = [
                    'name' => (string) ($attr['key'] ?? ''),
                    'value' => (string) ($attr['value'] ?? ''),
                ];
            }
        }
        $fulfillments = [];
        foreach ($node['fulfillments'] ?? [] as $f) {
            if (! is_array($f)) {
                continue;
            }
            $numbers = [];
            $company = '';
            foreach ($f['trackingInfo'] ?? [] as $info) {
                if (! is_array($info)) {
                    continue;
                }
                $n = trim((string) ($info['number'] ?? ''));
                if ($n !== '') {
                    $numbers[] = $n;
                }
                if ($company === '') {
                    $company = trim((string) ($info['company'] ?? ''));
                }
            }
            $fulfillments[] = [
                'status' => (string) ($f['status'] ?? 'success'),
                'tracking_number' => $numbers[0] ?? '',
                'tracking_numbers' => $numbers,
                'tracking_company' => $company,
            ];
        }
        $lines = [];
        foreach ($node['lineItems']['edges'] ?? [] as $edge) {
            $n = $edge['node'] ?? null;
            if (is_array($n)) {
                $lines[] = ['sku' => (string) ($n['sku'] ?? '')];
            }
        }

        return [
            'id' => $id,
            'name' => (string) ($node['name'] ?? ''),
            'created_at' => (string) ($node['createdAt'] ?? ''),
            'source_name' => (string) ($node['sourceName'] ?? ''),
            'tags' => implode(', ', is_array($node['tags'] ?? null) ? $node['tags'] : []),
            'note' => '',
            'note_attributes' => $attrs,
            'fulfillment_status' => strtolower((string) ($node['displayFulfillmentStatus'] ?? '')),
            'fulfillments' => $fulfillments,
            'line_items' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function trackingsFromOrder(array $order): array
    {
        $out = [];
        foreach ($order['fulfillments'] ?? [] as $fulfillment) {
            if (! is_array($fulfillment)) {
                continue;
            }
            $status = strtolower((string) ($fulfillment['status'] ?? ''));
            if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                continue;
            }
            $numbers = [];
            if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                $numbers = $fulfillment['tracking_numbers'];
            } elseif (! empty($fulfillment['tracking_number'])) {
                $numbers = [$fulfillment['tracking_number']];
            }
            foreach ($numbers as $n) {
                $tn = $this->normalizeTracking((string) $n);
                if ($tn !== '' && ! in_array($tn, $out, true)) {
                    $out[] = $tn;
                }
            }
        }

        return $out;
    }

    protected function normalizeTracking(string $tn): string
    {
        $tn = strtoupper(preg_replace('/\s+/', '', trim($tn)) ?? '');
        $tn = preg_replace('/\([^)]*\)$/', '', $tn) ?? $tn;

        return $tn;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function carrierFromOrder(array $order): string
    {
        foreach ($order['fulfillments'] ?? [] as $fulfillment) {
            if (is_array($fulfillment) && trim((string) ($fulfillment['tracking_company'] ?? '')) !== '') {
                return trim((string) $fulfillment['tracking_company']);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function skusFromOrder(array $order): array
    {
        $out = [];
        foreach ($order['line_items'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = trim((string) ($line['sku'] ?? ''));
            if ($sku !== '' && ! in_array($sku, $out, true)) {
                $out[] = $sku;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function channelOf(array $order): string
    {
        $src = strtolower((string) ($order['source_name'] ?? ''));
        $tags = strtolower((string) ($order['tags'] ?? ''));
        $note = strtolower((string) ($order['note'] ?? ''));
        $hay = $src.' '.$tags.' '.$note;
        if (str_contains($hay, 'doba') || $src === '145019994113') {
            return 'doba';
        }
        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? $attr['key'] ?? ''));
            if (str_contains($name, 'doba')) {
                return 'doba';
            }
        }
        foreach (['amazon', 'ebay2', 'ebay1', 'ebay3', 'ebay', 'newegg', 'aliexpress', 'tiktok2', 'tiktok', 'temu2', 'temu', 'shein', 'walmart', 'faire', 'reverb'] as $slug) {
            if (str_contains($src, $slug) || preg_match('/(?:^|[\s,])'.preg_quote($slug, '/').'-/', $tags)) {
                return $slug;
            }
        }

        return $src !== '' ? $src : 'shopify';
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{order_no: string, prepaid: bool}
     */
    protected function dobaMeta(array $order): array
    {
        $orderNo = '';
        $prepaid = false;
        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? $attr['key'] ?? ''));
            $val = trim((string) ($attr['value'] ?? ''));
            if ($orderNo === '' && str_contains($name, 'doba') && str_contains($name, 'order')) {
                $orderNo = $val;
            }
            if (str_contains($name, 'prepaid') || str_contains(strtolower($val), 'prepaid label')) {
                $prepaid = true;
            }
        }
        $note = strtolower((string) ($order['note'] ?? ''));
        if (str_contains($note, 'prepaid label')) {
            $prepaid = true;
        }

        return ['order_no' => $orderNo, 'prepaid' => $prepaid];
    }

    protected function isCollisionProneName(string $name): bool
    {
        return $name !== '' && (bool) preg_match('/^\d{5,10}$/', $name);
    }

    /**
     * Veeqo search for #334042 hits 113-3340426-4270650 because 334042 sits
     * inside one Amazon segment. Digit runs that only appear after stripping
     * hyphens are accidental and must not count.
     */
    protected function amazonSegmentContainsName(string $amazonId, string $name): bool
    {
        foreach (explode('-', $amazonId) as $part) {
            if ($part === $name) {
                return true;
            }
            if (strlen($part) > strlen($name) && str_contains($part, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function veeqoOwnerForShopifyName(VeeqoApiService $veeqo, string $name, string $tracking): ?array
    {
        static $cache = [];
        $key = $name.'|'.$tracking;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $res = $veeqo->listOrders([
            'query' => $name,
            'page_size' => 15,
            'page' => 1,
        ]);
        usleep(200000);
        if (empty($res['ok']) || ! is_array($res['data'] ?? null)) {
            $cache[$key] = null;

            return null;
        }
        $raw = $res['data'];
        $list = array_is_list($raw) ? $raw : (isset($raw['orders']) && is_array($raw['orders']) ? $raw['orders'] : []);
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $veeqoTns = $this->trackingsFromVeeqoPayload($row);
            if (! in_array($tracking, $veeqoTns, true)) {
                continue;
            }
            $identity = $this->veeqoIdentity($row);
            $plainName = strtolower($name);
            $matchedSelf = false;
            foreach ($identity as $id) {
                if (strtolower(str_replace(['#', '-'], '', $id)) === str_replace('-', '', $plainName)) {
                    $matchedSelf = true;
                    break;
                }
            }
            if ($matchedSelf) {
                continue;
            }
            $cache[$key] = [
                'where' => 'veeqo',
                'veeqo_order_id' => $row['id'] ?? null,
                'veeqo_number' => $row['number'] ?? ($row['order_number'] ?? null),
                'channel_order_number' => $row['channel_order_number'] ?? null,
                'identities' => $identity,
                'tracking' => $tracking,
            ];

            return $cache[$key];
        }

        $cache[$key] = null;

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function trackingsFromVeeqoPayload(array $order): array
    {
        $out = [];
        $walk = function ($node) use (&$walk, &$out): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                    continue;
                }
                $key = strtolower((string) $k);
                if (! str_contains($key, 'tracking')) {
                    continue;
                }
                $tn = $this->normalizeTracking((string) $v);
                if ($tn !== '' && strlen($tn) >= 8 && ! in_array($tn, $out, true)) {
                    $out[] = $tn;
                }
            }
        };
        $walk($order);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function veeqoIdentity(array $order): array
    {
        $keys = ['number', 'order_number', 'channel_order_number', 'channel_order_id', 'remote_id', 'customer_reference_number'];
        $out = [];
        foreach ($keys as $key) {
            $val = trim((string) ($order[$key] ?? ''));
            if ($val !== '' && ! in_array($val, $out, true)) {
                $out[] = $val;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $owners
     * @return list<array<string, mixed>>
     */
    protected function uniqueOwners(array $owners): array
    {
        $seen = [];
        $out = [];
        foreach ($owners as $owner) {
            $key = json_encode($owner);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $owner;
        }

        return $out;
    }
}
