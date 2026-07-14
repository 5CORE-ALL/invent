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
        'shipping_cost',
        'total_price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the lowest priced competitor for a given SKU
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getLowestPriceForSku($sku, $marketplace = 'ebay')
    {
        // Normalize SKU: remove ALL whitespace (including newlines, tabs), then add single spaces
        $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
        
        // Match using normalized SKU comparison (handles line breaks in database)
        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->where('total_price', '>', 0)
            ->orderBy('total_price', 'asc')
            ->first();
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
                return $items->first();
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

        $lmpEntries = self::dedupeByItemId($lmpEntries);
        self::attachLmpFieldsToRow($row, $lmpEntries, $lmpEntries->first());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $lmpEntries
     */
    private static function attachLmpFieldsToRow(array &$row, $lmpEntries, $lowestLmp = null): void
    {
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
                    'link' => $entry->product_link,
                    'title' => $entry->product_title,
                ];
            })
            ->values()
            ->toArray();
        $row['lmp_entries_total'] = $lmpEntries->count();
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
            'linked_lmp_skus' => [],
        ];
    }

    /**
     * Merge competitor rows from multiple SKUs, keeping one row per eBay item_id.
     *
     * @param  iterable<mixed>  $competitors
     */
    public static function dedupeByItemId(iterable $competitors): \Illuminate\Support\Collection
    {
        $byItemId = [];

        foreach ($competitors as $competitor) {
            $itemId = strtolower(trim((string) ($competitor->item_id ?? '')));
            $key = $itemId !== '' ? 'item:'.$itemId : 'id:'.(string) ($competitor->id ?? spl_object_id($competitor));

            if (! isset($byItemId[$key])) {
                $byItemId[$key] = $competitor;
                continue;
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
