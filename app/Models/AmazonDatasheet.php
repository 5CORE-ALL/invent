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
     * Match Product Master / grid key to `amazon_datsheets.sku` (spaces + case insensitive)
     * and return the stored seller MSKU string for SP-API Listings calls.
     */
    public static function resolveSellerMskuByProductKey(string $productOrGridSku): ?string
    {
        $raw = trim(str_replace("\xc2\xa0", ' ', $productOrGridSku));
        if ($raw === '') {
            return null;
        }

        $normSpace = strtoupper(preg_replace('/\s+/u', ' ', $raw));
        $compact = strtoupper(str_replace([' ', "\xc2\xa0"], '', $raw));

        $row = self::query()
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where(function ($q) use ($normSpace, $compact) {
                $q->whereRaw('UPPER(TRIM(sku)) = ?', [$normSpace])
                    ->orWhereRaw('UPPER(REPLACE(REPLACE(TRIM(sku), " ", ""), CHAR(9), "")) = ?', [$compact]);
            })
            ->orderBy('id')
            ->first();

        if (! $row) {
            return null;
        }

        $out = trim((string) ($row->sku ?? ''));

        return $out !== '' ? $out : null;
    }
}
