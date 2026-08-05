<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Build Pricing Errors Fix cache from /price-increase CVR data (NOT channel pricing tabulators).
 *
 * Full rebuild: one getCvrDataJson(?for_pricing_errors_fix=1) → unpivot pef_channels.
 * Single SKU patch: getBreakdownData (modal) for that SKU only.
 */
class PricingErrorsFixCvrCacheBuilder
{
    /** Breakdown marketplace label → [pef marketplace, pull_key, channel_label] */
    private const MP_MAP = [
        'amazon' => ['amazon', 'amazon', 'Amazon'],
        'ebay1' => ['ebay1', 'ebay', 'eBay 1'],
        'ebay2' => ['ebay2', 'ebay2', 'eBay 2'],
        'ebay3' => ['ebay3', 'ebay3', 'eBay 3'],
        'temu' => ['temu', 'temu', 'Temu'],
        'temu2' => ['temu2', 'temu2', 'Temu 2'],
        'doba' => ['doba', 'doba', 'Doba'],
        'tiktok' => ['tiktok', 'tiktok', 'TikTok'],
        'tiktok2' => ['tiktok2', 'tiktok2', 'TikTok 2'],
        'bestbuy' => ['bestbuy', 'bestbuy', 'Best Buy'],
        'macy' => ['macy', 'macy', "Macy's"],
        'macys' => ['macy', 'macy', "Macy's"],
        'reverb' => ['reverb', 'reverb', 'Reverb'],
        'topdawg' => ['topdawg', 'topdawg', 'TopDawg'],
        'shopify' => ['sb2c', 'sb2c', 'Shopify B2C'],
        'sb2c' => ['sb2c', 'sb2c', 'Shopify B2C'],
        'sb2b' => ['sb2b', 'sb2b', 'Shopify B2B'],
        'ppower' => ['ppower', 'ppower', 'Purchasing Power'],
        'shein' => ['shein', 'shein', 'Shein'],
        'faire' => ['faire', 'faire', 'Faire'],
        'aliexpress' => ['aliexpress', 'aliexpress', 'AliExpress'],
    ];

    public function __construct(
        private readonly CvrMasterController $cvr
    ) {}

    /**
     * @param  array<int, string>|null  $wantedPullKeys  PEF registry keys (amazon, ebay, …); null = all mapped
     * @param  array<int, string>|null  $onlySkus
     * @param  callable|null  $onProgress  fn(int $done, int $total, string $sku): void
     * @return array{rows: array<int, array<string, mixed>>, errors: array<string, string>}
     */
    public function build(?array $wantedPullKeys = null, ?array $onlySkus = null, bool $listedOnly = true, ?callable $onProgress = null): array
    {
        if ($onlySkus !== null && $onlySkus !== []) {
            return $this->buildFromBreakdown($wantedPullKeys, $onlySkus, $listedOnly, $onProgress);
        }

        return $this->buildFromCvrDataJson($wantedPullKeys, $listedOnly, $onProgress);
    }

