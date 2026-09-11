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
use App\Models\WalmartPricingSales;
use App\Models\WayfairDataView;
use App\Models\WayfairPricingPrice;
use App\Support\PushedListingPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * After an S PRC push, stamp data_view and keep the tabulator Price column
 * in sync so a later listings fetch cannot leave a stale list / retail price.
 */
class ChannelLivePriceSync
{
    /** After a Temu S PRC push, wait this long before overwriting Price with a live API pull. */
    public const TEMU_API_PULL_AFTER_PUSH_HOURS = 2;

    public static function normalize(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return match ($channel) {
            'bb', 'best_buy', 'bestbuyusa' => 'bestbuy',
            'ae', 'ali' => 'aliexpress',
            'tt', 'tiktokshop' => 'tiktok',
            'tiktok_2', 'tiktoktwo' => 'tiktok2',
            'td', 'top_dawg' => 'topdawg',
            'fb', 'facebook', 'fbmarketplace' => 'fb_marketplace',
            'wf' => 'wayfair',
            'wm', 'walmart_wfs' => 'walmart',
            'ebay2op' => 'ebay2',
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

        if ($channel === 'doba_withoutship') {
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

    public static function stamp(string $channel, string $sku, float $sprice): void
    {
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return;
        }

        $sku = strtoupper(trim($sku));
        $sprice = round($sprice, 2);
        $view = $viewClass::query()
            ->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])
            ->first()
            ?? new $viewClass(['sku' => $sku]);
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
        if (in_array($channel, ['temu', 'temu2'], true)) {
            $existing['PRICE_PULL_STATUS'] = 'pending';
            $existing['PRICE_PULL_DUE_AT'] = now()
                ->addHours(self::TEMU_API_PULL_AFTER_PUSH_HOURS)
                ->toDateTimeString();
            unset($existing['PRICE_PULLED_AT'], $existing['PRICE_PULLED_VALUE']);
        }

        $view->sku = $view->sku ?: $sku;
        $view->value = $existing;
        $view->save();
    }

    public static function writeLive(string $channel, string $sku, float $sprice): void
    {
        $channel = self::normalize($channel);
        $sku = strtoupper(trim($sku));
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
                    $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $row->sku)));
                    if ($sku !== '') {
                        $map[$sku] = $pushed;
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

    /**
     * @param  array<string, float>|null  $lookup
     */
    public static function preferIncoming(string $channel, string $sku, ?float $incoming, ?array $lookup = null): ?float
    {
        $channel = self::normalize($channel);
        if (in_array($channel, ['temu', 'temu2', 'temu3'], true)) {
            return ($incoming !== null && $incoming > 0) ? round($incoming, 2) : $incoming;
        }

        $key = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
        $pushed = $key !== '' && is_array($lookup) ? ($lookup[$key] ?? null) : null;
        if ($pushed === null && $lookup === null && $key !== '') {
            $pushed = self::lookupMap($channel)[$key] ?? null;
        }

        return PushedListingPrice::prefer($incoming, $pushed);
    }

    /**
     * SKUs still inside the post-push hold window — do not write API Price yet.
     *
     * @return array<string, true>
     */
    public static function temuApiPriceHoldSkus(string $channel): array
    {
        $hold = [];
        $now = now();
        $cutoff = $now->copy()->subHours(self::TEMU_API_PULL_AFTER_PUSH_HOURS);
        foreach (self::temuPricePullViewRows($channel) as $sku => $val) {
            $due = self::parseViewTime($val['PRICE_PULL_DUE_AT'] ?? null);
            if ($due !== null) {
                if ($due->gt($now)) {
                    $hold[$sku] = true;
                }
                continue;
            }
            $pushedAt = self::parseViewTime($val['SPRICE_PUSHED_AT'] ?? null);
            if ($pushedAt !== null && $pushedAt->gt($cutoff)) {
                $hold[$sku] = true;
            }
        }

        return $hold;
    }

    /**
     * Pushed SKUs whose 2-hour API confirm is due (or all pending when $force).
     *
     * @return list<string>
     */
    public static function dueTemuApiPullSkus(string $channel, int $limit = 80, bool $force = false): array
    {
        $due = [];
        $now = now();
        $cutoff = $now->copy()->subHours(self::TEMU_API_PULL_AFTER_PUSH_HOURS);
        foreach (self::temuPricePullViewRows($channel) as $sku => $val) {
            if (strtolower(trim((string) ($val['PRICE_PULL_STATUS'] ?? ''))) !== 'pending') {
                continue;
            }
            $dueAt = self::parseViewTime($val['PRICE_PULL_DUE_AT'] ?? null);
            if (! $force) {
                if ($dueAt !== null && $dueAt->gt($now)) {
                    continue;
                }
                if ($dueAt === null) {
                    $pushedAt = self::parseViewTime($val['SPRICE_PUSHED_AT'] ?? null);
                    if ($pushedAt === null || $pushedAt->gt($cutoff)) {
                        continue;
                    }
                }
            }
            $due[] = $sku;
            if (count($due) >= $limit) {
                break;
            }
        }

        return $due;
    }

    public static function markTemuApiPulled(string $channel, string $sku, ?float $price): void
    {
        $channel = self::normalize($channel);
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return;
        }
        $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
        if ($sku === '') {
            return;
        }
        $view = $viewClass::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->first();
        if (! $view) {
            return;
        }
        $existing = is_array($view->value)
            ? $view->value
            : (json_decode((string) ($view->value ?? ''), true) ?: []);
        if (! is_array($existing)) {
            $existing = [];
        }
        $existing['PRICE_PULL_STATUS'] = 'done';
        $existing['PRICE_PULLED_AT'] = now()->toDateTimeString();
        if ($price !== null && $price > 0) {
            $existing['PRICE_PULLED_VALUE'] = round($price, 2);
        }
        $view->value = $existing;
        $view->save();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function temuPricePullViewRows(string $channel): array
    {
        $channel = self::normalize($channel);
        $viewClass = self::viewClass($channel);
        if ($viewClass === null || ! class_exists($viewClass)) {
            return [];
        }
        $out = [];
        try {
            $viewClass::query()
                ->where(function ($q) {
                    $q->where('value', 'like', '%PRICE_PULL_%')
                        ->orWhere('value', 'like', '%SPRICE_PUSHED_AT%');
                })
                ->get(['sku', 'value'])
                ->each(function ($row) use (&$out) {
                    $sku = strtoupper(trim(str_replace("\xc2\xa0", ' ', (string) $row->sku)));
                    if ($sku === '') {
                        return;
                    }
                    $val = is_array($row->value)
                        ? $row->value
                        : (json_decode((string) ($row->value ?? ''), true) ?: []);
                    if (is_array($val)) {
                        $out[$sku] = $val;
                    }
                });
        } catch (Throwable $e) {
            Log::warning('ChannelLivePriceSync temu pull lookup failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        return $out;
    }

    private static function parseViewTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
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
            default => [],
        };
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

        $class::query()
            ->whereRaw('UPPER(TRIM('.$skuCol.')) = ?', [$sku])
            ->update([$column => $value]);
    }
}
