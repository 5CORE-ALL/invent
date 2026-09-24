<?php

namespace App\Services;

use App\Models\AliexpressDataView;
use App\Models\AliexpressMetric;
use App\Models\AliexpressPricingPrice;
use App\Models\BestbuyUsaProduct;
use App\Models\BestbuyUSADataView;
use App\Models\DobaDataView;
use App\Models\DobaMetric;
use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayDataView;
use App\Models\EbayMetric;
use App\Models\EbayThreeDataView;
use App\Models\EbayTwoDataView;
use App\Models\FaireDataView;
use App\Models\FaireMetric;
use App\Models\FBMarketplaceDataView;
use App\Models\FbMarketplacePriceSoldData;
use App\Models\NeweggDataView;
use App\Models\NeweggMetric;
use App\Models\NeweggPricing;
use App\Models\PLSDataView;
use App\Models\PLSProduct;
use App\Models\ReverbDataView;
use App\Models\ReverbMetric;
use App\Models\ReverbProduct;
use App\Models\SheinDataView;
use App\Models\SheinMetric;
use App\Models\SheinPricingPrice;
use App\Models\Temu2DataView;
use App\Models\Temu2Metric;
use App\Models\Temu3DataView;
use App\Models\Temu3Pricing;
use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Models\TikTokProduct;
use App\Models\TikTokProductTwo;
use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use App\Models\TopDawgDataView;
use App\Models\TopDawgProduct;
use App\Models\WalmartDataView;
use App\Models\WalmartMetrics;
use App\Models\MacyDataView;
use App\Models\MacyProduct;
use App\Models\MacysPriceData;
use App\Models\ShopifySku;
use App\Models\WalmartPricingSales;
use App\Models\WayfairDataView;
use App\Models\WayfairPricingPrice;
use App\Support\PushedListingPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * After an S PRC push, stamp data_view and keep the tabulator Price column
 * in sync so a later listings fetch cannot leave a stale list / retail price.
 */
