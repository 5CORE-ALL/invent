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
}
