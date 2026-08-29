<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EbaySkuCompetitor extends Model
{
    protected $table = 'ebay_sku_competitors';

    protected $fillable = [
        'sku',
        'item_id',
        'marketplace',
        'product_link',
        'image',
        'product_title',
        'price',
        'ignored',
        'shipping_cost',
        'total_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ignored' => 'boolean',
        'shipping_cost' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Whether a competitor is excluded from L1 / LMP column count.
     * Reads raw attributes so Eloquent casts / empty() cannot hide ignored=1.
     */
    public static function isIgnored($item): bool
    {
        $v = false;
        if (is_array($item)) {
            $v = $item['ignored'] ?? false;
        } elseif (is_object($item)) {
            if (method_exists($item, 'getAttributes')) {
                $attrs = $item->getAttributes();
                if (array_key_exists('ignored', $attrs)) {
                    $v = $attrs['ignored'];
                } elseif (method_exists($item, 'getRawOriginal') && $item->getRawOriginal('ignored') !== null) {
                    $v = $item->getRawOriginal('ignored');
                } else {
                    $v = $item->ignored ?? false;
                }
            } else {
                $v = $item->ignored ?? false;
            }
        }
        if (is_bool($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v !== 0;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Write ignored to the DB column (tinyint 0/1). Avoids Eloquent boolean-cast
     * dirty-checks that can skip the UPDATE so Ignore looks saved until refresh.
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
        $itemId = trim((string) ($row->item_id ?? ''));
        $q = \Illuminate\Support\Facades\DB::table($table);
        if ($itemId !== '') {
            $q->where('item_id', $itemId);
            if (! empty($row->marketplace)) {
                $q->where('marketplace', $row->marketplace);
            }
        } else {
            $q->where('id', $id);
        }
        $q->update($payload);

        return true;
    }

    /**
     * If any copy of an eBay listing is ignored, treat every SKU copy of that item_id as ignored.
     *
     * @param  iterable<mixed>  $items
     */
    public static function applyIgnoreToSameItemIds($items): \Illuminate\Support\Collection
    {
        $collection = collect($items)->values();
        $ignoredItemIds = [];
        foreach ($collection as $item) {
            if (! self::isIgnored($item)) {
                continue;
            }
            $itemId = strtolower(trim((string) (is_object($item) ? ($item->item_id ?? '') : ($item['item_id'] ?? ''))));
            if ($itemId !== '') {
                $ignoredItemIds[$itemId] = true;
            }
        }
        if ($ignoredItemIds === []) {
            return $collection;
        }

        return $collection->map(function ($item) use ($ignoredItemIds) {
            $itemId = strtolower(trim((string) (is_object($item) ? ($item->item_id ?? '') : ($item['item_id'] ?? ''))));
            if ($itemId === '' || ! isset($ignoredItemIds[$itemId])) {
                return $item;
            }
            if (is_object($item)) {
                $item->ignored = true;
            } elseif (is_array($item)) {
                $item['ignored'] = true;
            }

            return $item;
        })->values();
    }

    /**
     * @param  iterable<mixed>  $items
     */
    public static function withoutIgnored($items): \Illuminate\Support\Collection
    {
        return collect($items)->filter(fn ($item) => ! self::isIgnored($item))->values();
    }

    /**
     * Get the lowest priced competitor for a given SKU
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getLowestPriceForSku($sku, $marketplace = 'ebay')
    {
        // Normalize SKU: remove ALL whitespace (including newlines, tabs), then add single spaces
        $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
        
        // Match using normalized SKU comparison (handles line breaks in database)
        $q = self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->where('total_price', '>', 0)
            ->orderBy('total_price', 'asc');
        if (\Illuminate\Support\Facades\Schema::hasColumn('ebay_sku_competitors', 'ignored')) {
            $q->where(function ($qq) {
                $qq->where('ignored', false)->orWhereNull('ignored');
            });
        }

        return $q->get()->first(fn ($row) => ! self::isIgnored($row));
    }

    /**
     * Get all competitors for a given SKU ordered by price
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getCompetitorsForSku($sku, $marketplace = 'ebay')
    {
        // Normalize SKU: remove ALL whitespace (including newlines, tabs), then add single spaces
        $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
        
        // Match using normalized SKU comparison (handles line breaks in database)
        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->where('total_price', '>', 0)
            ->orderBy('total_price', 'asc')
            ->get();
    }

    /**
     * Pre-load LMP competitors grouped by normalized SKU for bulk tabulator views.
     *
     * @return array{details: \Illuminate\Support\Collection, lowest: \Illuminate\Support\Collection}
     */
    public static function buildGroupedLookup(string $marketplace = 'ebay'): array
    {
        $lmpRecords = self::where('marketplace', $marketplace)
            ->where('total_price', '>', 0)
            ->orderBy('total_price', 'asc')
            ->get()
            ->groupBy(function ($item) {
                return strtoupper(preg_replace('/\s+/', ' ', trim($item->sku)));
            });

        return [
            'details' => $lmpRecords,
            'lowest' => $lmpRecords->map(function ($items) {
                $active = self::withoutIgnored($items);

                return $active->isNotEmpty() ? $active->first() : null;
            }),
        ];
    }

    public static function normalizeSkuKey(?string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $sku)));
    }

    /**
     * Candidate SKU keys for LMP lookup (exact SKU, base SKU, common suffix variants).
     *
     * @return list<string>
     */
    public static function resolveLookupKeys(string $sku, ?string $fallbackSku = null): array
    {
        $keys = [];
        $add = function (?string $value) use (&$keys): void {
            $key = self::normalizeSkuKey($value);
            if ($key !== '') {
                $keys[] = $key;
            }
        };

        $add($sku);
        if ($fallbackSku) {
            $add($fallbackSku);
        }

        $normalized = self::normalizeSkuKey($sku);
        foreach ([' OPEN BOX', ' USED', ' 4PCS', ' 3PCS', ' 2PCS', ' WoG', ' WOG'] as $suffix) {
            if (str_ends_with($normalized, $suffix)) {
                $add(trim(substr($normalized, 0, -strlen($suffix))));
            }
        }

        if (str_starts_with($normalized, 'PARENT ')) {
            $add(trim(substr($normalized, 7)));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Attach LMP fields to a tabulator row from pre-loaded lookup maps.
     */
    public static function applyToRow(array &$row, string $sku, $lmpLowestLookup, $lmpDetailsLookup, ?string $fallbackSku = null): void
    {
        $lmpEntries = collect();
        $lowestLmp = null;

        foreach (self::resolveLookupKeys($sku, $fallbackSku) as $skuLookupKey) {
            $entries = $lmpDetailsLookup->get($skuLookupKey);
            if ($entries instanceof \Illuminate\Support\Collection && $entries->isNotEmpty()) {
                $lmpEntries = $entries;
                $lowestLmp = $lmpLowestLookup->get($skuLookupKey);
                break;
            }
        }

        $lmpEntries = self::applyIgnoreToSameItemIds($lmpEntries);
        $lowestLmp = $lmpEntries->first(fn ($e) => ! self::isIgnored($e));
        self::attachLmpFieldsToRow($row, $lmpEntries, $lowestLmp);
    }

    /**
     * Attach LMP fields merging competitors across Sku Link LMP group members
     * (same behavior as /ebay-tabulator-view).
     *
     * @param  list<string>  $linkedLmpSkus
     */
    public static function applyLinkedGroupToRow(
        array &$row,
        string $sku,
        $lmpDetailsLookup,
        array $linkedLmpSkus,
        ?string $fallbackSku = null
    ): void {
        $row['linked_lmp_skus'] = $linkedLmpSkus;

        $lmpEntries = collect();
        $skusToLookup = $linkedLmpSkus !== [] ? $linkedLmpSkus : [$sku];

        foreach ($skusToLookup as $linkedSku) {
            $member = (string) $linkedSku;
            $memberFallback = (strcasecmp($member, $sku) === 0) ? $fallbackSku : null;
            foreach (self::resolveLookupKeys($member, $memberFallback) as $skuLookupKey) {
                $entries = $lmpDetailsLookup->get($skuLookupKey);
                if ($entries instanceof \Illuminate\Support\Collection && $entries->isNotEmpty()) {
                    $lmpEntries = $lmpEntries->merge($entries);
                }
            }
        }

        $lmpEntries = self::applyIgnoreToSameItemIds($lmpEntries);
        $lmpEntries = self::dedupeByItemId($lmpEntries, $sku);
        // L1 = lowest non-ignored (same as Temu)
        $lowest = $lmpEntries->first(fn ($e) => ! self::isIgnored($e));
        self::attachLmpFieldsToRow($row, $lmpEntries, $lowest);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $lmpEntries
     */
    private static function attachLmpFieldsToRow(array &$row, $lmpEntries, $lowestLmp = null): void
    {
        $active = self::withoutIgnored($lmpEntries);
        if (! $lowestLmp || self::isIgnored($lowestLmp)) {
            $lowestLmp = $active->first();
        }
        $row['lmp_price'] = ($lowestLmp && isset($lowestLmp->total_price) && is_numeric($lowestLmp->total_price))
            ? floatval($lowestLmp->total_price)
            : null;
        $row['lmp_link'] = $lowestLmp->product_link ?? null;
        $row['lmp_item_id'] = $lowestLmp->item_id ?? null;
        $row['lmp_title'] = $lowestLmp->product_title ?? null;
        $row['lmp_entries'] = $lmpEntries
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'item_id' => $entry->item_id,
                    'price' => floatval($entry->price ?? 0),
                    'shipping_cost' => floatval($entry->shipping_cost ?? 0),
                    'total_price' => floatval($entry->total_price ?? 0),
                    'ignored' => self::isIgnored($entry),
                    'link' => $entry->product_link,
                    'title' => $entry->product_title,
                ];
            })
            ->values()
            ->toArray();
        // Column (N) = active competitors only; ignored rows stay in lmp_entries for the modal.
        $row['lmp_entries_total'] = $active->filter(function ($entry) {
            return (float) ($entry->total_price ?? 0) > 0;
        })->count();
        $lowestIgnored = $active->isEmpty()
            ? $lmpEntries->first(fn ($e) => (float) ($e->total_price ?? 0) > 0)
            : null;
        $row['lmp_ignored_price'] = ($lowestIgnored && is_numeric($lowestIgnored->total_price ?? null))
            ? floatval($lowestIgnored->total_price)
            : null;
    }

    /** @return array<string, mixed> */
    public static function emptyRowFields(): array
    {
        return [
            'lmp_price' => null,
            'lmp_link' => null,
            'lmp_item_id' => null,
            'lmp_title' => null,
            'lmp_entries' => [],
            'lmp_entries_total' => 0,
            'lmp_ignored_price' => null,
            'linked_lmp_skus' => [],
        ];
    }

    /**
     * Merge competitor rows from multiple SKUs, keeping one row per eBay item_id.
     * When the same listing was pulled onto sibling SKUs (4PCS vs 2PCS, WH vs BLK),
     * keep the row that belongs to $preferSku so L1 is that variation's BIN.
     *
     * @param  iterable<mixed>  $competitors
     */
    public static function dedupeByItemId(iterable $competitors, ?string $preferSku = null): \Illuminate\Support\Collection
    {
        $byItemId = [];
        $prefer = self::normalizeSkuKey($preferSku);

        foreach ($competitors as $competitor) {
            $itemId = strtolower(trim((string) ($competitor->item_id ?? '')));
            $key = $itemId !== '' ? 'item:'.$itemId : 'id:'.(string) ($competitor->id ?? spl_object_id($competitor));

            if (! isset($byItemId[$key])) {
                $byItemId[$key] = $competitor;
                continue;
            }

            if ($prefer !== '') {
                $existingMatch = self::normalizeSkuKey($byItemId[$key]->sku ?? '') === $prefer;
                $candidateMatch = self::normalizeSkuKey($competitor->sku ?? '') === $prefer;
                if ($candidateMatch && ! $existingMatch) {
                    $byItemId[$key] = $competitor;
                    continue;
                }
                if ($existingMatch && ! $candidateMatch) {
                    continue;
                }
            }

            $existingPrice = (float) ($byItemId[$key]->total_price ?? 0);
            $candidatePrice = (float) ($competitor->total_price ?? 0);

            if ($candidatePrice > 0 && ($existingPrice <= 0 || $candidatePrice < $existingPrice)) {
                $byItemId[$key] = $competitor;
            }
        }

        return collect(array_values($byItemId))
            ->sortBy(fn ($entry) => (float) ($entry->total_price ?? 0))
            ->values();
    }
}