class ChannelLivePriceSync
{
    public static function normalize(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'macy' => 'macys',
            'bb', 'best_buy', 'bestbuyusa' => 'bestbuy',
            'ae', 'ali' => 'aliexpress',
            'tt', 'tiktokshop' => 'tiktok',
            'tiktok_2', 'tiktoktwo' => 'tiktok2',
            'td', 'top_dawg' => 'topdawg',
            'fb', 'facebook', 'fbmarketplace' => 'fb_marketplace',
            'wf' => 'wayfair',
            'wm', 'walmart_wfs' => 'walmart',
            'ebay2op' => 'ebay2',
            'newtemuone' => 'temu',
            default => $channel,
        };
    }

    public static function confirmAfterPush(string $channel, string $sku, float $sprice): void
    {
        $channel = self::normalize($channel);
        $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
        $sprice = round($sprice, 2);
        if ($sku === '' || $sprice <= 0) {
            return;
        }

        try {
            self::stamp($channel, $sku, $sprice);
        } catch (Throwable $e) {
            Log::warning('ChannelLivePriceSync stamp failed', [
                'channel' => $channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        // eBay Price is CurrentPrice from GetItem / inventory reports.
        // TopDawg Price is SupplierProduct cost on the site.
        // Do not stamp Dil S PRC over a listing that is still at another amount.
        if ($channel === 'doba_withoutship' || $channel === 'topdawg' || self::isEbayChannel($channel)) {
            return;
        }

        try {
            self::writeLive($channel, $sku, $sprice);
        } catch (Throwable $e) {
            Log::warning('ChannelLivePriceSync writeLive failed', [
                'channel' => $channel,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function isEbayChannel(string $channel): bool
    {
        return in_array(self::normalize($channel), ['ebay1', 'ebay2', 'ebay3'], true);
    }

    public static function stamp(string $channel, string $sku, float $sprice): void
    {
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return;
        }

        $sku = strtoupper(trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', $sku)));
        $sprice = round($sprice, 2);
        $view = self::findDataViewBySku($viewClass, $sku);
        $existing = is_array($view->value)
            ? $view->value
            : (json_decode((string) ($view->value ?? ''), true) ?: []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $existing['SPRICE'] = $sprice;
        $existing['sprice'] = $sprice;
        $existing['SPRICE_PUSHED_VALUE'] = $sprice;
        $existing['CHANNEL_PUSHED_PRICE'] = $sprice;
        $existing['SPRICE_PUSHED_AT'] = now()->toDateTimeString();
        $existing['SPRICE_STATUS'] = 'pushed';
        $existing['SPRICE_STATUS_UPDATED_AT'] = now()->toDateTimeString();
        unset($existing['SPRICE_CLEARED']);

        $view->sku = $view->sku ?: $sku;
        $view->value = $existing;
        $view->save();
    }

    public static function writeLive(string $channel, string $sku, float $sprice): void
    {
        $channel = self::normalize($channel);
        $sku = strtoupper(trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', $sku)));
        $sprice = round($sprice, 2);
        if ($sku === '' || $sprice <= 0) {
            return;
        }

        $value = $sprice;
        if (in_array($channel, ['temu', 'temu2', 'temu3'], true)) {
            $base = TemuShopifySalesService::computePushBaseFromSprice($sprice);
            if ($base === null || $base <= 0) {
                return;
            }
            $value = $base;
        }

        foreach (self::writeTargets($channel) as $target) {
            try {
                self::updateTarget($target, $sku, $value);
            } catch (Throwable $e) {
                Log::warning('ChannelLivePriceSync target update failed', [
                    'channel' => $channel,
                    'sku' => $sku,
                    'model' => $target['model'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, float> UPPER sku → pushed S PRC
     */
    public static function lookupMap(string $channel): array
    {
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return [];
        }

        $map = [];
        try {
            $viewClass::query()
                ->where(function ($q) {
                    $q->where('value', 'like', '%SPRICE_PUSHED_VALUE%')
                        ->orWhere('value', 'like', '%CHANNEL_PUSHED_PRICE%');
                })
                ->get(['sku', 'value'])
                ->each(function ($row) use (&$map) {
                    $val = is_array($row->value)
                        ? $row->value
                        : (json_decode((string) ($row->value ?? ''), true) ?: []);
                    $pushed = PushedListingPrice::fromValue(is_array($val) ? $val : []);
                    if ($pushed === null) {
                        return;
                    }
                    foreach (self::skuLookupKeys((string) $row->sku) as $key) {
                        $map[$key] = $pushed;
                    }
                });
        } catch (Throwable $e) {
            Log::warning('ChannelLivePriceSync lookup failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        return $map;
    }

    public static function lookupPushed(string $channel, string $sku): ?float
    {
        $channel = self::normalize($channel);
        $key = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
        if ($key === '') {
            return null;
        }
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return null;
        }
        try {
            $row = self::findDataViewBySku($viewClass, $key);
            if (! $row->exists) {
                return null;
            }
            $val = is_array($row->value)
                ? $row->value
                : (json_decode((string) ($row->value ?? ''), true) ?: []);

            return PushedListingPrice::fromValue(is_array($val) ? $val : []);
        } catch (Throwable $e) {
            Log::warning('ChannelLivePriceSync lookupPushed failed', [
                'channel' => $channel,
                'sku' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, float>|null  $lookup
     */
    public static function preferIncoming(string $channel, string $sku, ?float $incoming, ?array $lookup = null): ?float
    {
        $channel = self::normalize($channel);
        $keys = self::skuLookupKeys($sku);
        $pushed = null;
        if (is_array($lookup)) {
            foreach ($keys as $key) {
                if (isset($lookup[$key]) && is_numeric($lookup[$key])) {
                    $pushed = (float) $lookup[$key];
                    break;
                }
            }
        }
        if ($pushed === null && $lookup === null && $keys !== []) {
            $map = self::lookupMap($channel);
            foreach ($keys as $key) {
                if (isset($map[$key]) && is_numeric($map[$key])) {
                    $pushed = (float) $map[$key];
                    break;
                }
            }
        }

        if (in_array($channel, ['temu', 'temu2', 'temu3'], true)) {
            return PushedListingPrice::temuBaseToWrite($incoming, $pushed);
        }

        // Live catalog price wins. A saved S PRC stays in SPRICE, not in Price.
        if ($channel === 'topdawg' || self::isEbayChannel($channel)) {
            if ($incoming !== null && $incoming > 0) {
                return round($incoming, 2);
            }

            return $pushed;
        }

        return PushedListingPrice::prefer($incoming, $pushed);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function shouldSkipPushAndRepair(
        string $channel,
        array $row,
        float $next,
        bool $dryRun = false
    ): bool {
        $live = (float) ($row['live'] ?? 0);
        if (! ($live > 0) || ! ($next > 0)) {
            return true;
        }

        $liveCompare = in_array(self::normalize($channel), ['temu', 'temu2', 'temu3'], true)
            ? round(TemuShopifySalesService::computeFullTemuPrice($live), 2)
            : $live;
        if (PushedListingPrice::same($next, $liveCompare)) {
            return true;
        }

        // A leftover SPRICE_PUSHED_VALUE must not skip the revise or overwrite
        // the live listing price when the site is still at another amount.
        if (self::isEbayChannel($channel)) {
            return false;
        }

        // Already submitted to TopDawg. Leave the site cost in the Price column.
        if (self::normalize($channel) === 'topdawg') {
            $pushedTd = (float) ($row['pushed_sprice'] ?? 0);
            if ($pushedTd > 0 && PushedListingPrice::same($pushedTd, $next)) {
                return true;
            }

            return false;
        }

        $pushed = (float) ($row['pushed_sprice'] ?? 0);
        if ($pushed > 0 && PushedListingPrice::same($pushed, $next)) {
            if (! $dryRun) {
                self::writeLive($channel, (string) ($row['sku'] ?? ''), $next);
            }

            return true;
        }

        return false;
    }

    public static function viewClass(string $channel): ?string
    {
        return match (self::normalize($channel)) {
            'ebay1' => EbayDataView::class,
            'ebay2' => EbayTwoDataView::class,
            'ebay3' => EbayThreeDataView::class,
            'bestbuy' => BestbuyUSADataView::class,
            'aliexpress' => AliexpressDataView::class,
            'newegg' => NeweggDataView::class,
            'temu' => TemuDataView::class,
            'temu2' => Temu2DataView::class,
            'temu3' => Temu3DataView::class,
            'reverb' => ReverbDataView::class,
            'tiktok' => TiktokShopDataView::class,
            'tiktok2' => TiktokTwoShopDataView::class,
            'doba', 'doba_withoutship' => DobaDataView::class,
            'faire' => FaireDataView::class,
            'shein' => SheinDataView::class,
            'wayfair' => WayfairDataView::class,
            'topdawg' => TopDawgDataView::class,
            'walmart' => WalmartDataView::class,
            'pls' => PLSDataView::class,
            'fb_marketplace' => FBMarketplaceDataView::class,
            'macys', 'macy' => MacyDataView::class,
            default => null,
        };
    }

    /**
     * @return list<array{model: class-string, column: string, sku?: string}>
     */
    private static function writeTargets(string $channel): array
    {
        return match ($channel) {
            'ebay1' => [
                ['model' => EbayMetric::class, 'column' => 'ebay_price'],
            ],
            'ebay2' => [
                ['model' => Ebay2Metric::class, 'column' => 'ebay_price'],
            ],
            'ebay3' => [
                ['model' => Ebay3Metric::class, 'column' => 'ebay_price'],
            ],
            'bestbuy' => [
                ['model' => BestbuyUsaProduct::class, 'column' => 'price'],
            ],
            'aliexpress' => [
                ['model' => AliexpressMetric::class, 'column' => 'price'],
                ['model' => AliexpressPricingPrice::class, 'column' => 'price'],
            ],
            'newegg' => [
                ['model' => NeweggMetric::class, 'column' => 'price'],
                ['model' => NeweggPricing::class, 'column' => 'selling_price', 'sku' => 'seller_part_number'],
            ],
            'temu' => [
                ['model' => TemuMetric::class, 'column' => 'base_price'],
            ],
            'temu2' => [
                ['model' => Temu2Metric::class, 'column' => 'base_price'],
            ],
            'temu3' => [
                ['model' => Temu3Pricing::class, 'column' => 'base_price'],
            ],
            'reverb' => [
                ['model' => ReverbMetric::class, 'column' => 'price'],
                ['model' => ReverbProduct::class, 'column' => 'price'],
            ],
            'tiktok' => [
                ['model' => TikTokProduct::class, 'column' => 'price'],
            ],
            'tiktok2' => [
                ['model' => TikTokProductTwo::class, 'column' => 'price'],
            ],
            'doba' => [
                ['model' => DobaMetric::class, 'column' => 'anticipated_income'],
            ],
            'faire' => [
                ['model' => FaireMetric::class, 'column' => 'price'],
            ],
            'shein' => [
                ['model' => SheinMetric::class, 'column' => 'price'],
                ['model' => SheinPricingPrice::class, 'column' => 'price'],
                ['model' => SheinPricingPrice::class, 'column' => 'special_offer_price'],
            ],
            'wayfair' => [
                ['model' => WayfairPricingPrice::class, 'column' => 'price'],
            ],
            'topdawg' => [
                ['model' => TopDawgProduct::class, 'column' => 'price'],
            ],
            'walmart' => [
                ['model' => WalmartMetrics::class, 'column' => 'price'],
                ['model' => WalmartPricingSales::class, 'column' => 'current_price'],
            ],
            'pls' => [
                ['model' => PLSProduct::class, 'column' => 'price'],
            ],
            'fb_marketplace' => [
                ['model' => FbMarketplacePriceSoldData::class, 'column' => 'price'],
            ],
            'macys', 'macy' => [
                ['model' => MacyProduct::class, 'column' => 'price'],
                ['model' => MacysPriceData::class, 'column' => 'price'],
                ['model' => MacysPriceData::class, 'column' => 'price', 'sku' => 'offer_sku'],
            ],
            default => [],
        };
    }

    private static function macysOfferIsActive(string $sku): bool
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return false;
        }
        try {
            $row = MacysPriceData::query()
                ->where(function ($q) use ($sku) {
                    $q->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
                        ->orWhereRaw('UPPER(TRIM(offer_sku)) = ?', [$sku]);
                })
                ->first(['activated']);

            return $row !== null && filter_var($row->activated, FILTER_VALIDATE_BOOLEAN);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{model: class-string, column: string, sku?: string}  $target
     */
    private static function updateTarget(array $target, string $sku, float $value): void
    {
        $class = $target['model'];
        $column = $target['column'];
        $skuCol = $target['sku'] ?? 'sku';
        if (! class_exists($class)) {
            return;
        }

        $table = (new $class)->getTable();
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $updated = $class::query()
            ->whereRaw('UPPER(TRIM('.$skuCol.')) = ?', [$sku])
            ->update([$column => $value]);

        if ($updated > 0 || ! in_array($class, [MacyProduct::class, MacysPriceData::class], true)) {
            return;
        }

        $ids = self::matchingModelIds($class, $skuCol, $sku);
        if ($ids !== []) {
            $class::query()->whereIn('id', $ids)->update([$column => $value]);
        }
    }

    /**
     * @return list<string>
     */
    public static function skuLookupKeys(string $sku): array
    {
        $keys = [];
        $raw = strtoupper(trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', $sku)));
        if ($raw !== '') {
            $keys[] = $raw;
        }
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '') {
            $keys[] = $norm;
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '') {
            $keys[] = $compact;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  class-string  $viewClass
     */
    private static function findDataViewBySku(string $viewClass, string $sku): object
    {
        $sku = strtoupper(trim(str_replace(["\xc2\xa0", "\xC2\xA0"], ' ', $sku)));
        $view = $viewClass::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first();
        if ($view) {
            return $view;
        }

        try {
            foreach ($viewClass::query()->whereNotNull('sku')->cursor() as $row) {
                if (ShopifySku::skusMatch((string) $row->sku, $sku)) {
                    return $row;
                }
            }
        } catch (Throwable) {
            // fall through to a new row
        }

        return new $viewClass(['sku' => $sku]);
    }

    /**
     * @param  class-string  $class
     * @return list<int>
     */
    private static function matchingModelIds(string $class, string $skuCol, string $sku): array
    {
        $ids = [];
        try {
            $select = ['id', $skuCol];
            if ($class === MacysPriceData::class && $skuCol !== 'offer_sku') {
                $select[] = 'offer_sku';
            }
            foreach ($class::query()->select($select)->cursor() as $row) {
                $stored = (string) ($row->{$skuCol} ?? '');
                if (ShopifySku::skusMatch($stored, $sku)) {
                    $ids[] = $row->id;
                    continue;
                }
                if ($class === MacysPriceData::class && ShopifySku::skusMatch((string) ($row->offer_sku ?? ''), $sku)) {
                    $ids[] = $row->id;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return array_values(array_unique($ids));
    }
}
