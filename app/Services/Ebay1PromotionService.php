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
 * eBay markdown sale-event sync via Sell Marketing API (item_price_markdown).
 * eBay1, eBay2, eBay3 (Ebay1PromotionService::for($channel)).
 *
 * Push Prc step 2 — PRMT % column:
 * - PRMT% = 10 → Add SKU/listing to the existing 10% sale event (Seller Hub "Add items")
 * - PRMT% changes (e.g. 10 → 8) → Remove from the old sale, add to the matching % sale
 * - PRMT% = 0 → Remove from all sale events
 * - Never create a second sale at the same %; reuse the seller's campaign
 * - If no sale exists at that %, create PEF SALE {n}%
 * - New PEF sales set blockPriceIncreaseInItemRevision=false so tabulator /
 *   Push Prc StartPrice revises are not rejected ("part of a sale")
 * - withPriceRevisionAllowed() unlocks or temporarily removes the SKU when an
 *   existing Hub sale still blocks price updates, then restores the sale
 */
class Ebay1PromotionService
{
    private const MARKETPLACE = 'EBAY_US';

    /** Sale events: start now, run 7 days. */
    private const DURATION_DAYS = 7;

    private const DV_PROMO_ID = 'PEF_PRMT_PROMOTION_ID';

    private const DV_PRMT_PCT = 'PEF_PRMT_PCT';

    private const DV_SALE_PCT = 'PEF_SALE_PCT';

    /** @var object */
    private $ebay;

    private readonly string $channel;

    private readonly string $label;

    /** @var array<string, true> */
    private array $detachedListingIds = [];

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
            if (! empty($existing['already_has_sku'])) {
                $this->tryUnlockSale($token, $promoId, false);
                $this->persistDv($dv, $val, $promoId, $pctInt);

                return [
                    'success' => true,
                    'message' => 'Listing already on '.$pctInt.'% sale event',
                    'promotion_id' => $promoId,
                    'percent' => (float) $pctInt,
                ];
            }

