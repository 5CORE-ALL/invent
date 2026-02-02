@extends('layouts.vertical', ['title' => 'Permissions', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
            max-height: 600px;
        }

        .custom-resizable-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .sidebar {
            min-height: calc(100vh - 70px);
            border-right: 1px solid #e0e0e0;
            background: #f8f9fa;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
        }

        .list-group-item {
            border: none;
            border-radius: 6px !important;
            margin-bottom: 5px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background-color: #e7f1ff;
            color: #0d6efd;
        }

        .list-group-item.active {
            background-color: #0d6efd;
            color: white;
            border-color: transparent;
        }

        .permission-table {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .permission-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            padding: 15px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        .permission-table td {
            vertical-align: middle;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .form-check-input {
            cursor: pointer;
            width: 1.2em;
            height: 1.2em;
            margin-top: 0;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .group-header {
            background-color: #f8f9fa !important;
            font-weight: 600;
        }

        .group-header h5 {
            color: #2c3e50;
            font-size: 1rem;
            margin: 0;
            padding: 10px 0;
        }

        .tab-pane.fade.show {
            display: table-row !important;
        }

        .btn-primary {
            padding: 10px 25px;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .text-center {
            text-align: center !important;
        }

        .sidebar {
            min-height: calc(100vh - 70px);
            border-right: 1px solid #e0e0e0;
        }

        .list-group-item {
            border: none;
            border-radius: 4px !important;
            margin-bottom: 2px;
            cursor: pointer;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }

        .list-group-item.active {
            background-color: #e7f1ff;
            color: #0d6efd;
            border-color: transparent;
        }

        .permission-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .permission-table td {
            vertical-align: middle;
        }

        .form-check-input {
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .group-header {
            background-color: #f8f9fa !important;
        }

        .tab-pane.fade.show {
            display: table-row !important;
        }

        .btn-primary {
            padding: 10px 25px;
            font-weight: 500;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Permissions',
        'sub_title' => 'User Management',
    ])

    <div class="container-fluid">
        <div class="row">
            <!-- Vertical Sidebar with Menu Groups -->
            <div class="col-md-2 bg-light sidebar">
                <div class="list-group list-group-flush mt-4">
                    @php
                        $menuGroups = [
                            'Main' => [['value' => 'dashboard', 'text' => 'Dashboard']],
                            'User Management' => [
                                ['value' => 'roles', 'text' => 'Roles'],
                                ['value' => 'permissions', 'text' => 'Permissions'],
                                ['value' => 'view_permissions', 'text' => 'View Permissions'],
                            ],
                            'Purchase Masters' => [
                                ['value' => 'purchase_master_categories', 'text' => 'Categories'],
                                ['value' => 'purchase_master_suppliers', 'text' => 'Suppliers'],
                                ['value' => 'purchase_master_claims_reimbursements', 'text' => 'Claims & Reimbursements'],
                                ['value' => 'purchase_master_forecast_analysis', 'text' => 'Forecast Analysis'],
                                ['value' => 'purchase_master_to_order_analysis', 'text' => 'To Order Analysis'],
                                ['value' => 'purchase_master_list_all_purchase_orders', 'text' => 'Purchase Contract'],
                                ['value' => 'purchase_master_purchase', 'text' => 'Purchase'],
                                ['value' => 'purchase_master_sourcing', 'text' => 'Sourcing'],
                                ['value' => 'purchase_master_ledger', 'text' => 'Ledger'],
                                ['value' => 'purchase_master_advance_payments', 'text' => 'Advance & Payments'],
                                ['value' => 'purchase_master_supplier_ledger', 'text' => 'Supplier Ledger'],
                                ['value' => 'purchase_master_mfrg_in_progress', 'text' => 'MIP'],
                                ['value' => 'purchase_master_ready_to_ship', 'text' => 'Ready To Ship'],
                                ['value' => 'purchase_master_mfrg_quality_check', 'text' => 'MFRG Quality Check'],
                                ['value' => 'purchase_master_china_load', 'text' => 'China Load'],
                                ['value' => 'purchase_master_transit_container_inv', 'text' => 'Transit Container INV'],
                                ['value' => 'purchase_master_container_planning', 'text' => 'Container Planning'],
                                ['value' => 'purchase_master_on_sea_transit', 'text' => 'On Sea Transit'],
                                ['value' => 'purchase_master_on_road_transit', 'text' => 'On Road Transit'],
                                ['value' => 'purchase_master_quality_enhance', 'text' => 'Quality Enhance'],
                            ],
                            'Product Masters' => [
                                ['value' => 'cp_masters', 'text' => 'CP Masters'],
                                ['value' => 'pricing_masters', 'text' => 'Pricing Masters'],
                                ['value' => 'pricing_masters_clone', 'text' => 'Pricing Masters Clone'],
                                ['value' => 'listing_analysis', 'text' => 'Listing Analysis'],
                                ['value' => 'cost_price_analysis', 'text' => 'Cost Price Analysis'],
                                ['value' => 'movement_analysis', 'text' => 'Movement Analysis'],
                                ['value' => 'tobedc_list', 'text' => '2BDC'],
                                ['value' => 'transit_analysis', 'text' => 'Transit Analysis'],
                                ['value' => 'review_analysis', 'text' => 'Review Analysis'],
                                ['value' => 'profit_roi_analysis', 'text' => 'Profit & ROI Analysis'],
                                ['value' => 'profit_returns_analysis', 'text' => 'Returns Analysis'],
                                ['value' => 'returns_analysis', 'text' => 'Returns Analysis'],
                                ['value' => 'stock_verification', 'text' => 'Stock Verification'],
                                ['value' => 'shortfall_analysis', 'text' => 'Shortfall Analysis'],

                               
                            ],
                            'Advertisement Master' => [
                                 ['value' => 'ppc', 'text' => 'PPC'],
                                ['value' => 'advt_kw_amazon', 'text' => 'KW Advt - Amazon'],
                                ['value' => 'advt_kw_ebay', 'text' => 'KW Advt - eBay'],
                                ['value' => 'advt_kw_walmart', 'text' => 'KW Advt - Walmart'],
                                ['value' => 'advt_prod_target_amazon', 'text' => 'Prod Target Advt - Amazon'],
                                ['value' => 'advt_headline_amazon', 'text' => 'Headline Advt - Amazon'],
                                ['value' => 'advt_promoted_ebay', 'text' => 'Promoted Advt - eBay'],
                                ['value' => 'advt_shopping_google', 'text' => 'Shopping Advt - Google'],
                                ['value' => 'advt_demand_gen_google', 'text' => 'Demand Gen Parents - Google Networks'],
                                ['value' => 'advt_meta_parent_img_fb', 'text' => 'Meta Parent Advt Images - FB'],
                                ['value' => 'advt_meta_parent_img_insta', 'text' => 'Meta Parent Advt Images - Insta'],
                                ['value' => 'advt_meta_parent_video_fb', 'text' => 'Meta Parent Advt Video - FB'],
                                ['value' => 'advt_meta_parent_video_insta', 'text' => 'Meta Parent Advt Video - Insta'],
                                ['value' => 'advt_serp_google', 'text' => 'Serp Advt - Google SERP'],
                                ['value' => 'advt_youtube_groups', 'text' => 'Youtube Advt Groups'],
                                ['value' => 'advt_demand_gen_groups', 'text' => 'Demand Gen Groups - Google Networks'],
                                ['value' => 'advt_budget_meta_img_fb', 'text' => 'Budget Control Meta Images - FB'],
                                ['value' => 'advt_budget_meta_img_insta', 'text' => 'Budget Control Meta Images - Insta'],
                                ['value' => 'advt_budget_meta_video_fb', 'text' => 'Budget Control Meta Video - FB'],
                                ['value' => 'advt_budget_meta_video_insta', 'text' => 'Budget Control Meta Video - Insta'],
                            

                            ],
                           
                            'Channel' => [
                                ['value' => 'channel_master', 'text' => 'Channel Master'],
                                ['value' => 'channel_opportunities', 'text' => 'Opportunities'],
                                ['value' => 'channel_application_approvals', 'text' => 'Application & Approvals'],
                                ['value' => 'channel_setup_account_shop', 'text' => 'Setup Account & Shop'],
                                ['value' => 'channel_price_adjustment_manager', 'text' => 'Price Adjustment Manager'],
                                ['value' => 'channel_movement_analysis', 'text' => 'Movement Analysis'],
                                ['value' => 'channel_sales_analysis', 'text' => 'Sales and Analysis'],
                                ['value' => 'channel_new_marketplaces', 'text' => 'New Marketplaces'],
                                ['value' => 'channel_promotion_master', 'text' => 'Promotion Master'],
                                ['value' => 'channel_odr_rate', 'text' => 'ODR Rate'],
                                ['value' => 'channel_fulfillment_rate', 'text' => 'Fulfillment Rate'],
                                ['value' => 'channel_valid_tracking_rate', 'text' => 'Valid Tracking Rate'],
                                ['value' => 'channel_late_shipment', 'text' => 'Late Shipment'],
                                ['value' => 'channel_on_time_delivery', 'text' => 'On Time Delivery'],
                                ['value' => 'channel_negative_seller', 'text' => 'Negative Seller'],
                                ['value' => 'channel_a_z_claims', 'text' => 'A-Z Claims'],
                                ['value' => 'channel_violation_compliance', 'text' => 'Violation/Compliance'],
                                ['value' => 'channel_refunds_returns', 'text' => 'Refunds / Returns'],
                                ['value' => 'channel_review_dashboard', 'text' => 'Review Dashboard'],
                                ['value' => 'channel_amazon_product_reviews', 'text' => 'Amazon Product Reviews'],
                                ['value' => 'channel_return_analysis', 'text' => 'Return Analysis'],
                                ['value' => 'channel_shipping_master', 'text' => 'Shipping Master'],
                                ['value' => 'channel_traffic_master', 'text' => 'Traffic Master'],
                                ['value' => 'channel_expenses_analysis', 'text' => 'Expenses Analysis'],
                                ['value' => 'channel_review_analysis', 'text' => 'Review Analysis'],
                                ['value' => 'channel_health_analysis', 'text' => 'Health Analysis'],
                                ['value' => 'channel_listing_analysis', 'text' => 'Listing Analysis'],
                                ['value' => 'channel_shipping_analysis', 'text' => 'Shipping Analysis'],
                                ['value' => 'channel_c_care_analysis', 'text' => 'C Care Analysis'],
                            ],

                             'Marketing Masters' => [
                                ['value' => 'listing_master', 'text' => 'Listing Masters'],
                                ['value' => 'low_visibility_masters', 'text' => 'Low Visibility Masters'],
                                ['value' => 'listing_audit_masters', 'text' => 'Listing Audit Masters'],
                                ['value' => 'zero_views_masters', 'text' => '0 Views Masters'],
                                ['value' => 'movement_analysis_master', 'text' => 'Movement Analysis Master'],
                                ['value' => 'carousel_sales_master', 'text' => 'Carousel Sales Master'],
                                ['value' => 'email_marketing', 'text' => 'Email Marketing'],
                                ['value' => 'whatsapp_marketing', 'text' => 'Whatsapp Marketing'],
                                ['value' => 'sms_marketing', 'text' => 'SMS Marketing'],
                                ['value' => 'dm_marketing', 'text' => 'DM Marketing'],
                                ['value' => 'phone_marketing', 'text' => 'Phone Appt Marketing'],
                                ['value' => 'letter_marketing', 'text' => 'Letter Marketing'],
                                ['value' => 'video_directory', 'text' => 'Video Directory'],
                                ['value' => 'product_videos', 'text' => 'Product Videos'],
                                ['value' => 'product_video_upload', 'text' => 'Product Video Upload'],
                                ['value' => 'assembly_video', 'text' => 'Assembly Video'],
                                ['value' => 'assembly_video_upload', 'text' => 'Assembly Video Upload'],
                                ['value' => '3d_video', 'text' => '3D Video'],
                                ['value' => '3d_video_upload', 'text' => '3D Video Upload'],
                                ['value' => '360_video', 'text' => '360 Video'],
                                ['value' => '360_video_upload', 'text' => '360 Video Upload'],
                                ['value' => 'benefits_video', 'text' => 'Benefits Video'],
                                ['value' => 'benefits_video_upload', 'text' => 'Benefits Video Upload'],
                                ['value' => 'diy_video', 'text' => 'DIY Video'],
                                ['value' => 'diy_video_upload', 'text' => 'DIY Video Upload'],
                                ['value' => 'shoppable_video_1_1_ratio', 'text' => '1:1 RATIO'],
                                ['value' => 'shoppable_video_4_5_ratio', 'text' => '4:5 RATIO'],
                                ['value' => 'shoppable_video_9_16_ratio', 'text' => '9:16 RATIO'],
                                ['value' => 'shoppable_video_16_9_ratio', 'text' => '16:9 RATIO'],
                                ['value' => 'video_ads_master', 'text' => 'Video Ads Master'],
                                ['value' => 'facebook_ads', 'text' => 'Facebook Ads'],
                                ['value' => 'facebook_feed_ads', 'text' => 'Facebook In Feed'],
                                ['value' => 'facebook_reel_ads', 'text' => 'Facebook Reel Ads'],
                                ['value' => 'instagram_ads', 'text' => 'Instagram Ads'],
                                ['value' => 'instagram_feed_ads', 'text' => 'Instagram In Feed'],
                                ['value' => 'instagram_reel_ads', 'text' => 'Instagram Reel Ads'],
                                ['value' => 'youtube_ads', 'text' => 'YouTube Ads'],
                                ['value' => 'youtube_shorts_ads', 'text' => 'YouTube Shorts Ads'],
                                ['value' => 'tiktok_ads', 'text' => 'Tik Tok Video Ads'],
                                ['value' => 'facebook_ads_manager', 'text' => 'Facebook Ads Manager'],
                                ['value' => 'image_carousel_ad_running', 'text' => 'Image Carousel Ad Running'],
                                ['value' => 'lqs_masters', 'text' => 'LQS Masters'],
                                ['value' => 'overall_lqs_cvr', 'text' => 'OverAll LQS - CVR'],
                                ['value' => 'listing_LQS_masters', 'text' => 'LQS - Listing'],
                                ['value' => 'cvr_LQS_masters', 'text' => 'Amazon LQS - CVR'],
                                ['value' => 'ebay_cvr_lqs_masters', 'text' => 'Ebay LQS - CVR'],
                                ['value' => 'traffic_session_masters', 'text' => 'Traffic And Session Masters'],
                                ['value' => 'conversion_content_masters', 'text' => 'Conversion Content Masters'],
                                ['value' => 'conversion_other_masters', 'text' => 'Conversion Other Masters'],
                                ['value' => 'pricing_masters_marketing', 'text' => 'Pricing Masters'],
                            ],
                               
                            'Shopify' => [
                                ['value' => 'shopify_products', 'text' => 'Shopify Products'],
                                ['value' => 'shopify_inventory', 'text' => 'Shopify Inventory'],
                                ['value' => 'movement_analysis_y2y', 'text' => 'Movement Analysis Y2Y'],
                                ['value' => 'movement_analysis_m2m', 'text' => 'Movement Analysis M2M'],
                                ['value' => 'movement_analysis_s2s', 'text' => 'Movement Analysis S2S'],
                                ['value' => 'shopify_forecast_analysis', 'text' => 'Forecast Analysis (Shopify)'],
                            ],

                            'Inventory Management' => [
                                ['value' => 'view_inventory', 'text' => 'View Inventory'],
                                ['value' => 'verifications_adjustments', 'text' => 'Verifications & Adjustments'],
                                ['value' => 'incoming', 'text' => 'Incoming'],
                                ['value' => 'outgoing', 'text' => 'Outgoing'],
                                ['value' => 'stock_adjustment', 'text' => 'Stock Adjustment'],
                                ['value' => 'stock_transfer', 'text' => 'Stock Transfer'],
                                ['value' => 'stock_balance', 'text' => 'Stock Balance'],
                                ['value' => 'trash_entries', 'text' => 'Trash Entries'],
                                ['value' => 'pallete_sales', 'text' => 'Pallete Sales'],
                            ],


                            'Inventory Warehouse' => [
                                ['value' => 'list_all_warehouses', 'text' => 'List All Warehouses'],
                                ['value' => 'inventory_locator', 'text' => 'Inventory Locator'],
                                ['value' => 'transfers', 'text' => 'Transfers'],
                                ['value' => 'returns_godown', 'text' => 'Returns Godown'],
                                ['value' => 'openbox_godown', 'text' => 'Open Box Godown'],
                                ['value' => 'showroom_godown', 'text' => 'Showroom Godown'],
                                ['value' => 'useditem_godown', 'text' => 'Used Item Godown'],
                                ['value' => 'trash_godown', 'text' => 'Trash Godown'],
                            ],
                             
                            'Marketplace' => [
                                ['value' => 'amazon_analytics', 'text' => 'Amazon Analytics'],
                                ['value' => 'amz_zero_view', 'text' => 'Amazon 0 View'],
                                ['value' => 'amazon_low_visibility', 'text' => 'Amazon Low Visibility'],
                                ['value' => 'amazon_fba_analysis', 'text' => 'Amazon FBA Analysis'],
                                ['value' => 'fba_inv_age', 'text' => 'FBA INV AGE'],
                                ['value' => 'amazon_pricing', 'text' => 'Amazon Pricing'],
                                ['value' => 'listing_amazon', 'text' => 'Listing Amazon'],
                                ['value' => 'listing_audit_amazon', 'text' => 'Listing Audit Amazon'],
                                ['value' => 'ebay', 'text' => 'eBay'],
                                ['value' => 'ebay_zero_view', 'text' => 'eBay 0 View'],
                                ['value' => 'ebay_low_visibility', 'text' => 'eBay Low Visibility'],
                                ['value' => 'listing_ebay', 'text' => 'Listing eBay'],
                                ['value' => 'listing_audit_ebay', 'text' => 'Listing Audit eBay'],
                                ['value' => 'shopify_b2c', 'text' => 'Shopify B2C'],
                                ['value' => 'listing_shopifyb2c', 'text' => 'Listing Shopify B2C'],
                                ['value' => 'listing_audit_shopifyb2c', 'text' => 'Listing Audit Shopify B2C'],
                                ['value' => 'macys', 'text' => 'Macy\'s'],
                                ['value' => 'listing_macys', 'text' => 'Listing Macy\'s'],
                                ['value' => 'listing_audit_macys', 'text' => 'Listing Audit Macy\'s'],
                                ['value' => 'newegg_b2c', 'text' => 'Newegg B2C'],
                                ['value' => 'listing_neweggb2c', 'text' => 'Listing Newegg B2C'],
                                ['value' => 'wayfair', 'text' => 'Wayfair'],
                                ['value' => 'listing_wayfair', 'text' => 'Listing Wayfair'],
                                ['value' => 'reverb', 'text' => 'Reverb'],
                                ['value' => 'listing_reverb', 'text' => 'Listing Reverb'],
                                ['value' => 'temu', 'text' => 'Temu'],
                                ['value' => 'listing_temu', 'text' => 'Listing Temu'],
                                ['value' => 'doba', 'text' => 'Doba'],
                                ['value' => 'listing_doba', 'text' => 'Listing Doba'],
                                ['value' => 'ebayTwo', 'text' => 'Ebay 2'],
                                ['value' => 'listing_ebayTwo', 'text' => 'Listing Ebay 2'],
                                ['value' => 'ebayThree', 'text' => 'Ebay 3'],
                                ['value' => 'listing_ebayThree', 'text' => 'Listing Ebay 3'],
                                ['value' => 'walmart', 'text' => 'Walmart'],
                                ['value' => 'listing_walmart', 'text' => 'Listing Walmart'],
                                ['value' => 'aliexpress', 'text' => 'Aliexpress'],
                                ['value' => 'listing_aliexpress', 'text' => 'Listing Aliexpress'],
                                ['value' => 'shopifywholesale', 'text' => 'Shopify wholesale/DS'],
                                ['value' => 'listing_shopifywholesale', 'text' => 'Listing Shopify wholesale/DS'],
                                ['value' => 'faire', 'text' => 'Faire'],
                                ['value' => 'listing_faire', 'text' => 'Listing Faire'],
                                ['value' => 'tiktokshop', 'text' => 'Tiktok Shop'],
                                ['value' => 'listing_tiktokshop', 'text' => 'Listing Tiktok Shop'],
                                ['value' => 'mercariwship', 'text' => 'Mercari w Ship'],
                                ['value' => 'listing_mercariwship', 'text' => 'Listing Mercari w Ship'],
                                ['value' => 'fbmarketplace', 'text' => 'FB Marketplace'],
                                ['value' => 'listing_fbmarketplace', 'text' => 'Listing FB Marketplace'],
                                ['value' => 'business5core', 'text' => 'Business 5Core'],
                                ['value' => 'listing_business5core', 'text' => 'Listing Business 5Core'],
                                ['value' => 'pls', 'text' => 'PLS'],
                                ['value' => 'listing_pls', 'text' => 'Listing PLS'],
                                ['value' => 'mercariwship', 'text' => 'Mercari w/o Ship'],
                                ['value' => 'listing_mercariwship', 'text' => 'Listing Mercari w/o Ship'],
                                ['value' => 'tiendamia', 'text' => 'Tiendamia'],
                                ['value' => 'listing_tiendamia', 'text' => 'Listing Tiendamia'],
                                ['value' => 'shein', 'text' => 'Shein'],
                                ['value' => 'listing_shein', 'text' => 'Listing Shein'],
                                ['value' => 'fbshop', 'text' => 'FB Shop'],
                                ['value' => 'listing_fbshop', 'text' => 'Listing FB Shop'],
                                ['value' => 'instagramshop', 'text' => 'Instagram Shop'],
                                ['value' => 'listing_instagramshop', 'text' => 'Listing Instagram Shop'],
                                ['value' => 'dhgate', 'text' => 'DHGate'],
                                ['value' => 'listing_dhgate', 'text' => 'Listing DHGate'],
                                ['value' => 'bestbuyusa', 'text' => 'Bestbuy USA'],
                                ['value' => 'listing_bestbuyusa', 'text' => 'Listing Bestbuy USA'],
                            ],
                        ];
                    @endphp
                    @foreach ($menuGroups as $group => $items)
                        <button class="list-group-item list-group-item-action text-dark fw-bold mb-2" data-bs-toggle="tab"
                            data-bs-target="#tab-{{ Str::slug($group) }}">
                            {{ $group }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Manage Permissions</h4>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkAllPermissions">
                                <label class="form-check-label" for="checkAllPermissions">Check All Permissions</label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="permissionsForm" action="{{ route('permissions.store') }}" method="POST">
                            @csrf
                            <!-- Role Headers -->
                            <div class="table-container">
                                <table class="table custom-resizable-table permission-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 200px">Permission Item</th>
                                            <th class="text-center" style="min-width: 100px">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input check-all-role"
                                                        data-role="viewer">
                                                </div>
                                                Viewer
                                                <div class="permission-actions">
                                                    <small>View</small>
                                                </div>

                                            </th>

                                            <th class="text-center" style="min-width: 100px">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input check-all-role"
                                                        data-role="user">
                                                </div>
                                                user
                                                <div class="permission-actions">
                                                    <small>View</small> | <small>Create</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="min-width: 100px">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input check-all-role"
                                                        data-role="manager">
                                                </div>
                                                Manager
                                                <div class="permission-actions">
                                                    <small>View</small> | <small>Create</small> | <small>Edit</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="min-width: 100px">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input check-all-role"
                                                        data-role="admin">
                                                </div>
                                                Admin
                                                <div class="permission-actions">
                                                    <small>All</small>
                                                </div>
                                            </th>
                                            <th class="text-center" style="min-width: 100px">
                                                <div class="form-check mb-2">
                                                    <input type="checkbox" class="form-check-input check-all-role"
                                                        data-role="superadmin">
                                                </div>
                                                Super Admin
                                                <div class="permission-actions">
                                                    <small>All</small>
                                                </div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="tab-content">

                                        @foreach ($menuGroups as $group => $items)
                                            <tr class="tab-pane fade" id="tab-{{ Str::slug($group) }}">
                                                <td colspan="6" class="group-header bg-light">
                                                    <h5 class="mb-0">{{ $group }}</h5>
                                                </td>
                                            </tr>
                                            @foreach ($items as $item)
                                                <tr class="tab-pane fade" id="tab-{{ Str::slug($group) }}">
                                                    <td>{{ $item['text'] }} <small
                                                            class="text-muted">({{ $item['value'] }})</small></td>
                                                    {{-- Viewer --}}
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            @php
                                                                $viewerChecked =
                                                                    isset($permissions['viewer']) &&
                                                                    isset($permissions['viewer'][$item['value']]);
                                                            @endphp
                                                            {{-- Debug: viewer-{{ $item['value'] }}: {{ $viewerChecked ? 'CHECKED' : 'NOT CHECKED' }} --}}
                                                            <input type="checkbox" class="form-check-input"
                                                                name="permissions[viewer][{{ $item['value'] }}]"
                                                                @if ($viewerChecked) checked @endif>
                                                        </div>
                                                    </td>

                                                    {{-- user --}}
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" class="form-check-input"
                                                                name="permissions[user][{{ $item['value'] }}]"
                                                                @if (isset($permissions['user']) && isset($permissions['user'][$item['value']])) checked @endif>
                                                        </div>
                                                    </td>
                                                    {{-- Manager --}}
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" class="form-check-input"
                                                                name="permissions[manager][{{ $item['value'] }}]"
                                                                @if (isset($permissions['manager']) && isset($permissions['manager'][$item['value']])) checked @endif>
                                                        </div>
                                                    </td>
                                                    {{-- Admin --}}
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" class="form-check-input"
                                                                name="permissions[admin][{{ $item['value'] }}]"
                                                                @if (isset($permissions['admin']) && isset($permissions['admin'][$item['value']])) checked @endif>
                                                        </div>
                                                    </td>
                                                    {{-- Superadmin --}}
                                                    <td class="text-center">
                                                        <div class="form-check d-flex justify-content-center">
                                                            <input type="checkbox" class="form-check-input"
                                                                name="permissions[superadmin][{{ $item['value'] }}]"
                                                                @if (isset($permissions['superadmin']) && isset($permissions['superadmin'][$item['value']])) checked @endif>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Save Permissions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submission
            document.getElementById('permissionsForm').addEventListener('submit', function(e) {
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Toastify({
                                text: data.message,
                                duration: 3000,
                                close: true,
                                gravity: 'top',
                                position: 'right',
                                backgroundColor: '#4caf50',
                            }).showToast();
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(error => {
                        Toastify({
                            text: error.message || 'An error occurred while saving permissions',
                            duration: 3000,
                            close: true,
                            gravity: 'top',
                            position: 'right',
                            backgroundColor: '#f44336',
                        }).showToast();
                    });
            });
            // Handle main "Check All Permissions" checkbox
            document.getElementById('checkAllPermissions').addEventListener('change', function() {
                const isChecked = this.checked;
                // Check/uncheck all checkboxes in the current tab
                document.querySelectorAll('tr.show input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                // Update all role header checkboxes
                document.querySelectorAll('.check-all-role').forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });

            // Handle role-specific "Check All" checkboxes
            document.querySelectorAll('.check-all-role').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const role = this.getAttribute('data-role');
                    const isChecked = this.checked;
                    // Find all checkboxes for this role that are currently visible
                    document.querySelectorAll(`tr.show input[name^="permissions[${role}]"]`)
                        .forEach(cb => {
                            cb.checked = isChecked;
                        });
                });
            });

            // Show all tabs by default so checkboxes can be checked
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.add('show');
            });

            // Show first tab as active
            const firstTab = document.querySelector('.list-group-item');
            if (firstTab) {
                firstTab.classList.add('active');
            }

            // Tab click handling
            document.querySelectorAll('.list-group-item').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.list-group-item').forEach(btn => {
                        btn.classList.remove('active');
                    });

                    // Add active class to clicked button
                    this.classList.add('active');

                    // Hide all tab panes
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show');
                    });

                    // Show selected tab panes
                    const targetId = this.getAttribute('data-bs-target');
                    document.querySelectorAll(targetId).forEach(el => {
                        el.classList.add('show');
                    });
                });
            });

            // Select all checkboxes in a column
            document.querySelectorAll('th').forEach((header, index) => {
                if (index > 0) { // Skip first header (Permission Item)
                    header.style.cursor = 'pointer';
                    header.addEventListener('click', function() {
                        const role = this.textContent.toLowerCase().trim();
                        const checkboxes = document.querySelectorAll(
                            `input[name^="permissions[${role}]"]`);
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                        checkboxes.forEach(cb => cb.checked = !allChecked);
                    });
                }
            });
        });
    </script>
@endsection
