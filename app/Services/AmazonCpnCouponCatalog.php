<?php

namespace App\Services;

use App\Models\ChannelTabulatorColumnSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Amazon CPN coupon tiers used by /amazon-tabulator-view Push CPN%.
 * Amazon SP-API cannot create Seller Central Coupons — these are our app's tier
 * definitions (5% / 10%) with "1 coupon per day" redemption attribute.
 * Price effect is applied via Listings our_price (same path as Push Prmt%).
 */
class AmazonCpnCouponCatalog
{
    public const CHANNEL_NAME = 'amazon_cpn_coupons';

    /** Allowed coupon percents on Amazon for this app. */
    public const TIERS = [5, 10];

    /**
     * @return list<array{percent:int,name:string,one_per_day:bool,one_per_customer:bool}>
     */
    public static function defaults(): array
    {
        return [
            [
                'percent' => 5,
                'name' => 'Amazon CPN 5%',
                'one_per_day' => true,
                'one_per_customer' => true,
            ],
            [
                'percent' => 10,
                'name' => 'Amazon CPN 10%',
                'one_per_day' => true,
                'one_per_customer' => true,
            ],
        ];
    }

    /**
     * Ensure the two coupon definitions exist (create/merge on first use).
     *
     * @return list<array{percent:int,name:string,one_per_day:bool,one_per_customer:bool}>
     */
    public function ensureCoupons(): array
    {
        $defaults = self::defaults();
        $row = ChannelTabulatorColumnSetting::query()
            ->where('channel_name', self::CHANNEL_NAME)
            ->first();

        $saved = is_array($row?->visibility) ? $row->visibility : [];
        $byPct = [];
        foreach ($saved as $item) {
            if (! is_array($item)) {
                continue;
            }
            $p = (int) ($item['percent'] ?? 0);
            if (in_array($p, self::TIERS, true)) {
                $byPct[$p] = $item;
            }
        }

        $coupons = [];
        foreach ($defaults as $def) {
            $p = $def['percent'];
            $prev = $byPct[$p] ?? [];
            $coupons[] = [
                'percent' => $p,
                'name' => (string) ($prev['name'] ?? $def['name']),
                // Attribute: 1 coupon per day only (also 1 per customer)
                'one_per_day' => array_key_exists('one_per_day', $prev)
                    ? (bool) $prev['one_per_day']
                    : true,
                'one_per_customer' => array_key_exists('one_per_customer', $prev)
                    ? (bool) $prev['one_per_customer']
                    : true,
            ];
        }

        try {
            $existing = ChannelTabulatorColumnSetting::query()
                ->where('channel_name', self::CHANNEL_NAME)
                ->first();

            if ($existing) {
                $existing->visibility = $coupons;
                $existing->column_order = array_column($coupons, 'percent');
                $existing->save();
            } else {
                // Table id is NOT AUTO_INCREMENT in this schema — assign next id manually.
                $nextId = ((int) (DB::table('channel_tabulator_column_settings')->max('id') ?? 0)) + 1;
                $row = new ChannelTabulatorColumnSetting;
                $row->id = $nextId;
                $row->channel_name = self::CHANNEL_NAME;
                $row->visibility = $coupons;
                $row->column_order = array_column($coupons, 'percent');
                $row->save();
            }
        } catch (Throwable $e) {
            // Coupons still apply in-memory for push; persistence is best-effort.
            Log::warning('[AmazonCpnCouponCatalog] persist failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $coupons;
    }

    /**
     * Map PEF CVR→CPN rule output (0–10) onto Amazon coupon tiers {0, 5, 10}.
     */
    public static function snapToTier(float $cpn): int
    {
        if (! is_finite($cpn) || $cpn <= 0) {
            return 0;
        }
        if ($cpn <= 5) {
            return 5;
        }

        return 10;
    }
}
