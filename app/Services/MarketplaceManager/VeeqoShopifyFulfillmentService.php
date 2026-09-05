<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrder;
use App\Models\BestBuyOrderMetric;
use App\Models\DobaDailyData;
use App\Models\Ebay1OrderMetric;
use App\Models\Ebay2OrderMetric;
use App\Models\Ebay3OrderMetric;
use App\Models\FaireOrderMetric;
use App\Models\MacyOrderMetric;
use App\Models\NeweggOrderMetric;
use App\Models\PlsSale;
use App\Models\PurchasingPowerSale;
use App\Models\ReverbOrderMetric;
use App\Models\SheinOrderMetric;
use App\Models\Temu2Order;
use App\Models\TemuOrder;
use App\Models\Tiktok2Order;
use App\Models\TiktokOrder;
use App\Models\TopDawgOrderMetric;
use App\Models\WayfairDailyData;
use App\Services\ShopifyStoreSelector;
use App\Models\MarketplaceSyncSettings;
use App\Services\FourSellerApiService;
use App\Services\GofoExpressService;
use App\Services\VeeqoApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Copy a shipping-label tracking number (Veeqo, GOFO via 4Seller, or the marketplace itself)
 * onto the Shopify order Marketplace Manager imported, and mark it fulfilled.
 */
class VeeqoShopifyFulfillmentService
{
    private const SHOPIFY_API_VERSION = '2025-01';

