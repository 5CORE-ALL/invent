<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Http;

/**
 * Lists and fulfils Shopify orders through the GraphQL Admin API.
 *
 * The REST bucket (2 calls/s) is shared with every other job on the store and stays empty, so
 * REST fulfilment kept failing with 429. GraphQL has its own cost budget (1,000 points,
 * refilled ~50/s): one query loads the order with its fulfillment orders and existing tracking,
 * one mutation creates the fulfillment.
 */
final class ShopifyGraphqlFulfiller
{
    public const API_VERSION = '2025-01';

    /** Keep this many points free for other jobs before sending the next call. */
    private const MIN_AVAILABLE = 150;

    private const DONE_STATUSES = ['FULFILLED', 'RESTOCKED'];

    private const ORDER_QUERY = <<<'GQL'
query fulfilOrder($id: ID!) {
  order(id: $id) {
    id
    legacyResourceId
    name
    tags
    cancelledAt
    displayFulfillmentStatus
    fulfillments(first: 20) { status trackingInfo(first: 5) { number } }
    fulfillmentOrders(first: 20) {
      nodes {
        id
        status
        requestStatus
        supportedActions { action }
        lineItems(first: 50) { nodes { id remainingQuantity lineItem { sku name } } }
      }
    }
  }
}
GQL;

    private ?bool $useV2 = null;

    /**
     * Open orders not yet fully fulfilled, oldest first.
     *
     * @param  array{store_url: string, token: string}  $config
     * @return list<array{id: string, name: string, tags: string, created_at: string, fulfillment_status: string}>|null
     */
    public function unfulfilledOrders(array $config, \DateTimeInterface $since): ?array
    {
        $query = <<<'GQL'
query openOrders($q: String!, $after: String) {
  orders(first: 100, query: $q, after: $after, sortKey: CREATED_AT) {
    pageInfo { hasNextPage endCursor }
    nodes { legacyResourceId name tags createdAt cancelledAt displayFulfillmentStatus }
  }
}
GQL;
        $search = 'status:open (fulfillment_status:unfulfilled OR fulfillment_status:partial) created_at:>='.$since->format('Y-m-d');
        $out = [];
        $after = null;
        for ($page = 0; $page < 60; $page++) {
            $res = $this->call($config, $query, ['q' => $search, 'after' => $after]);
            $conn = $res['data']['orders'] ?? null;
            if (! is_array($conn)) {
                return $out === [] ? null : $out;
            }
            foreach ((array) ($conn['nodes'] ?? []) as $node) {
                if (! is_array($node) || ! empty($node['cancelledAt'])) {
                    continue;
                }
                $status = strtoupper((string) ($node['displayFulfillmentStatus'] ?? ''));
                if (in_array($status, self::DONE_STATUSES, true)) {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($node['legacyResourceId'] ?? ''),
                    'name' => (string) ($node['name'] ?? ''),
                    'tags' => implode(', ', array_map('strval', (array) ($node['tags'] ?? []))),
                    'created_at' => (string) ($node['createdAt'] ?? ''),
                    'fulfillment_status' => $status,
                ];
            }
            if (empty($conn['pageInfo']['hasNextPage'])) {
                break;
            }
            $after = (string) ($conn['pageInfo']['endCursor'] ?? '');
        }

        return $out;
    }

    /**
     * 'open', 'cancelled', 'fulfilled' or 'unknown'.
     *
     * @param  array{store_url: string, token: string}  $config
     */
    public function orderState(array $config, string $orderId): string
    {
        $res = $this->call($config, 'query s($id: ID!) { order(id: $id) { cancelledAt displayFulfillmentStatus } }', ['id' => self::gid($orderId)]);
        $order = $res['data']['order'] ?? null;
        if (! is_array($order)) {
            return 'unknown';
        }
        if (! empty($order['cancelledAt'])) {
            return 'cancelled';
        }

        return in_array(strtoupper((string) ($order['displayFulfillmentStatus'] ?? '')), self::DONE_STATUSES, true) ? 'fulfilled' : 'open';
    }

