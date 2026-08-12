<?php

namespace App\Services;

use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay1 public coded coupons via Sell Marketing API (item_promotion / CODED_COUPON).
 *
 * Rules (product):
 * - All coupons are PUBLIC_SINGLE_SELLER_COUPON
 * - Coupon code auto-generated from % (e.g. 5 → SAVE05PCT)
 * - Starts immediately; duration = 30 days from creation
 * - Same CPN % → add SKU to the existing campaign for that % (do not create a second one)
 * - percent = 0 → remove SKU from coupon campaign(s)
 */
class Ebay1CouponService
{
    private const MARKETPLACE = 'EBAY_US';

    private const DURATION_DAYS = 30;

    private const DV_PROMO_ID = 'PEF_COUPON_PROMOTION_ID';

    private const DV_COUPON_PCT = 'PEF_COUPON_PCT';

    private const DV_COUPON_CODE = 'PEF_COUPON_CODE';

    public function __construct(
        private readonly EbayApiService $ebay
    ) {}

    /**
     * Sync CPN % to eBay1 public coded coupon for a SKU.
     *
     * @return array{success:bool,message:string,promotion_id:?string,coupon_code?:?string,paused?:bool,percent?:float}
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
            return $this->removeSkuFromCodedCoupons($token, $apiSku, $itemId, $dv, $val, $storedPromoId);
        }

        $pctInt = (int) round($percent);
        if ($pctInt < 5 || $pctInt > 80) {
            return [
                'success' => false,
                'message' => 'eBay coupon % must be 5–80 (or 0 to remove). Got '.$pctInt,
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        $imageUrl = $this->resolvePromotionImageUrl($itemId);
        if ($imageUrl === '') {
            return [
                'success' => false,
                'message' => 'eBay listing image required for coded coupon (promotionImageUrl)',
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        // Leave other %-tier coded coupons first (keep target %)
        $pre = $this->removeSkuFromCodedCoupons(
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
                'message' => $pre['message'] ?? 'Failed removing SKU from other coupon tier(s)',
                'promotion_id' => $storedPromoId ?: null,
            ];
        }

        $existing = $this->findCodedCouponForPercent($token, $pctInt, $apiSku, $itemId);
        if ($existing !== null) {
            $promoId = (string) $existing['promotion_id'];
            $code = (string) ($existing['coupon_code'] ?? $this->couponCodeForPercent($pctInt));

            if (! empty($existing['already_has_sku'])) {
                $this->persistDv($dv, $val, $promoId, $pctInt, $code);

                return [
                    'success' => true,
                    'message' => 'SKU already on public '.$pctInt.'% coupon ('.$code.')',
                    'promotion_id' => $promoId,
                    'coupon_code' => $code,
                    'percent' => (float) $pctInt,
                ];
            }

            $added = $this->addSkuToCodedCoupon($token, $promoId, $apiSku, $itemId, $imageUrl);
            if (! $added['success']) {
                return [
                    'success' => false,
                    'message' => $added['message'],
                    'promotion_id' => $promoId,
                    'coupon_code' => $code,
                ];
            }

            $this->persistDv($dv, $val, $promoId, $pctInt, $code);

            return [
                'success' => true,
                'message' => 'SKU added to existing public '.$pctInt.'% coupon ('.$code.')',
                'promotion_id' => $promoId,
                'coupon_code' => $code,
                'percent' => (float) $pctInt,
            ];
        }

        $created = $this->createCodedCoupon($token, $apiSku, $itemId, $pctInt, $imageUrl);
        if (! $created['success']) {
            return $created;
        }

        $promoId = (string) ($created['promotion_id'] ?? '');
        $code = (string) ($created['coupon_code'] ?? $this->couponCodeForPercent($pctInt));
        $this->persistDv($dv, $val, $promoId, $pctInt, $code);

        return [
            'success' => true,
            'message' => 'Created public '.$pctInt.'% coupon '.$code.' and added SKU',
            'promotion_id' => $promoId,
            'coupon_code' => $code,
            'percent' => (float) $pctInt,
        ];
    }

    /**
     * Autogenerate public coupon code from percent: 5 → SAVE05PCT (8–15 chars).
     */
    public function couponCodeForPercent(int $percent): string
    {
        $pct = max(1, min(99, $percent));

        return 'SAVE'.str_pad((string) $pct, 2, '0', STR_PAD_LEFT).'PCT';
    }

