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
        if (preg_match('/(?:spu_name|spuName)=([^&#]+)/i', $link, $m)) {
            return trim(rawurldecode((string) $m[1]));
        }

        return '';
    }

    /**
     * Platform skuCode from Seller Hub JSON / seller-link query params.
     *
     * @param  list<string>  $skus
     * @return array<string, string> requested SKU => skuCode
     */
    public static function skuCodeHintsForSellerSkus(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('shein_listing_statuses')) {
            return [];
        }

        $wanted = static::sellerSkuWantedMap($skus);

        $out = [];
        foreach (static::query()->get(['sku', 'value']) as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = is_array($row->value) ? $row->value : [];
            $code = trim((string) (
                $value['sku_code']
                ?? $value['shein_sku_code']
                ?? $value['platform_sku']
                ?? $value['skuCode']
                ?? ''
            ));
            if ($code === '') {
                $code = static::extractPlatformSkuFromSellerLink((string) ($value['seller_link'] ?? ''));
            }
            if ($code === '' || preg_match('/\s/', $code) || strcasecmp($code, $sku) === 0) {
                continue;
            }
            $requested = static::requestedSkuFromWanted($wanted, $sku);
            if ($requested !== '' && ! isset($out[$requested])) {
                $out[$requested] = $code;
            }
        }

        return $out;
    }

    public static function extractPlatformSkuFromSellerLink(?string $link): string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return '';
        }
        foreach (['sku_code', 'skuCode', 'skc_name', 'skcName'] as $param) {
            if (preg_match('/(?:^|[?&#])'.preg_quote($param, '/').'=([^&#]+)/i', $link, $m)) {
                $code = trim(rawurldecode((string) $m[1]));
                if ($code !== '' && ! preg_match('/\s/', $code) && ! preg_match('/^[a-z]\d{10,}$/i', $code)) {
                    return $code;
                }
            }
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

        $wanted = static::sellerSkuWantedMap($skus);

        $out = [];
        foreach (static::query()->get(['sku', 'value']) as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = is_array($row->value) ? $row->value : [];
            $spu = trim((string) ($value['spu_name'] ?? $value['spuName'] ?? $value['spu_code'] ?? $value['spuCode'] ?? ''));
            if ($spu === '') {
                $spu = static::extractSpuNameFromSellerLink((string) ($value['seller_link'] ?? ''));
            }
            if ($spu === '') {
                $spu = static::extractSpuNameFromSellerLink((string) ($value['buyer_link'] ?? ''));
            }
            if ($spu === '') {
                continue;
            }
            $requested = static::requestedSkuFromWanted($wanted, $sku);
            if ($requested !== '' && ! isset($out[$requested])) {
                $out[$requested] = $spu;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string> alias => requested SKU
     */
    protected static function sellerSkuWantedMap(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $sku) {
            $raw = trim((string) $sku);
            if ($raw === '') {
                continue;
            }
            foreach (\App\Services\SheinApiService::skuAliasesForLookup($raw) as $alias) {
                $wanted[$alias] = $raw;
                $wanted[strtoupper($alias)] = $raw;
            }
        }

        return $wanted;
    }

    /**
     * @param  array<string, string>  $wanted
     */
    protected static function requestedSkuFromWanted(array $wanted, string $listingSku): string
    {
        foreach (\App\Services\SheinApiService::skuAliasesForLookup($listingSku) as $alias) {
            if (isset($wanted[$alias])) {
                return $wanted[$alias];
            }
            $upper = strtoupper($alias);
            if (isset($wanted[$upper])) {
                return $wanted[$upper];
            }
        }

        return '';
    }
}