    /**
     * Fast path: same bulk source as /price-increase table.
     *
     * @param  array<int, string>|null  $wantedPullKeys
     * @param  callable|null  $onProgress
     * @return array{rows: array<int, array<string, mixed>>, errors: array<string, string>}
     */
    public function buildFromCvrDataJson(?array $wantedPullKeys = null, bool $listedOnly = true, ?callable $onProgress = null): array
    {
        $wantedPull = $wantedPullKeys !== null
            ? array_fill_keys(array_map('strtolower', $wantedPullKeys), true)
            : null;

        $rows = [];
        $errors = [];

        try {
            $resp = $this->cvr->getCvrDataJson(Request::create('/', 'GET', [
                'for_pricing_errors_fix' => 1,
            ]));
            $payload = json_decode($resp->getContent(), true);
            if (! is_array($payload) || isset($payload['error'])) {
                $errors['_cvr'] = is_array($payload)
                    ? (string) ($payload['error'] ?? 'cvr error')
                    : 'invalid cvr payload';

                return ['rows' => [], 'errors' => $errors];
            }

            $children = array_values(array_filter($payload, static function ($r) {
                if (! is_array($r)) {
                    return false;
                }

                return empty($r['is_parent_summary']);
            }));
            $total = count($children);
            $done = 0;

            foreach ($children as $row) {
                $sku = trim((string) ($row['sku'] ?? ''));
                $done++;
                if ($onProgress) {
                    $onProgress($done, $total, $sku !== '' ? $sku : '…');
                }
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }

                $inv = (float) ($row['inventory'] ?? 0);
                $ovL30 = (float) ($row['overall_l30'] ?? 0);
                $dil = isset($row['dil_percent'])
                    ? (float) $row['dil_percent']
                    : ($inv > 0 ? round(($ovL30 / $inv) * 100, 0) : 0.0);
                $parent = $row['parent'] ?? null;
                $image = $row['image_path'] ?? $row['sku_image'] ?? null;
                $channels = $row['pef_channels'] ?? null;
                if (! is_array($channels)) {
                    $errors[$sku] = 'missing pef_channels';
                    continue;
                }

                foreach ($channels as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mapped = $this->mapBreakdownItem($item, $sku, $parent, $inv, $ovL30, $dil, $image, $wantedPull);
                    if ($mapped === null) {
                        continue;
                    }
                    if ($listedOnly && ! ((float) ($mapped['price'] ?? 0) > 0) && ! ((float) ($mapped['sprice'] ?? 0) > 0)) {
                        continue;
                    }
                    $rows[] = $mapped;
                }
            }
        } catch (\Throwable $e) {
            $errors['_cvr'] = $e->getMessage();
            Log::error('PEF CVR bulk build failed: '.$e->getMessage());
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Slow path: modal breakdown — only for single-SKU refresh after save.
     *
     * @param  array<int, string>|null  $wantedPullKeys
     * @param  array<int, string>  $onlySkus
     * @param  callable|null  $onProgress
     * @return array{rows: array<int, array<string, mixed>>, errors: array<string, string>}
     */
    public function buildFromBreakdown(?array $wantedPullKeys, array $onlySkus, bool $listedOnly = true, ?callable $onProgress = null): array
    {
        $wantedPull = $wantedPullKeys !== null
            ? array_fill_keys(array_map('strtolower', $wantedPullKeys), true)
            : null;

        $rows = [];
        $errors = [];
        $total = count($onlySkus);
        $done = 0;

        foreach ($onlySkus as $skuRaw) {
            $sku = trim((string) $skuRaw);
            $done++;
            if ($onProgress) {
                $onProgress($done, $total, $sku);
            }
            if ($sku === '') {
                continue;
            }

            try {
                $resp = $this->cvr->getBreakdownData(Request::create('/', 'GET', ['sku' => $sku]));
                $payload = json_decode($resp->getContent(), true);
                if (! is_array($payload) || isset($payload['error'])) {
                    $errors[$sku] = is_array($payload) ? (string) ($payload['error'] ?? 'breakdown error') : 'invalid breakdown';
                    continue;
                }

                $inv = 0.0;
                $ovL30 = 0.0;
                $dil = 0.0;
                $parent = null;
                $image = null;

                foreach ($payload as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $mapped = $this->mapBreakdownItem($item, $sku, $parent, $inv, $ovL30, $dil, $image, $wantedPull);
                    if ($mapped === null) {
                        continue;
                    }
                    if ($listedOnly && ! ((float) ($mapped['price'] ?? 0) > 0) && ! ((float) ($mapped['sprice'] ?? 0) > 0)) {
                        continue;
                    }
                    $rows[] = $mapped;
                }
            } catch (\Throwable $e) {
                $errors[$sku] = $e->getMessage();
                Log::warning('PEF CVR cache SKU failed', ['sku' => $sku, 'error' => $e->getMessage()]);
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<string, true>|null  $wantedPull
     * @return array<string, mixed>|null
     */
    private function mapBreakdownItem(
        array $item,
        string $sku,
        $parent,
        float $inv,
        float $ovL30,
        float $dil,
        $image,
        ?array $wantedPull
    ): ?array {
        $label = (string) ($item['marketplace'] ?? '');
        $norm = strtolower(preg_replace('/\s+/', '', $label) ?? '');
        if ($norm === 'tiktok2' || $norm === 'tiktokshop2') {
            $norm = 'tiktok2';
        }
        if ($norm === 'fba' || $norm === 'walmart') {
            return null;
        }
        if (! isset(self::MP_MAP[$norm])) {
            return null;
        }
        [$marketplace, $pullKey, $channelLabel] = self::MP_MAP[$norm];
        if ($wantedPull !== null && ! isset($wantedPull[$pullKey]) && ! isset($wantedPull[$marketplace])) {
            return null;
        }

        $price = (float) ($item['price'] ?? 0);
        $sprice = (float) ($item['sprice'] ?? 0);
        $lp = (float) ($item['lp'] ?? 0);
        $ship = (float) ($item['ship'] ?? 0);
        $margin = (float) ($item['margin'] ?? 0);
        if (! ($margin > 0)) {
            $margin = $this->defaultMargin($norm);
        }
        if ($margin > 1) {
            $margin = $margin / 100;
        }
        // Modal: NPFT uses tacos_ch; Amazon-style SPFT uses item.ad when L30>0
        $tacosCh = (float) ($item['tacos_ch'] ?? $item['ad'] ?? 0);
        $adSku = (float) ($item['ad'] ?? $item['tacos_ch'] ?? 0);
        $l30 = (float) ($item['l30'] ?? 0);
        $success = $item['push_status'] ?? $item['SPRICE_STATUS'] ?? null;
        if (is_array($success)) {
            $success = null;
        }

        $metrics = $this->computeLikePriceIncreaseModal(
            $norm, $price, $sprice, $lp, $ship, $margin, $tacosCh, $l30, $adSku
        );

        return [
            'id' => $marketplace.'|'.$sku,
            'channel' => $channelLabel,
            'channel_key' => $marketplace,
            'pull_key' => $pullKey,
            'marketplace' => $marketplace,
            'image_path' => is_string($image) ? $image : null,
            'parent' => $parent,
            'sku' => $sku,
            'inv' => $inv,
            'ov_l30' => $ovL30,
            'dil' => $dil,
            'price' => $price > 0 ? round($price, 2) : null,
            'groi' => $metrics['groi'],
            'nroi' => $metrics['nroi'],
            'gpft' => $metrics['gpft'],
            'npft' => $metrics['npft'],
            'sprice' => $sprice > 0 ? round($sprice, 2) : null,
            'sroi' => $metrics['sroi'],
            'sgpft' => $metrics['sgpft'],
            'snroi' => $metrics['snroi'],
            'snpft' => $metrics['snpft'],
            'success' => is_scalar($success) ? (string) $success : null,
            'lp' => $lp,
            'ship' => $ship,
            'margin' => $margin,
            'ads_pct' => $tacosCh,
            '_selected' => false,
        ];
    }

    private function defaultMargin(string $norm): float
    {
        return match ($norm) {
            'doba', 'topdawg' => 0.95,
            'reverb', 'ebay2', 'ebay3', 'tiktok', 'tiktok2', 'sb2c', 'sb2b', 'shein' => 0.85,
            'ebay1' => 0.83,
            'ppower' => 0.65,
            'macy', 'macys' => 0.75,
            'temu', 'temu2' => 1.0,
            default => 0.80,
        };
    }

    /**
     * Same math as price_increase_view.blade.php renderMarketplaceData (~4420–4466).
     *
     * @return array{groi:?float,nroi:?float,gpft:?float,npft:?float,sroi:?float,sgpft:?float,snroi:?float,snpft:?float}
     */
    private function computeLikePriceIncreaseModal(
        string $norm,
        float $price,
        float $sprice,
        float $lp,
        float $ship,
        float $margin,
        float $tacosCh,
        float $l30,
        float $adSku = 0.0
    ): array {
        $out = [
            'groi' => null, 'nroi' => null, 'gpft' => null, 'npft' => null,
            'sroi' => null, 'sgpft' => null, 'snroi' => null, 'snpft' => null,
        ];

        // Match price_increase_view renderMarketplaceData isNoAdsMp (NOT bestbuy/macy/shopify/ae)
        $isTemu = ($norm === 'temu');
        $isTemu2 = ($norm === 'temu2');
        $isNoAds = in_array($norm, [
            'doba', 'ppower', 'topdawg', 'shein', 'faire',
        ], true);
        $isEbay = in_array($norm, ['ebay1', 'ebay2', 'ebay3'], true);
        $isReverb = ($norm === 'reverb');
        $isTiktok = ($norm === 'tiktok');

        if ($price > 0 && $lp > 0 && $margin > 0) {
            $gross = ($price * $margin) - $lp - $ship;
            $gpft = round(($gross / $price) * 100, 2);
            $groi = round(($gross / $lp) * 100, 2);
            $out['gpft'] = $gpft;
            $out['groi'] = $groi;

            // NPFT% = GPFT% − tacos_ch; Temu2/no-ads channels skip subtract
            if ($isNoAds || $isTemu2) {
                $out['npft'] = $gpft;
                $out['nroi'] = $groi;
            } elseif ($isTemu) {
                $out['npft'] = ($tacosCh == 100.0) ? $gpft : round($gpft - $tacosCh, 2);
                $out['nroi'] = ($tacosCh == 100.0) ? $groi : round($groi - $tacosCh, 2);
            } else {
                $out['npft'] = round($gpft - $tacosCh, 2);
                $adsPerUnit = $price * ($tacosCh / 100);
                $out['nroi'] = round((($gross - $adsPerUnit) / $lp) * 100, 2);
            }
        }

        if ($sprice > 0 && $lp > 0 && $margin > 0) {
            $isTemuMp = ($isTemu || $isTemu2);
            $calcSp = ($isTemuMp && $sprice <= 26.99) ? ($sprice + 2.99) : $sprice;

            if ($isTemuMp) {
                $temuProfit = ($sprice * 0.80) - $ship - $lp;
                $sgpft = round(($temuProfit / $sprice) * 100, 2);
                $sroi = round(($temuProfit / $lp) * 100, 2);
            } else {
                $sGross = ($calcSp * $margin) - $lp - $ship;
                $sgpft = round(($sGross / $calcSp) * 100, 2);
                $sroi = round(($sGross / $lp) * 100, 2);
            }
            $out['sgpft'] = $sgpft;
            $out['sroi'] = $sroi; // SGROI%

            // SPFT: TikTok/Temu/Reverb/eBay → tacos_ch; Amazon-style → item.ad (SKU), L30==0 skip
            // SNROI: Temu → SROI−tacos; else dollar ads from tacos_ch (modal)
            if ($isNoAds || $isTemu2) {
                $out['snpft'] = $sgpft;
                $out['snroi'] = $sroi;
            } elseif ($isTiktok || $isTemu || $isReverb || $isEbay) {
                $out['snpft'] = ($tacosCh == 100.0) ? $sgpft : round($sgpft - $tacosCh, 2);
                if ($isTemu) {
                    $out['snroi'] = ($tacosCh == 100.0) ? $sroi : round($sroi - $tacosCh, 2);
                } else {
                    $sGross2 = ($calcSp * $margin) - $lp - $ship;
                    $sAds = $calcSp * ($tacosCh / 100);
                    $out['snroi'] = round((($sGross2 - $sAds) / $lp) * 100, 2);
                }
            } else {
                $out['snpft'] = ($l30 == 0.0) ? $sgpft : round($sgpft - $adSku, 2);
                $sGross2 = ($calcSp * $margin) - $lp - $ship;
                $sAds = $calcSp * ($tacosCh / 100);
                $out['snroi'] = round((($sGross2 - $sAds) / $lp) * 100, 2);
            }
        }

        return $out;
    }
}
