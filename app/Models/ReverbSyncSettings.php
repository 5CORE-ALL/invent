<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReverbSyncSettings extends Model
{
    protected $table = 'reverb_sync_settings';

    protected $fillable = ['marketplace', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public static function getForReverb(): array
    {
        $row = self::where('marketplace', 'reverb')->first();
        return $row ? (array) $row->settings : self::defaults();
    }

    public static function setForReverb(array $settings): void
    {
        self::updateOrCreate(
            ['marketplace' => 'reverb'],
            ['settings' => $settings]
        );
    }

    public static function defaults(): array
    {
        return [
            'pricing' => [
                'price_sync' => false,
                'use_sale_price' => false,
                'currency_conversion' => false,
                'price_rules' => [],
                'price_rounding' => 'none',
                'apply_to_conditions' => [],
            ],
            'inventory' => [
                'inventory_sync' => false,
                'keep_listing_active' => false,
                'auto_relist' => false,
                'quantity_calc_percent' => 100,
                'no_manage_stock_quantity' => 0,
                'max_quantity' => 1,
                'min_quantity' => 1,
                'out_of_stock_threshold' => 1,
                'shopify_location_ids' => [],
            ],
            'order' => [
                'skip_shipped_orders' => false,
                'import_orders_to_main_store' => false,
                'import_orders_with_unlisted_products' => false,
                'keep_order_number_from_channel' => true,
                'tax_rules' => 'custom',
                'import_orders_without_tax' => false,
                'order_receipt_email' => true,
                'fulfillment_receipt_email' => true,
                'marketing_emails' => false,
                'import_sales_with_by' => 'incoming_order_files',
                'sort_order' => ['latest', 'lowest'],
                'shopify_order_tags' => ['reverb'],
            ],
        ];
    }
}
