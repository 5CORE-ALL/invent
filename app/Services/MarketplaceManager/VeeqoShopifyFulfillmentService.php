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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Copy a shipping-label tracking number (Veeqo, GOFO via 4Seller, or the marketplace itself)
 * onto the Shopify order Marketplace Manager imported, and mark it fulfilled.
 */
class VeeqoShopifyFulfillmentService
{
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

        return $this->fulfillShopifyFromLabels(
            (string) $ctx['shopify_order_id'],
            (array) $ctx['shopify_config'],
            (array) $ctx['refs'],
            is_array($ctx['local_tracking'] ?? null) ? $ctx['local_tracking'] : null
        );
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
    public function fulfillShopifyFromLabels(string $shopifyOrderId, array $shopifyConfig, array $refs, ?array $localTracking = null): array
    {
        $shopifyOrderId = trim($shopifyOrderId);
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'not_linked',
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $existing = $this->existingShopifyTracking($shopifyConfig, $shopifyOrderId);
        if ($existing !== null) {
            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_on_shopify',
                'message' => 'Shopify already has tracking '.$existing['tracking'].'.',
                'tracking' => $existing['tracking'],
                'carrier' => $existing['carrier'],
            ];
        }

        $found = null;
        $source = '';
        $veeqoChecked = $this->veeqo->isConfigured();
        $gofoChecked = $this->gofo->isConfigured();

        if ($veeqoChecked) {
            $veeqo = $this->findVeeqoShipment($refs);
            if ($veeqo !== null) {
                $found = $veeqo;
                $source = 'veeqo';
            }
        }

        if ($found === null && $gofoChecked) {
            $gofoRefs = array_values(array_filter(
                $refs,
                static fn ($r) => trim((string) $r) !== $shopifyOrderId
            ));
            $gofo = $this->gofo->findShipment($gofoRefs);
            if ($gofo !== null) {
                $found = $gofo;
                $source = 'gofo';
            }
        }

        if ($found === null && $this->fourSeller->isConfigured()) {
            $fs = $this->fourSeller->findShipment($refs);
            if ($fs !== null) {
                $found = $fs;
                $source = '4seller';
            }
        }

        if ($found === null) {
            $localTn = strtoupper(preg_replace('/\s+/', '', (string) ($localTracking['tracking'] ?? '')) ?? '');
            if (strlen($localTn) >= 8) {
                $found = [
                    'tracking' => $localTn,
                    'carrier' => (string) ($localTracking['carrier'] ?? 'Other'),
                ];
                $source = 'marketplace';
            }
        }