            $added = $this->addSkuToSale($token, $promoId, $apiSku, $itemId);
            if ($added['success']) {
                $this->tryUnlockSale($token, $promoId, false);
                $this->resumeIfNeeded($token, $promoId);
                $this->persistDv($dv, $val, $promoId, $pctInt);

                return [
                    'success' => true,
                    'message' => 'Added listing to '.$pctInt.'% sale event',
                    'promotion_id' => $promoId,
                    'percent' => (float) $pctInt,
                ];
            }
            Log::warning('eBay1 add to existing sale failed — will create a new 7-day sale', [
                'sku' => $sku,
                'promotion_id' => $promoId,
                'error' => $added['message'] ?? '',
            ]);
            // Do not abort: Hub rule-based / invalid-discount sales cannot accept Add items.
            // Create a listing-based PEF sale at this % instead.
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
            'message' => 'Created '.$pctInt.'% sale event and added listing',
            'promotion_id' => $newId,
            'percent' => (float) $pctInt,
        ];
    }

    /**
     * Run a StartPrice revise. If eBay blocks it because the listing is on a
     * markdown sale, unlock the sale (always allow price updates) and/or
     * temporarily remove the SKU, revise, then put the listing back on sale.
     *
     * @param  callable(): array<string, mixed>  $revise
     * @return array<string, mixed>
     */
    public function withPriceRevisionAllowed(string $sku, callable $revise): array
    {
        $first = $revise();
        if ($this->reviseSucceeded($first) || ! $this->isSalePriceLockError($first)) {
            return $first;
        }

        Log::warning($this->label.' StartPrice blocked by markdown sale — will pause/unlock or remove-revise-restore', [
            'sku' => $sku,
            'errors' => $first['errors'] ?? ($first['message'] ?? null),
        ]);

        // RUNNING sales reject the flag unless paused. Keep paused until revise finishes.
        $unlocked = $this->unlockPriceUpdatesForSku($sku, true, false);
        try {
            $second = $revise();
            if ($this->reviseSucceeded($second) || ! $this->isSalePriceLockError($second)) {
                Log::info($this->label.' StartPrice succeeded after sale unlock', [
                    'sku' => $sku,
                    'unlocked' => $unlocked['unlocked'] ?? 0,
                ]);

                return $second;
            }

            return $this->removeReviseRestore($sku, $revise);
        } finally {
            $this->resumeSalesForSku($sku);
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function isSalePriceLockError(array $result): bool
    {
        $blob = strtolower((string) ($result['message'] ?? ''));
        $errors = $result['errors'] ?? [];
        if ($errors === [] && is_array($result['data']['Errors'] ?? null)) {
            $errors = $result['data']['Errors'];
        }
        if (! is_array($errors)) {
            $errors = [$errors];
        } elseif ($errors !== [] && ! isset($errors[0])) {
            $errors = [$errors];
        }

        foreach ($errors as $error) {
            if (! is_array($error)) {
                $blob .= ' '.strtolower((string) $error);

                continue;
            }
            $blob .= ' '.strtolower((string) ($error['message'] ?? ''));
            $blob .= ' '.strtolower((string) ($error['LongMessage'] ?? ''));
            $blob .= ' '.strtolower((string) ($error['ShortMessage'] ?? ''));
            $blob .= ' '.(string) ($error['ErrorCode'] ?? $error['code'] ?? '');
            $params = $error['ErrorParameters'] ?? [];
            if (is_array($params)) {
                foreach ($params as $param) {
                    if (is_array($param) && isset($param['Value'])) {
                        $blob .= ' '.strtolower((string) $param['Value']);
                    }
                }
            }
        }

        return str_contains($blob, 'part of a sale')
            || str_contains($blob, 'always allow price updates')
            || str_contains($blob, 'blockpriceincrease')
            || str_contains($blob, '21919248');
    }

    /**
     * @return array{success:bool,unlocked:int,message:string}
     */
    public function unlockPriceUpdatesForSku(string $sku, bool $pauseIfNeeded = false, bool $resumeAfter = true): array
    {
        $sku = trim($sku);
        $metric = $this->findMetric($sku);
        $itemId = $metric?->item_id ? trim((string) $metric->item_id) : '';
        if ($itemId === '') {
            return ['success' => false, 'unlocked' => 0, 'message' => $this->label.' item_id not found for SKU'];
        }

        $dv = $this->findOrNewDataView($sku);
        $val = is_array($dv->value) ? $dv->value : [];
        $storedPromoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';
        $apiSku = trim((string) ($metric->sku ?: $sku));

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'unlocked' => 0, 'message' => $this->label.' token: '.$e->getMessage()];
        }

        $memberships = $this->salesContainingSku($token, $apiSku, $itemId, $storedPromoId);
        $canPause = $pauseIfNeeded;
        if ($memberships === []) {
            // Unknown membership — try the flag on locked sales, but do not
            // pause every Hub campaign just to find this SKU.
            $memberships = $this->salesBlockingPriceRevisions($token);
            $canPause = false;
        }

        $unlocked = 0;
        foreach ($memberships as $row) {
            if ($this->tryUnlockSale($token, (string) $row['promotion_id'], $canPause, $resumeAfter)) {
                $unlocked++;
            }
        }

        return [
            'success' => $unlocked > 0,
            'unlocked' => $unlocked,
            'message' => $unlocked > 0
                ? ('Unlocked '.$unlocked.' '.$this->label.' sale event'.($unlocked > 1 ? 's' : ''))
                : ('No '.$this->label.' sale event could be unlocked'),
        ];
    }

    private function resumeSalesForSku(string $sku): void
    {
        $sku = trim($sku);
        $metric = $this->findMetric($sku);
        $itemId = $metric?->item_id ? trim((string) $metric->item_id) : '';
        if ($itemId === '') {
            return;
        }

        $dv = $this->findOrNewDataView($sku);
        $val = is_array($dv->value) ? $dv->value : [];
        $storedPromoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';
        $apiSku = trim((string) ($metric->sku ?: $sku));

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable) {
            return;
        }

        foreach ($this->salesContainingSku($token, $apiSku, $itemId, $storedPromoId) as $row) {
            $this->resumeIfNeeded($token, (string) $row['promotion_id']);
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $revise
     * @return array<string, mixed>
     */
    private function removeReviseRestore(string $sku, callable $revise): array
    {
        $sku = trim($sku);
        $metric = $this->findMetric($sku);
        $itemId = $metric?->item_id ? trim((string) $metric->item_id) : '';
        if ($itemId === '') {
            return [
                'success' => false,
                'message' => $this->label.' item_id not found for SKU',
                'errors' => [['code' => 'NotFound', 'message' => $this->label.' item_id not found for SKU']],
            ];
        }

        $dv = $this->findOrNewDataView($sku);
        $val = is_array($dv->value) ? $dv->value : [];
        $storedPromoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';
        $apiSku = trim((string) ($metric->sku ?: $sku));

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $this->label.' token: '.$e->getMessage(),
                'errors' => [['code' => 'AuthError', 'message' => $this->label.' token: '.$e->getMessage()]],
            ];
        }

        $memberships = $this->salesContainingSku($token, $apiSku, $itemId, $storedPromoId);
        if ($memberships === []) {
            $this->detachListingIdFromSales($token, $itemId);
            $memberships = $this->salesContainingSku($token, $apiSku, $itemId, $storedPromoId);
        }

        $removed = [];
        foreach ($memberships as $row) {
            if (! empty($row['rule_based'])) {
                $this->tryUnlockSale($token, (string) $row['promotion_id'], true);

                continue;
            }
            $detail = is_array($row['detail'] ?? null)
                ? $row['detail']
                : $this->getMarkdown($token, (string) $row['promotion_id']);
            if (! is_array($detail)) {
                continue;
            }
            $rm = $this->removeListingFromSale(
                $token,
                (string) $row['promotion_id'],
                $itemId,
                $apiSku,
                $detail,
                false
            );
            if (empty($rm['success'])) {
                Log::warning($this->label.' sale remove-before-revise failed', [
                    'sku' => $sku,
                    'promotion_id' => $row['promotion_id'] ?? null,
                    'error' => $rm['message'] ?? '',
                ]);

                continue;
            }

            $check = $this->getMarkdown($token, (string) $row['promotion_id']);
            if (is_array($check) && $this->saleContainsSku($check, $apiSku, $itemId)) {
                Log::warning($this->label.' sale still contains SKU after remove — retry detach', [
                    'sku' => $sku,
                    'promotion_id' => $row['promotion_id'] ?? null,
                ]);
                $this->detachListingIdFromSales($token, $itemId);
                $check = $this->getMarkdown($token, (string) $row['promotion_id']);
                if (is_array($check) && $this->saleContainsSku($check, $apiSku, $itemId)) {
                    continue;
                }
            }

            $removed[] = $row;
        }

        if ($removed !== []) {
            usleep(1500000);
        }

        try {
            return $revise();
        } finally {
            foreach ($removed as $row) {
                $promoId = (string) $row['promotion_id'];
                $added = $this->addSkuToSale($token, $promoId, $apiSku, $itemId);
                if (! empty($added['success'])) {
                    $this->tryUnlockSale($token, $promoId, false);
                    $this->resumeIfNeeded($token, $promoId);

                    continue;
                }
                $pct = $row['percent'] ?? null;
                Log::warning($this->label.' sale restore after price revise failed', [
                    'sku' => $sku,
                    'promotion_id' => $promoId,
                    'error' => $added['message'] ?? '',
                ]);
                if (is_numeric($pct) && (int) $pct >= 5) {
                    $this->syncSkuPromotionPercent($sku, (float) $pct);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function reviseSucceeded(array $result): bool
    {
        return ! empty($result['success']);
    }

    private function tryUnlockSale(string $token, string $promoId, bool $pauseIfNeeded, bool $resumeAfter = true): bool
    {
        $promoId = trim($promoId);
        if ($promoId === '') {
            return false;
        }

        $detail = $this->getMarkdown($token, $promoId);
        if ($detail === null) {
            return false;
        }
        $wasRunning = strtoupper((string) ($detail['promotionStatus'] ?? '')) === 'RUNNING';
        if (! $this->saleBlocksPriceRevision($detail) && ! $pauseIfNeeded) {
            return true;
        }

        if ($wasRunning && $pauseIfNeeded) {
            if (! $this->ensureSalePaused($token, $promoId)) {
                Log::warning($this->label.' sale unlock skipped — could not pause RUNNING sale', [
                    'promotion_id' => $promoId,
                ]);

                return false;
            }
            $detail = $this->getMarkdown($token, $promoId) ?? $detail;
        }

        $items = $this->saleInventoryItems($detail);
        $payload = $this->saleWritePayload(
            $detail,
            $this->saleListingIds($detail),
            $items !== [] ? $items : null
        );
        $payload['blockPriceIncreaseInItemRevision'] = false;

        $resp = $this->putMarkdown($token, $promoId, $payload);
        $ok = $resp->successful() || $resp->status() === 204;
        if (! $ok) {
            Log::warning($this->label.' sale unlock PUT failed', [
                'promotion_id' => $promoId,
                'http' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 800),
            ]);
            if ($wasRunning && $pauseIfNeeded && $resumeAfter) {
                $this->resumeIfNeeded($token, $promoId);
            }

            return false;
        }

        $check = $this->getMarkdown($token, $promoId);
        $unlocked = is_array($check) ? ! $this->saleBlocksPriceRevision($check) : $ok;
        Log::info($this->label.' sale unlock PUT', [
            'promotion_id' => $promoId,
            'unlocked' => $unlocked,
            'status' => $check['promotionStatus'] ?? null,
            'block' => $check['blockPriceIncreaseInItemRevision'] ?? null,
        ]);

        if ($resumeAfter && $wasRunning) {
            $this->resumeIfNeeded($token, $promoId);
        }

        return $unlocked;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function saleBlocksPriceRevision(array $detail): bool
    {
        if (! array_key_exists('blockPriceIncreaseInItemRevision', $detail)) {
            return true;
        }
        $value = $detail['blockPriceIncreaseInItemRevision'];
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return ! in_array(strtolower($value), ['false', '0', 'no'], true);
        }

        return (bool) $value;
    }

    /**
     * @return list<array{promotion_id:string,percent:?int,rule_based:bool,detail:array<string, mixed>}>
     */
    private function salesContainingSku(string $token, string $sku, string $itemId, string $storedPromoId = ''): array
    {
        $candidates = $this->listMarkdownIds($token);
        if ($storedPromoId !== '' && ! in_array($storedPromoId, $candidates, true)) {
            array_unshift($candidates, $storedPromoId);
        }

        $found = [];
        foreach ($candidates as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED', 'DRAFT'], true)) {
                continue;
            }
            if (! $this->saleContainsSku($detail, $sku, $itemId)) {
                continue;
            }
            $found[] = [
                'promotion_id' => $promoId,
                'percent' => $this->salePercent($detail),
                'rule_based' => $this->saleIsRuleBased($detail),
                'detail' => $detail,
            ];
        }

        return $found;
    }

    /**
     * @return list<array{promotion_id:string,percent:?int,rule_based:bool,detail:array<string, mixed>}>
     */
    private function salesBlockingPriceRevisions(string $token): array
    {
        $found = [];
        foreach ($this->listMarkdownIds($token) as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED'], true)) {
                continue;
            }
            if (! $this->saleBlocksPriceRevision($detail)) {
                continue;
            }
            $found[] = [
                'promotion_id' => $promoId,
                'percent' => $this->salePercent($detail),
                'rule_based' => $this->saleIsRuleBased($detail),
                'detail' => $detail,
            ];
        }

        return $found;
    }

    /**
     * End every active markdown sale (RUNNING / SCHEDULED / PAUSED) by setting
     * endDate to now. RUNNING sales only accept inventory + endDate.
     *
     * @return array{success:bool,ended:int,failed:int,skipped:int,errors:list<string>}
     */
    public function endAllSales(): array
    {
        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'ended' => 0,
                'failed' => 1,
                'skipped' => 0,
                'errors' => [$this->label.' token: '.$e->getMessage()],
            ];
        }

        $ended = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        foreach ($this->listMarkdownIds($token) as $promoId) {
            $res = $this->endMarkdownSale($token, $promoId);
            if (! empty($res['skipped'])) {
                $skipped++;

                continue;
            }
            if (! empty($res['success'])) {
                $ended++;
            } else {
                $failed++;
                $errors[] = $promoId.': '.((string) ($res['message'] ?? 'end failed'));
            }
        }

        return [
            'success' => $failed === 0,
            'ended' => $ended,
            'failed' => $failed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{success:bool,message:string,skipped?:bool}
     */
    private function endMarkdownSale(string $token, string $promoId): array
    {
        $detail = $this->getMarkdown($token, $promoId);
        if ($detail === null) {
            return ['success' => false, 'message' => 'Sale not found'];
        }
        $status = strtoupper((string) ($detail['promotionStatus'] ?? ''));
        if (in_array($status, ['ENDED', 'EXPIRED'], true)) {
            return ['success' => true, 'message' => 'already ended', 'skipped' => true];
        }

        if (in_array($status, ['DRAFT', 'SCHEDULED'], true)) {
            $del = $this->http($token)->delete(
                'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($this->markdownApiId($promoId))
            );
            $gone = $this->getMarkdown($token, $promoId);
            $goneStatus = strtoupper((string) ($gone['promotionStatus'] ?? ''));
            if ($del->successful() || $gone === null || in_array($goneStatus, ['ENDED', 'EXPIRED'], true)) {
                Log::info($this->label.' sale delete', [
                    'promotion_id' => $promoId,
                    'http' => $del->status(),
                    'status' => $goneStatus ?: $status,
                ]);

                return ['success' => true, 'message' => 'deleted'];
            }
        }

        $startAt = strtotime((string) ($detail['startDate'] ?? '')) ?: time();
        $minEnd = $startAt + 86400 + 120;
        $endTs = max(time() + 120, $minEnd);
        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);
        $payload = $this->runningInventoryWritePayload(
            $detail,
            $listingIds,
            $listingIds === [] ? ($items !== [] ? $items : null) : null
        );
        $payload['endDate'] = gmdate('Y-m-d\TH:i:s.000\Z', $endTs);
        $resp = $this->putMarkdown($token, $promoId, $payload);
        $ok = $resp->successful() || $resp->status() === 204;
        $check = $this->getMarkdown($token, $promoId) ?? $detail;
        $nowStatus = strtoupper((string) ($check['promotionStatus'] ?? ''));
        $endAt = strtotime((string) ($check['endDate'] ?? '')) ?: 0;
        $endingSoon = $ok && $endAt > 0 && $endAt <= time() + 180;
        $scheduledEnd = $ok && $endAt > 0 && $endAt <= $minEnd + 300;
        $ended = $check === null
            || in_array($nowStatus, ['ENDED', 'EXPIRED'], true)
            || ($endAt > 0 && $endAt <= time() + 90)
            || $endingSoon
            || $scheduledEnd;

        Log::info($this->label.' sale end PUT', [
            'promotion_id' => $promoId,
            'http' => $resp->status(),
            'status' => $nowStatus ?: $status,
            'ended' => $ended,
            'end_date' => $check['endDate'] ?? null,
            'body' => mb_substr((string) $resp->body(), 0, 400),
        ]);

        if ($ended) {
            return [
                'success' => true,
                'message' => ($endingSoon || in_array($nowStatus, ['ENDED', 'EXPIRED'], true))
                    ? 'ended'
                    : ('scheduled to end '.((string) ($check['endDate'] ?? ''))),
            ];
        }

        return [
            'success' => false,
            'message' => $ok
                ? ('eBay kept sale '.$nowStatus)
                : ('End failed: '.$this->ebayErrorMessage($resp)),
        ];
    }

    /**
     * RUNNING markdowns younger than 24h cannot change endDate. Remove every
     * listing via markupListingIds so prices revert immediately.
     *
     * @param  array<string, mixed>  $detail
     * @return array{success:bool,message:string}
     */
    private function drainMarkdownSale(string $token, string $promoId, array $detail): array
    {
        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);
        if ($listingIds === [] && $items === []) {
            return ['success' => true, 'message' => 'already empty'];
        }

        $left = 0;
        $lastMsg = 'drain failed';
        foreach (array_chunk($listingIds !== [] ? $listingIds : [null], 500) as $idChunk) {
            $idChunk = array_values(array_filter($idChunk, fn ($id) => $id !== null && $id !== ''));
            $itemChunk = $listingIds === [] ? $items : [];
            $res = $this->markupListingsOnSale($token, $promoId, $detail, $idChunk, $itemChunk);
            $lastMsg = (string) ($res['message'] ?? $lastMsg);
            $check = $this->getMarkdown($token, $promoId);
            if (! is_array($check)) {
                return ['success' => true, 'message' => 'sale gone'];
            }
            $detail = $check;
            $listingIds = $this->saleListingIds($detail);
            $items = $this->saleInventoryItems($detail);
            $left = count($listingIds) + count($items);
            if ($left === 0) {
                return ['success' => true, 'message' => 'listings removed'];
            }
        }

        if ($items !== []) {
            $res = $this->markupListingsOnSale($token, $promoId, $detail, [], $items);
            $lastMsg = (string) ($res['message'] ?? $lastMsg);
            $check = $this->getMarkdown($token, $promoId);
            $left = is_array($check)
                ? count($this->saleListingIds($check)) + count($this->saleInventoryItems($check))
                : 0;
            if ($left === 0) {
                return ['success' => true, 'message' => 'listings removed'];
            }
        }

        return [
            'success' => $left === 0,
            'message' => $left === 0 ? 'listings removed' : ($lastMsg.' ('.$left.' still on sale)'),
        ];
    }

    /**
     * @param  list<string>  $listingIds
     * @param  list<array<string, mixed>>  $inventoryItems
     * @param  array<string, mixed>  $detail
     * @return array{success:bool,message:string}
     */
    private function markupListingsOnSale(
        string $token,
        string $promoId,
        array $detail,
        array $listingIds,
        array $inventoryItems
    ): array {
        $currentIds = $this->saleListingIds($detail);
        $currentItems = $this->saleInventoryItems($detail);
        $wantIds = array_fill_keys(array_map(
            fn ($id) => $this->markdownListingId((string) $id),
            $listingIds
        ), true);
        $wantSkus = [];
        foreach ($inventoryItems as $it) {
            $sku = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
            if ($sku !== '') {
                $wantSkus[$sku] = true;
            }
        }

        $keptIds = [];
        foreach ($currentIds as $lid) {
            $norm = $this->markdownListingId((string) $lid);
            if ($norm !== '' && ! isset($wantIds[$norm])) {
                $keptIds[] = $norm;
            }
        }
        $keptItems = [];
        foreach ($currentItems as $it) {
            $sku = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
            if ($sku !== '' && ! isset($wantSkus[$sku])) {
                $keptItems[] = $it;
            }
        }

        $preferListingIds = $currentIds !== [] || $listingIds !== [];
        $payload = $this->runningInventoryWritePayload(
            $detail,
            $preferListingIds ? $keptIds : [],
            $preferListingIds ? null : ($keptItems !== [] ? $keptItems : null)
        );
        if ($keptIds === [] && $keptItems === []) {
            $payload['selectedInventoryDiscounts'][0]['inventoryCriterion'] = [
                'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
                'listingIds' => [],
            ];
        }
        // Official update of a RUNNING markdown must send SCHEDULED or eBay
        // accepts 200 and ignores listingIds.
        $payload['promotionStatus'] = 'SCHEDULED';
        $resp = $this->putMarkdown($token, $promoId, $payload);
        Log::info($this->label.' sale inventory replace PUT', [
            'promotion_id' => $promoId,
            'http' => $resp->status(),
            'kept_listings' => count($keptIds),
            'kept_items' => count($keptItems),
            'body' => mb_substr((string) $resp->body(), 0, 400),
        ]);
        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'inventory replace accepted'];
        }

        return ['success' => false, 'message' => 'Inventory replace failed: '.$this->ebayErrorMessage($resp)];
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
        Model $dv,
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
            if (! $this->saleContainsSku($detail, $sku, $itemId)) {
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
                ? ('SKU not on any active '.$this->label.' sale event')
                : ('SKU removed from '.$this->label.' sale event'.($foundOn > 1 ? 's' : '')),
            'promotion_id' => $removedFrom[0] ?? null,
            'paused' => true,
        ];
    }

    /**
     * Pick the seller's sale event at this PRMT%. Prefer the RUNNING campaign
     * with the most items (the Hub sale), not a tiny PEF-created duplicate.
     *
     * @return array{promotion_id:string,already_has_sku:bool}|null
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
            $endAt = strtotime((string) ($detail['endDate'] ?? '')) ?: 0;
            if ($endAt > 0 && $endAt <= time()) {
                continue;
            }
            if ($this->salePercent($detail) !== $pctInt) {
                continue;
            }
            if ($this->saleIsRuleBased($detail)) {
                continue;
            }

            $has = $this->saleContainsSku($detail, $sku, $itemId);
            $count = count($this->saleInventoryItems($detail));
            $score = ($status === 'RUNNING' ? 100000 : ($status === 'SCHEDULED' ? 10000 : 0)) + $count;
            $entry = [
                'promotion_id' => $promoId,
                'already_has_sku' => $has,
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
            ->get('https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($this->markdownApiId($promoId)));
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
        $listingId = $this->markdownListingId($itemId);
        $payload = $this->markdownPayload(
            $pctInt,
            $imageUrl,
            $listingId !== '' ? [$listingId] : [],
            null,
            null,
            null,
            null
        );
        $resp = $this->http($token)
            ->post('https://api.ebay.com/sell/marketing/v1/item_price_markdown', $payload);

        if (! $resp->successful() && $resp->status() !== 201) {
            Log::error('eBay1 sale event create failed', [
                'sku' => $sku,
                'item_id' => $itemId,
                'status' => $resp->status(),
                'body' => mb_substr($resp->body(), 0, 800),
            ]);
            $msg = $this->ebayErrorMessage($resp);

            return [
                'success' => false,
                'message' => 'Create failed: '.$msg,
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
        if ($this->saleContainsSku($detail, $sku, $itemId)) {
            return ['success' => true, 'message' => 'already present'];
        }

        $pct = $this->salePercent($detail);
        if ($pct === null) {
            return ['success' => false, 'message' => 'Sale event has no discount %'];
        }

        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);
        $lid = $this->markdownListingId($itemId);

        if ($this->saleIsRuleBased($detail) && $listingIds === [] && $items === []) {
            return ['success' => false, 'message' => 'Sale is rule-based (cannot add a single SKU)'];
        }
        if ($lid === '') {
            return ['success' => false, 'message' => 'Listing id required to add variation listing'];
        }
        if (! in_array($lid, array_map(fn ($id) => $this->markdownListingId((string) $id), $listingIds), true)) {
            $listingIds[] = $lid;
        }

        $payload = $this->saleWritePayload($detail, $listingIds, null);

        $wasRunning = strtoupper((string) ($detail['promotionStatus'] ?? '')) === 'RUNNING';
        $resp = $this->putMarkdown($token, $promoId, $payload);

        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'add item ok'];
        }

        if ($wasRunning) {
            $this->pauseSale($token, $promoId);
            $pausedDetail = $this->getMarkdown($token, $promoId) ?? $detail;
            $payload = $this->saleWritePayload($pausedDetail, $listingIds, null);
            $resp = $this->putMarkdown($token, $promoId, $payload);
            if ($resp->successful() || $resp->status() === 204) {
                $this->resumeIfNeeded($token, $promoId);

                return ['success' => true, 'message' => 'add item ok'];
            }
            $this->resumeIfNeeded($token, $promoId);
        }

        Log::error('eBay1 sale event add item failed', [
            'promotion_id' => $promoId,
            'sku' => $sku,
            'item_id' => $itemId,
            'status' => $resp->status(),
            'body' => mb_substr($resp->body(), 0, 500),
        ]);
        $msg = $this->ebayErrorMessage($resp);
        if ($this->isVariationMarkdownError($resp)) {
            $msg = 'eBay rejected variation SKU '.$sku.'. '.$msg;
        }

        return ['success' => false, 'message' => 'Add item failed: '.$msg];
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
        array $detail,
        bool $resumeAfter = true
    ): array {
        $listingIds = $this->saleListingIds($detail);
        $items = $this->saleInventoryItems($detail);
        $wantSku = strtoupper(trim($sku));
        $wantLid = $this->markdownListingId($itemId);

        $removeIds = [];
        $keptIds = [];
        foreach ($listingIds as $lid) {
            $lid = trim((string) $lid);
            if ($lid === '') {
                continue;
            }
            if ($wantLid !== '' && $this->markdownListingId($lid) === $wantLid) {
                $removeIds[] = $lid;
            } else {
                $keptIds[] = $lid;
            }
        }
        $removeItems = [];
        $keptItems = [];
        foreach ($items as $it) {
            $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
            if ($id !== '' && $wantSku !== '' && $id === $wantSku) {
                $removeItems[] = $it;
            } else {
                $keptItems[] = $it;
            }
        }

        if ($removeIds === [] && $removeItems === []) {
            return ['success' => true, 'message' => 'SKU not on this sale'];
        }
        if ($keptIds === [] && $keptItems === []) {
            return [
                'success' => false,
                'message' => 'eBay will not empty a sale (needs at least 1 listing). Sale ends '
                    .((string) ($detail['endDate'] ?? 'after 24h')),
            ];
        }

        $res = $this->markupListingsOnSale($token, $promoId, $detail, $removeIds, $removeItems);
        $check = $this->getMarkdown($token, $promoId);
        $stillOn = is_array($check) && $this->saleContainsSku($check, $sku, $itemId);
        Log::info($this->label.' sale remove via inventory replace', [
            'promotion_id' => $promoId,
            'still_on_sale' => $stillOn,
            'kept_listings' => count($keptIds),
            'message' => $res['message'] ?? null,
        ]);
        if ($stillOn) {
            return ['success' => false, 'message' => (string) ($res['message'] ?? 'eBay ignored listing remove')];
        }
        if ($resumeAfter) {
            $this->resumeIfNeeded($token, $promoId);
        }

        return ['success' => true, 'message' => 'remove item ok'];
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

        [$start, $end] = $this->saleDateWindow($detail);
        $status = strtoupper((string) ($detail['promotionStatus'] ?? ''));

        $payload = [
            'name' => (string) ($detail['name'] ?? $this->campaignNameForPercent($pct)),
            'description' => $this->clipDescription($desc),
            'marketplaceId' => (string) ($detail['marketplaceId'] ?? self::MARKETPLACE),
            'startDate' => $start,
            'endDate' => $end,
            // RUNNING inventory PUTs are ignored unless the sale is PAUSED first.
            // Sending SCHEDULED for a paused sale also makes eBay ignore listingIds.
            'promotionStatus' => match ($status) {
                'RUNNING' => 'RUNNING',
                'PAUSED' => 'PAUSED',
                default => 'SCHEDULED',
            },
        ];

        foreach (['promotionImageUrl', 'applyFreeShipping', 'autoSelectFutureInventory', 'priority'] as $key) {
            if (array_key_exists($key, $detail) && $detail[$key] !== null && $detail[$key] !== '') {
                $payload[$key] = $detail[$key];
            }
        }

        // Non-running writes always allow StartPrice revises. RUNNING keeps the
        // current flag so inventory-only PUTs are not rejected by eBay.
        if ($status === 'RUNNING' && array_key_exists('blockPriceIncreaseInItemRevision', $detail)
            && $detail['blockPriceIncreaseInItemRevision'] !== null && $detail['blockPriceIncreaseInItemRevision'] !== '') {
            $payload['blockPriceIncreaseInItemRevision'] = $detail['blockPriceIncreaseInItemRevision'];
        } else {
            $payload['blockPriceIncreaseInItemRevision'] = false;
        }

        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        $first = is_array($discounts[0] ?? null) ? $discounts[0] : [];
        $benefit = is_array($first['discountBenefit'] ?? null)
            ? $first['discountBenefit']
            : ['percentageOffItem' => (string) $pct];

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        // eBay rejects listingIds and inventoryItems in the same request.
        if ($listingIds !== []) {
            $criterion['listingIds'] = array_values(array_map(
                fn ($id) => $this->markdownListingId((string) $id),
                $listingIds
            ));
        } elseif ($inventoryItems !== null && $inventoryItems !== []) {
            $criterion['inventoryItems'] = array_values($inventoryItems);
        } else {
            $criterion['listingIds'] = [];
        }

        $discount = [
            'discountBenefit' => $benefit,
            'inventoryCriterion' => $criterion,
        ];
        $discountId = trim((string) ($first['discountId'] ?? $first['discount_id'] ?? ''));
        if ($discountId !== '') {
            $discount['discountId'] = $discountId;
        }
        if (array_key_exists('ruleOrder', $first) && $first['ruleOrder'] !== null && $first['ruleOrder'] !== '') {
            $discount['ruleOrder'] = $first['ruleOrder'];
        }

        $payload['selectedInventoryDiscounts'] = [$discount];

        return $payload;
    }

    /**
     * Hub "entire store / category" markdowns use INVENTORY_BY_RULE.
     * Those discounts cannot take listingIds ("The discount ID is invalid").
     *
     * @param  array<string, mixed>  $detail
     */
    private function saleIsRuleBased(array $detail): bool
    {
        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        if (! is_array($discounts)) {
            return false;
        }
        foreach ($discounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $crit = is_array($row['inventoryCriterion'] ?? null) ? $row['inventoryCriterion'] : [];
            $type = strtoupper((string) ($crit['inventoryCriterionType'] ?? ''));
            if ($type === 'INVENTORY_BY_RULE' || ! empty($crit['ruleCriteria'])) {
                return true;
            }
        }

        return false;
    }

    private function markdownListingId(string $itemId): string
    {
        $id = trim($itemId);
        if (preg_match('/^v1\\|([^|]+)/', $id, $m)) {
            return trim((string) $m[1]);
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function putMarkdown(string $token, string $promoId, array $payload): Response
    {
        return $this->http($token)
            ->put(
                'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($this->markdownApiId($promoId)),
                $payload
            );
    }

    private function markdownApiId(string $promoId): string
    {
        $id = trim($promoId);
        if ($id !== '' && ! str_contains($id, '@')) {
            return $id.'@'.self::MARKETPLACE;
        }

        return $id;
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
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @param  array<string, mixed>|null  $existingDetail
     * @return array<string, mixed>
     */
    private function markdownPayload(
        int $pctInt,
        string $imageUrl,
        array $listingIds,
        ?string $startDate,
        ?string $endDate,
        ?array $existingDetail,
        ?array $inventoryItems = null
    ): array {
        [$start, $end] = $this->saleDateWindow($existingDetail);

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        if ($inventoryItems !== null && $inventoryItems !== []) {
            $criterion['inventoryItems'] = array_values($inventoryItems);
        } else {
            $criterion['listingIds'] = array_values(array_map(
                fn ($id) => $this->markdownListingId((string) $id),
                $listingIds
            ));
        }

        $payload = [
            'name' => $this->campaignNameForPercent($pctInt),
            'description' => $this->clipDescription('PEF SALE '.$pctInt.'%'),
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionImageUrl' => $imageUrl,
            'blockPriceIncreaseInItemRevision' => false,
            'selectedInventoryDiscounts' => [
                [
                    'discountBenefit' => [
                        'percentageOffItem' => (string) $pctInt,
                    ],
                    'inventoryCriterion' => $criterion,
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
     * Listing is on the sale via inventory SKU or shared variation listing ID.
     *
     * @param  array<string, mixed>  $detail
     */
    private function saleContainsSku(array $detail, string $sku, string $itemId = ''): bool
    {
        $want = strtoupper(trim($sku));
        if ($want !== '') {
            foreach ($this->saleInventoryItems($detail) as $it) {
                $id = strtoupper(trim((string) ($it['inventoryReferenceId'] ?? '')));
                if ($id !== '' && $id === $want) {
                    return true;
                }
            }
        }
        $lid = $this->markdownListingId($itemId);
        if ($lid === '') {
            return false;
        }
        foreach ($this->saleListingIds($detail) as $existing) {
            if ($this->markdownListingId((string) $existing) === $lid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull a variation listing off listing-ID sales so leftover children (PRMT 0) are not discounted.
     */
    private function detachListingIdFromSales(string $token, string $itemId): void
    {
        $itemId = $this->markdownListingId($itemId);
        if ($itemId === '' || isset($this->detachedListingIds[$itemId])) {
            return;
        }
        $this->detachedListingIds[$itemId] = true;
        foreach ($this->listMarkdownIds($token) as $promoId) {
            $detail = $this->getMarkdown($token, $promoId);
            if ($detail === null) {
                continue;
            }
            $status = (string) ($detail['promotionStatus'] ?? '');
            if (! in_array($status, ['RUNNING', 'SCHEDULED', 'PAUSED', 'DRAFT'], true)) {
                continue;
            }
            $onListing = false;
            foreach ($this->saleListingIds($detail) as $lid) {
                if ($this->markdownListingId((string) $lid) === $itemId) {
                    $onListing = true;
                    break;
                }
            }
            if (! $onListing) {
                continue;
            }
            $this->removeListingFromSale($token, $promoId, $itemId, '', $detail);
        }
    }

    private function isVariationMarkdownError(Response $resp): bool
    {
        $body = strtolower($this->ebayErrorMessage($resp).' '.$resp->body());

        return str_contains($body, 'listing with variations')
            || str_contains($body, 'parent, or main, sku')
            || str_contains($body, 'variation sku')
            || str_contains($body, '"errorid":38275')
            || str_contains($body, 'errorid":38275');
    }

    private function pauseSale(string $token, string $promoId): bool
    {
        return $this->ensureSalePaused($token, $promoId);
    }

    /**
     * Pause is only success when GET returns PAUSED. HTTP 400/409 is not enough —
     * those were treated as OK while PEF SALE 10% stayed RUNNING.
     */
    private function ensureSalePaused(string $token, string $promoId): bool
    {
        $detail = $this->getMarkdown($token, $promoId);
        $status = strtoupper((string) ($detail['promotionStatus'] ?? ''));
        if ($status === 'PAUSED') {
            return true;
        }

        $apiId = $this->markdownApiId($promoId);
        $urls = [
            'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($apiId).'/pause',
            'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($apiId).'/pause',
        ];

        foreach ($urls as $url) {
            $resp = $this->http($token)->post($url);
            Log::info($this->label.' sale pause', [
                'promotion_id' => $promoId,
                'http' => $resp->status(),
                'url' => $url,
                'body' => mb_substr((string) $resp->body(), 0, 400),
            ]);
            usleep(500000);
            $check = $this->getMarkdown($token, $promoId);
            $now = strtoupper((string) ($check['promotionStatus'] ?? ''));
            if ($now === 'PAUSED') {
                return true;
            }
        }

        Log::warning($this->label.' sale pause did not stick', [
            'promotion_id' => $promoId,
            'status' => $this->getMarkdown($token, $promoId)['promotionStatus'] ?? null,
        ]);

        return false;
    }

    /**
     * RUNNING markdowns only accept inventory / endDate. Sending
     * blockPriceIncreaseInItemRevision makes eBay return 200 and no-op.
     *
     * @param  list<string>  $listingIds
     * @param  list<array<string, mixed>>|null  $inventoryItems
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function runningInventoryWritePayload(array $detail, array $listingIds, ?array $inventoryItems): array
    {
        $pct = $this->salePercent($detail) ?? 10;
        $start = trim((string) ($detail['startDate'] ?? ''));
        $end = trim((string) ($detail['endDate'] ?? ''));
        if ($end === '') {
            [, $end] = $this->saleDateWindow($detail);
        }

        $discounts = $detail['selectedInventoryDiscounts'] ?? [];
        $first = is_array($discounts[0] ?? null) ? $discounts[0] : [];
        $benefit = is_array($first['discountBenefit'] ?? null)
            ? $first['discountBenefit']
            : ['percentageOffItem' => (string) $pct];

        $criterion = [
            'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
        ];
        // eBay rejects listingIds and inventoryItems in the same request.
        if ($listingIds !== []) {
            $criterion['listingIds'] = array_values(array_map(
                fn ($id) => $this->markdownListingId((string) $id),
                $listingIds
            ));
        } elseif ($inventoryItems !== null && $inventoryItems !== []) {
            $criterion['inventoryItems'] = array_values($inventoryItems);
        } else {
            $criterion['listingIds'] = [];
        }

        $discount = [
            'discountBenefit' => $benefit,
            'inventoryCriterion' => $criterion,
        ];
        $discountId = trim((string) ($first['discountId'] ?? $first['discount_id'] ?? ''));
        if ($discountId !== '') {
            $discount['discountId'] = $discountId;
        }
        if (array_key_exists('ruleOrder', $first) && $first['ruleOrder'] !== null && $first['ruleOrder'] !== '') {
            $discount['ruleOrder'] = $first['ruleOrder'];
        }

        $payload = [
            'name' => (string) ($detail['name'] ?? $this->campaignNameForPercent($pct)),
            'marketplaceId' => (string) ($detail['marketplaceId'] ?? self::MARKETPLACE),
            'promotionStatus' => 'RUNNING',
            'endDate' => $end,
            'selectedInventoryDiscounts' => [$discount],
        ];
        if ($start !== '') {
            $payload['startDate'] = $start;
        }
        $desc = trim((string) ($detail['description'] ?? ''));
        if ($desc !== '') {
            $payload['description'] = $this->clipDescription($desc);
        }
        $image = trim((string) ($detail['promotionImageUrl'] ?? ''));
        if ($image !== '') {
            $payload['promotionImageUrl'] = $image;
        }

        return $payload;
    }

    private function resumeIfNeeded(string $token, string $promoId): void
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/item_price_markdown/'.rawurlencode($this->markdownApiId($promoId)).'/resume';
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
    private function persistDv(Model $dv, array $val, string $promoId, int|float $pct): void
    {
        if ($promoId !== '') {
            $val[self::DV_PROMO_ID] = $promoId;
        } else {
            unset($val[self::DV_PROMO_ID]);
        }
        $val[self::DV_PRMT_PCT] = (float) $pct;
        $val[self::DV_SALE_PCT] = (float) $pct;
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

    /**
     * Start = now (eBay needs a start slightly in the future). End = 7 days later.
     * Running sales keep their original start (eBay rejects start changes).
     *
     * @param  array<string, mixed>|null  $existingDetail
     * @return array{0:string,1:string}
     */
    private function saleDateWindow(?array $existingDetail = null): array
    {
        $start = now('UTC')->addSeconds(30)->format('Y-m-d\TH:i:s.000\Z');
        $end = now('UTC')->addDays(self::DURATION_DAYS)->format('Y-m-d\TH:i:s.000\Z');

        $status = strtoupper((string) ($existingDetail['promotionStatus'] ?? ''));
        $existingStart = trim((string) ($existingDetail['startDate'] ?? ''));
        $existingEnd = trim((string) ($existingDetail['endDate'] ?? ''));
        if ($existingStart !== '' && in_array($status, ['RUNNING', 'PAUSED', 'SCHEDULED'], true)) {
            $start = $existingStart;
        }
        if ($existingEnd !== '') {
            $end = $existingEnd;
        }

        return [$start, $end];
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
