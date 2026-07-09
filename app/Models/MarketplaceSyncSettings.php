<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSyncSettings extends Model
{
    protected $table = 'marketplace_sync_settings';

    protected $fillable = ['marketplace', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public static function getFor(string $marketplace): array
    {
        $row = self::where('marketplace', $marketplace)->first();

        return $row ? (array) $row->settings : self::defaults();
    }

    public static function setFor(string $marketplace, array $settings): void
    {
        self::updateOrCreate(
            ['marketplace' => $marketplace],
            ['settings' => $settings]
        );
    }

    public static function aliexpressCanCreateProducts(?array $settings = null): bool
    {
        $settings ??= self::getFor('aliexpress');

        return (bool) ($settings['listings']['create_products_on_aliexpress'] ?? false);
    }

    public static function defaults(): array
    {
        return [
            'pricing' => [
                'price_sync' => false,
                'use_sale_price' => false,
                'currency_conversion' => false,
                'adjustment_type' => 'increase',
                'adjustment_value' => 0,
                'adjustment_method' => 'percent',
            ],
            'inventory' => [
                'inventory_sync' => false,
                'quantity_calc_percent' => 100,
                'max_quantity' => null,
                'min_quantity' => 1,
                'out_of_stock_threshold' => 0,
            ],
            'order' => [
                'auto_import_to_shopify' => false,
                'keep_order_number_from_channel' => true,
                'shopify_order_tags' => [],
                'shopify_store' => 'main',
                'shopify_source_name' => 'aliexpress',
                'shopify_source_display_name' => 'AliExpress',
            ],
            'listings' => [
                'auto_link_by_sku' => true,
                'create_products_on_aliexpress' => false,
                'sync_title' => false,
                'sync_images' => false,
            ],
        ];
    }
}
