<?php

namespace App\Support;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;

/**
 * Shopify views / price / Dil% for a Google Shopping campaign name.
 * Dil = shopify_skus.quantity (OV L30) ÷ inv × 100 — same formula as Amazon Ads.
 * Same SKU matching as the Shopping inventory column — not Amazon Ads rule data.
 */
final class GoogleShoppingBgtSkuMetrics
{
    /**
     * @return array{views_l7: float, views_l30: float, price: float|null, inv: float, ovl30: float, dil: float|null}
     */
    public static function empty(): array
    {
        return [
            'views_l7' => 0.0,
            'views_l30' => 0.0,
            'price' => null,
            'inv' => 0.0,
            'ovl30' => 0.0,
            'dil' => 0.0,
        ];
    }

    /**
     * @return \Closure(string): array{views_l7: float, views_l30: float, price: float|null, inv: float, ovl30: float, dil: float|null}
     */
    public static function resolver(): \Closure
    {
        $payload = Cache::remember('gads_shopping_bgt_sku_metrics_v4', 900, static function (): array {
            $allPm = ProductMaster::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->get(['sku', 'parent']);

            $childSkus = [];
            $childrenByFamily = [];
            $parentSkuToFamily = [];
            $normToSku = [];
            $skuToParentKey = [];
            foreach ($allPm as $pm) {
                $s = trim((string) ($pm->sku ?? ''));
                if ($s === '') {
                    continue;
                }
                $normSku = preg_replace('/\s+/', ' ', strtoupper(rtrim($s, '.')));
                if (str_starts_with(strtoupper($s), 'PARENT')) {
                    $parentCol = trim((string) ($pm->parent ?? ''));
                    if ($parentCol !== '') {
                        $parentSkuToFamily[$normSku] = preg_replace('/\s+/', ' ', strtoupper($parentCol));
                    } else {
                        $rest = trim(preg_replace('/^PARENT\s+/i', '', $s) ?? '');
                        $parentSkuToFamily[$normSku] = $rest === ''
                            ? $normSku
                            : preg_replace('/\s+/', ' ', strtoupper(rtrim($rest, '.')));
                    }

                    continue;
                }
                $childSkus[] = $s;
                if ($normSku !== '' && ! isset($normToSku[$normSku])) {
                    $normToSku[$normSku] = $s;
                }
                $fam = preg_replace('/\s+/', ' ', strtoupper(trim((string) ($pm->parent ?? ''))));
                if ($fam === '') {
                    continue;
                }
                $skuToParentKey[$normSku] = $fam;
                $childrenByFamily[$fam][] = $s;
            }

            $shopifyByPmSku = ShopifySku::mapByProductSkus(array_values(array_unique($childSkus)));

            $bySku = [];
            foreach ($childSkus as $sku) {
                $rec = $shopifyByPmSku->get($sku);
                $inv = (float) ($rec?->inv ?? 0);
                $ovl30 = (float) ($rec?->quantity ?? 0);
                $bySku[preg_replace('/\s+/', ' ', strtoupper(rtrim($sku, '.')))] = [
                    'views_l7' => (float) ($rec?->views_l7 ?? 0),
                    'views_l30' => (float) ($rec?->views ?? 0),
                    'price' => self::positivePrice($rec?->price ?? null),
                    'inv' => $inv,
                    'ovl30' => $ovl30,
                    'dil' => AmazonAdsCampaignSkuMetrics::tabulatorDil($inv, $ovl30),
                ];
            }

            $byFamily = [];
            foreach ($childrenByFamily as $fam => $kids) {
                $viewsL7 = 0.0;
                $viewsL30 = 0.0;
                $inv = 0.0;
                $ovl30 = 0.0;
                $priceSum = 0.0;
                $priceN = 0;
                foreach ($kids as $sku) {
                    $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim((string) $sku, '.')));
                    $m = $bySku[$norm] ?? null;
                    if ($m === null) {
                        continue;
                    }
                    $viewsL7 += (float) ($m['views_l7'] ?? 0);
                    $viewsL30 += (float) ($m['views_l30'] ?? 0);
                    $inv += (float) ($m['inv'] ?? 0);
                    $ovl30 += (float) ($m['ovl30'] ?? 0);
                    if ($m['price'] !== null) {
                        $priceSum += (float) $m['price'];
                        $priceN++;
                    }
                }
                $byFamily[$fam] = [
                    'views_l7' => $viewsL7,
                    'views_l30' => $viewsL30,
                    'price' => $priceN > 0 ? round($priceSum / $priceN, 2) : null,
                    'inv' => $inv,
                    'ovl30' => $ovl30,
                    'dil' => AmazonAdsCampaignSkuMetrics::tabulatorDil($inv, $ovl30),
                ];
            }

            return [
                'bySku' => $bySku,
                'byFamily' => $byFamily,
                'parentSkuToFamily' => $parentSkuToFamily,
                'skuToParentKey' => $skuToParentKey,
                'normToSku' => $normToSku,
            ];
        });

        $memo = [];

        return static function (string $campaignName) use ($payload, &$memo): array {
            $norm = preg_replace('/\s+/', ' ', strtoupper(rtrim(trim($campaignName), '.')));
            if ($norm === '') {
                return GoogleShoppingBgtSkuMetrics::empty();
            }
            if (array_key_exists($norm, $memo)) {
                return $memo[$norm];
            }

            if (str_starts_with($norm, 'PARENT ')) {
                $fam = $payload['parentSkuToFamily'][$norm]
                    ?? preg_replace('/\s+/', ' ', trim(substr($norm, strlen('PARENT '))));
                $out = $payload['byFamily'][$fam] ?? GoogleShoppingBgtSkuMetrics::empty();
                $memo[$norm] = $out;

                return $out;
            }

            if (isset($payload['bySku'][$norm])) {
                $memo[$norm] = $payload['bySku'][$norm];

                return $memo[$norm];
            }
            if (isset($payload['skuToParentKey'][$norm])) {
                $fam = $payload['skuToParentKey'][$norm];
                $out = $payload['byFamily'][$fam] ?? GoogleShoppingBgtSkuMetrics::empty();
                $memo[$norm] = $out;

                return $out;
            }

            $memo[$norm] = GoogleShoppingBgtSkuMetrics::empty();

            return $memo[$norm];
        };
    }

    private static function positivePrice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;

        return (is_finite($n) && $n > 0) ? $n : null;
    }
}
