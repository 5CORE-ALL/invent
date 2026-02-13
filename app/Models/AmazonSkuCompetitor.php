<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonSkuCompetitor extends Model
{
    protected $table = 'amazon_sku_competitors';

    protected $fillable = [
        'sku',
        'asin',
        'marketplace',
        'product_link',
        'image',
        'product_title',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Get the lowest priced competitor for a given SKU
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getLowestPriceForSku($sku, $marketplace = 'amazon')
    {
        // Normalize SKU: remove ALL whitespace (including newlines, tabs), then add single spaces
        $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
        
        // Match using normalized SKU comparison (handles line breaks in database)
        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->first();
    }

    /**
     * Get all competitors for a given SKU ordered by price
     * Handles SKUs with line breaks, extra spaces, and case differences
     */
    public static function getCompetitorsForSku($sku, $marketplace = 'amazon')
    {
        // Normalize SKU: remove ALL whitespace (including newlines, tabs), then add single spaces
        $normalizedSku = strtoupper(preg_replace('/\s+/', ' ', trim($sku)));
        
        // Match using normalized SKU comparison (handles line breaks in database)
        return self::whereRaw('UPPER(REPLACE(REPLACE(REPLACE(REPLACE(sku, CHAR(10), " "), CHAR(13), " "), CHAR(9), " "), "  ", " ")) = ?', [$normalizedSku])
            ->where('marketplace', $marketplace)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->get();
    }
}
