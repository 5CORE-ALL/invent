@extends('layouts.vertical', ['title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'hideFloatingTaskButton' => true])

@section('css')
<style>
    .dashboard-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 0.75rem;
        margin-top: 0.25rem;
        margin-bottom: 0.75rem;
        align-items: stretch;
    }
    /* Uniform rectangular tiles — no full-width banners */
    .dashboard-cards-grid > .dashboard-badge-panel {
        margin: 0 !important;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        min-height: 220px;
        height: 100%;
        padding: 0.75rem !important;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
        border-radius: 0.5rem !important;
        box-sizing: border-box;
    }
    .dashboard-badge-panel {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.55rem;
    }
    .dashboard-badge-panel__icon {
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
        min-height: 40px;
        align-self: flex-start;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.35rem;
        background: linear-gradient(145deg, #dbeafe, #eff6ff);
        font-size: 1.25rem;
        line-height: 1;
        overflow: hidden;
    }
    .dashboard-badge-panel__icon-emoji {
        display: block;
        font-style: normal;
        line-height: 1;
    }
    .dashboard-badge-panel__icon .ri-store-2-line {
        font-size: 1.15rem;
        color: #475569;
        line-height: 1;
    }
    .dashboard-badge-panel__body {
        flex: 1 1 auto;
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .dashboard-badge-panel__badges {
        display: flex;
        flex-wrap: wrap;
        align-content: flex-start;
        gap: 0.3rem;
        width: 100%;
        max-width: 100%;
        flex: 1 1 auto;
    }
    .dashboard-badge-panel__badges .badge {
        white-space: nowrap;
        font-size: 0.72rem !important;
        padding: 0.28rem 0.45rem !important;
        font-weight: 700 !important;
        line-height: 1.2 !important;
        border-radius: 0.25rem !important;
    }
    .dashboard-badge-panel__header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.2rem 0.5rem;
        margin-bottom: 0 !important;
        width: 100%;
        max-width: 100%;
    }
    .dashboard-badge-panel__header .dash-card-actions {
        margin-left: auto;
    }
    .dashboard-badge-panel__header h6 {
        font-size: 0.9rem;
        font-weight: 700;
        margin: 0;
    }
    .dashboard-badge-panel__updated {
        font-size: 0.7rem;
        color: #6b7280;
        white-space: nowrap;
    }
    .dashboard-badge-panel__icon-img {
        width: 40px;
        height: 40px;
        object-fit: contain;
        border-radius: 0.35rem;
        display: block;
    }
    .dashboard-badge-panel__icon-img--tile {
        object-fit: cover;
        border-radius: 0.35rem;
    }
    .dashboard-badge-panel__badges .lc-score-badge {
        border-radius: 0.25rem !important;
        font-size: 0.78rem !important;
        padding: 0.35rem 0.55rem !important;
        font-weight: 700 !important;
        cursor: pointer;
        user-select: none;
    }
    @media (min-width: 1400px) {
        .dashboard-cards-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    @media (max-width: 991.98px) {
        .dashboard-cards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 575.98px) {
        .dashboard-cards-grid {
            grid-template-columns: 1fr;
        }
        .dashboard-cards-grid > .dashboard-badge-panel {
            min-height: 0;
        }
    }
    #lcMetricChartModal.modal {
        --tz-modal-width: 100%;
        --tz-modal-margin: 0.5rem 0;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    #lcMetricChartModal .modal-dialog {
        width: 100% !important;
        max-width: none !important;
        margin: 0.5rem 0 0 0 !important;
    }
    #lcMetricChartModal .modal-content {
        border-radius: 0;
        width: 100%;
        max-width: 100%;
    }
</style>
@endsection

@section('content')
@include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Dashboard'])

@php
    use App\Models\BadgeData;

    $ostRow = BadgeData::forPage('on-sea-transit');
    $ost = array_merge([
        'pre_load' => 0,
        'on_sea' => 0,
        'landed' => 0,
        'transit' => 0,
        'total_value' => 0,
        'due' => 0,
        'value' => 0,
    ], $ostRow?->data ?? []);

    $forecastBadgeRow = BadgeData::forPage('forecast-analysis');
    $faRaw = $forecastBadgeRow?->data ?? [];
    $fa = [
        'total_msl_c' => (float) ($faRaw['total_msl_c'] ?? $faRaw['msl_lp'] ?? 0),
        'total_msl_sp_amz' => (float) ($faRaw['total_msl_sp_amz'] ?? $faRaw['msl_sp'] ?? 0),
        'total_inv_value' => (float) ($faRaw['total_inv_value'] ?? $faRaw['inv'] ?? 0),
        'total_lp_value' => (float) ($faRaw['total_lp_value'] ?? $faRaw['lp'] ?? 0),
        'total_order_value' => (float) ($faRaw['total_order_value'] ?? $faRaw['ord'] ?? 0),
        'total_minimal_msl' => (float) ($faRaw['total_minimal_msl'] ?? $faRaw['missing'] ?? 0),
        'total_mip_value' => (float) ($faRaw['total_mip_value'] ?? $faRaw['mip'] ?? 0),
        'total_r2s_value' => (float) ($faRaw['total_r2s_value'] ?? $faRaw['r2s'] ?? 0),
        'total_transit_value' => (float) ($faRaw['total_transit_value'] ?? $faRaw['trn'] ?? 0),
        'total_cbm' => (int) ($faRaw['total_cbm'] ?? $faRaw['cbm'] ?? 0),
        'zero_stock_pct' => (int) ($faRaw['zero_stock_pct'] ?? 0),
    ];
    $formatForecastBadgeK = static function ($value): string {
        $n = (float) $value;
        if (! is_finite($n)) {
            return '0';
        }

        return number_format((int) round($n / 1000)).'K';
    };

    $ammRow = BadgeData::forPage('all-marketplace-master');
    $amm = array_merge([
        'channels' => 0,
        'l30_sales' => 0,
        'y_sales' => 0,
        'l30_orders' => 0,
        'gprofit_pct' => 0,
        'g_roi' => 0,
        'ad_spend' => 0,
        'ads_pct' => 0,
        'total_views' => 0,
        'cvr_pct' => null,
        'net_profit' => 0,
        'npft_pct' => 0,
        'n_roi' => 0,
        'clicks' => 0,
        'map' => 0,
        'nmap' => 0,
        'missing_l' => 0,
        'inventory_value_amazon' => 0,
        'inv_at_lp' => 0,
        'tat' => 0,
        'avg_rating' => 0,
        'total_reviews' => 0,
        'seller_avg_rating' => 0,
        'seller_total_reviews' => 0,
    ], $ammRow?->data ?? []);
    $fmtAmmDollar = static fn ($value): string => '$'.number_format((int) round((float) $value));
    $fmtAmmInt = static fn ($value): string => number_format((int) round((float) $value));
    $ammYSalesLabel = ((float) ($amm['y_sales'] ?? 0)) > 0
        ? $fmtAmmDollar($amm['y_sales'])
        : 'NYS';
    $ammCvrLabel = $amm['cvr_pct'] !== null
        ? number_format((float) $amm['cvr_pct'], 2).'%'
        : '-';

    // Listing Catalogue scores (same sources as sidebar / listing pages)
    $lcMissingL = \App\Support\Marketplace\ListingChannelCounts::totalMissingL(true);
    $lcNmap = \App\Support\Badges\AllMarketplaceMasterBadgeCalculator::nmapCountForSidebar();
    $lcVariationsMismatch = \App\Http\Controllers\MarketPlace\VariationsVerifyMasterController::totalMismatchCountForSidebar();
    try {
        \App\Http\Controllers\MarketPlace\ListingCatalogueController::persistTodaySnapshot((int) $lcVariationsMismatch);
    } catch (\Throwable $e) {
        // ignore snapshot failures on dashboard render
    }
    $lcUpdatedAt = now('America/Los_Angeles');

    $amzAdsMissingCount = \App\Http\Controllers\AmazonAdsMissingController::missingTotalCount();
    $adm = \App\Http\Controllers\AdvertisementMaster\AdvertisementMasterController::dashboardBadgeTotals();
    $fmtAdmDollar = static fn ($value): string => '$'.number_format((int) round((float) $value));
    $fmtAdmInt = static fn ($value): string => number_format((int) round((float) $value));

    // Page badge snapshots (KPIs from relevant page toolbars)
    $loadPageBadges = static function (string $calculatorClass, array $defaults) {
        try {
            $page = $calculatorClass::pageName();
            $row = BadgeData::forPage($page);
            if (! $row || empty($row->data)) {
                BadgeData::saveForCalculator($calculatorClass);
                $row = BadgeData::forPage($page);
            }

            return [
                'row' => $row,
                'data' => array_merge($defaults, $row?->data ?? []),
            ];
        } catch (\Throwable $e) {
            return ['row' => null, 'data' => $defaults];
        }
    };

    $vmPack = $loadPageBadges(\App\Support\Badges\VideoMasterBadgeCalculator::class, [
        'products' => 0, 'with_video' => 0, 'missing_video' => 0,
    ]);
    $vmRow = $vmPack['row'];
    $vm = $vmPack['data'];

    $videosPack = $loadPageBadges(\App\Support\Badges\VideosMasterBadgeCalculator::class, [
        'sku_count' => 0, 'missing_po' => 0, 'missing_shop' => 0, 'missing_howto' => 0,
        'missing_setup' => 0, 'missing_ts' => 0, 'missing_bs' => 0, 'missing_pb' => 0,
    ]);
    $videosRow = $videosPack['row'];
    $videos = $videosPack['data'];

    $riPack = $loadPageBadges(\App\Support\Badges\RawImagesBadgeCalculator::class, [
        'sku_count' => 0, 'with_raw_image' => 0, 'missing' => 0,
    ]);
    $riRow = $riPack['row'];
    $ri = $riPack['data'];

    $riBatchPack = $loadPageBadges(\App\Support\Badges\RawImagesBatchCooBadgeCalculator::class, [
        'sku_count' => 0, 'with_raw_image' => 0, 'missing' => 0,
    ]);
    $riBatchRow = $riBatchPack['row'];
    $riBatch = $riBatchPack['data'];

    $riHero2Pack = $loadPageBadges(\App\Support\Badges\RawImagesHero2BadgeCalculator::class, [
        'sku_count' => 0, 'with_raw_image' => 0, 'missing' => 0,
    ]);
    $riHero2Row = $riHero2Pack['row'];
    $riHero2 = $riHero2Pack['data'];

    $vamPack = $loadPageBadges(\App\Support\Badges\VideoAdsMasterBadgeCalculator::class, [
        'required' => 0, 'sku' => 0, 'parent' => 0, 'group' => 0, 'available' => 0, 'missing' => 0,
    ]);
    $vamRow = $vamPack['row'];
    $vam = $vamPack['data'];

    $ccPack = $loadPageBadges(\App\Support\Badges\CustomerCareBadgeCalculator::class, [
        'pending_followups' => 0, 'active_issues' => 0, 'dispatch_issues' => 0,
        'qc_issues' => 0, 'label_issues' => 0, 'l30_issue_rows' => 0,
    ]);
    $cc = $ccPack['data'];

    $ahPack = $loadPageBadges(\App\Support\Badges\AccountHealthBadgeCalculator::class, [
        'cc_red' => 0, 'cc_yellow' => 0, 'cc_green' => 0, 'cc_unrated' => 0,
        'ship_red' => 0, 'ship_yellow' => 0, 'ship_green' => 0, 'ship_unrated' => 0,
    ]);
    $ah = $ahPack['data'];

    $vaPack = $loadPageBadges(\App\Support\Badges\InventoryVerifyBadgeCalculator::class, [
        'verified' => 0, 'unverified' => 0, 'total' => 0,
    ]);
    $va = $vaPack['data'];

    $poPack = $loadPageBadges(\App\Support\Badges\PurchaseContractBadgeCalculator::class, [
        'o_amount' => 0, 'advance' => 0, 'balance' => 0, 'po_count' => 0,
    ]);
    $poBadges = $poPack['data'];

    // Label-prefix → badges_data key for status dots + rolling charts
    $kpi = static fn (string $prefix, string $page, string $field, $value = null, ?string $label = null) => [
        'prefix' => $prefix,
        'key' => \App\Support\Badges\BadgeDataCatalog::makeKey($page, $field),
        'value' => is_numeric($value) ? (float) $value : null,
        'label' => $label,
    ];
    $dashKpiAutoMap = [
        $kpi('SALES:', 'all-marketplace-master', 'l30_sales', $amm['l30_sales'] ?? null, 'Sales'),
        $kpi('CVR:', 'all-marketplace-master', 'cvr_pct', $amm['cvr_pct'] ?? null, 'CVR'),
        $kpi('GPFT:', 'all-marketplace-master', 'gprofit_pct', $amm['gprofit_pct'] ?? null, 'GPFT'),
        $kpi('G ROI:', 'all-marketplace-master', 'g_roi', $amm['g_roi'] ?? null, 'G ROI'),
        $kpi('NPFT%:', 'all-marketplace-master', 'npft_pct', $amm['npft_pct'] ?? null, 'NPFT %'),
        $kpi('NPFT:', 'all-marketplace-master', 'net_profit', $amm['net_profit'] ?? null, 'NPFT $'),
        $kpi('NROI:', 'all-marketplace-master', 'n_roi', $amm['n_roi'] ?? null, 'NROI'),
        $kpi('INV:', 'all-marketplace-master', 'inventory_value_amazon', $amm['inventory_value_amazon'] ?? null, 'Inventory'),
        $kpi('INV@LP:', 'all-marketplace-master', 'inv_at_lp', $amm['inv_at_lp'] ?? null, 'Inv@LP'),
        $kpi('TAT:', 'all-marketplace-master', 'tat', $amm['tat'] ?? null, 'TAT'),
        $kpi('VIDEO MASTER:', 'video-master', 'products', $vm['products'] ?? null, 'Video Master'),
        $kpi('WITH VIDEO:', 'video-master', 'with_video', $vm['with_video'] ?? null, 'With Video'),
        $kpi('MISSING VIDEO:', 'video-master', 'missing_video', $vm['missing_video'] ?? null, 'Missing Video'),
        $kpi('VIDEOS SKUS:', 'videos-master', 'sku_count', $videos['sku_count'] ?? null, 'Videos SKUs'),
        $kpi('MISSING PO:', 'videos-master', 'missing_po', $videos['missing_po'] ?? null, 'Missing PO'),
        $kpi('MISSING SHOP:', 'videos-master', 'missing_shop', $videos['missing_shop'] ?? null, 'Missing Shop'),
        $kpi('MISSING HOWTO:', 'videos-master', 'missing_howto', $videos['missing_howto'] ?? null, 'Missing HowTo'),
        $kpi('MISSING SETUP:', 'videos-master', 'missing_setup', $videos['missing_setup'] ?? null, 'Missing Setup'),
        $kpi('MISSING TS:', 'videos-master', 'missing_ts', $videos['missing_ts'] ?? null, 'Missing TS'),
        $kpi('MISSING BS:', 'videos-master', 'missing_bs', $videos['missing_bs'] ?? null, 'Missing BS'),
        $kpi('MISSING PB:', 'videos-master', 'missing_pb', $videos['missing_pb'] ?? null, 'Missing PB'),
        $kpi('RAW IMAGES SKUS:', 'raw-images', 'sku_count', $ri['sku_count'] ?? null, 'Raw Images SKUs'),
        $kpi('MISSING RAW IMAGES:', 'raw-images', 'missing', $ri['missing'] ?? null, 'Missing Raw Images'),
        $kpi('BATCH+COO SKUS:', 'raw-images-batch-coo', 'sku_count', $riBatch['sku_count'] ?? null, 'Batch +COO SKUs'),
        $kpi('MISSING BATCH+COO:', 'raw-images-batch-coo', 'missing', $riBatch['missing'] ?? null, 'Missing Batch +COO'),
        $kpi('HERO IMAGE 2 SKUS:', 'raw-images-hero-2', 'sku_count', $riHero2['sku_count'] ?? null, 'Hero Image 2 SKUs'),
        $kpi('MISSING HERO IMAGE 2:', 'raw-images-hero-2', 'missing', $riHero2['missing'] ?? null, 'Missing Hero Image 2'),
        $kpi('REQUIRED:', 'video-ads-master', 'required', $vam['required'] ?? null, 'Required'),
        $kpi('SKU:', 'video-ads-master', 'sku', $vam['sku'] ?? null, 'SKU targets'),
        $kpi('PARENT:', 'video-ads-master', 'parent', $vam['parent'] ?? null, 'Parent targets'),
        $kpi('GROUP:', 'video-ads-master', 'group', $vam['group'] ?? null, 'Group targets'),
        $kpi('AVAILABLE:', 'video-ads-master', 'available', $vam['available'] ?? null, 'Available'),
        $kpi('MISSING:', 'video-ads-master', 'missing', $vam['missing'] ?? null, 'Missing links'),
        $kpi('PENDING:', 'customer-care', 'pending_followups', $cc['pending_followups'] ?? null, 'Pending follow-ups'),
        $kpi('ACTIVE ISSUES:', 'customer-care', 'active_issues', $cc['active_issues'] ?? null, 'Active Issues'),
        $kpi('L30 ISSUES:', 'customer-care', 'l30_issue_rows', $cc['l30_issue_rows'] ?? null, 'L30 Issues'),
        $kpi('QC:', 'customer-care', 'qc_issues', $cc['qc_issues'] ?? null, 'QC Issues'),
        $kpi('LABEL:', 'customer-care', 'label_issues', $cc['label_issues'] ?? null, 'Label Issues'),
        $kpi('DISPATCH:', 'customer-care', 'dispatch_issues', $cc['dispatch_issues'] ?? null, 'Dispatch Issues'),
        $kpi('SHIP RED:', 'account-health', 'ship_red', $ah['ship_red'] ?? null, 'Shipping Red'),
        $kpi('SHIP YELLOW:', 'account-health', 'ship_yellow', $ah['ship_yellow'] ?? null, 'Shipping Yellow'),
        $kpi('SHIP GREEN:', 'account-health', 'ship_green', $ah['ship_green'] ?? null, 'Shipping Green'),
        $kpi('SHIP UNRATED:', 'account-health', 'ship_unrated', $ah['ship_unrated'] ?? null, 'Shipping Unrated'),
        $kpi('CC RED:', 'account-health', 'cc_red', $ah['cc_red'] ?? null, 'CC Health Red'),
        $kpi('CC YELLOW:', 'account-health', 'cc_yellow', $ah['cc_yellow'] ?? null, 'CC Health Yellow'),
        $kpi('CC GREEN:', 'account-health', 'cc_green', $ah['cc_green'] ?? null, 'CC Health Green'),
        $kpi('CC UNRATED:', 'account-health', 'cc_unrated', $ah['cc_unrated'] ?? null, 'CC Health Unrated'),
        $kpi('ORD:', 'forecast-analysis', 'total_order_value', $fa['total_order_value'] ?? null, 'Order value'),
        $kpi('FA MISSING:', 'forecast-analysis', 'total_minimal_msl', $fa['total_minimal_msl'] ?? null, 'Forecast Missing'),
        $kpi('MIP:', 'forecast-analysis', 'total_mip_value', $fa['total_mip_value'] ?? null, 'MIP'),
        $kpi('ON SEA:', 'on-sea-transit', 'on_sea', $ost['on_sea'] ?? null, 'On Sea'),
        $kpi('DUE:', 'on-sea-transit', 'due', $ost['due'] ?? null, 'Due'),
        $kpi('PRE-LOAD:', 'on-sea-transit', 'pre_load', $ost['pre_load'] ?? null, 'Pre-Load'),
        $kpi('LANDED:', 'on-sea-transit', 'landed', $ost['landed'] ?? null, 'Landed'),
        $kpi('TRANSIT:', 'on-sea-transit', 'transit', $ost['transit'] ?? null, 'Transit'),
        $kpi('O AMT:', 'purchase-contract', 'o_amount', $poBadges['o_amount'] ?? null, 'O Amount'),
        $kpi('ADVANCE:', 'purchase-contract', 'advance', $poBadges['advance'] ?? null, 'Advance'),
        $kpi('BALANCE:', 'purchase-contract', 'balance', $poBadges['balance'] ?? null, 'Balance'),
        $kpi('VERIFIED:', 'verify-adjust', 'verified', $va['verified'] ?? null, 'Verified'),
        $kpi('UNVERIFIED:', 'verify-adjust', 'unverified', $va['unverified'] ?? null, 'Unverified'),
        $kpi('ACTIVE:', 'advertisement', 'active', $adm['active'] ?? null, 'ACTIVE'),
        $kpi('SPEND:', 'advertisement', 'spend', $adm['spend'] ?? null, 'SPEND'),
        $kpi('CLICKS:', 'advertisement', 'clicks', $adm['clicks'] ?? null, 'CLICKS'),
        $kpi('SOLD:', 'advertisement', 'sold', $adm['sold'] ?? null, 'SOLD'),
        $kpi('ADS SALES:', 'advertisement', 'sales', $adm['sales'] ?? null, 'ADS SALES'),
        $kpi('ACOS:', 'advertisement', 'acos', $adm['acos'] ?? null, 'ACOS'),
        $kpi('TCOS:', 'advertisement', 'tcos', $adm['tcos'] ?? null, 'TCOS'),
        $kpi('TOTAL SALES:', 'advertisement', 'ssales', $adm['ssales'] ?? null, 'TOTAL SALES'),
        $kpi('ADS MISSING:', 'advertisement', 'ads_missing', $amzAdsMissingCount ?? null, 'Ads Missing'),
        $kpi('CHANNELS:', 'all-marketplace-master', 'channels', $amm['channels'] ?? null, 'Channels'),
        $kpi('Y SALES:', 'all-marketplace-master', 'y_sales', $amm['y_sales'] ?? null, 'Y Sales'),
        $kpi('ORDERS:', 'all-marketplace-master', 'l30_orders', $amm['l30_orders'] ?? null, 'Orders'),
        $kpi('TACOS:', 'all-marketplace-master', 'ads_pct', $amm['ads_pct'] ?? null, 'TACOS'),
        $kpi('VIEWS:', 'all-marketplace-master', 'total_views', $amm['total_views'] ?? null, 'Views'),
        $kpi('MAP:', 'all-marketplace-master', 'map', $amm['map'] ?? null, 'Map'),
        $kpi('N MAP:', 'all-marketplace-master', 'nmap', $amm['nmap'] ?? null, 'N Map'),
        $kpi('MISSING L:', 'all-marketplace-master', 'missing_l', $amm['missing_l'] ?? null, 'Missing L'),
    ];

    // Seed today's history from live dashboard values (so dots/charts work even before cron)
    try {
        $historyBuckets = [];
        foreach ($dashKpiAutoMap as $row) {
            if ($row['value'] === null) {
                continue;
            }
            $parsed = \App\Support\Badges\BadgeDataCatalog::parseKey($row['key']);
            if (! $parsed) {
                continue;
            }
            $historyBuckets[$parsed['page']][$parsed['field']] = $row['value'];
        }
        foreach ($historyBuckets as $page => $fields) {
            \App\Models\BadgeDataHistory::recordPage($page, $fields);
        }
    } catch (\Throwable $e) {
        // ignore history seed failures
    }
@endphp

@include('partials.dashboard-card-playback')
@include('partials.dashboard-card-actions')

<div class="dashboard-cards-grid">
<!-- All Marketplace Master — badges_data (page_name: all-marketplace-master) -->
<div id="all-marketplace-master-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <i class="ri-store-2-line" title="Store"></i>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                All Marketplace Master
            </h6>
            @if ($ammRow?->updated_at)
                <small class="dashboard-badge-panel__updated">Updated {{ $ammRow->updated_at->format('M j, g:i A') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button">Channels: {{ (int) ($amm['channels'] ?? 0) }}</span>
            <span class="badge bg-success text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of Sales column">Sales: {{ $fmtAmmDollar($amm['l30_sales']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #17a2b8; color: white; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Yesterday's sales">Y Sales: {{ $ammYSalesLabel }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of Orders column">Orders: {{ $fmtAmmInt($amm['l30_orders']) }}</span>
            <span class="badge bg-secondary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total ad spend">Spend: {{ $fmtAmmDollar($amm['ad_spend']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #6610f2; color: white; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="TACOS %">TACOS: {{ number_format((float) ($amm['ads_pct'] ?? 0), 1) }}%</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total views">views: {{ $fmtAmmInt($amm['total_views']) }}</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Listing CVR">CVR: {{ $ammCvrLabel }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total clicks">Clicks: {{ $fmtAmmInt($amm['clicks']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #198754; color: #fff; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of Map column">Map: {{ $fmtAmmInt($amm['map']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #a71d2a; color: #fff; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of N Map column">N Map: {{ $fmtAmmInt($amm['nmap']) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Missing listings">Missing L: {{ $fmtAmmInt($amm['missing_l']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Weighted avg rating">Reviews: {{ number_format((float) ($amm['avg_rating'] ?? 0), 1) }} ★ | {{ $fmtAmmInt($amm['total_reviews']) }}</span>
            <span class="badge bg-dark text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Seller reviews">Seller review: {{ number_format((float) ($amm['seller_avg_rating'] ?? 0), 1) }} ★ | {{ $fmtAmmInt($amm['seller_total_reviews']) }}</span>
        </div>
    </div>
</div>

<!-- Advertisement — all /advertisement-master header badges -->
<div id="advertisement-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fecaca, #fff1f2); padding: 0; overflow: hidden;">
        <a href="{{ route('advertisement.master') }}" title="Open Advertisement Master">
            <img
                src="{{ asset('assets/images/advertising-wordcloud.png') }}"
                alt="Advertising"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Advertisement
            </h6>
            @if (! empty($adm['updated_at']))
                <small class="dashboard-badge-panel__updated">Updated {{ $adm['updated_at']->format('M j, g:i A') }}</small>
            @elseif (! empty($adm['snapshot_date']))
                <small class="dashboard-badge-panel__updated">Snapshot {{ \Carbon\Carbon::parse($adm['snapshot_date'])->format('M j') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            <span class="badge fs-6 p-2" style="background-color:#059669;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Active campaigns">ACTIVE: {{ $fmtAdmInt($adm['active']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#ef4444;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Ad spend">SPEND: {{ $fmtAdmDollar($adm['spend']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#4c7ed8;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Clicks">CLICKS: {{ $fmtAdmInt($adm['clicks']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#f59e0b;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Sold">SOLD: {{ $fmtAdmInt($adm['sold']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Ads sales">ADS SALES: {{ $fmtAdmDollar($adm['sales']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#db2777;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="CVR = Sold / Clicks">CVR: {{ number_format((float) $adm['cvr'], 1) }}%</span>
            <span class="badge fs-6 p-2" style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="ACOS = Spend / Ads Sales">ACOS: {{ (int) $adm['acos'] }}%</span>
            <span class="badge fs-6 p-2" style="background-color:#7c3aed;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="TCOS = Spend / Total Sales">TCOS: {{ (int) $adm['tcos'] }}%</span>
            <span class="badge fs-6 p-2" style="background-color:#0d9488;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('advertisement.master') }}'" role="button" title="Combined store L30 sales">TOTAL SALES: {{ $fmtAdmDollar($adm['ssales']) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#a71d2a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('amazon.ads.missing') }}'" role="button" title="Ads Missing Amz">Ads Missing: {{ number_format((int) $amzAdsMissingCount) }}</span>
        </div>
    </div>
</div>

<!-- Video — badges_data KPIs from Video Master / Videos / Video Request & Check -->
<div id="video-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #bae6fd, #eff6ff); padding: 0; overflow: hidden;">
        <a href="{{ route('video.master') }}" title="Open Video Master">
            <img
                src="{{ asset('assets/images/video-wordcloud.png') }}"
                alt="Video"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Video
            </h6>
            @php
                $videoUpdated = collect([$vmRow?->updated_at, $videosRow?->updated_at, $vamRow?->updated_at])->filter()->sortDesc()->first();
            @endphp
            @if ($videoUpdated)
                <small class="dashboard-badge-panel__updated">Updated {{ $videoUpdated->format('M j, g:i A') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            {{-- Video Master --}}
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.master') }}'" role="button" title="Video Master — products">Video Master: {{ number_format((int) ($vm['products'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#0284c7;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.master') }}'" role="button" title="Video Master — SKUs with a video">With Video: {{ number_format((int) ($vm['with_video'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.master') }}'" role="button" title="Video Master — SKUs missing video">Missing Video: {{ number_format((int) ($vm['missing_video'] ?? 0)) }}</span>

            {{-- Videos Master (all missing-column KPIs) --}}
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — SKU count">Videos SKUs: {{ number_format((int) ($videos['sku_count'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#7c3aed;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Product Overview">Missing PO: {{ number_format((int) ($videos['missing_po'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#6366f1;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Shop / Unboxing">Missing Shop: {{ number_format((int) ($videos['missing_shop'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#db2777;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing HowTo">Missing HowTo: {{ number_format((int) ($videos['missing_howto'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Setup">Missing Setup: {{ number_format((int) ($videos['missing_setup'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#d97706;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Troubleshooting">Missing TS: {{ number_format((int) ($videos['missing_ts'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#b45309;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Brand Story">Missing BS: {{ number_format((int) ($videos['missing_bs'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#9f1239;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('videos.master') }}'" role="button" title="Videos — missing Product Benefits">Missing PB: {{ number_format((int) ($videos['missing_pb'] ?? 0)) }}</span>

            {{-- Video Request & Check (all toolbar KPIs) --}}
            <span class="badge fs-6 p-2" style="background-color:#0ea5e9;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — required rows">Required: {{ number_format((int) ($vam['required'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#2563eb;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — SKU targets">SKU: {{ number_format((int) ($vam['sku'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#4f46e5;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — Parent targets">Parent: {{ number_format((int) ($vam['parent'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#7c3aed;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — Group targets">Group: {{ number_format((int) ($vam['group'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#059669;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — links available">Available: {{ number_format((int) ($vam['available'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#a71d2a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('video.ads.master') }}'" role="button" title="Video Request & Check — missing links">Missing: {{ number_format((int) ($vam['missing'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- Raw Images — missing original files -->
<div id="raw-images-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fde68a, #fffbeb); display:flex;align-items:center;justify-content:center;">
        <a href="{{ route('raw.images') }}" title="Open Raw Images" style="color:#b45309;font-size:28px;">
            <i class="fas fa-camera"></i>
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">Raw Images</h6>
            @if ($riRow?->updated_at)
                <small class="dashboard-badge-panel__updated">Updated {{ $riRow->updated_at->format('M j, g:i A') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('raw.images') }}'" role="button" title="Raw Images — SKU count">SKUs: {{ number_format((int) ($ri['sku_count'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#059669;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('raw.images') }}'" role="button" title="SKUs with a raw image">With Raw Image: {{ number_format((int) ($ri['with_raw_image'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('raw.images') }}'" role="button" title="SKUs missing a raw image">Missing Raw Images: {{ number_format((int) ($ri['missing'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#b45309;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('raw.images.batch.coo') }}'" role="button" title="Raw Images (Batch +COO) — SKU count">Batch +COO SKUs: {{ number_format((int) ($riBatch['sku_count'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('raw.images.batch.coo') }}'" role="button" title="SKUs missing Batch +COO raw images">Missing Batch +COO: {{ number_format((int) ($riBatch['missing'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#0f766e;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ \Illuminate\Support\Facades\Route::has('raw.images.hero.2') ? route('raw.images.hero.2') : url('/raw-images-hero-2') }}'" role="button" title="Hero Image 2 — SKU count">Hero Image 2 SKUs: {{ number_format((int) ($riHero2['sku_count'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ \Illuminate\Support\Facades\Route::has('raw.images.hero.2') ? route('raw.images.hero.2') : url('/raw-images-hero-2') }}'" role="button" title="SKUs missing Hero Image 2">Missing Hero Image 2: {{ number_format((int) ($riHero2['missing'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- Pricing — user-provided PRICE image -->
<div id="pricing-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fefce8, #fefce8); padding: 0; overflow: hidden;">
        <a href="{{ route('pricing.master.cvr') }}" title="Open Pricing Master CVR">
            <img
                src="{{ asset('assets/images/pricing-dashboard-icon.png') }}"
                alt="Pricing"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Pricing
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#eab308;color:#212529;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('pricing.master.cvr') }}'"
                role="button"
                title="Open Pricing Master CVR"
            >Pricing Master CVR</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('price.increase') }}'"
                role="button"
                title="Open Price Increase"
            >Price Increase</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('pricing.analysis') }}'"
                role="button"
                title="Open Pricing Analysis"
            >Pricing Analysis</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#f59e0b;color:#212529;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('pricing.container') }}'"
                role="button"
                title="Open Pricing Container"
            >Pricing Container</span>
            <span
                class="badge bg-success text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="L30 Sales"
            >Sales: {{ $fmtAmmDollar($amm['l30_sales']) }}</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Listing CVR"
            >CVR: {{ $ammCvrLabel }}</span>
            <span
                class="badge bg-warning text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Blended Gprofit%"
            >GPFT: {{ number_format((float) ($amm['gprofit_pct'] ?? 0), 1) }}%</span>
            <span
                class="badge bg-danger text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="G ROI"
            >G ROI: {{ number_format((int) round((float) ($amm['g_roi'] ?? 0))) }}%</span>
            <span
                class="badge bg-warning text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Net profit $"
            >NPFT: {{ $fmtAmmDollar($amm['net_profit']) }}</span>
            <span
                class="badge bg-warning text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Net profit %"
            >NPFT%: {{ number_format((float) ($amm['npft_pct'] ?? 0), 1) }}%</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="N ROI"
            >NROI: {{ number_format((int) round((float) ($amm['n_roi'] ?? 0))) }}%</span>
        </div>
    </div>
</div>

<!-- Customer Care — user-provided headset image -->
<div id="customer-care-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #ddd6fe, #f5f3ff); padding: 0; overflow: hidden;">
        <a href="{{ route('customer.care') }}" title="Open Customer Care Overview">
            <img
                src="{{ asset('assets/images/customer-care-dashboard-icon.png') }}"
                alt="Customer Care"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Customer Care
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#7c3aed;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care') }}'"
                role="button"
                title="Open Customer Care Overview"
            >Overview</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.cc.messages.returns') }}'"
                role="button"
                title="Open CC Message"
            >CC Message</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.refunds') }}'"
                role="button"
                title="Open Refunds"
            >Refunds</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#0d9488;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.followups') }}'"
                role="button"
                title="Open Follow Up CC"
            >Follow Up CC</span>
            <span
                class="badge bg-danger text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.issues') }}'"
                role="button"
                title="Open All Issues"
            >All Issues</span>
            <span class="badge fs-6 p-2" style="background-color:#0d9488;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.followups') }}'" role="button" title="Follow Up CC — pending">Pending: {{ number_format((int) ($cc['pending_followups'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.dispatch.issues') }}'" role="button" title="All Issues — active">Active Issues: {{ number_format((int) ($cc['active_issues'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.dispatch.issues') }}'" role="button" title="All Issues — L30 rows">L30 Issues: {{ number_format((int) ($cc['l30_issue_rows'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- Fulfillment — user-provided label printer image -->
<div id="fulfillment-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #e5e7eb, #f9fafb); padding: 0; overflow: hidden;">
        <a href="{{ route('fullfillment.rate') }}" title="Open Fulfillment Rate">
            <img
                src="{{ asset('assets/images/fulfillment-dashboard-icon.png') }}"
                alt="Fulfillment"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Fulfillment
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#374151;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('fullfillment.rate') }}'"
                role="button"
                title="Open Fulfillment Rate"
            >Fulfillment Rate</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('shipping.master') }}'"
                role="button"
                title="Open Shipping Master"
            >Shipping Master</span>
            <span
                class="badge bg-warning text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.qc.and.packing') }}'"
                role="button"
                title="Open QC PKG issues"
            >QC PKG Issues</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.label.issues') }}'"
                role="button"
                title="Open Label Issues"
            >Label Issues</span>
            <span
                class="badge bg-danger text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.issues.only') }}'"
                role="button"
                title="Open Dispatch Issues"
            >Dispatch Issues</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.qc.and.packing') }}'" role="button" title="QC PKG active issues">QC: {{ number_format((int) ($cc['qc_issues'] ?? 0)) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.label.issues') }}'" role="button" title="Label Issues active">Label: {{ number_format((int) ($cc['label_issues'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.dispatch.issues.only') }}'" role="button" title="Dispatch Issues active">Dispatch: {{ number_format((int) ($cc['dispatch_issues'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#dc2626;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Red">Ship Red: {{ number_format((int) ($ah['ship_red'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#eab308;color:#212529;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Yellow">Ship Yellow: {{ number_format((int) ($ah['ship_yellow'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Green">Ship Green: {{ number_format((int) ($ah['ship_green'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- Dispatch — user-provided DISPATCH letters image -->
<div id="dispatch-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #bfdbfe, #eff6ff); padding: 0; overflow: hidden;">
        <a href="{{ route('customer.care.dispatch.issues.only') }}" title="Open Dispatch Issues">
            <img
                src="{{ asset('assets/images/dispatch-dashboard-icon.png') }}"
                alt="Dispatch"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Dispatch
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge bg-danger text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.issues.only') }}'"
                role="button"
                title="Open Dispatch Issues"
            >Dispatch Issues</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.carrier.and.claim') }}'"
                role="button"
                title="Open Carrier Claims"
            >Carrier Claims</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.carrier.issue') }}'"
                role="button"
                title="Open Carrier Scan Issues"
            >Carrier Scan Issues</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.dispatch.chargeback.issues') }}'"
                role="button"
                title="Open Chargeback Issues"
            >Chargeback Issues</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#2563eb;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ url('fba-dispatch-page') }}'"
                role="button"
                title="Open FBA Dispatch"
            >FBA Dispatch</span>
        </div>
    </div>
</div>

<!-- Purchases — user-provided China shipping image -->
<div id="purchases-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fecaca, #fff1f2); padding: 0; overflow: hidden;">
        <a href="{{ route('purchase.index') }}" title="Open Purchase">
            <img
                src="{{ asset('assets/images/purchases-dashboard-icon.png') }}"
                alt="Purchases"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Purchases
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#dc2626;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('purchase.index') }}'"
                role="button"
                title="Open Purchase"
            >Purchase</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('to.order.analysis') }}'"
                role="button"
                title="Open Order"
            >Order</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('forecast.analysis') }}'"
                role="button"
                title="Open Forecast Analysis"
            >Forecast Analysis</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#0d9488;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('on.sea.transit') }}'"
                role="button"
                title="Open On Sea Transit"
            >On Sea Transit</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('list-all-purchase-orders') }}'"
                role="button"
                title="Open Purchase Contract"
            >Purchase Contract</span>
            <span class="badge bg-success text-dark fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Forecast — Order value">Ord: ${{ $formatForecastBadgeK($fa['total_order_value']) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Forecast — Missing">FA Missing: ${{ $formatForecastBadgeK($fa['total_minimal_msl']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Forecast — MIP">MIP: ${{ $formatForecastBadgeK($fa['total_mip_value']) }}</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button" title="On Sea Transit — On Sea">On Sea: {{ number_format((int) ($ost['on_sea'] ?? 0)) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button" title="On Sea Transit — Due">Due: ${{ number_format((float) ($ost['due'] ?? 0), 0) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('list-all-purchase-orders') }}'" role="button" title="Purchase Contract — O Amount">O Amt: {{ $fmtAmmDollar($poBadges['o_amount'] ?? 0) }}</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('list-all-purchase-orders') }}'" role="button" title="Purchase Contract — Advance">Advance: {{ $fmtAmmDollar($poBadges['advance'] ?? 0) }}</span>
            <span class="badge bg-success text-white fs-6 p-2" style="font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('list-all-purchase-orders') }}'" role="button" title="Purchase Contract — Balance">Balance: {{ $fmtAmmDollar($poBadges['balance'] ?? 0) }}</span>
        </div>
    </div>
</div>

<!-- Inventory — user-provided INVENTORY image -->
<div id="inventory-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fed7aa, #fff7ed); padding: 0; overflow: hidden;">
        <a href="{{ route('view-inventory-data') }}" title="Open Inventory Main">
            <img
                src="{{ asset('assets/images/inventory-dashboard-icon.png') }}"
                alt="Inventory"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Inventory
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#ea580c;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('view-inventory-data') }}'"
                role="button"
                title="Open Inventory Main"
            >Inventory Main</span>
            <span
                class="badge bg-success text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('incoming.view') }}'"
                role="button"
                title="Open Incoming"
            >Incoming</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('outgoing.view') }}'"
                role="button"
                title="Open Outgoing"
            >Outgoing</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('stock.balance.view') }}'"
                role="button"
                title="Open Stock Balance / TRF"
            >Stock Balance</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#7c3aed;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('verify-adjust') }}'"
                role="button"
                title="Open Verification & Adjustment"
            >Verify / Adjust</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Inventory × Amz Price"
            >inv: {{ $fmtAmmDollar($amm['inventory_value_amazon']) }}</span>
            <span
                class="badge bg-warning text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="Shopify inv × LP"
            >Inv@LP: {{ $fmtAmmDollar($amm['inv_at_lp']) }}</span>
            <span
                class="badge bg-secondary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('all.marketplace.master') }}'"
                role="button"
                title="inv ÷ Sales"
            >TAT: {{ ((float) ($amm['tat'] ?? 0)) > 0 ? number_format((float) $amm['tat'], 2) : '0' }}</span>
            <span class="badge fs-6 p-2" style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('verify-adjust') }}'" role="button" title="Verify / Adjust — verified">Verified: {{ number_format((int) ($va['verified'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#eab308;color:#212529;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('verify-adjust') }}'" role="button" title="Verify / Adjust — unverified">Unverified: {{ number_format((int) ($va['unverified'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- Account Health — user-provided health-e commerce image -->
<div id="account-health-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #a5f3fc, #ecfeff); padding: 0; overflow: hidden;">
        <a href="{{ route('account.health.master.channel.dashboard') }}" title="Open Dashboard Account Health" target="_blank" rel="noopener">
            <img
                src="{{ asset('assets/images/account-health-dashboard-icon.png') }}"
                alt="Account Health"
                class="dashboard-badge-panel__icon-img dashboard-badge-panel__icon-img--tile"
                loading="lazy"
            >
        </a>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Account Health
            </h6>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge fs-6 p-2"
                style="background-color:#0d9488;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.open('{{ route('account.health.master.channel.dashboard') }}', '_blank')"
                role="button"
                title="Open Dashboard Account Health"
            >Dashboard Account Health</span>
            <span
                class="badge bg-primary text-white fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('account.health.master.tabulator') }}'"
                role="button"
                title="Open CC Message Health"
            >CC Message Health</span>
            <span
                class="badge bg-info text-dark fs-6 p-2"
                style="font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('customer.care.health.tabulator') }}'"
                role="button"
                title="Open Customer Care Health"
            >Customer Care Health</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#2563eb;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'"
                role="button"
                title="Open Shipping Health"
            >Shipping Health</span>
            <span
                class="badge fs-6 p-2"
                style="background-color:#374151;color:#fff;font-weight:bold;cursor:pointer;"
                onclick="window.location.href='{{ route('fullfillment.rate') }}'"
                role="button"
                title="Open Fulfillment Rate"
            >Fulfillment Rate</span>
            <span class="badge fs-6 p-2" style="background-color:#dc2626;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.health.tabulator') }}'" role="button" title="Customer Care Health — Red">CC Red: {{ number_format((int) ($ah['cc_red'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#eab308;color:#212529;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.health.tabulator') }}'" role="button" title="Customer Care Health — Yellow">CC Yellow: {{ number_format((int) ($ah['cc_yellow'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.health.tabulator') }}'" role="button" title="Customer Care Health — Green">CC Green: {{ number_format((int) ($ah['cc_green'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#6b7280;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('customer.care.health.tabulator') }}'" role="button" title="Customer Care Health — Unrated">CC Unrated: {{ number_format((int) ($ah['cc_unrated'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#dc2626;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Red">Ship Red: {{ number_format((int) ($ah['ship_red'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#eab308;color:#212529;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Yellow">Ship Yellow: {{ number_format((int) ($ah['ship_yellow'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#16a34a;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Green">Ship Green: {{ number_format((int) ($ah['ship_green'] ?? 0)) }}</span>
            <span class="badge fs-6 p-2" style="background-color:#6b7280;color:#fff;font-weight:bold;cursor:pointer;" onclick="window.location.href='{{ route('shipping.health.overview.tabulator') }}'" role="button" title="Shipping Health — Unrated">Ship Unrated: {{ number_format((int) ($ah['ship_unrated'] ?? 0)) }}</span>
        </div>
    </div>
</div>

<!-- On Sea Transit — badges_data (page_name: on-sea-transit) -->
<div id="on-sea-transit-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <span class="dashboard-badge-panel__icon-emoji">🚢</span>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                On Sea Transit
            </h6>
            @if ($ostRow?->updated_at)
                <small class="dashboard-badge-panel__updated">Updated {{ $ostRow->updated_at->format('M j, g:i A') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">Pre-Load: {{ (int) ($ost['pre_load'] ?? 0) }}</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">On Sea: {{ (int) ($ost['on_sea'] ?? 0) }}</span>
            <span class="badge text-white fs-6 p-2" style="font-weight: bold; background-color: #654321;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">Landed: {{ (int) ($ost['landed'] ?? 0) }}</span>
            <span class="badge bg-info text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">transit: {{ (int) ($ost['transit'] ?? 0) }}</span>
            <span class="badge bg-success text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">${{ number_format((float) ($ost['total_value'] ?? 0), 0) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">Due: ${{ number_format((float) ($ost['due'] ?? 0), 0) }}</span>
            <span class="badge text-white fs-6 p-2" style="font-weight: bold; background-color: #6366f1;" onclick="window.location.href='{{ route('on.sea.transit') }}'" role="button">Value: ${{ number_format((float) ($ost['value'] ?? 0), 0) }}</span>
        </div>
    </div>
</div>

<!-- Forecast Analysis — badges_data (page_name: forecast-analysis) -->
<div id="forecast-analysis-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <span class="dashboard-badge-panel__icon-emoji">📊</span>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Forecast Analysis
            </h6>
            @if ($forecastBadgeRow?->updated_at)
                <small class="dashboard-badge-panel__updated">Updated {{ $forecastBadgeRow->updated_at->format('M j, g:i A') }}</small>
            @endif
        </div>
        <div class="dashboard-badge-panel__badges">
            <span class="badge bg-success text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="MSL × LP">MSL_LP: ${{ $formatForecastBadgeK($fa['total_msl_c']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="MSL × AMZ price ÷ 4">MSL_SP: ${{ $formatForecastBadgeK($fa['total_msl_sp_amz']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="INV Value">INV: ${{ $formatForecastBadgeK($fa['total_inv_value']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="LP Value">LP: ${{ $formatForecastBadgeK($fa['total_lp_value']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="2 Ord × CP">Ord: ${{ $formatForecastBadgeK($fa['total_order_value']) }}</span>
            <span class="badge bg-secondary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Missing forecast.analysis">Missing: ${{ $formatForecastBadgeK($fa['total_minimal_msl']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="MIP Value">MIP: ${{ $formatForecastBadgeK($fa['total_mip_value']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="R2S Value">R2S: ${{ $formatForecastBadgeK($fa['total_r2s_value']) }}</span>
            <span class="badge bg-secondary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Transit Value">Trn: ${{ $formatForecastBadgeK($fa['total_transit_value']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Total CBM">CBM: {{ number_format($fa['total_cbm']) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('forecast.analysis') }}'" role="button" title="Child SKUs with INV ≤ 0">{{ $fa['zero_stock_pct'] }}%</span>
        </div>
    </div>
</div>

<!-- Listing Catalogue — Missing L / N Map / Variations Verify scores + rolling history -->
<div id="listing-catalogue-card" class="p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fef3c7, #fffbeb);">
        <img src="{{ asset('assets/images/listing-catalogue-icon.png') }}" alt="Listing Catalogue" class="dashboard-badge-panel__icon-img">
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Listing Catalogue
            </h6>
            <small class="dashboard-badge-panel__updated">Updated {{ $lcUpdatedAt->format('M j, g:i A') }} (California)</small>
        </div>
        <div class="dashboard-badge-panel__badges">
            <span
                class="badge lc-score-badge"
                style="background-color:#a71d2a;color:#fff;"
                role="button"
                tabindex="0"
                data-lc-metric="missing_l"
                data-lc-label="Missing Listing"
                data-lc-value="{{ (int) $lcMissingL }}"
                data-lc-page="{{ route('missing.listing') }}"
                title="Click for rolling history — Missing L from listing pages"
            >Missing L: {{ number_format((int) $lcMissingL) }}</span>

            <span
                class="badge lc-score-badge"
                style="background-color:#0d6efd;color:#fff;"
                role="button"
                tabindex="0"
                data-lc-metric="nmap"
                data-lc-label="Missing Mapping"
                data-lc-value="{{ (int) $lcNmap }}"
                data-lc-page="{{ route('map.issues') }}"
                title="Click for rolling history — N Map from Missing Mapping"
            >N Map: {{ number_format((int) $lcNmap) }}</span>

            <span
                class="badge lc-score-badge"
                style="background-color:#f59e0b;color:#212529;"
                role="button"
                tabindex="0"
                data-lc-metric="variations_mismatch"
                data-lc-label="Variations Verify"
                data-lc-value="{{ (int) $lcVariationsMismatch }}"
                data-lc-page="{{ route('variations.verify.masters') }}"
                title="Click for rolling history — Mismatch from Variations Verify Masters"
            >Mismatch: {{ number_format((int) $lcVariationsMismatch) }}</span>
        </div>
    </div>
</div>

</div>

{{-- Listing Catalogue rolling history modal --}}
<div class="modal fade p-0" id="lcMetricChartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow: hidden;">
            <div class="modal-header bg-info text-white py-1 px-3">
                <h6 class="modal-title mb-0" style="font-size: 13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="lcChartModalTitle">Listing Catalogue - Rolling window</span>
                    <a id="lcChartPageLink" href="#" target="_blank" rel="noopener" class="ms-2 text-white small" title="Open page">
                        <i class="mdi mdi-open-in-new"></i>
                    </a>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="lcChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                        <option value="7">7 Days</option>
                        <option value="30">30 Days</option>
                        <option value="32" selected>32 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-2">
                <div id="lcChartContainer" style="height: 22vh; display: flex; align-items: stretch;">
                    <div style="flex: 1; min-width: 0; position: relative;">
                        <canvas id="lcMetricChart"></canvas>
                    </div>
                    <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #dc3545;">Highest</div>
                            <div id="lcChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                        </div>
                        <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6c757d;">Median</div>
                            <div id="lcChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #198754;">Lowest</div>
                            <div id="lcChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                        </div>
                    </div>
                </div>
                <div id="lcChartLoading" class="text-center py-3" style="display: none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                </div>
                <div id="lcChartNoData" class="text-center py-3" style="display: none;">
                    <p class="text-muted small mb-0">Daily history is not available yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.dashboard-customize')
@include('partials.dashboard-kpi-dots')
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    let lcChartInstance = null;
    let lcChartAjax = null;
    let lcMetric = 'missing_l';
    let lcLabel = 'Missing Listing';
    let lcBadgeValue = null;
    let lcPageUrl = '';
    let lcDays = 32;

    function lcFmt(v) {
        return Math.round(Number(v || 0)).toLocaleString('en-US');
    }

    function lcRangeLabel(days) {
        return days === 0 ? 'Lifetime' : ('L' + days);
    }

    function showLcChart(metric, label, value, pageUrl) {
        lcMetric = metric;
        lcLabel = label || metric;
        lcBadgeValue = (value !== undefined && value !== null && !isNaN(value)) ? Number(value) : null;
        lcPageUrl = pageUrl || '';
        lcDays = parseInt(document.getElementById('lcChartRangeSelect').value, 10) || 32;

        document.getElementById('lcChartModalTitle').textContent =
            lcLabel + ' (Rolling ' + lcRangeLabel(lcDays) + ', California)';
        const link = document.getElementById('lcChartPageLink');
        if (lcPageUrl) {
            link.href = lcPageUrl;
            link.style.display = '';
        } else {
            link.style.display = 'none';
        }

        const modalEl = document.getElementById('lcMetricChartModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        loadLcChart();
    }

    function loadLcChart() {
        if (lcChartAjax) lcChartAjax.abort();
        document.getElementById('lcChartNoData').style.display = 'none';
        document.getElementById('lcChartContainer').style.display = 'none';
        document.getElementById('lcChartLoading').style.display = 'block';

        const params = new URLSearchParams({
            metric: lcMetric,
            days: String(lcDays),
        });
        if (lcBadgeValue !== null) params.set('badge_value', String(lcBadgeValue));

        lcChartAjax = fetch("{{ route('listing.catalogue.chart.data') }}?" + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(function (r) { return r.json(); }).then(function (response) {
            lcChartAjax = null;
            document.getElementById('lcChartLoading').style.display = 'none';
            if (response && response.success !== false && response.data && response.data.length > 0) {
                document.getElementById('lcChartContainer').style.display = 'flex';
                renderLcChart(response.data);
            } else {
                document.getElementById('lcChartNoData').style.display = 'block';
            }
        }).catch(function () {
            lcChartAjax = null;
            document.getElementById('lcChartLoading').style.display = 'none';
            document.getElementById('lcChartNoData').style.display = 'block';
        });
    }

    function renderLcChart(data) {
        const ctx = document.getElementById('lcMetricChart').getContext('2d');
        if (lcChartInstance) lcChartInstance.destroy();

        const labels = data.map(function (d) { return d.date; });
        const values = data.map(function (d) { return Number(d.value || 0); });
        const dataMin = Math.min.apply(null, values);
        const dataMax = Math.max.apply(null, values);
        const sorted = values.slice().sort(function (a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yMin = Math.max(0, dataMin - range * 0.1);
        const yMax = dataMax + range * 0.1;

        const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
        const highestEl = document.getElementById('lcChartHighest');
        const medianEl = document.getElementById('lcChartMedian');
        const lowestEl = document.getElementById('lcChartLowest');
        highestEl.textContent = lcFmt(dataMax);
        highestEl.style.color = dataMax === 0 ? refGreen : refRed;
        medianEl.textContent = lcFmt(median);
        medianEl.style.color = median === 0 ? refGreen : (median > 0 ? refRed : refGray);
        lowestEl.textContent = lcFmt(dataMin);
        lowestEl.style.color = dataMin === 0 ? refGreen : refRed;

        const dotColors = values.map(function (v, i) {
            if (i === 0) return refGray;
            return v > values[i - 1] ? '#28a745' : (v < values[i - 1] ? refRed : refGray);
        });

        lcChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: lcLabel,
                    data: values,
                    backgroundColor: 'rgba(108,117,125,0.08)',
                    borderColor: '#adb5bd',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Value: ' + lcFmt(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: { callback: function (v) { return lcFmt(v); }, font: { size: 9 } }
                    },
                    x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.lc-score-badge').forEach(function (el) {
            el.addEventListener('click', function () {
                showLcChart(
                    el.getAttribute('data-lc-metric'),
                    el.getAttribute('data-lc-label'),
                    el.getAttribute('data-lc-value'),
                    el.getAttribute('data-lc-page')
                );
            });
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    el.click();
                }
            });
        });

        document.getElementById('lcChartRangeSelect').addEventListener('change', function () {
            const days = parseInt(this.value, 10);
            if (days === lcDays) return;
            lcDays = days;
            document.getElementById('lcChartModalTitle').textContent =
                lcLabel + ' (Rolling ' + lcRangeLabel(lcDays) + ', California)';
            loadLcChart();
        });
    });
})();
</script>
@endsection
