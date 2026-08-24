<?php

namespace App\Support\Marketplace;

/**
 * Per-marketplace listing-editor layout (tabs, fields, copy).
 */
class ListingManagerEditorProfile
{
    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   family: string,
     *   tabs: list<array{id: string, label: string}>,
     *   identifier_fields: list<string>,
     *   ebay: bool,
     *   tiktok: bool,
     *   temu: bool,
     *   faire: bool,
     *   category_placeholder: string,
     *   optimize_label: string,
     *   header_quick: string,
     *   header_import: string,
     *   page_title: string
     * }
     */
    public static function forChannel(?string $channelName): array
    {
        $key = ListingChannelCounts::normalize((string) $channelName);
        $family = self::family($key);
        $label = trim((string) $channelName) !== '' ? trim((string) $channelName) : 'Channel';

        $profiles = [
            'ebay' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Pricing'],
                    ['id' => 'category', 'label' => 'Category'],
                    ['id' => 'policies', 'label' => 'Business Policies'],
                    ['id' => 'relist', 'label' => 'Auto Relist'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc', 'ean', 'isbn', 'epid'],
                'category_placeholder' => 'Search eBay categories (e.g. speaker)',
                'optimize_label' => 'Optimize Description for eBay',
                'header_quick' => 'Quick/Auto List to eBay',
                'header_import' => 'Import from eBay',
                'pricing_title' => 'Pricing',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'eBay identifiers including UPC, EAN, ISBN, and ePID.',
                'images_help' => 'Drag images to reorder. First image is Primary. Gallery Plus is optional.',
                'category_help' => 'Search and select an eBay leaf category, then set condition and item specifics.',
                'policies_help' => 'Shipping, payment, and return policies must exist on the linked eBay account.',
            ],
            'tiktok' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'TikTok Category'],
                    ['id' => 'policies', 'label' => 'Warehouse & Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search TikTok categories (e.g. speaker)',
                'optimize_label' => 'Optimize Description for TikTok',
                'header_quick' => 'Quick/Auto List to TikTok Shop',
                'header_import' => 'Import from TikTok Shop',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'TikTok Shop uses SKU, brand, manufacturer, and UPC. eBay fields such as ePID are not used.',
                'images_help' => 'TikTok Shop needs at least one product image. First image is the main photo.',
                'category_help' => 'Search TikTok categories by keyword (same as Seller Center). Pick a leaf path such as Phones & Electronics - Audio & Video - Speakers.',
                'policies_help' => 'Package weight is required. Warehouse ID is optional when your shop already has a default warehouse.',
            ],
            'temu' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Temu Category'],
                    ['id' => 'policies', 'label' => 'Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Temu leaf category ID',
                'optimize_label' => 'Optimize Description for Temu',
                'header_quick' => 'Quick/Auto List to Temu',
                'header_import' => 'Import from Temu',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Temu uses SKU, brand, manufacturer, and UPC. eBay business policies are not used.',
                'images_help' => 'Temu needs at least one product image. First image is the main photo.',
                'category_help' => 'Enter the Temu leaf category ID. Required before publish.',
                'policies_help' => 'Package weight and dimensions come from Product Master and are required for Temu.',
            ],
            'faire' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Wholesale Price'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Faire category',
                'optimize_label' => 'Optimize Description',
                'header_quick' => 'Quick/Auto List to Faire',
                'header_import' => 'Import from Faire',
                'pricing_title' => 'Wholesale Price',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Faire listings use SKU, brand, and UPC from Product Master.',
                'images_help' => 'Drag images to reorder. First image is Primary.',
                'category_help' => '',
                'policies_help' => '',
            ],
            'default' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Category'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Category ID or name',
                'optimize_label' => 'Optimize Description',
                'header_quick' => 'Quick/Auto List to Channel',
                'header_import' => 'Import from Channel',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Product identifiers from Product Master.',
                'images_help' => 'Drag images to reorder. First image is Primary.',
                'category_help' => 'Enter the marketplace category ID or name.',
                'policies_help' => '',
            ],
        ];

        $base = $profiles[$family] ?? $profiles['default'];

        return [
            'key' => $key !== '' ? $key : 'default',
            'label' => $label,
            'family' => $family,
            'tabs' => $base['tabs'],
            'identifier_fields' => $base['identifier_fields'],
            'ebay' => $family === 'ebay',
            'tiktok' => $family === 'tiktok',
            'temu' => $family === 'temu',
            'faire' => $family === 'faire',
            'category_placeholder' => $base['category_placeholder'],
            'optimize_label' => $base['optimize_label'],
            'header_quick' => $base['header_quick'],
            'header_import' => $base['header_import'],
            'page_title' => $label.' Listings',
            'pricing_title' => $base['pricing_title'] ?? 'Pricing',
            'title_heading' => $base['title_heading'] ?? 'Title & Description',
            'identifier_help' => $base['identifier_help'] ?? '',
            'images_help' => $base['images_help'] ?? 'Drag images to reorder. First image is Primary.',
            'category_help' => $base['category_help'] ?? '',
            'policies_help' => $base['policies_help'] ?? '',
        ];
    }

    public static function family(string $normalizedKey): string
    {
        if (in_array($normalizedKey, ['ebay', 'ebay1', 'ebayone', 'ebay2', 'ebaytwo', 'ebay3', 'ebaythree', 'ebayvariation'], true)) {
            return 'ebay';
        }
        if (in_array($normalizedKey, ['tiktok', 'tiktokshop', 'tiktok2', 'tiktokshop2', 'tiktoktwo'], true)) {
            return 'tiktok';
        }
        if (in_array($normalizedKey, ['temu', 'temu2', 'temutwo'], true)) {
            return 'temu';
        }
        if ($normalizedKey === 'faire') {
            return 'faire';
        }

        return 'default';
    }
}