    /**
     * Fulfil the open lines (only those whose SKU is in $skus when given) with one tracking number.
     *
     * @param  array{store_url: string, token: string}  $config
     * @param  list<string>|null  $skus
     * @return array{status: 'fulfilled'|'already'|'cancelled'|'no_open_lines'|'unavailable'|'error', message: string}
     */
    public function fulfil(array $config, string $orderId, string $tracking, string $carrier, ?array $skus = null): array
    {
        $tracking = strtoupper((string) preg_replace('/\s+/', '', $tracking));
        $order = $this->loadOrder($config, $orderId);
        if ($order === null) {
            return ['status' => 'unavailable', 'message' => 'Shopify GraphQL did not return the order.'];
        }
        if (! empty($order['cancelledAt'])) {
            return ['status' => 'cancelled', 'message' => 'Shopify order is cancelled.'];
        }

        if ($this->releaseBlocked($config, $order)) {
            $order = $this->loadOrder($config, $orderId) ?? $order;
        }

        $byFo = self::openLines($order, $skus);
        if ($byFo === []) {
            if (self::hasTracking($order, $tracking)) {
                return ['status' => 'already', 'message' => 'Shopify already has tracking '.$tracking.'.'];
            }

            return ['status' => 'no_open_lines', 'message' => 'No open Shopify lines to fulfil ('.self::foSummary($order).').'];
        }

        $done = 0;
        $errors = [];
        foreach ($byFo as $foId => $lines) {
            $input = [
                'notifyCustomer' => false,
                'trackingInfo' => array_filter(['number' => $tracking, 'company' => mb_substr(trim($carrier), 0, 100)]),
                'lineItemsByFulfillmentOrder' => [[
                    'fulfillmentOrderId' => $foId,
                    'fulfillmentOrderLineItems' => $lines,
                ]],
            ];
            $error = $this->createFulfillment($config, $input);
            if ($error === null) {
                $done++;
            } else {
                $errors[] = $error;
            }
        }

        if ($done > 0) {
            return ['status' => 'fulfilled', 'message' => 'Fulfilled '.$done.' fulfillment order(s) with '.$tracking.'.'.($errors !== [] ? ' Some failed: '.implode('; ', $errors) : '')];
        }

        return ['status' => 'error', 'message' => mb_substr(implode('; ', $errors), 0, 250)];
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  list<string>|null  $skus
     * @return array<string, list<array{id: string, quantity: int}>>
     */
    public static function openLines(array $order, ?array $skus): array
    {
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $out = [];
        foreach ((array) ($order['fulfillmentOrders']['nodes'] ?? []) as $fo) {
            if (! is_array($fo) || ! in_array(strtoupper((string) ($fo['status'] ?? '')), ['OPEN', 'IN_PROGRESS'], true)) {
                continue;
            }
            if (! in_array('CREATE_FULFILLMENT', self::actions($fo), true)) {
                continue;
            }
            foreach ((array) ($fo['lineItems']['nodes'] ?? []) as $line) {
                $qty = (int) ($line['remainingQuantity'] ?? 0);
                if ($qty < 1) {
                    continue;
                }
                if ($skus !== null) {
                    $lineSku = (string) ($line['lineItem']['sku'] ?? '');
                    $hit = false;
                    foreach ($skus as $sku) {
                        if ($sku !== '' && $matcher->skusEqual($lineSku, $sku)) {
                            $hit = true;
                            break;
                        }
                    }
                    if (! $hit) {
                        continue;
                    }
                }
                $out[(string) $fo['id']][] = ['id' => (string) $line['id'], 'quantity' => $qty];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public static function hasTracking(array $order, string $tracking): bool
    {
        foreach ((array) ($order['fulfillments'] ?? []) as $f) {
            if (strtoupper((string) ($f['status'] ?? '')) !== 'SUCCESS') {
                continue;
            }
            foreach ((array) ($f['trackingInfo'] ?? []) as $info) {
                if (strtoupper((string) preg_replace('/\s+/', '', (string) ($info['number'] ?? ''))) === $tracking) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Holds and schedules keep an order "Unfulfilled" with nothing to fulfil; open them first.
     *
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $order
     */
    private function releaseBlocked(array $config, array $order): bool
    {
        $changed = false;
        foreach ((array) ($order['fulfillmentOrders']['nodes'] ?? []) as $fo) {
            $actions = self::actions($fo);
            $status = strtoupper((string) ($fo['status'] ?? ''));
            if ($status === 'ON_HOLD' && in_array('RELEASE_HOLD', $actions, true)) {
                $res = $this->call($config, 'mutation r($id: ID!) { fulfillmentOrderReleaseHold(id: $id) { userErrors { message } } }', ['id' => $fo['id']]);
                $changed = $changed || ($res['data']['fulfillmentOrderReleaseHold'] ?? null) !== null;
            } elseif ($status === 'SCHEDULED' && in_array('MARK_AS_OPEN', $actions, true)) {
                $res = $this->call($config, 'mutation o($id: ID!) { fulfillmentOrderOpen(id: $id) { userErrors { message } } }', ['id' => $fo['id']]);
                $changed = $changed || ($res['data']['fulfillmentOrderOpen'] ?? null) !== null;
            }
        }

        return $changed;
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @param  array<string, mixed>  $input
     */
    private function createFulfillment(array $config, array $input): ?string
    {
        if ($this->useV2 !== true) {
            $res = $this->call($config, 'mutation f($f: FulfillmentInput!) { fulfillmentCreate(fulfillment: $f) { fulfillment { id status } userErrors { field message } } }', ['f' => $input]);
            // A schema without fulfillmentCreate rejects the whole document (data null): use the V2 mutation.
            if (! ($res['data'] === null && $res['errors'] !== [] && $res['status'] === 200)) {
                $this->useV2 = false;

                return self::mutationError($res, 'fulfillmentCreate');
            }
            $this->useV2 = true;
        }
        $res = $this->call($config, 'mutation f($f: FulfillmentV2Input!) { fulfillmentCreateV2(fulfillment: $f) { fulfillment { id status } userErrors { field message } } }', ['f' => $input]);

        return self::mutationError($res, 'fulfillmentCreateV2');
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @return array<string, mixed>|null
     */
    private function loadOrder(array $config, string $orderId): ?array
    {
        $res = $this->call($config, self::ORDER_QUERY, ['id' => self::gid($orderId)]);
        $order = $res['data']['order'] ?? null;

        return is_array($order) ? $order : null;
    }

    /**
     * @param  array{store_url: string, token: string}  $config
     * @return array{data: mixed, errors: list<mixed>, status: int}
     */
    private function call(array $config, string $query, array $variables = []): array
    {
        $url = 'https://'.$config['store_url'].'/admin/api/'.self::API_VERSION.'/graphql.json';
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            try {
                $res = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $config['token'],
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post($url, ['query' => $query, 'variables' => (object) $variables]);
            } catch (\Throwable $e) {
                if ($attempt >= 6) {
                    return ['data' => null, 'errors' => [['message' => $e->getMessage()]], 'status' => 0];
                }
                sleep(2 * $attempt);

                continue;
            }

            if ($res->status() === 429 || $res->status() >= 500) {
                sleep(min(10, 2 * $attempt));

                continue;
            }
            $json = (array) $res->json();
            $errors = array_values((array) ($json['errors'] ?? []));
            $throttle = $json['extensions']['cost']['throttleStatus'] ?? null;
            if (self::throttled($errors)) {
                usleep((int) (self::throttleWait($throttle, (int) ($json['extensions']['cost']['requestedQueryCost'] ?? 200)) * 1_000_000));

                continue;
            }
            if (is_array($throttle) && (float) ($throttle['currentlyAvailable'] ?? 1000) < self::MIN_AVAILABLE) {
                usleep((int) (self::throttleWait($throttle, self::MIN_AVAILABLE) * 1_000_000));
            }

            return ['data' => $json['data'] ?? null, 'errors' => $errors, 'status' => $res->status()];
        }

        return ['data' => null, 'errors' => [['message' => 'Shopify GraphQL kept throttling.']], 'status' => 429];
    }

    /**
     * Seconds until the bucket holds $needed points again.
     *
     * @param  array<string, mixed>|null  $throttle
     */
    public static function throttleWait(?array $throttle, int $needed): float
    {
        if (! is_array($throttle)) {
            return 2.0;
        }
        $available = (float) ($throttle['currentlyAvailable'] ?? 0);
        $restore = max(1.0, (float) ($throttle['restoreRate'] ?? 50));

        return round(min(15.0, max(0.5, ($needed - $available) / $restore)), 2);
    }

    /** @param list<mixed> $errors */
    private static function throttled(array $errors): bool
    {
        foreach ($errors as $e) {
            if (strtoupper((string) ($e['extensions']['code'] ?? '')) === 'THROTTLED' || stripos((string) ($e['message'] ?? ''), 'throttled') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{data: mixed, errors: list<mixed>, status: int}  $res
     */
    private static function mutationError(array $res, string $field): ?string
    {
        $payload = $res['data'][$field] ?? null;
        if (is_array($payload) && ! empty($payload['fulfillment']['id'])) {
            return null;
        }
        $messages = [];
        foreach ((array) ($payload['userErrors'] ?? []) as $e) {
            $messages[] = (string) ($e['message'] ?? '');
        }
        foreach ($res['errors'] as $e) {
            $messages[] = (string) ($e['message'] ?? '');
        }
        $messages = array_filter($messages);

        return $messages !== [] ? implode('; ', $messages) : 'Shopify did not create the fulfillment (HTTP '.$res['status'].').';
    }

    /** @return list<string> */
    private static function actions(mixed $fo): array
    {
        return array_map(fn ($a) => strtoupper((string) ($a['action'] ?? '')), (array) (is_array($fo) ? ($fo['supportedActions'] ?? []) : []));
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private static function foSummary(array $order): string
    {
        $parts = [];
        foreach ((array) ($order['fulfillmentOrders']['nodes'] ?? []) as $fo) {
            $req = strtoupper((string) ($fo['requestStatus'] ?? ''));
            $parts[] = strtolower((string) ($fo['status'] ?? '?')).($req !== '' && $req !== 'UNSUBMITTED' ? '/'.strtolower($req) : '');
        }

        return $parts === [] ? 'no fulfillment orders' : 'fulfillment orders: '.implode(', ', $parts);
    }

    private static function gid(string $orderId): string
    {
        $orderId = trim($orderId);

        return str_starts_with($orderId, 'gid://') ? $orderId : 'gid://shopify/Order/'.$orderId;
    }
}
