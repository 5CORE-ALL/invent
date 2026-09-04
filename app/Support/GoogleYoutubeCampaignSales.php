<?php

namespace App\Support;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YouTube campaign Sales.
 *
 * Preferred: GA4 actual revenue or Google Ads conversion value once the
 * conversion action is set to transaction value.
 *
 * Fallback (only when that $ is missing or still the old $1 placeholder):
 * sold × Shopify family price.
 */
final class GoogleYoutubeCampaignSales
{
    /**
     * Values at or below this per conversion are treated as the old fixed
     * $1 conversion-action placeholder, not store transaction value.
     */
    private const PLACEHOLDER_VALUE_PER_SALE = 1.51;

    /** @var list<string> */
    private const HOOKS = [
        'USING PRODUCT BUT NO RESULT HOOK',
        'IF I KNEW THIS BEFORE HOOK',
        'UNPOPULAR OPINION HOOK',
        'RELATABLE PAIN HOOK',
        'CURIOSITY GAP HOOK',
        'MYTH-BUSTING HOOK',
        'MISSING OUT HOOK',
    ];

    /**
     * Title matchers per stripped campaign family. First matching SKU set wins.
     *
     * @var array<string, list<string>>
     */
    private const FAMILY_TITLE_LIKES = [
        'MUSIC STAND' => ['%MUSIC STAND%'],
        'DRUM MICS' => ['%DRUM MIC%'],
        'DRUM THRONE' => ['%DRUM THRONE%', '%DRUMMING CHAIR%'],
        'DYNAMIC MICROPHONES' => ['%DYNAMIC MIC%', '%DYNAMIC MICROPHONE%'],
        'CAR AUDIO' => ['%CAR AUDIO%', '%CAR SUBWOOFER%'],
        'GUITAR STOOLS' => ['%GUITAR STOOL%'],
        'GUITAR STANDS AND HOOKS' => ['%GUITAR STAND%', '%GUITAR%HANGER%', '%GUITAR%HOOK%'],
        'KEYBOARD & PIANO BENCHES' => ['%PIANO BENCH%', '%KEYBOARD BENCH%', '%PIANO STOOL%'],
        'MEGAPHONE' => ['%MEGAPHONE%', '%BULL HORN%'],
        'MEGAPHONES' => ['%MEGAPHONE%', '%BULL HORN%'],
        'MIC STAND' => ['%MIC STAND%', '%MICROPHONE STAND%'],
        'RETRO MIC' => ['%RETRO%MIC%'],
        'RETRO MICS' => ['%RETRO%MIC%'],
        'SPEAKER STAND' => ['%SPEAKER STAND%'],
        'SPEAKER STANDS' => ['%SPEAKER STAND%'],
    ];

    /**
     * Keep incoming sales when it is real transaction value (GA4 or Ads).
     * Only then fall back to sold × Shopify family price.
     */
    public static function lift(float $sales, float $sold, string $campaignName): float
    {
        if (! self::needsShopifyPriceFallback($sales, $sold)) {
            return $sales;
        }

        $price = self::priceForCampaign($campaignName);
        if ($price !== null && $price > self::PLACEHOLDER_VALUE_PER_SALE) {
            return round($sold * $price, 2);
        }

        return $sales;
    }

    /**
     * True when Ads/GA4 did not send a usable transaction $ — $0, or the
     * legacy $1-per-conversion placeholder.
     */
    public static function needsShopifyPriceFallback(float $sales, float $sold): bool
    {
        if ($sold <= 0) {
            return false;
        }

        return ($sales / $sold) <= self::PLACEHOLDER_VALUE_PER_SALE;
    }

    public static function priceForCampaign(string $campaignName): ?float
    {
        $family = self::familyFromCampaignName($campaignName);
        if ($family === '') {
            return null;
        }

        $map = self::familyPriceMap();

        return $map[$family] ?? null;
    }

