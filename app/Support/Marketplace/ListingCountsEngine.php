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

            $listingId = trim((string) ($listedIdBySkuLower[strtolower($sku)] ?? ''));
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

        $map = [];
        $rows = $modelClass::whereIn('sku', $skus)->get(['sku', $column]);
        foreach ($rows as $row) {
            $sku = trim((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            $id = trim((string) ($row->{$column} ?? ''));
            if ($id === '') {
                continue;
            }
            if ($rejectSkuAsId && strcasecmp($id, $sku) === 0) {
                continue;
            }
            $map[strtolower($sku)] = $id;
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
