@extends('layouts.vertical', ['title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'hideFloatingTaskButton' => true])

@section('css')
<style>
    .dashboard-badge-panel {
        width: fit-content;
        max-width: 100%;
        display: flex;
        align-items: stretch;
        gap: 0.875rem;
    }
    .dashboard-badge-panel__icon {
        flex: 0 0 52px;
        width: 52px;
        min-height: 52px;
        align-self: center;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: linear-gradient(145deg, #dbeafe, #eff6ff);
        font-size: 1.75rem;
        line-height: 1;
    }
    .dashboard-badge-panel__icon-emoji {
        display: block;
        font-style: normal;
        line-height: 1;
    }
    .dashboard-badge-panel__icon .ri-store-2-line {
        font-size: 1.5rem;
        color: #475569;
        line-height: 1;
    }
    .dashboard-badge-panel__body {
        flex: 0 1 auto;
        width: auto;
    }
    .dashboard-badge-panel__badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        width: fit-content;
        max-width: 100%;
    }
    .dashboard-badge-panel__badges .badge {
        white-space: nowrap;
    }
    .dashboard-badge-panel__header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.75rem;
        margin-bottom: 0.5rem;
        width: fit-content;
        max-width: 100%;
    }
    .dashboard-badge-panel__updated {
        font-size: 0.8125rem;
        color: #6b7280;
        white-space: nowrap;
    }
    .dashboard-badge-panel__icon-img {
        width: 48px;
        height: 48px;
        object-fit: contain;
        border-radius: 50%;
        display: block;
    }
    .dashboard-badge-panel__badges .lc-score-badge {
        border-radius: 0.35rem !important;
        font-size: 1.05rem !important;
        padding: 0.65rem 1rem !important;
        font-weight: 700 !important;
        cursor: pointer;
        user-select: none;
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
@endphp

<!-- All Marketplace Master — badges_data (page_name: all-marketplace-master) -->
<div id="all-marketplace-master-card" class="mt-2 mb-3 p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <i class="ri-store-2-line" title="Store"></i>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                All Marketplace Master
                <a href="{{ route('all.marketplace.master') }}" class="ms-2 small text-decoration-none" title="Open All Marketplace Master">
                    <i class="mdi mdi-open-in-new"></i>
                </a>
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
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Blended Gprofit%">GPFT: {{ number_format((float) ($amm['gprofit_pct'] ?? 0), 1) }}%</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="G ROI">G ROI: {{ number_format((int) round((float) ($amm['g_roi'] ?? 0))) }}%</span>
            <span class="badge bg-secondary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total ad spend">Spend: {{ $fmtAmmDollar($amm['ad_spend']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #6610f2; color: white; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="TACOS %">TACOS: {{ number_format((float) ($amm['ads_pct'] ?? 0), 1) }}%</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total views">views: {{ $fmtAmmInt($amm['total_views']) }}</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Listing CVR">CVR: {{ $ammCvrLabel }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Net profit $">NPFT: {{ $fmtAmmDollar($amm['net_profit']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Net profit %">NPFT: {{ number_format((float) ($amm['npft_pct'] ?? 0), 1) }}%</span>
            <span class="badge bg-primary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="N ROI">NROI: {{ number_format((int) round((float) ($amm['n_roi'] ?? 0))) }}%</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Total clicks">Clicks: {{ $fmtAmmInt($amm['clicks']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #198754; color: #fff; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of Map column">Map: {{ $fmtAmmInt($amm['map']) }}</span>
            <span class="badge fs-6 p-2" style="background-color: #a71d2a; color: #fff; font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Sum of N Map column">N Map: {{ $fmtAmmInt($amm['nmap']) }}</span>
            <span class="badge bg-danger text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Missing listings">Missing L: {{ $fmtAmmInt($amm['missing_l']) }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Inventory × Amazon Price">inv: {{ $fmtAmmDollar($amm['inventory_value_amazon']) }}</span>
            <span class="badge bg-warning text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Shopify inv × LP">Inv@LP: {{ $fmtAmmDollar($amm['inv_at_lp']) }}</span>
            <span class="badge bg-secondary text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="inv ÷ Sales">TAT: {{ ((float) ($amm['tat'] ?? 0)) > 0 ? number_format((float) $amm['tat'], 2) : '0' }}</span>
            <span class="badge bg-info text-dark fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Weighted avg rating">Reviews: {{ number_format((float) ($amm['avg_rating'] ?? 0), 1) }} ★ | {{ $fmtAmmInt($amm['total_reviews']) }}</span>
            <span class="badge bg-dark text-white fs-6 p-2" style="font-weight: bold;" onclick="window.location.href='{{ route('all.marketplace.master') }}'" role="button" title="Seller reviews">Seller review: {{ number_format((float) ($amm['seller_avg_rating'] ?? 0), 1) }} ★ | {{ $fmtAmmInt($amm['seller_total_reviews']) }}</span>
        </div>
    </div>
</div>

<!-- On Sea Transit — badges_data (page_name: on-sea-transit) -->
<div id="on-sea-transit-card" class="mt-2 mb-3 p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <span class="dashboard-badge-panel__icon-emoji">🚢</span>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                On Sea Transit
                <a href="{{ route('on.sea.transit') }}" class="ms-2 small text-decoration-none" title="Open On Sea Transit">
                    <i class="mdi mdi-open-in-new"></i>
                </a>
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
<div id="forecast-analysis-card" class="mt-2 mb-3 p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true">
        <span class="dashboard-badge-panel__icon-emoji">📊</span>
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Forecast Analysis
                <a href="{{ route('forecast.analysis') }}" class="ms-2 small text-decoration-none" title="Open Forecast Analysis">
                    <i class="mdi mdi-open-in-new"></i>
                </a>
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
<div id="listing-catalogue-card" class="mt-2 mb-3 p-3 bg-white rounded shadow-sm border dashboard-badge-panel">
    <div class="dashboard-badge-panel__icon" aria-hidden="true" style="background: linear-gradient(145deg, #fef3c7, #fffbeb);">
        <img src="{{ asset('assets/images/listing-catalogue-icon.png') }}" alt="Listing Catalogue" class="dashboard-badge-panel__icon-img">
    </div>
    <div class="dashboard-badge-panel__body">
        <div class="dashboard-badge-panel__header">
            <h6 class="mb-0">
                Listing Catalogue
                <a href="{{ route('missing.listing') }}" class="ms-2 small text-decoration-none" title="Open Missing Listing">
                    <i class="mdi mdi-open-in-new"></i>
                </a>
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
