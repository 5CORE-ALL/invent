<?php

namespace App\Services;

use App\Models\AliexpressDataView;
use App\Models\DobaDataView;
use App\Models\DobaWithoutShipDataView;
use App\Models\EbayDataView;
use App\Models\EbaySkuDailyData;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\BestbuyUSADataView;
use App\Models\FaireDataView;
use App\Models\FBMarketplaceDataView;
use App\Models\MacyDataView;
use App\Models\MercariWoShipDataView;
use App\Models\MercariWShipDataView;
use App\Models\NeweggDataView;
use App\Models\PLSDataView;
use App\Models\PurchasingPowerDataView;
use App\Models\ReverbDataView;
use App\Models\SheinDataView;
use App\Models\ShopifyB2BDataView;
use App\Models\Shopifyb2cDataView;
use App\Models\Temu2DataView;
use App\Models\Temu3DataView;
use App\Models\TemuDataView;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use App\Models\TopDawgDataView;
use App\Models\WalmartDataView;
use App\Models\WayfairDataView;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel PRMT%/CPN%/DSC%/Appr/Push Prc — same JSON key format as Amazon
 * (amazon_data_view.value), stored on each channel's own *_data_view.value.
 *
 * Keys: PEF_PRMT_PCT, PEF_ZERO_SOLD_PRMT_PCT, PEF_CPN_PCT, PEF_DSC_PCT, PEF_APPR,
 *       PUSH_PRC_STATUS, PUSH_PRC_VALUE, PUSH_PRC_PUSHED_AT,
 *       PUSH_STD_PRC_STATUS, PUSH_STD_PRC_VALUE, PUSH_STD_PRC_PUSHED_AT
 *
 * Never uses amazon_data_view for channel promo fields.
 * Never uses a separate channel_promo_pricing table.
 */
class ChannelPromoPricingService
{
    /** @var list<string> */
    public const CHANNELS = [
        'ebay1',
        'ebay2',
        'ebay2op',
        'ebay3',
        'shopify_b2c',
        'shopify_b2b',
        'macys',
        'bestbuy',
        'reverb',
        'walmart',
        'wayfair',
        'temu',
        'temu2',
        'temu3',
        'doba',
        'doba_withoutship',
        'tiktok',
        'tiktok2',
        'topdawg',
        'purchasing_power',
        'aliexpress',
        'shein',
        'newegg',
        'faire',
        'pls',
        'mercari_wship',
        'mercari_woship',
        'fb_marketplace',
    ];

    /**
     * Channel → Eloquent data_view model (same pattern as amazon_data_view).
     *
     * @var array<string, class-string<Model>>
     */
    private const DATA_VIEW_MODELS = [
        'ebay1' => EbayDataView::class,
        'ebay2' => EbayTwoDataView::class,
        'ebay2op' => EbayTwoDataView::class,
        'ebay3' => EbayThreeDataView::class,
        'shopify_b2c' => Shopifyb2cDataView::class,
        'shopify_b2b' => ShopifyB2BDataView::class,
        'macys' => MacyDataView::class,
        'bestbuy' => BestbuyUSADataView::class,
        'reverb' => ReverbDataView::class,
        'walmart' => WalmartDataView::class,
        'wayfair' => WayfairDataView::class,
        'temu' => TemuDataView::class,
        'temu2' => Temu2DataView::class,
        'temu3' => Temu3DataView::class,
        'doba' => DobaDataView::class,
        'doba_withoutship' => DobaWithoutShipDataView::class,
        'tiktok' => TiktokShopDataView::class,
        'tiktok2' => TiktokTwoShopDataView::class,
        'topdawg' => TopDawgDataView::class,
        'purchasing_power' => PurchasingPowerDataView::class,
        'aliexpress' => AliexpressDataView::class,
        'shein' => SheinDataView::class,
        'newegg' => NeweggDataView::class,
        'faire' => FaireDataView::class,
        'pls' => PLSDataView::class,
        'mercari_wship' => MercariWShipDataView::class,
        'mercari_woship' => MercariWoShipDataView::class,
        'fb_marketplace' => FBMarketplaceDataView::class,
    ];

