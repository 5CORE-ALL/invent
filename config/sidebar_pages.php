<?php
// Example: config/sidebar_pages.php
return [
    // Main
    ['value' => 'dashboard', 'text' => 'Dashboard', 'group' => 'Main'],

    // User
    ['value' => 'roles', 'text' => 'Roles', 'group' => 'User'],
    ['value' => 'permissions', 'text' => 'Permission', 'group' => 'User'],

    // Purchase Masters
    ['value' =>'purchase_master_categories', 'text' => 'Categories', 'group' => 'Purchase Masters'],
    ['value' =>'purchase_master_suppliers', 'text' => 'Suppliers', 'group' => 'Purchase Masters'],
    ['value' =>'purchase_master_mfrg_in_progress', 'text' => 'MFRG In Progress', 'group' => 'Purchase Masters'],

    // Product Masters
    ['value' => 'product_lists', 'text' => 'Product Lists', 'group' => 'Product Masters'],
    ['value' => 'tobedc_list', 'text' => 'To be DC', 'group' => 'Product Masters'],
    ['value' => 'pricing_masters', 'text' => 'Pricing Masters', 'group' => 'Product Masters'],
    ['value' => 'listing_analysis', 'text' => 'Listing Analysis', 'group' => 'Product Masters'],
    ['value' => 'cost_price_analysis', 'text' => 'Cost Price Analysis', 'group' => 'Product Masters'],
    ['value' => 'movement_analysis', 'text' => 'Movement Analysis', 'group' => 'Product Masters'],
    ['value' => 'forecast_analysis', 'text' => 'Forecast Analysis', 'group' => 'Product Masters'],
    ['value' => 'to_order_analysis', 'text' => 'To Order Analysis', 'group' => 'Product Masters'],
    ['value' => 'in_order_analysis', 'text' => 'In Order Analysis', 'group' => 'Product Masters'],
    ['value' => 'transit_analysis', 'text' => 'Transit Analysis', 'group' => 'Product Masters'],
    ['value' => 'review_analysis', 'text' => 'Review Analysis', 'group' => 'Product Masters'],
    ['value' => 'profit_roi_analysis', 'text' => 'Profit & ROI Analysis', 'group' => 'Product Masters'],
    ['value' => 'returns_analysis', 'text' => 'Returns Analysis', 'group' => 'Product Masters'],
    ['value' => 'stock_verification', 'text' => 'Stock Verification', 'group' => 'Product Masters'],
    ['value' => 'shortfall_analysis', 'text' => 'Shortfall Analysis', 'group' => 'Product Masters'],

    // Marketing Masters
    ['value' => 'listing_master', 'text' => 'Listing Masters', 'group' => 'Marketing Masters'],
    ['value' => 'mm_video_posted', 'text' => 'Video Posted', 'group' => 'Marketing Masters'],
    ['value' => 'listing_LQS_masters', 'text' => 'LQS - Listing', 'group' => 'Marketing Masters'],
    ['value' => 'cvr_LQS_masters', 'text' => 'LQS - CVR', 'group' => 'Marketing Masters'],

    // Channel
    ['value' => 'channel_master', 'text' => 'Channel Master', 'group' => 'Channel'],
    ['value' => 'channel_promotion_master', 'text' => 'Promotion Master', 'group' => 'Channel'],
    ['value' => 'return_analysis_channel', 'text' => 'Return Analysis (Channel)', 'group' => 'Channel'],
    ['value' => 'expenses_analysis', 'text' => 'Expenses Analysis', 'group' => 'Channel'],
    ['value' => 'review_analysis_channel', 'text' => 'Review Analysis (Channel)', 'group' => 'Channel'],
    ['value' => 'health_analysis', 'text' => 'Health Analysis', 'group' => 'Channel'],
    ['value' => 'listing_analysis_channel', 'text' => 'Listing Analysis (Channel)', 'group' => 'Channel'],
    ['value' => 'shipping_analysis', 'text' => 'Shipping Analysis', 'group' => 'Channel'],
    ['value' => 'c_care_analysis', 'text' => 'C Care Analysis', 'group' => 'Channel'],

    // Shopify
    ['value' => 'shopify_products', 'text' => 'Shopify Products', 'group' => 'Shopify'],
    ['value' => 'shopify_inventory', 'text' => 'Shopify Inventory', 'group' => 'Shopify'],
    ['value' => 'movement_analysis_y2y', 'text' => 'Movement Analysis Y2Y', 'group' => 'Shopify'],
    ['value' => 'movement_analysis_m2m', 'text' => 'Movement Analysis M2M', 'group' => 'Shopify'],
    ['value' => 'movement_analysis_s2s', 'text' => 'Movement Analysis S2S', 'group' => 'Shopify'],
    ['value' => 'shopify_forecast_analysis', 'text' => 'Forecast Analysis (Shopify)', 'group' => 'Shopify'],

    // Inventory Management
    ['value' => 'verifications_adjustments', 'text' => 'Verifications & Adjustments', 'group' => 'Inventory Management'],
    ['value' => 'incoming_returns', 'text' => 'Incoming Returns', 'group' => 'Inventory Management'],
    ['value' => 'outgoing_reissues', 'text' => 'Outgoing Reissues', 'group' => 'Inventory Management'],
    ['value' => 'incoming_shipments', 'text' => 'Incoming Shipments', 'group' => 'Inventory Management'],
    ['value' => 'outgoing_shipments', 'text' => 'Outgoing Shipments', 'group' => 'Inventory Management'],
    ['value' => 'trash_entries', 'text' => 'Trash Entries', 'group' => 'Inventory Management'],
    ['value' => 'pallete_sales', 'text' => 'Pallete Sales', 'group' => 'Inventory Management'],
    ['value' => 'view_inventory', 'text' => 'View Inventory', 'group' => 'Inventory Management'],
    ['value' => 'stock_adjustment', 'text' => 'Stock Adjustment', 'group' => 'Inventory Management'],

    // Inventory Warehouse
    ['value' => 'list_all_warehouses', 'text' => 'List All Warehouses', 'group' => 'Inventory Warehouse'],
    ['value' => 'create_new_warehouse', 'text' => 'Create New Warehouse', 'group' => 'Inventory Warehouse'],
    ['value' => 'inventory_locator', 'text' => 'Inventory Locator', 'group' => 'Inventory Warehouse'],
    ['value' => 'transfers', 'text' => 'Transfers', 'group' => 'Inventory Warehouse'],

    // Catalogue Manager
    ['value' => 'catalogue_manager_amz', 'text' => 'Catalogue Manager AMZ', 'group' => 'Catalogue Manager'],

    // Listing Masters
    ['value' => 'listing_masters', 'text' => 'Listing Masters', 'group' => 'Listing Masters'],

    // Analytics
    ['value' => 'reverb_analytics', 'text' => 'Reverb Analytics', 'group' => 'Analytics'],
    ['value' => 'ebay_analytics', 'text' => 'Ebay Analytics', 'group' => 'Analytics'],

    // Marketplace
    ['value' => 'amazon_analytics', 'text' => 'Amazon Analytics', 'group' => 'Marketplace'],
    ['value' => 'amz_zero_view', 'text' => 'Amazon 0 View', 'group' => 'Marketplace'],
    ['value' => 'amazon_low_visibility', 'text' => 'Amazon Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'amazon_fba_analysis', 'text' => 'Amazon FBA Analysis', 'group' => 'Marketplace'],
    ['value' => 'fba_inv_age', 'text' => 'FBA INV AGE', 'group' => 'Marketplace'],
    ['value' => 'amazon_pricing', 'text' => 'Amazon Pricing', 'group' => 'Marketplace'],
    ['value' => 'listing_amazon', 'text' => 'Listing Amazon', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_amazon', 'text' => 'Listing Audit Amazon', 'group' => 'Marketplace'],

    ['value' => 'ebay', 'text' => 'eBay', 'group' => 'Marketplace'],
    ['value' => 'ebay_zero_view', 'text' => 'eBay 0 View', 'group' => 'Marketplace'],
    ['value' => 'ebay_low_visibility', 'text' => 'eBay Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_ebay', 'text' => 'Listing eBay', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_ebay', 'text' => 'Listing Audit eBay', 'group' => 'Marketplace'],

    ['value' => 'shopify_b2c', 'text' => 'Shopify B2C', 'group' => 'Marketplace'],
    ['value' => 'shopifyb2c_zero_view', 'text' => 'Shopify B2C 0 View', 'group' => 'Marketplace'],
    ['value' => 'shopifyb2c_low_visibility', 'text' => 'Shopify B2C Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_shopifyb2c', 'text' => 'Listing Shopify B2C', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_shopifyb2c', 'text' => 'Listing Audit Shopify B2C', 'group' => 'Marketplace'],

    ['value' => 'macys', 'text' => 'Macy\'s', 'group' => 'Marketplace'],
    ['value' => 'macys_zero_view', 'text' => 'Macy\'s 0 View', 'group' => 'Marketplace'],
    ['value' => 'macy_low_visibility', 'text' => 'Macy\'s Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_macys', 'text' => 'Listing Macy\'s', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_macys', 'text' => 'Listing Audit Macy\'s', 'group' => 'Marketplace'],

    ['value' => 'newegg_b2c', 'text' => 'Newegg B2C', 'group' => 'Marketplace'],
    ['value' => 'neweggb2c_zero_view', 'text' => 'Newegg B2C 0 View', 'group' => 'Marketplace'],
    ['value' => 'neweggb2c_low_visibility', 'text' => 'Newegg B2C Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_Neweggb2c', 'text' => 'Listing Newegg B2C', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_Neweggb2c', 'text' => 'Listing Audit Newegg B2C', 'group' => 'Marketplace'],

    ['value' => 'wayfair', 'text' => 'Wayfair', 'group' => 'Marketplace'],
    ['value' => 'wayfair_zero_view', 'text' => 'Wayfair 0 View', 'group' => 'Marketplace'],
    ['value' => 'wayfair_low_visibility', 'text' => 'Wayfair Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_wayfair', 'text' => 'Listing Wayfair', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_wayfair', 'text' => 'Listing Audit Wayfair', 'group' => 'Marketplace'],

    ['value' => 'reverb', 'text' => 'Reverb', 'group' => 'Marketplace'],
    ['value' => 'reverb_zero_view', 'text' => 'Reverb 0 View', 'group' => 'Marketplace'],
    ['value' => 'reverb_low_visibility', 'text' => 'Reverb Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_reverb', 'text' => 'Listing Reverb', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_reverb', 'text' => 'Listing Audit Reverb', 'group' => 'Marketplace'],

    ['value' => 'temu', 'text' => 'Temu', 'group' => 'Marketplace'],
    ['value' => 'temu_zero_view', 'text' => 'Temu 0 View', 'group' => 'Marketplace'],
    ['value' => 'temu_low_visibility', 'text' => 'Temu Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_temu', 'text' => 'Listing Temu', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_temu', 'text' => 'Listing Audit Temu', 'group' => 'Marketplace'],

    ['value' => 'doba', 'text' => 'Doba', 'group' => 'Marketplace'],
    ['value' => 'doba_zero_view', 'text' => 'Doba 0 View', 'group' => 'Marketplace'],
    ['value' => 'doba_low_visibility', 'text' => 'Doba Low Visibility', 'group' => 'Marketplace'],
    ['value' => 'listing_doba', 'text' => 'Listing Doba', 'group' => 'Marketplace'],
    ['value' => 'listing_audit_doba', 'text' => 'Listing Audit Doba', 'group' => 'Marketplace'],

    ['value' => 'ebayTwo', 'text' => 'Ebay 2', 'group' => 'Marketplace'],
    ['value' => 'listing_ebayTwo', 'text' => 'Listing Ebay 2', 'group' => 'Marketplace'],

    ['value' => 'ebayThree', 'text' => 'Ebay 3', 'group' => 'Marketplace'],
    ['value' => 'listing_ebayThree', 'text' => 'Listing Ebay 3', 'group' => 'Marketplace'],

    ['value' => 'walmart', 'text' => 'Walmart', 'group' => 'Marketplace'],
    ['value' => 'listing_walmart', 'text' => 'Listing Walmart', 'group' => 'Marketplace'],

    ['value' => 'aliexpress', 'text' => 'Aliexpress', 'group' => 'Marketplace'],
    ['value' => 'listing_aliexpress', 'text' => 'Listing Aliexpress', 'group' => 'Marketplace'],

    ['value' => 'ebayvariation', 'text' => 'eBay Variation', 'group' => 'Marketplace'],
    ['value' => 'listing_ebayvariation', 'text' => 'Listing eBay Variation', 'group' => 'Marketplace'],

    ['value' => 'shopifywholesale', 'text' => 'Shopify wholesale/DS', 'group' => 'Marketplace'],
    ['value' => 'listing_shopifywholesale', 'text' => 'Listing Shopify wholesale/DS', 'group' => 'Marketplace'],

    ['value' => 'faire', 'text' => 'Faire', 'group' => 'Marketplace'],
    ['value' => 'listing_faire', 'text' => 'Listing Faire', 'group' => 'Marketplace'],

    ['value' => 'tiktokshop', 'text' => 'Tiktok Shop', 'group' => 'Marketplace'],
    ['value' => 'listing_tiktokshop', 'text' => 'Listing Tiktok Shop', 'group' => 'Marketplace'],

    ['value' => 'mercariwship', 'text' => 'Mercari w Ship', 'group' => 'Marketplace'],
    ['value' => 'listing_mercariwship', 'text' => 'Listing Mercari w Ship', 'group' => 'Marketplace'],

    ['value' => 'fbmarketplace', 'text' => 'FB Marketplace', 'group' => 'Marketplace'],
    ['value' => 'listing_fbmarketplace', 'text' => 'Listing FB Marketplace', 'group' => 'Marketplace'],

    ['value' => 'business5core', 'text' => 'Business 5Core', 'group' => 'Marketplace'],
    ['value' => 'listing_business5core', 'text' => 'Listing Business 5Core', 'group' => 'Marketplace'],

    ['value' => 'pls', 'text' => 'PLS', 'group' => 'Marketplace'],
    ['value' => 'listing_pls', 'text' => 'Listing PLS', 'group' => 'Marketplace'],

    ['value' => 'autods', 'text' => 'Auto DS', 'group' => 'Marketplace'],
    ['value' => 'listing_autods', 'text' => 'Listing Auto DS', 'group' => 'Marketplace'],

    ['value' => 'mercariwoship', 'text' => 'Mercari w/o Ship', 'group' => 'Marketplace'],
    ['value' => 'listing_mercariwoship', 'text' => 'Listing Mercari w/o Ship', 'group' => 'Marketplace'],

    ['value' => 'poshmark', 'text' => 'Poshmark', 'group' => 'Marketplace'],
    ['value' => 'listing_poshmark', 'text' => 'Listing Poshmark', 'group' => 'Marketplace'],

    ['value' => 'tiendamia', 'text' => 'Tiendamia', 'group' => 'Marketplace'],
    ['value' => 'listing_tiendamia', 'text' => 'Listing Tiendamia', 'group' => 'Marketplace'],

    ['value' => 'shein', 'text' => 'Shein', 'group' => 'Marketplace'],
    ['value' => 'listing_shein', 'text' => 'Listing Shein', 'group' => 'Marketplace'],

    ['value' => 'spocket', 'text' => 'Spocket', 'group' => 'Marketplace'],
    ['value' => 'listing_spocket', 'text' => 'Listing Spocket', 'group' => 'Marketplace'],

    ['value' => 'zendrop', 'text' => 'Zendrop', 'group' => 'Marketplace'],
    ['value' => 'listing_zendrop', 'text' => 'Listing Zendrop', 'group' => 'Marketplace'],

    ['value' => 'syncee', 'text' => 'Syncee', 'group' => 'Marketplace'],
    ['value' => 'listing_syncee', 'text' => 'Listing Syncee', 'group' => 'Marketplace'],

    ['value' => 'offerup', 'text' => 'Offerup', 'group' => 'Marketplace'],
    ['value' => 'listing_offerup', 'text' => 'Listing Offerup', 'group' => 'Marketplace'],

    ['value' => 'neweggb2b', 'text' => 'Newegg B2B', 'group' => 'Marketplace'],
    ['value' => 'listing_neweggb2b', 'text' => 'Listing Newegg B2B', 'group' => 'Marketplace'],

    ['value' => 'appscenic', 'text' => 'Appscenic', 'group' => 'Marketplace'],
    ['value' => 'listing_appscenic', 'text' => 'Listing Appscenic', 'group' => 'Marketplace'],

    ['value' => 'fbshop', 'text' => 'FB Shop', 'group' => 'Marketplace'],
    ['value' => 'listing_fbshop', 'text' => 'Listing FB Shop', 'group' => 'Marketplace'],

    ['value' => 'instagramshop', 'text' => 'Instagram Shop', 'group' => 'Marketplace'],
    ['value' => 'listing_instagramshop', 'text' => 'Listing Instagram Shop', 'group' => 'Marketplace'],

    ['value' => 'yamibuy', 'text' => 'Yamibuy', 'group' => 'Marketplace'],
    ['value' => 'listing_yamibuy', 'text' => 'Listing Yamibuy', 'group' => 'Marketplace'],

    ['value' => 'dhgate', 'text' => 'DHGate', 'group' => 'Marketplace'],
    ['value' => 'listing_dhgate', 'text' => 'Listing DHGate', 'group' => 'Marketplace'],

    ['value' => 'bestbuyusa', 'text' => 'Bestbuy USA', 'group' => 'Marketplace'],
    ['value' => 'listing_bestbuyusa', 'text' => 'Listing Bestbuy USA', 'group' => 'Marketplace'],

    ['value' => 'swgearexchange', 'text' => 'SW Gear Exchange', 'group' => 'Marketplace'],
    ['value' => 'listing_swgearexchange', 'text' => 'Listing SW Gear Exchange', 'group' => 'Marketplace'],

    ['value' => 'ebayTwo_zero_view', 'text' => 'Ebay 2 0 View', 'group' => 'Marketplace'],
    ['value' => 'ebayThree_zero_view', 'text' => 'Ebay 3 0 View', 'group' => 'Marketplace'],
    ['value' => 'walmart_zero_view', 'text' => 'Walmart 0 View', 'group' => 'Marketplace'],
    ['value' => 'aliexpress_zero_view', 'text' => 'Aliexpress 0 View', 'group' => 'Marketplace'],
    ['value' => 'ebay_variation_zero_view', 'text' => 'eBay Variation 0 View', 'group' => 'Marketplace'],
    ['value' => 'shopify_wholesale_zero_view', 'text' => 'Shopify Wholesale 0 View', 'group' => 'Marketplace'],
    ['value' => 'faire_zero_view', 'text' => 'Faire 0 View', 'group' => 'Marketplace'],
    ['value' => 'tiktokshop_zero_view', 'text' => 'Tiktok Shop 0 View', 'group' => 'Marketplace'],
    ['value' => 'mercariwship_zero_view', 'text' => 'Mercari w Ship 0 View', 'group' => 'Marketplace'],
    ['value' => 'fbmarketplace_zero_view', 'text' => 'FB Marketplace 0 View', 'group' => 'Marketplace'],
    ['value' => 'business5core_zero_view', 'text' => 'Business 5Core 0 View', 'group' => 'Marketplace'],
    ['value' => 'pls_zero_view', 'text' => 'PLS 0 View', 'group' => 'Marketplace'],
    ['value' => 'autods_zero_view', 'text' => 'Auto DS 0 View', 'group' => 'Marketplace'],
    ['value' => 'mercariwoship_zero_view', 'text' => 'Mercari w/o Ship 0 View', 'group' => 'Marketplace'],
    ['value' => 'poshmark_zero_view', 'text' => 'Poshmark 0 View', 'group' => 'Marketplace'],
    ['value' => 'tiendamia_zero_view', 'text' => 'Tiendamia 0 View', 'group' => 'Marketplace'],
    ['value' => 'shein_zero_view', 'text' => 'Shein 0 View', 'group' => 'Marketplace'],
    ['value' => 'spocket_zero_view', 'text' => 'Spocket 0 View', 'group' => 'Marketplace'],
    ['value' => 'zendrop_zero_view', 'text' => 'Zendrop 0 View', 'group' => 'Marketplace'],
    ['value' => 'syncee_zero_view', 'text' => 'Syncee 0 View', 'group' => 'Marketplace'],
    ['value' => 'offerup_zero_view', 'text' => 'Offerup 0 View', 'group' => 'Marketplace'],
    ['value' => 'neweggb2b_zero_view', 'text' => 'Newegg B2B 0 View', 'group' => 'Marketplace'],
    ['value' => 'appscenic_zero_view', 'text' => 'Appscenic 0 View', 'group' => 'Marketplace'],
    ['value' => 'fbshop_zero_view', 'text' => 'FB Shop 0 View', 'group' => 'Marketplace'],
    ['value' => 'instagramshop_zero_view', 'text' => 'Instagram Shop 0 View', 'group' => 'Marketplace'],
    ['value' => 'yamibuy_zero_view', 'text' => 'Yamibuy 0 View', 'group' => 'Marketplace'],
    ['value' => 'dhgate_zero_view', 'text' => 'DHGate 0 View', 'group' => 'Marketplace'],
    ['value' => 'bestbuyusa_zero_view', 'text' => 'Bestbuy USA 0 View', 'group' => 'Marketplace'],
    ['value' => 'swgearexchange_zero_view', 'text' => 'SW Gear Exchange 0 View', 'group' => 'Marketplace'],
];