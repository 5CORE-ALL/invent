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
     *   wayfair: bool,
     *   reverb: bool,
     *   newegg: bool,
     *   mirakl: bool,
     *   topdawg: bool,
     *   shein: bool,
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
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
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
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
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
                'category_placeholder' => 'Search Temu categories (e.g. light stand)',
                'optimize_label' => 'Optimize Description for Temu',
                'header_quick' => 'Quick/Auto List to Temu',
                'header_import' => 'Import from Temu',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Temu needs at least one Image Master photo. First image is the main photo.',
                'category_help' => 'Type a keyword such as light stand. Click a Temu suggestion to fill the leaf category ID before you publish.',
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
                'identifier_help' => 'Brand is always 5 Core. Model is the SKU. Condition is Brand New.',
                'images_help' => 'Load photos from Image Master. Reverb recommends at least 11 photos. First image is Primary.',
                'category_help' => 'Search Reverb categories and select a leaf path. Make is 5 Core, model is the SKU, and condition is Brand New.',
                'policies_help' => 'Set a Reverb shipping profile, shipping rates, or local pickup. Package size and weight come from Dim/Wt Master.',
            ],
            'faire' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Wholesale Price'],
                    ['id' => 'category', 'label' => 'Product Type'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search Faire product types (e.g. lighting)',
                'optimize_label' => 'Optimize Description',
                'header_quick' => 'Quick/Auto List to Faire',
                'header_import' => 'Import from Faire',
                'pricing_title' => 'Wholesale Price',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU.',
                'images_help' => 'Load photos from Image Master. First image is Primary.',
                'category_help' => 'Choose a Faire product type. Add organization tags to group products in the brand portal.',
                'policies_help' => '',
            ],
            'wayfair' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Wayfair Class'],
                    ['id' => 'policies', 'label' => 'Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search categories or product classes',
                'optimize_label' => 'Optimize Description for Wayfair',
                'header_quick' => 'Quick/Auto List to Wayfair',
                'header_import' => 'Import from Wayfair',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Color and country of origin are sent with the Wayfair class questions.',
                'images_help' => 'Load photos from Image Master. Wayfair uses the first 8 HTTPS images.',
                'category_help' => 'Search categories or product classes, pick a class, then complete every required Wayfair product-form question.',
                'policies_help' => 'Package size, weight, lead time, and box count come from Dim/Wt Master and the Wayfair shipping form.',
            ],
            'newegg' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Newegg Category'],
                    ['id' => 'policies', 'label' => 'Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search Newegg categories (e.g. speaker)',
                'optimize_label' => 'Optimize Description for Newegg',
                'header_quick' => 'Quick/Auto List to Newegg',
                'header_import' => 'Import from Newegg',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Manufacturer must already exist in Newegg (ask Marketplace Content to add 5 Core if it is missing). Model/MPN is the SKU. Use a 12-digit UPC or 13-digit EAN.',
                'images_help' => 'Load photos from Image Master. First image is Primary. Newegg accepts JPG/JPEG/GIF.',
                'category_help' => 'Search Newegg Seller Portal subcategories and pick a leaf. The Subcategory ID is required to create the listing. After publish, the item appears in Pricing & Inventory only when Newegg assigns a 9SI item number.',
                'policies_help' => 'Package size and weight come from Dim/Wt Master.',
            ],
            'mirakl' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Category'],
                    ['id' => 'policies', 'label' => 'Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search categories (e.g. speaker)',
                'optimize_label' => 'Optimize Description',
                'header_quick' => 'Quick/Auto List',
                'header_import' => 'Import from Marketplace',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Load photos from Image Master. First image is Primary. Mirakl uses HTTPS images.',
                'category_help' => 'Search and select a Mirakl category code used by this marketplace, or type a category code. Title, description, images, price, and stock are pushed on Save & Publish.',
                'policies_help' => 'Package size and weight come from Dim/Wt Master.',
            ],
            'amazon' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Product Type'],
                    ['id' => 'policies', 'label' => 'Packaging'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search Amazon product types (e.g. light stand)',
                'optimize_label' => 'Optimize Description for Amazon',
                'header_quick' => 'Quick/Auto List to Amazon',
                'header_import' => 'Import from Amazon',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New. UPC is required to create a new Amazon SKU.',
                'images_help' => 'Load photos from Image Master. Amazon uses the first 9 HTTPS images.',
                'category_help' => 'Type a keyword such as light stand. Amazon suggestions appear below — pick the product type (for example LIGHTING_ACCESSORY) before you publish.',
                'policies_help' => 'Package size and weight come from Dim/Wt Master. Amazon will not show a new SKU in Manage Inventory until Product Type, Packaging, images, and a UPC (or existing ASIN) are complete.',
            ],
            'topdawg' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Category'],
                    ['id' => 'policies', 'label' => 'Package'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search TopDawg categories (e.g. microphone)',
                'optimize_label' => 'Optimize Description for TopDawg',
                'header_quick' => 'Quick/Auto List to TopDawg',
                'header_import' => 'Import from TopDawg',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New. UPC is sent as GTIN when present.',
                'images_help' => 'Load photos from Image Master. TopDawg needs 4 image slots — we repeat the primary photo if fewer are loaded.',
                'category_help' => 'Search and select a TopDawg department / section / category. Gender, age group, condition, and pack-of are required on their create-product form.',
                'policies_help' => 'Package size and weight come from Dim/Wt Master and are required. Product made-in is sent as Country of origin.',
            ],
            'shein' => [
                'tabs' => [
                    ['id' => 'identifiers', 'label' => 'Product Identifiers'],
                    ['id' => 'variations', 'label' => 'Variations'],
                    ['id' => 'title', 'label' => 'Title & Description'],
                    ['id' => 'images', 'label' => 'Images'],
                    ['id' => 'pricing', 'label' => 'Price & Stock'],
                    ['id' => 'category', 'label' => 'Category'],
                ],
                'identifier_fields' => ['sku', 'asin', 'brand', 'manufacturer', 'upc'],
                'category_placeholder' => 'Search Shein categories (e.g. light stand)',
                'optimize_label' => 'Optimize Description for Shein',
                'header_quick' => 'Quick/Auto List to Shein',
                'header_import' => 'Import from Shein',
                'pricing_title' => 'Price & Stock',
                'title_heading' => 'Title & Description',
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
                'images_help' => 'Load photos from Image Master. First image is Primary. Shein needs a public https image.',
                'category_help' => 'Search Shein leaf categories by keyword (same as Seller Center). Pick a path such as Home & Living > Lighting > Light Stands before you publish.',
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
                'identifier_help' => 'Brand is always 5 Core. Model/MPN is the SKU. Condition is New.',
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
            'wayfair' => $family === 'wayfair',
            'reverb' => $family === 'reverb',
            'amazon' => $family === 'amazon',
            'newegg' => $family === 'newegg',
            'mirakl' => $family === 'mirakl',
            'topdawg' => $family === 'topdawg',
            'shein' => $family === 'shein',
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
        if (in_array($normalizedKey, ['temu', 'temu1', 'temu2', 'temutwo'], true)) {
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
        if (in_array($normalizedKey, ['newegg', 'neweggb2c', 'neweggb2b'], true)) {
            return 'newegg';
        }
        if ($normalizedKey === 'shein') {
            return 'shein';
        }
        if ($normalizedKey === 'wayfair') {
            return 'wayfair';
        }
        if (in_array($normalizedKey, ['macys', 'macy', 'bestbuy', 'bestbuyusa', 'purchasingpower'], true)) {
            return 'mirakl';
        }
        if (in_array($normalizedKey, ['topdawg', 'topdawginc', 'top-dawg', 'top_dawg'], true)) {
            return 'topdawg';
        }

        return 'default';
    }
}
