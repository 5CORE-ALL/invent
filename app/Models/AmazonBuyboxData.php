<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonBuyboxData extends Model
{
    protected $table = 'amazon_buybox_data';

    protected $fillable = [
        'sku',
        'asin',
        'item_condition',
        'status',
        'total_offer_count',
        'offer_count_amazon',
        'offer_count_merchant',
        'list_price',
        'competitive_price_threshold',
        'suggested_lower_price_plus_shipping',
        'buybox_listing_price',
        'buybox_landed_price',
        'buybox_shipping',
        'buybox_currency',
        'lowest_listing_price',
        'lowest_landed_price',
        'lowest_shipping',
        'lowest_fulfillment_channel',
        'is_buy_box_winner',
        'my_offer',
        'is_fulfilled_by_amazon',
        'is_featured_merchant',
        'is_prime',
        'is_national_prime',
        'our_listing_price',
        'our_shipping',
        'our_landed_price',
        'our_subcondition',
        'our_feedback_rating',
        'our_feedback_count',
        'our_ship_min_hours',
        'our_ship_max_hours',
        'our_ships_from_country',
        'bb_seller_id',
        'bb_is_fulfilled_by_amazon',
        'bb_is_featured_merchant',
        'bb_is_prime',
        'bb_listing_price',
        'bb_shipping',
        'bb_landed_price',
        'bb_feedback_rating',
        'bb_feedback_count',
        'bb_subcondition',
        'bb_ships_from_country',
        'sales_rank',
        'sales_rank_category',
        'raw_payload',
        'error_message',
        'fetched_at',
    ];

    protected $casts = [
        'list_price' => 'decimal:2',
        'competitive_price_threshold' => 'decimal:2',
        'suggested_lower_price_plus_shipping' => 'decimal:2',
        'buybox_listing_price' => 'decimal:2',
        'buybox_landed_price' => 'decimal:2',
        'buybox_shipping' => 'decimal:2',
        'lowest_listing_price' => 'decimal:2',
        'lowest_landed_price' => 'decimal:2',
        'lowest_shipping' => 'decimal:2',
        'is_buy_box_winner' => 'boolean',
        'my_offer' => 'boolean',
        'is_fulfilled_by_amazon' => 'boolean',
        'is_featured_merchant' => 'boolean',
        'is_prime' => 'boolean',
        'is_national_prime' => 'boolean',
        'our_listing_price' => 'decimal:2',
        'our_shipping' => 'decimal:2',
        'our_landed_price' => 'decimal:2',
        'our_feedback_rating' => 'decimal:2',
        'bb_is_fulfilled_by_amazon' => 'boolean',
        'bb_is_featured_merchant' => 'boolean',
        'bb_is_prime' => 'boolean',
        'bb_listing_price' => 'decimal:2',
        'bb_shipping' => 'decimal:2',
        'bb_landed_price' => 'decimal:2',
        'bb_feedback_rating' => 'decimal:2',
        'raw_payload' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * How long a pulled "our listing price" may override the datasheet Price.
     *
     * amazon:pull-buybox only runs for SKUs with INV >= 1, so rows for everything else (and rows from a
     * failed pull) keep an old our_listing_price forever. Past this age the datasheet price (listings
     * sync + pushed-price read-back) is the better source and must win, or the app disagrees with Amazon.
     */
    public const SITE_PRICE_MAX_AGE_HOURS = 48;

    /**
     * Rows whose our_listing_price is recent and came from a successful pull.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function applyFreshSitePrice($query)
    {
        return $query
            ->where('our_listing_price', '>', 0)
            ->whereNull('error_message')
            ->where('fetched_at', '>=', now()->subHours(self::SITE_PRICE_MAX_AGE_HOURS));
    }
}
