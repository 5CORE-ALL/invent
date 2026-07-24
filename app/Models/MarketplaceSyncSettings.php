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
        $settings = $row ? (array) $row->settings : self::defaults($marketplace);

        return array_replace_recursive(self::defaults($marketplace), $settings);
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

    public static function alibabaCanCreateProducts(?array $settings = null): bool
    {
        $settings ??= self::getFor('alibaba');

        return (bool) ($settings['listings']['create_products_on_alibaba'] ?? false);
    }

    public static function reverbCanCreateProducts(?array $settings = null): bool
    {
        $settings ??= self::getFor('reverb');

        return (bool) ($settings['listings']['create_products_on_reverb'] ?? false);
    }

    public static function neweggCanCreateProducts(?array $settings = null): bool
    {
        $settings ??= self::getFor('newegg');

        return (bool) ($settings['listings']['create_products_on_newegg'] ?? false);
    }

    public static function canFetchOrders(string $marketplace, ?array $settings = null): bool
    {
        $settings ??= self::getFor($marketplace);

        return (bool) ($settings['order']['fetch_orders'] ?? true);
    }

    public static function canAutoImportToShopify(string $marketplace, ?array $settings = null): bool
    {
        $settings ??= self::getFor($marketplace);

        return (bool) ($settings['order']['auto_import_to_shopify'] ?? false);
    }

    public static function importPaidOrdersOnly(string $marketplace, ?array $settings = null): bool
    {
        $settings ??= self::getFor($marketplace);

        return (bool) ($settings['order']['import_paid_orders_only'] ?? false);
    }

    public static function canAutoLinkBySku(string $marketplace, ?array $settings = null): bool
    {
        $settings ??= self::getFor($marketplace);

        return (bool) ($settings['listings']['auto_link_by_sku'] ?? true);
    }

    public static function defaults(?string $marketplace = null): array
    {
        $marketplace = strtolower((string) $marketplace);
        $isAlibaba = $marketplace === 'alibaba';
        $isReverb = $marketplace === 'reverb';
        $isNewegg = $marketplace === 'newegg';

        $sourceName = 'aliexpress';
        $sourceDisplay = 'AliExpress';
        if ($isAlibaba) {
            $sourceName = 'alibaba';
            $sourceDisplay = 'Alibaba';
        } elseif ($isReverb) {
            $sourceName = 'reverb';
            $sourceDisplay = 'Reverb';
        } elseif ($isNewegg) {
            $sourceName = 'newegg';
            $sourceDisplay = 'Newegg';
        }

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
                // Never invent stock when Shopify is 0 — min only applies when ATS > 0.
                'min_quantity' => 0,
                'out_of_stock_threshold' => 0,
            ],
            'order' => [
                'fetch_orders' => true,
                // Newegg: keep order + address + tracking automation ON by default.
                'auto_import_to_shopify' => $isNewegg,
                'import_paid_orders_only' => false,
                'keep_order_number_from_channel' => true,
                // Shopify label/tracking → declare shipment (ON by default per channel).
                'push_tracking_to_aliexpress' => $marketplace === 'aliexpress',
                'push_tracking_to_reverb' => $isReverb,
                'push_tracking_to_newegg' => $isNewegg,
                // Marketplace address → fill missing Shopify shipping + customer fields.
                'sync_address_to_shopify' => in_array($marketplace, ['newegg', 'aliexpress', 'reverb'], true),
                'tracking_send_notification' => false,
                'shopify_order_tags' => [],
                'shopify_store' => 'main',
                'shopify_source_name' => $sourceName,
                'shopify_source_display_name' => $sourceDisplay,
            ],
            'listings' => [
                'auto_link_by_sku' => true,
                'create_products_on_aliexpress' => false,
                'create_products_on_alibaba' => false,
                'create_products_on_reverb' => false,
                'create_products_on_newegg' => false,
                'sync_title' => false,
                'sync_images' => false,
            ],
        ];
    }
}
