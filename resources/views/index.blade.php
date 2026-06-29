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
@endsection
