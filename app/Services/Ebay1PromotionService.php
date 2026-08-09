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

    /** PEF promotions: start ASAP, end after 1 day. */
    private const DURATION_DAYS = 1;

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

        $dv = EbayDataView::query()->where('sku', $sku)->first()
            ?: EbayDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])->first()
            ?: new EbayDataView(['sku' => $sku]);
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

        $imageUrl = $this->resolvePromotionImageUrl($itemId);
        if ($imageUrl === '') {
            return [
                'success' => false,
                'message' => 'eBay listing image required for promotion (promotionImageUrl) — GetItem returned none',
                'promotion_id' => $promoId ?: null,
            ];
        }

        $apiSku = trim((string) ($metric->sku ?: $sku));

        if ($promoId !== '') {
            $updated = $this->updatePromotion($token, $promoId, $itemId, $apiSku, $pctInt, $imageUrl);
            if ($updated['success']) {
                $this->resumeIfNeeded($token, $promoId);
                $this->persistDv($dv, $val, $promoId, $pctInt);

                return [
                    'success' => true,
                    'message' => 'eBay1 promotion updated to '.$pctInt.'% (SKU '.$apiSku.')',
                    'promotion_id' => $promoId,
                    'percent' => (float) $pctInt,
                ];
            }
            Log::warning('eBay1 promotion update failed; creating new', [
                'sku' => $apiSku,
                'promotion_id' => $promoId,
                'error' => $updated['message'] ?? '',
            ]);
        }

        $created = $this->createPromotion($token, $itemId, $apiSku, $pctInt, $imageUrl);
        if (! $created['success']) {
            return $created;
        }
        $newId = (string) ($created['promotion_id'] ?? '');
        $this->persistDv($dv, $val, $newId, $pctInt);

        return [
            'success' => true,
            'message' => 'eBay1 promotion created at '.$pctInt.'% (SKU '.$apiSku.')',
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
    private function createPromotion(string $token, string $itemId, string $sku, int $pctInt, string $imageUrl): array
    {
        $payload = $this->promotionPayload($itemId, $sku, $pctInt, $imageUrl);
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
    private function updatePromotion(string $token, string $promoId, string $itemId, string $sku, int $pctInt, string $imageUrl): array
    {
        $payload = $this->promotionPayload($itemId, $sku, $pctInt, $imageUrl);
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
            // ignore — may already be RUNNING / SCHEDULED
        }
    }

    /**
     * ORDER_DISCOUNT: buy 1+ get percentageOffOrder (supported combo on EBAY_US).
     *
     * @return array<string, mixed>
     */
    private function promotionPayload(string $itemId, string $sku, int $pctInt, string $imageUrl): array
    {
        // eBay requires startDate later than "now" — use +30s so it starts almost immediately
        $start = now('UTC')->addSeconds(30)->format('Y-m-d\TH:i:s.000\Z');
        $end = now('UTC')->addDays(self::DURATION_DAYS)->format('Y-m-d\TH:i:s.000\Z');
        $safeSku = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $sku) ?: 'SKU';

        return [
            'name' => 'PEF PRMT '.$safeSku.' '.$pctInt.'% '.now('UTC')->format('mdHi'),
            'description' => 'Pricing Errors Fix promotion % for '.$safeSku,
            'marketplaceId' => self::MARKETPLACE,
            'startDate' => $start,
            'endDate' => $end,
            'promotionStatus' => 'SCHEDULED',
            'promotionType' => 'ORDER_DISCOUNT',
            'promotionImageUrl' => $imageUrl,
            // Attach by SKU so the promotion lists the SKU (not only listing id)
            'inventoryCriterion' => [
                'inventoryCriterionType' => 'INVENTORY_BY_VALUE',
                'inventoryItems' => [
                    [
                        'inventoryReferenceId' => $sku,
                    ],
                ],
            ],
            'discountRules' => [
                [
                    'discountSpecification' => [
                        'minQuantity' => 1,
                    ],
                    'discountBenefit' => [
                        // percentageOffItem + minQuantity is rejected (38241); percentageOffOrder works
                        'percentageOffOrder' => (string) $pctInt,
                    ],
                    'ruleOrder' => 1,
                ],
            ],
        ];
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
            Log::warning('eBay1 promotion image resolve failed', [
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
