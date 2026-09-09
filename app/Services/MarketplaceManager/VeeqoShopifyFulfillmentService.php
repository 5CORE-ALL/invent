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
use App\Support\DobaTrackingNumber;
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

    /** @var (callable(array<string, mixed>): void)|null */
    protected $progressReporter = null;

    /** @var array{checked: int, fulfilled: int, skipped: int, failed: int} */
    protected array $progressTotals = [
        'checked' => 0,
        'fulfilled' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    /** @var array<string, int> */
    protected array $skipReasons = [];

    public function __construct(
        protected VeeqoApiService $veeqo,
        protected GofoExpressService $gofo,
        protected FourSellerApiService $fourSeller,
        protected ShopifyStoreSelector $stores,
    ) {}

    /**
     * @param  (callable(array<string, mixed>): void)|null  $reporter
     */
    public function setProgressReporter(?callable $reporter): static
    {
        $this->progressReporter = $reporter;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function reportProgress(array $event): void
    {
        if ($this->progressReporter === null) {
            return;
        }
        ($this->progressReporter)($event);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function bumpProgress(string $outcome, array $extra = []): void
    {
        $this->progressTotals['checked']++;
        if ($outcome === 'fulfilled') {
            $this->progressTotals['fulfilled']++;
        } elseif ($outcome === 'failed') {
            $this->progressTotals['failed']++;
        } else {
            $this->progressTotals['skipped']++;
            $reason = trim((string) ($extra['reason'] ?? 'other'));
            if ($reason === '') {
                $reason = 'other';
            }
            $this->skipReasons[$reason] = (int) ($this->skipReasons[$reason] ?? 0) + 1;
        }
        $this->reportProgress(array_merge($this->progressTotals, $extra, [
            'type' => 'tick',
            'success' => $outcome === 'fulfilled',
            'skip_reasons' => $this->skipReasons,
        ]));
    }

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

        $skus = [];
        foreach ((array) ($ctx['skus'] ?? []) as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && ! in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }
        $single = trim((string) ($ctx['sku'] ?? ''));
        if ($single !== '' && ! in_array($single, $skus, true)) {
            array_unshift($skus, $single);
        }
        if ($skus === []) {
            $shopifyOrder = $this->shopifyOrderPayload(
                (array) $ctx['shopify_config'],
                (string) ($ctx['shopify_order_id'] ?? '')
            );
            if (is_array($shopifyOrder)) {
                $skus = $this->skusFromShopifyOrder($shopifyOrder);
            }
        }
        if ($skus === []) {
            $skus = [''];
        }

        $last = [
            'success' => false,
            'skipped' => true,
            'action' => 'sku_required',
            'message' => 'Marketplace SKU missing — tracking not attached.',
        ];
        $ok = null;
        foreach ($skus as $sku) {
            $result = $this->fulfillShopifyFromLabels(
                (string) $ctx['shopify_order_id'],
                (array) $ctx['shopify_config'],
                (array) $ctx['refs'],
                is_array($ctx['local_tracking'] ?? null) ? $ctx['local_tracking'] : null,
                $sku,
                is_array($ctx['marketplace_order_ids'] ?? null) ? $ctx['marketplace_order_ids'] : [],
                $marketplace
            );
            $last = $result;
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
            if (! empty($result['success']) || (($result['action'] ?? '') === 'shopify_fulfilled')) {
                $ok = $result;
            }
        }

        return $ok ?? $last;
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

        $strict = $this->isStrictTrackingMarketplace($marketplace);
        $marketplaceOrderIds = app(ShopifyFulfillmentTrackingMatcher::class)->uniqueIds(
            $marketplaceOrderIds !== [] ? $marketplaceOrderIds : $refs
        );
        $marketplaceOrderIds = $this->expandMarketplaceOrderIdVariants($marketplaceOrderIds);
        // Keep confirmed channel ids (Newegg/eBay/etc. are often 8–10 digits).
        // Only drop Shopify Admin 13-digit ids — never the marketplace order number.
        $marketplaceOrderIds = array_values(array_filter(
            $marketplaceOrderIds,
            fn ($id) => ! $this->isShopifyInternalIdRef((string) $id)
        ));
        $sku = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);

        if ($strict && $marketplaceOrderIds === []) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'order_id_required',
                'message' => 'Marketplace order id missing — tracking not attached.',
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
            if ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true)) {
                $lineSkus = $this->skusFromShopifyOrder($orderCheck);
                if (count($lineSkus) === 1) {
                    $sku = $lineSkus[0];
                } else {
                    $lineCount = 0;
                    foreach ($orderCheck['line_items'] ?? [] as $line) {
                        if (is_array($line)) {
                            $lineCount++;
                        }
                    }
                    if ($lineCount !== 1) {
                        return [
                            'success' => false,
                            'skipped' => true,
                            'action' => 'sku_required',
                            'message' => 'Marketplace SKU missing — tracking not attached.',
                        ];
                    }
                    $sku = '';
                }
            }
            if ($sku !== '' && ! app(ShopifyFulfillmentTrackingMatcher::class)->orderHasSku($orderCheck, $sku)) {
                Log::info('VeeqoShopifyFulfillmentService: skip fulfill — Shopify SKU mismatch', [
                    'marketplace' => $marketplace,
                    'shopify_order_id' => $shopifyOrderId,
                    'sku' => $sku,
                    'matched_order_id' => $matchedOrderId,
                ]);

                return [
                    'success' => false,
                    'skipped' => true,
                    'action' => 'sku_mismatch',
                    'message' => 'Shopify order does not contain this marketplace SKU.',
                ];
            }
            // Confirmed marketplace order ids only — do not drop numeric Newegg
            // ids as "Shopify-like", and never look up Veeqo by Shopify #.
            $refs = $this->confirmedMarketplaceRefs($marketplaceOrderIds);
        } else {
            // Never search Veeqo/GOFO by Shopify #334042 — it collides with
            // Amazon ids like 113-3340426-4270650.
            $refs = $this->confirmedMarketplaceRefs($refs);
        }

        if (strtolower(trim($marketplace)) === 'doba' && is_array($localTracking)) {
            $localTn = trim((string) ($localTracking['tracking'] ?? ''));
            if ($localTn !== '') {
                $localTracking['tracking'] = DobaTrackingNumber::sanitize($localTn);
            }
        }

        $existing = $this->existingShopifyTracking(
            $shopifyConfig,
            $shopifyOrderId,
            $sku,
            $marketplaceOrderIds,
            $strict
        );
        if ($existing !== null) {
            $existingTn = (string) ($existing['tracking'] ?? '');
            $existingCarrier = (string) ($existing['carrier'] ?? '');
            if (strtolower(trim($marketplace)) === 'doba' && DobaTrackingNumber::needsSanitize($existingTn)) {
                $clean = DobaTrackingNumber::sanitize($existingTn);
                $updated = $this->updateExistingShopifyFulfillmentTracking(
                    (string) ($shopifyConfig['store_url'] ?? ''),
                    (string) ($shopifyConfig['token'] ?? ''),
                    $shopifyOrderId,
                    $clean,
                    $existingCarrier !== '' ? $existingCarrier : 'UPS'
                );
                $this->rewriteDobaShopifyPrepaidNote($shopifyConfig, $shopifyOrderId);
                $this->cacheTrackingOnShopifyRawOrder($shopifyOrderId, $clean, $existingCarrier);

                return [
                    'success' => ! empty($updated['success']),
                    'skipped' => false,
                    'action' => ! empty($updated['success']) ? 'shopify_fulfilled' : 'shopify_fulfill_failed',
                    'message' => ! empty($updated['success'])
                        ? 'Shopify Doba tracking cleaned to '.$clean.'.'
                        : (string) ($updated['message'] ?? 'Failed to strip carrier from Doba tracking.'),
                    'tracking' => $clean,
                    'carrier' => $existingCarrier,
                    'sku' => $sku !== '' ? $sku : null,
                ];
            }

            $this->cacheTrackingOnShopifyRawOrder(
                $shopifyOrderId,
                $existingTn,
                $existingCarrier
            );

            return [
                'success' => true,
                'skipped' => true,
                'action' => 'already_on_shopify',
                'message' => 'Shopify already has tracking '.$existingTn.'.',
                'tracking' => $existingTn,
                'carrier' => $existing['carrier'],
                'sku' => $sku !== '' ? $sku : null,
            ];
        }

        if (strtolower(trim($marketplace)) === 'doba' && ! $this->dobaMayUseExternalLabel($localTracking, $shopifyConfig, $shopifyOrderId)) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'tracking_not_found',
                'message' => 'Doba order is not prepaid — Veeqo/GOFO tracking was not attached.',
            ];
        }

        $found = $this->lookupLabelTracking(
            $refs,
            is_array($localTracking) ? $localTracking : null,
            false,
            $sku
        );
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
        if (strtolower(trim($marketplace)) === 'doba') {
            $found['tracking'] = DobaTrackingNumber::sanitize((string) ($found['tracking'] ?? ''));
        }

        $carrier = $this->shopifyCarrierName((string) ($found['carrier'] ?? 'Other'), (string) ($found['tracking'] ?? ''));
        $this->cacheTrackingOnShopifyRawOrder($shopifyOrderId, (string) $found['tracking'], $carrier);
        $written = $this->createShopifyFulfillment(
            $shopifyConfig,
            $shopifyOrderId,
            $found['tracking'],
            $carrier,
            $sku
        );
        if (strtolower(trim($marketplace)) === 'doba') {
            $this->rewriteDobaShopifyPrepaidNote($shopifyConfig, $shopifyOrderId);
        }

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
            'sku' => $sku !== '' ? $sku : null,
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
    public function lookupLabelTracking(array $refs, ?array $localTracking = null, bool $fast = false, string $sku = ''): ?array
    {
        $localTn = strtoupper(preg_replace('/\s+/', '', (string) ($localTracking['tracking'] ?? '')) ?? '');
        if (strlen($localTn) >= 8) {
            return [
                'tracking' => $localTn,
                'carrier' => (string) ($localTracking['carrier'] ?? 'Other'),
                'source' => 'marketplace',
            ];
        }

        if (Cache::get('mm.label_ssl_broken')) {
            return null;
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
                $veeqo = $this->findVeeqoShipment(array_slice($marketRefs, 0, 2), true, $sku);
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
            $veeqoRefs = $this->strongMarketplaceRefs($marketRefs !== [] ? $marketRefs : $clean);
            $veeqo = $veeqoRefs === [] ? null : $this->findVeeqoShipment($veeqoRefs, false, $sku);
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
            if ($this->isShopifyInternalIdRef($ref)) {
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
    public function syncPendingUnfulfilled(int $limit = 80, bool $fresh = false, bool $all = false): array
    {
        $limit = max(1, min($all ? 8000 : 2000, $limit));
        $marketplaces = MarketplaceManagerRegistry::slugs();
        $shopifyScanLimit = ($fresh || $all)
            ? $limit
            : max(20, (int) ceil($limit * 0.9));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;

        $this->progressTotals = ['checked' => 0, 'fulfilled' => 0, 'skipped' => 0, 'failed' => 0];
        $this->skipReasons = [];
        $this->reportProgress([
            'type' => 'start',
            'max' => $limit,
            'checked' => 0,
            'fulfilled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ]);

        if ($fresh) {
            $localSweep = $this->syncLocalTrackedLinkedOrders(min(300, $limit));
            $checked += (int) ($localSweep['checked'] ?? 0);
            $fulfilled += (int) ($localSweep['fulfilled'] ?? 0);
            $skipped += (int) ($localSweep['skipped'] ?? 0);
            $failed += (int) ($localSweep['failed'] ?? 0);
            $shopifyScanLimit = max(20, $limit - $checked);
        }

        $shopifyScan = $this->syncUnfulfilledShopifyCopies($shopifyScanLimit, $fresh, $all);
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
                    $this->bumpProgress('fulfilled', [
                        'label' => $slug.' #'.$orderId,
                        'marketplace' => $slug,
                        'tracking' => (string) ($result['tracking'] ?? ''),
                        'carrier' => (string) ($result['carrier'] ?? ''),
                        'message' => (string) ($result['message'] ?? ''),
                    ]);
                } elseif (! empty($result['skipped']) || (($result['action'] ?? '') === 'already_on_shopify')) {
                    $skipped++;
                    $this->bumpProgress('skipped', [
                        'label' => $slug.' #'.$orderId,
                        'marketplace' => $slug,
                        'reason' => (string) ($result['action'] ?? 'skipped'),
                    ]);
                } else {
                    $failed++;
                    $this->bumpProgress('failed', [
                        'label' => $slug.' #'.$orderId,
                        'marketplace' => $slug,
                    ]);
                }
                usleep(120000);
            }
        }

        $this->reportProgress([
            'type' => 'finish',
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'skip_reasons' => $this->skipReasons,
        ]);

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'skip_reasons' => $this->skipReasons,
            'message' => 'Fetch tracking: checked '.$checked.', fulfilled '.$fulfilled.', skipped '.$skipped.', failed '.$failed
                .' (Shopify copies: checked '.((int) ($shopifyScan['checked'] ?? 0))
                .', fulfilled '.((int) ($shopifyScan['fulfilled'] ?? 0))
                .', skipped '.((int) ($shopifyScan['skipped'] ?? 0))
                .', failed '.((int) ($shopifyScan['failed'] ?? 0)).').',
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
     * Write marketplace tracking we already stored locally onto the linked Shopify copy.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int}
     */
    public function syncLocalTrackedLinkedOrders(int $limit = 200): array
    {
        $limit = max(1, min(400, $limit));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;
        $seenShopify = [];

        foreach (['temu', 'temu2'] as $slug) {
            $class = $slug === 'temu2' ? Temu2Order::class : TemuOrder::class;
            $table = (new $class)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tracking_number') || ! Schema::hasColumn($table, 'shopify_order_id')) {
                continue;
            }
            $rows = $class::query()
                ->whereNotNull('tracking_number')
                ->where('tracking_number', '!=', '')
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->where('shopify_order_id', 'not like', 'manual%')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['id', 'shopify_order_id']);
            foreach ($rows as $row) {
                if ($checked >= $limit) {
                    break 2;
                }
                $shopifyId = (string) ($row->shopify_order_id ?? '');
                if ($shopifyId === '' || isset($seenShopify[$shopifyId])) {
                    continue;
                }
                $seenShopify[$shopifyId] = true;
                $checked++;
                $result = $this->fulfillMarketplaceOrder($slug, (int) $row->id);
                if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
                    $fulfilled++;
                    $this->bumpProgress('fulfilled', [
                        'label' => $slug.' Shopify '.$shopifyId,
                        'marketplace' => $slug,
                        'tracking' => (string) ($result['tracking'] ?? ''),
                        'carrier' => (string) ($result['carrier'] ?? ''),
                        'message' => (string) ($result['message'] ?? ''),
                    ]);
                } elseif (! empty($result['skipped']) || (($result['action'] ?? '') === 'already_on_shopify')) {
                    $skipped++;
                    $this->bumpProgress('skipped', [
                        'label' => $slug.' Shopify '.$shopifyId,
                        'marketplace' => $slug,
                        'reason' => (string) ($result['action'] ?? 'skipped'),
                    ]);
                } else {
                    $failed++;
                    $this->bumpProgress('failed', ['label' => $slug.' Shopify '.$shopifyId, 'marketplace' => $slug]);
                }
                usleep(80000);
            }
        }

        return compact('checked', 'fulfilled', 'skipped', 'failed');
    }

    /**
     * Unfulfilled Marketplace Manager Shopify copies (any channel), even when
     * the local marketplace table has no shopify_order_id yet.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int}
     */
    public function syncUnfulfilledShopifyCopies(int $limit = 40, bool $fresh = false, bool $all = false): array
    {
        $limit = max(1, min($all ? 8000 : 2000, $limit));
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

            $remaining = $limit - $checked;
            $listed = $all
                ? $this->listUnfulfilledShopifyOrders($storeUrl, $token, max($remaining * 2, $remaining), [
                    'days' => 400,
                    'max_pages' => 80,
                ])
                : $this->listUnfulfilledShopifyOrdersMixed($storeUrl, $token, $remaining);
            $orders = $all
                ? $this->interleaveNewestAndOldest($listed, $remaining * 3)
                : $listed;
            foreach ($orders as $order) {
                if ($checked >= $limit) {
                    break;
                }
                $shopifyId = (string) ($order['id'] ?? '');
                if ($shopifyId === '' || $this->shopifyOrderLooksFba($order)) {
                    continue;
                }
                $identity = $this->marketplaceIdentityFromShopifyOrder($order);
                $refs = $identity['refs'];
                if ($refs === []) {
                    continue;
                }
                $marketplace = $identity['slug'];
                $marketplaceOrderIds = $identity['ids'];
                $skus = $this->skusFromShopifyOrder($order);
                if ($marketplace === 'amazon') {
                    foreach ($this->amazonSkusForOrderIds($marketplaceOrderIds) as $amazonSku) {
                        if (! in_array($amazonSku, $skus, true)) {
                            $skus[] = $amazonSku;
                        }
                    }
                }
                $skuPasses = $skus !== [] ? $skus : [''];
                $checked++;
                $cacheKey = 'mm_fetch_tracking_shopify_v3:'.$shopifyId;
                $orderLabel = trim((string) ($order['name'] ?? '')).' '.($marketplace !== '' ? $marketplace : 'marketplace');
                if (! $fresh && Cache::has($cacheKey)) {
                    $skipped++;
                    $this->bumpProgress('skipped', [
                        'label' => trim($orderLabel),
                        'marketplace' => $marketplace,
                        'reason' => 'recently_checked',
                    ]);
                    continue;
                }
                $local = $this->localTrackingFromShopifyOrder($order)
                    ?? $this->localTrackingForMarketplaceRefs($marketplace, $marketplaceOrderIds);
                $anyFulfilled = false;
                $allMatched = true;
                $lastResult = ['success' => false, 'action' => 'tracking_not_found'];
                foreach ($skuPasses as $sku) {
                    $pass = $this->fulfillShopifyFromLabels(
                        $shopifyId,
                        $config,
                        $refs,
                        $local,
                        $sku,
                        $marketplaceOrderIds,
                        $marketplace
                    );
                    $lastResult = $pass;
                    $action = (string) ($pass['action'] ?? '');
                    if (! empty($pass['success']) && in_array($action, ['shopify_fulfilled', 'already_on_shopify'], true)) {
                        if ($action === 'shopify_fulfilled') {
                            $anyFulfilled = true;
                        }
                        $this->pushChannelTrackingForShopifyOrder($order, $shopifyId, $pass);
                        continue;
                    }
                    $allMatched = false;
                }
                $action = (string) ($lastResult['action'] ?? '');
                if ($anyFulfilled) {
                    $fulfilled++;
                    $amazonId = $this->amazonOrderIdFromShopifyOrder($order);
                    if ($amazonId !== '') {
                        $this->linkAmazonOrderToShopify($amazonId, $shopifyId);
                    }
                    Cache::put($cacheKey, 1, $allMatched ? now()->addDays(7) : now()->addMinutes(8));
                    $this->bumpProgress('fulfilled', [
                        'label' => trim((string) ($order['name'] ?? $shopifyId).' '.$marketplace),
                        'marketplace' => $marketplace,
                        'tracking' => (string) ($lastResult['tracking'] ?? ''),
                        'carrier' => (string) ($lastResult['carrier'] ?? ''),
                        'message' => (string) ($lastResult['message'] ?? ''),
                    ]);
                } elseif ($allMatched && $action === 'already_on_shopify') {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addMinutes(25));
                    $this->bumpProgress('skipped', [
                        'label' => trim($orderLabel),
                        'marketplace' => $marketplace,
                        'reason' => 'already_on_shopify',
                    ]);
                } elseif (in_array($action, ['tracking_not_found', 'not_linked'], true)) {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addMinutes(25));
                    $this->bumpProgress('skipped', [
                        'label' => trim($orderLabel),
                        'marketplace' => $marketplace,
                        'reason' => $action,
                    ]);
                } elseif (! empty($lastResult['skipped'])) {
                    $skipped++;
                    Cache::put($cacheKey, 1, now()->addMinutes(40));
                    $this->bumpProgress('skipped', [
                        'label' => trim($orderLabel),
                        'marketplace' => $marketplace,
                        'reason' => $action !== '' ? $action : 'skipped',
                    ]);
                } else {
                    $failed++;
                    Cache::put($cacheKey, 1, now()->addMinutes(2));
                    $this->bumpProgress('failed', ['label' => trim($orderLabel), 'marketplace' => $marketplace]);
                }
                usleep(120000);
                if ($checked > 0 && $checked % 25 === 0) {
                    Log::info('VeeqoShopifyFulfillmentService: Shopify copy catch-up', compact(
                        'checked',
                        'fulfilled',
                        'skipped',
                        'failed'
                    ));
                }
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
    /**
     * Newest open copies plus a rotating older window so Friday Amazon FBM
     * orders are not starved behind thousands of newer Unfulfilled rows.
     *
     * @return list<array<string, mixed>>
     */
    protected function listUnfulfilledShopifyOrdersMixed(string $storeUrl, string $token, int $limit): array
    {
        $newestNeed = min(400, max(80, (int) ceil($limit * 0.45)));
        $olderNeed = max($limit, (int) ceil($limit * 1.5));
        $newest = $this->listUnfulfilledShopifyOrders($storeUrl, $token, $newestNeed, [
            'days' => 12,
            'max_pages' => 8,
        ]);

        $cacheKey = 'mm.unfulfilled.window_end:'.strtolower($storeUrl);
        $windowEnd = Cache::get($cacheKey);
        if (! is_string($windowEnd) || trim($windowEnd) === '') {
            $windowEnd = now()->subDays(12)->toIso8601String();
        }
        $windowStart = \Illuminate\Support\Carbon::parse($windowEnd)->subDays(21);
        $older = $this->listUnfulfilledShopifyOrders($storeUrl, $token, $olderNeed, [
            'created_at_min' => $windowStart->toIso8601String(),
            'created_at_max' => $windowEnd,
            'max_pages' => 20,
        ]);

        if ($windowStart->lt(now()->subDays(400))) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $windowStart->toIso8601String(), now()->addDays(14));
        }

        $out = [];
        $seen = [];
        foreach (array_merge($older, $newest) as $order) {
            if (! is_array($order)) {
                continue;
            }
            $id = (string) ($order['id'] ?? '');
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            if ($id !== '') {
                $seen[$id] = true;
            }
            $out[] = $order;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return list<array<string, mixed>>
     */
    protected function interleaveNewestAndOldest(array $orders, int $limit): array
    {
        $limit = max(1, $limit);
        $n = count($orders);
        if ($n <= $limit) {
            return $orders;
        }
        $pick = [];
        $i = 0;
        $j = $n - 1;
        while (count($pick) < $limit && $i <= $j) {
            $pick[] = $orders[$i++];
            if (count($pick) < $limit && $i <= $j) {
                $pick[] = $orders[$j--];
            }
        }

        return $pick;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function amazonSkusForOrderIds(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('amazon_orders')) {
            return [];
        }
        $row = AmazonOrder::query()->whereIn('amazon_order_id', $ids)->first();
        if ($row === null) {
            return [];
        }

        return $this->skuListFromMarketplaceModel('amazon', $row);
    }

    /**
     * @param  array{days?: int, created_at_min?: string, created_at_max?: string, max_pages?: int}  $range
     * @return list<array<string, mixed>>
     */
    protected function listUnfulfilledShopifyOrders(string $storeUrl, string $token, int $limit, array $range = []): array
    {
        $out = [];
        $seen = [];
        $maxPages = max(1, (int) ($range['max_pages'] ?? 80));
        $createdMin = (string) ($range['created_at_min'] ?? now()->subDays((int) ($range['days'] ?? 400))->toIso8601String());
        $createdMax = isset($range['created_at_max']) ? (string) $range['created_at_max'] : '';
        foreach (['unfulfilled', 'partial'] as $fulfillmentStatus) {
            $path = 'orders.json';
            $payload = [
                'status' => 'open',
                'fulfillment_status' => $fulfillmentStatus,
                'limit' => 250,
                'created_at_min' => $createdMin,
                'fields' => 'id,name,tags,note,note_attributes,source_name,source_identifier,fulfillment_status,line_items',
            ];
            if ($createdMax !== '') {
                $payload['created_at_max'] = $createdMax;
            }
            for ($page = 0; $page < $maxPages && count($out) < $limit; $page++) {
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
                    $id = (string) ($order['id'] ?? '');
                    if ($id !== '' && isset($seen[$id])) {
                        continue;
                    }
                    if ($id !== '') {
                        $seen[$id] = true;
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
     * Full marketplace identity from Shopify tags / notes.
     * Accepts slug-id, slug_id (Faire), Temu PO-…, Amazon 3-7-7, and Best Buy BBY03-….
     *
     * @param  array<string, mixed>  $order
     * @return array{slug: string, ids: list<string>, refs: list<string>}
     */
    protected function marketplaceIdentityFromShopifyOrder(array $order): array
    {
        $tagsRaw = (string) ($order['tags'] ?? '');
        $note = (string) ($order['note'] ?? '');
        $rawHay = $tagsRaw.' '.$note;
        $hay = strtolower($rawHay);
        $sourceId = trim((string) ($order['source_identifier'] ?? ''));
        $slugs = MarketplaceManagerRegistry::slugs();
        usort($slugs, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        $slug = '';
        $ids = [];
        $refs = [];

        $pushId = function (string $id) use (&$ids, &$refs): void {
            $id = trim($id);
            if ($id === '' || strlen($id) < 4) {
                return;
            }
            foreach ($this->expandMarketplaceOrderIdVariants([$id]) as $variant) {
                if ($variant !== '' && ! in_array($variant, $ids, true)) {
                    $ids[] = $variant;
                }
                if ($variant !== '' && ! in_array($variant, $refs, true)) {
                    $refs[] = $variant;
                }
            }
        };

        foreach ($slugs as $candidate) {
            $candidate = strtolower((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (! preg_match_all('/(?:^|[\s,])'.preg_quote($candidate, '/').'[-_]([^\s,]+)/i', $rawHay, $matches)) {
                continue;
            }
            if ($slug === '') {
                $slug = $candidate;
            }
            foreach ($matches[1] as $id) {
                $id = trim((string) $id, " \t");
                if ($id === '') {
                    continue;
                }
                $pushId($id);
                $prefixed = $candidate.'-'.$id;
                if (! in_array($prefixed, $refs, true)) {
                    $refs[] = $prefixed;
                }
            }
        }

        if (preg_match_all('/\b(BBY\d{2}-[A-Z0-9-]+)/i', $rawHay, $bbyMatches)) {
            if ($slug === '') {
                $slug = 'bestbuy';
            }
            foreach ($bbyMatches[1] as $id) {
                $pushId((string) $id);
            }
        }

        if (preg_match_all('/PO-\d[\w-]{6,}/i', $rawHay, $poMatches)) {
            foreach ($poMatches[0] as $po) {
                $pushId((string) $po);
            }
            if ($slug === '') {
                $slug = str_contains($hay, 'temu2') ? 'temu2' : (str_contains($hay, 'temu') ? 'temu' : $slug);
            }
        }

        $amazonId = $this->amazonOrderIdFromShopifyOrder($order);
        if ($amazonId !== '') {
            $pushId($amazonId);
            if ($slug === '') {
                $slug = 'amazon';
            }
        }

        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? $attr['key'] ?? ''));
            $val = trim((string) ($attr['value'] ?? ''));
            if ($val === '' || strlen($val) < 6 || str_contains($name, 'track')) {
                continue;
            }
            if (str_contains($name, 'order') || str_contains($name, 'po_') || $name === 'po') {
                if (! $this->isCollisionProneOrderRef($val) && ! $this->isShopifyInternalIdRef($val)) {
                    $pushId($val);
                }
            }
            if ($slug === '') {
                if (str_contains($name, 'doba') || str_contains(strtolower($val), 'doba')) {
                    $slug = 'doba';
                }
                foreach ($slugs as $candidate) {
                    $candidate = strtolower((string) $candidate);
                    if ($candidate !== '' && ($name === $candidate.'_order_id' || str_contains($name, $candidate))) {
                        $slug = $candidate;
                        break;
                    }
                }
            }
        }

        if (
            $sourceId !== ''
            && ! $this->isCollisionProneOrderRef($sourceId)
            && ! $this->isShopifyInternalIdRef($sourceId)
        ) {
            $pushId($sourceId);
        }

        if ($slug === '') {
            $source = strtolower((string) ($order['source_name'] ?? ''));
            if (str_contains($source, 'doba') || $source === '145019994113' || str_contains($hay, 'doba')) {
                $slug = 'doba';
            } elseif (
                (str_contains($hay, 'mirakl') || str_contains($source, 'mirakl'))
                && (str_contains($hay, 'best buy') || str_contains($hay, 'bestbuy'))
            ) {
                $slug = 'bestbuy';
            } else {
                foreach ($slugs as $candidate) {
                    $candidate = strtolower((string) $candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    if ($source === $candidate || str_contains($source, $candidate)) {
                        $slug = $candidate;
                        break;
                    }
                    if (preg_match('/(?:^|[\s,])'.preg_quote($candidate, '/').'(?:[\s,]|$)/i', $hay)) {
                        $slug = $candidate;
                        break;
                    }
                }
            }
        }

        $uniqueRefs = [];
        foreach (array_merge($refs, $ids) as $ref) {
            $ref = trim((string) $ref);
            if ($ref !== '' && ! in_array($ref, $uniqueRefs, true)) {
                $uniqueRefs[] = $ref;
            }
        }

        return [
            'slug' => $slug,
            'ids' => $ids,
            'refs' => $uniqueRefs,
        ];
    }

    /**
     * Marketplace order ids from Shopify tags (amazon-…, ebay1-…, temu-…, faire_…, BBY03-…).
     *
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function marketplaceRefsFromShopifyOrder(array $order): array
    {
        return $this->marketplaceIdentityFromShopifyOrder($order)['refs'];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function marketplaceSlugFromShopifyOrder(array $order): string
    {
        return $this->marketplaceIdentityFromShopifyOrder($order)['slug'];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function marketplaceOrderIdsFromShopifyOrder(array $order, string $marketplace = ''): array
    {
        $identity = $this->marketplaceIdentityFromShopifyOrder($order);
        if ($marketplace !== '' && $identity['slug'] !== '' && $identity['slug'] !== strtolower(trim($marketplace))) {
            return $identity['ids'];
        }

        return $identity['ids'];
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function expandMarketplaceOrderIdVariants(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $candidates = [$id];
            if (preg_match('/^PO-(.+)$/i', $id, $m)) {
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $candidates[] = $tail;
                }
            } elseif (
                preg_match('/^\d{3}-[\w-]+$/', $id)
                && ! preg_match('/^\d{3}-\d{7}-\d{7}$/', $id)
            ) {
                $candidates[] = 'PO-'.$id;
            }
            if (preg_match('/^BBY\d{2}-(.+)$/i', $id, $m)) {
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $candidates[] = $tail;
                }
            }
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && ! in_array($candidate, $out, true)) {
                    $out[] = $candidate;
                }
            }
        }

        return $out;
    }

    /**
     * Tracking already stored on the local marketplace order for these full ids.
     *
     * @param  list<string>  $ids
     * @return array{tracking: string, carrier: string}|null
     */
    protected function localTrackingForMarketplaceRefs(string $marketplace, array $ids): ?array
    {
        $marketplace = strtolower(trim($marketplace));
        $ids = $this->expandMarketplaceOrderIdVariants($ids);
        if ($marketplace === '' || $ids === []) {
            return null;
        }

        $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);
        $local = $model !== null
            ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
            : null;
        if ($local !== null) {
            return $local;
        }

        return $this->pullLiveMarketplaceTracking($marketplace, $ids);
    }

    /**
     * Confirm tracking on the marketplace by full order id (Temu / Faire / eBay / channel detail APIs).
     *
     * @param  list<string>  $ids
     * @return array{tracking: string, carrier: string}|null
     */
    protected function pullLiveMarketplaceTracking(string $marketplace, array $ids): ?array
    {
        $ids = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $ids)));
        if ($ids === []) {
            return null;
        }

        if (in_array($marketplace, ['temu', 'temu2'], true)) {
            if (Cache::get('mm.temu.ip_blocked')) {
                $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);

                return $model !== null
                    ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
                    : null;
            }
            $svc = $marketplace === 'temu2'
                ? app(Temu2OrderTrackingPullService::class)
                : app(TemuOrderTrackingPullService::class);
            $api = $marketplace === 'temu2'
                ? app(\App\Services\Temu2ApiService::class)
                : app(\App\Services\TemuApiService::class);
            foreach ($ids as $id) {
                if (! preg_match('/^(PO-)?\d{3}-[\w-]+$/i', $id)) {
                    continue;
                }
                if (method_exists($api, 'getShipmentInfo')) {
                    try {
                        $shipment = $api->getShipmentInfo($id);
                        $msg = strtolower((string) ($shipment['message'] ?? ''));
                        if (str_contains($msg, 'not_in_ip_white_list')) {
                            Cache::put('mm.temu.ip_blocked', 1, now()->addHours(6));
                        }
                        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($shipment['tracking_number'] ?? '')) ?? '');
                        if (strlen($tn) >= 8) {
                            return [
                                'tracking' => $tn,
                                'carrier' => trim((string) ($shipment['carrier'] ?? '')) ?: 'Other',
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::info('VeeqoShopifyFulfillmentService: Temu shipment lookup failed', [
                            'marketplace' => $marketplace,
                            'order_id' => $id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                try {
                    $result = $svc->pullForParentOrder($id, true);
                } catch (\Throwable $e) {
                    Log::info('VeeqoShopifyFulfillmentService: marketplace tracking pull failed', [
                        'marketplace' => $marketplace,
                        'order_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                $tn = strtoupper(preg_replace('/\s+/', '', (string) ($result['tracking_number'] ?? '')) ?? '');
                if (strlen($tn) >= 8) {
                    return [
                        'tracking' => $tn,
                        'carrier' => trim((string) ($result['carrier'] ?? '')) ?: 'Other',
                    ];
                }
            }

            $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);

            return $model !== null
                ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
                : null;
        }

        if ($marketplace === 'faire') {
            foreach ($ids as $id) {
                if (strlen($id) < 6 || $this->isShopifyInternalIdRef($id)) {
                    continue;
                }
                try {
                    $res = app(\App\Services\FaireApiService::class)->getOrder($id);
                } catch (\Throwable $e) {
                    continue;
                }
                $json = is_array($res['json'] ?? null) ? $res['json'] : (is_array($res) ? $res : null);
                if (! is_array($json)) {
                    continue;
                }
                $local = $this->trackingFromMixed($json);
                if ($local !== null) {
                    return $local;
                }
            }
        }

        if (in_array($marketplace, ['ebay1', 'ebay2', 'ebay3'], true)) {
            foreach ($ids as $id) {
                if (! preg_match('/^\d{2}-\d{5}-\d{5}$/', $id)) {
                    continue;
                }
                try {
                    $pulled = app(EbaySellFulfillmentTracking::class)->readTrackingFromEbay($marketplace, $id);
                } catch (\Throwable $e) {
                    continue;
                }
                if (is_array($pulled) && strlen(trim((string) ($pulled['tracking'] ?? ''))) >= 8) {
                    return $pulled;
                }
            }
        }

        if (in_array($marketplace, ['newegg', 'reverb', 'aliexpress', 'alibaba', 'faire', 'shein'], true)) {
            $fallback = app(ChannelTrackingApiFallbackService::class);
            foreach ($ids as $id) {
                if (strlen($id) < 5 || $this->isShopifyInternalIdRef($id)) {
                    continue;
                }
                try {
                    $pulled = $fallback->pullTrackingForOrder($marketplace, $id);
                } catch (\Throwable $e) {
                    Log::info('VeeqoShopifyFulfillmentService: channel API fallback failed', [
                        'marketplace' => $marketplace,
                        'order_id' => $id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                if (is_array($pulled) && strlen(trim((string) ($pulled['tracking'] ?? ''))) >= 8) {
                    return $pulled;
                }
            }
            $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);

            return $model !== null
                ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
                : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     */
    protected function findMarketplaceOrderByChannelIds(string $marketplace, array $ids): ?object
    {
        $ids = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $ids)));
        if ($ids === []) {
            return null;
        }

        if ($marketplace === 'amazon' && Schema::hasTable('amazon_orders')) {
            return AmazonOrder::query()->whereIn('amazon_order_id', $ids)->first();
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

        return $class::query()
            ->where(function ($query) use ($refFields, $ids): void {
                foreach ($refFields as $i => $field) {
                    if ($i === 0) {
                        $query->whereIn($field, $ids);
                    } else {
                        $query->orWhereIn($field, $ids);
                    }
                }
            })
            ->first();
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function trackingFromLoadedMarketplaceModel(string $marketplace, object $model): ?array
    {
        $local = $this->trackingFromModel($model);
        if ($local === null) {
            foreach (['raw_payload', 'raw_json', 'raw_data'] as $rawField) {
                $raw = $model->{$rawField} ?? null;
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = is_array($decoded) ? $decoded : null;
                }
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

        return $local;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return list<string>
     */
    protected function skusFromShopifyOrder(array $order): array
    {
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $out = [];
        foreach ($order['line_items'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = $matcher->normalizeSku((string) ($line['sku'] ?? ''));
            if ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true) || in_array($sku, $out, true)) {
                continue;
            }
            $out[] = $sku;
        }

        return $out;
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
        $strict = $this->isStrictTrackingMarketplace($marketplace);
        if ($shopifyOrderId !== '' && ! str_starts_with($shopifyOrderId, 'manual') && ! $strict) {
            // Shopify Admin id only — never the short #334042 display name.
            if (! $this->isCollisionProneOrderRef($shopifyOrderId)) {
                $refs[] = $shopifyOrderId;
            }
        }

        $skus = [];
        foreach ((array) ($row['skus'] ?? []) as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '' && ! in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }
        $sku = trim((string) ($row['sku'] ?? ''));
        if ($sku !== '' && ! in_array($sku, $skus, true)) {
            array_unshift($skus, $sku);
        }

        return [
            'shopify_order_id' => $shopifyOrderId,
            'shopify_config' => $this->shopifyConfigFor($marketplace),
            'refs' => $refs,
            'marketplace_order_ids' => $marketplaceOrderIds,
            'sku' => $sku !== '' ? $sku : (string) ($skus[0] ?? ''),
            'skus' => $skus,
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

            $amazonSkus = $this->skuListFromMarketplaceModel('amazon', $order);

            return [
                'shopify_order_id' => (string) ($order->shopify_order_id ?? ''),
                'refs' => array_filter([
                    (string) $order->amazon_order_id,
                    $seller,
                ]),
                'sku' => (string) ($amazonSkus[0] ?? ''),
                'skus' => $amazonSkus,
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

        $skus = $this->skuListFromMarketplaceModel($marketplace, $model);

        return [
            'shopify_order_id' => (string) ($model->shopify_order_id ?? ''),
            'refs' => $refs,
            'sku' => (string) ($skus[0] ?? ''),
            'skus' => $skus,
            'local_tracking' => $local,
        ];
    }

    protected function skuFromMarketplaceModel(string $marketplace, object $model): string
    {
        $skus = $this->skuListFromMarketplaceModel($marketplace, $model);

        return (string) ($skus[0] ?? '');
    }

    /**
     * @return list<string>
     */
    protected function skuListFromMarketplaceModel(string $marketplace, object $model): array
    {
        $out = [];
        $push = static function (string $sku) use (&$out): void {
            $sku = trim($sku);
            if ($sku === '' || in_array($sku, ['__ORDER__', '__UNKNOWN__'], true) || in_array($sku, $out, true)) {
                return;
            }
            $out[] = $sku;
        };

        if ($marketplace === 'amazon' && $model instanceof AmazonOrder) {
            foreach ($model->items()->pluck('sku') as $sku) {
                $push((string) $sku);
            }
        }

        foreach ([
            'sku',
            'seller_sku',
            'display_sku',
            'ext_code',
            'product_sku',
            'offer_sku',
        ] as $field) {
            $push((string) ($model->{$field} ?? ''));
        }

        foreach (['raw_payload', 'raw_json', 'raw_data'] as $rawField) {
            $raw = $model->{$rawField} ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (! is_array($raw)) {
                continue;
            }
            foreach (['SellerSKU', 'seller_sku', 'sku', 'merchant_sku', 'extCode', 'ext_code'] as $key) {
                $push((string) data_get($raw, $key, ''));
            }
            foreach (($raw['OrderItems'] ?? $raw['order_items'] ?? $raw['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['SellerSKU', 'seller_sku', 'sku', 'merchant_sku', 'extCode', 'ext_code'] as $key) {
                    $push((string) ($item[$key] ?? ''));
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, veeqo_order_id: ?int}|null
     */
    public function findVeeqoShipment(array $refs, bool $fast = false, string $sku = ''): ?array
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
            if ($this->isShopifyInternalIdRef($ref)) {
                continue;
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
            $hit = $this->searchVeeqoOrders($ref, $clean, $sku);
            if ($hit !== null) {
                return $hit;
            }
            if ($fast) {
                continue;
            }
            $hit = $this->searchVeeqoShipments($ref, $clean, $sku);
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
    protected function searchVeeqoOrders(string $query, array $allRefs, string $sku = ''): ?array
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
            $ship = $this->extractShipment($order, $sku);
            if ($ship !== null) {
                $ship['veeqo_order_id'] = isset($order['id']) && is_numeric($order['id']) ? (int) $order['id'] : null;

                return $ship;
            }
            if (isset($order['id']) && is_numeric($order['id'])) {
                $full = $this->veeqo->getOrder((int) $order['id']);
                if (! empty($full['ok']) && is_array($full['data'] ?? null)) {
                    $ship = $this->extractShipment($full['data'], $sku);
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
    protected function searchVeeqoShipments(string $query, array $allRefs, string $sku = ''): ?array
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
            $ship = $this->extractShipment($row, $sku);
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
        if ($aDash === $bDash && (str_contains($a, '-') || str_contains($b, '-'))) {
            return true;
        }

        // #334042 must not match Amazon 113-3340426-4270650
        $shorter = strlen($aDash) <= strlen($bDash) ? $aDash : $bDash;
        $longer = strlen($aDash) <= strlen($bDash) ? $bDash : $aDash;
        if (strlen($shorter) >= 6 && str_contains($longer, $shorter) && $shorter !== $longer) {
            return false;
        }

        return false;
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
     * Shopify Admin REST ids are 13 digits (e.g. 7159464132845).
     * Doba order nos are 14 digits (YYMMDD…) and must not be dropped.
     */
    protected function isShopifyInternalIdRef(string $ref): bool
    {
        $n = $this->normalizeOrderRef($ref);
        if ($n === '') {
            return false;
        }
        // Doba / dated marketplace ids: 26083068732127
        if (preg_match('/^2\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{4,}$/', $n)) {
            return false;
        }

        return (bool) preg_match('/^\d{13}$/', $n);
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
            if ($ref === '' || $this->isShopifyInternalIdRef($ref)) {
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
     * Marketplace order ids we already resolved from the channel row / tags.
     * Keep numeric Newegg ids; only drop Shopify internal gids and URLs.
     *
     * @param  list<string>  $refs
     * @return list<string>
     */
    protected function confirmedMarketplaceRefs(array $refs): array
    {
        $out = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '' || $this->isShopifyInternalIdRef($ref)) {
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
     * Attach a label only when the Shopify copy contains this full
     * marketplace order id and this SKU. Never search by Shopify #.
     */
    protected function isStrictTrackingMarketplace(string $marketplace): bool
    {
        $marketplace = strtolower(trim($marketplace));

        return $marketplace !== '';
    }

    /**
     * Regular Doba orders must not inherit a Veeqo/GOFO label.
     * Prepaid Doba may use tracking already on the Doba/Shopify prepaid note.
     *
     * @param  array{tracking?: string, carrier?: string}|null  $localTracking
     * @param  array{store_url?: string, token?: string}  $shopifyConfig
     */
    protected function dobaMayUseExternalLabel(?array $localTracking, array $shopifyConfig, string $shopifyOrderId): bool
    {
        $localTn = DobaTrackingNumber::sanitize((string) ($localTracking['tracking'] ?? ''));
        if (strlen($localTn) >= 8) {
            return true;
        }

        $order = $this->shopifyOrderPayload($shopifyConfig, $shopifyOrderId);
        if (is_array($order)) {
            foreach ($order['note_attributes'] ?? [] as $attr) {
                if (! is_array($attr)) {
                    continue;
                }
                $name = strtolower((string) ($attr['name'] ?? $attr['key'] ?? ''));
                $val = strtolower((string) ($attr['value'] ?? ''));
                if (str_contains($name, 'prepaid') || str_contains($val, 'prepaid label')) {
                    return true;
                }
            }
        }

        if (Schema::hasTable('doba_daily_data') && Schema::hasColumn('doba_daily_data', 'order_type')) {
            $sid = preg_replace('/\D+/', '', $shopifyOrderId);
            $type = DobaDailyData::query()
                ->where(function ($q) use ($shopifyOrderId, $sid) {
                    $q->where('shopify_order_id', $shopifyOrderId);
                    if ($sid !== '') {
                        $q->orWhere('shopify_order_id', $sid);
                    }
                })
                ->value('order_type');
            if (strtolower(trim((string) $type)) === 'pickup with a prepaid label') {
                return true;
            }
        }

        return false;
    }

    protected function marketplaceSkuColumn(string $marketplace, string $table): ?string
    {
        $col = in_array($marketplace, ['tiktok', 'tiktok2'], true) ? 'seller_sku' : 'sku';

        return Schema::hasColumn($table, $col) ? $col : null;
    }

    /**
     * @return array{tracking: string, carrier: string}|null
     */
    protected function extractShipment(array $order, string $sku = ''): ?array
    {
        $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
        $skuMiss = $want !== '' && $this->payloadHasSkuFields($order) && ! $this->payloadContainsSku($order, $want);

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
            if ($skuMiss && count($buckets) !== 1) {
                continue;
            }
            $carrier = $this->carrierFrom($shipment, $row, $tracking);

            return ['tracking' => $tracking, 'carrier' => $carrier];
        }

        $direct = $this->trackingNumberFrom($order);
        if ($direct !== null && ! $skuMiss) {
            return ['tracking' => $direct, 'carrier' => $this->carrierFrom($order, [], $direct)];
        }
        if ($direct !== null && $skuMiss) {
            return ['tracking' => $direct, 'carrier' => $this->carrierFrom($order, [], $direct)];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadHasSkuFields(array $payload): bool
    {
        $found = false;
        $walk = static function ($node) use (&$walk, &$found): void {
            if ($found || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                    continue;
                }
                $key = strtolower((string) $k);
                if (
                    in_array($key, ['sku', 'seller_sku', 'seller_part_number', 'sellernumber', 'part_number'], true)
                    || str_ends_with($key, '_sku')
                ) {
                    if (trim((string) $v) !== '') {
                        $found = true;

                        return;
                    }
                }
            }
        };
        $walk($payload);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadContainsSku(array $payload, string $want): bool
    {
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $found = false;
        $walk = static function ($node) use (&$walk, &$found, $matcher, $want): void {
            if ($found || ! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                    continue;
                }
                $key = strtolower((string) $k);
                if (
                    in_array($key, ['sku', 'seller_sku', 'seller_part_number', 'sellernumber', 'part_number'], true)
                    || str_ends_with($key, '_sku')
                ) {
                    if ($matcher->skusEqual((string) $v, $want)) {
                        $found = true;

                        return;
                    }
                }
            }
        };
        $walk($payload);

        return $found;
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
                    $info = is_array($fulfillment['tracking_info'] ?? null) ? $fulfillment['tracking_info'] : [];
                    if (trim((string) ($info['number'] ?? '')) !== '') {
                        $numbers[] = $info['number'];
                    }
                    if (! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                        $numbers = array_merge($numbers, $fulfillment['tracking_numbers']);
                    } elseif (! empty($fulfillment['tracking_number'])) {
                        $numbers[] = $fulfillment['tracking_number'];
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
            if ($lineItems === [] && trim($sku) !== '') {
                $prepared = $this->prepareShopifyFulfillmentOrders($storeUrl, $token, $shopifyOrderId, false, '');
                $lineItems = $prepared['line_items'] ?? [];
            }
            if ($lineItems === []) {
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
                $updated = $this->updateExistingShopifyFulfillmentTracking($storeUrl, $token, $shopifyOrderId, $tracking, $carrier);
                if (! empty($updated['success'])) {
                    return $updated;
                }
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
     * Fix an existing Doba Shopify order whose fulfillment / notes still have (UPS).
     *
     * @return array{success: bool, updated: bool, tracking?: string, message: string}
     */
    public function cleanExistingDobaShopifyTracking(string $shopifyOrderId): array
    {
        $config = $this->shopifyConfigFor('doba');
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $shopifyOrderId = trim($shopifyOrderId);
        if ($storeUrl === '' || $token === '' || $shopifyOrderId === '') {
            return ['success' => false, 'updated' => false, 'message' => 'Shopify credentials or order id missing.'];
        }

        $order = $this->shopifyOrderPayload($config, $shopifyOrderId);
        if ($order === null) {
            return ['success' => false, 'updated' => false, 'message' => 'Shopify order not found.'];
        }

        $dirty = '';
        $carrier = 'UPS';
        foreach ($order['fulfillments'] ?? [] as $fulfillment) {
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
            if ($number !== '' && DobaTrackingNumber::needsSanitize($number)) {
                $dirty = $number;
                $carrier = trim((string) ($fulfillment['tracking_company'] ?? '')) ?: 'UPS';
                break;
            }
        }

        $noteDirty = false;
        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $nameKey = strtolower(trim((string) ($attr['name'] ?? $attr['key'] ?? '')));
            $value = (string) ($attr['value'] ?? '');
            if (
                $value !== ''
                && DobaTrackingNumber::needsSanitize($value)
                && (str_contains($nameKey, 'tracking') || str_contains($nameKey, 'prepaid'))
            ) {
                $noteDirty = true;
                if ($dirty === '') {
                    $dirty = $value;
                }
            }
        }

        if ($dirty === '' && ! $noteDirty) {
            return ['success' => true, 'updated' => false, 'message' => 'Tracking already clean.'];
        }

        $clean = DobaTrackingNumber::sanitize($dirty);
        $updated = false;
        if ($clean !== '' && DobaTrackingNumber::needsSanitize($dirty)) {
            $res = $this->updateExistingShopifyFulfillmentTracking($storeUrl, $token, $shopifyOrderId, $clean, $carrier);
            $updated = ! empty($res['success']);
            if (! $updated) {
                return [
                    'success' => false,
                    'updated' => false,
                    'tracking' => $clean,
                    'message' => (string) ($res['message'] ?? 'Shopify tracking update failed.'),
                ];
            }
        }

        $this->rewriteDobaShopifyPrepaidNote($config, $shopifyOrderId);
        if ($clean !== '') {
            $this->cacheTrackingOnShopifyRawOrder($shopifyOrderId, $clean, $carrier);
        }

        return [
            'success' => true,
            'updated' => $updated || $noteDirty,
            'tracking' => $clean,
            'message' => $clean !== '' ? 'Cleaned to '.$clean.'.' : 'Prepaid note cleaned.',
        ];
    }

    /**
     * Strip carrier suffixes from Doba "Tracking Number of Prepaid Label" notes.
     *
     * @param  array{store_url?: string, token?: string}  $config
     */
    public function rewriteDobaShopifyPrepaidNote(array $config, string $shopifyOrderId): void
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $shopifyOrderId = trim($shopifyOrderId);
        if ($storeUrl === '' || $token === '' || $shopifyOrderId === '') {
            return;
        }

        $order = $this->shopifyOrderPayload($config, $shopifyOrderId);
        if ($order === null) {
            return;
        }

        $attrs = $order['note_attributes'] ?? [];
        if (! is_array($attrs) || $attrs === []) {
            return;
        }

        $changed = false;
        $next = [];
        foreach ($attrs as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = trim((string) ($attr['name'] ?? $attr['key'] ?? ''));
            $value = (string) ($attr['value'] ?? '');
            $nameKey = strtolower($name);
            if (
                $value !== ''
                && DobaTrackingNumber::needsSanitize($value)
                && (
                    str_contains($nameKey, 'tracking')
                    || str_contains($nameKey, 'prepaid')
                )
            ) {
                $value = DobaTrackingNumber::sanitize($value);
                $changed = true;
            }
            $next[] = ['name' => $name, 'value' => $value];
        }

        if (! $changed) {
            return;
        }

        try {
            $this->shopifyApi($storeUrl, $token, 'PUT', "orders/{$shopifyOrderId}.json", [
                'order' => [
                    'id' => (int) $shopifyOrderId,
                    'note_attributes' => $next,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VeeqoShopifyFulfillmentService: Doba prepaid note rewrite failed', [
                'shopify_order_id' => $shopifyOrderId,
                'error' => $e->getMessage(),
            ]);
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

        if ($items === [] && $want !== '') {
            $fallback = [];
            foreach ($fo['line_items'] ?? [] as $li) {
                if (! is_array($li) || empty($li['id'])) {
                    continue;
                }
                $qty = (int) ($li['fulfillable_quantity'] ?? 0);
                if ($qty < 1) {
                    $qty = (int) ($li['quantity'] ?? 0) - (int) ($li['fulfilled_quantity'] ?? 0);
                }
                if ($qty < 1) {
                    continue;
                }
                $fallback[] = ['id' => (int) $li['id'], 'quantity' => $qty];
            }
            if (count($fallback) === 1) {
                return $fallback;
            }
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
                $verb = strtoupper($method);
                $last = match ($verb) {
                    'POST' => $req->post($url, $payload),
                    'PUT' => $req->put($url, $payload),
                    default => $req->get($url, $payload),
                };
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
        array $marketplaceOrderIds = [],
        bool $requireOrderAndSku = false
    ): ?array {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return null;
        }

        $sku = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
        $orderIds = app(ShopifyFulfillmentTrackingMatcher::class)->uniqueIds($marketplaceOrderIds);
        if ($requireOrderAndSku && ($sku === '' || $orderIds === [])) {
            return null;
        }
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
                $info = is_array($fulfillment['tracking_info'] ?? null) ? $fulfillment['tracking_info'] : [];
                $number = trim((string) ($info['number'] ?? ''));
                if ($number === '' && ! empty($fulfillment['tracking_numbers']) && is_array($fulfillment['tracking_numbers'])) {
                    $number = trim((string) ($fulfillment['tracking_numbers'][0] ?? ''));
                }
                if ($number === '' && ! empty($fulfillment['tracking_number'])) {
                    $number = trim((string) $fulfillment['tracking_number']);
                }
                if ($number === '') {
                    continue;
                }
                $carrier = trim((string) ($fulfillment['tracking_company'] ?? ''));
                if ($carrier === '') {
                    $carrier = trim((string) ($info['company'] ?? ''));
                }

                return [
                    'tracking' => strtoupper(preg_replace('/\s+/', '', $number) ?? $number),
                    'carrier' => $carrier !== '' ? $carrier : 'Other',
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
                'fields' => 'id,name,tags,note,note_attributes,source_name,source_identifier,line_items,fulfillments',
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
                $skuCol = $this->marketplaceSkuColumn($marketplace, $table);
                $select = ['id', $uniqueCol];
                if ($skuCol !== null) {
                    $select[] = $skuCol;
                }
                if (Schema::hasColumn($table, $dateCol)) {
                    $query->orderByDesc($dateCol);
                }
                $query->orderByDesc('id')->limit($limit * 40);
                foreach ($query->get($select) as $row) {
                    $key = trim((string) ($row->{$uniqueCol} ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    if ($this->isStrictTrackingMarketplace($marketplace) && $skuCol !== null) {
                        $sku = trim((string) ($row->{$skuCol} ?? ''));
                        if ($sku === '' || in_array(strtolower($sku), ['__order__', '__unknown__'], true)) {
                            continue;
                        }
                        $key .= '|'.strtoupper($sku);
                    }
                    if (isset($seen[$key])) {
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
        if ($action === 'shopify_fulfilled') {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'done'), 1, now()->addHours(6));

            return;
        }
        if ($action === 'already_on_shopify') {
            // Shopify has a label — keep retrying the marketplace declare.
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'done'), 1, now()->addMinutes(20));

            return;
        }
        if (in_array($action, ['tracking_not_found', 'not_linked', 'unsupported'], true)
            || (! empty($result['skipped']) && $action !== 'shopify_fulfilled')) {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'miss'), 1, now()->addMinutes(8));
        }
    }

    protected function autoFetchCacheKey(string $marketplace, int $orderId, string $kind): string
    {
        return 'mm_fetch_tracking_v2_'.$kind.':'.$marketplace.':'.$orderId;
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
        if (strtolower(trim($marketplace)) === 'doba') {
            $tn = DobaTrackingNumber::sanitize($tn);
        }
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
