<?php

namespace App\Services;

use App\Models\EbayDataView;
use App\Models\EbayMetric;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * eBay1 threshold promotion sync via Sell Marketing API (item_promotion / ORDER_DISCOUNT).
 * Used by PEF PRMT % column: percent > 0 creates/updates; 0 pauses any promotion.
 */
class Ebay1PromotionService
{
    private const MARKETPLACE = 'EBAY_US';

    private const DV_PROMO_ID = 'PEF_PRMT_PROMOTION_ID';

    private const DV_PRMT_PCT = 'PEF_PRMT_PCT';

    public function __construct(
        private readonly EbayApiService $ebay
    ) {}

    /**
     * Sync PRMT % to eBay1 promotion for a SKU.
     * - percent <= 0 → pause any stored promotion
     * - percent > 0 → create or update ORDER_DISCOUNT % off (min qty 1)
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

        $dv = EbayDataView::query()->firstOrNew(['sku' => $sku]);
        $val = is_array($dv->value) ? $dv->value : [];
        $promoId = isset($val[self::DV_PROMO_ID]) ? trim((string) $val[self::DV_PROMO_ID]) : '';

        try {
            $token = $this->ebay->generateBearerToken();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'eBay1 token: '.$e->getMessage(), 'promotion_id' => $promoId ?: null];
        }

        if ($percent <= 0) {
            return $this->pausePromotion($token, $sku, $dv, $val, $promoId);
        }

        $pctInt = (int) round($percent);
        if ($pctInt < 5 || $pctInt > 80) {
            return [
                'success' => false,
                'message' => 'eBay promotion % must be 5–80 (or 0 to pause). Got '.$pctInt,
                'promotion_id' => $promoId ?: null,
            ];
        }

        if ($promoId !== '') {
            $updated = $this->updatePromotion($token, $promoId, $itemId, $sku, $pctInt);
            if ($updated['success']) {
                $this->resumeIfNeeded($token, $promoId);
                $this->persistDv($dv, $val, $promoId, $pctInt);

                return [
                    'success' => true,
                    'message' => 'eBay1 promotion updated to '.$pctInt.'%',
                    'promotion_id' => $promoId,
                    'percent' => (float) $pctInt,
                ];
            }
            Log::warning('eBay1 promotion update failed; creating new', [
                'sku' => $sku,
                'promotion_id' => $promoId,
                'error' => $updated['message'] ?? '',
            ]);
        }

        $created = $this->createPromotion($token, $itemId, $sku, $pctInt);
        if (! $created['success']) {
            return $created;
        }
        $newId = (string) ($created['promotion_id'] ?? '');
        $this->persistDv($dv, $val, $newId, $pctInt);

        return [
            'success' => true,
            'message' => 'eBay1 promotion created at '.$pctInt.'%',
            'promotion_id' => $newId,
            'percent' => (float) $pctInt,
        ];
    }

    /**
     * @param  array<string, mixed>  $val
     * @return array{success:bool,message:string,promotion_id:?string,paused?:bool}
     */
    private function pausePromotion(string $token, string $sku, EbayDataView $dv, array $val, string $promoId): array
    {
        if ($promoId === '') {
            $this->persistDv($dv, $val, '', 0);

            return [
                'success' => true,
                'message' => 'No active eBay1 promotion to pause',
                'promotion_id' => null,
                'paused' => true,
            ];
        }

        // Generic promotion pause endpoint (works for item_promotion ids)
        $url = 'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($promoId).'/pause';
        $resp = Http::withoutVerifying()
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Content-Language' => 'en-US',
            ])
            ->timeout(45)
            ->post($url);

        if ($resp->successful() || in_array($resp->status(), [204, 400, 409], true)) {
            $this->persistDv($dv, $val, $promoId, 0);

            return [
                'success' => true,
                'message' => 'eBay1 promotion paused',
                'promotion_id' => $promoId,
                'paused' => true,
            ];
        }

        Log::error('eBay1 promotion pause failed', [
            'sku' => $sku,
            'promotion_id' => $promoId,
            'status' => $resp->status(),
            'body' => $resp->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Pause failed: '.$this->ebayErrorMessage($resp),
            'promotion_id' => $promoId,
        ];
    }

    /**
     * @return array{success:bool,message:string,promotion_id:?string}
     */
    private function createPromotion(string $token, string $itemId, string $sku, int $pctInt): array
    {
        $payload = $this->promotionPayload($itemId, $sku, $pctInt);
        $resp = Http::withoutVerifying()
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Content-Language' => 'en-US',
            ])
            ->timeout(60)
            ->post('https://api.ebay.com/sell/marketing/v1/item_promotion', $payload);

        if (! $resp->successful() && $resp->status() !== 201) {
            Log::error('eBay1 promotion create failed', [
                'sku' => $sku,
                'status' => $resp->status(),
                'body' => $resp->body(),
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
                'message' => 'Create ok but promotion id missing from response',
                'promotion_id' => null,
            ];
        }

        return [
            'success' => true,
            'message' => 'created',
            'promotion_id' => $promoId,
        ];
    }

    /**
     * @return array{success:bool,message:string}
     */
    private function updatePromotion(string $token, string $promoId, string $itemId, string $sku, int $pctInt): array
    {
        $payload = $this->promotionPayload($itemId, $sku, $pctInt);
        $resp = Http::withoutVerifying()
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Content-Language' => 'en-US',
            ])
            ->timeout(60)
            ->put('https://api.ebay.com/sell/marketing/v1/item_promotion/'.rawurlencode($promoId), $payload);

        if ($resp->successful() || $resp->status() === 204) {
            return ['success' => true, 'message' => 'updated'];
        }

        return ['success' => false, 'message' => $this->ebayErrorMessage($resp)];
    }

    private function resumeIfNeeded(string $token, string $promoId): void
    {
        $url = 'https://api.ebay.com/sell/marketing/v1/promotion/'.rawurlencode($promoId).'/resume';
        try {
            Http::withoutVerifying()
                ->withToken($token)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json', 'Content-Language' => 'en-US'])
                ->timeout(30)
                ->post($url);
        } catch (\Throwable $e) {
            // ignore — may already be RUNNING
        }
    }

    /**
     * ORDER_DISCOUNT: buy 1+ get percentageOffItem.
     *
     * @return array<string, mixed>
     */
    private function promotionPayload(string $itemId, string $sku, int $pctInt): array
    {
        $start = now('UTC')->subMinute()->format('Y-m-d\TH:i:s.000\Z');
        $end = now('UTC')->addYear()->format('Y-m-d\TH:i:s.000\Z');
        $safeSku = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $sku) ?: 'SKU';

        return [
            'name' => 'PEF PRMT '.$safeSku,
            'description' => 'Pricing Errors Fix promotion % for '.$safeSku,
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'ORDER_DISCOUNT',
            'inventoryCriterion' => [
                'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
                'listingIds' => [$itemId],
            ],
            'discountRules' => [
                [
                    'discountSpecification' => [
                        'minQuantity' => 1,
                    ],
                    'discountBenefit' => [
                        'percentageOffItem' => (string) $pctInt,
                    ],
                    'ruleOrder' => 1,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $val
     */
    private function persistDv(EbayDataView $dv, array $val, string $promoId, int|float $pct): void
    {
        if ($promoId !== '') {
            $val[self::DV_PROMO_ID] = $promoId;
        }
        $val[self::DV_PRMT_PCT] = (float) $pct;
        $dv->value = $val;
        $dv->save();
    }

    private function extractPromotionId(\Illuminate\Http\Client\Response $resp): string
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

    private function ebayErrorMessage(\Illuminate\Http\Client\Response $resp): string
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
