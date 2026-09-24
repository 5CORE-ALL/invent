<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSkuCompetitor extends Model
{
    protected $table = 'google_sku_competitors';

    protected $fillable = [
        'sku',
        'product_id',
        'source',
        'marketplace',
        'search_query',
        'product_link',
        'product_title',
        'image',
        'price',
        'ignored',
        'rating',
        'reviews',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ignored' => 'boolean',
        'rating' => 'decimal:2',
        'reviews' => 'integer',
    ];

    public static function normalizeSkuKey(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $sku)));
    }

    public function scopeWherePositivePrice($query)
    {
        return $query->where('price', '>', 0);
    }

    public function scopeOrderByNumericPrice($query, string $direction = 'asc')
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        return $query->orderByRaw('CAST(price AS DECIMAL(10,2)) ' . $dir);
    }

    public static function lowestFromCollection($items)
    {
        $active = self::applyIgnoreToSameOffers($items)->filter(fn ($item) => ! self::isIgnored($item));

        return $active->sortBy(fn ($item) => (float) ($item->price ?? 0))->first();
    }

    public static function sortCollectionByNumericPrice($items)
    {
        return collect($items)->sortBy(fn ($item) => (float) ($item->price ?? 0))->values();
    }

    public static function offerDedupeKey($item): string
    {
        $productId = strtoupper(trim((string) ($item->product_id ?? '')));
        $source = strtoupper(trim((string) ($item->source ?? '')));
        $link = strtoupper(trim((string) ($item->product_link ?? $item->link ?? '')));
        $key = $productId.'|'.$source.'|'.$link;

        if ($key === '||') {
            return 'id:'.(string) ($item->id ?? spl_object_id($item));
        }

        return $key;
    }

    public static function uniqueOffersFromCollection($items)
    {
        $seen = [];
        $out = collect();
        foreach (self::sortCollectionByNumericPrice(self::applyIgnoreToSameOffers($items)) as $item) {
            $key = self::offerDedupeKey($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out->push($item);
        }

        return $out->values();
    }

    /**
     * If any linked copy of the same Google offer is ignored, every copy is ignored.
     * Otherwise a sibling row revives that price as L1 when the modal reloads.
     *
     * @param  iterable<mixed>  $items
     */
    public static function applyIgnoreToSameOffers($items): \Illuminate\Support\Collection
    {
        $collection = collect($items)->values();
        $ignoredKeys = [];
        foreach ($collection as $item) {
            if (! self::isIgnored($item)) {
                continue;
            }
            $key = self::offerDedupeKey($item);
            if ($key !== '') {
                $ignoredKeys[$key] = true;
            }
        }
        if ($ignoredKeys === []) {
            return $collection;
        }

        return $collection->map(function ($item) use ($ignoredKeys) {
            $key = self::offerDedupeKey($item);
            if ($key === '' || ! isset($ignoredKeys[$key])) {
                return $item;
            }
            if (is_object($item)) {
                $item->ignored = true;
            } elseif (is_array($item)) {
                $item['ignored'] = 1;
            }

            return $item;
        });
    }

    public static function isIgnored($item): bool
    {
        if (! $item) {
            return false;
        }
        $v = is_object($item) ? ($item->ignored ?? null) : ($item['ignored'] ?? null);
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }
        if (is_string($v)) {
            return in_array(strtolower(trim($v)), ['true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Write ignored onto every copy of this offer inside the Sku Link LMP group.
     * A direct column update avoids the boolean-cast dirty check that can skip
     * the save, so Ignore looks set until the modal reloads and then disappears.
     */
    public static function persistIgnored(int $id, bool $ignored): bool
    {
        if ($id < 1) {
            return false;
        }

        $table = (new static)->getTable();
        $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();
        if (! $row) {
            return false;
        }

        $payload = [
            'ignored' => $ignored ? 1 : 0,
            'updated_at' => now(),
        ];

        $productId = trim((string) ($row->product_id ?? ''));
        $groupKeys = [];
        try {
            $groupKeys = \App\Services\LmpSkuGroupService::normalizedGroupKeySet((string) ($row->sku ?? ''));
        } catch (\Throwable $e) {
            $groupKeys = [];
        }
        $own = self::normalizeSkuKey((string) ($row->sku ?? ''));
        if ($own !== '') {
            $groupKeys[$own] = true;
        }

        if ($productId === '') {
            \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->update($payload);

            return true;
        }

        $sourceQuery = \Illuminate\Support\Facades\DB::table($table)->where('product_id', $row->product_id);
        if ($row->source === null || $row->source === '') {
            $sourceQuery->where(function ($query) {
                $query->whereNull('source')->orWhere('source', '');
            });
        } else {
            $sourceQuery->where('source', $row->source);
        }

        $link = strtoupper(trim((string) ($row->product_link ?? '')));
        $ids = [];
        foreach ($sourceQuery->get(['id', 'sku', 'product_link']) as $candidate) {
            $skuKey = self::normalizeSkuKey((string) ($candidate->sku ?? ''));
            if ($skuKey === '' || ! isset($groupKeys[$skuKey])) {
                continue;
            }
            if (strtoupper(trim((string) ($candidate->product_link ?? ''))) !== $link) {
                continue;
            }
            $ids[] = (int) $candidate->id;
        }
        if ($ids === []) {
            $ids = [$id];
        }

        \Illuminate\Support\Facades\DB::table($table)->whereIn('id', $ids)->update($payload);

        return true;
    }

    /**
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'google'): array
    {
        $records = self::where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->get()
            ->groupBy(fn ($item) => self::normalizeSkuKey($item->sku));

        return [
            'details' => $records->map(fn ($items) => self::sortCollectionByNumericPrice($items)),
            'lowest' => $records->map(fn ($items) => self::lowestFromCollection($items)),
        ];
    }

    /**
     * Grid-only LMP: sku → list of {d,p,i} (dedupe key, price, ignored). No images/titles.
     *
     * @return array<string, list<array{d:string,p:float,i:int}>>
     */
    public static function buildLeanOfferLookup(string $marketplace = 'google'): array
    {
        $details = [];
        self::query()
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->select(['id', 'sku', 'price', 'product_id', 'source', 'product_link', 'ignored'])
            ->orderBy('id')
            ->chunkById(3000, function ($rows) use (&$details) {
                foreach ($rows as $row) {
                    $key = self::normalizeSkuKey($row->sku);
                    if ($key === '') {
                        continue;
                    }
                    $price = (float) ($row->price ?? 0);
                    if ($price <= 0) {
                        continue;
                    }
                    $details[$key][] = [
                        'd' => self::offerDedupeKey($row),
                        'p' => $price,
                        'i' => self::isIgnored($row) ? 1 : 0,
                    ];
                }
            });

        return $details;
    }

    /**
     * Lowest price that still counts for L1. An offer is ignored when any
     * linked copy is ignored, so the grid matches the LMP modal without a reload.
     *
     * @param  list<array{d?:string,p?:mixed,i?:mixed}>  $offers
     * @return array{lowest: ?float, ignored_lowest: ?float, total: int}
     */
    public static function summarizeLeanOffers(array $offers): array
    {
        $byKey = [];
        foreach ($offers as $comp) {
            $dedupeKey = (string) ($comp['d'] ?? '');
            if ($dedupeKey === '') {
                continue;
            }
            $price = (float) ($comp['p'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $ignored = ! empty($comp['i']);
            if (! isset($byKey[$dedupeKey])) {
                $byKey[$dedupeKey] = ['p' => $price, 'i' => $ignored];

                continue;
            }
            if ($ignored) {
                $byKey[$dedupeKey]['i'] = true;
            }
            if ($price < $byKey[$dedupeKey]['p']) {
                $byKey[$dedupeKey]['p'] = $price;
            }
        }

        $lowest = null;
        $ignoredLowest = null;
        foreach ($byKey as $offer) {
            if (! empty($offer['i'])) {
                if ($ignoredLowest === null || $offer['p'] < $ignoredLowest) {
                    $ignoredLowest = $offer['p'];
                }

                continue;
            }
            if ($lowest === null || $offer['p'] < $lowest) {
                $lowest = $offer['p'];
            }
        }

        return [
            'lowest' => $lowest !== null ? round($lowest, 2) : null,
            'ignored_lowest' => ($lowest === null && $ignoredLowest !== null) ? round($ignoredLowest, 2) : null,
            'total' => count($byKey),
        ];
    }

    public static function getCompetitorsForSku($sku, $marketplace = 'google')
    {
        $normalizedSku = self::normalizeSkuKey($sku);

        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->orderByNumericPrice('asc')
            ->get();
    }

    /**
     * @param  list<string>  $skus
     */
    public static function getCompetitorsForSkus(array $skus, string $marketplace = 'google')
    {
        $normKeys = [];
        foreach ($skus as $sku) {
            $key = self::normalizeSkuKey((string) $sku);
            if ($key !== '') {
                $normKeys[$key] = true;
            }
        }
        $normKeys = array_keys($normKeys);
        if ($normKeys === []) {
            return collect();
        }

        $records = self::where('marketplace', $marketplace)
            ->wherePositivePrice()
            ->where(function ($query) use ($normKeys) {
                foreach ($normKeys as $key) {
                    $query->orWhereRaw(
                        'UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?',
                        [$key]
                    );
                }
            })
            ->get();

        return self::uniqueOffersFromCollection($records);
    }
}
