<?php

namespace App\Services;

use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay1 coupon / markdown sync via Sell Marketing API (item_price_markdown).
 *
 * PEF CPN % never creates coupons. It only:
 * - percent > 0 → add SKU to an existing markdown coupon at that %
 * - percent = 0 → remove SKU from markdown coupon(s) it belongs to
 *
 * Note: eBay allows shrinking inventory on SCHEDULED markdowns; RUNNING markdowns
 * only accept additive inventoryItems updates (remove must be done while SCHEDULED
 * or manually in Seller Hub).
 */
class Ebay1CouponService
{
    private const MARKETPLACE = 'EBAY_US';

    private const DV_PROMO_ID = 'PEF_COUPON_PROMOTION_ID';

    private const DV_COUPON_PCT = 'PEF_COUPON_PCT';

    public function __construct(
        private readonly EbayApiService $ebay
    ) {}

    /**
     * Sync CPN % to eBay1 coupon membership for a SKU.
     *
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool,percent?:float}
     */
    public function syncSkuCouponPercent(string $sku, float $percent): array
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
            return $this->removeSkuFromCoupons($token, $apiSku, $itemId, $dv, $val, $storedPromoId);
        }

        $pctInt = (int) round($percent);
        if ($pctInt < 5 || $pctInt > 80) {
            return [
                'success' => false,
                'message' => 'eBay coupon % must be 5–80 (or 0 to remove). Got '.$pctInt,
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        // Leave any other %-tier coupons first, then add to the matching tier.
        $pre = $this->removeSkuFromCoupons($token, $apiSku, $itemId, $dv, $val, $storedPromoId, keepPercent: $pctInt, persist: false);
        if (empty($pre['success'])) {
            return [
                'success' => false,
                'message' => $pre['message'] ?? 'Failed removing SKU from other coupon tier(s)',
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        $target = $this->findCouponForPercent($token, $pctInt, $apiSku, $itemId, $storedPromoId);
        if ($target === null) {
            return [
                'success' => false,
                'message' => 'No existing eBay1 coupon at '.$pctInt.'% — create/maintain the coupon in Seller Hub, then retry to add this SKU',
                'promotion_id' => null,
            ];
        }

        $promoId = (string) $target['promotion_id'];
        if (! empty($target['already_has_sku'])) {
            $this->persistDv($dv, $val, $promoId, $pctInt);

            return [
                'success' => true,
                'message' => 'SKU already on eBay1 '.$pctInt.'% coupon',
                'promotion_id' => $promoId,
                'percent' => (float) $pctInt,
            ];
        }

        $added = $this->addSkuToCoupon($token, $promoId, $apiSku, $itemId);
        if (! $added['success']) {
            return [
                'success' => false,
                'message' => $added['message'],
                'promotion_id' => $promoId,
            ];
        }

        $this->persistDv($dv, $val, $promoId, $pctInt);

        return [
            'success' => true,
            'message' => 'SKU added to eBay1 '.$pctInt.'% coupon',
            'promotion_id' => $promoId,
            'percent' => (float) $pctInt,
        ];
    }

    /**
     * Remove SKU from markdown coupons (all, or all except keepPercent).
     *
     * @param  array<string, mixed>  $val
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool}
     */
    private function removeSkuFromCoupons(
        string $token,
        string $sku,
        string $itemId,
        EbayDataView $dv,
        array $val,
        string $storedPromoId,
        ?int $keepPercent = null,
        bool $persist = true
    ): array {
        $candidates = $this->listActiveMarkdownIds($token);
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
            if (! $this->couponContainsSku($detail, $sku, $itemId)) {
                continue;
            }
            $pct = $this->couponPercent($detail);
            if ($keepPercent !== null && $pct === $keepPercent) {
                continue;
            }

            $foundOn++;
            $rm = $this->removeSkuFromCoupon($token, $promoId, $sku, $itemId, $detail);
            if ($rm['success']) {
                $removedFrom[] = $promoId;
            } else {
                $errors[] = $rm['message'];
            }
        }

        if ($errors !== []) {
            $uniq = array_values(array_unique($errors));

            return [
                'success' => false,
                'message' => implode(' | ', $uniq),
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        if ($persist) {
            $this->persistDv($dv, $val, '', 0);
        }

        if (! $persist) {
            return [
                'success' => true,
                'message' => 'pre-remove done',
                'promotion_id' => null,
            ];
        }

        return [
            'success' => true,
            'message' => $foundOn === 0
                ? 'SKU not on any active eBay1 coupon'
                : 'SKU removed from eBay1 coupon'.($foundOn > 1 ? 's' : ''),
            'promotion_id' => $removedFrom[0] ?? null,
            'paused' => true,
        ];
    }

    /**
     * Prefer a RUNNING coupon (live discount), then SCHEDULED/PAUSED at the same %.
     *
     * @return array{promotion_id:string,already_has_sku:bool}|null
     */
    private function findCouponForPercent(string $token, int $pctInt, string $sku, string $itemId, string $preferredId): ?array
    {
        $ids = $this->listActiveMarkdownIds($token);
        if ($preferredId !== '' && ! in_array($preferredId, $ids, true)) {
            array_unshift($ids, $preferredId);
        }

        $matchRunning = null;
        $matchOther = null;
        $matchWithSku = null;

        foreach ($ids as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED'], true)) {
                continue;
            }
            if ($this->couponPercent($detail) !== $pctInt) {
                continue;
            }

            $has = $this->couponContainsSku($detail, $sku, $itemId);
            $entry = ['promotion_id' => $promoId, 'already_has_sku' => $has];
            if ($has) {
                $matchWithSku = $entry;
                break;
            }
            if ($status === 'RUNNING') {
                $matchRunning ??= $entry;
            } else {
                $matchOther ??= $entry;
            }
        }

        return $matchWithSku ?? $matchRunning ?? $matchOther;
    }

    /**
     * @return list<string>
     */
    private function listActiveMarkdownIds(string $token): array
    {
        $ids = [];
        $offset = 0;
        $limit = 50;

        for ($page = 0; $page < 10; $page++) {
            $resp = $this->http($token)->get('https://api.ebay.com/sell/marketing/v1/promotion', [
                'marketplace_id' => self::MARKETPLACE,
                'promotion_type' => 'MARKDOWN_SALE',
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! $resp->successful()) {
                Log::warning('eBay1 coupon list failed', [
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
                if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED'], true)) {
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
     * @param  array<string, mixed>  $detail
     */
    private function couponPercent(array $detail): ?int
    {
        $disc = $detail['selectedInventoryDiscounts'][0]['discountBenefit'] ?? null;
        if (! is_array($disc)) {
            return null;
        }
        $raw = $disc['percentageOffItem'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) round((float) $raw);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function couponContainsSku(array $detail, string $sku, string $itemId = ''): bool
    {
        $want = strtoupper(trim($sku));
        $crit = $detail['selectedInventoryDiscounts'][0]['inventoryCriterion'] ?? [];
        if (! is_array($crit)) {
            return false;
        }
        $items = $crit['inventoryItems'] ?? [];
        if (is_array($items)) {
            foreach ($items as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
                if ($id !== '' && $id === $want) {
                    return true;
                }
            }
        }
        if ($itemId !== '') {
            $listingIds = $crit['listingIds'] ?? [];
            if (is_array($listingIds)) {
                foreach ($listingIds as $lid) {
                    if (trim((string) $lid) === $itemId) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array{success:bool,message:string}
     */
    private function addSkuToCoupon(string $token, string $promoId, string $sku, string $itemId): array
    {
        $detail = $this->getMarkdown($token, $promoId);
        if ($detail === null) {
            return ['success' => false, 'message' => 'Coupon not found: '.$promoId];
        }
        if ($this->couponContainsSku($detail, $sku, $itemId)) {
            return ['success' => true, 'message' => 'already present'];
        }

        $disc = $detail['selectedInventoryDiscounts'][0] ?? null;
        if (! is_array($disc)) {
            return ['success' => false, 'message' => 'Coupon has no discount tier'];
        }

        $crit = is_array($disc['inventoryCriterion'] ?? null) ? $disc['inventoryCriterion'] : [];
        $listingIds = $crit['listingIds'] ?? null;
        $usesListingIds = is_array($listingIds) && $listingIds !== [];

        if ($usesListingIds) {
            if ($itemId === '') {
                return ['success' => false, 'message' => 'Listing id required to add SKU to listingIds coupon'];
            }
            $listingIds[] = $itemId;
            $payload = $this->baseUpdatePayload($detail, $disc, inventoryItems: null, listingIds: array_values(array_unique(array_map('strval', $listingIds))));
        } else {
            $items = $crit['inventoryItems'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }
            $items[] = ['inventoryReferenceId' => $sku];
            $payload = $this->baseUpdatePayload($detail, $disc, inventoryItems: $items, listingIds: null);
        }

        $put = $this->putMarkdownWithRetry($token, $promoId, $payload, 'add SKU');
        if (! $put['success']) {
            return $put;
        }

        $after = $this->getMarkdown($token, $promoId);
        if ($after !== null && ! $this->couponContainsSku($after, $sku, $itemId)) {
            return ['success' => false, 'message' => 'Add SKU returned OK but SKU not on coupon yet — retry'];
        }

        return ['success' => true, 'message' => 'add SKU ok'];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{success:bool,message:string}
     */
    private function removeSkuFromCoupon(string $token, string $promoId, string $sku, string $itemId, array $detail): array
    {
        $status = (string) ($detail['promotionStatus'] ?? '');
        $disc = $detail['selectedInventoryDiscounts'][0] ?? null;
        if (! is_array($disc)) {
            return ['success' => false, 'message' => 'Coupon has no discount tier'];
        }

        $crit = is_array($disc['inventoryCriterion'] ?? null) ? $disc['inventoryCriterion'] : [];
        $listingIds = $crit['listingIds'] ?? null;
        $usesListingIds = is_array($listingIds) && $listingIds !== [];

        if ($usesListingIds) {
            $keptListings = [];
            foreach ($listingIds as $lid) {
                $lid = trim((string) $lid);
                if ($lid === '' || ($itemId !== '' && $lid === $itemId)) {
                    continue;
                }
                $keptListings[] = $lid;
            }
            $keptItems = null;
        } else {
            $items = $crit['inventoryItems'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }
            $want = strtoupper(trim($sku));
            $keptItems = [];
            foreach ($items as $it) {
                if (! is_array($it)) {
                    continue;
                }
                $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
                if ($id !== '' && $id === $want) {
                    continue;
                }
                $keptItems[] = $it;
            }
            $keptListings = null;
        }

        $isEmpty = $usesListingIds ? ($keptListings === []) : ($keptItems === []);

        // SCHEDULED/DRAFT/PAUSED: inventory replace works. RUNNING: eBay keeps existing SKUs.
        if (in_array($status, ['SCHEDULED', 'DRAFT', 'PAUSED'], true)) {
            if ($isEmpty) {
                $del = $this->http($token)
                    ->delete('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId));
                if ($del->successful() || $del->status() === 204) {
                    return ['success' => true, 'message' => 'coupon deleted (last SKU removed)'];
                }

                return ['success' => false, 'message' => 'Remove last SKU failed: '.$this->ebayErrorMessage($del)];
            }

            $payload = $this->baseUpdatePayload($detail, $disc, inventoryItems: $keptItems, listingIds: $keptListings);
            $put = $this->putMarkdownWithRetry($token, $promoId, $payload, 'remove SKU');
            if (! $put['success']) {
                return $put;
            }
            $after = $this->getMarkdown($token, $promoId);
            if ($after !== null && $this->couponContainsSku($after, $sku, $itemId)) {
                return ['success' => false, 'message' => 'Remove SKU returned OK but SKU still on coupon'];
            }

            return ['success' => true, 'message' => 'remove SKU ok'];
        }

        if ($status === 'RUNNING') {
            return [
                'success' => false,
                'message' => 'Cannot remove SKU from a RUNNING eBay coupon via API (eBay only allows adding inventory while RUNNING). Remove in Seller Hub, or use a SCHEDULED coupon for membership changes.',
            ];
        }

        return ['success' => false, 'message' => 'Unsupported coupon status '.$status];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $disc
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @param  list<string>|null  $listingIds
     * @return array<string, mixed>
     */
    private function baseUpdatePayload(array $detail, array $disc, ?array $inventoryItems, ?array $listingIds = null): array
    {
        $pct = $this->couponPercent($detail) ?? 0;
        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        if ($listingIds !== null) {
            $criterion['listingIds'] = array_values($listingIds);
        } else {
            $criterion['inventoryItems'] = array_values($inventoryItems ?? []);
        }

        $tier = [
            'discountBenefit' => [
                'percentageOffItem' => (string) $pct,
            ],
            'inventoryCriterion' => $criterion,
        ];
        if (! empty($disc['discountId'])) {
            $tier['discountId'] = (string) $disc['discountId'];
        }
        if (isset($disc['ruleOrder'])) {
            $tier['ruleOrder'] = $disc['ruleOrder'];
        }

        $payload = [
            'name' => (string) ($detail['name'] ?? 'PEF coupon'),
            'description' => (string) ($detail['description'] ?? 'PEF coupon'),
            'marketplaceId' => (string) ($detail['marketplaceId'] ?? self::MARKETPLACE),
            'startDate' => (string) ($detail['startDate'] ?? now('UTC')->format('Y-m-d\TH:i:s.000\Z')),
            'endDate' => (string) ($detail['endDate'] ?? now('UTC')->addDay()->format('Y-m-d\TH:i:s.000\Z')),
            // Required when updating a RUNNING promotion.
            'promotionStatus' => 'SCHEDULED',
            'promotionImageUrl' => (string) ($detail['promotionImageUrl'] ?? ''),
            'selectedInventoryDiscounts' => [$tier],
        ];

        foreach (['blockPriceIncreaseInItemRevision', 'applyFreeShipping', 'autoSelectFutureInventory'] as $flag) {
            if (array_key_exists($flag, $detail)) {
                $payload[$flag] = (bool) $detail[$flag];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success:bool,message:string}
     */
    private function putMarkdownWithRetry(string $token, string $promoId, array $payload, string $action): array
    {
        $lastMsg = 'unknown error';
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $resp = $this->http($token)
                ->put('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($promoId), $payload);

            if ($resp->successful() || $resp->status() === 204) {
                return ['success' => true, 'message' => $action.' ok'];
            }

            $lastMsg = $this->ebayErrorMessage($resp);
            $code = (string) (data_get($resp->json(), 'errors.0.errorId') ?? '');
            // 345073 = update already processing
            if ($code === '345073' || str_contains(strtolower($lastMsg), 'already processing')) {
                usleep(750000 * $attempt);

                continue;
            }

            Log::error('eBay1 coupon '.$action.' failed', [
                'promotion_id' => $promoId,
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 500),
            ]);

            return ['success' => false, 'message' => $action.' failed: '.$lastMsg];
        }

        return ['success' => false, 'message' => $action.' failed after retries: '.$lastMsg];
    }

    private function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withoutVerifying()
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Content-Language' => 'en-US',
            ])
            ->timeout(60);
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
        $val[self::DV_COUPON_PCT] = (float) $pct;
        if (! $dv->exists) {
            $dv->sku = $dv->sku ?: null;
        }
        $dv->value = $val;
        $dv->save();
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