    public function __construct(
        protected VeeqoApiService $veeqo,
        protected GofoExpressService $gofo,
        protected FourSellerApiService $fourSeller,
        protected ShopifyStoreSelector $stores,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   tracking?: string|null,
     *   carrier?: string|null
     * }
     */
    public function fulfillMarketplaceOrder(string $marketplace, int $orderId): array
    {
        $ctx = $this->contextForMarketplaceOrder($marketplace, $orderId);
        if ($ctx === null) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'unsupported',
                'message' => 'This marketplace order is not set up for label tracking → Shopify yet.',
            ];
        }

        $result = $this->fulfillShopifyFromLabels(
            (string) $ctx['shopify_order_id'],
            (array) $ctx['shopify_config'],
            (array) $ctx['refs'],
            is_array($ctx['local_tracking'] ?? null) ? $ctx['local_tracking'] : null,
            (string) ($ctx['sku'] ?? ''),
            is_array($ctx['marketplace_order_ids'] ?? null) ? $ctx['marketplace_order_ids'] : [],
            $marketplace
        );
        $tn = trim((string) ($result['tracking'] ?? ''));
        if ($tn !== '') {
            $this->persistTrackingOntoMarketplaceOrder(
                $marketplace,
                $orderId,
                (string) ($ctx['shopify_order_id'] ?? ''),
                $tn,
                (string) ($result['carrier'] ?? '')
            );
            $this->pushChannelTrackingAfterShopify($marketplace, $orderId, $result);
        }

        return $result;
    }

    /**
     * @param  list<string>  $refs
     * @param  array{store_url?: string, token?: string}  $shopifyConfig
     * @param  array{tracking?: string, carrier?: string}|null  $localTracking
     * @return array{
     *   success: bool,
     *   skipped?: bool,
     *   action?: string|null,
     *   message: string,
     *   tracking?: string|null,
     *   carrier?: string|null
     * }
     */
    public function fulfillShopifyFromLabels(
        string $shopifyOrderId,
        array $shopifyConfig,
        array $refs,
        ?array $localTracking = null,
        string $sku = '',
        array $marketplaceOrderIds = [],
        string $marketplace = ''
    ): array {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'not_linked',
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $strict = in_array(strtolower(trim($marketplace)), ['ebay2', 'tiktok', 'aliexpress'], true);
        $marketplaceOrderIds = app(ShopifyFulfillmentTrackingMatcher::class)->uniqueIds(
            $marketplaceOrderIds !== [] ? $marketplaceOrderIds : $refs
        );
        $sku = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);

        if ($strict && $marketplaceOrderIds === []) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'order_id_required',
                'message' => 'Marketplace order id missing — tracking not attached.',
            ];
        }
        if ($strict && ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true))) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'sku_required',
                'message' => 'Marketplace SKU missing — tracking not attached.',
            ];
        }

        if ($strict) {
            $orderCheck = $this->shopifyOrderPayload($shopifyConfig, $shopifyOrderId);
            if ($orderCheck === null) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'action' => 'shopify_order_missing',
                    'message' => 'Could not load the Shopify order to match marketplace order id + SKU.',
                ];
            }
            $matchedOrderId = app(ShopifyFulfillmentTrackingMatcher::class)
                ->matchFullOrderId($orderCheck, $marketplaceOrderIds);
            if ($matchedOrderId === null) {
                Log::info('VeeqoShopifyFulfillmentService: skip fulfill — Shopify order id mismatch', [
                    'marketplace' => $marketplace,
                    'shopify_order_id' => $shopifyOrderId,
                    'wanted' => $marketplaceOrderIds,
                ]);

                return [
                    'success' => false,
                    'skipped' => true,
                    'action' => 'order_id_mismatch',
                    'message' => 'Shopify order does not contain the full marketplace order id.',
                ];
            }
            $refs = $this->strongMarketplaceRefs($marketplaceOrderIds);
        } else {
            $refs = array_values(array_unique(array_filter(array_merge(
                $refs,
                $this->shopifyDisplayNameRefs($shopifyConfig, $shopifyOrderId)
            ))));
        }

        $existing = $this->existingShopifyTracking(
            $shopifyConfig,
            $shopifyOrderId,
            $sku,
            $marketplaceOrderIds
        );
        if ($existing !== null) {
            $this->cacheTrackingOnShopifyRawOrder(
                $shopifyOrderId,
                (string) $existing['tracking'],
                (string) ($existing['carrier'] ?? '')
            );

            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_on_shopify',
                'message' => 'Shopify already has tracking '.$existing['tracking'].'.',
                'tracking' => $existing['tracking'],
                'carrier' => $existing['carrier'],
            ];
        }

        $found = $this->lookupLabelTracking($refs, is_array($localTracking) ? $localTracking : null);
        if ($found === null) {
            $checked = [];
            if ($this->veeqo->isConfigured()) {
                $checked[] = 'Veeqo';
            }
            if ($this->gofo->isConfigured()) {
                $checked[] = 'GOFO (4Seller)';
            }
            $checked[] = 'the marketplace order';
            $who = implode(', ', $checked);

            return [
                'success' => false,
                'skipped' => true,
                'action' => 'tracking_not_found',
                'message' => 'No tracking found yet (checked '.$who.'). Buy the label in Veeqo or 4Seller/GOFO, then try again.',
            ];
        }

        $source = (string) ($found['source'] ?? 'marketplace');

        $carrier = $this->shopifyCarrierName((string) ($found['carrier'] ?? 'Other'), (string) ($found['tracking'] ?? ''));
        $this->cacheTrackingOnShopifyRawOrder($shopifyOrderId, (string) $found['tracking'], $carrier);
        $written = $this->createShopifyFulfillment(
            $shopifyConfig,
            $shopifyOrderId,
            $found['tracking'],
            $carrier,
            $sku
        );

        if (empty($written['success'])) {
            return [
                'success' => false,
                'action' => 'shopify_fulfill_failed',
                'message' => (string) ($written['message'] ?? 'Failed to fulfill the Shopify order with tracking.'),
                'tracking' => $found['tracking'],
                'carrier' => $carrier,
            ];
        }

        $label = match ($source) {
            '4seller' => '4Seller',
            'veeqo' => 'Veeqo',
            'gofo' => 'GOFO (4Seller)',
            default => 'marketplace',
        };

        return [
            'success' => true,
            'skipped' => ! empty($written['already']),
            'action' => ! empty($written['already']) ? 'already_on_shopify' : 'shopify_fulfilled',
            'message' => ! empty($written['already'])
                ? 'Shopify already has tracking '.$found['tracking'].'.'
                : 'Shopify order fulfilled with '.$label.' tracking '.$found['tracking'].' ('.$carrier.').',
            'tracking' => $found['tracking'],
            'carrier' => $carrier,
        ];
    }

    public function fulfillShopifyFromVeeqo(string $shopifyOrderId, array $shopifyConfig, array $refs): array
    {
        return $this->fulfillShopifyFromLabels($shopifyOrderId, $shopifyConfig, $refs);
    }

    /**
     * Look up a shipping-label tracking number from Veeqo, GOFO, 4Seller, or the local order payload.
     * Does not write to Shopify.
     *
     * @param  list<string>  $refs
     * @param  array{tracking?: string, carrier?: string}|null  $localTracking
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    public function lookupLabelTracking(array $refs, ?array $localTracking = null, bool $fast = false): ?array
    {
        $localTn = strtoupper(preg_replace('/\s+/', '', (string) ($localTracking['tracking'] ?? '')) ?? '');
        if (strlen($localTn) >= 8) {
            return [
                'tracking' => $localTn,
                'carrier' => (string) ($localTracking['carrier'] ?? 'Other'),
                'source' => 'marketplace',
            ];
        }

        $clean = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '' || in_array($ref, $clean, true)) {
                continue;
            }
            $clean[] = $ref;
        }
        $marketRefs = $this->marketplaceLookupRefs($clean, $fast ? 3 : 8);

        if ($fast) {
            // Label Created eBay/Amazon labels are usually bought in GOFO/4Seller.
            // Hitting Veeqo first burned the HTTP deadline and left most rows blank.
            if ($this->gofo->isConfigured() && $marketRefs !== []) {
                $gofo = $this->gofo->findShipment($marketRefs, true);
                if ($gofo !== null && trim((string) ($gofo['tracking'] ?? '')) !== '') {
                    return [
                        'tracking' => (string) $gofo['tracking'],
                        'carrier' => (string) ($gofo['carrier'] ?? 'GOFO'),
                        'source' => 'gofo',
                    ];
                }
            }
            if ($this->veeqo->isConfigured() && $marketRefs !== []) {
                $veeqo = $this->findVeeqoShipment(array_slice($marketRefs, 0, 2), true);
                if ($veeqo !== null && trim((string) ($veeqo['tracking'] ?? '')) !== '') {
                    return [
                        'tracking' => (string) $veeqo['tracking'],
                        'carrier' => (string) ($veeqo['carrier'] ?? 'Veeqo'),
                        'source' => 'veeqo',
                    ];
                }
            }

            return null;
        }

        if ($this->veeqo->isConfigured()) {
            $veeqo = $this->findVeeqoShipment($clean, false);
            if ($veeqo !== null && trim((string) ($veeqo['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $veeqo['tracking'],
                    'carrier' => (string) ($veeqo['carrier'] ?? 'Veeqo'),
                    'source' => 'veeqo',
                ];
            }
        }

        if ($this->gofo->isConfigured()) {
            $gofoRefs = $this->strongMarketplaceRefs($marketRefs !== [] ? $marketRefs : $clean);
            $gofo = $gofoRefs === [] ? null : $this->gofo->findShipment($gofoRefs);
            if ($gofo !== null && trim((string) ($gofo['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $gofo['tracking'],
                    'carrier' => (string) ($gofo['carrier'] ?? 'GOFO'),
                    'source' => 'gofo',
                ];
            }
        }

        if ($this->fourSeller->isConfigured()) {
            $fs = $this->fourSeller->findShipment($this->strongMarketplaceRefs($clean));
            if ($fs !== null && trim((string) ($fs['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $fs['tracking'],
                    'carrier' => (string) ($fs['carrier'] ?? 'GOFO'),
                    'source' => '4seller',
                ];
            }
        }

        return null;
    }

    /**
     * Marketplace / customer order numbers suitable for GOFO and Veeqo search.
     * Drops Shopify GIDs and other long internal ids that will never match a label.
     *
     * @param  list<string>  $refs
     * @return list<string>
     */
    protected function marketplaceLookupRefs(array $refs, int $max = 6): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '' || strlen($ref) < 6) {
                continue;
            }
            if ($this->isCollisionProneOrderRef($ref) || $this->isShopifyInternalIdRef($ref)) {
                continue;
            }
            if (str_starts_with($ref, 'gid://') || str_starts_with($ref, 'https://')) {
                continue;
            }
            if (strlen($ref) > 64) {
                continue;
            }
            if (! in_array($ref, $out, true)) {
                $out[] = $ref;
            }
            if (preg_match('/^PO-(.+)$/i', $ref, $m)) {
                $tail = trim((string) ($m[1] ?? ''));
                if (
                    $tail !== ''
                    && ! $this->isCollisionProneOrderRef($tail)
                    && ! $this->isShopifyInternalIdRef($tail)
                    && ! in_array($tail, $out, true)
                ) {
                    $out[] = $tail;
                }
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Auto-run for every Marketplace Manager channel: unfulfilled Shopify copies
     * plus locally linked orders from the last 180 days.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingUnfulfilled(int $limit = 80): array
    {
        $limit = max(1, min(400, $limit));
        $marketplaces = MarketplaceManagerRegistry::slugs();
        $shopifyScanLimit = max(20, (int) ceil($limit * 0.55));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;

        $shopifyScan = $this->syncUnfulfilledShopifyCopies($shopifyScanLimit);
        $checked += (int) ($shopifyScan['checked'] ?? 0);
        $fulfilled += (int) ($shopifyScan['fulfilled'] ?? 0);
        $skipped += (int) ($shopifyScan['skipped'] ?? 0);
        $failed += (int) ($shopifyScan['failed'] ?? 0);

        $remaining = max(0, $limit - $checked);
        $queues = [];
        if ($remaining > 0 && $marketplaces !== []) {
            $perMarket = max(3, (int) ceil($remaining / count($marketplaces)));
            foreach ($marketplaces as $slug) {
                $ids = $this->pendingLinkedOrderIds($slug, $perMarket);
                if ($ids !== []) {
                    $queues[$slug] = $ids;
                }
            }
        }

        $progress = true;
        while ($checked < $limit && $progress && $queues !== []) {
            $progress = false;
            foreach (array_keys($queues) as $slug) {
                if ($checked >= $limit) {
                    break;
                }
                if (($queues[$slug] ?? []) === []) {
                    unset($queues[$slug]);
                    continue;
                }
                $orderId = (int) array_shift($queues[$slug]);
                if (($queues[$slug] ?? []) === []) {
                    unset($queues[$slug]);
                }
                if ($orderId < 1) {
                    continue;
                }
                $progress = true;
                $checked++;
                $result = $this->fulfillMarketplaceOrder((string) $slug, $orderId);
                $this->rememberAutoFetchResult((string) $slug, $orderId, $result);
                if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
                    $fulfilled++;
                } elseif (! empty($result['skipped']) || (($result['action'] ?? '') === 'already_on_shopify')) {
                    $skipped++;
                } else {
                    $failed++;
                }
                usleep(120000);
            }
        }

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Fetch tracking: checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * Fulfill Shopify copies for one channel (Veeqo/GOFO), then the hub pushes
     * tracking back to that marketplace. Used by each channel's tracking cron
     * so a missing Shopify label cannot starve Newegg/TikTok/Shein/etc.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingUnfulfilledForMarketplace(string $marketplace, int $limit = 40): array
    {
        $marketplace = strtolower(trim($marketplace));
        $limit = max(1, min(200, $limit));
        $ids = $this->pendingLinkedOrderIds($marketplace, $limit);
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($ids as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId < 1) {
                continue;
            }
            $checked++;
            $result = $this->fulfillMarketplaceOrder($marketplace, $orderId);
            $this->rememberAutoFetchResult($marketplace, $orderId, $result);
            if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
                $fulfilled++;
            } elseif (! empty($result['skipped']) || (($result['action'] ?? '') === 'already_on_shopify')) {
                $skipped++;
            } else {
                $failed++;
            }
            usleep(120000);
        }

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Fetch tracking ({$marketplace}): checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * Unfulfilled Marketplace Manager Shopify copies (any channel), even when
     * the local marketplace table has no shopify_order_id yet.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int}
     */
    public function syncUnfulfilledShopifyCopies(int $limit = 40): array
    {
        $limit = max(1, min(120, $limit));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->uniqueShopifyConfigs() as $config) {
            if ($checked >= $limit) {
                break;
            }
            $storeUrl = trim((string) ($config['store_url'] ?? ''));
            $token = trim((string) ($config['token'] ?? ''));
            if ($storeUrl === '' || $token === '') {
                continue;
            }

            $need = ($limit - $checked) * 3;
            foreach ($this->listUnfulfilledShopifyOrders($storeUrl, $token, $need) as $order) {
                if ($checked >= $limit) {
                    break;
                }
                $shopifyId = (string) ($order['id'] ?? '');
                if ($shopifyId === '' || $this->shopifyOrderLooksFba($order)) {
                    continue;
                }
                $refs = $this->marketplaceRefsFromShopifyOrder($order);
                if ($refs === []) {
                    continue;
                }
                $checked++;
                $cacheKey = 'mm_fetch_tracking_shopify_v2:'.$shopifyId;
                if (Cache::has($cacheKey)) {
                    $skipped++;
                    continue;
                }
                $local = $this->localTrackingFromShopifyOrder($order);
                $result = $this->fulfillShopifyFromLabels($shopifyId, $config, $refs, $local);
                $action = (string) ($result['action'] ?? '');
                if (! empty($result['success']) && $action === 'shopify_fulfilled') {
                    $fulfilled++;
                    Cache::put($cacheKey, 1, now()->addDays(7));
                    $amazonId = $this->amazonOrderIdFromShopifyOrder($order);
                    if ($amazonId !== '') {
                        $this->linkAmazonOrderToShopify($amazonId, $shopifyId);
                    }
                    $this->pushChannelTrackingForShopifyOrder($order, $shopifyId, $result);
                } elseif ($action === 'already_on_shopify') {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addDays(7));
                    $this->pushChannelTrackingForShopifyOrder($order, $shopifyId, $result);
                } elseif (in_array($action, ['tracking_not_found', 'not_linked'], true)) {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addMinutes(8));
                } elseif (! empty($result['skipped'])) {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addHours(2));
                } else {
                    $failed++;
                    Cache::put($cacheKey, 1, now()->addMinutes(2));
                }
                usleep(120000);
            }
        }

        return compact('checked', 'fulfilled', 'skipped', 'failed');
    }

    /**
     * @deprecated Use syncUnfulfilledShopifyCopies — kept so older callers still cover Amazon.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int}
     */
    public function syncUnfulfilledShopifyAmazon(int $limit = 40): array
    {
        return $this->syncUnfulfilledShopifyCopies($limit);
    }

    /**
     * Fulfill one Shopify Amazon copy from Veeqo/GOFO using the Amazon order id.
     *
     * @return array<string, mixed>
     */
    public function fulfillShopifyAmazonOrder(string $amazonOrderId, ?string $shopifyOrderName = null): array
    {
        $amazonOrderId = trim($amazonOrderId);
        if ($amazonOrderId === '') {
            return ['success' => false, 'message' => 'Amazon order id required.'];
        }
        $config = $this->shopifyConfigFor('amazon');
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are missing.'];
        }

        $shopify = $this->findShopifyOrderByAmazonId($storeUrl, $token, $amazonOrderId, $shopifyOrderName);
        if ($shopify === null) {
            return ['success' => false, 'message' => 'Shopify order not found for Amazon '.$amazonOrderId.'.'];
        }

        $result = $this->fulfillShopifyFromLabels(
            (string) $shopify['id'],
            $config,
            [$amazonOrderId, ltrim((string) ($shopify['name'] ?? ''), '#')]
        );
        if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
            $this->linkAmazonOrderToShopify($amazonOrderId, (string) $shopify['id']);
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listUnfulfilledShopifyOrders(string $storeUrl, string $token, int $limit): array
    {
        $out = [];
        $path = 'orders.json';
        $payload = [
            'status' => 'open',
            'fulfillment_status' => 'unfulfilled',
            'limit' => 50,
            'created_at_min' => now()->subDays(45)->toIso8601String(),
            'fields' => 'id,name,tags,note,note_attributes,fulfillment_status',
        ];
        for ($page = 0; $page < 8 && count($out) < $limit; $page++) {
            try {
                $res = $this->shopifyApi($storeUrl, $token, 'GET', $path, $payload);
            } catch (\Throwable $e) {
                break;
            }
            if ($res === null || ! $res->successful()) {
                break;
            }
            $chunk = $res->json('orders') ?? [];
            if (! is_array($chunk) || $chunk === []) {
                break;
            }
            foreach ($chunk as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $out[] = $order;
                if (count($out) >= $limit) {
                    break;
                }
            }
            $next = $this->shopifyNextPage($res);
            if ($next === null) {
                break;
            }
            $path = $next['path'];
            $payload = $next['query'];
        }

        return $out;
    }

    /**
     * @return array{id: int|string, name?: string}|null
     */
    protected function findShopifyOrderByAmazonId(string $storeUrl, string $token, string $amazonOrderId, ?string $shopifyOrderName = null): ?array
    {
        try {
            $gql = Http::withoutVerifying()->withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/graphql.json", [
                'query' => 'query ($q: String!) { orders(first: 8, query: $q) { edges { node { id name tags displayFulfillmentStatus } } } }',
                'variables' => [
                    'q' => 'tag:amazon-'.$amazonOrderId,
                ],
            ]);
            if ($gql->successful()) {
                foreach ($gql->json('data.orders.edges') ?? [] as $edge) {
                    $node = $edge['node'] ?? null;
                    if (! is_array($node)) {
                        continue;
                    }
                    $gid = (string) ($node['id'] ?? '');
                    if (preg_match('/Order\/(\d+)/', $gid, $m)) {
                        return ['id' => $m[1], 'name' => (string) ($node['name'] ?? '')];
                    }
                }
            }
        } catch (\Throwable $e) {
            // REST fallback below.
        }

        if ($shopifyOrderName) {
            try {
                $res = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders.json", [
                    'status' => 'any',
                    'name' => ltrim($shopifyOrderName, '#'),
                    'limit' => 5,
                    'fields' => 'id,name,tags,fulfillment_status',
                ]);
                foreach ($res->json('orders') ?? [] as $order) {
                    if (is_array($order) && ! empty($order['id'])) {
                        return $order;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        foreach ($this->listUnfulfilledShopifyOrders($storeUrl, $token, 80) as $order) {
            if ($this->amazonOrderIdFromShopifyOrder($order) === $amazonOrderId) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function amazonOrderIdFromShopifyOrder(array $order): string
    {
        $hay = trim((string) ($order['tags'] ?? '')).' '.trim((string) ($order['note'] ?? '')).' '.trim((string) ($order['name'] ?? ''));
        if (preg_match('/(\d{3}-\d{7}-\d{7})/', $hay, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Marketplace order ids from Shopify tags (amazon-…, ebay1-…, temu-…, etc).
     *
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function marketplaceRefsFromShopifyOrder(array $order): array
    {
        $tagsRaw = (string) ($order['tags'] ?? '');
        $note = (string) ($order['note'] ?? '');
        $hay = strtolower($tagsRaw.' '.$note);
        if (trim($hay) === '' && empty($order['note_attributes']) && trim((string) ($order['name'] ?? '')) === '') {
            return [];
        }

        $slugs = MarketplaceManagerRegistry::slugs();
        usort($slugs, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        $refs = [];
        $matched = false;
        foreach ($slugs as $slug) {
            $slug = strtolower((string) $slug);
            if ($slug === '' || ! preg_match('/(?:^|[\s,])'.preg_quote($slug, '/').'-([^\s,]+)/i', $hay, $m)) {
                continue;
            }
            $matched = true;
            $id = trim((string) ($m[1] ?? ''));
            if ($id !== '') {
                $refs[] = $id;
                $refs[] = $slug.'-'.$id;
            }
        }

        if (preg_match_all('/PO-\d[\w-]{6,}/i', $tagsRaw.' '.$note, $poMatches)) {
            $matched = true;
            foreach ($poMatches[0] as $po) {
                $refs[] = $po;
            }
        }

        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? ''));
            $val = trim((string) ($attr['value'] ?? ''));
            if ($val === '' || strlen($val) < 6) {
                continue;
            }
            if (str_contains($name, 'order') || str_contains($name, 'po_') || $name === 'po' || str_contains($name, 'track')) {
                $matched = true;
                $refs[] = $val;
            }
        }

        $amazonId = $this->amazonOrderIdFromShopifyOrder($order);
        if ($amazonId !== '') {
            $matched = true;
            $refs[] = $amazonId;
        }

        if (! $matched) {
            return [];
        }

        $name = ltrim((string) ($order['name'] ?? ''), '#');
        if ($name !== '') {
            $refs[] = $name;
        }

        $unique = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref !== '' && ! in_array($ref, $unique, true)) {
                $unique[] = $ref;
            }
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function shopifyOrderLooksFba(array $order): bool
    {
        $tags = strtolower((string) ($order['tags'] ?? ''));

        return (bool) preg_match('/(?:^|[\s,])(fba|afn)(?:[\s,]|$)/', $tags);
    }

    /**
     * @return list<array{store_url: string, token: string, store_key?: string}>
     */
    protected function uniqueShopifyConfigs(): array
    {
        $out = [];
        foreach (MarketplaceManagerRegistry::slugs() as $slug) {
            $config = $this->shopifyConfigFor($slug);
            $url = strtolower(trim((string) ($config['store_url'] ?? '')));
            $token = trim((string) ($config['token'] ?? ''));
            if ($url === '' || $token === '' || isset($out[$url])) {
                continue;
            }
            $out[$url] = $config;
        }

        return array_values($out);
    }

    protected function linkAmazonOrderToShopify(string $amazonOrderId, string $shopifyOrderId): void
    {
        if (! Schema::hasTable('amazon_orders') || ! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return;
        }
        try {
            AmazonOrder::query()
                ->where('amazon_order_id', $amazonOrderId)
                ->where(function ($q) {
                    $q->whereNull('shopify_order_id')->orWhere('shopify_order_id', '');
                })
                ->update(['shopify_order_id' => $shopifyOrderId]);
        } catch (\Throwable $e) {
            // Linking is best-effort.
        }
    }

    /**
     * @return array{shopify_order_id: string, shopify_config: array<string, string>, refs: list<string>}|null
     */
    public function contextForMarketplaceOrder(string $marketplace, int $orderId): ?array
    {
        $marketplace = strtolower(trim($marketplace));
        $row = $this->loadMarketplaceOrder($marketplace, $orderId);
        if ($row === null) {
            return null;
        }

        $shopifyOrderId = trim((string) ($row['shopify_order_id'] ?? ''));
        $refs = [];
        foreach ((array) ($row['refs'] ?? []) as $ref) {
            $ref = trim((string) $ref);
            if ($ref !== '' && ! in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }
        $marketplaceOrderIds = $refs;
        $strict = in_array($marketplace, ['ebay2', 'tiktok', 'aliexpress'], true);
        if ($shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual') && ! $strict) {
            $refs[] = $shopifyOrderId;
            foreach ($this->shopifyOrderNumberRefs($shopifyOrderId) as $num) {
                if (! in_array($num, $refs, true)) {
                    $refs[] = $num;
                }
            }
        }

        return [
            'shopify_order_id' => $shopifyOrderId,
            'shopify_config' => $this->shopifyConfigFor($marketplace),
            'refs' => $refs,
            'marketplace_order_ids' => $marketplaceOrderIds,
            'sku' => trim((string) ($row['sku'] ?? '')),
            'local_tracking' => is_array($row['local_tracking'] ?? null) ? $row['local_tracking'] : null,
        ];
    }

    /**
     * @return array{shopify_order_id: string, refs: list<string>}|null
     */
    protected function loadMarketplaceOrder(string $marketplace, int $orderId): ?array
    {
        if ($marketplace === 'amazon' && Schema::hasTable('amazon_orders')) {
            $order = AmazonOrder::query()->find($orderId);
            if ($order === null) {
                return null;
            }
            $raw = AmazonOrder::decodeRawPayload($order->raw_data ?? null);
            $seller = trim((string) ($raw['SellerOrderId'] ?? $raw['sellerOrderId'] ?? ''));

            return [
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'refs' => array_filter([
                    (string) $order->amazon_order_id,
                    $seller,
                ]),
                'sku' => $this->skuFromMarketplaceModel('amazon', $order),
                'local_tracking' => $this->trackingFromMixed($raw),
            ];
        }

        $simple = match ($marketplace) {
            'temu' => [TemuOrder::class, ['parent_order_sn', 'order_sn']],
            'temu2' => [Temu2Order::class, ['parent_order_sn', 'order_sn']],
            'ebay1' => [Ebay1OrderMetric::class, ['order_id', 'order_number']],
            'ebay2' => [Ebay2OrderMetric::class, ['order_id', 'order_number']],
            'ebay3' => [Ebay3OrderMetric::class, ['order_id', 'order_number']],
            'newegg' => [NeweggOrderMetric::class, ['order_id', 'order_number']],
            'shein' => [SheinOrderMetric::class, ['order_id', 'order_number']],
            'reverb' => [ReverbOrderMetric::class, ['order_id', 'order_number']],
            'faire' => [FaireOrderMetric::class, ['order_id', 'order_number']],
            'aliexpress' => [AliexpressOrderMetric::class, ['order_id', 'order_number']],
            'alibaba' => [AlibabaOrderMetric::class, ['order_id', 'order_number']],
            'topdawg' => [TopDawgOrderMetric::class, ['order_id', 'order_number']],
            'bestbuy' => [BestBuyOrderMetric::class, ['order_id', 'channel_order_id']],
            'macy' => [MacyOrderMetric::class, ['order_id', 'channel_order_id']],
            'wayfair' => [WayfairDailyData::class, ['po_number']],
            'purchasingpower' => [PurchasingPowerSale::class, ['order_id', 'order_number']],
            'doba' => [DobaDailyData::class, ['order_no', 'platform_order_no']],
            'tiktok' => [TiktokOrder::class, ['order_id']],
            'tiktok2' => [Tiktok2Order::class, ['order_id']],
            'pls' => [PlsSale::class, ['order_name', 'order_number']],
            default => null,
        };

        if ($simple === null) {
            return null;
        }

        [$class, $refFields] = $simple;
        $model = $class::query()->find($orderId);
        if ($model === null) {
            return null;
        }

        $refs = [];
        foreach ($refFields as $field) {
            $refs[] = (string) ($model->{$field} ?? '');
        }

        $local = $this->trackingFromModel($model);
        if ($local === null) {
            foreach (['raw_payload', 'raw_json', 'raw_data'] as $rawField) {
                $raw = $model->{$rawField} ?? null;
                if (is_array($raw)) {
                    $local = $this->trackingFromMixed($raw);
                    if ($local !== null) {
                        break;
                    }
                }
            }
        }
        if ($local === null && in_array($marketplace, ['ebay1', 'ebay2', 'ebay3'], true)) {
            $ebayOrderId = trim((string) ($model->order_id ?? ''));
            if ($ebayOrderId !== '') {
                $cacheKey = 'mm.ebay.pull-tracking.'.$marketplace.'.'.$ebayOrderId;
                $pulled = Cache::remember($cacheKey, now()->addMinutes(20), function () use ($marketplace, $ebayOrderId) {
                    return app(EbaySellFulfillmentTracking::class)->readTrackingFromEbay($marketplace, $ebayOrderId);
                });
                if (is_array($pulled) && trim((string) ($pulled['tracking'] ?? '')) !== '') {
                    $local = $pulled;
                }
            }
        }

        return [
            'shopify_order_id' => (string) ($model->shopify_order_id ?? ''),
            'refs' => $refs,
            'sku' => $this->skuFromMarketplaceModel($marketplace, $model),
            'local_tracking' => $local,
        ];
    }

    protected function skuFromMarketplaceModel(string $marketplace, object $model): string
    {
        $sku = match ($marketplace) {
            'tiktok', 'tiktok2' => (string) ($model->seller_sku ?? ''),
            default => (string) ($model->sku ?? ''),
        };

        return trim($sku);
    }

    /**
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, veeqo_order_id: ?int}|null
     */
    public function findVeeqoShipment(array $refs, bool $fast = false): ?array
    {
        $clean = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if (strlen($ref) < 6) {
                continue;
            }
            $variants = [$ref];
            if (str_contains($ref, '-')) {
                $variants[] = str_replace('-', '', $ref);
            }
            if (str_starts_with($ref, '#')) {
                $variants[] = ltrim($ref, '#');
            }
            $plain = ltrim($ref, '#');
            if ($plain !== $ref) {
                $variants[] = $plain;
            }
            if ($plain !== '' && ! str_starts_with(strtolower($plain), 'amz')) {
                $variants[] = 'Amz'.$plain;
            }
            if (preg_match('/^PO-(.+)$/i', $plain, $m)) {
                $variants[] = trim((string) $m[1]);
            }
            foreach ($variants as $candidate) {
                $candidate = trim($candidate);
                if (strlen($candidate) < 6 || $this->isShopifyInternalIdRef($candidate)) {
                    continue;
                }
                if (! in_array($candidate, $clean, true)) {
                    $clean[] = $candidate;
                }
            }
            if ($fast && count($clean) >= 2) {
                break;
            }
        }
        if ($clean === []) {
            return null;
        }

        foreach ($clean as $ref) {
            $hit = $this->searchVeeqoOrders($ref, $clean);
            if ($hit !== null) {
                return $hit;
            }
            if ($fast) {
                continue;
            }
            $hit = $this->searchVeeqoShipments($ref, $clean);
            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $allRefs
     * @return array{tracking: string, carrier: string, veeqo_order_id: ?int}|null
     */
    protected function searchVeeqoOrders(string $query, array $allRefs): ?array
    {
        $res = $this->veeqo->listOrders([
            'query' => $query,
            'page_size' => 25,
            'page' => 1,
        ]);
        if (empty($res['ok']) || ! is_array($res['data'] ?? null)) {
            return null;
        }

        $raw = $res['data'];
        $list = array_is_list($raw) ? $raw : (isset($raw['orders']) && is_array($raw['orders']) ? $raw['orders'] : []);

        $normalized = array_map(static fn ($r) => strtolower(preg_replace('/\s+/', '', (string) $r) ?? ''), $allRefs);

        foreach ($list as $order) {
            if (! is_array($order)) {
                continue;
            }
            if (! $this->orderLooksLikeRef($order, $normalized)) {
                continue;
            }
            $ship = $this->extractShipment($order);
            if ($ship !== null) {
                $ship['veeqo_order_id'] = isset($order['id']) && is_numeric($order['id']) ? (int) $order['id'] : null;

                return $ship;
            }
            if (isset($order['id']) && is_numeric($order['id'])) {
                $full = $this->veeqo->getOrder((int) $order['id']);
                if (! empty($full['ok']) && is_array($full['data'] ?? null)) {
                    $ship = $this->extractShipment($full['data']);
                    if ($ship !== null) {
                        $ship['veeqo_order_id'] = (int) $order['id'];

                        return $ship;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $allRefs
     * @return array{tracking: string, carrier: string, veeqo_order_id: ?int}|null
     */
    protected function searchVeeqoShipments(string $query, array $allRefs): ?array
    {
        $res = $this->veeqo->listShipments([
            'query' => $query,
            'page_size' => 25,
            'page' => 1,
        ]);
        if (empty($res['ok']) || ! is_array($res['data'] ?? null)) {
            return null;
        }

        $raw = $res['data'];
        $list = array_is_list($raw)
            ? $raw
            : (isset($raw['shipments']) && is_array($raw['shipments']) ? $raw['shipments'] : []);
        $normalized = array_map(static fn ($r) => strtolower(preg_replace('/\s+/', '', (string) $r) ?? ''), $allRefs);

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! $this->orderLooksLikeRef($row, $normalized)) {
                continue;
            }
            $ship = $this->extractShipment($row);
            if ($ship !== null) {
                $ship['veeqo_order_id'] = isset($row['order_id']) && is_numeric($row['order_id'])
                    ? (int) $row['order_id']
                    : (isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null);

                return $ship;
            }
        }

        return null;
    }

    /**
     * Accept a Veeqo/GOFO row only when an order-number field equals a ref.
     * Never substring-match the whole payload (Shopify #334262 can appear inside
     * phones, zips, or older order ids and attach the wrong label).
     *
     * @param  list<string>  $normalizedRefs
     */
    protected function orderLooksLikeRef(array $order, array $normalizedRefs): bool
    {
        foreach ($this->orderIdentityValues($order) as $value) {
            foreach ($normalizedRefs as $ref) {
                if ($this->orderRefsMatch($value, (string) $ref)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Order-id fields only — not tracking numbers, phones, addresses, or SKUs.
     *
     * @return list<string>
     */
    protected function orderIdentityValues(array $order): array
    {
        $keys = [
            'number',
            'order_number',
            'order_no',
            'order_id',
            'orderid',
            'channel_order_number',
            'channel_order_id',
            'channel_order_no',
            'customer_reference_number',
            'customer_reference',
            'reference_number',
            'reference',
            'remote_id',
            'remote_order_id',
            'remote_order_number',
            'shopify_id',
            'shopify_order_id',
            'shopify_order_number',
            'shopify_name',
            'marketplace_order_id',
            'marketplace_order_number',
            'platform_order_no',
            'platform_order_id',
            'platform_order_number',
            'ebay_order_id',
            'seller_order_id',
            'seller_order_number',
            'po_number',
            'purchase_order_number',
            'allocated_order_number',
        ];
        $out = [];
        $walk = static function ($node) use (&$walk, &$out, $keys): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                    continue;
                }
                $key = strtolower((string) $k);
                $s = trim((string) $v);
                if ($s === '') {
                    continue;
                }
                if (
                    in_array($key, $keys, true)
                    || str_ends_with($key, '_order_id')
                    || str_ends_with($key, '_order_number')
                    || str_ends_with($key, '_order_no')
                ) {
                    $out[] = $s;
                }
            }
        };
        $walk($order);

        return $out;
    }

    protected function orderRefsMatch(string $left, string $right): bool
    {
        $a = $this->normalizeOrderRef($left);
        $b = $this->normalizeOrderRef($right);
        if ($a === '' || $b === '' || strlen($a) < 6 || strlen($b) < 6) {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $aDash = str_replace('-', '', $a);
        $bDash = str_replace('-', '', $b);

        return $aDash === $bDash && (str_contains($a, '-') || str_contains($b, '-'));
    }

    protected function normalizeOrderRef(string $ref): string
    {
        return strtolower(preg_replace('/\s+/', '', ltrim(trim($ref), '#')) ?? '');
    }

    /**
     * Short all-digit Shopify names (#334262) collide inside Veeqo/GOFO search.
     */
    protected function isCollisionProneOrderRef(string $ref): bool
    {
        $n = $this->normalizeOrderRef($ref);

        return $n !== '' && (bool) preg_match('/^\d{5,10}$/', $n);
    }

    /**
     * Shopify Admin REST ids (typically 12–14 digits), not marketplace order numbers.
     */
    protected function isShopifyInternalIdRef(string $ref): bool
    {
        $n = $this->normalizeOrderRef($ref);

        return $n !== '' && (bool) preg_match('/^\d{12,14}$/', $n);
    }

    /**
     * @param  list<string>  $refs
     * @return list<string>
     */
    protected function strongMarketplaceRefs(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '' || $this->isCollisionProneOrderRef($ref) || $this->isShopifyInternalIdRef($ref)) {
                continue;
            }
            if (str_starts_with($ref, 'gid://') || str_starts_with($ref, 'https://')) {
                continue;
            }
            if (! in_array($ref, $out, true)) {
                $out[] = $ref;
            }
        }

        return $out;
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function extractShipment(array $order): ?array
    {
        $buckets = [];
        if (isset($order['allocations']) && is_array($order['allocations'])) {
            $buckets = array_merge($buckets, $order['allocations']);
        }
        if (isset($order['shipments']) && is_array($order['shipments'])) {
            foreach ($order['shipments'] as $shipment) {
                $buckets[] = ['shipment' => $shipment];
            }
        }

        foreach ($buckets as $row) {
            if (! is_array($row)) {
                continue;
            }
            $shipment = is_array($row['shipment'] ?? null) ? $row['shipment'] : $row;
            $tracking = $this->trackingNumberFrom($shipment);
            if ($tracking === null) {
                continue;
            }
            $carrier = $this->carrierFrom($shipment, $row, $tracking);

            return ['tracking' => $tracking, 'carrier' => $carrier];
        }

        $direct = $this->trackingNumberFrom($order);
        if ($direct !== null) {
            return ['tracking' => $direct, 'carrier' => $this->carrierFrom($order, [], $direct)];
        }

        return null;
    }

    protected function trackingNumberFrom(array $row): ?string
    {
        $candidates = [
            $row['tracking_number'] ?? null,
            $row['trackingNumber'] ?? null,
            $row['tracking'] ?? null,
            $row['shipment_tracking_number'] ?? null,
            $row['mail_tracking_number'] ?? null,
        ];
        foreach ($candidates as $raw) {
            if (is_array($raw)) {
                $raw = $raw['tracking_number'] ?? $raw['number'] ?? $raw['value'] ?? null;
            }
            $tn = strtoupper(preg_replace('/\s+/', '', (string) $raw) ?? '');
            if ($tn !== '') {
                return $tn;
            }
        }

        return null;
    }

    protected function carrierFrom(array $shipment, array $parent, string $tracking = ''): string
    {
        $name = '';
        foreach ([
            $shipment['carrier']['name'] ?? null,
            $shipment['carrier_name'] ?? null,
            $shipment['service_carrier'] ?? null,
            $parent['carrier']['name'] ?? null,
        ] as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                $name = $c;
                break;
            }
        }

        return $this->shopifyCarrierName($name, $tracking);
    }

    protected function shopifyCarrierName(string $name, string $tracking = ''): string
    {
        $fromTn = $this->carrierFromTrackingNumber($tracking);
        $hay = strtolower($name);
        if (str_contains($hay, 'usps') || str_contains($hay, 'postal') || str_contains($hay, 'buy shipping')) {
            return $fromTn ?: 'USPS';
        }
        if (str_contains($hay, 'ups') && ! str_contains($hay, 'usps')) {
            return $fromTn ?: 'UPS';
        }
        if (str_contains($hay, 'fedex') || str_contains($hay, 'federal express')) {
            return $fromTn ?: 'FedEx';
        }
        if (str_contains($hay, 'dhl')) {
            return 'DHL';
        }
        if (str_contains($hay, 'ontrac')) {
            return 'OnTrac';
        }
        if (str_contains($hay, 'gofo')) {
            return 'GOFO';
        }

        return $fromTn ?: ($name !== '' ? $name : 'Other');
    }

    protected function carrierFromTrackingNumber(string $tracking): ?string
    {
        $tn = strtoupper(preg_replace('/\s+/', '', $tracking) ?? '');
        if ($tn === '') {
            return null;
        }
        if (preg_match('/^9\d{19,21}$/', $tn) || preg_match('/^420\d{20,}$/', $tn)) {
            return 'USPS';
        }
        if (str_starts_with($tn, '1Z')) {
            return 'UPS';
        }
        if (preg_match('/^\d{12,15}$/', $tn)) {
            return 'FedEx';
        }

        return null;
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array{success: bool, already?: bool, message: string}
     */
    protected function createShopifyFulfillment(
        array $config,
        string $shopifyOrderId,
        string $tracking,
        string $carrier,
        string $sku = ''
    ): array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are missing for this marketplace.'];
        }

        try {
            $orderRes = $this->shopifyApi(
                    $storeUrl,
                    $token,
                    'GET',
                    "orders/{$shopifyOrderId}.json",
                    ['fields' => 'id,fulfillments,fulfillment_status']
                );

            if ($orderRes->successful()) {
                foreach ($orderRes->json('order.fulfillments') ?? [] as $fulfillment) {
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
                        $n = strtoupper(preg_replace('/\s+/', '', (string) $n) ?? '');
                        if ($n !== '' && $n === $tracking) {
                            return ['success' => true, 'already' => true, 'message' => 'Tracking already on Shopify.'];
                        }
                    }
                }
            }

            $prepared = $this->prepareShopifyFulfillmentOrders($storeUrl, $token, $shopifyOrderId, false, $sku);
            if (($prepared['error'] ?? null) !== null) {
                return ['success' => false, 'message' => (string) $prepared['error']];
            }
            $lineItems = $prepared['line_items'] ?? [];
            if ($lineItems === []) {
                if (trim($sku) !== '') {
                    return [
                        'success' => false,
                        'message' => 'No open Shopify fulfillment lines match this marketplace SKU.',
                    ];
                }
                $updated = $this->updateExistingShopifyFulfillmentTracking($storeUrl, $token, $shopifyOrderId, $tracking, $carrier);
                if (! empty($updated['success'])) {
                    return $updated;
                }

                return [
                    'success' => false,
                    'message' => 'Shopify has no open fulfillment orders to fulfill (already fulfilled, on hold, or assigned to a service).',
                ];
            }

            $payload = [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItems,
                    'tracking_info' => [
                        'number' => $tracking,
                        'company' => mb_substr($carrier, 0, 100),
                    ],
                    'notify_customer' => false,
                ],
            ];

            $post = $this->shopifyApi($storeUrl, $token, 'POST', 'fulfillments.json', $payload);
            if (! $post->successful() && $post->status() === 422 && $this->shopifyFulfillmentNeedsLocationRetry((string) $post->body())) {
                $retried = $this->prepareShopifyFulfillmentOrders($storeUrl, $token, $shopifyOrderId, true, $sku);
                if (($retried['line_items'] ?? []) !== []) {
                    $payload['fulfillment']['line_items_by_fulfillment_order'] = $retried['line_items'];
                    $post = $this->shopifyApi($storeUrl, $token, 'POST', 'fulfillments.json', $payload);
                }
            }

            if (! $post->successful()) {
                $snippet = $this->shopifyErrorSnippet($post);
                Log::warning('VeeqoShopifyFulfillmentService: Shopify fulfill failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $post->status(),
                    'body' => mb_substr((string) $post->body(), 0, 400),
                ]);

                return ['success' => false, 'message' => 'Shopify fulfill failed (HTTP '.$post->status().'): '.$snippet];
            }

            return ['success' => true, 'message' => 'Shopify fulfilled.'];
        } catch (\Throwable $e) {
            Log::warning('VeeqoShopifyFulfillmentService: exception', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * When fulfillment orders are already closed, attach tracking to the existing fulfillment.
     *
     * @return array{success: bool, already?: bool, message: string}
     */
    protected function updateExistingShopifyFulfillmentTracking(
        string $storeUrl,
        string $token,
        string $shopifyOrderId,
        string $tracking,
        string $carrier
    ): array {
        $orderRes = $this->shopifyApi(
            $storeUrl,
            $token,
            'GET',
            "orders/{$shopifyOrderId}.json",
            ['fields' => 'id,fulfillments']
        );
        if (! $orderRes->successful()) {
            return ['success' => false, 'message' => 'Could not load Shopify fulfillments to update tracking.'];
        }

        $want = strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
        foreach ($orderRes->json('order.fulfillments') ?? [] as $fulfillment) {
            if (! is_array($fulfillment) || empty($fulfillment['id'])) {
                continue;
            }
            $status = strtolower((string) ($fulfillment['status'] ?? ''));
            if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                continue;
            }

            $existing = '';
            if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                $existing = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
            }
            if ($existing === '' && ! empty($fulfillment['tracking_number'])) {
                $existing = trim((string) $fulfillment['tracking_number']);
            }
            $existingNorm = strtoupper(preg_replace('/\s+/', '', $existing) ?? $existing);
            if ($existingNorm !== '' && $existingNorm === $want) {
                return ['success' => true, 'already' => true, 'message' => 'Tracking already on Shopify.'];
            }

            $post = $this->shopifyApi(
                $storeUrl,
                $token,
                'POST',
                'fulfillments/'.((int) $fulfillment['id']).'/update_tracking.json',
                [
                    'fulfillment' => [
                        'notify_customer' => false,
                        'tracking_info' => [
                            'number' => $tracking,
                            'company' => mb_substr($carrier, 0, 100),
                        ],
                    ],
                ]
            );
            if ($post->successful()) {
                return ['success' => true, 'message' => 'Shopify fulfillment tracking updated.'];
            }

            Log::warning('VeeqoShopifyFulfillmentService: update_tracking failed', [
                'shopify_order_id' => $shopifyOrderId,
                'fulfillment_id' => $fulfillment['id'],
                'status' => $post->status(),
                'body' => mb_substr((string) $post->body(), 0, 300),
            ]);
        }

        return ['success' => false, 'message' => 'No Shopify fulfillment available to attach tracking.'];
    }

    protected function releaseShopifyFulfillmentHold(string $storeUrl, string $token, int $fulfillmentOrderId): bool
    {
        if ($fulfillmentOrderId < 1) {
            return false;
        }

        try {
            $res = $this->shopifyApi(
                $storeUrl,
                $token,
                'POST',
                "fulfillment_orders/{$fulfillmentOrderId}/release_hold.json"
            );
            if ($res->successful()) {
                return true;
            }
            Log::info('VeeqoShopifyFulfillmentService: fulfillment hold not released', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'status' => $res->status(),
                'body' => mb_substr($res->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::info('VeeqoShopifyFulfillmentService: fulfillment hold release failed', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Release holds, move FOs that cannot be fulfilled in place, and collect FO line items.
     *
     * @return array{line_items: list<array<string, mixed>>, error: string|null}
     */
    protected function prepareShopifyFulfillmentOrders(
        string $storeUrl,
        string $token,
        string $shopifyOrderId,
        bool $forceMove = false,
        string $sku = ''
    ): array
    {
        $foRes = $this->shopifyApi($storeUrl, $token, 'GET', "orders/{$shopifyOrderId}/fulfillment_orders.json");
        if (! $foRes->successful()) {
            return ['line_items' => [], 'error' => 'Could not load Shopify fulfillment orders (HTTP '.$foRes->status().').'];
        }

        $orders = $foRes->json('fulfillment_orders') ?? [];
        if (! is_array($orders)) {
            $orders = [];
        }

        $released = false;
        foreach ($orders as $fo) {
            if (! is_array($fo) || empty($fo['id'])) {
                continue;
            }
            $status = strtolower((string) ($fo['status'] ?? ''));
            $actions = $this->shopifyFulfillmentActions($fo);
            if ($status === 'on_hold' || in_array('release_hold', $actions, true)) {
                if ($this->releaseShopifyFulfillmentHold($storeUrl, $token, (int) $fo['id'])) {
                    $released = true;
                }
            }
        }
        if ($released) {
            $foRes = $this->shopifyApi($storeUrl, $token, 'GET', "orders/{$shopifyOrderId}/fulfillment_orders.json");
            if ($foRes->successful() && is_array($foRes->json('fulfillment_orders'))) {
                $orders = $foRes->json('fulfillment_orders');
            }
        }

        $orderLines = [];
        if (trim($sku) !== '') {
            try {
                $orderRes = $this->shopifyApi(
                    $storeUrl,
                    $token,
                    'GET',
                    "orders/{$shopifyOrderId}.json",
                    ['fields' => 'id,line_items']
                );
                if ($orderRes->successful() && is_array($orderRes->json('order.line_items'))) {
                    $orderLines = $orderRes->json('order.line_items');
                }
            } catch (\Throwable) {
                $orderLines = [];
            }
        }

        $locationId = null;
        $lineItems = [];
        foreach ($orders as $fo) {
            if (! is_array($fo) || empty($fo['id'])) {
                continue;
            }
            $status = strtolower((string) ($fo['status'] ?? ''));
            if (! in_array($status, ['open', 'in_progress', 'scheduled', 'incomplete'], true)) {
                continue;
            }
            $actions = $this->shopifyFulfillmentActions($fo);
            $canCreate = $actions === [] || in_array('create_fulfillment', $actions, true);
            $canMove = in_array('move', $actions, true);
            $foId = (int) $fo['id'];

            if ($forceMove || (! $canCreate && $canMove)) {
                if ($locationId === null) {
                    $locationId = $this->shopifyMerchantLocationId($storeUrl, $token, $fo);
                }
                if ($locationId) {
                    $moved = $this->moveShopifyFulfillmentOrder($storeUrl, $token, $foId, $locationId);
                    if (is_array($moved) && ! empty($moved['id'])) {
                        $fo = $moved;
                        $foId = (int) $fo['id'];
                        $actions = $this->shopifyFulfillmentActions($fo);
                        $canCreate = $actions === [] || in_array('create_fulfillment', $actions, true);
                    }
                }
            }

            if (! $canCreate && $actions !== []) {
                continue;
            }

            $rawLines = $fo['line_items'] ?? null;
            $items = is_array($rawLines) ? $this->shopifyFulfillmentOrderLineItems($fo, $sku, $orderLines) : [];
            if (is_array($rawLines) && $rawLines !== [] && $items === []) {
                continue;
            }

            $entry = ['fulfillment_order_id' => $foId];
            if ($items !== []) {
                $entry['fulfillment_order_line_items'] = $items;
            }
            $lineItems[] = $entry;
        }

        return ['line_items' => $lineItems, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $fo
     * @return list<string>
     */
    protected function shopifyFulfillmentActions(array $fo): array
    {
        $out = [];
        foreach ((array) ($fo['supported_actions'] ?? []) as $action) {
            $action = strtolower(trim((string) $action));
            if ($action !== '' && ! in_array($action, $out, true)) {
                $out[] = $action;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fo
     * @param  list<array<string, mixed>>  $orderLines
     * @return list<array{id: int, quantity: int}>
     */
    protected function shopifyFulfillmentOrderLineItems(array $fo, string $sku = '', array $orderLines = []): array
    {
        $items = [];
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $want = $matcher->normalizeSku($sku);
        foreach ($fo['line_items'] ?? [] as $li) {
            if (! is_array($li) || empty($li['id'])) {
                continue;
            }
            if ($want !== '') {
                $lineSku = $matcher->normalizeSku((string) ($li['sku'] ?? ''));
                if ($lineSku === '') {
                    $lineItemId = (string) ($li['line_item_id'] ?? '');
                    foreach ($orderLines as $orderLine) {
                        if (! is_array($orderLine)) {
                            continue;
                        }
                        if ($lineItemId !== '' && (string) ($orderLine['id'] ?? '') === $lineItemId) {
                            $lineSku = $matcher->normalizeSku((string) ($orderLine['sku'] ?? ''));
                            break;
                        }
                    }
                }
                if ($lineSku === '' || ! $matcher->skusEqual($lineSku, $want)) {
                    continue;
                }
            }
            $qty = (int) ($li['fulfillable_quantity'] ?? 0);
            if ($qty < 1) {
                $qty = (int) ($li['quantity'] ?? 0) - (int) ($li['fulfilled_quantity'] ?? 0);
            }
            if ($qty < 1) {
                continue;
            }
            $items[] = ['id' => (int) $li['id'], 'quantity' => $qty];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $fo
     */
    protected function shopifyMerchantLocationId(string $storeUrl, string $token, array $fo): ?int
    {
        $assigned = (int) ($fo['assigned_location_id'] ?? 0);
        try {
            $res = $this->shopifyApi($storeUrl, $token, 'GET', 'locations.json');
        } catch (\Throwable $e) {
            return $assigned > 0 ? $assigned : null;
        }
        if (! $res->successful()) {
            return $assigned > 0 ? $assigned : null;
        }

        $preferred = null;
        foreach ($res->json('locations') ?? [] as $loc) {
            if (! is_array($loc) || empty($loc['id']) || empty($loc['active'])) {
                continue;
            }
            $id = (int) $loc['id'];
            $name = strtolower((string) ($loc['name'] ?? ''));
            if (str_contains($name, 'fulfillment service')) {
                continue;
            }
            if ($preferred === null) {
                $preferred = $id;
            }
            if ($assigned > 0 && $id !== $assigned) {
                return $id;
            }
        }

        return $preferred ?: ($assigned > 0 ? $assigned : null);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function moveShopifyFulfillmentOrder(string $storeUrl, string $token, int $fulfillmentOrderId, int $locationId): ?array
    {
        if ($fulfillmentOrderId < 1 || $locationId < 1) {
            return null;
        }

        try {
            $res = $this->shopifyApi(
                $storeUrl,
                $token,
                'POST',
                "fulfillment_orders/{$fulfillmentOrderId}/move.json",
                ['fulfillment_order' => ['new_location_id' => $locationId]]
            );
            if ($res->successful()) {
                $moved = $res->json('moved_fulfillment_order');

                return is_array($moved) && ! empty($moved['id']) ? $moved : null;
            }
            Log::info('VeeqoShopifyFulfillmentService: fulfillment order move failed', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'location_id' => $locationId,
                'status' => $res->status(),
                'body' => mb_substr((string) $res->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::info('VeeqoShopifyFulfillmentService: fulfillment order move exception', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function shopifyFulfillmentNeedsLocationRetry(string $body): bool
    {
        $body = strtolower($body);

        return str_contains($body, 'location')
            || str_contains($body, 'create_fulfillment')
            || str_contains($body, 'assigned')
            || str_contains($body, 'fulfillment order')
            || str_contains($body, 'on hold')
            || str_contains($body, 'on_hold');
    }

    protected function shopifyErrorSnippet($response): string
    {
        $json = method_exists($response, 'json') ? $response->json() : null;
        if (is_array($json)) {
            $errors = $json['errors'] ?? $json['error'] ?? null;
            if (is_string($errors) && trim($errors) !== '') {
                return mb_substr(trim($errors), 0, 180);
            }
            if (is_array($errors) && $errors !== []) {
                $encoded = json_encode($errors);

                return mb_substr(is_string($encoded) ? $encoded : 'Shopify error', 0, 180);
            }
        }

        return mb_substr(trim((string) (method_exists($response, 'body') ? $response->body() : '')), 0, 180);
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return list<string>
     */
    protected function shopifyDisplayNameRefs(array $config, string $shopifyOrderId): array
    {
        $fromDb = $this->shopifyOrderNumberRefs($shopifyOrderId);
        if ($fromDb !== []) {
            return $fromDb;
        }

        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return [];
        }

        try {
            $res = $this->shopifyApi($storeUrl, $token, 'GET', "orders/{$shopifyOrderId}.json", ['fields' => 'id,name']);
            $name = ltrim((string) ($res->json('order.name') ?? ''), '#');

            return $name !== '' ? [$name] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{tracking: string, carrier: string}|null
     */
    protected function localTrackingFromShopifyOrder(array $order): ?array
    {
        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? ''));
            $val = trim((string) ($attr['value'] ?? ''));
            if ($val === '' || strlen($val) < 8 || ! str_contains($name, 'track')) {
                continue;
            }

            return ['tracking' => $val, 'carrier' => 'Other'];
        }

        $note = (string) ($order['note'] ?? '');
        if (preg_match('/Tracking:\s*([A-Za-z0-9]{8,})/', $note, $m)) {
            return ['tracking' => $m[1], 'carrier' => 'Other'];
        }

        return null;
    }

    /**
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfigFor(string $marketplace): array
    {
        $settings = MarketplaceSyncSettings::getFor($marketplace);
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return $this->stores->getConfigForStore($storeKey);
    }

    /**
     * Shopify REST call with Retry-After handling for 429s.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function shopifyApi(string $storeUrl, string $token, string $method, string $path, array $payload = [])
    {
        $url = "https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/{$path}";
        $last = null;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                $req = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30);
                $last = strtoupper($method) === 'POST'
                    ? $req->post($url, $payload)
                    : $req->get($url, $payload);
            } catch (\Throwable $e) {
                if ($attempt >= 4) {
                    throw $e;
                }
                sleep(2 * $attempt);
                continue;
            }
            if ($last->status() !== 429) {
                return $last;
            }
            $wait = (int) ($last->header('Retry-After') ?: (2 * $attempt));
            sleep(max(2, min(20, $wait)));
        }

        return $last;
    }

    /**
     * @return array{path: string, query: array<string, mixed>}|null
     */
    protected function shopifyNextPage($response): ?array
    {
        if ($response === null) {
            return null;
        }
        $link = $response->header('Link') ?: $response->header('link');
        if (! is_string($link) || ! preg_match('/<([^>]+)>;\s*rel="next"/i', $link, $m)) {
            return null;
        }
        $parsed = parse_url($m[1]);
        $path = (string) ($parsed['path'] ?? '');
        $apiPath = 'orders.json';
        if (preg_match('#admin/api/[^/]+/(.+)$#', $path, $pm)) {
            $apiPath = $pm[1];
        }
        $query = [];
        parse_str((string) ($parsed['query'] ?? ''), $query);

        return ['path' => $apiPath, 'query' => $query];
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function trackingFromModel(object $model): ?array
    {
        $tn = trim((string) ($model->tracking_number ?? ''));
        if (strlen($tn) < 8) {
            return null;
        }

        $carrier = trim((string) (
            $model->carrier
            ?? $model->carrier_name
            ?? $model->shipping_company
            ?? $model->shipping_provider
            ?? 'Other'
        ));

        return ['tracking' => strtoupper(preg_replace('/\s+/', '', $tn) ?? $tn), 'carrier' => $carrier !== '' ? $carrier : 'Other'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{tracking: string, carrier: string}|null
     */
    protected function trackingFromMixed(array $data): ?array
    {
        $tracking = null;
        $carrier = '';
        $walk = static function ($value, $key = '') use (&$walk, &$tracking, &$carrier): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, (string) $k);
                }

                return;
            }
            $k = strtolower((string) $key);
            $s = trim((string) $value);
            if ($s === '') {
                return;
            }
            if ($tracking === null && preg_match('/^(tracking(_)?(number|no|id)?|shipmenttrackingnumber|waybill|mail.?no|logistics.?no)$/', $k)) {
                $tn = strtoupper(preg_replace('/\s+/', '', $s) ?? '');
                if (strlen($tn) >= 8 && ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn)) {
                    $tracking = $tn;
                }
            }
            if ($carrier === '' && preg_match('/carrier|shipping.?company|logistics.?company|shippingcarriercode/', $k) && ! is_numeric($s)) {
                $carrier = $s;
            }
        };
        $walk($data);

        if ($tracking === null) {
            return null;
        }

        return ['tracking' => $tracking, 'carrier' => $carrier !== '' ? $carrier : 'Other'];
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array{tracking: string, carrier: string}|null
     */
    protected function existingShopifyTracking(
        array $config,
        string $shopifyOrderId,
        string $sku = '',
        array $marketplaceOrderIds = []
    ): ?array {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return null;
        }

        $sku = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
        $orderIds = app(ShopifyFulfillmentTrackingMatcher::class)->uniqueIds($marketplaceOrderIds);
        if ($sku !== '' && $orderIds !== []) {
            $matched = app(ShopifyFulfillmentTrackingMatcher::class)->match(
                $config,
                $shopifyOrderId,
                (string) $orderIds[0],
                $sku,
                array_slice($orderIds, 1),
                'VeeqoShopifyFulfillmentService'
            );
            if (empty($matched['tracking'])) {
                return null;
            }

            return [
                'tracking' => strtoupper(preg_replace('/\s+/', '', (string) $matched['tracking']) ?? (string) $matched['tracking']),
                'carrier' => trim((string) ($matched['carrier'] ?? '')) ?: 'Other',
            ];
        }

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
            ]);
            if (! $response->successful()) {
                return null;
            }
            foreach ($response->json('order.fulfillments') ?? [] as $fulfillment) {
                if (! is_array($fulfillment)) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                $number = '';
                if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $number = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
                }
                if ($number === '' && ! empty($fulfillment['tracking_number'])) {
                    $number = trim((string) $fulfillment['tracking_number']);
                }
                if ($number === '') {
                    continue;
                }

                return [
                    'tracking' => strtoupper(preg_replace('/\s+/', '', $number) ?? $number),
                    'carrier' => trim((string) ($fulfillment['tracking_company'] ?? '')) ?: 'Other',
                ];
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array<string, mixed>|null
     */
    protected function shopifyOrderPayload(array $config, string $shopifyOrderId): ?array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $shopifyOrderId = trim($shopifyOrderId);
        if ($storeUrl === '' || $token === '' || $shopifyOrderId === '') {
            return null;
        }

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,name,tags,note,note_attributes,source_identifier,line_items,fulfillments',
            ]);
            if (! $response->successful()) {
                return null;
            }
            $order = $response->json('order');

            return is_array($order) ? $order : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<int>
     */
    protected function pendingLinkedOrderIds(string $marketplace, int $limit): array
    {
        $since = now()->subDays(180);
        $limit = max(1, min(80, $limit));

        if ($marketplace === 'amazon' && Schema::hasTable('amazon_orders') && Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            $ids = AmazonOrder::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->where('shopify_order_id', 'not like', 'manual%')
                ->where(function ($q) {
                    $q->whereNull('fulfillment_channel')->orWhere('fulfillment_channel', '!=', 'AFN');
                })
                ->where(function ($q) {
                    $q->whereNull('status')->orWhereNotIn('status', ['Canceled', 'Cancelled']);
                })
                ->where(function ($q) use ($since) {
                    $q->where('order_date', '>=', $since)->orWhere('created_at', '>=', $since);
                })
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->limit(max(80, $limit * 40))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return $this->filterAutoFetchCandidates('amazon', $ids, $limit);
        }

        $map = [
            'temu' => [TemuOrder::class, 'parent_order_time'],
            'temu2' => [Temu2Order::class, 'parent_order_time'],
            'ebay1' => [Ebay1OrderMetric::class, 'order_date'],
            'ebay2' => [Ebay2OrderMetric::class, 'order_date'],
            'ebay3' => [Ebay3OrderMetric::class, 'order_date'],
            'newegg' => [NeweggOrderMetric::class, 'order_date'],
            'shein' => [SheinOrderMetric::class, 'order_date'],
            'reverb' => [ReverbOrderMetric::class, 'order_date'],
            'faire' => [FaireOrderMetric::class, 'order_date'],
            'tiktok' => [TiktokOrder::class, 'order_created_at'],
            'tiktok2' => [Tiktok2Order::class, 'order_created_at'],
            'aliexpress' => [AliexpressOrderMetric::class, 'order_date'],
            'alibaba' => [AlibabaOrderMetric::class, 'order_date'],
            'topdawg' => [TopDawgOrderMetric::class, 'order_date'],
            'bestbuy' => [BestBuyOrderMetric::class, 'order_created_at'],
            'macy' => [MacyOrderMetric::class, 'order_created_at'],
            'wayfair' => [WayfairDailyData::class, 'po_date'],
            'purchasingpower' => [PurchasingPowerSale::class, 'date_created'],
            'doba' => [DobaDailyData::class, 'order_time'],
            'pls' => [PlsSale::class, 'order_date'],
        ];
        if (! isset($map[$marketplace])) {
            return [];
        }
        [$class, $dateCol] = $map[$marketplace];

        try {
            $model = new $class;
            $table = $model->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'shopify_order_id')) {
                return [];
            }

            $query = $class::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->where('shopify_order_id', 'not like', 'manual%');

            if ($marketplace === 'pls' && Schema::hasColumn($table, 'fulfillment_status')) {
                $query->where(function ($q) {
                    $q->whereNull('fulfillment_status')
                        ->orWhereNotIn('fulfillment_status', ['fulfilled']);
                });
            }
            if ($marketplace === 'pls' && Schema::hasColumn($table, 'cancelled_at')) {
                $query->whereNull('cancelled_at');
            }

            if (Schema::hasColumn($table, $dateCol)) {
                $query->where(function ($q) use ($dateCol, $since, $table) {
                    $q->where($dateCol, '>=', $since);
                    if (Schema::hasColumn($table, 'created_at')) {
                        $q->orWhere('created_at', '>=', $since);
                    }
                });
            } elseif (Schema::hasColumn($table, 'created_at')) {
                $query->where('created_at', '>=', $since);
            }

            $uniqueCol = match ($marketplace) {
                'pls' => 'shopify_order_id',
                'wayfair' => 'po_number',
                'doba' => 'order_no',
                'purchasingpower' => 'order_number',
                'temu', 'temu2' => 'parent_order_sn',
                'bestbuy', 'macy', 'aliexpress', 'alibaba', 'topdawg',
                'newegg', 'shein', 'reverb', 'tiktok', 'tiktok2', 'faire',
                'ebay1', 'ebay2', 'ebay3' => 'order_id',
                default => null,
            };

            if ($uniqueCol && Schema::hasColumn($table, $uniqueCol)) {
                $ids = [];
                $seen = [];
                if (Schema::hasColumn($table, $dateCol)) {
                    $query->orderByDesc($dateCol);
                }
                $query->orderByDesc('id')->limit($limit * 40);
                foreach ($query->get(['id', $uniqueCol]) as $row) {
                    $key = trim((string) ($row->{$uniqueCol} ?? ''));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $ids[] = (int) $row->id;
                }

                return $this->filterAutoFetchCandidates($marketplace, $ids, $limit);
            }

            if (Schema::hasColumn($table, $dateCol)) {
                $query->orderByDesc($dateCol);
            }
            $ids = $query->orderByDesc('id')
                ->limit(max(80, $limit * 40))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return $this->filterAutoFetchCandidates($marketplace, $ids, $limit);
        } catch (\Throwable $e) {
            Log::info('VeeqoShopifyFulfillmentService: pending query skipped', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Skip orders we already fulfilled, or recently checked with no label yet,
     * so auto-run rotates through older orders instead of the same newest IDs.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    protected function filterAutoFetchCandidates(string $marketplace, array $ids, int $limit): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            if (Cache::has($this->autoFetchCacheKey($marketplace, $id, 'done'))) {
                continue;
            }
            if (Cache::has($this->autoFetchCacheKey($marketplace, $id, 'miss'))) {
                continue;
            }
            $out[] = $id;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function rememberAutoFetchResult(string $marketplace, int $orderId, array $result): void
    {
        $action = (string) ($result['action'] ?? '');
        if (in_array($action, ['shopify_fulfilled', 'already_on_shopify'], true)) {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'done'), 1, now()->addDays(7));

            return;
        }
        if (in_array($action, ['tracking_not_found', 'not_linked', 'unsupported'], true)
            || (! empty($result['skipped']) && $action !== 'shopify_fulfilled')) {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'miss'), 1, now()->addMinutes(8));
        }
    }

    protected function autoFetchCacheKey(string $marketplace, int $orderId, string $kind): string
    {
        return 'mm_fetch_tracking_'.$kind.':'.$marketplace.':'.$orderId;
    }

    /**
     * Write found tracking onto the marketplace order + Shopify cache so SOF can show it
     * even when Amazon/eBay already say SHIPPED/FULFILLED.
     */
    public function persistTrackingOntoMarketplaceOrder(
        string $marketplace,
        int $orderId,
        string $shopifyOrderId,
        string $tracking,
        string $carrier = ''
    ): void {
        $tn = strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
        if ($tn === '' || strlen($tn) < 8) {
            return;
        }
        $carrier = trim($carrier);

        $this->rememberShopifyTracking($shopifyOrderId, $tn, $carrier);
        $this->enrollCarrierTrackingNumber($tn, $carrier);

        try {
            if ($marketplace === 'amazon') {
                $order = AmazonOrder::query()->find($orderId);
                if ($order === null) {
                    return;
                }
                $raw = AmazonOrder::decodeRawPayload($order->raw_data ?? null);
                $raw['tracking_number'] = $tn;
                if ($carrier !== '') {
                    $raw['carrier'] = $carrier;
                }
                $order->raw_data = $raw;
                $order->save();

                return;
            }

            $row = $this->loadMarketplaceOrder($marketplace, $orderId);
            if ($row === null) {
                return;
            }
            $class = match ($marketplace) {
                'temu' => TemuOrder::class,
                'temu2' => Temu2Order::class,
                'ebay1' => Ebay1OrderMetric::class,
                'ebay2' => Ebay2OrderMetric::class,
                'ebay3' => Ebay3OrderMetric::class,
                'newegg' => NeweggOrderMetric::class,
                'shein' => SheinOrderMetric::class,
                'reverb' => ReverbOrderMetric::class,
                'faire' => FaireOrderMetric::class,
                'aliexpress' => AliexpressOrderMetric::class,
                'alibaba' => AlibabaOrderMetric::class,
                'topdawg' => TopDawgOrderMetric::class,
                'bestbuy' => BestBuyOrderMetric::class,
                'macy' => MacyOrderMetric::class,
                'wayfair' => WayfairDailyData::class,
                'purchasingpower' => PurchasingPowerSale::class,
                'doba' => DobaDailyData::class,
                'tiktok' => TiktokOrder::class,
                'tiktok2' => Tiktok2Order::class,
                default => null,
            };
            if ($class === null) {
                return;
            }
            $model = $class::query()->find($orderId);
            if ($model === null) {
                return;
            }
            if (Schema::hasColumn($model->getTable(), 'tracking_number')) {
                $model->tracking_number = $tn;
                if ($carrier !== '' && Schema::hasColumn($model->getTable(), 'carrier')) {
                    $model->carrier = $carrier;
                } elseif ($carrier !== '' && Schema::hasColumn($model->getTable(), 'carrier_name')) {
                    $model->carrier_name = $carrier;
                } elseif ($carrier !== '' && Schema::hasColumn($model->getTable(), 'shipping_company')) {
                    $model->shipping_company = $carrier;
                }
            }
            foreach (['raw_payload', 'raw_json', 'raw_data'] as $field) {
                if (! isset($model->{$field})) {
                    continue;
                }
                $raw = $model->{$field};
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : [];
                }
                if (! is_array($raw)) {
                    $raw = [];
                }
                $raw['tracking_number'] = $tn;
                if ($carrier !== '') {
                    $raw['carrier'] = $carrier;
                }
                $model->{$field} = $raw;
                break;
            }
            $model->save();
        } catch (\Throwable $e) {
            Log::debug('persistTrackingOntoMarketplaceOrder failed', [
                'marketplace' => $marketplace,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function pushChannelTrackingAfterShopify(string $marketplace, int $orderId, array $result): void
    {
        app(MarketplaceChannelFulfillmentHub::class)->pushAfterShopifyTracking($marketplace, $orderId, $result);
    }

    /**
     * @param  array<string, mixed>  $shopifyOrder
     * @param  array<string, mixed>  $result
     */
    protected function pushChannelTrackingForShopifyOrder(array $shopifyOrder, string $shopifyOrderId, array $result): void
    {
        app(MarketplaceChannelFulfillmentHub::class)->pushAfterShopifyCopy($shopifyOrder, $shopifyOrderId, $result);
    }

    /**
     * @return list<string>
     */
    protected function shopifyOrderNumberRefs(string $shopifyOrderId): array
    {
        if (! Schema::hasTable('shopify_raw_orders')) {
            return [];
        }
        $sid = $this->shopifyNumericId($shopifyOrderId);
        try {
            $q = DB::table('shopify_raw_orders')->select(['order_number']);
            if ($sid !== null) {
                $q->where('order_id', $sid);
            } else {
                $q->where('order_number', $shopifyOrderId);
            }
            $num = trim((string) ($q->value('order_number') ?? ''));
        } catch (\Throwable) {
            return [];
        }
        if ($num === '') {
            return [];
        }
        $out = [$num];
        $plain = ltrim($num, '#');
        if ($plain !== $num) {
            $out[] = $plain;
        }

        return $out;
    }

    protected function shopifyNumericId(string $shopifyOrderId): ?int
    {
        $sid = trim($shopifyOrderId);
        if ($sid === '') {
            return null;
        }
        if (preg_match('/(\d{6,})$/', $sid, $m) === 1) {
            return (int) $m[1];
        }

        return ctype_digit($sid) ? (int) $sid : null;
    }

    protected function enrollCarrierTrackingNumber(string $tracking, string $carrier): void
    {
        if (! Schema::hasTable('carrier_tracking_statuses')) {
            return;
        }
        $tn = trim($tracking);
        if ($tn === '' || strlen($tn) < 8) {
            return;
        }
        try {
            $now = now();
            $guessed = \App\Support\TrackingCarrierGuesser::fill(
                $carrier !== '' ? $carrier : null,
                $tn
            ) ?? $carrier;
            DB::table('carrier_tracking_statuses')->upsert(
                [[
                    'tracking_number' => mb_substr($tn, 0, 128),
                    'carrier' => $guessed !== '' ? mb_substr((string) $guessed, 0, 128) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['tracking_number'],
                ['carrier', 'updated_at']
            );
        } catch (\Throwable $e) {
            Log::debug('enrollCarrierTrackingNumber failed', ['error' => $e->getMessage()]);
        }
    }

    public function rememberShopifyTracking(string $shopifyOrderId, string $tracking, string $carrier): void
    {
        $this->cacheTrackingOnShopifyRawOrder($shopifyOrderId, $tracking, $carrier);
    }

    /**
     * Keep SOF overlays in sync when Veeqo/GOFO finds tracking, even if marketplace status is still unshipped.
     */
    protected function cacheTrackingOnShopifyRawOrder(string $shopifyOrderId, string $tracking, string $carrier): void
    {
        $sid = trim($shopifyOrderId);
        $tn = trim($tracking);
        if ($sid === '' || $tn === '' || ! Schema::hasTable('shopify_raw_orders')) {
            return;
        }

        try {
            $payload = [
                'tracking_number' => $tn,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('shopify_raw_orders', 'tracking_company') && trim($carrier) !== '') {
                $payload['tracking_company'] = trim($carrier);
            }
            if (Schema::hasColumn('shopify_raw_orders', 'fulfillment_status')) {
                $payload['fulfillment_status'] = 'fulfilled';
            }
            $query = DB::table('shopify_raw_orders');
            $numericId = $this->shopifyNumericId($sid);
            if ($numericId !== null) {
                $query->where('order_id', $numericId)->update($payload);

                return;
            }
            $query->where('order_number', $sid)->update($payload);
        } catch (\Throwable $e) {
            Log::debug('cacheTrackingOnShopifyRawOrder failed', ['error' => $e->getMessage()]);
        }
    }
}
