<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'sessions_l7',
        'sessions_l15',
        'sessions_l30',
        
        'sessions_l60',
        'sessions_l90',        
        'asin',
        'amazon_title',
        'sku',
        'price',
        'organic_views',
        'sold',
        'listing_status',
    ];

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

        $normSpace = strtoupper(preg_replace('/\s+/u', ' ', $raw));
        $compact = strtoupper(str_replace([' ', "\xc2\xa0"], '', $raw));

        $compactExpr = 'UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), ""))';

        $row = self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($normSpace, $compact, $compactExpr) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [$normSpace])
                    ->orWhereRaw("{$compactExpr} = ?", [$compact]);
            })
            ->orderBy('id')
            ->first();

        if (! $row && strlen($compact) >= 6) {
            $candidates = self::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->whereRaw("{$compactExpr} LIKE ?", [$compact . '%'])
                ->orderBy('id')
                ->get();

            if ($candidates->count() === 1) {
                $row = $candidates->first();
            }
        }

        if (! $row) {
            return null;
        }

        $out = trim((string) ($row->sku ?? ''));

        return $out !== '' ? $out : null;
    }
}
