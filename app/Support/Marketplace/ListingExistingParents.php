<?php

namespace App\Support\Marketplace;

use App\Models\ProductMaster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * "Add to an existing parent listing" support for the Publish variation listing window.
 *
 * Only channels whose publish service can really attach a SKU to a live parent listing
 * report support here; for every other channel the window keeps its old behaviour.
 */
final class ListingExistingParents
{
    /** Channels (after ListingChannelCounts::normalize) that can attach to a live parent. */
    private const SUPPORTED = [
        'ebay', 'ebay1', 'ebayone',
        'ebay2', 'ebaytwo',
        'ebay3', 'ebaythree',
        'aliexpress',
    ];

    public static function supports(string $channel): bool
    {
        return in_array(ListingChannelCounts::normalize($channel), self::SUPPORTED, true);
    }

    public static function isEbay(string $channel): bool
    {
        return str_starts_with(ListingChannelCounts::normalize($channel), 'ebay');
    }

    /**
     * Live parent listings that already hold siblings of this group.
     *
     * @param  list<string>  $groupSkus  every child SKU of the group (listed or not)
     * @param  array<string, string>  $listedMap  lower-case SKU => listing id (marketplace's own id)
     * @return list<array{id: string, title: string, skus: list<string>, sku_count: int}>
     */
    public static function forGroup(string $channel, array $groupSkus, array $listedMap): array
    {
        $byId = [];
        foreach ($groupSkus as $sku) {
            $sku = trim((string) $sku);
            if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                continue;
            }
            $id = trim((string) ($listedMap[strtolower($sku)] ?? ''));
            if ($id !== '') {
                $byId[$id][] = $sku;
            }
        }
        if ($byId === []) {
            return [];
        }

        $titles = [];
        $totals = [];
        $cfg = ChannelListingRegistry::get($channel);
        $model = (string) ($cfg['listed']['model'] ?? '');
        $column = (string) ($cfg['listed']['column'] ?? '');
        if (self::isEbay($channel) && $model !== '' && class_exists($model) && $column !== '') {
            try {
                $table = (new $model())->getTable();
                if (Schema::hasTable($table)) {
                    $ids = array_keys($byId);
                    $totals = $model::query()->whereIn($column, $ids)
                        ->selectRaw($column.' as listing_id, COUNT(*) as n')
                        ->groupBy($column)
                        ->pluck('n', 'listing_id')
                        ->all();
                    if (Schema::hasColumn($table, 'ebay_title')) {
                        $titles = $model::query()->whereIn($column, $ids)
                            ->whereNotNull('ebay_title')
                            ->pluck('ebay_title', $column)
                            ->all();
                    }
                }
            } catch (\Throwable) {
            }
        }

        $out = [];
        foreach ($byId as $id => $skus) {
            $total = (int) ($totals[$id] ?? count($skus));
            // A listing with one SKU is a single listing, not a parent (eBay cannot turn it into a variation listing).
            if ($total < self::minSkus($channel)) {
                continue;
            }
            $out[] = [
                'id' => (string) $id,
                'title' => trim((string) ($titles[$id] ?? '')),
                'skus' => array_values(array_unique($skus)),
                'sku_count' => max($total, count($skus)),
            ];
        }
        usort($out, static fn ($a, $b) => $b['sku_count'] <=> $a['sku_count']);

        return $out;
    }

    /** Fewest SKUs a live listing must hold to count as a parent. */
    private static function minSkus(string $channel): int
    {
        return ListingChannelCounts::normalize($channel) === 'aliexpress' ? 1 : 2;
    }

    /**
     * Every parent (variation) listing on this marketplace, searchable by parent name, SKU,
     * listing id or title. Works for any marketplace whose listing ids are stored in the database.
     *
     * @return list<array{id: string, title: string, parent: string, skus: list<string>, sku_count: int}>
     */
    public static function search(string $channel, string $query = '', string $preferParent = '', int $limit = 60): array
    {
        $key = ListingChannelCounts::normalize($channel);
        if ($key === '') {
            return [];
        }

        $all = Cache::remember('lp_existing_parents_v1:'.$key, 300, static fn () => self::buildAll($channel));

        $q = strtolower(trim($query));
        $prefer = strtolower(trim($preferParent));
        $hits = [];
        foreach ($all as $row) {
            if ($q !== '') {
                $hay = strtolower($row['id'].' '.$row['parent'].' '.$row['title'].' '.implode(' ', $row['skus']));
                $match = true;
                foreach (preg_split('/\s+/', $q) ?: [] as $word) {
                    if ($word !== '' && ! str_contains($hay, $word)) {
                        $match = false;
                        break;
                    }
                }
                if (! $match) {
                    continue;
                }
            }
            $row['_rank'] = ($prefer !== '' && strtolower($row['parent']) === $prefer) ? 0 : 1;
            $hits[] = $row;
        }

        usort($hits, static fn ($a, $b) => [$a['_rank'], -$a['sku_count']] <=> [$b['_rank'], -$b['sku_count']]);

        return array_map(static function ($row) {
            unset($row['_rank']);

            return $row;
        }, array_slice($hits, 0, max(1, $limit)));
    }

    /**
     * @return list<array{id: string, title: string, parent: string, skus: list<string>, sku_count: int}>
     */
    private static function buildAll(string $channel): array
    {
        $cfg = ChannelListingRegistry::get($channel);
        if ($cfg === null) {
            return [];
        }

        $products = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereNotNull('sku')
            ->whereRaw("UPPER(TRIM(sku)) NOT LIKE 'PARENT%'")
            ->get(['sku', 'parent']);
        $skus = [];
        $parentOf = [];
        foreach ($products as $p) {
            $sku = trim((string) $p->sku);
            if ($sku === '') {
                continue;
            }
            $skus[] = $sku;
            $parentOf[strtolower($sku)] = trim((string) $p->parent);
        }

        try {
            $listedMap = ChannelListingRegistry::loadListedIds($cfg, $skus);
        } catch (\Throwable) {
            return [];
        }

        $byId = [];
        foreach ($skus as $sku) {
            $id = trim((string) ($listedMap[strtolower($sku)] ?? ''));
            if ($id === '' || strcasecmp($id, $sku) === 0) {
                continue;
            }
            $byId[$id][] = $sku;
        }

        $titles = [];
        $model = (string) ($cfg['listed']['model'] ?? '');
        $column = (string) ($cfg['listed']['column'] ?? '');
        if (self::isEbay($channel) && $model !== '' && class_exists($model) && $column !== '') {
            try {
                $table = (new $model())->getTable();
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'ebay_title')) {
                    $titles = $model::query()->whereIn($column, array_keys($byId))
                        ->whereNotNull('ebay_title')
                        ->pluck('ebay_title', $column)
                        ->all();
                }
            } catch (\Throwable) {
            }
        }

        $min = self::minSkus($channel);
        $out = [];
        foreach ($byId as $id => $listedSkus) {
            $listedSkus = array_values(array_unique($listedSkus));
            if (count($listedSkus) < $min) {
                continue;
            }
            $counts = [];
            foreach ($listedSkus as $sku) {
                $name = $parentOf[strtolower($sku)] ?? '';
                if ($name !== '') {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
            arsort($counts);
            $out[] = [
                'id' => (string) $id,
                'title' => trim((string) ($titles[$id] ?? '')),
                'parent' => (string) (array_key_first($counts) ?? ''),
                'skus' => $listedSkus,
                'sku_count' => count($listedSkus),
            ];
        }

        usort($out, static fn ($a, $b) => $b['sku_count'] <=> $a['sku_count']);

        return $out;
    }
}
