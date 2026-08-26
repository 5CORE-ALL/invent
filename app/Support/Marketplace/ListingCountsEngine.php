<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

/**
 * Shared listing-page count loop (same rules as /listing-ebaytwo).
 *
 * Universe: ProductMaster (not deleted), non-PARENT, Shopify INV > 0.
 * NRL/REQ from channel DataView.value.NRL.
 * Listed from channel-specific id map (sku_lower → listing id string).
 * Missing L = REQ and not listed.
 */
class ListingCountsEngine
{
    /**
     * @param  Collection<string, mixed>  $nrValuesBySkuUpper  sku_upper → raw DataView value
     * @param  array<string, string>  $listedIdBySkuLower  sku_lower → non-empty listing id when listed
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(Collection $nrValuesBySkuUpper, array $listedIdBySkuLower): array
    {
        $productMasters = ProductMaster::whereNull('deleted_at')->get();
        $skus = $productMasters->pluck('sku')->unique()->filter()->values()->all();
        $shopifyData = ShopifySku::mapByProductSkus($skus);

        $reqCount = 0;
        $nrlCount = 0;
        $listedCount = 0;
        $missingL = 0;

        foreach ($productMasters as $item) {
            $sku = trim((string) $item->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            $inv = (float) ($shopifyData[$sku]->inv ?? 0);
            if ($inv <= 0) {
                continue;
            }

            $nrReq = self::nrReqFromDataView($nrValuesBySkuUpper->get(strtoupper($sku)));
            if ($nrReq === 'REQ') {
                $reqCount++;
            } else {
                $nrlCount++;
            }

            $listingId = self::listingIdFromMap($listedIdBySkuLower, $sku);
            if ($listingId !== '') {
                $listedCount++;
            } elseif ($nrReq === 'REQ') {
                $missingL++;
            }
        }

        return [
            'REQ' => $reqCount,
            'NRL' => $nrlCount,
            'Listed' => $listedCount,
            'Pending' => $missingL,
            'MissingL' => $missingL,
        ];
    }

    /**
     * Normalize DataView NRL value to REQ|NR (EbayTwo default).
     */
    public static function nrReqFromDataView(mixed $raw): string
    {
        if (! is_array($raw)) {
            $raw = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        }
        $nrlRaw = strtoupper(trim((string) ($raw['NRL'] ?? '')));

        return in_array($nrlRaw, ['NRL', 'NR'], true) ? 'NR' : 'REQ';
    }

    /**
     * @param  class-string  $dataViewClass
     * @param  list<string>  $skus
     * @return Collection<string, mixed>
     */
    public static function loadNrValues(string $dataViewClass, array $skus): Collection
    {
        if ($dataViewClass === '' || ! class_exists($dataViewClass) || $skus === []) {
            return collect();
        }

        return $dataViewClass::whereIn('sku', $skus)
            ->get(['sku', 'value'])
            ->mapWithKeys(function ($row) {
                return [strtoupper(trim((string) $row->sku)) => $row->value];
            });
    }

    /**
     * Resolve a listing id with exact and normalized SKU keys
     * (NBSP / hyphen variants like "DS CH YLW REST-LVR").
     *
     * @param  array<string, string>  $listedIdBySkuLower
     */
    public static function listingIdFromMap(array $listedIdBySkuLower, string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '' || $listedIdBySkuLower === []) {
            return '';
        }

        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        foreach (array_unique(array_filter([
            strtolower($sku),
            $norm,
            strtolower($norm),
        ], fn ($key) => $key !== '')) as $key) {
            $id = trim((string) ($listedIdBySkuLower[$key] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * Load sku_lower → id from a metric/product model column (non-empty string = listed).
     *
     * @param  class-string  $modelClass
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedIdsFromColumn(string $modelClass, array $skus, string $column, bool $rejectSkuAsId = false): array
    {
        if ($modelClass === '' || ! class_exists($modelClass) || $skus === []) {
            return [];
        }

        $wantedNorm = [];
        foreach ($skus as $rawSku) {
            $norm = ShopifySku::normalizeSkuForShopifyLookup((string) $rawSku);
            if ($norm !== '') {
                $wantedNorm[$norm] = true;
            }
        }
        if ($wantedNorm === []) {
            return [];
        }

        $byNorm = [];
        $modelClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get(['sku', $column])
            ->each(function ($row) use (&$byNorm, $column, $rejectSkuAsId, $wantedNorm) {
                $sku = trim((string) $row->sku);
                $id = trim((string) ($row->{$column} ?? ''));
                if ($sku === '' || $id === '') {
                    return;
                }
                if ($rejectSkuAsId && strcasecmp($id, $sku) === 0) {
                    return;
                }
                $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
                if ($norm === '' || ! isset($wantedNorm[$norm]) || isset($byNorm[$norm])) {
                    return;
                }
                $byNorm[$norm] = $id;
            });

        $map = [];
        foreach ($skus as $rawSku) {
            $sku = trim((string) $rawSku);
            if ($sku === '') {
                continue;
            }
            $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
            if ($norm === '' || ! isset($byNorm[$norm])) {
                continue;
            }
            $id = $byNorm[$norm];
            $map[strtolower($sku)] = $id;
            $map[$norm] = $id;
            $map[strtolower($norm)] = $id;
        }

        return $map;
    }

    /**
     * Listed when numeric price column > 0. Listing id = sku (presence marker).
     *
     * @param  class-string  $modelClass
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedIdsFromPrice(string $modelClass, array $skus, string $priceColumn = 'price'): array
    {
        if ($modelClass === '' || ! class_exists($modelClass) || $skus === []) {
            return [];
        }

        $map = [];
        $rows = $modelClass::whereIn('sku', $skus)->get(['sku', $priceColumn]);
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            if ((float) ($row->{$priceColumn} ?? 0) > 0) {
                $map[strtolower($sku)] = $sku;
            }
        }

        return $map;
    }

    /**
     * Last-resort listed map from ListingStatus.value.listed === 'Listed'.
     *
     * @param  class-string  $statusClass
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    public static function listedIdsFromStatus(string $statusClass, array $skus): array
    {
        if ($statusClass === '' || ! class_exists($statusClass) || $skus === []) {
            return [];
        }

        $map = [];
        $rows = $statusClass::whereIn('sku', $skus)->get(['sku', 'value']);
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $value = $row->value;
            if (! is_array($value)) {
                $value = is_string($value) ? (json_decode($value, true) ?: []) : [];
            }
            $listed = $value['listed'] ?? $value['Listed'] ?? null;
            $isListed = false;
            if (is_bool($listed)) {
                $isListed = $listed;
            } elseif (is_string($listed)) {
                $isListed = strcasecmp(trim($listed), 'Listed') === 0 || strtolower(trim($listed)) === 'true';
            }
            if ($isListed) {
                $map[strtolower($sku)] = $sku;
            }
        }

        return $map;
    }
}
