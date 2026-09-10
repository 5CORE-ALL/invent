<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Support\Collection;

/**
 * Shared listing-page count loop (same rules as /listing-ebaytwo).
 *
 * Listing pages and /missing-listing share the same universe:
 * ProductMaster (not deleted), non-PARENT, Shopify INV > 0.
 * NRL/REQ from channel DataView.value.NRL (and listing-status overlay).
 * Listed from channel-specific id map (sku_lower → listing id string).
 * Missing L = in-stock REQ SKU not on the marketplace API.
 */
class ListingCountsEngine
{
    private static ?Collection $productMastersMemo = null;

    /** @var list<string>|null */
    private static ?array $productSkusMemo = null;

    private static ?Collection $shopifyMapMemo = null;

    /**
     * @return Collection<int, ProductMaster>
     */
    public static function productMasters(): Collection
    {
        return self::$productMastersMemo ??= ProductMaster::whereNull('deleted_at')->get();
    }

    /**
     * @return list<string>
     */
    public static function productSkus(): array
    {
        return self::$productSkusMemo ??= self::productMasters()
            ->pluck('sku')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * One Shopify INV map per request — /missing-listing used to rebuild this
     * for every channel (~1.7s each) and later channels timed out as 0.
     */
    public static function requestShopifyMap(): Collection
    {
        return self::$shopifyMapMemo ??= self::shopifyMap(self::productSkus());
    }

    /**
     * CP Master SKUs for both listing pages and /missing-listing.
     *
     * @return list<string>
     */
    public static function countUniverseSkus(bool $requirePositiveInv = true): array
    {
        return self::productSkus();
    }

    /**
     * @param  Collection<string, mixed>  $nrValuesBySkuUpper  sku_upper → raw DataView value
     * @param  array<string, string>  $listedIdBySkuLower  sku_lower → non-empty listing id when listed
     * @return array{REQ: int, NRL: int, Listed: int, Pending: int, MissingL: int}
     */
    public static function counts(Collection $nrValuesBySkuUpper, array $listedIdBySkuLower, bool $requirePositiveInv = true): array
    {
        $productMasters = self::productMasters();
        $shopifyData = $requirePositiveInv ? self::requestShopifyMap() : collect();

        $reqCount = 0;
        $nrlCount = 0;
        $listedCount = 0;
        $missingL = 0;

        foreach ($productMasters as $item) {
            $sku = trim((string) $item->sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }

            if ($requirePositiveInv) {
                $inv = self::shopifyInv(self::shopifyRow($shopifyData, $sku, (string) $item->sku));
                if ($inv <= 0) {
                    continue;
                }
            }

            $nrReq = self::nrReqFromDataView(self::lookupNrValue($nrValuesBySkuUpper, $sku));
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
     * Normalize DataView NRL/NR value to REQ|NR.
     * Faire analytics stores NR; Amazon/eBay2 store NRL; listing-status uses nr_req.
     */
    public static function nrReqFromDataView(mixed $raw): string
    {
        if (is_string($raw)) {
            $trimmed = strtoupper(trim($raw));
            if (in_array($trimmed, ['NRL', 'NR'], true)) {
                return 'NR';
            }
            $raw = json_decode($raw, true) ?: [];
        } elseif (! is_array($raw)) {
            $raw = [];
        }

        foreach (['NRL', 'NR', 'NRP', 'nr_req'] as $key) {
            $value = $raw[$key] ?? null;
            if ($value === true || $value === 1 || $value === '1') {
                return 'NR';
            }
            $normalized = strtoupper(trim((string) $value));
            if (in_array($normalized, ['NRL', 'NR'], true)) {
                return 'NR';
            }
        }

        return 'REQ';
    }

    /**
     * Shopify rows keyed by the Product Master SKU and its trimmed form.
     *
     * @param  list<string>  $productSkus
     * @return Collection<string, ShopifySku>
     */
    public static function shopifyMap(array $productSkus): Collection
    {
        $map = ShopifySku::mapByProductSkus($productSkus);
        foreach ($productSkus as $sku) {
            $sku = (string) $sku;
            $trim = trim($sku);
            if ($trim !== '' && ! $map->has($trim) && $map->has($sku)) {
                $map[$trim] = $map->get($sku);
            }
        }

        return $map;
    }

    public static function shopifyRow(Collection $shopifyData, string $sku, string $original = ''): ?ShopifySku
    {
        foreach (array_unique(array_filter([$sku, $original, trim($sku), trim($original)])) as $key) {
            $row = $shopifyData->get($key);
            if ($row instanceof ShopifySku) {
                return $row;
            }
        }

        return ShopifySku::firstForProductSku($sku !== '' ? $sku : $original);
    }

    /**
     * Same INV the listing pages use (shopify_skus.inv), then live available_to_sell.
     */
    public static function shopifyInv(?ShopifySku $row): float
    {
        if ($row === null) {
            return 0.0;
        }
        foreach (['inv', 'available_to_sell'] as $field) {
            $value = $row->{$field} ?? null;
            if ($value !== null && $value !== '' && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return 0.0;
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

        $wanted = [];
        foreach ($skus as $sku) {
            foreach (self::skuLookupKeys((string) $sku) as $key) {
                $wanted[$key] = true;
            }
        }
        if ($wanted === []) {
            return collect();
        }

        $out = collect();
        $dataViewClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'value'])
            ->each(function ($row) use ($out, $wanted) {
                $keys = self::skuLookupKeys((string) $row->sku);
                $matches = false;
                foreach ($keys as $key) {
                    if (isset($wanted[$key])) {
                        $matches = true;
                        break;
                    }
                }
                if (! $matches) {
                    return;
                }
                foreach ($keys as $key) {
                    if (! $out->has($key)) {
                        $out[$key] = $row->value;
                    }
                }
            });

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function skuLookupKeys(string $sku): array
    {
        $sku = trim(str_replace("\xC2\xA0", ' ', $sku));
        if ($sku === '') {
            return [];
        }

        $keys = [
            strtoupper($sku),
            strtoupper(preg_replace('/\s+/u', ' ', $sku) ?? $sku),
            strtoupper(str_replace(' ', '', $sku)),
        ];
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '') {
            $keys[] = strtoupper($norm);
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '') {
            $keys[] = $compact;
            $keys[] = 'c:'.$compact;
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Keys used to match Product Master SKUs to marketplace catalog SKUs.
     * "LS 100-6 RED" and "LS100-6RED" share c:LS1006RED.
     *
     * @return list<string>
     */
    public static function skuIndexKeys(string $sku): array
    {
        $sku = trim(str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $sku));
        if ($sku === '') {
            return [];
        }

        $keys = [];
        $norm = ShopifySku::normalizeSkuForShopifyLookup($sku);
        if ($norm !== '') {
            $keys[] = $norm;
            $lower = strtolower($norm);
            if ($lower !== $norm) {
                $keys[] = $lower;
            }
        }
        $compact = ShopifySku::compactSkuForLookup($sku);
        if ($compact !== '') {
            $keys[] = 'c:'.$compact;
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, true>
     */
    public static function wantedSkuKeySet(array $skus): array
    {
        $wanted = [];
        foreach ($skus as $raw) {
            foreach (self::skuIndexKeys((string) $raw) as $key) {
                $wanted[$key] = true;
            }
        }

        return $wanted;
    }

    /**
     * @param  array<string, string>  $byKey
     * @param  array<string, true>  $wanted
     */
    public static function putListedForSku(array &$byKey, array $wanted, string $sku, string $id): void
    {
        $sku = trim($sku);
        $id = trim($id);
        if ($sku === '' || $id === '') {
            return;
        }

        $keys = self::skuIndexKeys($sku);
        $hit = false;
        foreach ($keys as $key) {
            if (isset($wanted[$key])) {
                $hit = true;
                break;
            }
        }
        if (! $hit) {
            return;
        }

        foreach ($keys as $key) {
            $existing = $byKey[$key] ?? '';
            $preferNew = $existing === '' || (
                strcasecmp($existing, $sku) === 0 && strcasecmp($id, $sku) !== 0
            );
            if ($preferNew) {
                $byKey[$key] = $id;
            }
        }
    }

    /**
     * @param  list<string>  $skus
     * @param  array<string, string>  $byKey
     * @return array<string, string>
     */
    public static function listedMapForProductSkus(array $skus, array $byKey): array
    {
        $map = [];
        foreach ($skus as $raw) {
            $sku = trim((string) $raw);
            if ($sku === '') {
                continue;
            }
            $id = '';
            foreach (self::skuIndexKeys($sku) as $key) {
                $cand = trim((string) ($byKey[$key] ?? ''));
                if ($cand !== '') {
                    $id = $cand;
                    break;
                }
            }
            if ($id === '') {
                continue;
            }
            $map[strtolower($sku)] = $id;
            foreach (self::skuIndexKeys($sku) as $key) {
                $map[$key] = $id;
            }
        }

        return $map;
    }

    /**
     * Uploaded / in-review / unable-to-list is NOT live on the marketplace.
     * Those SKUs may stay in Missing L. "Yes" / live / active must not.
     */
    public static function isPendingOrReviewListingState(?string $state): bool
    {
        $s = strtolower(trim((string) $state));
        $s = preg_replace('/[\s\-_]+/', '', $s) ?? $s;
        if ($s === '') {
            return false;
        }

        return in_array($s, [
            'uploaded',
            'pending',
            'review',
            'reviewing',
            'underreview',
            'inreview',
            'submitted',
            'awaiting',
            'awaitingapproval',
            'approvalpending',
            'unable',
            'unabletolist',
            'rejected',
            'declined',
            'failed',
            'draft',
            'unpublished',
            'unlisted',
            'archived',
        ], true);
    }

    public static function lookupNrValue(Collection $nrValuesBySkuUpper, string $sku): mixed
    {
        foreach (self::skuLookupKeys($sku) as $key) {
            if ($nrValuesBySkuUpper->has($key)) {
                return $nrValuesBySkuUpper->get($key);
            }
        }

        return null;
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

        $try = array_merge([strtolower($sku)], self::skuIndexKeys($sku));
        foreach (array_unique(array_filter($try, fn ($key) => $key !== '')) as $key) {
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

        $wanted = self::wantedSkuKeySet($skus);
        if ($wanted === []) {
            return [];
        }

        $byKey = [];
        $modelClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get(['sku', $column])
            ->each(function ($row) use (&$byKey, $column, $rejectSkuAsId, $wanted) {
                $sku = trim((string) $row->sku);
                $id = trim((string) ($row->{$column} ?? ''));
                if ($sku === '' || $id === '') {
                    return;
                }
                if ($rejectSkuAsId && strcasecmp($id, $sku) === 0) {
                    return;
                }
                self::putListedForSku($byKey, $wanted, $sku, $id);
            });

        return self::listedMapForProductSkus($skus, $byKey);
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

        $wanted = self::wantedSkuKeySet($skus);
        if ($wanted === []) {
            return [];
        }

        $byKey = [];
        $modelClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', $priceColumn])
            ->each(function ($row) use (&$byKey, $priceColumn, $wanted) {
                $sku = trim((string) $row->sku);
                if ($sku === '' || (float) ($row->{$priceColumn} ?? 0) <= 0) {
                    return;
                }
                self::putListedForSku($byKey, $wanted, $sku, $sku);
            });

        return self::listedMapForProductSkus($skus, $byKey);
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

        $wanted = self::wantedSkuKeySet($skus);
        if ($wanted === []) {
            return [];
        }

        $byKey = [];
        $statusClass::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->get(['sku', 'value'])
            ->each(function ($row) use (&$byKey, $wanted) {
                $sku = trim((string) $row->sku);
                if ($sku === '') {
                    return;
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
                    $flag = strtolower(trim($listed));
                    $isListed = $flag === 'listed' || $flag === 'true' || $flag === 'yes';
                    if (self::isPendingOrReviewListingState($listed)) {
                        $isListed = false;
                    }
                }
                $state = (string) ($value['state'] ?? $value['listing_state'] ?? '');
                if (self::isPendingOrReviewListingState($state)) {
                    $isListed = false;
                }
                if (! $isListed) {
                    return;
                }
                $id = trim((string) ($value['listing_id'] ?? $value['item_id'] ?? $sku));
                self::putListedForSku($byKey, $wanted, $sku, $id !== '' ? $id : $sku);
            });

        return self::listedMapForProductSkus($skus, $byKey);
    }
}