    public function isSupported(string $channel): bool
    {
        return in_array(strtolower(trim($channel)), self::CHANNELS, true);
    }

    /**
     * @return class-string<Model>|null
     */
    public function dataViewModel(string $channel): ?string
    {
        $channel = strtolower(trim($channel));

        return self::DATA_VIEW_MODELS[$channel] ?? null;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array<string, mixed>> keyed by UPPER(trim(sku))
     */
    public function mapForSkus(string $channel, array $skus): array
    {
        $channel = strtolower(trim($channel));
        $modelClass = $this->dataViewModel($channel);
        if (! $this->isSupported($channel) || $skus === [] || ! $modelClass) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();
        if (! Schema::hasTable($table)) {
            return [];
        }

        $norm = [];
        foreach ($skus as $sku) {
            $s = strtoupper(trim((string) $sku));
            if ($s !== '') {
                $norm[$s] = true;
            }
        }
        if ($norm === []) {
            return [];
        }

        $rows = $modelClass::query()
            ->whereIn(DB::raw('UPPER(TRIM(sku))'), array_keys($norm))
            ->get(['sku', 'value']);

        $out = [];
        foreach ($rows as $row) {
            $key = strtoupper(trim((string) $row->sku));
            $val = $this->decodeValue($row->value);
            $mapped = $this->mapFromValue($val);
            if ($mapped !== null) {
                $out[$key] = $mapped;
            }
        }

        return $out;
    }

    /**
     * Merge promo fields onto a row array (returns updated row).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $map
     * @return array<string, mixed>
     */
    public function applyToRow(array $row, array $map, string $sku): array
    {
        $key = strtoupper(trim($sku));
        $promo = ($key !== '' && isset($map[$key])) ? $map[$key] : null;

        $prmt = $promo['prmt_pct'] ?? null;
        $cpn = $promo['cpn_pct'] ?? null;
        $zeroSold = $promo['zero_sold_prmt'] ?? null;

        $dsc = $promo['dsc'] ?? null;

        $row['prmt_pct'] = $prmt !== null ? (string) $prmt : null;
        $row['cpn_pct'] = $cpn !== null ? (string) $cpn : null;
        $row['PEF_PRMT_PCT'] = is_numeric($prmt) ? (float) $prmt : null;
        $row['PEF_CPN_PCT'] = is_numeric($cpn) ? (float) $cpn : null;
        $row['zero_sold_prmt'] = $zeroSold !== null ? (string) $zeroSold : null;
        $row['dsc'] = $dsc !== null && $dsc !== '' ? (string) $dsc : null;
        $row['appr'] = $promo['appr'] ?? false;
        $row['PUSH_PRC_STATUS'] = $promo['PUSH_PRC_STATUS'] ?? null;
        $row['PUSH_PRC_VALUE'] = $promo['PUSH_PRC_VALUE'] ?? null;
        $row['PUSH_STD_PRC_STATUS'] = $promo['PUSH_STD_PRC_STATUS'] ?? null;
        $row['PUSH_STD_PRC_VALUE'] = $promo['PUSH_STD_PRC_VALUE'] ?? null;
        $row['PUSH_BUMP_STATUS'] = $promo['PUSH_BUMP_STATUS'] ?? null;
        $row['PUSH_BUMP_VALUE'] = $promo['PUSH_BUMP_VALUE'] ?? null;
        $row['PEF_COUPON_PCT'] = $promo['PEF_COUPON_PCT'] ?? null;
        $row['PEF_COUPON_CODE'] = $promo['PEF_COUPON_CODE'] ?? null;
        $row['coupon_code'] = $promo['coupon_code'] ?? $promo['PEF_COUPON_CODE'] ?? null;
        $row['PEF_COUPON_PROMOTION_ID'] = $promo['PEF_COUPON_PROMOTION_ID'] ?? null;
        $row['PEF_SALE_PCT'] = $promo['PEF_SALE_PCT'] ?? null;
        $row['PEF_PRMT_PROMOTION_ID'] = $promo['PEF_PRMT_PROMOTION_ID'] ?? null;
        $row['_prmt_pct_applied'] = is_numeric($promo['_prmt_pct_applied'] ?? null)
            ? (float) $promo['_prmt_pct_applied']
            : (is_numeric($prmt) ? (float) $prmt : 0);
        $row['_zero_sold_prmt_applied'] = is_numeric($promo['_zero_sold_prmt_applied'] ?? null)
            ? (float) $promo['_zero_sold_prmt_applied']
            : (is_numeric($zeroSold) ? (float) $zeroSold : 0);
        $row['_cpn_pct_applied'] = is_numeric($promo['_cpn_pct_applied'] ?? null)
            ? (float) $promo['_cpn_pct_applied']
            : (is_numeric($cpn) ? (float) $cpn : 0);
        $row['_dsc_applied'] = is_numeric($promo['_dsc_applied'] ?? null)
            ? (float) $promo['_dsc_applied']
            : (is_numeric($dsc) ? (float) $dsc : 0);

        return $row;
    }

    /**
     * Upsert Amazon-format PEF / Push Prc keys into the channel's data_view.value.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>  mapped promo fields (same shape as mapForSkus entry)
     */
    public function upsert(string $channel, string $sku, array $fields): array
    {
        $channel = strtolower(trim($channel));
        $modelClass = $this->dataViewModel($channel);
        if (! $this->isSupported($channel) || ! $modelClass) {
            throw new \InvalidArgumentException('Unsupported channel or missing data_view: '.$channel);
        }

        $skuNorm = strtoupper(trim($sku));
        if ($skuNorm === '') {
            throw new \InvalidArgumentException('SKU required');
        }

        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        // Lock row during read-modify-write so bulk Dil/CPN/S PRC saves cannot wipe PEF_* keys.
        return DB::transaction(function () use ($modelClass, $table, $skuNorm, $fields, $channel) {
            /** @var Model|null $row */
            $row = $modelClass::query()
                ->whereRaw('UPPER(TRIM(sku)) = ?', [$skuNorm])
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = new $modelClass(['sku' => $skuNorm]);
                // Some *_data_view tables (e.g. ebay_data_view) have non-AI `id`
                if (Schema::hasColumn($table, 'id') && empty($row->getKey())) {
                    try {
                        $nextId = ((int) (DB::table($table)->max('id') ?? 0)) + 1;
                        $row->setAttribute($row->getKeyName(), $nextId);
                    } catch (\Throwable $e) {
                        // leave for DB default / auto-increment if available
                    }
                }
            }

            $existing = $this->decodeValue($row->value);

            if (array_key_exists('prmt_pct', $fields)) {
                $pct = $this->clampPct($fields['prmt_pct']);
                if ($pct === null) {
                    unset($existing['PEF_PRMT_PCT']);
                } else {
                    $existing['PEF_PRMT_PCT'] = $pct;
                }
            }
            if (array_key_exists('zero_sold_prmt', $fields)) {
                $pct = $this->clampPct($fields['zero_sold_prmt']);
                if ($pct === null) {
                    unset($existing['PEF_ZERO_SOLD_PRMT_PCT']);
                } else {
                    $existing['PEF_ZERO_SOLD_PRMT_PCT'] = $pct;
                }
            }
            if (array_key_exists('cpn_pct', $fields)) {
                $pct = $this->clampPct($fields['cpn_pct']);
                if ($pct === null) {
                    unset($existing['PEF_CPN_PCT']);
                } else {
                    $existing['PEF_CPN_PCT'] = $pct;
                }
            }
            if (array_key_exists('dsc_pct', $fields) || array_key_exists('dsc', $fields)) {
                $pct = $this->clampPct($fields['dsc_pct'] ?? $fields['dsc'] ?? null);
                if ($pct === null) {
                    unset($existing['PEF_DSC_PCT']);
                } else {
                    $existing['PEF_DSC_PCT'] = $pct;
                }
            }
            if (array_key_exists('appr', $fields)) {
                $existing['PEF_APPR'] = filter_var($fields['appr'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
            if (array_key_exists('pef_coupon_pct', $fields) || array_key_exists('PEF_COUPON_PCT', $fields)) {
                $pct = $this->clampPct($fields['pef_coupon_pct'] ?? $fields['PEF_COUPON_PCT'] ?? null);
                if ($pct === null || $pct <= 0) {
                    unset($existing['PEF_COUPON_PCT']);
                } else {
                    $existing['PEF_COUPON_PCT'] = $pct;
                }
            }
            if (array_key_exists('pef_coupon_code', $fields) || array_key_exists('PEF_COUPON_CODE', $fields)) {
                $code = trim((string) ($fields['pef_coupon_code'] ?? $fields['PEF_COUPON_CODE'] ?? ''));
                if ($code === '') {
                    unset($existing['PEF_COUPON_CODE']);
                } else {
                    $existing['PEF_COUPON_CODE'] = $code;
                }
            }
            if (array_key_exists('push_prc_status', $fields)) {
                $st = $fields['push_prc_status'];
                if ($st === null || $st === '') {
                    unset($existing['PUSH_PRC_STATUS']);
                } else {
                    $existing['PUSH_PRC_STATUS'] = substr((string) $st, 0, 64);
                }
            }
            if (array_key_exists('push_prc_value', $fields)) {
                $val = $fields['push_prc_value'];
                if (is_numeric($val) && (float) $val > 0) {
                    $existing['PUSH_PRC_VALUE'] = round((float) $val, 2);
                } else {
                    unset($existing['PUSH_PRC_VALUE']);
                }
            }
            if (array_key_exists('push_prc_pushed_at', $fields)) {
                $at = $fields['push_prc_pushed_at'];
                if ($at) {
                    $existing['PUSH_PRC_PUSHED_AT'] = is_string($at)
                        ? $at
                        : now()->toDateTimeString();
                } else {
                    unset($existing['PUSH_PRC_PUSHED_AT']);
                }
            } elseif (($existing['PUSH_PRC_STATUS'] ?? null) === 'pushed' && empty($existing['PUSH_PRC_PUSHED_AT'])) {
                $existing['PUSH_PRC_PUSHED_AT'] = now()->toDateTimeString();
            }
            if (array_key_exists('push_std_prc_status', $fields)) {
                $st = $fields['push_std_prc_status'];
                if ($st === null || $st === '') {
                    unset($existing['PUSH_STD_PRC_STATUS']);
                } else {
                    $existing['PUSH_STD_PRC_STATUS'] = substr((string) $st, 0, 64);
                }
            }
            if (array_key_exists('push_std_prc_value', $fields)) {
                $val = $fields['push_std_prc_value'];
                if (is_numeric($val) && (float) $val > 0) {
                    $existing['PUSH_STD_PRC_VALUE'] = round((float) $val, 2);
                } else {
                    unset($existing['PUSH_STD_PRC_VALUE']);
                }
            }
            if (array_key_exists('push_std_prc_pushed_at', $fields)) {
                $at = $fields['push_std_prc_pushed_at'];
                if ($at) {
                    $existing['PUSH_STD_PRC_PUSHED_AT'] = is_string($at)
                        ? $at
                        : now()->toDateTimeString();
                } else {
                    unset($existing['PUSH_STD_PRC_PUSHED_AT']);
                }
            } elseif (($existing['PUSH_STD_PRC_STATUS'] ?? null) === 'pushed' && empty($existing['PUSH_STD_PRC_PUSHED_AT'])) {
                $existing['PUSH_STD_PRC_PUSHED_AT'] = now()->toDateTimeString();
            }
            if (array_key_exists('push_bump_status', $fields)) {
                $st = $fields['push_bump_status'];
                if ($st === null || $st === '') {
                    unset($existing['PUSH_BUMP_STATUS']);
                } else {
                    $existing['PUSH_BUMP_STATUS'] = substr((string) $st, 0, 64);
                }
            }
            if (array_key_exists('push_bump_value', $fields)) {
                $val = $fields['push_bump_value'];
                if (is_numeric($val) && (float) $val >= 0) {
                    $existing['PUSH_BUMP_VALUE'] = round((float) $val, 2);
                } else {
                    unset($existing['PUSH_BUMP_VALUE']);
                }
            }
            if (array_key_exists('push_bump_pushed_at', $fields)) {
                $at = $fields['push_bump_pushed_at'];
                if ($at) {
                    $existing['PUSH_BUMP_PUSHED_AT'] = is_string($at)
                        ? $at
                        : now()->toDateTimeString();
                } else {
                    unset($existing['PUSH_BUMP_PUSHED_AT']);
                }
            } elseif (($existing['PUSH_BUMP_STATUS'] ?? null) === 'pushed' && empty($existing['PUSH_BUMP_PUSHED_AT'])) {
                $existing['PUSH_BUMP_PUSHED_AT'] = now()->toDateTimeString();
            }

            $row->sku = $row->sku ?: $skuNorm;
            $row->value = $existing;
            $row->save();

            if ($channel === 'ebay1') {
                $this->syncEbay1DailyPromo($skuNorm, $existing);
            }

            return $this->mapFromValue($existing) ?? [
                'prmt_pct' => null,
                'zero_sold_prmt' => null,
                'cpn_pct' => null,
                'dsc' => null,
                'appr' => false,
                'PUSH_PRC_STATUS' => null,
                'PUSH_PRC_VALUE' => null,
                'PUSH_STD_PRC_STATUS' => null,
                'PUSH_STD_PRC_VALUE' => null,
                '_prmt_pct_applied' => 0,
                '_zero_sold_prmt_applied' => 0,
                '_cpn_pct_applied' => 0,
                '_dsc_applied' => 0,
                'sku' => (string) $row->sku,
            ];
        });
    }

    public function markPushed(string $channel, string $sku, float $value): void
    {
        $this->upsert($channel, $sku, [
            'push_prc_status' => 'pushed',
            'push_prc_value' => $value,
            'push_prc_pushed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $val
     * @return array<string, mixed>|null
     */
    private function mapFromValue(array $val): ?array
    {
        $prmt = $this->nullablePct($val['PEF_PRMT_PCT'] ?? null);
        $zeroSold = $this->nullablePct($val['PEF_ZERO_SOLD_PRMT_PCT'] ?? null);
        $cpn = $this->nullablePct($val['PEF_CPN_PCT'] ?? null);
        $dsc = $this->nullablePct($val['PEF_DSC_PCT'] ?? null);
        $couponPct = $this->nullablePct($val['PEF_COUPON_PCT'] ?? null);
        $couponCode = isset($val['PEF_COUPON_CODE']) ? trim((string) $val['PEF_COUPON_CODE']) : '';
        $couponPromoId = isset($val['PEF_COUPON_PROMOTION_ID']) ? trim((string) $val['PEF_COUPON_PROMOTION_ID']) : '';
        $salePct = $this->nullablePct($val['PEF_SALE_PCT'] ?? null);
        $salePromoId = isset($val['PEF_PRMT_PROMOTION_ID']) ? trim((string) $val['PEF_PRMT_PROMOTION_ID']) : '';
        $hasAny = $prmt !== null || $zeroSold !== null || $cpn !== null || $dsc !== null
            || isset($val['PEF_APPR'])
            || isset($val['PUSH_PRC_STATUS'])
            || isset($val['PUSH_PRC_VALUE'])
            || isset($val['PUSH_STD_PRC_STATUS'])
            || isset($val['PUSH_STD_PRC_VALUE'])
            || isset($val['PUSH_BUMP_STATUS'])
            || isset($val['PUSH_BUMP_VALUE'])
            || $couponPct !== null
            || $couponCode !== ''
            || $couponPromoId !== ''
            || $salePct !== null
            || $salePromoId !== '';

        if (! $hasAny) {
            return null;
        }

        $pushVal = $val['PUSH_PRC_VALUE'] ?? null;
        $pushStdVal = $val['PUSH_STD_PRC_VALUE'] ?? null;
        $pushBumpVal = $val['PUSH_BUMP_VALUE'] ?? null;

        return [
            'prmt_pct' => $prmt,
            'zero_sold_prmt' => $zeroSold,
            'cpn_pct' => $cpn,
            'dsc' => $dsc,
            'appr' => filter_var($val['PEF_APPR'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'PUSH_PRC_STATUS' => $val['PUSH_PRC_STATUS'] ?? null,
            'PUSH_PRC_VALUE' => (is_numeric($pushVal) && (float) $pushVal > 0)
                ? round((float) $pushVal, 2)
                : null,
            'PUSH_STD_PRC_STATUS' => $val['PUSH_STD_PRC_STATUS'] ?? null,
            'PUSH_STD_PRC_VALUE' => (is_numeric($pushStdVal) && (float) $pushStdVal > 0)
                ? round((float) $pushStdVal, 2)
                : null,
            'PUSH_BUMP_STATUS' => $val['PUSH_BUMP_STATUS'] ?? null,
            'PUSH_BUMP_VALUE' => is_numeric($pushBumpVal)
                ? round((float) $pushBumpVal, 2)
                : null,
            'PEF_COUPON_PCT' => $couponPct,
            'PEF_COUPON_CODE' => $couponCode !== '' ? $couponCode : null,
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'PEF_COUPON_PROMOTION_ID' => $couponPromoId !== '' ? $couponPromoId : null,
            'PEF_SALE_PCT' => $salePct,
            'PEF_PRMT_PROMOTION_ID' => $salePromoId !== '' ? $salePromoId : null,
            '_prmt_pct_applied' => is_numeric($prmt) ? (float) $prmt : 0,
            '_zero_sold_prmt_applied' => is_numeric($zeroSold) ? (float) $zeroSold : 0,
            '_cpn_pct_applied' => is_numeric($cpn) ? (float) $cpn : 0,
            '_dsc_applied' => is_numeric($dsc) ? (float) $dsc : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValue(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function clampPct(mixed $val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (! is_numeric($val)) {
            return null;
        }
        $n = round((float) $val, 2);

        return $n < 0 ? 0.0 : $n;
    }

    private function nullablePct(mixed $val): ?float
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return round((float) $val, 2);
    }

    /**
     * Write today's PDT snapshot so PRMT% / CPN% history dots have a point.
     *
     * @param  array<string, mixed>  $existing
     */
    private function syncEbay1DailyPromo(string $skuNorm, array $existing): void
    {
        try {
            $today = Carbon::now('America/Los_Angeles')->toDateString();
            $daily = EbaySkuDailyData::firstOrNew([
                'sku' => $skuNorm,
                'record_date' => $today,
            ]);
            $payload = is_array($daily->daily_data) ? $daily->daily_data : [];
            if (isset($existing['PEF_PRMT_PCT']) && is_numeric($existing['PEF_PRMT_PCT'])) {
                $payload['prmt_pct'] = round((float) $existing['PEF_PRMT_PCT'], 2);
            }
            if (isset($existing['PEF_CPN_PCT']) && is_numeric($existing['PEF_CPN_PCT'])) {
                $payload['cpn_pct'] = round((float) $existing['PEF_CPN_PCT'], 2);
            }
            if (isset($existing['PUSH_PRC_VALUE']) && is_numeric($existing['PUSH_PRC_VALUE'])) {
                $payload['push_prc'] = round((float) $existing['PUSH_PRC_VALUE'], 2);
            }
            if (isset($existing['SPRICE']) && is_numeric($existing['SPRICE']) && (float) $existing['SPRICE'] > 0) {
                $payload['sprice'] = round((float) $existing['SPRICE'], 2);
            }
            $daily->daily_data = $payload;
            $daily->save();
        } catch (\Throwable $e) {
            Log::warning('Could not sync eBay1 promo to daily history', [
                'sku' => $skuNorm,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
