<?php

namespace App\Models;

use App\Models\ShopifySku;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SheinListingStatus extends Model
{
    protected $table = 'shein_listing_statuses';
    protected $fillable = ['sku', 'value'];
    protected $casts = ['value' => 'array'];

    /**
     * Seller parent SKUs marked Listed (includes Seller Hub Pending with `--` stock).
     *
     * @return list<string>
     */
    public static function listedSellerSkus(): array
    {
        if (! Schema::hasTable('shein_listing_statuses')) {
            return [];
        }

        $out = [];
        foreach (static::query()->get(['sku', 'value']) as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = is_array($row->value) ? $row->value : [];
            if (strtolower(trim((string) ($value['listed'] ?? ''))) !== 'listed') {
                continue;
            }
            $out[$sku] = true;
        }

        return array_keys($out);
    }

    public static function extractSpuNameFromSellerLink(?string $link): string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return '';
        }
        if (preg_match('/spu_name=([^&#]+)/i', $link, $m)) {
            return trim(rawurldecode((string) $m[1]));
        }

        return '';
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string> requested SKU => SPU name
     */
    public static function spuNamesForSellerSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('shein_listing_statuses')) {
            return [];
        }

        $wanted = [];
        foreach ($skus as $sku) {
            $raw = trim((string) $sku);
            if ($raw === '') {
                continue;
            }
            $wanted[$raw] = $raw;
            $norm = ShopifySku::normalizeSkuForShopifyLookup($raw);
            if ($norm !== '') {
                $wanted[$norm] = $raw;
            }
        }

        $out = [];
        foreach (static::query()->get(['sku', 'value']) as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = is_array($row->value) ? $row->value : [];
            $spu = static::extractSpuNameFromSellerLink((string) ($value['seller_link'] ?? ''));
            if ($spu === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            foreach (array_unique(array_filter([$sku, $norm])) as $key) {
                if (isset($wanted[$key]) && ! isset($out[$wanted[$key]])) {
                    $out[$wanted[$key]] = $spu;
                }
            }
        }

        return $out;
    }
}