        if ($found === null) {
            $checked = [];
            if ($veeqoChecked) {
                $checked[] = 'Veeqo';
            }
            if ($gofoChecked) {
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

        $carrier = $this->shopifyCarrierName((string) ($found['carrier'] ?? 'Other'));
        $written = $this->createShopifyFulfillment(
            $shopifyConfig,
            $shopifyOrderId,
            $found['tracking'],
            $carrier
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
     * Auto-run: linked Shopify orders from the last 30 days that still need a fulfillment.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function syncPendingUnfulfilled(int $limit = 80): array
    {
        $limit = max(1, min(250, $limit));
        $marketplaces = [
            'amazon', 'ebay1', 'ebay2', 'ebay3', 'temu', 'temu2',
            'newegg', 'shein', 'reverb', 'faire', 'tiktok', 'tiktok2',
            'aliexpress', 'alibaba', 'topdawg', 'bestbuy', 'macy',
            'wayfair', 'purchasingpower', 'doba', 'pls',
        ];
        $perMarket = max(5, (int) ceil($limit / count($marketplaces)));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($marketplaces as $slug) {
            if ($checked >= $limit) {
                break;
            }
            foreach ($this->pendingLinkedOrderIds($slug, $perMarket) as $orderId) {
                if ($checked >= $limit) {
                    break;
                }
                $checked++;
                $result = $this->fulfillMarketplaceOrder($slug, $orderId);
                if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
                    $fulfilled++;
                } elseif (! empty($result['skipped']) || (($result['action'] ?? '') === 'already_on_shopify')) {
                    $skipped++;
                } else {
                    $failed++;
                }
                usleep(150000);
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
        if ($shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual')) {
            $refs[] = $shopifyOrderId;
        }
        if ($shopifyOrderId === '' || $refs === []) {
            return [
                'shopify_order_id' => $shopifyOrderId,
                'shopify_config' => $this->shopifyConfigFor($marketplace),
                'refs' => $refs,
                'local_tracking' => is_array($row['local_tracking'] ?? null) ? $row['local_tracking'] : null,
            ];
        }

        return [
            'shopify_order_id' => $shopifyOrderId,
            'shopify_config' => $this->shopifyConfigFor($marketplace),
            'refs' => $refs,
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

        return [
            'shopify_order_id' => (string) ($model->shopify_order_id ?? ''),
            'refs' => $refs,
            'local_tracking' => $local,
        ];
    }

    /**
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, veeqo_order_id: ?int}|null
     */
    public function findVeeqoShipment(array $refs): ?array
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
            foreach ($variants as $candidate) {
                $candidate = trim($candidate);
                if (strlen($candidate) < 6) {
                    continue;
                }
                if (! in_array($candidate, $clean, true)) {
                    $clean[] = $candidate;
                }
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
     * @param  list<string>  $normalizedRefs
     */
    protected function orderLooksLikeRef(array $order, array $normalizedRefs): bool
    {
        $hay = strtolower(preg_replace('/\s+/', '', $this->flattenScalarStrings($order)) ?? '');
        if ($hay === '') {
            return false;
        }
        foreach ($normalizedRefs as $ref) {
            if ($ref !== '' && str_contains($hay, $ref)) {
                return true;
            }
        }

        return false;
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
            $carrier = $this->carrierFrom($shipment, $row);

            return ['tracking' => $tracking, 'carrier' => $carrier];
        }

        $direct = $this->trackingNumberFrom($order);
        if ($direct !== null) {
            return ['tracking' => $direct, 'carrier' => $this->carrierFrom($order, [])];
        }

        return null;
    }

    protected function trackingNumberFrom(array $row): ?string
    {
        $candidates = [
            $row['tracking_number'] ?? null,
            $row['trackingNumber'] ?? null,
            $row['tracking'] ?? null,
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

    protected function carrierFrom(array $shipment, array $parent): string
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

        return $this->shopifyCarrierName($name);
    }

    protected function shopifyCarrierName(string $name): string
    {
        $hay = strtolower($name);
        if (str_contains($hay, 'usps') || str_contains($hay, 'postal')) {
            return 'USPS';
        }
        if (str_contains($hay, 'ups') && ! str_contains($hay, 'usps')) {
            return 'UPS';
        }
        if (str_contains($hay, 'fedex') || str_contains($hay, 'federal express')) {
            return 'FedEx';
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

        return $name !== '' ? $name : 'Other';
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array{success: bool, already?: bool, message: string}
     */
    protected function createShopifyFulfillment(array $config, string $shopifyOrderId, string $tracking, string $carrier): array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are missing for this marketplace.'];
        }

        try {
            $orderRes = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,fulfillments,fulfillment_status',
            ]);

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

            $foRes = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}/fulfillment_orders.json");

            if (! $foRes->successful()) {
                return ['success' => false, 'message' => 'Could not load Shopify fulfillment orders (HTTP '.$foRes->status().').'];
            }

            $lineItems = [];
            foreach ($foRes->json('fulfillment_orders') ?? [] as $fo) {
                if (! is_array($fo) || empty($fo['id'])) {
                    continue;
                }
                $status = strtolower((string) ($fo['status'] ?? ''));
                if (! in_array($status, ['open', 'in_progress', 'scheduled'], true)) {
                    continue;
                }
                $lineItems[] = ['fulfillment_order_id' => (int) $fo['id']];
            }

            if ($lineItems === []) {
                return [
                    'success' => false,
                    'message' => 'Shopify has no open fulfillment orders to fulfill (already fulfilled or on hold).',
                ];
            }

            $post = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://{$storeUrl}/admin/api/2024-01/fulfillments.json", [
                'fulfillment' => [
                    'line_items_by_fulfillment_order' => $lineItems,
                    'tracking_info' => [
                        'number' => $tracking,
                        'company' => mb_substr($carrier, 0, 100),
                    ],
                    'notify_customer' => false,
                ],
            ]);

            if (! $post->successful()) {
                Log::warning('VeeqoShopifyFulfillmentService: Shopify fulfill failed', [
                    'shopify_order_id' => $shopifyOrderId,
                    'status' => $post->status(),
                    'body' => mb_substr($post->body(), 0, 400),
                ]);

                return ['success' => false, 'message' => 'Shopify fulfill failed (HTTP '.$post->status().').'];
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
     * @return array{store_url: string, token: string, store_key: string}
     */
    protected function shopifyConfigFor(string $marketplace): array
    {
        $settings = MarketplaceSyncSettings::getFor($marketplace);
        $storeKey = (string) ($settings['order']['shopify_store'] ?? 'main');

        return $this->stores->getConfigForStore($storeKey);
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
            if ($carrier === '' && preg_match('/carrier|shipping.?company|logistics.?company/', $k) && ! is_numeric($s)) {
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
    protected function existingShopifyTracking(array $config, string $shopifyOrderId): ?array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/2024-01/orders/{$shopifyOrderId}.json", [
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
     * @return list<int>
     */
    protected function pendingLinkedOrderIds(string $marketplace, int $limit): array
    {
        $since = now()->subDays(30);
        $limit = max(1, min(80, $limit));

        if ($marketplace === 'amazon' && Schema::hasTable('amazon_orders') && Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
            return AmazonOrder::query()
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
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
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
                'bestbuy', 'macy', 'aliexpress', 'alibaba', 'topdawg' => 'order_id',
                default => null,
            };

            if ($uniqueCol && Schema::hasColumn($table, $uniqueCol)) {
                $ids = [];
                $seen = [];
                foreach ($query->orderByDesc('id')->limit($limit * 8)->get(['id', $uniqueCol]) as $row) {
                    $key = trim((string) ($row->{$uniqueCol} ?? ''));
                    if ($key === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $ids[] = (int) $row->id;
                    if (count($ids) >= $limit) {
                        break;
                    }
                }

                return $ids;
            }

            return $query->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable $e) {
            Log::info('VeeqoShopifyFulfillmentService: pending query skipped', [
                'marketplace' => $marketplace,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function flattenScalarStrings(array $data): string
    {
        $out = [];
        $walk = static function ($value) use (&$walk, &$out): void {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $walk($v);
                }

                return;
            }
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        };
        $walk($data);

        return implode(' ', $out);
    }
}
