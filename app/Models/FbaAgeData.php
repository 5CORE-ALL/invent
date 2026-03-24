<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbaAgeData extends Model
{
    protected $table = 'fba_age_data';

    protected $fillable = [
        'snapshot_date',
        'sku',
        'fnsku',
        'asin',
        'product_name',
        'condition',
        'marketplace',
        'currency',

        // Current stock
        'available',
        'pending_removal_quantity',
        'inbound_quantity',
        'inbound_working',
        'inbound_shipped',
        'inbound_received',
        'reserved_quantity',
        'unfulfillable_quantity',

        // Age buckets (coarse)
        'inv_age_0_to_90_days',
        'inv_age_91_to_180_days',
        'inv_age_181_to_270_days',
        'inv_age_271_to_365_days',
        'inv_age_366_to_455_days',
        'inv_age_456_plus_days',

        // Age buckets (fine-grained)
        'inv_age_0_to_30_days',
        'inv_age_31_to_60_days',
        'inv_age_61_to_90_days',
        'inv_age_181_to_330_days',
        'inv_age_331_to_365_days',

        // AIS projections
        'ais_qty_181_210', 'ais_est_181_210',
        'ais_qty_211_240', 'ais_est_211_240',
        'ais_qty_241_270', 'ais_est_241_270',
        'ais_qty_271_300', 'ais_est_271_300',
        'ais_qty_301_330', 'ais_est_301_330',
        'ais_qty_331_365', 'ais_est_331_365',
        'ais_qty_366_455', 'ais_est_366_455',
        'ais_qty_456_plus', 'ais_est_456_plus',

        // Sales velocity
        'units_shipped_t7',
        'units_shipped_t30',
        'units_shipped_t60',
        'units_shipped_t90',
        'sales_shipped_last_7_days',
        'sales_shipped_last_30_days',
        'sales_shipped_last_60_days',
        'sales_shipped_last_90_days',

        // Pricing
        'your_price',
        'sales_price',
        'lowest_price_new_plus_shipping',
        'lowest_price_used',
        'featuredoffer_price',

        // Health & recommendations
        'health_status',
        'alert',
        'recommended_action',
        'recommended_removal_quantity',
        'recommended_sales_price',
        'recommended_sale_duration_days',
        'estimated_cost_savings',
        'no_sale_last_6_months',

        // Supply / days metrics
        'sell_through',
        'days_of_supply',
        'total_days_of_supply',
        'estimated_excess_quantity',
        'weeks_of_cover_t30',
        'weeks_of_cover_t90',
        'historical_days_of_supply',
        'short_term_days_of_supply',
        'long_term_days_of_supply',
        'fba_minimum_inventory_level',
        'inventory_age_snapshot_date',

        // Storage / volume
        'storage_type',
        'storage_volume',
        'item_volume',
        'volume_unit_measurement',

        // Fees
        'estimated_storage_cost_next_month',

        // Misc
        'sales_rank',
        'product_group',
    ];

    protected $casts = [
        'snapshot_date'                  => 'date',
        'inventory_age_snapshot_date'    => 'date',
        'available'                      => 'integer',
        'pending_removal_quantity'       => 'integer',
        'inbound_quantity'               => 'integer',
        'inbound_working'                => 'integer',
        'inbound_shipped'                => 'integer',
        'inbound_received'               => 'integer',
        'reserved_quantity'              => 'integer',
        'unfulfillable_quantity'         => 'integer',
        'inv_age_0_to_90_days'           => 'integer',
        'inv_age_91_to_180_days'         => 'integer',
        'inv_age_181_to_270_days'        => 'integer',
        'inv_age_271_to_365_days'        => 'integer',
        'inv_age_366_to_455_days'        => 'integer',
        'inv_age_456_plus_days'          => 'integer',
        'inv_age_0_to_30_days'           => 'integer',
        'inv_age_31_to_60_days'          => 'integer',
        'inv_age_61_to_90_days'          => 'integer',
        'inv_age_181_to_330_days'        => 'integer',
        'inv_age_331_to_365_days'        => 'integer',
        'units_shipped_t7'               => 'integer',
        'units_shipped_t30'              => 'integer',
        'units_shipped_t60'              => 'integer',
        'units_shipped_t90'              => 'integer',
        'recommended_removal_quantity'   => 'integer',
        'recommended_sale_duration_days' => 'integer',
        'days_of_supply'                 => 'integer',
        'total_days_of_supply'           => 'integer',
        'estimated_excess_quantity'      => 'integer',
        'fba_minimum_inventory_level'    => 'integer',
        'sales_rank'                     => 'integer',
        'no_sale_last_6_months'          => 'boolean',
        'your_price'                     => 'decimal:2',
        'sales_price'                    => 'decimal:2',
        'lowest_price_new_plus_shipping' => 'decimal:2',
        'lowest_price_used'              => 'decimal:2',
        'featuredoffer_price'            => 'decimal:2',
        'estimated_cost_savings'         => 'decimal:2',
        'estimated_storage_cost_next_month' => 'decimal:2',
        'sell_through'                   => 'decimal:4',
        'weeks_of_cover_t30'             => 'decimal:2',
        'weeks_of_cover_t90'             => 'decimal:2',
        'historical_days_of_supply'      => 'decimal:2',
        'short_term_days_of_supply'      => 'decimal:2',
        'long_term_days_of_supply'       => 'decimal:2',
        'storage_volume'                 => 'decimal:6',
        'item_volume'                    => 'decimal:6',
        'sales_shipped_last_7_days'      => 'decimal:2',
        'sales_shipped_last_30_days'     => 'decimal:2',
        'sales_shipped_last_60_days'     => 'decimal:2',
        'sales_shipped_last_90_days'     => 'decimal:2',
    ];
}
