<?php

namespace App\Support;

use App\Models\AmazonDataView;
use App\Models\ProductMaster;
use App\Services\LmpSkuGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Std NPFT % per SKU, the same number /lmp-overall shows:
 *   ((Std Price × 0.70 − ship − LP) / Std Price) × 100
 * Std Price is amazon_data_view.STANDARD_PRICE. A SKU with none uses the
 * first Sku Link LMP sibling that has one. LP and ship come from
 * product_master.Values.
 */
final class EbayStdNpftLookup
{
    /**
     * @param  list<string>  $skus
     * @return array<string, ?float> Keyed by strtoupper(trim(sku)). Null = no Std Price.
     */
    public static function forSkus(array $skus): array
    {
        $own = [];
        foreach ($skus as $sku) {
            $key = self::key((string) $sku);
            if ($key !== '' && ! str_starts_with($key, 'PARENT')) {
                $own[$key] = trim((string) $sku);
            }
        }
        if ($own === []) {
            return [];
        }

        $groups = app(LmpSkuGroupService::class);
        try {
            $groups->prepareForSkus(array_values($own));
        } catch (\Throwable $e) {
            Log::warning('Dil vs SBid: SKU link groups failed', ['error' => $e->getMessage()]);
        }

        $members = [];
        $every = $own;
        foreach ($own as $key => $display) {
            $group = $groups->groupContaining($display);
            if ($group === []) {
                $group = [$display];
            }
            $members[$key] = $group;
            foreach ($group as $member) {
                $memberKey = self::key((string) $member);
                if ($memberKey !== '') {
                    $every[$memberKey] = trim((string) $member);
                }
            }
        }

        $std = self::stdPrices(array_values($every));
        $cost = self::costs(array_values($own));

        $out = [];
        foreach ($own as $key => $display) {
            $price = null;
            foreach ($members[$key] as $member) {
                $memberKey = self::key((string) $member);
                if ($memberKey !== '' && isset($std[$memberKey])) {
                    $price = $std[$memberKey];
                    break;
                }
            }
            $c = $cost[$key] ?? ['lp' => null, 'ship' => 0.0];
            $out[$key] = DilVsSbidRule::stdNpft($price, $c['lp'], $c['ship']);
        }

        return $out;
    }

    public static function key(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($sku)) ?? '');
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, float>
     */
    private static function stdPrices(array $skus): array
    {
        $out = [];
        if ($skus === [] || ! Schema::hasTable('amazon_data_view')) {
            return $out;
        }
        try {
            foreach (array_chunk($skus, 500) as $chunk) {
                $upper = array_map(fn ($s) => strtoupper(trim($s)), $chunk);
                $rows = AmazonDataView::query()
                    ->select(['id', 'sku', 'value'])
                    ->whereIn(DB::raw('UPPER(TRIM(sku))'), $upper)
                    ->get();
                foreach ($rows as $row) {
                    $val = is_array($row->value) ? $row->value : [];
                    $price = $val['STANDARD_PRICE'] ?? null;
                    if (is_numeric($price) && (float) $price > 0) {
                        $out[self::key((string) $row->sku)] = round((float) $price, 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Dil vs SBid: Std Price lookup failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, array{lp: ?float, ship: float}>
     */
    private static function costs(array $skus): array
    {
        $out = [];
        try {
            foreach (array_chunk($skus, 500) as $chunk) {
                $rows = ProductMaster::query()
                    ->whereNull('deleted_at')
                    ->whereIn('sku', $chunk)
                    ->get(['sku', 'Values']);
                foreach ($rows as $product) {
                    $values = is_array($product->Values) ? $product->Values : [];
                    $lp = 0.0;
                    foreach ($values as $k => $v) {
                        if (strtolower((string) $k) === 'lp' && is_numeric($v)) {
                            $lp = (float) $v;
                            break;
                        }
                    }
                    $ship = (isset($values['ship']) && is_numeric($values['ship'])) ? (float) $values['ship'] : 0.0;
                    $out[self::key((string) $product->sku)] = [
                        'lp' => $lp > 0 ? round($lp, 2) : null,
                        'ship' => round($ship, 2),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Dil vs SBid: LP / ship lookup failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }
}
