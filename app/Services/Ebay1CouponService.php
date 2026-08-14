<?php

namespace App\Services;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Public coded coupons via Sell Marketing API (item_promotion / CODED_COUPON).
 * eBay1, eBay2, eBay3 (Ebay1CouponService::for($channel)).
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

    /** @var object */
    private $ebay;

    private readonly string $channel;

    private readonly string $label;

    public function __construct(?EbayApiService $ebay = null, string $channel = 'ebay1')
    {
        $this->channel = strtolower(trim($channel)) ?: 'ebay1';
        $this->label = match (true) {
            $this->isEbay3() => 'eBay3',
            $this->isEbay2() => 'eBay2',
            default => 'eBay1',
        };
        $this->ebay = $ebay ?? match (true) {
            $this->isEbay3() => app(EbayThreeApiService::class),
            $this->isEbay2() => app(Ebay2ApiService::class),
            default => app(EbayApiService::class),
        };
    }

    public static function for(string $channel): self
    {
        $channel = strtolower(trim($channel)) ?: 'ebay1';

        return new self(null, $channel);
    }

    private function isEbay2(): bool
    {
        return in_array($this->channel, ['ebay2', 'ebay2op'], true);
    }

    private function isEbay3(): bool
    {
        return $this->channel === 'ebay3';
    }

    private function findMetric(string $sku): ?object
    {
        if ($this->isEbay3()) {
            return Ebay3Metric::query()->where('sku', $sku)->first()
                ?: Ebay3Metric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        }
        if ($this->isEbay2()) {
            return Ebay2Metric::query()->where('sku', $sku)->first()
                ?: Ebay2Metric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
        }

        return EbayMetric::query()->where('sku', $sku)->first()
            ?: EbayMetric::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first();
    }

    private function findOrNewDataView(string $sku): Model
    {
        if ($this->isEbay3()) {
            return EbayThreeDataView::query()->where('sku', $sku)->first()
                ?: EbayThreeDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                ?: new EbayThreeDataView(['sku' => $sku]);
        }
        if ($this->isEbay2()) {
            return EbayTwoDataView::query()->where('sku', $sku)->first()
                ?: EbayTwoDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
                ?: new EbayTwoDataView(['sku' => $sku]);
        }

        return EbayDataView::query()->where('sku', $sku)->first()
            ?: EbayDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: new EbayDataView(['sku' => $sku]);
    }

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

        $metric = $this->findMetric($sku);
        $itemId = $metric?->item_id ? trim((string) $metric->item_id) : '';
        if ($itemId === '') {
            return ['success' => false, 'message' => $this->label.' item_id not found for SKU', 'promotion_id' => null];
        }

        $dv = $this->findOrNewDataView($sku);
        $val = is_array($dv->value) ? $dv->value : [];
        $storedPromoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';
        $apiSku = trim((string) ($metric->sku ?: $sku));

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $this->label.' token: '.$e->getMessage(), 'promotion_id' => $storedPromoId ?: null];
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
        Model $dv,
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
                ? 'SKU not on any active '.$this->label.' coded coupon'
                : 'SKU removed from '.$this->label.' coded coupon'.($foundOn > 1 ? 's' : ''),
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
            $wantCode = strtoupper($this->couponCodeForPercent($pctInt));
            $isOurs = $isOurs || strtoupper($code) === $wantCode;

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
            ->get('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($this->couponApiId($promoId)));
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
            $wantListing = $this->couponListingId($itemId);
            $listingIds = $crit['listingIds'] ?? [];
            if (is_array($listingIds) && $wantListing !== '') {
                foreach ($listingIds as $lid) {
                    if ($this->couponListingId((string) $lid) === $wantListing) {
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
        $listingId = $this->couponListingId($itemId);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $tryCode = $attempt === 1
                ? $code
                : $this->couponCodeWithSuffix($pctInt);

            foreach ([false, true] as $withMaxDiscount) {
                $payload = $this->codedCouponPayload(
                    $sku,
                    $itemId,
                    $pctInt,
                    $imageUrl,
                    $tryCode,
                    inventoryItems: $listingId === '' ? [['inventoryReferenceId' => $sku]] : null,
                    listingIds: $listingId !== '' ? [$listingId] : null,
                    startDate: null,
                    endDate: null,
                    existingDetail: null,
                    includeMaxDiscount: $withMaxDiscount
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

                if (! $withMaxDiscount && $this->isMaxDiscountRequiredError($resp)) {
                    continue;
                }
                if ($withMaxDiscount && $this->isMaxDiscountForbiddenError($resp)) {
                    continue;
                }

                if ($this->isCouponCodeTakenError($resp)) {
                    $attached = $this->attachSkuToExistingCodedCoupon(
                        $token,
                        $pctInt,
                        $sku,
                        $itemId,
                        $imageUrl
                    );
                    if ($attached !== null) {
                        return $attached;
                    }
                    break;
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
        }

        return [
            'success' => false,
            'message' => 'Create failed after code retries: '.$lastMsg,
            'promotion_id' => null,
        ];
    }

    /**
     * @return array{success:bool,message:string,promotion_id:?string,coupon_code?:string}|null
     */
    private function attachSkuToExistingCodedCoupon(
        string $token,
        int $pctInt,
        string $sku,
        string $itemId,
        string $imageUrl
    ): ?array {
        $existing = $this->findCodedCouponForPercent($token, $pctInt, $sku, $itemId);
        if ($existing === null) {
            return null;
        }
        $promoId = (string) $existing['promotion_id'];
        $code = (string) ($existing['coupon_code'] ?? $this->couponCodeForPercent($pctInt));
        if (! empty($existing['already_has_sku'])) {
            return [
                'success' => true,
                'message' => 'SKU already on public '.$pctInt.'% coupon ('.$code.')',
                'promotion_id' => $promoId,
                'coupon_code' => $code,
            ];
        }
        $added = $this->addSkuToCodedCoupon($token, $promoId, $sku, $itemId, $imageUrl);
        if (! $added['success']) {
            return [
                'success' => false,
                'message' => $added['message'],
                'promotion_id' => $promoId,
                'coupon_code' => $code,
            ];
        }

        return [
            'success' => true,
            'message' => 'SKU added to existing public '.$pctInt.'% coupon ('.$code.')',
            'promotion_id' => $promoId,
            'coupon_code' => $code,
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
        $listingId = $this->couponListingId($itemId);

        if ($usesListingIds) {
            if ($listingId === '') {
                return ['success' => false, 'message' => 'Listing id required to add SKU'];
            }
            $listingIds[] = $listingId;
            $items = null;
            $listings = array_values(array_unique(array_filter(array_map(
                fn ($lid) => $this->couponListingId((string) $lid),
                $listingIds
            ))));
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

        $wasRunning = strtoupper(trim((string) ($detail['promotionStatus'] ?? ''))) === 'RUNNING';
        $wasPaused = strtoupper(trim((string) ($detail['promotionStatus'] ?? ''))) === 'PAUSED';
        $resp = $this->putCodedCouponWithDiscountRetry($token, $promoId, $payload);

        if ($resp->successful() || $resp->status() === 204) {
            if ($wasPaused) {
                $this->resumeCoupon($token, $promoId);
            }

            return ['success' => true, 'message' => 'add SKU ok'];
        }

        // RUNNING Hub coupons often require pause → update inventory → resume.
        if ($wasRunning) {
            $this->pauseCoupon($token, $promoId);
            $pausedDetail = $this->getItemPromotion($token, $promoId) ?? $detail;
            $payload = $this->codedCouponPayload(
                $sku,
                $itemId,
                $pct,
                $img,
                $code,
                inventoryItems: $items,
                listingIds: $listings,
                startDate: (string) ($pausedDetail['startDate'] ?? $detail['startDate'] ?? ''),
                endDate: (string) ($pausedDetail['endDate'] ?? $detail['endDate'] ?? ''),
                existingDetail: $pausedDetail
            );
            $resp = $this->putCodedCouponWithDiscountRetry($token, $promoId, $payload);
            if ($resp->successful() || $resp->status() === 204) {
                $this->resumeCoupon($token, $promoId);

                return ['success' => true, 'message' => 'add SKU ok'];
            }
            $this->resumeCoupon($token, $promoId);
        }

        // Child variation SKU on an inventoryItems campaign → convert to listing ids and retry.
        if ($listings === null && $listingId !== '' && $this->isVariationSkuCouponError($resp)) {
            $fromItems = $this->listingIdsFromCouponInventory($crit);
            $listings = array_values(array_unique(array_filter(array_merge($fromItems, [$listingId]))));
            $payload = $this->codedCouponPayload(
                $sku,
                $itemId,
                $pct,
                $img,
                $code,
                inventoryItems: null,
                listingIds: $listings,
                startDate: (string) ($detail['startDate'] ?? ''),
                endDate: (string) ($detail['endDate'] ?? ''),
                existingDetail: $detail
            );
            if ($wasRunning) {
                $this->pauseCoupon($token, $promoId);
            }
            $retry = $this->putCodedCouponWithDiscountRetry($token, $promoId, $payload);
            if ($retry->successful() || $retry->status() === 204) {
                if ($wasRunning || $wasPaused) {
                    $this->resumeCoupon($token, $promoId);
                }

                return ['success' => true, 'message' => 'add SKU ok'];
            }
            if ($wasRunning) {
                $this->resumeCoupon($token, $promoId);
            }
            $resp = $retry;
        }

        Log::error('eBay1 coded coupon add SKU failed', [
            'promotion_id' => $promoId,
            'sku' => $sku,
            'status' => $resp->status(),
            'body' => mb_substr($resp->body(), 0, 500),
            'payload_keys' => array_keys($payload),
            'coupon_code' => $payload['couponConfiguration']['couponCode'] ?? null,
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
            $wantListing = $this->couponListingId($itemId);
            $keptListings = [];
            foreach ($listingIds as $lid) {
                $lid = $this->couponListingId((string) $lid);
                if ($lid === '' || ($wantListing !== '' && $lid === $wantListing)) {
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

        $resp = $this->putCodedCouponWithDiscountRetry($token, $promoId, $payload);

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
        ?array $existingDetail = null,
        bool $includeMaxDiscount = true
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

        if (is_array($existingDetail)) {
            return $this->liveCouponInventoryPayload(
                $existingDetail,
                $listingIds !== null ? null : ($inventoryItems ?? [['inventoryReferenceId' => $sku]]),
                $listingIds,
                $imageUrl
            );
        }

        $couponConfig = [
            'couponCode' => $couponCode,
            'couponType' => 'PUBLIC_SINGLE_SELLER_COUPON',
            'maxCouponRedemptionPerUser' => 1,
        ];
        $rule = [
            'discountSpecification' => ['minQuantity' => 1],
            'discountBenefit' => ['percentageOffOrder' => (string) $pctInt],
            'ruleOrder' => 1,
        ];
        if ($includeMaxDiscount) {
            $rule['maxDiscountAmount'] = ['currency' => 'USD', 'value' => '50'];
        }

        return [
            'name' => $this->campaignNameForPercent($pctInt),
            'description' => $this->clipDescription($pctInt.'% off with code '.$couponCode),
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'CODED_COUPON',
            'promotionImageUrl' => $imageUrl,
            'budget' => ['currency' => 'USD', 'value' => '500'],
            'couponConfiguration' => $couponConfig,
            'inventoryCriterion' => $criterion,
            'discountRules' => [$rule],
        ];
    }

    /**
     * RUNNING/PAUSED coupon PUTs may only change inventoryCriterion and endDate.
     * couponConfiguration must be echoed unchanged (couponCode is required; dropping
     * maxCouponRedemptionPerUser is treated as changing it to unlimited → 345142).
     *
     * @param  array<string, mixed>  $detail
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @param  list<string>|null  $listingIds
     * @return array<string, mixed>
     */
    private function liveCouponInventoryPayload(
        array $detail,
        ?array $inventoryItems,
        ?array $listingIds,
        string $imageUrl = ''
    ): array {
        $pct = $this->couponPercent($detail) ?? 0;
        $code = trim((string) ($detail['couponConfiguration']['couponCode']
            ?? $detail['couponConfiguration']['coupon_code']
            ?? $this->couponCodeForPercent($pct)));
        $existingRule = is_array($detail['discountRules'][0] ?? null) ? $detail['discountRules'][0] : [];
        $spec = is_array($existingRule['discountSpecification'] ?? null)
            ? $existingRule['discountSpecification']
            : ['minQuantity' => 1];
        $benefit = is_array($existingRule['discountBenefit'] ?? null)
            ? $existingRule['discountBenefit']
            : ['percentageOffOrder' => (string) $pct];

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        if ($listingIds !== null) {
            $criterion['listingIds'] = array_values($listingIds);
        } else {
            $criterion['inventoryItems'] = array_values($inventoryItems ?? []);
        }

        $rule = [
            'discountSpecification' => [
                'minQuantity' => $spec['minQuantity'] ?? $spec['min_quantity'] ?? 1,
            ],
            'discountBenefit' => [
                'percentageOffOrder' => (string) ($benefit['percentageOffOrder']
                    ?? $benefit['percentage_off_order']
                    ?? $pct),
            ],
            'ruleOrder' => $existingRule['ruleOrder'] ?? $existingRule['rule_order'] ?? 1,
        ];
        $discountId = trim((string) ($existingRule['discountId'] ?? $existingRule['discount_id'] ?? ''));
        if ($discountId !== '') {
            $rule['discountId'] = $discountId;
        }
        $maxDisc = $existingRule['maxDiscountAmount']
            ?? $existingRule['max_discount_amount']
            ?? $benefit['maxDiscountAmount']
            ?? $benefit['max_discount_amount']
            ?? null;
        // Echo or default. Omitting it on this campaign returns "valid entry is required".
        $rule['maxDiscountAmount'] = $this->couponWholeDollarAmount(is_array($maxDisc) ? $maxDisc : null);

        $img = trim((string) ($detail['promotionImageUrl'] ?? $detail['promotion_image_url'] ?? ''));
        if ($img === '' || ! filter_var($img, FILTER_VALIDATE_URL)) {
            $img = $imageUrl;
        }

        $payload = [
            'name' => (string) ($detail['name'] ?? $this->campaignNameForPercent($pct)),
            'description' => $this->clipDescription(trim((string) ($detail['description'] ?? '')) !== ''
                ? (string) $detail['description']
                : $pct.'% off with code '.$code),
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => (string) ($detail['startDate'] ?? $detail['start_date'] ?? ''),
            'endDate' => (string) ($detail['endDate'] ?? $detail['end_date'] ?? ''),
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'CODED_COUPON',
            // Echo existing coupon config. Omitting couponCode → required error.
            // Omitting maxCouponRedemptionPerUser → eBay treats it as "unlimited" (345142).
            'couponConfiguration' => $this->couponConfigurationForUpdate($detail, $code),
            'inventoryCriterion' => $criterion,
            'discountRules' => [$rule],
        ];
        if ($img !== '' && filter_var($img, FILTER_VALIDATE_URL)) {
            $payload['promotionImageUrl'] = $img;
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

    private function couponListingId(string $itemId): string
    {
        $id = trim($itemId);
        if (preg_match('/^v1\\|([^|]+)/', $id, $m)) {
            return trim((string) $m[1]);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $crit
     * @return list<string>
     */
    private function listingIdsFromCouponInventory(array $crit): array
    {
        $ids = [];
        $items = $crit['inventoryItems'] ?? [];
        if (! is_array($items)) {
            return $ids;
        }
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $ref = trim((string) ($it['inventoryReferenceId'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $metric = $this->findMetric($ref);
            $lid = $metric?->item_id ? $this->couponListingId((string) $metric->item_id) : '';
            if ($lid !== '') {
                $ids[] = $lid;
            }
        }

        return $ids;
    }

    private function couponApiId(string $promoId): string
    {
        $id = trim($promoId);
        if ($id !== '' && ! str_contains($id, '@')) {
            return $id.'@'.self::MARKETPLACE;
        }

        return $id;
    }

    private function isMaxRedemptionForbiddenError(Response $resp): bool
    {
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return str_contains($body, 'maxcouponredemptionperuser');
    }

    private function isMaxDiscountRequiredError(Response $resp): bool
    {
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return str_contains($body, 'maxdiscountamount')
            && (str_contains($body, 'required') || str_contains($body, 'valid entry'));
    }

    private function isMaxDiscountForbiddenError(Response $resp): bool
    {
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return str_contains($body, 'maxdiscountamount')
            && (str_contains($body, 'can not be set')
                || str_contains($body, 'cannot be set')
                || str_contains($body, 'not valid')
                || str_contains($body, 'promotion type'));
    }

    private function isCouponCodeTakenError(Response $resp): bool
    {
        $json = $resp->json();
        $errors = is_array($json) ? ($json['errors'] ?? []) : [];
        if (is_array($errors)) {
            foreach ($errors as $err) {
                if (! is_array($err)) {
                    continue;
                }
                if ((int) ($err['errorId'] ?? 0) === 345145) {
                    return true;
                }
                $params = $err['parameters'] ?? [];
                if (is_array($params)) {
                    foreach ($params as $p) {
                        if (! is_array($p)) {
                            continue;
                        }
                        if (strtolower((string) ($p['name'] ?? '')) === 'fieldname'
                            && strtolower((string) ($p['value'] ?? '')) === 'couponcode') {
                            return true;
                        }
                    }
                }
            }
        }
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return (str_contains($body, 'couponcode') || str_contains($body, 'coupon code'))
            && (str_contains($body, 'already') || str_contains($body, 'exist') || str_contains($body, 'unique'));
    }

    private function isVariationSkuCouponError(Response $resp): bool
    {
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return str_contains($body, 'listing with variations')
            || str_contains($body, 'parent, or main, sku')
            || str_contains($body, '"errorid":38275')
            || str_contains($body, 'errorid":38275');
    }

    private function putCodedCoupon(string $token, string $promoId, array $payload): Response
    {
        return $this->http($token)
            ->put('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($this->couponApiId($promoId)), $payload);
    }

    /**
     * Some coded coupons require maxDiscountAmount; others reject it (345145).
     */
    private function putCodedCouponWithDiscountRetry(string $token, string $promoId, array $payload): Response
    {
        $resp = $this->putCodedCoupon($token, $promoId, $payload);
        if ($resp->successful() || $resp->status() === 204) {
            return $resp;
        }
        if ($this->isMaxDiscountRequiredError($resp) && ! isset($payload['discountRules'][0]['maxDiscountAmount'])) {
            $payload['discountRules'][0]['maxDiscountAmount'] = $this->couponWholeDollarAmount(null);

            return $this->putCodedCoupon($token, $promoId, $payload);
        }
        if ($this->isMaxDiscountForbiddenError($resp) && isset($payload['discountRules'][0]['maxDiscountAmount'])) {
            unset($payload['discountRules'][0]['maxDiscountAmount']);

            return $this->putCodedCoupon($token, $promoId, $payload);
        }

        return $resp;
    }

    /**
     * eBay Amount.value for coupons must be whole dollars.
     *
     * @param  array<string, mixed>|null  $amount
     * @return array{currency:string,value:string}
     */
    private function couponWholeDollarAmount(?array $amount, string $defaultValue = '50'): array
    {
        $currency = 'USD';
        $value = $defaultValue;
        if (is_array($amount)) {
            $currency = trim((string) ($amount['currency'] ?? $amount['currencyId'] ?? 'USD'));
            if (($amount['value'] ?? '') !== '' && $amount['value'] !== null) {
                $value = (string) $amount['value'];
            }
        }

        return [
            'currency' => $currency !== '' ? $currency : 'USD',
            'value' => (string) max(1, (int) round((float) $value)),
        ];
    }

    /**
     * Keep couponCode / couponType / max redemptions identical to the live campaign.
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function couponConfigurationForUpdate(array $detail, string $fallbackCode): array
    {
        $raw = $detail['couponConfiguration'] ?? $detail['coupon_configuration'] ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }
        $code = trim((string) ($raw['couponCode'] ?? $raw['coupon_code'] ?? $fallbackCode));
        $type = trim((string) ($raw['couponType'] ?? $raw['coupon_type'] ?? 'PUBLIC_SINGLE_SELLER_COUPON'));
        $cfg = [
            'couponCode' => $code !== '' ? $code : $fallbackCode,
            'couponType' => $type !== '' ? $type : 'PUBLIC_SINGLE_SELLER_COUPON',
        ];
        $max = $raw['maxCouponRedemptionPerUser'] ?? $raw['max_coupon_redemption_per_user'] ?? null;
        // Always send the current limit. Leaving it off is treated as "unlimited" (345142).
        $cfg['maxCouponRedemptionPerUser'] = ($max !== null && $max !== '') ? (int) $max : 1;

        return $cfg;
    }

    private function pauseCoupon(string $token, string $promoId): bool
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($this->couponApiId($promoId)).'/pause';
        $resp = $this->http($token)->post($url);

        return $resp->successful() || in_array($resp->status(), [204, 400, 409], true);
    }

    private function resumeCoupon(string $token, string $promoId): void
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($this->couponApiId($promoId)).'/resume';
        try {
            $this->http($token)->timeout(30)->post($url);
        } catch (\Throwable $e) {
            // already RUNNING / SCHEDULED
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
    private function persistDv(Model $dv, array $val, string $promoId, int|float $pct, string $code): void
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

    /** eBay promotion description max length is 50. */
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
