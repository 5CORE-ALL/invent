<?php

/**
 * Tab / PWA icons by page title and route.
 *
 * - definitions: reusable icon ids → public-relative file (under public/).
 * - by_title_exact / by_title_contains: match layout $title (see @extends).
 * - by_any_segment: catch-all route name `any` → first URL segment (e.g. index → dashboard).
 * - by_route: ordered patterns vs route name (Str::is), from page_icons_routes.php — covers left-sidebar links.
 */
return [
    'definitions' => [
        'task' => ['file' => 'images/favicons/task.svg'],
        'auth' => ['file' => 'images/favicons/auth.svg'],
        'error' => ['file' => 'images/favicons/error.svg'],
        'maintenance' => ['file' => 'images/favicons/maintenance.svg'],
        'pricing_cvr' => ['file' => 'images/favicons/pricing-cvr.svg'],
        'team' => ['file' => 'images/favicons/team.svg'],
        'dashboard' => ['file' => 'images/favicons/dashboard.svg'],
        'marketplace' => ['file' => 'images/favicons/marketplace.svg'],
        'repricer' => ['file' => 'images/favicons/repricer.svg'],
        'ai_title' => ['file' => 'images/favicons/ai-title.svg'],
        'sidebar_resources' => ['file' => 'images/favicons/sidebar-resources.svg'],
        'sidebar_warehouse' => ['file' => 'images/favicons/sidebar-warehouse.svg'],
        'sidebar_listing' => ['file' => 'images/favicons/sidebar-listing.svg'],
        'sidebar_reviews' => ['file' => 'images/favicons/sidebar-reviews.svg'],
        'sidebar_crm' => ['file' => 'images/favicons/sidebar-crm.svg'],
        'sidebar_customer_care' => ['file' => 'images/favicons/sidebar-customer-care.svg'],
        'sidebar_purchase' => ['file' => 'images/favicons/sidebar-purchase.svg'],
        'sidebar_product' => ['file' => 'images/favicons/sidebar-product.svg'],
        'sidebar_inventory' => ['file' => 'images/favicons/sidebar-inventory.svg'],
        'sidebar_channel' => ['file' => 'images/favicons/sidebar-channel.svg'],
        'sidebar_ads' => ['file' => 'images/favicons/sidebar-ads.svg'],
        'sidebar_video' => ['file' => 'images/favicons/sidebar-video.svg'],
        'sidebar_analytics' => ['file' => 'images/favicons/sidebar-analytics.svg'],
        'sidebar_orders' => ['file' => 'images/favicons/sidebar-orders.svg'],
        'sidebar_growth' => ['file' => 'images/favicons/sidebar-growth.svg'],
        'sidebar_finance' => ['file' => 'images/favicons/sidebar-finance.svg'],
        'sidebar_verify' => ['file' => 'images/favicons/sidebar-verify.svg'],
        'sidebar_shopify' => ['file' => 'images/favicons/sidebar-shopify.svg'],
    ],

    'by_title_exact' => [
        'Task Manager' => 'task',
        'Task Summary' => 'task',
        'Automated Tasks' => 'task',
        'Create Task' => 'task',
        'Edit Task' => 'task',
        'Archived Tasks' => 'task',
        'Create Automated Task' => 'task',
        'Edit Automated Task' => 'task',
        'Log In' => 'auth',
        'Register' => 'auth',
        'Recover Password' => 'auth',
        'Logout' => 'auth',
        'Lock Screen' => 'auth',
        'Error 404' => 'error',
        'Error 500' => 'error',
        'Maintenance' => 'maintenance',
        'Master Analytics' => 'pricing_cvr',
        'CVR Master' => 'pricing_cvr',
        'Amazon Competitor Search' => 'repricer',
        'eBay Competitor Search' => 'repricer',
        'AI Title Manager' => 'ai_title',
        'Team Management' => 'team',
        'Dashboard' => 'dashboard',
        'Active Channel' => 'marketplace',
    ],

    'by_title_contains' => [],

    /**
     * Route named `any` with first segment from /{any} (sidebar: route('any', 'index')).
     */
    'by_any_segment' => [
        'index' => 'dashboard',
        'home' => 'dashboard',
    ],

    'by_route' => require __DIR__.'/page_icons_routes.php',
];
