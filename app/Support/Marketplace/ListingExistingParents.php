<?php

namespace App\Support\Marketplace;

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
        if (! self::supports($channel)) {
            return [];
        }

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
            // An eBay listing with one SKU is a single listing; eBay cannot turn it into a variation listing.
            if (self::isEbay($channel) && $total < 2) {
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
}