    public static function familyFromCampaignName(string $campaignName): string
    {
        $n = preg_replace('/\s+/', ' ', strtoupper(trim($campaignName))) ?? '';
        $n = preg_replace('/\s+YT$/', '', $n) ?? $n;
        foreach (self::HOOKS as $hook) {
            $suffix = ' '.$hook;
            if (str_ends_with($n, $suffix)) {
                return trim(substr($n, 0, -strlen($suffix)));
            }
        }

        return trim($n);
    }

    /**
     * @return array<string, float>
     */
    private static function familyPriceMap(): array
    {
        return Cache::remember('gads_yt_family_shopify_price_v1', 900, static function (): array {
            if (! Schema::hasTable('shopify_skus')) {
                return [];
            }

            $out = [];
            foreach (array_keys(self::FAMILY_TITLE_LIKES) as $family) {
                $price = self::lookupFamilyPrice($family);
                if ($price !== null) {
                    $out[$family] = $price;
                }
            }

            return $out;
        });
    }

    private static function lookupFamilyPrice(string $family): ?float
    {
        $likes = self::FAMILY_TITLE_LIKES[$family] ?? [];
        if ($likes === []) {
            return null;
        }

        $q = DB::table('shopify_skus')->where('price', '>', 1);
        $q->where(function ($w) use ($likes) {
            foreach ($likes as $i => $like) {
                if ($i === 0) {
                    $w->whereRaw('UPPER(product_title) LIKE ?', [$like]);
                } else {
                    $w->orWhereRaw('UPPER(product_title) LIKE ?', [$like]);
                }
            }
        });

        if ($family === 'DYNAMIC MICROPHONES') {
            $q->whereRaw('UPPER(product_title) NOT LIKE ?', ['%DRUM MIC%']);
        }
        if ($family === 'MIC STAND') {
            $q->whereRaw('UPPER(product_title) NOT LIKE ?', ['%MUSIC STAND%']);
        }
        if ($family === 'GUITAR STOOLS') {
            $q->whereRaw('UPPER(product_title) NOT LIKE ?', ['%DRUM THRONE%']);
        }
        if ($family === 'MUSIC STAND') {
            $q->whereRaw('UPPER(product_title) NOT LIKE ?', ['%CLIP%']);
        }

        $rows = $q->get(['price', 'inv']);
        $priced = $rows->filter(fn ($r) => is_numeric($r->price) && (float) $r->price > 1);
        $inStock = $priced->filter(fn ($r) => (float) ($r->inv ?? 0) > 0);
        $use = $inStock->isNotEmpty() ? $inStock : $priced;
        $median = self::median($use->map(fn ($r) => (float) $r->price)->values()->all());
        if ($median !== null) {
            return $median;
        }

        return self::priceFromProductMasterParent($family);
    }

    private static function priceFromProductMasterParent(string $family): ?float
    {
        if (! Schema::hasTable('product_master')) {
            return null;
        }

        $needles = [$family];
        if (str_ends_with($family, 'S') && ! str_ends_with($family, 'SS')) {
            $needles[] = substr($family, 0, -1);
        }

        $skus = ProductMaster::query()
            ->where(function ($q) use ($needles) {
                foreach ($needles as $n) {
                    $q->orWhereRaw('UPPER(parent) LIKE ?', ['%'.$n.'%'])
                        ->orWhereRaw('UPPER(sku) LIKE ?', ['%'.$n.'%']);
                }
            })
            ->pluck('sku')
            ->filter()
            ->all();

        if ($skus === []) {
            return null;
        }

        $prices = ShopifySku::query()
            ->whereIn('sku', $skus)
            ->where('price', '>', 1)
            ->pluck('price')
            ->map(fn ($p) => (float) $p)
            ->filter(fn ($p) => $p > 1)
            ->values()
            ->all();

        return self::median($prices);
    }

    /**
     * @param  list<float>  $values
     */
    private static function median(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn ($v) => is_finite((float) $v) && $v > 0));
        $n = count($values);
        if ($n === 0) {
            return null;
        }
        sort($values);
        $mid = intdiv($n, 2);
        $raw = $n % 2 === 1 ? $values[$mid] : (($values[$mid - 1] + $values[$mid]) / 2);

        return round($raw, 2);
    }
}
