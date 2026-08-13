<?php

namespace App\Services;

use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay1 Sale Event sync via Sell Marketing API (item_price_markdown).
 *
 * Push Prc step 2 — PRMT % column:
 * - PRMT% = 10 → Add SKU/listing to the existing 10% sale event (Seller Hub "Add items")
 * - PRMT% changes (e.g. 10 → 8) → Remove from the old sale, add to the matching % sale
 * - PRMT% = 0 → Remove from all sale events
 * - Never create a second sale at the same %; reuse the seller's campaign
 * - If no sale exists at that %, create PEF SALE {n}%
 */
class Ebay1PromotionService
{
    private const MARKETPLACE = 'EBAY_US';

    private const DURATION_DAYS = 30;

    private const DV_PROMO_ID = 'PEF_PRMT_PROMOTION_ID';

    private const DV_PRMT_PCT = 'PEF_PRMT_PCT';

    public function __construct(
        private readonly EbayApiService $ebay
    ) {}

    /**
     * Sync PRMT % to an eBay1 markdown sale event for a SKU.
     *
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool,percent?:float}
     */
    public function syncSkuPromotionPercent(string $sku, float $percent): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return ['success' => false, 'message' => 'SKU required', 'promotion_id' => null];
        }

        $metric = EbayMetric::query()->where('sku', $sku)->first()
            ?: EbayMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        $itemId = $metric?->item_id ? trim((string) $metric->item_id) : '';
        if ($itemId === '') {
            return ['success' => false, 'message' => 'eBay1 item_id not found for SKU', 'promotion_id' => null];
        }

        $dv = EbayDataView::query()->where('sku', $sku)->first()
            ?: EbayDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: new EbayDataView(['sku' => $sku]);
        $val = is_array($dv->value) ? $dv->value : [];
        $storedPromoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';
        $apiSku = trim((string) ($metric->sku ?: $sku));

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'eBay1 token: '.$e->getMessage(), 'promotion_id' => $storedPromoId ?: null];
        }

        if ($percent <= 0) {
            return $this->removeSkuFromSales($token, $apiSku, $itemId, $dv, $val, $storedPromoId);
        }

        $pctInt = (int) round($percent);
        if ($pctInt < 5 || $pctInt > 80) {
            return [
                'success' => false,
                'message' => 'eBay sale % must be 5–80 (or 0 to remove). Got '.$pctInt,
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        // Leave other %-tier sales first (keep current PRMT%)
        $pre = $this->removeSkuFromSales(
            $token,
            $apiSku,
            $itemId,
            $dv,
            $val,
            $storedPromoId,
            keepPercent: $pctInt,
            persist: false
        );
        if (empty($pre['success'])) {
            return [
                'success' => false,
                'message' => $pre['message'] ?? 'Failed removing SKU from other sale tier(s)',
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        $existing = $this->findSaleForPercent($token, $pctInt, $itemId, $apiSku);
        if ($existing !== null) {
            $promoId = (string) $existing['promotion_id'];
            if (! empty($existing['already_has_listing'])) {
                $this->persistDv($dv, $val, $promoId, $pctInt);

                return [
                    'success' => true,
                    'message' => 'SKU already on '.$pctInt.'% sale event',
                    'promotion_id' => $promoId,
                    'percent' => (float) $pctInt,
                ];
            }

            $added = $this->addSkuToSale($token, $promoId, $apiSku, $itemId);
            if (! $added['success']) {
                return [
                    'success' => false,
                    'message' => $added['message'] ?? 'Add item to sale failed',
                    'promotion_id' => $promoId,
                ];
            }
            $this->resumeIfNeeded($token, $promoId);
            $this->persistDv($dv, $val, $promoId, $pctInt);

            return [
                'success' => true,
                'message' => 'Added SKU to '.$pctInt.'% sale event',
                'promotion_id' => $promoId,
                'percent' => (float) $pctInt,
            ];
        }

        $imageUrl = $this->resolvePromotionImageUrl($itemId);
        if ($imageUrl === '') {
            return [
                'success' => false,
                'message' => 'No '.$pctInt.'% sale event found, and listing image is required to create one',
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        $created = $this->createMarkdown($token, $itemId, $apiSku, $pctInt, $imageUrl);
        if (! $created['success']) {
            return $created;
        }
        $newId = (string) ($created['promotion_id'] ?? '');
        $this->persistDv($dv, $val, $newId, $pctInt);

        return [
            'success' => true,
            'message' => 'Created '.$pctInt.'% sale event and added SKU',
            'promotion_id' => $newId,
            'percent' => (float) $pctInt,
        ];
    }

    public function campaignNameForPercent(int $percent): string
    {
        return 'PEF SALE '.$percent.'%';
    }

    /**
     * @param  array<string, mixed>  $val
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool}
     */
    private function removeSkuFromSales(
        string $token,
        string $sku,
        string $itemId,
        EbayDataView $dv,
        array $val,
        string $storedPromoId,
        ?int $keepPercent = null,
        bool $persist = true
    ): array {
        $candidates = $this->listMarkdownIds($token);
        if ($storedPromoId !== '' && ! in_array($storedPromoId, $candidates, true)) {
            array_unshift($candidates, $storedPromoId);
        }

        $removedFrom = [];
        $errors = [];
        $foundOn = 0;

        foreach ($candidates as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED', 'DRAFT'], true)) {
                continue;
            }
            if (! $this->saleContainsListing($detail, $itemId, $sku)) {
                continue;
            }
            $pct = $this->salePercent($detail);
            if ($keepPercent !== null && $pct === $keepPercent) {
                continue;
            }

            $foundOn++;
            $rm = $this->removeListingFromSale($token, $promoId, $itemId, $sku, $detail);
            if ($rm['success']) {
                $removedFrom[] = $promoId;
            } else {
                $errors[] = $rm['message'];
            }
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'message' => implode(' | ', array_values(array_unique($errors))),
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        if ($persist) {
            $this->persistDv($dv, $val, '', 0);
        }

        if (! $persist) {
            return ['success' => true, 'message' => 'pre-remove done', 'promotion_id' => null];
        }

        return [
            'success' => true,
            'message' => $foundOn === 0
                ? 'SKU not on any active eBay1 sale event'
                : 'SKU removed from eBay1 sale event'.($foundOn > 1 ? 's' : ''),
            'promotion_id' => $removedFrom[0] ?? null,
            'paused' => true,
        ];
    }

    /**
     * Pick the seller's sale event at this PRMT%. Prefer the RUNNING campaign
     * with the most items (the Hub sale), not a tiny PEF-created duplicate.
     *
     * @return array{promotion_id:string,already_has_listing:bool}|null
     */
    private function findSaleForPercent(string $token, int $pctInt, string $itemId, string $sku): ?array
    {
        $ids = $this->listMarkdownIds($token);
        $best = null;
        $bestScore = -1;
        $withItem = null;

        foreach ($ids as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED'], true)) {
                continue;
            }
            if ($this->salePercent($detail) !== $pctInt) {
                continue;
            }

            $has = $this->saleContainsListing($detail, $itemId, $sku);
            $count = count($this->saleListingIds($detail)) + count($this->saleInventoryItems($detail));
            $score = ($status === 'RUNNING' ? 100000 : ($status === 'SCHEDULED' ? 10000 : 0)) + $count;
            $entry = [
                'promotion_id' => $promoId,
                'already_has_listing' => $has,
            ];

            if ($has) {
                $withItem = $entry;
                break;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        return $withItem ?? $best;
    }

    /**
     * @return list<string>
     */
    private function listMarkdownIds(string $token): array
    {
        $ids = [];
        $offset = 0;
        $limit = 50;

        for ($page = 0; $page < 20; $page++) {
            $resp = $this->http($token)->get('https://api.ebay.com/sell/marketing/v1/promotion', [
                'marketplace_id' => self::MARKETPLACE,
                'promotion_type' => 'MARKDOWN_SALE',
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! $resp->successful()) {
                Log::warning('eBay1 markdown sale list failed', [
                    'status' => $resp->status(),
                    'body' => mb_substr($resp->body(), 0, 400),
                ]);
                break;
            }
            $json = $resp->json();
            $promos = is_array($json) ? ($json['promotions'] ?? []) : [];
            if (! is_array($promos) || $promos === []) {
                break;
            }
            foreach ($promos as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $status = (string) ($p['promotionStatus'] ?? '');
                if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED', 'DRAFT'], true)) {
                    continue;
                }
                $id = trim((string) ($p['promotionId'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
            $total = (int) ($json['total'] ?? count($ids));
            $offset += $limit;
            if ($offset >= $total) {
                break;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMarkdown(string $token, string $promoId): ?array
    {
        $resp = $this->http($token)
            ->get('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId));
        if (! $resp->successful()) {
            return null;
        }
        $json = $resp->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @return array{success:bool,message:string,promotion_id:?string}
     */
    private function createMarkdown(string $token, string $itemId, string $sku, int $pctInt, string $imageUrl): array
    {
        $payload = $this->markdownPayload($pctInt, $imageUrl, [$itemId], null, null, null);
        $resp = $this->http($token)
            ->post('https://api.ebay.com/sell/marketing/v1/item_price_markdown', $payload);

        if (! $resp->successful() && $resp->status() !== 201) {
            Log::error('eBay1 sale event create failed', [
                'sku' => $sku,
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 800),
            ]);

            return [
                'success' => false,
                'message' => 'Create failed: '.$this->ebayErrorMessage($resp),
                'promotion_id' => null,
            ];
        }

        $promoId = $this->extractPromotionId($resp);
        if ($promoId === '') {
            return [
                'success' => false,
                'message' => 'Create ok but sale id missing from response',
                'promotion_id' => null,
            ];
        }

        $this->resumeIfNeeded($token, $promoId);

        return [
            'success' => true,
            'message' => 'created',
            'promotion_id' => $promoId,
        ];
    }

    /**
     * Seller Hub "Add items" — keep the existing sale's name/% and append this SKU/listing.
     *
     * @return array{success:bool,message:string}
     */
    private function addSkuToSale(string $token, string $promoId, string $sku, string $itemId): array
    {
        $detail = $this->getMarkdown($token, $promoId);
        if ($detail === null) {
            return ['success' => false, 'message' => 'Sale event not found: '.$promoId];
        }
        if ($this->saleContainsListing($detail, $itemId, $sku)) {
            return ['success' => true, 'message' => 'already present'];
        }

        $pct = $this->salePercent($detail);
        if ($pct === null) {
            return ['success' => false, 'message' => 'Sale event has no discount %'];
        }

        $usesItems = $this->saleInventoryItems($detail) !== [];
        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);

        if ($usesItems) {
            $items[] = $this->inventoryItem($sku);
            $payload = $this->saleWritePayload($detail, [], $items);
        } else {
            if ($itemId !== '') {
                $listingIds[] = $itemId;
            }
            $listingIds = array_values(array_unique(array_filter(array_map('strval', $listingIds))));
            $payload = $this->saleWritePayload($detail, $listingIds, null);
        }

        $resp = $this->http($token)
            ->put('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId), $payload);

        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'add item ok'];
        }

        // Retry the other inventory shape if Hub stored the opposite type
        if ($usesItems && $itemId !== '') {
            $listingIds = $this->saleListingIds($detail);
            $listingIds[] = $itemId;
            $payload = $this->saleWritePayload($detail, array_values(array_unique($listingIds)), null);
            $resp = $this->http($token)
                ->put('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId), $payload);
            if ($resp->successful() || $resp->status() === 204) {
                return ['success' => true, 'message' => 'add item ok'];
            }
        } elseif (! $usesItems && $sku !== '') {
            $items = $this->saleInventoryItems($detail);
            $items[] = $this->inventoryItem($sku);
            $payload = $this->saleWritePayload($detail, [], $items);
            $resp = $this->http($token)
                ->put('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId), $payload);
            if ($resp->successful() || $resp->status() === 204) {
                return ['success' => true, 'message' => 'add item ok'];
            }
        }

        Log::error('eBay1 sale event add item failed', [
            'promotion_id' => $promoId,
            'sku' => $sku,
            'item_id' => $itemId,
            'status' => $resp->status(),
            'body' => mb_substr($resp->body(), 0, 500),
        ]);

        return ['success' => false, 'message' => 'Add item failed: '.$this->ebayErrorMessage($resp)];
    }

    /**
     * Seller Hub "Remove items" — drop this SKU/listing, keep the sale event.
     *
     * @param  array<string, mixed>  $detail
     * @return array{success:bool,message:string}
     */
    private function removeListingFromSale(
        string $token,
        string $promoId,
        string $itemId,
        string $sku,
        array $detail
    ): array {
        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);
        $usesItems = $items !== [];

        if ($usesItems) {
            $want = strtoupper(trim($sku));
            $keptItems = [];
            foreach ($items as $it) {
                $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
                if ($id !== '' && $want !== '' && $id === $want) {
                    continue;
                }
                $keptItems[] = $it;
            }
            $isEmpty = $keptItems === [];
            $writeIds = [];
            $writeItems = $keptItems;
        } else {
            $kept = [];
            foreach ($listingIds as $lid) {
                $lid = trim((string) $lid);
                if ($lid === '' || ($itemId !== '' && $lid === $itemId)) {
                    continue;
                }
                $kept[] = $lid;
            }
            $kept = array_values(array_unique($kept));
            $isEmpty = $kept === [];
            $writeIds = $kept;
            $writeItems = null;
        }

        $status = (string) ($detail['promotionStatus'] ?? '');
        if ($status === 'RUNNING') {
            $this->pauseSale($token, $promoId);
        }

        if ($isEmpty) {
            $paused = $this->pauseSale($token, $promoId);
            if ($paused) {
                return ['success' => true, 'message' => 'SKU removed; sale kept (no items left)'];
            }

            return ['success' => false, 'message' => 'Pause empty sale failed'];
        }

        $payload = $this->saleWritePayload($detail, $writeIds, $writeItems);
        $resp = $this->http($token)
            ->put('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId), $payload);

        if ($resp->successful() || $resp->status() === 204) {
            if ($status === 'RUNNING') {
                $this->resumeIfNeeded($token, $promoId);
            }

            return ['success' => true, 'message' => 'remove item ok'];
        }

        return ['success' => false, 'message' => 'Remove item failed: '.$this->ebayErrorMessage($resp)];
    }

    /**
     * Update payload that preserves the seller's sale name, dates, and discount.
     *
     * @param  array<string, mixed>  $detail
     * @param  list<string>  $listingIds
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @return array<string, mixed>
     */
    private function saleWritePayload(array $detail, array $listingIds, ?array $inventoryItems): array
    {
        $pct = $this->salePercent($detail) ?? 10;
        $desc = trim((string) ($detail['description'] ?? ''));
        if ($desc === '') {
            $desc = (string) ($detail['name'] ?? $this->campaignNameForPercent($pct));
        }

        $payload = [
            'name' => (string) ($detail['name'] ?? $this->campaignNameForPercent($pct)),
            'description' => $this->clipDescription($desc),
            'marketplaceId' => (string) ($detail['marketplaceId'] ?? self::MARKETPLACE),
            'startDate' => (string) ($detail['startDate'] ?? now('UTC')->subMinute()->format('Y-m-d\TH:i:s.000\Z')),
            'endDate' => (string) ($detail['endDate'] ?? now('UTC')->addDays(self::DURATION_DAYS)->format('Y-m-d\TH:i:s.000\Z')),
            'promotionStatus' => 'SCHEDULED',
        ];

        foreach (['promotionImageUrl', 'applyFreeShipping', 'autoSelectFutureInventory', 'blockPriceIncreaseInItemRevision', 'priority'] as $key) {
            if (array_key_exists($key, $detail) && $detail[$key] !== null && $detail[$key] !== '') {
                $payload[$key] = $detail[$key];
            }
        }

        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        $first = is_array($discounts[0] ?? null) ? $discounts[0] : [];
        $benefit = is_array($first['discountBenefit'] ?? null)
            ? $first['discountBenefit']
            : ['percentageOffItem' => (string) $pct];

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        if ($inventoryItems !== null) {
            $criterion['inventoryItems'] = array_values($inventoryItems);
        } else {
            $criterion['listingIds'] = array_values($listingIds);
        }

        $payload['selectedInventoryDiscounts'] = [
            [
                'discountBenefit' => $benefit,
                'inventoryCriterion' => $criterion,
            ],
        ];

        return $payload;
    }

    /**
     * @return array{inventoryReferenceId:string,inventoryReferenceType:string}
     */
    private function inventoryItem(string $sku): array
    {
        return [
            'inventoryReferenceId' => $sku,
            'inventoryReferenceType' => 'INVENTORY_ITEM',
        ];
    }

    /**
     * @param  list<string>  $listingIds
     * @param  array<string, mixed>|null  $existingDetail
     * @return array<string, mixed>
     */
    private function markdownPayload(
        int $pctInt,
        string $imageUrl,
        array $listingIds,
        ?string $startDate,
        ?string $endDate,
        ?array $existingDetail
    ): array {
        $start = ($startDate !== null && $startDate !== '')
            ? $startDate
            : now('UTC')->subMinute()->format('Y-m-d\TH:i:s.000\Z');
        $end = ($endDate !== null && $endDate !== '')
            ? $endDate
            : now('UTC')->addDays(self::DURATION_DAYS)->format('Y-m-d\TH:i:s.000\Z');

        $payload = [
            'name' => $this->campaignNameForPercent($pctInt),
            'description' => $this->clipDescription('PEF SALE '.$pctInt.'%'),
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionImageUrl' => $imageUrl,
            'blockPriceIncreaseInItemRevision' => true,
            'selectedInventoryDiscounts' => [
                [
                    'discountBenefit' => [
                        'percentageOffItem' => (string) $pctInt,
                    ],
                    'inventoryCriterion' => [
                        'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
                        'listingIds' => array_values($listingIds),
                    ],
                ],
            ],
        ];

        if (is_array($existingDetail) && ($existingDetail['promotionStatus'] ?? '') === 'RUNNING') {
            $payload['promotionStatus'] = 'SCHEDULED';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function salePercent(array $detail): ?int
    {
        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        $pct = null;
        if (is_array($discounts) && $discounts !== []) {
            $pct = $discounts[0]['discountBenefit']['percentageOffItem']
                ?? $discounts[0]['discountBenefit']['percentage_off_item']
                ?? $discounts[0]['discountBenefit']['percentageOffOrder']
                ?? null;
        }
        if (! is_numeric($pct)) {
            $hay = (string) ($detail['name'] ?? '').' '.(string) ($detail['description'] ?? '');
            if (preg_match('/(\d+)\s*%/', $hay, $m)) {
                $pct = (int) $m[1];
            }
        }
        if (! is_numeric($pct)) {
            return null;
        }

        return (int) round((float) $pct);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<string>
     */
    private function saleListingIds(array $detail): array
    {
        $ids = [];
        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        if (! is_array($discounts)) {
            return [];
        }
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $crit = is_array($row['inventoryCriterion'] ?? null) ? $row['inventoryCriterion'] : [];
            foreach (($crit['listingIds'] ?? []) as $lid) {
                $lid = trim((string) $lid);
                if ($lid !== '') {
                    $ids[] = $lid;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return list<array<string, mixed>>
     */
    private function saleInventoryItems(array $detail): array
    {
        $items = [];
        $seen = [];
        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        if (! is_array($discounts)) {
            return [];
        }
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $crit = is_array($row['inventoryCriterion'] ?? null) ? $row['inventoryCriterion'] : [];
            foreach (($crit['inventoryItems'] ?? []) as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $id = trim((string) ($it['inventoryReferenceId'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $key = strtoupper($id);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = [
                    'inventoryReferenceId' => $id,
                    'inventoryReferenceType' => (string) ($it['inventoryReferenceType'] ?? 'INVENTORY_ITEM'),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function saleContainsListing(array $detail, string $itemId, string $sku): bool
    {
        if ($itemId !== '' && in_array($itemId, $this->saleListingIds($detail), true)) {
            return true;
        }
        if ($sku === '') {
            return false;
        }
        $want = strtoupper(trim($sku));
        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        if (! is_array($discounts)) {
            return false;
        }
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $crit = is_array($row['inventoryCriterion'] ?? null) ? $row['inventoryCriterion'] : [];
            foreach (($crit['inventoryItems'] ?? []) as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
                if ($id !== '' && $id === $want) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pauseSale(string $token, string $promoId): bool
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId).'/pause';
        $resp = $this->http($token)->post($url);

        return $resp->successful() || in_array($resp->status(), [204, 400, 409], true);
    }

    private function resumeIfNeeded(string $token, string $promoId): void
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId).'/resume';
        try {
            $this->http($token)->timeout(30)->post($url);
        } catch (\Throwable $e) {
            // already RUNNING / SCHEDULED
        }
    }

    private function resolvePromotionImageUrl(string $itemId): string
    {
        try {
            $details = $this->ebay->getItem($itemId);
            $item = is_array($details) ? ($details['Item'] ?? null) : null;
            if (! is_array($item)) {
                return '';
            }
            $pic = $item['PictureDetails']['PictureURL']
                ?? $item['PictureDetails']['GalleryURL']
                ?? null;
            if (is_array($pic)) {
                $pic = $pic[0] ?? reset($pic);
            }
            $url = is_string($pic) ? trim($pic) : '';

            return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        } catch (\Throwable $e) {
            Log::warning('eBay1 sale event image resolve failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @param  array<string, mixed>  $val
     */
    private function persistDv(EbayDataView $dv, array $val, string $promoId, int|float $pct): void
    {
        if ($promoId !== '') {
            $val[self::DV_PROMO_ID] = $promoId;
        } else {
            unset($val[self::DV_PROMO_ID]);
        }
        $val[self::DV_PRMT_PCT] = (float) $pct;
        if (! $dv->exists) {
            $dv->sku = $dv->sku ?: null;
        }
        $dv->value = $val;
        $dv->save();
    }

    private function extractPromotionId(Response $resp): string
    {
        $loc = (string) ($resp->header('Location') ?? $resp->header('location') ?? '');
        if ($loc !== '') {
            if (preg_match('#(?:item_price_markdown|item_promotion|promotion)/([^/?]+)#', $loc, $m)) {
                return urldecode($m[1]);
            }
            $parts = explode('/', rtrim($loc, '/'));
            $last = (string) end($parts);
            if ($last !== '') {
                return urldecode($last);
            }
        }
        $json = $resp->json();
        if (is_array($json)) {
            foreach (['promotionId', 'promotion_id', 'id'] as $k) {
                if (! empty($json[$k])) {
                    return (string) $json[$k];
                }
            }
        }

        return '';
    }

    private function clipDescription(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= 50) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 50));
    }

    private function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withoutVerifying()
            ->withToken($token)
            ->asJson()
            ->acceptJson()
            ->withHeaders([
                'Content-Language' => 'en-US',
            ])
            ->timeout(60);
    }

    private function ebayErrorMessage(Response $resp): string
    {
        $json = $resp->json();
        if (is_array($json)) {
            if (! empty($json['errors'][0]['message'])) {
                return (string) $json['errors'][0]['message'];
            }
            if (! empty($json['message'])) {
                return (string) $json['message'];
            }
        }
        $body = trim((string) $resp->body());

        return $body !== '' ? mb_substr($body, 0, 240) : ('HTTP '.$resp->status());
    }
}
