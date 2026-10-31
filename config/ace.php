<?php
/**
 * Pages that get the "+ ATE" / "ATE" buttons in the top bar (Auto Task Events).
 *
 * view name => data source. A page's rows come from its own JSON data endpoint:
 *   []                        -> auto-detected from the page's Tabulator table (its ajaxURL)
 *   ['route' => 'route.name'] -> that named GET route
 *   ['url' => '/some/path']   -> that GET path
 *
 * The Users and Promotions pages include the buttons in their own toolbar and are not listed here.
 */
return [
    'pages' => [
        // ---- Analytics pages ----
        'channels.all-marketplace-master' => [],
        'channels.l7-marketplace-master' => [],
        'channels.yesterday-marketplace-master' => [],
        'fba.fba_analytics' => [],
        'market-places.alibaba_analytics' => ['route' => 'alibaba.analytics.data'],
        'market-places.aliexpress_analytics' => ['url' => '/aliexpress/view-data'],
        'market-places.aliexpress_pricing_view' => [],
        'market-places.aliexpress_tabulator_view' => [],
        'market-places.amazon_tabulator_view' => [],
        'market-places.amazonpricing_cvr_tabular' => [],
        'market-places.amz_zero_views_diagnostic' => [],
        'market-places.bestbuy_tabulator_view' => [],
        'market-places.depop_pricing' => [],
        'market-places.dhgate_analytics' => ['route' => 'dhgate.analytics.data'],
        'market-places.doba-analytics' => ['url' => '/doba-data-view'],
        'market-places.doba_tabulator_view' => [],
        'market-places.doba_withoutship_tabulator_view' => [],
        'market-places.ebay' => ['url' => '/ebay-data-view'],
        'market-places.ebay2_tabulator_view' => [],
        'market-places.ebay2op_tabulator_view' => [],
        'market-places.ebay3_tabulator_view' => [],
        'market-places.ebayThreeAnalysis' => ['url' => '/ebay3/view-data'],
        'market-places.ebayTwoAnalysis' => ['url' => '/ebay2-data-view'],
        'market-places.ebay_pricing_data' => [],
        'market-places.ebay_tabulator_view' => [],
        'market-places.faire_pricing_view' => [],
        'market-places.faire_tabulator_view' => [],
        'market-places.fb_marketplace_tabulator_view' => [],
        'market-places.instagram_analytics' => [],
        'market-places.macys_tabulator_view' => [],
        'market-places.mercari_with_ship_tabulator_view' => [],
        'market-places.mercari_without_ship_tabulator_view' => [],
        'market-places.new_temuone_tabulator_view' => [],
        'market-places.new_temutwo_tabulator_view' => [],
        'market-places.newegg_pricing_tabulator_view' => [],
        'market-places.offerup_analytics' => ['route' => 'offerup.analytics.data'],
        'market-places.pls_pricing_view' => [],
        'market-places.purchasing_power_tabulator_view' => [],
        'market-places.reverb_sales_tabulator_view' => [],
        'market-places.reverb_tabulator_view' => [],
        'market-places.shein_pricing_view' => [],
        'market-places.shein_tabulator_view' => [],
        'market-places.shopify_b2b_tabulator_view' => [],
        'market-places.shopify_b2c_tabulator_view' => [],
        'market-places.shopify_openbox_analytics' => ['route' => 'shopify.openbox.analytics.data'],
        'market-places.temu2_tabulator_view' => [],
        'market-places.temu3_decrease' => [],
        'market-places.temu3_tabulator_view' => [],
        'market-places.tiktok_tabulator_view' => [],
        'market-places.topdawg_tabulator_view' => [],
        'market-places.vinted_analytics' => [],
        'market-places.vinted_pricing' => [],
        'market-places.walmart_sheet_upload_view' => ['url' => '/walmart-sheet-upload-data-json'],
        'market-places.walmart_tabulator_view' => [],
        'market-places.wayfair_pricing_view' => [],
        'market-places.zero-market-places.bestbuyUSAAnalytics' => ['url' => '/zero_bestbuyusa/view-data'],

        // ---- Active Channels pages ----
        'market-places.variations_verify_masters' => [],
        'channels.api_vs_sheet.tabulator' => ['route' => 'api.vs.sheet.tabulator.data'],
        'channels.escalated_claims.tabulator' => ['route' => 'escalated.claims.tabulator.data'],
        'channels.shipping_health_overview.tabulator' => ['route' => 'shipping.health.overview.tabulator.data'],
        'channels.customer_care_health.tabulator' => ['route' => 'customer.care.health.tabulator.data'],
        'channels.shipping_health_score.tabulator' => ['route' => 'escalated.claims.tabulator.data'],
    ],
];
