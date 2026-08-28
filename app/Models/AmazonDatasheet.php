<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AmazonDatasheet extends Model
{
    use HasFactory;

    protected $table = 'amazon_datsheets';

    protected $fillable = [
        'units_ordered_l7',
        'units_ordered_l15',
        'units_ordered_l30',
        'units_ordered_l60',
        'units_ordered_l90',
        'sessions_l1',
        'sessions_l7',
        'sessions_l15',
        'sessions_l30',
        'sessions_l60',
        'sessions_l90',
        'buy_box_percentage',
        'asin',
        'amazon_title',
        'sku',
        'price',
        'organic_views',
        'sold',
        'listing_status',
    ];

    /**
     * Collapse NBSP and runs of whitespace; uppercase. Used to prefer an exact
     * Product Master MSKU when multiple amazon_datsheets rows share one lookup key.
     */
    public static function normalizeSkuSpaces(?string $sku): string
    {
        if ($sku === null || $sku === '') {
            return '';
        }

        return strtoupper(preg_replace('/\s+/u', ' ', trim(str_replace("\xC2\xA0", ' ', $sku))) ?? '');
    }

    /**
     * Normalize a SKU the same way the amazon-tabulator-view page does for its
     * amazon_datsheets lookup: uppercase, NBSP -> space, trim, then remove ALL
     * spaces (so "DP 200 1 Pcs" and "DP200 1Pcs" map to the same key). Used so
     * /map-issues resolves the same listed/stock rows as the Amazon page.
     */
    public static function normalizeSkuForLookup(?string $sku): string
    {
        if ($sku === null) {
            return '';
        }
        $clean = strtoupper(str_replace("\xC2\xA0", ' ', trim($sku)));
        $clean = str_replace(' ', '', $clean);

        // Fold trailing piece-count spelling drift so "2 PCS" / "2PCS" / "2PIECES"
        // all match the datasheet's "2PC" (same idea as ReverbProduct::normalizeSkuForLookup).
        return preg_replace('/(\d+)(PCS?|PIECES?)$/', '$1PC', $clean);
    }

    /**
     * All datasheet rows grouped by {@see normalizeSkuForLookup}.
     * Callers must resolve collisions with {@see pickBestForProductSku}.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function groupedByNormalizedSku(): Collection
    {
        return self::query()->get()->groupBy(function ($item) {
            return self::normalizeSkuForLookup($item->sku ?? '');
        });
    }

    /**
     * When several amazon_datsheets rows share one compact key (e.g. "SS ECO 2PK ORG WoB"
     * and "SSECO2PKORGWoB"), prefer the space-normalized exact Product Master MSKU.
     * Otherwise prefer a priced row with the newest updated_at.
     *
     * @param  Collection<int, self>|iterable<int, self>|null  $candidates
     */
    public static function pickBestForProductSku(string $productSku, $candidates): ?self
    {
        $candidates = collect($candidates)->filter()->values();
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $want = self::normalizeSkuSpaces($productSku);
        if ($want !== '') {
            $exact = $candidates->first(
                fn ($row) => self::normalizeSkuSpaces($row->sku ?? '') === $want
            );
            if ($exact) {
                return $exact;
            }
        }

        return $candidates->sortByDesc(function ($row) {
            $hasPrice = (is_numeric($row->price) && (float) $row->price > 0) ? 1 : 0;
            $ts = $row->updated_at ? $row->updated_at->getTimestamp() : 0;

            return sprintf('%d-%020d-%d', $hasPrice, $ts, (int) ($row->id ?? 0));
        })->first();
    }

    /**
     * Sales & Traffic buyBoxPercentage is usually 0–100. Some payloads send 0–1.
     */
    public static function normalizeBuyBoxPercentage(mixed $raw): ?float
    {
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;
        if ($n < 0) {
            return null;
        }
        if ($n > 0 && $n < 1) {
            $n *= 100;
        }

        return round(min($n, 100), 2);
    }

    /**
     * Match Product Master / grid key to `amazon_datsheets.sku` (spaces + case insensitive)
     * and return the stored seller MSKU string for SP-API Listings calls.
     *
     * If the grid sends a **short** code (e.g. "CS 04 2W") and the sheet has the full MSKU
     * ("CS 04 2W WoG"), exact match fails; a second pass finds a **unique** row whose
     * space-stripped MSKU starts with the same prefix (min 6 compact chars) so Listings
     * API gets the real seller SKU.
     */
    public static function resolveSellerMskuByProductKey(string $productOrGridSku): ?string
    {
        $raw = trim(str_replace("\xc2\xa0", ' ', $productOrGridSku));
        if ($raw === '') {
            return null;
        }

        $normSpace = self::normalizeSkuSpaces($raw);
        $compact = strtoupper(str_replace([' ', "\xc2\xa0"], '', $raw));

        $compactExpr = 'UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), ""))';

        $candidates = self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($normSpace, $compact, $compactExpr) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [$normSpace])
                    ->orWhereRaw("{$compactExpr} = ?", [$compact]);
            })
            ->orderBy('id')
            ->get();

        $row = self::pickBestForProductSku($raw, $candidates);

        if (! $row && strlen($compact) >= 6) {
            $prefixCandidates = self::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw("{$compactExpr} LIKE ?", [$compact . '%'])
                ->orderBy('id')
                ->get();

            if ($prefixCandidates->count() === 1) {
                $row = $prefixCandidates->first();
            }
        }

        if (! $row) {
            return null;
        }

        $out = trim((string) ($row->sku ?? ''));

        return $out !== '' ? $out : null;
    }
}
