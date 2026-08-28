<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketplacePercentage extends Model
{
    use SoftDeletes;

    protected $table = 'marketplace_percentages';

    protected $fillable = ['marketplace', 'percentage','ad_updates'];

    protected $dates = ['deleted_at'];

    /**
     * Take-home decimal (0–1) from marketplace_percentages.percentage.
     * First matching marketplace name wins. Default 100 (1.0) when no row exists.
     */
    public static function takeHomeDecimal(string ...$marketplaces): float
    {
        foreach ($marketplaces as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $row = static::query()->where('marketplace', $name)->first();
            if ($row === null) {
                continue;
            }
            $pct = (float) $row->percentage;

            return $pct > 1 ? $pct / 100 : $pct;
        }

        return 1.0;
    }

    /**
     * marketplace_percentages.marketplace names for a channel-pef-promo key.
     *
     * @return list<string>
     */
    public static function promoChannelNames(string $channel): array
    {
        return match ($channel) {
            'ebay1' => ['Ebay'],
            'ebay2', 'ebay2op' => ['EbayTwo'],
            'ebay3' => ['EbayThree'],
            'shopify_b2c' => ['ShopifyB2C'],
            'shopify_b2b' => ['ShopifyB2B'],
            'macys', 'macy' => ['Macys', 'Macy'],
            'bestbuy' => ['BestbuyUSA', 'BestBuy'],
            'reverb' => ['Reverb'],
            'walmart' => ['Walmart'],
            'wayfair' => ['Wayfair'],
            'temu' => ['Temu'],
            'temu2' => ['Temu 2', 'TemuTwo', 'Temu2', 'Temu'],
            'doba', 'doba_withoutship' => ['Doba'],
            'tiktok', 'tiktok2' => ['TiktokShop'],
            'topdawg' => ['TopDawg'],
            'purchasing_power' => ['Purchase', 'PurchasingPower'],
            'aliexpress' => ['Aliexpress', 'AliExpress'],
            'shein' => ['Shein'],
            'newegg' => ['Neweggb2c', 'Newegg'],
            'faire' => ['Faire'],
            'pls' => ['PLS', 'Pls'],
            'mercari_wship' => ['MercariWShip'],
            'mercari_woship' => ['MercariWoShip'],
            'fb_marketplace' => ['FB Marketplace', 'FBMarketplace'],
            'vinted' => ['Vinted'],
            'depop' => ['Depop'],
            default => [],
        };
    }

    /** Take-home decimal (0–1) for a channel-pef-promo analytics page. */
    public static function takeHomeForPromoChannel(string $channel): float
    {
        $names = static::promoChannelNames($channel);
        if ($names === []) {
            return 1.0;
        }

        return static::takeHomeDecimal(...$names);
    }

    /**
     * eBay channel aliases → take-home decimal from marketplace_percentages.
     * Names match the table: Ebay, EbayTwo, EbayThree.
     *
     * @return array<string, float>
     */
    public static function ebayTakeHomeMap(): array
    {
        $ebay1 = static::takeHomeDecimal('Ebay');
        $ebay2 = static::takeHomeDecimal('EbayTwo');
        $ebay3 = static::takeHomeDecimal('EbayThree');

        return [
            'ebay' => $ebay1,
            'ebay1' => $ebay1,
            'ebayone' => $ebay1,
            'ebay2' => $ebay2,
            'ebaytwo' => $ebay2,
            'ebay3' => $ebay3,
            'ebaythree' => $ebay3,
        ];
    }

    /**
     * Temu / Temu 2 take-home decimal from marketplace_percentages.
     * Names match the table: Temu, Temu 2 (aliases TemuTwo / Temu2).
     *
     * @return array<string, float>
     */
    public static function temuTakeHomeMap(): array
    {
        $temu = static::takeHomeDecimal('Temu');
        $temu2 = static::takeHomeDecimal('Temu 2', 'TemuTwo', 'Temu2');

        return [
            'temu' => $temu,
            'temu2' => $temu2,
            'temutwo' => $temu2,
        ];
    }

    /**
     * Label from `marketplace_percentages.marketplace` for a row in `marketplaces`, when names align.
     * Used by SKU Image Manager so UI matches the percentage master list.
     */
    public static function displayNameForMarketplace(?Marketplace $marketplace): ?string
    {
        if ($marketplace === null) {
            return null;
        }

        foreach (self::matchNeedlesForMarketplace($marketplace) as $needle) {
            $label = static::query()
                ->whereRaw('LOWER(TRIM(marketplace)) = ?', [$needle])
                ->orderBy('id')
                ->value('marketplace');
            if ($label !== null && $label !== '') {
                return (string) $label;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function matchNeedlesForMarketplace(Marketplace $marketplace): array
    {
        $candidates = [
            strtolower(trim((string) $marketplace->name)),
            strtolower(trim((string) $marketplace->code)),
            strtolower(Str::studly((string) $marketplace->code)),
            strtolower(str_replace(['_', '-', ' '], '', (string) $marketplace->code)),
            strtolower(str_replace(['_', '-', ' '], '', (string) $marketplace->name)),
        ];

        $out = [];
        foreach ($candidates as $c) {
            if ($c !== '') {
                $out[] = $c;
            }
        }

        return array_values(array_unique($out));
    }
}