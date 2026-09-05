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
     *   reverb: bool,
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
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Load photos from Image Master. First image is Primary.',
                'category_help' => 'Search and select an eBay leaf category, then set condition and item specifics.',
                'policies_help' => 'Shipping, payment, and return policies must exist on the linked eBay account. Package size and weight come from Dim/Wt Master.',
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
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'TikTok Shop needs at least one Image Master photo. First image is the main photo.',
                'category_help' => 'Search TikTok categories by keyword (same as Seller Center). Pick a leaf path such as Phones & Electronics - Audio & Video - Speakers.',
                'policies_help' => 'Package size and weight come from Dim/Wt Master. Warehouse ID is optional when your shop already has a default warehouse.',
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
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Temu needs at least one Image Master photo. First image is the main photo.',
                'category_help' => 'Enter the Temu leaf category ID. Required before publish.',
                'policies_help' => 'Package weight and dimensions come from Dim/Wt Master and are required for Temu.',
            ],
            'reverb' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Photos & Videos'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Reverb Details'],
                    ['id' => 'policies', 'label' => 'Shipping & Package'],
                ],
                'identifier_fields' => ['sku', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search Reverb categories (e.g. microphone)',
                'optimize_label' => 'Optimize Description for Reverb',
                'header_quick' => 'Quick/Auto List to Reverb',
                'header_import' => 'Import from Reverb',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core Inc. Model is the SKU. Condition is Brand New.',
                'images_help' => 'Load photos from Image Master. Reverb recommends at least 11 photos. First image is Primary.',
                'category_help' => 'Search Reverb categories and select a leaf path. Make is 5 Core Inc., model is the SKU, and condition is Brand New.',
                'policies_help' => 'Set a Reverb shipping profile, shipping rates, or local pickup. Package size and weight come from Dim/Wt Master.',
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
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU.',
                'images_help' => 'Load photos from Image Master. First image is Primary.',
                'category_help' => '',
                'policies_help' => '',
            ],
            'amazon' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Amazon product type',
                'optimize_label' => 'Optimize Description for Amazon',
                'header_quick' => 'Quick/Auto List to Amazon',
                'header_import' => 'Import from Amazon',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Load photos from Image Master. Amazon uses the first 9 HTTPS images.',
                'category_help' => '',
                'policies_help' => 'Save & Publish updates an existing Amazon listing (title, quantity, images). New SKUs must exist in Seller Central first.',
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
                'identifier_help' => 'Brand is always 5 Core Inc. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Load photos from Image Master. First image is Primary.',
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
            'reverb' => $family === 'reverb',
            'category_placeholder' => $base['category_placeholder'],
            'optimize_label' => $base['optimize_label'],
            'header_quick' => $base['header_quick'],
            'header_import' => $base['header_import'],
            'page_title' => $label.' Listings',
            'pricing_title' => $base['pricing_title'] ?? 'Pricing',
            'title_heading' => $base['title_heading'] ?? 'Title & Description',
            'identifier_help' => $base['identifier_help'] ?? '',
            'images_help' => $base['images_help'] ?? 'Load photos from Image Master. First image is Primary.',
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
        if (in_array($normalizedKey, ['reverb', 'reverbcom'], true)) {
            return 'reverb';
        }
        if (in_array($normalizedKey, ['amazon', 'amazonfba', 'amz', 'amzfbm'], true)) {
            return 'amazon';
        }
        if ($normalizedKey === 'shein') {
            return 'shein';
        }
        if ($normalizedKey === 'wayfair') {
            return 'wayfair';
        }

        return 'default';
    }
}
