<?php

namespace App\Services\MarketplaceManager;

use App\Models\AlibabaOrderMetric;
use App\Models\AliexpressOrderMetric;
use App\Models\AmazonOrder;
use App\Models\B5cB2bOrder;
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
use App\Services\TikTok2ShopService;
use App\Services\TikTokShopService;
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

    protected int $fulfillNest = 0;

    /** Current Shopify REST order id so 13-digit TikTok/Doba ids are not dropped. */
    protected string $shopifyOrderRestId = '';

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
        if ($this->fulfillNest > 0) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'nested',
                'message' => 'Shopify label copy already in progress.',
            ];
        }
        $this->fulfillNest++;
        try {
            return $this->fulfillMarketplaceOrderInner($marketplace, $orderId);
        } finally {
            $this->fulfillNest--;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function fulfillMarketplaceOrderInner(string $marketplace, int $orderId): array
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
            $pushed = [];
            $bundle = $this->fulfillShopifyFromLabelsAll(
                (string) $ctx['shopify_order_id'],
                (array) $ctx['shopify_config'],
                (array) $ctx['refs'],
                is_array($ctx['local_tracking'] ?? null) ? $ctx['local_tracking'] : null,
                $sku,
                is_array($ctx['marketplace_order_ids'] ?? null) ? $ctx['marketplace_order_ids'] : [],
                $marketplace
            );
            foreach ($bundle['results'] as $result) {
                $last = $result;
                $action = (string) ($result['action'] ?? '');
                $tn = strtoupper(trim((string) ($result['tracking'] ?? '')));
                // Always save onto the marketplace order + SOF even when Shopify
                // fulfill fails — Label Created must show the GOFO/4Seller number.
                if ($tn !== '' && strlen($tn) >= 8 && ! isset($pushed[$tn])) {
                    $this->persistTrackingOntoMarketplaceOrder(
                        $marketplace,
                        $orderId,
                        (string) ($ctx['shopify_order_id'] ?? ''),
                        $tn,
                        (string) ($result['carrier'] ?? '')
                    );
                    $pushed[$tn] = true;
                }
                if ($tn !== '' && in_array($action, ['shopify_fulfilled', 'already_on_shopify'], true)) {
                    $this->pushChannelTrackingAfterShopify($marketplace, $orderId, $result);
                }
                if (! empty($result['success']) || $action === 'shopify_fulfilled') {
                    $ok = $result;
                }
            }
        }

        return $ok ?? $last;
    }

    /**
     * Keep attaching unused Veeqo/GOFO labels until Shopify has no open qty for this SKU.
     *
     * @param  list<string>  $refs
     * @param  array{store_url?: string, token?: string}  $shopifyConfig
     * @param  array{tracking?: string, carrier?: string}|null  $localTracking
     * @param  list<string>  $marketplaceOrderIds
     * @return array{results: list<array<string, mixed>>, last: array<string, mixed>}
     */
    public function fulfillShopifyFromLabelsAll(
        string $shopifyOrderId,
        array $shopifyConfig,
        array $refs,
        ?array $localTracking,
        string $sku,
        array $marketplaceOrderIds,
        string $marketplace
    ): array {
        $results = [];
        $last = [
            'success' => false,
            'skipped' => true,
            'action' => 'tracking_not_found',
            'message' => 'No tracking found yet.',
        ];
        $guard = 0;
        while ($guard++ < 20) {
            $result = $this->fulfillShopifyFromLabels(
                $shopifyOrderId,
                $shopifyConfig,
                $refs,
                $localTracking,
                $sku,
                $marketplaceOrderIds,
                $marketplace
            );
            $last = $result;
            $results[] = $result;
            if ((string) ($result['action'] ?? '') === 'shopify_fulfilled') {
                continue;
            }
            break;
        }

        return ['results' => $results, 'last' => $last];
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
        $this->rememberShopifyOrderRestId($shopifyOrderId);
        if ($shopifyOrderId === '' || str_starts_with($shopifyOrderId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'not_linked',
                'message' => 'Order is not linked to a Shopify order yet. Import/push to Shopify first.',
            ];
        }

        $strict = $this->isStrictTrackingMarketplace($marketplace);
        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $marketplaceOrderIds = $matcher->fullOrderIdsFirst(
            $marketplaceOrderIds !== [] ? $marketplaceOrderIds : $refs
        );
        $marketplaceOrderIds = $matcher->fullOrderIdsFirst(
            $this->expandMarketplaceOrderIdVariants($marketplaceOrderIds)
        );
        $marketplace = strtolower(trim($marketplace));
        if ($marketplace !== '') {
            $marketplaceOrderIds = array_values(array_filter(
                $marketplaceOrderIds,
                static function ($id) use ($matcher, $marketplace) {
                    return $matcher->slugsCompatible(
                        $matcher->slugFromOrderId((string) $id),
                        $marketplace
                    );
                }
            ));
        }
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
        if ($existing !== null && $this->isStolenMarketplaceTracking(
            (string) ($existing['tracking'] ?? ''),
            $marketplace,
            $marketplaceOrderIds,
            $shopifyOrderId
        )) {
            Log::info('VeeqoShopifyFulfillmentService: wrong-channel tracking on Shopify — will replace', [
                'marketplace' => $marketplace,
                'shopify_order_id' => $shopifyOrderId,
                'wanted' => $marketplaceOrderIds,
                'tracking' => $existing['tracking'] ?? null,
            ]);
            $existing = null;
        }
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
        }

        if (strtolower(trim($marketplace)) === 'doba' && ! $this->dobaMayUseExternalLabel($localTracking, $shopifyConfig, $shopifyOrderId)) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'tracking_not_found',
                'message' => 'Doba order is not prepaid — Veeqo/GOFO tracking was not attached.',
            ];
        }

        $existingTrackings = $this->existingShopifyTrackings(
            $shopifyConfig,
            $shopifyOrderId,
            $sku,
            $marketplaceOrderIds,
            $strict
        );
        $found = $this->lookupLabelTracking(
            $refs,
            is_array($localTracking) ? $localTracking : null,
            false,
            $sku,
            $existingTrackings
        );
        $foundTn = VeeqoAllocationTracking::normalizeTracking((string) ($found['tracking'] ?? ''));
        if ($foundTn !== '' && in_array($foundTn, array_map(
            static fn ($tn) => VeeqoAllocationTracking::normalizeTracking((string) $tn),
            $existingTrackings
        ), true)) {
            $found = null;
        }
        $openQty = $this->shopifyOpenFulfillableQty($shopifyConfig, $shopifyOrderId, $sku);
        if ($found === null && $existingTrackings !== []) {
            $found = $this->lookupLabelTracking(
                $refs,
                is_array($localTracking) ? $localTracking : null,
                false,
                '',
                $existingTrackings
            );
        }
        if ($found !== null && $this->isStolenMarketplaceTracking(
            (string) ($found['tracking'] ?? ''),
            $marketplace,
            $marketplaceOrderIds,
            $shopifyOrderId
        )) {
            Log::info('VeeqoShopifyFulfillmentService: looked-up label belongs to another order — ignored', [
                'marketplace' => $marketplace,
                'shopify_order_id' => $shopifyOrderId,
                'wanted' => $marketplaceOrderIds,
                'tracking' => $found['tracking'] ?? null,
            ]);
            $found = null;
        }

        if (
            $existing !== null
            && $found !== null
            && $marketplace !== 'doba'
            && ! app(ShopifyFulfillmentTrackingMatcher::class)->trackingNumbersEqual(
                (string) ($existing['tracking'] ?? ''),
                (string) ($found['tracking'] ?? '')
            )
        ) {
            if ($openQty > 0) {
                Log::info('VeeqoShopifyFulfillmentService: extra Veeqo label for remaining Shopify qty', [
                    'marketplace' => $marketplace,
                    'shopify_order_id' => $shopifyOrderId,
                    'wanted' => $marketplaceOrderIds,
                    'shopify_tracking' => $existing['tracking'] ?? null,
                    'label_tracking' => $found['tracking'] ?? null,
                    'open_qty' => $openQty,
                ]);
                $existing = null;
            } else {
                Log::info('VeeqoShopifyFulfillmentService: Shopify tracking is not this order\'s label — replacing', [
                    'marketplace' => $marketplace,
                    'shopify_order_id' => $shopifyOrderId,
                    'wanted' => $marketplaceOrderIds,
                    'shopify_tracking' => $existing['tracking'] ?? null,
                    'label_tracking' => $found['tracking'] ?? null,
                ]);
                $existing = null;
            }
        }

        if (
            $found === null
            && $existing !== null
            && self::shouldFulfillRemainingWithExistingTracking(
                $openQty,
                (string) ($existing['tracking'] ?? '')
            )
        ) {
            $found = [
                'tracking' => (string) ($existing['tracking'] ?? ''),
                'carrier' => (string) ($existing['carrier'] ?? 'Other'),
                'source' => 'shopify',
            ];
            $existing = null;
        }

        if ($existing !== null) {
            $existingTn = (string) ($existing['tracking'] ?? '');
            $existingCarrier = (string) ($existing['carrier'] ?? '');
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
            $sku,
            ((string) ($found['source'] ?? '') === 'shopify' && $openQty > 0) ? $openQty : 0
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
     * @param  list<string>  $excludeTrackings
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    public function lookupLabelTracking(array $refs, ?array $localTracking = null, bool $fast = false, string $sku = '', array $excludeTrackings = []): ?array
    {
        $exclude = [];
        foreach ($excludeTrackings as $tn) {
            $key = VeeqoAllocationTracking::normalizeTracking((string) $tn);
            if ($key !== '') {
                $exclude[$key] = true;
            }
        }

        $localHit = self::sofLocalTrackingIfReady($localTracking, array_keys($exclude));
        if ($localHit !== null) {
            return $localHit;
        }

        if (Cache::get('mm.label_ssl_broken')) {
            return $localHit;
        }

        $clean = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '' || in_array($ref, $clean, true)) {
                continue;
            }
            $clean[] = $ref;
        }
        $marketRefs = $this->marketplaceLookupRefs($clean, $fast ? 4 : 10);

        // Labels are usually bought in 4Seller/GOFO (all marketplaces). Try those
        // before Veeqo so Amazon/eBay/Temu Shopify copies get fulfilled first.
        $labelHit = $this->lookupWarehouseLabelTracking($marketRefs !== [] ? $marketRefs : $clean, $fast);
        if ($labelHit !== null) {
            return $labelHit;
        }

        if ($this->veeqo->isConfigured()) {
            $veeqoRefs = $fast
                ? array_slice($marketRefs !== [] ? $marketRefs : $clean, 0, 2)
                : $this->strongMarketplaceRefs($marketRefs !== [] ? $marketRefs : $clean);
            $veeqo = $veeqoRefs === []
                ? null
                : $this->findVeeqoShipment($veeqoRefs, $fast, $sku, $excludeTrackings);
            if ($veeqo !== null && trim((string) ($veeqo['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $veeqo['tracking'],
                    'carrier' => (string) ($veeqo['carrier'] ?? 'Veeqo'),
                    'source' => 'veeqo',
                ];
            }
        }

        return $localHit;
    }

    /**
     * 4Seller/GOFO label lookup by marketplace platform order id (all channels).
     *
     * @param  list<string>  $refs
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    protected function lookupWarehouseLabelTracking(array $refs, bool $fast = false): ?array
    {
        $gofoRefs = $this->strongMarketplaceRefs($refs);
        if ($gofoRefs === []) {
            return null;
        }

        if ($this->gofo->isConfigured()) {
            $gofo = $this->gofo->findShipment($gofoRefs, $fast);
            if ($gofo !== null && trim((string) ($gofo['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $gofo['tracking'],
                    'carrier' => (string) ($gofo['carrier'] ?? 'GOFO'),
                    'source' => 'gofo',
                ];
            }
        }

        // Always try 4Seller — labels bought there often use S20… as GOFO orderNo
        // while the platform id only exists in 4Seller. Fast pulls used to skip this
        // and left Amazon/eBay Label Created rows blank.
        if ($this->fourSeller->isConfigured()) {
            $fs = $this->fourSeller->findShipment($gofoRefs);
            if ($fs !== null && trim((string) ($fs['tracking'] ?? '')) !== '') {
                $hit = [
                    'tracking' => (string) $fs['tracking'],
                    'carrier' => (string) ($fs['carrier'] ?? 'GOFO'),
                    'source' => '4seller',
                ];
                // 4Seller often stores GOFO orderNo as S20… — retry GOFO with that id.
                $gofoOrderNo = trim((string) ($fs['gofo_order_no'] ?? $fs['order_no'] ?? ''));
                if ($gofoOrderNo !== '' && $this->gofo->isConfigured()) {
                    $viaGofo = $this->gofo->findShipment([$gofoOrderNo], true);
                    if ($viaGofo !== null && trim((string) ($viaGofo['tracking'] ?? '')) !== '') {
                        return [
                            'tracking' => (string) $viaGofo['tracking'],
                            'carrier' => (string) ($viaGofo['carrier'] ?? $hit['carrier']),
                            'source' => 'gofo',
                        ];
                    }
                }

                return $hit;
            }
        }

        return null;
    }

    /**
     * Native channel APIs (TikTok / eBay / Shein / Temu / …) after Veeqo/GOFO miss.
     *
     * @param  list<string>  $ids
     * @return array{tracking: string, carrier: string, source?: string}|null
     */
    public function lookupLiveChannelTracking(string $marketplace, array $ids): ?array
    {
        $hit = $this->pullLiveMarketplaceTracking(strtolower(trim($marketplace)), $ids);
        if ($hit === null || trim((string) ($hit['tracking'] ?? '')) === '') {
            return null;
        }
        $hit['source'] = $hit['source'] ?? 'channel';

        return $hit;
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
            $plain = ltrim($ref, '#');
            // Shopify/4Seller/GOFO Amazon copies: Amz111-… and hyphenless 3-7-7.
            if (preg_match('/^\d{3}-\d{7}-\d{7}$/', $plain) === 1) {
                foreach (['Amz'.$plain, '#Amz'.$plain, str_replace('-', '', $plain)] as $amzRef) {
                    if (! in_array($amzRef, $out, true)) {
                        $out[] = $amzRef;
                    }
                }
            } elseif (preg_match('/^Amz(\d{3}-\d{7}-\d{7})$/i', $plain, $amz) === 1) {
                $oid = (string) $amz[1];
                foreach ([$oid, str_replace('-', '', $oid)] as $amzRef) {
                    if (! in_array($amzRef, $out, true)) {
                        $out[] = $amzRef;
                    }
                }
            }
            if (preg_match('/^(?:temu2?-|aliexpress-|alibaba-|PO-|TT2?-|tiktok2?-|BBY\d{2}-)/i', $ref, $m)) {
                $tail = trim((string) preg_replace('/^(?:temu2?-|aliexpress-|alibaba-|PO-|TT2?-|tiktok2?-|BBY\d{2}-)/i', '', $ref));
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

        usort($out, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        return array_values($out);
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

        $sofFromShopify = $this->syncUnfulfilledShopifyFromSofTracking(min(250, max(80, (int) ceil($limit * 0.4))));
        $checked += (int) ($sofFromShopify['checked'] ?? 0);
        $fulfilled += (int) ($sofFromShopify['fulfilled'] ?? 0);
        $skipped += (int) ($sofFromShopify['skipped'] ?? 0);
        $failed += (int) ($sofFromShopify['failed'] ?? 0);

        $sofSweep = $this->syncSofTrackingToUnfulfilledShopify(min(400, max(150, (int) ceil($limit * 0.6))));
        $checked += (int) ($sofSweep['checked'] ?? 0);
        $fulfilled += (int) ($sofSweep['fulfilled'] ?? 0);
        $skipped += (int) ($sofSweep['skipped'] ?? 0);
        $failed += (int) ($sofSweep['failed'] ?? 0);

        $localSweep = $this->syncLocalTrackedLinkedOrders(min(800, max(150, (int) ceil($limit * 0.55))));
        $checked += (int) ($localSweep['checked'] ?? 0);
        $fulfilled += (int) ($localSweep['fulfilled'] ?? 0);
        $skipped += (int) ($localSweep['skipped'] ?? 0);
        $failed += (int) ($localSweep['failed'] ?? 0);
        $shopifyScanLimit = max(20, $limit - $checked);

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
        $limit = max(1, min(800, $limit));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;
        $seenShopify = [];
        $since = now('America/Los_Angeles')->subDays(21)->startOfDay();
        $map = $this->localTrackedMarketplaceMap();
        $perMarket = max(40, (int) ceil($limit / max(1, count($map))));

        foreach ($map as $slug => [$class, $dateCol]) {
            $table = (new $class)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'shopify_order_id')) {
                continue;
            }
            $query = $class::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->where('shopify_order_id', 'not like', 'manual%');
            if (Schema::hasColumn($table, $dateCol)) {
                $query->where($dateCol, '>=', $since);
            } elseif (Schema::hasColumn($table, 'created_at')) {
                $query->where('created_at', '>=', $since);
            }
            if (Schema::hasColumn($table, $dateCol)) {
                $query->orderByDesc($dateCol);
            }
            $rows = $query->orderByDesc('id')->limit(max(80, $perMarket * 6))->get();
            foreach ($rows as $row) {
                if ($checked >= $limit) {
                    break 2;
                }
                $shopifyId = (string) ($row->shopify_order_id ?? '');
                if ($shopifyId === '' || isset($seenShopify[$shopifyId])) {
                    continue;
                }
                if (! $this->marketplaceRowReadyToFulfill((string) $slug, $row)) {
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
                $cacheKey = 'mm_fetch_tracking_shopify_v6:'.$shopifyId;
                $orderLabel = trim((string) ($order['name'] ?? '')).' '.($marketplace !== '' ? $marketplace : 'marketplace');
                $isRecent = $this->shopifyOrderIsRecent($order);
                if (! $fresh && ! $isRecent && Cache::has($cacheKey)) {
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
                    $bundle = $this->fulfillShopifyFromLabelsAll(
                        $shopifyId,
                        $config,
                        $refs,
                        $local,
                        $sku,
                        $marketplaceOrderIds,
                        $marketplace
                    );
                    $lastResult = $bundle['last'];
                    foreach ($bundle['results'] as $pass) {
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
        $recentMin = now('America/Los_Angeles')->subDays(3)->startOfDay()->utc()->toIso8601String();
        $recentNeed = min(1500, max(400, $limit * 3));
        $recent = $this->interleaveNewestAndOldest(
            $this->listUnfulfilledShopifyOrders($storeUrl, $token, $recentNeed, [
                'created_at_min' => $recentMin,
                'max_pages' => 24,
            ]),
            $recentNeed
        );

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
        foreach (array_merge($recent, $older, $newest) as $order) {
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
     * @param  array<string, mixed>  $order
     */
    protected function shopifyOrderIsRecent(array $order): bool
    {
        $raw = trim((string) ($order['created_at'] ?? ''));
        if ($raw === '') {
            return false;
        }
        try {
            return \Illuminate\Support\Carbon::parse($raw)
                ->gte(now('America/Los_Angeles')->subDays(3)->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    /**
     * Tracking already on SOF / the marketplace row is enough to fulfill Shopify.
     * Do not wait on Veeqo/GOFO — those lookups hang and leave copies unfulfilled.
     *
     * @param  array{tracking?: string, carrier?: string}|null  $localTracking
     * @param  list<string>  $excludeTrackings
     * @return array{tracking: string, carrier: string, source: string}|null
     */
    /**
     * Veeqo / 3PL holds the fulfillment order — Admin cannot add tracking until we move it.
     *
     * @param  array<string, mixed>  $fo
     */
    public static function fulfillmentOrderAssignedToService(array $fo): bool
    {
        $name = strtolower((string) (data_get($fo, 'assigned_location.name') ?? ''));
        if (str_contains($name, 'fulfillment service') || str_contains($name, 'veeqo') || str_contains($name, 'gofo')) {
            return true;
        }
        $handle = strtolower((string) (data_get($fo, 'assigned_fulfillment_service.handle') ?? ''));
        if ($handle !== '' && $handle !== 'manual') {
            return true;
        }
        $request = strtolower((string) ($fo['request_status'] ?? ''));
        if (in_array($request, ['submitted', 'accepted', 'cancellation_rejected'], true)) {
            return true;
        }
        $actions = [];
        foreach ((array) ($fo['supported_actions'] ?? []) as $action) {
            $action = strtolower(trim((string) $action));
            if ($action !== '') {
                $actions[] = $action;
            }
        }

        return $actions !== [] && ! in_array('create_fulfillment', $actions, true);
    }

    public static function sofLocalTrackingIfReady(?array $localTracking, array $excludeTrackings = []): ?array
    {
        $tn = strtoupper(preg_replace('/\s+/', '', (string) ($localTracking['tracking'] ?? '')) ?? '');
        if (strlen($tn) < 8 || preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn) === 1) {
            return null;
        }
        foreach ($excludeTrackings as $have) {
            $have = strtoupper(preg_replace('/\s+/', '', (string) $have) ?? '');
            if ($have !== '' && $have === $tn) {
                return null;
            }
        }

        return [
            'tracking' => $tn,
            'carrier' => trim((string) ($localTracking['carrier'] ?? '')) ?: 'Other',
            'source' => 'sof',
        ];
    }

    /**
     * Walk Shopify Unfulfilled (last 7 days) and apply tracking already on SOF.
     * Does not call Veeqo/GOFO — those lookups starve yesterday’s copies behind ~10k open orders.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function syncUnfulfilledShopifyFromSofTracking(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $pagePush = $this->pushSofPageTrackingToShopify($limit);
        $checked = (int) ($pagePush['checked'] ?? 0);
        $fulfilled = (int) ($pagePush['fulfilled'] ?? 0);
        $skipped = (int) ($pagePush['skipped'] ?? 0);
        $failed = (int) ($pagePush['failed'] ?? 0);
        if ($checked >= $limit) {
            return [
                'checked' => $checked,
                'fulfilled' => $fulfilled,
                'skipped' => $skipped,
                'failed' => $failed,
                'message' => $pagePush['message'] ?? "Unfulfilled Shopify←SOF: checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
            ];
        }

        $since = now('America/Los_Angeles')->subDays(7)->startOfDay()->utc()->toIso8601String();
        foreach ($this->uniqueShopifyConfigs() as $config) {
            $storeUrl = trim((string) ($config['store_url'] ?? ''));
            $token = trim((string) ($config['token'] ?? ''));
            if ($storeUrl === '' || $token === '') {
                continue;
            }
            $orders = $this->listUnfulfilledShopifyOrders($storeUrl, $token, max($limit * 2, $limit), [
                'created_at_min' => $since,
                'max_pages' => 16,
            ]);
            foreach ($orders as $order) {
                if ($checked >= $limit) {
                    break 2;
                }
                if (! is_array($order)) {
                    continue;
                }
                $shopifyId = (string) ($order['id'] ?? '');
                if ($shopifyId === '' || $this->shopifyOrderLooksFba($order)) {
                    continue;
                }
                $identity = $this->marketplaceIdentityFromShopifyOrder($order);
                $marketplace = (string) ($identity['slug'] ?? '');
                $ids = is_array($identity['ids'] ?? null) ? $identity['ids'] : [];
                if ($marketplace === '' || $ids === []) {
                    continue;
                }
                $ready = self::sofLocalTrackingIfReady(
                    $this->sofTrackingForUnfulfilledShopify($marketplace, $ids)
                );
                if ($ready === null) {
                    continue;
                }
                $checked++;
                $skus = $this->skusFromShopifyOrder($order);
                if ($skus === []) {
                    $skus = [''];
                }
                $anyOk = false;
                $already = false;
                $lastMessage = 'Shopify fulfill did not run.';
                foreach ($skus as $sku) {
                    $created = $this->createShopifyFulfillment(
                        $config,
                        $shopifyId,
                        $ready['tracking'],
                        $ready['carrier'],
                        (string) $sku,
                        0
                    );
                    if (empty($created['success'])) {
                        $lastMessage = (string) ($created['message'] ?? 'Shopify fulfill failed.');
                        continue;
                    }
                    $anyOk = true;
                    $already = $already || ! empty($created['already']);
                    $lastMessage = (string) ($created['message'] ?? ('Shopify fulfilled with '.$ready['tracking']));
                    $this->cacheTrackingOnShopifyRawOrder($shopifyId, $ready['tracking'], $ready['carrier']);
                    $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);
                    if ($model !== null) {
                        $this->persistTrackingOntoMarketplaceOrder(
                            $marketplace,
                            (int) $model->id,
                            $shopifyId,
                            $ready['tracking'],
                            $ready['carrier']
                        );
                    }
                    if (empty($created['already'])) {
                        break;
                    }
                }
                if ($anyOk && ! $already) {
                    $fulfilled++;
                } elseif ($anyOk) {
                    $skipped++;
                } else {
                    $failed++;
                    Log::info('VeeqoShopifyFulfillmentService: SOF→Shopify fulfill failed', [
                        'shopify_order_id' => $shopifyId,
                        'marketplace' => $marketplace,
                        'tracking' => $ready['tracking'],
                        'message' => $lastMessage,
                    ]);
                }
                usleep(80000);
            }
        }

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "Unfulfilled Shopify←SOF: checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array{tracking: string, carrier: string}|null
     */
    protected function sofTrackingForUnfulfilledShopify(string $marketplace, array $ids): ?array
    {
        $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);
        if ($model === null) {
            return null;
        }
        if ($marketplace === 'amazon' && $model instanceof AmazonOrder) {
            $hit = $model->localTracking();
            if (trim((string) ($hit['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $hit['tracking'],
                    'carrier' => trim((string) ($hit['carrier'] ?? '')) ?: 'Other',
                ];
            }
        }
        if (in_array($marketplace, ['tiktok', 'tiktok2'], true)) {
            $raw = $model->raw_json ?? $model->raw_payload ?? $model->raw_data ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw)) {
                $hit = self::trackingFromTikTokOrderPayload($raw);
                if (is_array($hit) && trim((string) ($hit['tracking'] ?? '')) !== '') {
                    return $hit;
                }
            }
        }

        return $this->trackingFromLoadedMarketplaceModel($marketplace, $model);
    }

    /**
     * Use the exact tracking numbers /sales-order-fulfillment already displays.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function pushSofPageTrackingToShopify(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;
        $rows = app(\App\Http\Controllers\Channels\SalesOrderFulfillmentController::class)
            ->trackingRowsReadyForShopifyPush(max($limit * 2, $limit));

        foreach ($rows as $row) {
            if ($checked >= $limit) {
                break;
            }
            $marketplace = (string) ($row['marketplace'] ?? '');
            $tracking = (string) ($row['tracking'] ?? '');
            $carrier = (string) ($row['carrier'] ?? 'Other');
            $storedId = (string) ($row['shopify_order_id'] ?? '');
            if ($marketplace === '' || $tracking === '' || $storedId === '') {
                continue;
            }
            $config = $this->shopifyConfigFor($marketplace);
            $shopifyId = $this->resolveShopifyRestOrderId($config, $storedId);
            if ($shopifyId === '') {
                $failed++;
                $checked++;
                Log::info('VeeqoShopifyFulfillmentService: SOF page row has no Shopify REST id', [
                    'marketplace' => $marketplace,
                    'stored' => $storedId,
                    'tracking' => $tracking,
                ]);
                continue;
            }
            $checked++;
            $created = $this->createShopifyFulfillment(
                $config,
                $shopifyId,
                $tracking,
                $carrier,
                (string) ($row['sku'] ?? ''),
                0
            );
            if (empty($created['success'])) {
                $created = $this->createShopifyFulfillment($config, $shopifyId, $tracking, $carrier, '', 0);
            }
            if (! empty($created['success']) && empty($created['already'])) {
                $fulfilled++;
                $this->cacheTrackingOnShopifyRawOrder($shopifyId, $tracking, $carrier);
                $rowId = (int) ($row['row_id'] ?? 0);
                if ($rowId > 0) {
                    $this->persistTrackingOntoMarketplaceOrder($marketplace, $rowId, $shopifyId, $tracking, $carrier);
                }
            } elseif (! empty($created['success'])) {
                $skipped++;
            } else {
                $failed++;
                Log::info('VeeqoShopifyFulfillmentService: SOF page→Shopify fulfill failed', [
                    'marketplace' => $marketplace,
                    'shopify_order_id' => $shopifyId,
                    'tracking' => $tracking,
                    'message' => $created['message'] ?? '',
                ]);
            }
            usleep(80000);
        }

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "SOF page→Shopify: checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * Shopify Admin REST needs the 13-digit id, not #3427433.
     */
    protected function resolveShopifyRestOrderId(array $config, string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '' || str_starts_with($stored, 'manual')) {
            return '';
        }
        if (preg_match('/^\d{10,}$/', $stored) === 1) {
            return $stored;
        }

        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return '';
        }
        $name = ltrim($stored, '#');
        if ($name === '') {
            return '';
        }
        try {
            $gql = $this->shopifyApi($storeUrl, $token, 'POST', 'graphql.json', [
                'query' => 'query ($q: String!) { orders(first: 5, query: $q) { edges { node { id name } } } }',
                'variables' => ['q' => 'name:#'.$name.' OR name:'.$name],
            ]);
            foreach ($gql?->json('data.orders.edges') ?? [] as $edge) {
                $gid = (string) data_get($edge, 'node.id', '');
                if (preg_match('/(\d{10,})$/', $gid, $m)) {
                    return $m[1];
                }
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }

    /**
     * Push tracking already shown on /sales-order-fulfillment onto unfulfilled Shopify copies.
     *
     * @return array{checked: int, fulfilled: int, skipped: int, failed: int, message: string}
     */
    public function syncSofTrackingToUnfulfilledShopify(int $limit = 200): array
    {
        $limit = max(1, min(800, $limit));
        $checked = 0;
        $fulfilled = 0;
        $skipped = 0;
        $failed = 0;
        $seenShopify = [];
        $since = now('America/Los_Angeles')->subDays(30)->startOfDay();
        $map = $this->localTrackedMarketplaceMap();
        $perMarket = max(30, (int) ceil($limit / max(1, count($map))));

        foreach ($map as $slug => [$class, $dateCol]) {
            $table = (new $class)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'shopify_order_id')) {
                continue;
            }
            $query = $class::query()
                ->whereNotNull('shopify_order_id')
                ->where('shopify_order_id', '!=', '')
                ->where('shopify_order_id', 'not like', 'manual%');
            if ($slug === 'amazon' && Schema::hasColumn($table, 'fulfillment_channel')) {
                $query->where(function ($q) {
                    $q->whereNull('fulfillment_channel')
                        ->orWhereRaw("UPPER(TRIM(COALESCE(fulfillment_channel, ''))) != ?", ['AFN']);
                });
            }
            if (Schema::hasColumn($table, $dateCol)) {
                $query->where($dateCol, '>=', $since)->orderByDesc($dateCol);
            } elseif (Schema::hasColumn($table, 'created_at')) {
                $query->where('created_at', '>=', $since);
            }
            $rows = $query->orderByDesc('id')->limit(max(80, $perMarket * 6))->get();
            foreach ($rows as $row) {
                if ($checked >= $limit) {
                    break 2;
                }
                $shopifyId = trim((string) ($row->shopify_order_id ?? ''));
                if ($shopifyId === '' || isset($seenShopify[$shopifyId])) {
                    continue;
                }
                if ($slug === 'amazon' && $row instanceof AmazonOrder && $row->isFba()) {
                    continue;
                }
                $local = $this->trackingFromLoadedMarketplaceModel((string) $slug, $row);
                $ready = self::sofLocalTrackingIfReady($local);
                if ($ready === null) {
                    continue;
                }
                $seenShopify[$shopifyId] = true;
                $checked++;
                $result = $this->fulfillShopifyWithKnownTracking(
                    (string) $slug,
                    $row,
                    $ready['tracking'],
                    $ready['carrier']
                );
                if (! empty($result['success']) && ($result['action'] ?? '') === 'shopify_fulfilled') {
                    $fulfilled++;
                } elseif (! empty($result['skipped']) || ($result['action'] ?? '') === 'already_on_shopify') {
                    $skipped++;
                } else {
                    $failed++;
                }
                usleep(80000);
            }
        }

        return [
            'checked' => $checked,
            'fulfilled' => $fulfilled,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => "SOF→Shopify: checked {$checked}, fulfilled {$fulfilled}, skipped {$skipped}, failed {$failed}.",
        ];
    }

    /**
     * @return array{success: bool, skipped?: bool, action?: string, message: string, tracking?: string, carrier?: string}
     */
    protected function fulfillShopifyWithKnownTracking(
        string $marketplace,
        object $row,
        string $tracking,
        string $carrier
    ): array {
        $shopifyId = trim((string) ($row->shopify_order_id ?? ''));
        if ($shopifyId === '' || str_starts_with($shopifyId, 'manual')) {
            return [
                'success' => false,
                'skipped' => true,
                'action' => 'not_linked',
                'message' => 'Order is not linked to a Shopify order yet.',
            ];
        }

        $config = $this->shopifyConfigFor($marketplace);
        $skus = $this->skuListFromMarketplaceModel($marketplace, $row);
        if ($skus === []) {
            $skus = [''];
        }

        $last = [
            'success' => false,
            'skipped' => true,
            'action' => 'shopify_fulfill_failed',
            'message' => 'Shopify fulfill did not run.',
            'tracking' => $tracking,
            'carrier' => $carrier,
        ];
        $anyOk = false;
        foreach ($skus as $sku) {
            $created = $this->createShopifyFulfillment($config, $shopifyId, $tracking, $carrier, (string) $sku, 0);
            if (empty($created['success'])) {
                $last = [
                    'success' => false,
                    'action' => 'shopify_fulfill_failed',
                    'message' => (string) ($created['message'] ?? 'Shopify fulfill failed.'),
                    'tracking' => $tracking,
                    'carrier' => $carrier,
                ];
                continue;
            }
            $anyOk = true;
            $this->cacheTrackingOnShopifyRawOrder($shopifyId, $tracking, $carrier);
            $this->persistTrackingOntoMarketplaceOrder(
                $marketplace,
                (int) $row->id,
                $shopifyId,
                $tracking,
                $carrier
            );
            $last = [
                'success' => true,
                'skipped' => ! empty($created['already']),
                'action' => ! empty($created['already']) ? 'already_on_shopify' : 'shopify_fulfilled',
                'message' => (string) ($created['message'] ?? ('Shopify fulfilled with '.$tracking)),
                'tracking' => $tracking,
                'carrier' => $carrier,
            ];
            if (empty($created['already'])) {
                break;
            }
        }

        return $anyOk ? array_merge($last, ['success' => true]) : $last;
    }

    protected function localTrackedMarketplaceMap(): array
    {
        return [
            'amazon' => [AmazonOrder::class, 'order_date'],
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
            'b5cb2b' => [B5cB2bOrder::class, 'ordered_at'],
        ];
    }

    protected function firstTrackingColumn(string $table): ?string
    {
        foreach (['tracking_number', 'tracking_reference', 'tracking', 'tracking_no', 'shipment_tracking'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    /**
     * True when the marketplace row already has a label or is marked shipped.
     */
    protected function marketplaceRowReadyToFulfill(string $marketplace, object $row): bool
    {
        $local = $this->trackingFromLoadedMarketplaceModel($marketplace, $row);
        if ($local !== null && strlen(trim((string) ($local['tracking'] ?? ''))) >= 8) {
            return true;
        }

        foreach ([
            'order_status', 'line_status', 'status', 'fulfillment_status',
            'shipping_status', 'order_state', 'package_status',
        ] as $field) {
            if (self::marketplaceStatusLooksShipped((string) ($row->{$field} ?? ''))) {
                return true;
            }
        }

        return false;
    }

    public static function marketplaceStatusLooksShipped(string $status): bool
    {
        $status = strtoupper(trim($status));
        if ($status === '') {
            return false;
        }
        if (preg_match('/CANCEL|REFUND|RETURN|UNPAID|ON.?HOLD|AWAITING.?SHIPMENT|PENDING|UNFULFILL/', $status)) {
            return false;
        }

        return (bool) preg_match(
            '/SHIP|IN.?TRANSIT|DELIVER|COMPLETE|FULFILL|RTS|AWAITING.?COLLECTION|COLLECTED|DISPATCH|PACKAGE/',
            $status
        );
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
                'fields' => 'id,name,created_at,tags,note,note_attributes,source_name,source_identifier,fulfillment_status,line_items',
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
        if (! empty($order['id'])) {
            $this->rememberShopifyOrderRestId((string) $order['id']);
        }
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
        if ($amazonId !== '' && ($slug === '' || $slug === 'amazon')) {
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

        $nameId = self::tiktokOrderIdFromShopifyName((string) ($order['name'] ?? ''));
        if ($nameId !== '') {
            $pushId($nameId);
            if ($slug === '') {
                $slug = str_starts_with(strtoupper(ltrim((string) ($order['name'] ?? ''), '#')), 'TT2')
                    ? 'tiktok2'
                    : 'tiktok';
            }
        }
        if (preg_match('/tiktok(?:\s+shop)?\s+order\s+(\d{12,20})/i', $rawHay, $noteTikTok)) {
            $pushId((string) $noteTikTok[1]);
            if ($slug === '') {
                $slug = 'tiktok';
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
            if (preg_match('/^\d+_(\d{8,}-[A-Z])(?:-\d+)?$/i', $id, $m)) {
                $tail = trim((string) ($m[1] ?? ''));
                if ($tail !== '') {
                    $candidates[] = $tail;
                }
            }
            if (preg_match('/^(?:TT2?|tiktok2?)-(.+)$/i', $id, $m)) {
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

        if (in_array($marketplace, ['newegg', 'reverb', 'aliexpress', 'alibaba', 'faire', 'shein', 'bestbuy', 'macy', 'topdawg', 'wayfair'], true)) {
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

        if (in_array($marketplace, ['tiktok', 'tiktok2'], true)) {
            $api = $marketplace === 'tiktok2'
                ? app(TikTok2ShopService::class)
                : app(TikTokShopService::class);
            foreach ($ids as $id) {
                $orderId = self::tiktokOrderIdFromShopifyName($id);
                if ($orderId === '' && preg_match('/^\d{12,20}$/', $id)) {
                    $orderId = $id;
                }
                if ($orderId === '' || $this->isShopifyInternalIdRef($orderId)) {
                    continue;
                }
                try {
                    if (method_exists($api, 'isAuthenticated') && ! $api->isAuthenticated()) {
                        break;
                    }
                    $details = $api->getOrderDetails([$orderId]);
                } catch (\Throwable $e) {
                    Log::info('VeeqoShopifyFulfillmentService: TikTok order detail tracking failed', [
                        'marketplace' => $marketplace,
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                $orders = is_array($details)
                    ? ($details['orders'] ?? $details['data']['orders'] ?? [])
                    : [];
                foreach (is_array($orders) ? $orders : [] as $order) {
                    if (! is_array($order)) {
                        continue;
                    }
                    $hit = self::trackingFromTikTokOrderPayload($order);
                    if ($hit !== null) {
                        return $hit;
                    }
                }
            }
            $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);

            return $model !== null
                ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
                : null;
        }

        $model = $this->findMarketplaceOrderByChannelIds($marketplace, $ids);

        return $model !== null
            ? $this->trackingFromLoadedMarketplaceModel($marketplace, $model)
            : null;
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
            'bestbuy' => [BestBuyOrderMetric::class, ['order_id', 'channel_order_id', 'order_line_id']],
            'macy' => [MacyOrderMetric::class, ['order_id', 'channel_order_id']],
            'wayfair' => [WayfairDailyData::class, ['po_number']],
            'purchasingpower' => [PurchasingPowerSale::class, ['order_id', 'order_number']],
            'doba' => [DobaDailyData::class, ['order_no', 'platform_order_no']],
            'tiktok' => [TiktokOrder::class, ['order_id']],
            'tiktok2' => [Tiktok2Order::class, ['order_id']],
            'pls' => [PlsSale::class, ['order_name', 'order_number']],
            'b5cb2b' => [B5cB2bOrder::class, ['store_order_id']],
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
        if ($marketplace === 'amazon' && $model instanceof AmazonOrder) {
            $hit = $model->localTracking();
            if (trim((string) ($hit['tracking'] ?? '')) !== '') {
                return [
                    'tracking' => (string) $hit['tracking'],
                    'carrier' => trim((string) ($hit['carrier'] ?? '')) ?: 'Other',
                ];
            }
        }
        if (in_array($marketplace, ['tiktok', 'tiktok2'], true)) {
            $raw = $model->raw_json ?? $model->raw_payload ?? $model->raw_data ?? null;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw)) {
                $hit = self::trackingFromTikTokOrderPayload($raw);
                if (is_array($hit) && trim((string) ($hit['tracking'] ?? '')) !== '') {
                    return $hit;
                }
            }
        }

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
                'refs' => array_values(array_filter(array_unique(array_merge(
                    AmazonOrder::warehouseOrderRefs((string) $order->amazon_order_id),
                    [$seller]
                )))),
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
            'bestbuy' => [BestBuyOrderMetric::class, ['order_id', 'channel_order_id', 'order_line_id']],
            'macy' => [MacyOrderMetric::class, ['order_id', 'channel_order_id']],
            'wayfair' => [WayfairDailyData::class, ['po_number']],
            'purchasingpower' => [PurchasingPowerSale::class, ['order_id', 'order_number']],
            'doba' => [DobaDailyData::class, ['order_no', 'platform_order_no']],
            'tiktok' => [TiktokOrder::class, ['order_id']],
            'tiktok2' => [Tiktok2Order::class, ['order_id']],
            'pls' => [PlsSale::class, ['order_name', 'order_number']],
            'b5cb2b' => [B5cB2bOrder::class, ['store_order_id']],
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

        if ($marketplace === 'bestbuy' && $model instanceof BestBuyOrderMetric) {
            $oid = trim((string) ($model->order_id ?? ''));
            $cid = trim((string) ($model->channel_order_id ?? ''));
            if ($oid !== '' || $cid !== '') {
                $family = BestBuyOrderMetric::query()
                    ->when($oid !== '', fn ($q) => $q->where('order_id', $oid))
                    ->when($oid === '' && $cid !== '', fn ($q) => $q->where('channel_order_id', $cid))
                    ->get(['order_id', 'channel_order_id', 'order_line_id']);
                foreach ($family as $sibling) {
                    foreach (['order_id', 'channel_order_id', 'order_line_id'] as $field) {
                        $value = trim((string) ($sibling->{$field} ?? ''));
                        if ($value !== '' && ! in_array($value, $refs, true)) {
                            $refs[] = $value;
                        }
                    }
                }
            }
        }

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
    public function findVeeqoShipment(array $refs, bool $fast = false, string $sku = '', array $excludeTrackings = []): ?array
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
            $hit = $this->searchVeeqoOrders($ref, $clean, $sku, $excludeTrackings);
            if ($hit !== null) {
                return $hit;
            }
            if ($fast) {
                continue;
            }
            $hit = $this->searchVeeqoShipments($ref, $clean, $sku, $excludeTrackings);
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
    protected function searchVeeqoOrders(string $query, array $allRefs, string $sku = '', array $excludeTrackings = []): ?array
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
            $ship = $this->extractShipment($order, $sku, $excludeTrackings);
            if ($ship !== null) {
                $ship['veeqo_order_id'] = isset($order['id']) && is_numeric($order['id']) ? (int) $order['id'] : null;

                return $ship;
            }
            if (isset($order['id']) && is_numeric($order['id'])) {
                $full = $this->veeqo->getOrder((int) $order['id']);
                if (! empty($full['ok']) && is_array($full['data'] ?? null)) {
                    $ship = $this->extractShipment($full['data'], $sku, $excludeTrackings);
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
    protected function searchVeeqoShipments(string $query, array $allRefs, string $sku = '', array $excludeTrackings = []): ?array
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
            $ship = $this->extractShipment($row, $sku, $excludeTrackings);
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

    protected function rememberShopifyOrderRestId(string $shopifyOrderId): void
    {
        $this->shopifyOrderRestId = preg_match('/^\d{13}$/', trim($shopifyOrderId)) === 1
            ? trim($shopifyOrderId)
            : '';
    }

    /**
     * Shopify Admin REST ids are 13 digits (e.g. 7159464132845).
     * TikTok / Doba marketplace ids are also 13 digits and must be kept unless
     * they are this order's own Shopify REST id.
     */
    protected function isShopifyInternalIdRef(string $ref): bool
    {
        return self::isShopifyAdminRestId($ref, $this->shopifyOrderRestId);
    }

    public static function isShopifyAdminRestId(string $ref, string $shopifyOrderId = ''): bool
    {
        $n = strtolower(preg_replace('/\s+/', '', ltrim(trim($ref), '#')) ?? '');
        if ($n === '' || preg_match('/^\d{13}$/', $n) !== 1) {
            return false;
        }
        // Doba / dated marketplace ids: 26083068732127
        if (preg_match('/^2\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{4,}$/', $n) === 1) {
            return false;
        }
        $shopifyId = preg_replace('/\D+/', '', $shopifyOrderId) ?? '';

        return strlen($shopifyId) === 13 && $n === $shopifyId;
    }

    public static function shouldFulfillRemainingWithExistingTracking(int $openQty, string $existingTracking): bool
    {
        return $openQty > 0 && strlen(trim($existingTracking)) >= 8;
    }

    public static function tiktokOrderIdFromShopifyName(string $name): string
    {
        $name = ltrim(trim($name), '#');
        if (preg_match('/^TT2?-(\d{12,20})$/i', $name, $m)) {
            return (string) $m[1];
        }
        if (preg_match('/^(?:tiktok2?)-(\d{12,20})$/i', $name, $m)) {
            return (string) $m[1];
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array{tracking: string, carrier: string}|null
     */
    public static function trackingFromTikTokOrderPayload(array $order): ?array
    {
        $packages = $order['packages'] ?? $order['package_list'] ?? [];
        if (! is_array($packages)) {
            $packages = [];
        }
        $lineItems = $order['line_items'] ?? $order['item_list'] ?? [];
        if (! is_array($lineItems)) {
            $lineItems = [];
        }
        $rows = array_merge($packages, $lineItems);
        $rows[] = $order;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $tn = $row['tracking_number'] ?? ($row['tracking_number_list'][0] ?? '');
            if (is_array($tn)) {
                $tn = $tn[0] ?? '';
            }
            $tn = strtoupper(preg_replace('/\s+/', '', (string) $tn) ?? '');
            if (strlen($tn) < 8 || preg_match('/^\d{3}-\d{7}-\d{7}$/', $tn)) {
                continue;
            }
            $carrier = trim((string) (
                $row['shipping_provider_name']
                ?? $row['shipping_provider']
                ?? $order['shipping_provider_name']
                ?? $order['shipping_provider']
                ?? ''
            ));

            return [
                'tracking' => $tn,
                'carrier' => $carrier !== '' ? $carrier : 'GOFO',
            ];
        }

        return null;
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
     * True when this tracking already belongs to a different marketplace order.
     *
     * @param  list<string>  $marketplaceOrderIds
     */
    protected function isStolenMarketplaceTracking(
        string $tracking,
        string $marketplace,
        array $marketplaceOrderIds,
        string $shopifyOrderId
    ): bool {
        $tracking = trim($tracking);
        $marketplace = strtolower(trim($marketplace));
        if ($tracking === '' || $marketplace === '') {
            return false;
        }

        $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
        $channelOrderId = '';
        foreach ($marketplaceOrderIds as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $slug = $matcher->slugFromOrderId($id);
            if ($slug === '' || $slug === $marketplace) {
                $channelOrderId = $id;
                break;
            }
        }
        if ($channelOrderId === '' && $marketplaceOrderIds !== []) {
            $channelOrderId = trim((string) $marketplaceOrderIds[0]);
        }

        return app(MarketplaceTrackingOwnership::class)->isWrongFor(
            $tracking,
            $marketplace,
            $channelOrderId,
            $shopifyOrderId
        );
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
     * @param  list<string>  $excludeTrackings
     * @return array{tracking: string, carrier: string}|null
     */
    protected function extractShipment(array $order, string $sku = '', array $excludeTrackings = []): ?array
    {
        $hit = VeeqoAllocationTracking::pick($order, $sku, $excludeTrackings);
        if ($hit === null) {
            $direct = $this->trackingNumberFrom($order);
            $directKey = $direct !== null ? VeeqoAllocationTracking::normalizeTracking($direct) : '';
            if ($direct !== null && ($excludeTrackings === [] || ! in_array($directKey, array_map(
                static fn ($tn) => VeeqoAllocationTracking::normalizeTracking((string) $tn),
                $excludeTrackings
            ), true))) {
                $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
                $skuMiss = $want !== '' && $this->payloadHasSkuFields($order) && ! $this->payloadContainsSku($order, $want);
                if (! $skuMiss) {
                    return ['tracking' => $direct, 'carrier' => $this->carrierFrom($order, [], $direct)];
                }
            }

            return null;
        }

        return [
            'tracking' => (string) $hit['tracking'],
            'carrier' => $this->carrierFrom(
                $hit['shipment'],
                $hit['bucket'],
                (string) $hit['tracking']
            ),
        ];
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
        string $sku = '',
        int $maxQuantity = 0
    ): array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return ['success' => false, 'message' => 'Shopify store credentials are missing for this marketplace.'];
        }

        try {
            $tracking = strtoupper(preg_replace('/\s+/', '', $tracking) ?? $tracking);
            $orderRes = $this->shopifyApi(
                    $storeUrl,
                    $token,
                    'GET',
                    "orders/{$shopifyOrderId}.json",
                    ['fields' => 'id,fulfillments,fulfillment_status,line_items']
                );

            $openQty = 0;
            if ($orderRes->successful()) {
                $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
                foreach ($orderRes->json('order.line_items') ?? [] as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $qty = (int) ($line['fulfillable_quantity'] ?? 0);
                    if ($qty < 1) {
                        continue;
                    }
                    if ($want !== '' && ! app(ShopifyFulfillmentTrackingMatcher::class)->skusEqual((string) ($line['sku'] ?? ''), $want)) {
                        continue;
                    }
                    $openQty += $qty;
                }
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
                        if ($n !== '' && $n === $tracking && $openQty < 1) {
                            return ['success' => true, 'already' => true, 'message' => 'Tracking already on Shopify.'];
                        }
                    }
                }
            }

            $maxQuantity = $maxQuantity > 0 ? $maxQuantity : (trim($sku) !== '' ? 1 : 0);
            $prepared = $this->prepareShopifyFulfillmentOrders($storeUrl, $token, $shopifyOrderId, false, $sku, $maxQuantity);
            if (($prepared['error'] ?? null) !== null) {
                return ['success' => false, 'message' => (string) $prepared['error']];
            }
            $lineItems = $prepared['line_items'] ?? [];
            if ($lineItems === []) {
                if (trim($sku) === '') {
                    $updated = $this->updateExistingShopifyFulfillmentTracking($storeUrl, $token, $shopifyOrderId, $tracking, $carrier);
                    if (! empty($updated['success'])) {
                        return $updated;
                    }
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
                $retried = $this->prepareShopifyFulfillmentOrders($storeUrl, $token, $shopifyOrderId, true, $sku, $maxQuantity);
                if (($retried['line_items'] ?? []) !== []) {
                    $payload['fulfillment']['line_items_by_fulfillment_order'] = $retried['line_items'];
                    $post = $this->shopifyApi($storeUrl, $token, 'POST', 'fulfillments.json', $payload);
                }
            }

            if (! $post->successful()) {
                if (trim($sku) === '') {
                    $updated = $this->updateExistingShopifyFulfillmentTracking($storeUrl, $token, $shopifyOrderId, $tracking, $carrier);
                    if (! empty($updated['success'])) {
                        return $updated;
                    }
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
        string $sku = '',
        int $maxQuantity = 0
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
            if (! in_array($status, ['open', 'in_progress', 'scheduled', 'incomplete', 'on_hold'], true)) {
                continue;
            }
            $actions = $this->shopifyFulfillmentActions($fo);
            $canCreate = $actions === [] || in_array('create_fulfillment', $actions, true);
            $canMove = in_array('move', $actions, true);
            $foId = (int) $fo['id'];
            $didCancelRequest = false;

            if (
                in_array('cancel_fulfillment_request', $actions, true)
                && (! $canCreate || self::fulfillmentOrderAssignedToService($fo))
            ) {
                $cancelled = $this->cancelShopifyFulfillmentRequest($storeUrl, $token, $foId);
                if (is_array($cancelled) && ! empty($cancelled['id'])) {
                    $fo = $cancelled;
                    $foId = (int) $fo['id'];
                    $actions = $this->shopifyFulfillmentActions($fo);
                    $canCreate = $actions === [] || in_array('create_fulfillment', $actions, true);
                    $canMove = in_array('move', $actions, true);
                    $status = strtolower((string) ($fo['status'] ?? $status));
                    $didCancelRequest = true;
                } elseif ($cancelled !== null) {
                    $didCancelRequest = true;
                    $canMove = true;
                }
            }

            $needsTakeover = $forceMove
                || self::fulfillmentOrderAssignedToService($fo)
                || (! $canCreate && ($canMove || $didCancelRequest));
            if ($needsTakeover) {
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
                        $status = strtolower((string) ($fo['status'] ?? $status));
                    }
                }
            }

            if ($status === 'on_hold' || in_array('release_hold', $actions, true)) {
                if ($this->releaseShopifyFulfillmentHold($storeUrl, $token, $foId)) {
                    $status = 'open';
                }
            }

            if ($status === 'on_hold' && ! $canCreate) {
                continue;
            }

            if (! $canCreate && $actions !== []) {
                continue;
            }

            $rawLines = $fo['line_items'] ?? null;
            $items = is_array($rawLines) ? $this->shopifyFulfillmentOrderLineItems($fo, $sku, $orderLines, $maxQuantity) : [];
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
    protected function shopifyFulfillmentOrderLineItems(array $fo, string $sku = '', array $orderLines = [], int $maxQuantity = 0): array
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
            if (array_key_exists('fulfillable_quantity', $li)) {
                $qty = (int) $li['fulfillable_quantity'];
            } else {
                $qty = (int) ($li['quantity'] ?? 0) - (int) ($li['fulfilled_quantity'] ?? 0);
            }
            if ($qty < 1) {
                continue;
            }
            $take = $maxQuantity > 0 ? min($qty, $maxQuantity) : $qty;
            $items[] = ['id' => (int) $li['id'], 'quantity' => $take];
            if ($maxQuantity > 0) {
                $maxQuantity -= $take;
                if ($maxQuantity < 1) {
                    return $items;
                }
            }
        }

        if ($items === [] && $want !== '') {
            $fallback = [];
            foreach ($fo['line_items'] ?? [] as $li) {
                if (! is_array($li) || empty($li['id'])) {
                    continue;
                }
                if (array_key_exists('fulfillable_quantity', $li)) {
                    $qty = (int) $li['fulfillable_quantity'];
                } else {
                    $qty = (int) ($li['quantity'] ?? 0) - (int) ($li['fulfilled_quantity'] ?? 0);
                }
                if ($qty < 1) {
                    continue;
                }
                if ($maxQuantity > 0) {
                    $qty = min($qty, $maxQuantity);
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
    /**
     * Pull a TikTok/Mirakl (or other 3PL) request back so Admin can fulfill it.
     *
     * @return array<string, mixed>|null  Reloaded FO, empty array if cancel succeeded without payload, null on failure
     */
    protected function cancelShopifyFulfillmentRequest(string $storeUrl, string $token, int $fulfillmentOrderId): ?array
    {
        if ($fulfillmentOrderId < 1) {
            return null;
        }

        try {
            $res = $this->shopifyApi(
                $storeUrl,
                $token,
                'POST',
                "fulfillment_orders/{$fulfillmentOrderId}/fulfillment_request/cancel.json"
            );
            if ($res->successful()) {
                $replacement = $res->json('replacement_fulfillment_order');
                if (is_array($replacement) && ! empty($replacement['id'])) {
                    return $replacement;
                }
                $fo = $res->json('fulfillment_order');

                return is_array($fo) && ! empty($fo['id']) ? $fo : [];
            }
            Log::info('VeeqoShopifyFulfillmentService: fulfillment request cancel failed', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'status' => $res->status(),
                'body' => mb_substr((string) $res->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::info('VeeqoShopifyFulfillmentService: fulfillment request cancel exception', [
                'fulfillment_order_id' => $fulfillmentOrderId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

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
        $tn = '';
        foreach (['tracking_number', 'tracking_reference', 'tracking', 'tracking_no', 'shipment_tracking'] as $field) {
            $tn = trim((string) ($model->{$field} ?? ''));
            if (strlen($tn) >= 8) {
                break;
            }
        }
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
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders/{$shopifyOrderId}.json");
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
     * Remaining unfulfilled units on the Shopify copy for this SKU (0 = all fulfilled).
     *
     * @param  array{store_url?: string, token?: string}  $config
     */
    protected function shopifyOpenFulfillableQty(array $config, string $shopifyOrderId, string $sku = ''): int
    {
        $order = $this->shopifyOrderPayload($config, $shopifyOrderId);
        if ($order === null) {
            return 0;
        }
        $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
        $total = 0;
        foreach ($order['line_items'] ?? [] as $line) {
            if (! is_array($line)) {
                continue;
            }
            $qty = (int) ($line['fulfillable_quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }
            if ($want !== '' && ! app(ShopifyFulfillmentTrackingMatcher::class)->skusEqual((string) ($line['sku'] ?? ''), $want)) {
                continue;
            }
            $total += $qty;
        }

        return $total;
    }

    /**
     * Attach every unused Veeqo/GOFO label onto the Shopify copies for these channel order ids.
     *
     * @param  list<string>  $orderRefs
     * @return list<array<string, mixed>>
     */
    public function fulfillShopifyCopiesByOrderRefs(array $orderRefs, string $marketplace = 'bestbuy'): array
    {
        $out = [];
        $marketplace = strtolower(trim($marketplace));
        foreach ($orderRefs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }
            if ($out !== []) {
                usleep(600000);
            }
            $rows = $this->marketplaceRowsByChannelRef($marketplace, $ref);
            if ($rows === []) {
                $direct = $this->fulfillShopifyCopyByMarketplaceRef($marketplace, $ref);
                $out[] = $direct;
                continue;
            }
            $seen = [];
            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);
                if ($id < 1 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $result = $this->fulfillMarketplaceOrder($marketplace, $id);
                $out[] = array_merge([
                    'ref' => $ref,
                    'row_id' => $id,
                    'sku' => (string) ($row->sku ?? ''),
                    'shopify_order_id' => (string) ($row->shopify_order_id ?? ''),
                ], $result);
            }
        }

        return $out;
    }

    /**
     * Find the Shopify copy by marketplace order id in tags/notes, then attach every unused label.
     *
     * @return array<string, mixed>
     */
    protected function fulfillShopifyCopyByMarketplaceRef(string $marketplace, string $ref): array
    {
        $config = $this->shopifyConfigFor($marketplace);
        $hit = $this->findShopifyOrderByMarketplaceRef($config, $ref);
        if ($hit === null) {
            return [
                'ref' => $ref,
                'success' => false,
                'message' => 'No '.$marketplace.' order row or Shopify copy found for '.$ref.'.',
            ];
        }
        $shopifyId = (string) ($hit['id'] ?? '');
        $order = $this->shopifyOrderPayload($config, $shopifyId);
        if ($order === null || ($order['line_items'] ?? []) === []) {
            usleep(400000);
            $order = $this->shopifyOrderPayload($config, $shopifyId);
        }
        if ($order === null) {
            return [
                'ref' => $ref,
                'success' => false,
                'shopify_order_id' => $shopifyId,
                'message' => 'Shopify order '.$shopifyId.' could not be loaded.',
            ];
        }
        $identity = $this->marketplaceIdentityFromShopifyOrder($order);
        $refs = $identity['refs'] !== [] ? $identity['refs'] : [$ref];
        $ids = $identity['ids'] !== [] ? $identity['ids'] : [$ref];
        if (! in_array($ref, $refs, true)) {
            $refs[] = $ref;
        }
        if (! in_array($ref, $ids, true)) {
            $ids[] = $ref;
        }
        $skus = $this->skusFromShopifyOrder($order);
        $skuPasses = $skus !== [] ? $skus : [''];
        $last = [
            'ref' => $ref,
            'success' => false,
            'shopify_order_id' => $shopifyId,
            'message' => 'No unused Veeqo label found.',
        ];
        $attached = [];
        foreach ($skuPasses as $sku) {
            $bundle = $this->fulfillShopifyFromLabelsAll(
                $shopifyId,
                $config,
                $refs,
                $this->localTrackingFromShopifyOrder($order),
                $sku,
                $ids,
                $marketplace
            );
            foreach ($bundle['results'] as $result) {
                $last = array_merge([
                    'ref' => $ref,
                    'sku' => $sku,
                    'shopify_order_id' => $shopifyId,
                ], $result);
                if ((string) ($result['action'] ?? '') === 'shopify_fulfilled') {
                    $tn = trim((string) ($result['tracking'] ?? ''));
                    if ($tn !== '') {
                        $attached[] = $tn;
                    }
                    $this->pushChannelTrackingForShopifyOrder($order, $shopifyId, $result);
                }
            }
        }

        if ($attached !== []) {
            $last['success'] = true;
            $last['action'] = 'shopify_fulfilled';
            $last['tracking'] = implode(', ', $attached);
            $last['message'] = 'Shopify order fulfilled with extra Veeqo tracking '.implode(', ', $attached).'.';
        }

        return $last;
    }

    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @return array{id: int|string, name?: string}|null
     */
    protected function findShopifyOrderByMarketplaceRef(array $config, string $ref): ?array
    {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $ref = trim($ref);
        if ($storeUrl === '' || $token === '' || $ref === '') {
            return null;
        }

        $queries = array_values(array_unique([
            $ref,
            '"'.$ref.'"',
        ]));
        foreach ($queries as $q) {
            try {
                $gql = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/graphql.json", [
                    'query' => 'query ($q: String!) { orders(first: 8, query: $q) { edges { node { id name tags displayFulfillmentStatus } } } }',
                    'variables' => ['q' => $q],
                ]);
                if (! $gql->successful()) {
                    continue;
                }
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
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return list<object>
     */
    protected function marketplaceRowsByChannelRef(string $marketplace, string $ref): array
    {
        if ($marketplace === 'bestbuy') {
            return BestBuyOrderMetric::withoutGlobalScopes()
                ->where(function ($query) use ($ref): void {
                    $query->where('channel_order_id', $ref)
                        ->orWhere('order_id', $ref)
                        ->orWhere('order_line_id', $ref)
                        ->orWhere('order_line_id', 'like', $ref.'%');
                })
                ->where(function ($query): void {
                    $query->where('channel_name', 'like', '%Best Buy%')
                        ->orWhere('channel_name', 'Best Buy USA');
                })
                ->get()
                ->all();
        }

        $one = $this->findMarketplaceOrderByChannelIds($marketplace, [$ref]);

        return $one !== null ? [$one] : [];
    }

    /**
     * Every tracking already on Shopify for this SKU (multi-label orders).
     *
     * @param  array{store_url?: string, token?: string}  $config
     * @param  list<string>  $marketplaceOrderIds
     * @return list<string>
     */
    protected function existingShopifyTrackings(
        array $config,
        string $shopifyOrderId,
        string $sku = '',
        array $marketplaceOrderIds = [],
        bool $requireOrderAndSku = false
    ): array {
        $first = $this->existingShopifyTracking($config, $shopifyOrderId, $sku, $marketplaceOrderIds, $requireOrderAndSku);
        $out = [];
        if (is_array($first) && trim((string) ($first['tracking'] ?? '')) !== '') {
            $out[] = (string) $first['tracking'];
        }

        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        $want = app(ShopifyFulfillmentTrackingMatcher::class)->normalizeSku($sku);
        if ($storeUrl === '' || $token === '' || $want === '') {
            return $out;
        }

        try {
            $response = Http::withoutVerifying()->withHeaders([
                'X-Shopify-Access-Token' => $token,
            ])->timeout(30)->get("https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders/{$shopifyOrderId}.json", [
                'fields' => 'id,line_items,fulfillments,tags,note,name',
            ]);
            if (! $response->successful()) {
                return $out;
            }
            $order = $response->json('order');
            if (! is_array($order)) {
                return $out;
            }
            $orderLines = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];
            $matcher = app(ShopifyFulfillmentTrackingMatcher::class);
            foreach ($order['fulfillments'] ?? [] as $fulfillment) {
                if (! is_array($fulfillment)) {
                    continue;
                }
                $status = strtolower((string) ($fulfillment['status'] ?? ''));
                if (in_array($status, ['cancelled', 'error', 'failure'], true)) {
                    continue;
                }
                if (! $matcher->fulfillmentMatchesSku($fulfillment, $want, $orderLines)) {
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
                    $n = VeeqoAllocationTracking::normalizeTracking((string) $n);
                    if ($n !== '' && ! in_array($n, $out, true)) {
                        $out[] = $n;
                    }
                }
            }
        } catch (\Throwable $e) {
            return $out;
        }

        return $out;
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

        $url = "https://{$storeUrl}/admin/api/".self::SHOPIFY_API_VERSION."/orders/{$shopifyOrderId}.json";
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $response = Http::withoutVerifying()->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->timeout(30)->get($url);
                if ($response->successful()) {
                    $order = $response->json('order');

                    return is_array($order) ? $order : null;
                }
                if ($response->status() === 429) {
                    usleep(800000 * ($attempt + 1));
                    continue;
                }
            } catch (\Throwable) {
                // retry
            }
            usleep(350000 * ($attempt + 1));
        }

        return null;
    }

    /**
     * @return list<int>
     */
    protected function pendingLinkedOrderIds(string $marketplace, int $limit): array
    {
        $since = now()->subDays(180);
        $limit = max(1, min(200, $limit));

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
                ->orderByRaw("CASE WHEN UPPER(TRIM(COALESCE(status, ''))) IN ('SHIPPED','PARTIALLYSHIPPED') THEN 0 ELSE 1 END")
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
            'b5cb2b' => [B5cB2bOrder::class, 'ordered_at'],
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
                $statusCol = null;
                foreach (['order_status', 'status', 'fulfillment_status', 'shipping_status', 'package_status'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $statusCol = $col;
                        break;
                    }
                }
                $select = ['id', $uniqueCol];
                if ($skuCol !== null) {
                    $select[] = $skuCol;
                }
                if ($statusCol !== null) {
                    $select[] = $statusCol;
                    // Prefer channel-shipped / labeled rows so Shopify copies get fulfilled first.
                    $query->orderByRaw(
                        "CASE WHEN UPPER(TRIM(COALESCE(`{$statusCol}`, ''))) REGEXP 'SHIP|TRANSIT|DELIVER|COMPLETE|FULFILL|DISPATCH|PACKAGE|RTS|COLLECT' THEN 0 ELSE 1 END"
                    );
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
        // Tracking found but Shopify write failed — retry soon (SOF already has the number).
        if ($action === 'shopify_fulfill_failed' && strlen(trim((string) ($result['tracking'] ?? ''))) >= 8) {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'miss'), 1, now()->addMinutes(2));

            return;
        }
        if (in_array($action, ['tracking_not_found', 'not_linked', 'unsupported'], true)
            || (! empty($result['skipped']) && $action !== 'shopify_fulfilled')) {
            Cache::put($this->autoFetchCacheKey($marketplace, $orderId, 'miss'), 1, now()->addMinutes(8));
        }
    }

    protected function autoFetchCacheKey(string $marketplace, int $orderId, string $kind): string
    {
        return 'mm_fetch_tracking_v6_'.$kind.':'.$marketplace.':'.$orderId;
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
                'b5cb2b' => B5cB2bOrder::class,
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
            } elseif (Schema::hasColumn($model->getTable(), 'tracking_reference')) {
                $model->tracking_reference = $tn;
            }
            foreach (['raw_payload', 'raw_json', 'raw_data'] as $field) {
                if (! Schema::hasColumn($model->getTable(), $field)) {
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
