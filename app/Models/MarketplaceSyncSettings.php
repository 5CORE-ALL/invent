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

    public static function faireCanCreateProducts(?array $settings = null): bool
    {
        $settings ??= self::getFor('faire');

        return (bool) ($settings['listings']['create_products_on_faire'] ?? false);
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
        $isShein = $marketplace === 'shein';
        $isAmazon = $marketplace === 'amazon';
        $isTopDawg = $marketplace === 'topdawg';
        $isTemu = $marketplace === 'temu';
        $isEbay1 = $marketplace === 'ebay1';
        $isEbay2 = $marketplace === 'ebay2';
        $isEbay3 = $marketplace === 'ebay3';
        $isFaire = $marketplace === 'faire';

        $sourceName = 'aliexpress';
        $sourceDisplay = 'AliExpress';
        if ($isAmazon) {
            $sourceName = 'amazon';
            $sourceDisplay = 'Amazon';
        } elseif ($isTopDawg) {
            $sourceName = 'topdawg';
            $sourceDisplay = 'TopDawg';
        } elseif ($isTemu) {
            $sourceName = 'temu';
            $sourceDisplay = 'Temu';
        } elseif ($isAlibaba) {
            $sourceName = 'alibaba';
            $sourceDisplay = 'Alibaba';
        } elseif ($isReverb) {
            $sourceName = 'reverb';
            $sourceDisplay = 'Reverb';
        } elseif ($isNewegg) {
            $sourceName = 'newegg';
            $sourceDisplay = 'Newegg';
        } elseif ($isShein) {
            $sourceName = 'shein';
            $sourceDisplay = 'Shein';
        } elseif ($isEbay1) {
            $sourceName = 'ebay1';
            $sourceDisplay = 'eBay 1';
        } elseif ($isEbay2) {
            $sourceName = 'ebay2';
            $sourceDisplay = 'eBay 2';
        } elseif ($isEbay3) {
            $sourceName = 'ebay3';
            $sourceDisplay = 'eBay 3';
        } elseif ($isFaire) {
            $sourceName = 'faire';
            $sourceDisplay = 'Faire';
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
                // Newegg/Shein/Faire: keep order + address + tracking automation ON by default.
                // Amazon stays local (Seller Central fulfillment); other channels default ON.
                'auto_import_to_shopify' => $isNewegg || $isShein || $isTopDawg || $isTemu || $isEbay1 || $isEbay2 || $isEbay3 || $isFaire,
                'import_paid_orders_only' => false,
                'keep_order_number_from_channel' => true,
                // Shopify label/tracking → declare shipment (ON by default per channel).
                'push_tracking_to_aliexpress' => $marketplace === 'aliexpress',
                'push_tracking_to_alibaba' => $isAlibaba,
                'push_tracking_to_reverb' => $isReverb,
                'push_tracking_to_newegg' => $isNewegg,
                'push_tracking_to_shein' => $isShein,
                'push_tracking_to_topdawg' => $isTopDawg,
                'push_tracking_to_temu' => $isTemu,
                'push_tracking_to_ebay1' => $isEbay1,
                'push_tracking_to_ebay2' => $isEbay2,
                'push_tracking_to_ebay3' => $isEbay3,
                'push_tracking_to_faire' => $isFaire,
                'push_tracking_to_amazon' => $isAmazon,
                // Marketplace address → fill missing Shopify shipping + customer fields.
                'sync_address_to_shopify' => in_array($marketplace, ['newegg', 'shein', 'topdawg', 'temu', 'ebay1', 'ebay2', 'ebay3', 'aliexpress', 'alibaba', 'reverb', 'faire', 'amazon'], true),
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
                'create_products_on_shein' => false,
                'create_products_on_topdawg' => false,
                'create_products_on_temu' => false,
                'create_products_on_ebay1' => false,
                'create_products_on_ebay2' => false,
                'create_products_on_ebay3' => false,
                'create_products_on_faire' => false,
                'sync_title' => false,
                'sync_images' => false,
            ],
        ];
    }
}