    /**
     * Campaign display name (stable per %) so we can recognize our campaigns.
     */
    public function campaignNameForPercent(int $percent): string
    {
        return 'PEF CPN '.$percent.'%';
    }

    /**
     * @param  array<string, mixed>  $val
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool}
     */
    private function removeSkuFromCodedCoupons(
        string $token,
        string $sku,
        string $itemId,
        EbayDataView $dv,
        array $val,
        string $storedPromoId,
        ?int $keepPercent = null,
        bool $persist = true
    ): array {
        $candidates = $this->listCodedCouponIds($token);
        if ($storedPromoId !== '' && ! in_array($storedPromoId, $candidates, true)) {
            array_unshift($candidates, $storedPromoId);
        }

        $removedFrom = [];
        $errors = [];
        $foundOn = 0;

        foreach ($candidates as $promoId) {
            $detail = $this->getItemPromotion($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $type = strtoupper((string) ($detail['promotionType'] ?? ''));
            $hasCouponCfg = isset($detail['couponConfiguration']) || isset($detail['coupon_configuration']);
            if ($type !== 'CODED_COUPON' && ! $hasCouponCfg) {
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
            $rm = $this->removeSkuFromCodedCoupon($token, $promoId, $sku, $itemId, $detail);
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
            $this->persistDv($dv, $val, '', 0, '');
        }

        if (! $persist) {
            return ['success' => true, 'message' => 'pre-remove done', 'promotion_id' => null];
        }

        return [
            'success' => true,
            'message' => $foundOn === 0
                ? 'SKU not on any active eBay1 coded coupon'
                : 'SKU removed from eBay1 coded coupon'.($foundOn > 1 ? 's' : ''),
            'promotion_id' => $removedFrom[0] ?? null,
            'paused' => true,
        ];
    }

    /**
     * @return array{promotion_id:string,coupon_code:string,already_has_sku:bool}|null
     */
    private function findCodedCouponForPercent(string $token, int $pctInt, string $sku, string $itemId): ?array
    {
        $ids = $this->listCodedCouponIds($token);
        $matchRunning = null;
        $matchOther = null;
        $matchWithSku = null;
        $wantName = strtoupper($this->campaignNameForPercent($pctInt));

        foreach ($ids as $promoId) {
            $detail = $this->getItemPromotion($token, $promoId);
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

            // Prefer our PEF CPN {n}% campaigns when multiple exist at same %
            $name = strtoupper(trim((string) ($detail['name'] ?? '')));
            $isOurs = $name === $wantName || str_starts_with($name, 'PEF CPN '.$pctInt);

            $code = trim((string) ($detail['couponConfiguration']['couponCode']
                ?? $detail['couponConfiguration']['coupon_code']
                ?? ''));
            if ($code === '') {
                $code = $this->couponCodeForPercent($pctInt);
            }

            $has = $this->couponContainsSku($detail, $sku, $itemId);
            $entry = [
                'promotion_id' => $promoId,
                'coupon_code' => $code,
                'already_has_sku' => $has,
            ];

            if ($has) {
                $matchWithSku = $entry;
                break;
            }
            if ($isOurs && $status === 'RUNNING') {
                $matchRunning ??= $entry;
            } elseif ($isOurs) {
                $matchOther ??= $entry;
            } elseif ($status === 'RUNNING') {
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
    private function listCodedCouponIds(string $token): array
    {
        $ids = [];
        $offset = 0;
        $limit = 50;

        for ($page = 0; $page < 20; $page++) {
            $resp = $this->http($token)->get('https://api.ebay.com/sell/marketing/v1/promotion', [
                'marketplace_id' => self::MARKETPLACE,
                'promotion_type' => 'CODED_COUPON',
                'limit' => $limit,
                'offset' => $offset,
            ]);
            if (! $resp->successful()) {
                Log::warning('eBay1 coded coupon list failed', [
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
    private function getItemPromotion(string $token, string $promoId): ?array
    {
        $resp = $this->http($token)
            ->get('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($promoId));
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
        $rule = $detail['discountRules'][0]['discountBenefit']
            ?? $detail['selectedInventoryDiscounts'][0]['discountBenefit']
            ?? null;
        if (! is_array($rule)) {
            return null;
        }
        $raw = $rule['percentageOffOrder'] ?? $rule['percentageOffItem'] ?? null;
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
        $crit = $detail['inventoryCriterion']
            ?? $detail['selectedInventoryDiscounts'][0]['inventoryCriterion']
            ?? [];
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
     * @return array{success:bool,message:string,promotion_id:?string,coupon_code?:string}
     */
    private function createCodedCoupon(
        string $token,
        string $sku,
        string $itemId,
        int $pctInt,
        string $imageUrl
    ): array {
        $code = $this->couponCodeForPercent($pctInt);
        $lastMsg = 'unknown';

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $tryCode = $attempt === 1
                ? $code
                : $this->couponCodeWithSuffix($pctInt);

            $payload = $this->codedCouponPayload(
                $sku,
                $itemId,
                $pctInt,
                $imageUrl,
                $tryCode,
                inventoryItems: [['inventoryReferenceId' => $sku]],
                startDate: null,
                endDate: null,
                existingDetail: null
            );

            $resp = $this->http($token)
                ->post('https://api.ebay.com/sell/marketing/v1/item_promotion', $payload);

            if ($resp->successful() || $resp->status() === 201) {
                $promoId = $this->extractPromotionId($resp);
                if ($promoId === '') {
                    return [
                        'success' => false,
                        'message' => 'Create ok but promotion id missing',
                        'promotion_id' => null,
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'created',
                    'promotion_id' => $promoId,
                    'coupon_code' => $tryCode,
                ];
            }

            $lastMsg = $this->ebayErrorMessage($resp);
            $body = strtolower($lastMsg.' '.$resp->body());
            // Code already used → retry with suffix
            if (str_contains($body, 'coupon') && (str_contains($body, 'unique') || str_contains($body, 'already') || str_contains($body, 'exist'))) {
                continue;
            }

            Log::error('eBay1 coded coupon create failed', [
                'sku' => $sku,
                'percent' => $pctInt,
                'code' => $tryCode,
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 500),
            ]);

            return [
                'success' => false,
                'message' => 'Create failed: '.$lastMsg,
                'promotion_id' => null,
            ];
        }

        return [
            'success' => false,
            'message' => 'Create failed after code retries: '.$lastMsg,
            'promotion_id' => null,
        ];
    }

    private function couponCodeWithSuffix(int $percent): string
    {
        $base = 'SAVE'.str_pad((string) max(1, min(99, $percent)), 2, '0', STR_PAD_LEFT);
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        // Keep 8–15 chars: SAVE05 + 5 = 10
        return substr($base.$suffix, 0, 15);
    }

    /**
     * @return array{success:bool,message:string}
     */
    private function addSkuToCodedCoupon(
        string $token,
        string $promoId,
        string $sku,
        string $itemId,
        string $imageUrl
    ): array {
        $detail = $this->getItemPromotion($token, $promoId);
        if ($detail === null) {
            return ['success' => false, 'message' => 'Coupon campaign not found: '.$promoId];
        }
        if ($this->couponContainsSku($detail, $sku, $itemId)) {
            return ['success' => true, 'message' => 'already present'];
        }

        $pct = $this->couponPercent($detail);
        if ($pct === null) {
            return ['success' => false, 'message' => 'Campaign has no discount %'];
        }

        $crit = is_array($detail['inventoryCriterion'] ?? null) ? $detail['inventoryCriterion'] : [];
        $listingIds = $crit['listingIds'] ?? null;
        $usesListingIds = is_array($listingIds) && $listingIds !== [];

        if ($usesListingIds) {
            if ($itemId === '') {
                return ['success' => false, 'message' => 'Listing id required to add SKU'];
            }
            $listingIds[] = $itemId;
            $items = null;
            $listings = array_values(array_unique(array_map('strval', $listingIds)));
        } else {
            $items = $crit['inventoryItems'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }
            $items[] = ['inventoryReferenceId' => $sku];
            $listings = null;
        }

        $code = trim((string) ($detail['couponConfiguration']['couponCode']
            ?? $detail['couponConfiguration']['coupon_code']
            ?? $this->couponCodeForPercent($pct)));

        $img = trim((string) ($detail['promotionImageUrl'] ?? ''));
        if ($img === '' || ! filter_var($img, FILTER_VALIDATE_URL)) {
            $img = $imageUrl;
        }

        $payload = $this->codedCouponPayload(
            $sku,
            $itemId,
            $pct,
            $img,
            $code,
            inventoryItems: $items,
            listingIds: $listings,
            startDate: (string) ($detail['startDate'] ?? ''),
            endDate: (string) ($detail['endDate'] ?? ''),
            existingDetail: $detail
        );

        $resp = $this->http($token)
            ->put('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($promoId), $payload);

        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'add SKU ok'];
        }

        Log::error('eBay1 coded coupon add SKU failed', [
            'promotion_id' => $promoId,
            'sku' => $sku,
            'status' => $resp->status(),
            'body' => mb_substr($resp->body(), 0, 500),
        ]);

        return ['success' => false, 'message' => 'Add SKU failed: '.$this->ebayErrorMessage($resp)];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{success:bool,message:string}
     */
    private function removeSkuFromCodedCoupon(
        string $token,
        string $promoId,
        string $sku,
        string $itemId,
        array $detail
    ): array {
        $pct = $this->couponPercent($detail) ?? 0;
        $crit = is_array($detail['inventoryCriterion'] ?? null) ? $detail['inventoryCriterion'] : [];
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
            $isEmpty = $keptListings === [];
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
            $isEmpty = $keptItems === [];
        }

        // Last SKU → pause campaign (keep campaign for future SKUs at this %)
        if ($isEmpty) {
            $url = 'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($promoId).'/pause';
            $resp = $this->http($token)->post($url);
            if ($resp->successful() || in_array($resp->status(), [204, 400, 409], true)) {
                return ['success' => true, 'message' => 'SKU removed; empty campaign paused'];
            }

            return ['success' => false, 'message' => 'Pause empty campaign failed: '.$this->ebayErrorMessage($resp)];
        }

        $code = trim((string) ($detail['couponConfiguration']['couponCode']
            ?? $detail['couponConfiguration']['coupon_code']
            ?? $this->couponCodeForPercent($pct)));
        $img = trim((string) ($detail['promotionImageUrl'] ?? ''));

        $payload = $this->codedCouponPayload(
            $sku,
            $itemId,
            $pct,
            $img !== '' ? $img : 'https://i.ebayimg.com/images/g/placeholder/s-l1600.jpg',
            $code,
            inventoryItems: $keptItems,
            listingIds: $keptListings,
            startDate: (string) ($detail['startDate'] ?? ''),
            endDate: (string) ($detail['endDate'] ?? ''),
            existingDetail: $detail
        );

        $resp = $this->http($token)
            ->put('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($promoId), $payload);

        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'remove SKU ok'];
        }

        return ['success' => false, 'message' => 'Remove SKU failed: '.$this->ebayErrorMessage($resp)];
    }

    /**
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @param  list<string>|null  $listingIds
     * @param  array<string, mixed>|null  $existingDetail
     * @return array<string, mixed>
     */
    private function codedCouponPayload(
        string $sku,
        string $itemId,
        int $pctInt,
        string $imageUrl,
        string $couponCode,
        ?array $inventoryItems = null,
        ?array $listingIds = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?array $existingDetail = null
    ): array {
        $start = ($startDate !== null && $startDate !== '')
            ? $startDate
            : now('UTC')->addSeconds(30)->format('Y-m-d\TH:i:s.000\Z');
        $end = ($endDate !== null && $endDate !== '')
            ? $endDate
            : now('UTC')->addDays(self::DURATION_DAYS)->format('Y-m-d\TH:i:s.000\Z');

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        if ($listingIds !== null) {
            $criterion['listingIds'] = array_values($listingIds);
        } else {
            $criterion['inventoryItems'] = array_values($inventoryItems ?? [
                ['inventoryReferenceId' => $sku],
            ]);
        }

        $couponType = 'PUBLIC_SINGLE_SELLER_COUPON';
        if (is_array($existingDetail)) {
            $couponType = (string) ($existingDetail['couponConfiguration']['couponType']
                ?? $existingDetail['couponConfiguration']['coupon_type']
                ?? $couponType);
        }

        $payload = [
            'name' => $this->campaignNameForPercent($pctInt),
            'description' => $pctInt.'% public coupon (auto '.$couponCode.')',
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'CODED_COUPON',
            'promotionImageUrl' => $imageUrl,
            'couponConfiguration' => [
                'couponCode' => $couponCode,
                'couponType' => $couponType,
                'maxCouponRedemptionPerUser' => 1,
            ],
            'inventoryCriterion' => $criterion,
            'discountRules' => [
                [
                    'discountSpecification' => [
                        'minQuantity' => 1,
                    ],
                    'discountBenefit' => [
                        'percentageOffOrder' => (string) $pctInt,
                    ],
                    'ruleOrder' => 1,
                ],
            ],
        ];

        // Updating a RUNNING promo requires SCHEDULED status in body (eBay rule)
        if (is_array($existingDetail) && ($existingDetail['promotionStatus'] ?? '') === 'RUNNING') {
            $payload['promotionStatus'] = 'SCHEDULED';
        }

        return $payload;
    }

    private function resolvePromotionImageUrl(string $itemId): string
    {
        try {
            $details = $this->ebay->getItem($itemId);
            $item = is_array($details) ? ($details['Item'] ?? null) : null;
            if (! is_array($item)) {
                return '';
            }
            $pic = $item['PictureDetails']['GalleryURL']
                ?? $item['PictureDetails']['PictureURL']
                ?? null;
            if (is_array($pic)) {
                $pic = $pic[0] ?? reset($pic);
            }
            $url = is_string($pic) ? trim($pic) : '';

            return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        } catch (\Throwable $e) {
            Log::warning('eBay1 coupon image resolve failed', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function extractPromotionId(Response $resp): string
    {
        $loc = (string) ($resp->header('Location') ?? $resp->header('location') ?? '');
        if ($loc !== '') {
            if (preg_match('#(?:item_promotion|promotion)/([^/?]+)#', $loc, $m)) {
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

    /**
     * @param  array<string, mixed>  $val
     */
    private function persistDv(EbayDataView $dv, array $val, string $promoId, int|float $pct, string $code): void
    {
        if ($promoId !== '') {
            $val[self::DV_PROMO_ID] = $promoId;
        } else {
            unset($val[self::DV_PROMO_ID]);
        }
        if ($code !== '') {
            $val[self::DV_COUPON_CODE] = $code;
        } else {
            unset($val[self::DV_COUPON_CODE]);
        }
        $val[self::DV_COUPON_PCT] = (float) $pct;
        // Keep channel promo CPN% in sync when coupon is pushed
        $val['PEF_CPN_PCT'] = (float) $pct;
        if (! $dv->exists) {
            $dv->sku = $dv->sku ?: null;
        }
        $dv->value = $val;
        $dv->save();
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
