@extends('layouts.vertical', ['title' => 'G-Shopping Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
            position: relative !important;
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 5px;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
            min-height: 120px;
            height: auto;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 120px;
            padding: 10px 5px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
        }


        /* Specific width for AD SOLD L30 column header - prevent overflow */
        .tabulator .tabulator-header .tabulator-col[data-field="ad_sold_L30"] {
            min-width: 130px !important;
            width: 140px !important;
            max-width: 140px !important;
            overflow: hidden !important;
        }

        .tabulator .tabulator-header .tabulator-col[data-field="ad_sold_L30"] .tabulator-col-content {
            overflow: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .tabulator .tabulator-header .tabulator-col[data-field="ad_sold_L30"] .tabulator-col-content .tabulator-col-content-holder,
        .tabulator .tabulator-header .tabulator-col[data-field="ad_sold_L30"] .tabulator-col-title-holder {
            overflow: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        .tabulator .tabulator-cell {
            overflow: visible;
            white-space: nowrap;
        }

        /* Ensure AD SOLD L30 cell has proper width */
        .tabulator .tabulator-cell[data-field="ad_sold_L30"] {
            min-width: 130px !important;
            width: 140px !important;
            overflow: visible !important;
        }

        /* Special handling for rowSelection column - don't rotate checkbox column */
        .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-content .tabulator-col-content-holder,
        .tabulator .tabulator-header .tabulator-col:first-child .tabulator-col-title-holder {
            writing-mode: initial !important;
            text-orientation: initial !important;
            transform: none !important;
        }

        /* Ensure all header cells have consistent height */
        .tabulator .tabulator-header .tabulator-col {
            vertical-align: bottom;
        }

        /* Hide sorting arrows but keep sorting functionality */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter-element {
            display: none !important;
        }

        /* Ensure header is clickable for sorting */
        .tabulator .tabulator-header .tabulator-col {
            cursor: pointer;
            pointer-events: auto;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            pointer-events: auto;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            pointer-events: none;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #budget-under-table .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-dot.green {
            background-color: #28a745;
        }

        .status-dot.red {
            background-color: #dc3545;
        }

        .status-dot.yellow {
            background-color: #ffc107;
        }

        .status-dot.gray {
            background-color: #6c757d;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }

        .utilization-type-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .utilization-type-btn.active {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .utilization-type-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .utilization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        .badge-count-item {
            cursor: pointer;
            transition: all 0.2s;
        }

        .badge-count-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        #campaignChart {
            height: 500px !important;
        }

        #chartContainer {
            max-height: 500px;
        }

        #campaignModalChartContainer {
            max-height: 400px;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'G-Shopping Utilized',
        'sub_title' => 'G-Shopping Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <button id="daterange-btn" class="btn btn-outline-dark">
                            <span>Date range: Select</span> <i class="fa-solid fa-chevron-down ms-1"></i>
                        </button>
                    </div>
                    <!-- Stats Row -->
                    <div class="row text-center mb-4">
                        <!-- Clicks -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Clicks</div>
                                <div class="h3 mb-0 fw-bold text-primary card-clicks">{{ $clicks->sum() }}</div>
                            </div>
                        </div>

                        <!-- Spend -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Spend</div>
                                <div class="h3 mb-0 fw-bold text-success card-spend">
                                    ${{ number_format($spend->sum(), 0) }}
                                </div>
                            </div>
                        </div>

                        <!-- Sales -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Sales</div>
                                <div class="h3 mb-0 fw-bold text-info card-sales">
                                    ${{ number_format($sales->sum(), 0) }}
                                </div>
                            </div>
                        </div>

                        <!-- Orders -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Orders</div>
                                <div class="h3 mb-0 fw-bold text-danger card-orders">{{ $orders->sum() }}</div>
                            </div>
                        </div>

                        <!-- ACOS -->
                        <div class="col-md-2 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">ACOS</div>
                                <div class="h3 mb-0 fw-bold text-warning card-acos">
                                    @php
                                        $totalSpend = $spend->sum();
                                        $totalSales = $sales->sum();
                                        if ($totalSales >= 1) {
                                            $acos = ($totalSpend / $totalSales) * 100;
                                        } elseif ($totalSpend > 0) {
                                            $acos = 100; // Spend but no/negligible sales
                                        } else {
                                            $acos = 0;
                                        }
                                    @endphp
                                    {{ number_format($acos, 0) }}%
                                </div>
                            </div>
                        </div>

                        <!-- CVR -->
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">CVR</div>
                                        <div class="h3 mb-0 fw-bold text-danger card-cvr">
                                            @php
                                                $totalOrders = $orders->sum();
                                                $totalClicks = $clicks->sum();
                                                $cvr = $totalClicks > 0 ? ($totalOrders / $totalClicks) * 100 : 0;
                                            @endphp
                                            {{ number_format($cvr, 2) }}%
                                        </div>
                                    </div>
                                    <button id="toggleChartBtn" class="btn btn-sm btn-info ms-2">
                                        <i id="chartArrow" class="fa-solid fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart (hidden by default) -->
                    <div id="chartContainer" style="display: none;">
                        <canvas id="campaignChart" height="120"></canvas>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body">
                    <!-- Filters and Stats Section -->
                    <div class="card border-0 shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                        <div class="card-body">
                            <!-- Filters Row - Top -->
                            <div class="row g-3 align-items-end mb-3 pb-3 border-bottom">
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>Utilization Type
                                    </label>
                                    <select id="utilization-type-select" class="form-select form-select-md">
                                        <option value="all" selected>All</option>
                                        <option value="over">Over Utilized</option>
                                        <option value="under">Under Utilized</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-toggle-on me-1" style="color: #64748b;"></i>Status
                                    </label>
                                    <select id="status-filter" class="form-select form-select-md">
                                        <option value="">All Status</option>
                                        <option value="ENABLED">Enabled</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                    </label>
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">All Inventory</option>
                                        <option value="INV_GT_0" selected>INV > 0</option>
                                        <option value="INV_0">0 INV</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRL
                                    </label>
                                    <select id="nrl-filter" class="form-select form-select-md">
                                        <option value="">All NRL</option>
                                        <option value="NRL">NRL</option>
                                        <option value="RL">RL</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRA
                                    </label>
                                    <select id="nra-filter" class="form-select form-select-md">
                                        <option value="">All NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.8125rem;">
                                        <i class="fa-solid fa-check-square me-1" style="color: #64748b;"></i>Bulk
                                        Actions
                                    </label>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" id="select-all-rows-btn"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-check-double"></i> All
                                        </button>
                                        <button type="button" id="mark-nra-btn" class="btn btn-sm btn-danger"
                                            disabled>
                                            <i class="fa fa-times-circle"></i> NRA
                                        </button>
                                        <button type="button" id="mark-ra-btn" class="btn btn-sm btn-success"
                                            disabled>
                                            <i class="fa fa-check-circle"></i> RA
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistics Badges Row - Below Filters -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold mb-0"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" id="bulk-enable-campaigns-btn" class="btn btn-sm btn-success d-none" title="Enable selected campaigns">
                                                <i class="fa fa-play-circle me-1"></i> Enable
                                            </button>
                                            <button type="button" id="bulk-pause-campaigns-btn" class="btn btn-sm btn-warning d-none" title="Pause selected campaigns">
                                                <i class="fa fa-pause-circle me-1"></i> Pause
                                            </button>
                                            <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center">
                                                <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                            </a>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap align-items-center">
                                        <div class="badge-count-item"
                                            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">Total SKU: </span>
                                            <span class="fw-bold" id="total-sku-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item total-campaign-card" id="total-campaign-card"
                                            style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">Campaign: </span>
                                            <span class="fw-bold" id="total-campaign-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item missing-campaign-card" id="missing-campaign-card"
                                            style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">Missing: </span>
                                            <span class="fw-bold" id="missing-campaign-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item nra-missing-card" id="nra-missing-card"
                                            style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">NRA MISSING: </span>
                                            <span class="fw-bold" id="nra-missing-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item zero-inv-card" id="zero-inv-card"
                                            style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">Zero INV: </span>
                                            <span class="fw-bold" id="zero-inv-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item nra-card" id="nra-card"
                                            style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">NRA: </span>
                                            <span class="fw-bold" id="nra-count" style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item ra-card" id="ra-card"
                                            style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">RA: </span>
                                            <span class="fw-bold" id="ra-count" style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item utilization-card" data-type="7ub"
                                            style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">7UB: </span>
                                            <span class="fw-bold" id="7ub-count" style="font-size: 0.9rem;">0</span>
                                        </div>
                                        <div class="badge-count-item utilization-card" data-type="7ub-1ub"
                                            style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 6px 12px; border-radius: 6px; color: black; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap;">
                                            <span style="font-size: 0.85rem;">7UB+1UB: </span>
                                            <span class="fw-bold" id="7ub-1ub-count"
                                                style="font-size: 0.9rem;">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Multi-Range Filter Row (single row) -->
                            <div class="row align-items-end g-2 mt-3 pt-3 border-top">
                                <div class="col">
                                    <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">1UB
                                        (%)</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" id="1ub-min" class="form-control form-control-sm"
                                            placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                        <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                        <input type="number" id="1ub-max" class="form-control form-control-sm"
                                            placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                    </div>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">7UB
                                        (%)</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" id="7ub-min" class="form-control form-control-sm"
                                            placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                        <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                        <input type="number" id="7ub-max" class="form-control form-control-sm"
                                            placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                    </div>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.75rem;">SBID ($)</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" id="sbid-min" class="form-control form-control-sm"
                                            placeholder="Min" step="0.01" style="font-size: 0.8rem;">
                                        <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                        <input type="number" id="sbid-max" class="form-control form-control-sm"
                                            placeholder="Max" step="0.01" style="font-size: 0.8rem;">
                                    </div>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.75rem;">ACOS L30 (%)</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" id="acos-min" class="form-control form-control-sm"
                                            placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                        <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                        <input type="number" id="acos-max" class="form-control form-control-sm"
                                            placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                    </div>
                                </div>
                                <div class="col">
                                    <label class="form-label fw-semibold mb-2"
                                        style="color: #475569; font-size: 0.75rem;">Price ($)</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" id="price-min" class="form-control form-control-sm"
                                            placeholder="Min" step="0.01" style="font-size: 0.8rem;">
                                        <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                        <input type="number" id="price-max" class="form-control form-control-sm"
                                            placeholder="Max" step="0.01" style="font-size: 0.8rem;">
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label fw-semibold" style="color: #475569; font-size: 0.75rem; visibility: hidden;">
                                        Clear
                                    </label>
                                    <button type="button" id="clear-range-filters-btn" class="btn btn-sm btn-danger" title="Clear all range filters">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>  
                </div>
            </div>
        </div>

        <!-- Main Table Card -->
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-4">
                    <!-- Campaign Search - Just Above Table -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3">
                                <!-- Search Input -->
                                <div class="flex-grow-1">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0" style="border-color: #e2e8f0;">
                                            <i class="fa-solid fa-search" style="color: #94a3b8;"></i>
                                        </span>
                                        <input type="text" id="global-search"
                                            class="form-control form-control-md border-start-0"
                                            placeholder="Search by campaign name or SKU..." style="border-color: #e2e8f0;">
                                    </div>
                                </div>
                                <!-- Pagination Count Display - Right Side -->
                                <div>
                                    <span id="pagination-count" class="badge badge-light"
                                        style="font-weight: 500;color: #000000;font-size: 1rem;padding: 8px 12px;">
                                        Showing 0 of 0 rows
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Chart Modal -->
    <div class="modal fade" id="utilizationChartModal" tabindex="-1" aria-labelledby="utilizationChartModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="utilizationChartModalLabel">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        <span id="chart-title">Utilization Trend</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <canvas id="utilizationChart" height="80"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Bootstrap JS for modal functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            document.body.style.zoom = "90%"; // Removed to make UI elements larger

            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let totalSkuCountFromBackend = 0; // Store total SKU count from backend

            const invFilter = document.querySelector("#inv-filter");
            const nrlFilter = document.querySelector("#nrl-filter");

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts from table data (calculated directly from frontend)
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                // Use 'active' to get filtered data (data that passes combinedFilter)
                // This ensures counts match what's actually displayed in the table
                // Get rows instead of data to ensure we get all filtered rows
                const activeRows = table.getRows('active');
                const allData = activeRows.map(row => row.getData());
                let overCount = 0;
                let underCount = 0;
                let missingCount = 0;
                let nraMissingCount = 0;
                let zeroInvCount = 0;
                let totalCampaignCount = 0;
                let nraCount = 0;
                let raCount = 0;
                let validSkuCount = 0;
                let count7ub = 0;
                let count7ub1ub = 0;

                const processedSkusForNra = new Set();
                const processedSkusForCampaign = new Set();
                const processedSkusForMissing = new Set();
                const processedSkusForNraMissing = new Set();
                const processedSkusForZeroInv = new Set();
                const processedSkusForValidCount = new Set();

                // Data is already filtered by combinedFilter, so we just count what's visible
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const isValidSku = sku && !sku.toUpperCase().includes('PARENT');

                    if (!isValidSku) return;

                    let inv = parseFloat(row.INV || 0);

                    // Count INV 0 (data is already filtered, so all items here match the filters)
                    if (inv <= 0 && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }

                    // Count NRA/RA
                    if (!processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (rowNra === 'NRA') {
                            nraCount++;
                        } else {
                            raCount++;
                        }
                    }

                    // Count campaigns
                    const hasCampaign = row.campaign_id && row.campaignName;
                    let invFilterVal = $("#inv-filter").val();
                    
                    if (invFilterVal === "INV_0") {
                        // When INV 0 filter is active, count all INV 0 items (same as INV 0 count)
                        if (inv <= 0 && !processedSkusForCampaign.has(sku)) {
                            processedSkusForCampaign.add(sku);
                            totalCampaignCount++;
                        }
                    } else if (hasCampaign) {
                        // For other filters, only count items with actual campaigns
                        if (!processedSkusForCampaign.has(sku)) {
                            processedSkusForCampaign.add(sku);
                            totalCampaignCount++;
                        }
                    }

                    // Count missing campaigns
                    if (!hasCampaign) {
                        if (!processedSkusForMissing.has(sku)) {
                            processedSkusForMissing.add(sku);
                            let rowNrlForMissing = row.NRL ? row.NRL.trim() : "";
                            let rowNraForMissing = row.NRA ? row.NRA.trim() : "";
                            if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') {
                                missingCount++;
                            } else {
                                if (!processedSkusForNraMissing.has(sku)) {
                                    processedSkusForNraMissing.add(sku);
                                    nraMissingCount++;
                                }
                            }
                        }
                    }

                    // Count valid SKUs
                    if (!processedSkusForValidCount.has(sku)) {
                        processedSkusForValidCount.add(sku);
                        validSkuCount++;
                    }

                    let budget = parseFloat(row.campaignBudgetAmount) || 0;
                    let spend_L7 = parseFloat(row.spend_L7 || 0);
                    let spend_L1 = parseFloat(row.spend_L1 || 0);

                    let ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                    let ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;

                    // Count utilization types (matching backend logic from getFilteredCampaignIds)
                    // Over: UB7 > 99% AND UB1 > 99%
                    // Under: UB7 < 66% AND UB1 < 66%
                    if (ub7 > 99 && ub1 > 99) {
                        overCount++;
                    } else if (ub7 < 66 && ub1 < 66) {
                        underCount++;
                    }

                    // Count 7UB (based on 7UB only)
                    if (ub7 > 99 || ub7 < 66) {
                        count7ub++;
                    }

                    // Count 7UB + 1UB (based on both 7UB and 1UB)
                    if ((ub7 > 99 && ub1 > 99) || (ub7 < 66 && ub1 < 66)) {
                        count7ub1ub++;
                    }
                });

                document.getElementById('missing-campaign-count').textContent = missingCount;
                document.getElementById('nra-missing-count').textContent = nraMissingCount;
                document.getElementById('total-campaign-count').textContent = totalCampaignCount;
                document.getElementById('nra-count').textContent = nraCount;
                document.getElementById('ra-count').textContent = raCount;
                document.getElementById('zero-inv-count').textContent = zeroInvCount;
                // Total SKU count is set in ajaxResponse from backend, don't override here

                const utilizationSelect = document.getElementById('utilization-type-select');
                if (utilizationSelect) {
                    utilizationSelect.options[0].text = `All (${validSkuCount})`;
                    utilizationSelect.options[1].text = `Over Utilized (${overCount})`;
                    utilizationSelect.options[2].text = `Under Utilized (${underCount})`;
                }

                document.getElementById('7ub-count').textContent = count7ub;
                document.getElementById('7ub-1ub-count').textContent = count7ub1ub;
            }

            // Function to update pagination count display
            function updatePaginationCount() {
                try {
                    if (typeof table === 'undefined' || !table) {
                        return;
                    }

                    // Calculate actual filtered count to match pagination
                    const filteredData = table.getData('active');
                    if (!filteredData || filteredData.length === undefined) {
                        return;
                    }

                    const totalRows = filteredData.length;
                    const pageSize = table.getPageSize() || 100;
                    const currentPage = table.getPage() || 1;

                    let startRow = 0;
                    let endRow = 0;

                    if (totalRows > 0) {
                        startRow = ((currentPage - 1) * pageSize) + 1;
                        endRow = Math.min(currentPage * pageSize, totalRows);
                    }

                    const paginationCountEl = document.getElementById('pagination-count');
                    if (paginationCountEl) {
                        if (totalRows === 0) {
                            paginationCountEl.textContent = 'Showing 0 of 0 rows';
                        } else {
                            paginationCountEl.textContent = `Showing ${startRow} to ${endRow} of ${totalRows} rows`;
                        }
                    }
                } catch (error) {
                    console.warn('Error updating pagination count:', error);
                }
            }

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/google/shopping/data",
                layout: "fitDataFill",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",
                virtualDom: true,
                pagination: "local",
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                selectable: true,
                selectableRangeMode: "click",
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';

                    if (sku && sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        visible: false
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        hozAlign: "left",
                        formatter: function(cell) {
                            let row = cell.getRow().getData();
                            let sku = cell.getValue();
                            let campaignId = row.campaign_id || '';
                            let campaignName = row.campaignName || '';
                            let campaignStatus = (row.campaignStatus || '').toUpperCase();
                            let isEnabled = campaignStatus === 'ENABLED';
                            
                            return `
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                    <span>${sku}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        ${campaignId ? `
                                            <div class="form-check form-switch">
                                                <input class="form-check-input campaign-toggle-switch" 
                                                       type="checkbox" 
                                                       role="switch" 
                                                       data-sku="${sku}"
                                                       data-campaign-id="${campaignId}"
                                                       ${isEnabled ? 'checked' : ''}
                                                       style="cursor: pointer; width: 3rem; height: 1.5rem;">
                                            </div>
                                        ` : ''}
                                        ${campaignId && campaignName ? `
                                            <button class="btn btn-sm btn-outline-primary campaign-chart-btn" data-campaign-name="${String(campaignName).replace(/&/g, '&amp;').replace(/"/g, '&quot;')}" title="Campaign chart"><i class="fas fa-chart-line"></i></button>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "Missing",
                        field: "hasCampaign",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            // Check if campaign exists: hasCampaign field or if campaign_id/campaignName exists
                            const hasCampaign = row.hasCampaign !== undefined ?
                                row.hasCampaign :
                                (row.campaign_id && row.campaignName);

                            // Check if NRA is "NRA" and campaign is missing - show yellow dot
                            const nraValue = row.NRA ? row.NRA.trim() : "";
                            let dotColor, title;

                            if (nraValue === 'NRA' && !hasCampaign) {
                                // NRA items that are missing should show yellow dot
                                dotColor = 'yellow';
                                title = 'NRA - Campaign Missing';
                            } else {
                                // Regular logic: green if campaign exists, red if missing
                                dotColor = hasCampaign ? 'green' : 'red';
                                title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            }

                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${title}"></span>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return `<div class="text-center">${value || 0}<i class="fa-solid fa-circle-info ms-1 info-icon-inv-toggle" style="cursor: pointer; color: #6366f1;" title="Click to show/hide details"></i></div>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue() || "REQ"; // Default to REQ

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                                </select>
                            `;
                        },
                        visible: false,
                        hozAlign: "center"
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const rowData = row.getData();
                            // If NRL is 'NRL' (red dot), default to NRA, otherwise default to RA
                            const nrlValue = rowData.NRL || "REQ";
                            const defaultValue = (nrlValue === 'NRL') ? "NRA" : "RA";
                            const value = (cell.getValue()?.trim()) || defaultValue;

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2) +
                                " <i class='fa fa-info-circle text-primary toggle-price-cols-btn' style='cursor:pointer; margin-left:5px; pointer-events:auto;' title='Click to show/hide GPFT, PFT, ROI, SPRICE, SPFT columns'></i>";
                        },
                        sorter: "number",
                        width: 120
                    },
                    {
                        title: "GPFT",
                        field: "GPFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "PFT%",
                        field: "PFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0%';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '0%';
                            let color = '';

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "ROI%",
                        field: "roi",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0%';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '0%';
                            let color = '';

                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "SPRICE",
                        field: "SPRICE",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const currentPrice = parseFloat(rowData.price) || 0;
                            const sprice = parseFloat(value) || 0;

                            if (!value || sprice === 0) return '';

                            // Show blank if price and SPRICE match
                            if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice
                                .toFixed(2)) {
                                return '';
                            }

                            return `$${parseFloat(value).toFixed(2)}`;
                        },
                        sorter: "number",
                        width: 100
                    },
                    {
                        title: "SPFT",
                        field: "SPFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';

                            let color = '';
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                    },
                    {
                        title: "SBGT",
                        field: "sbgt",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            // Use same rounded values as Spend L30 / Sales L30 display so ACOS matches (83/1=8300%, not 82.94/1=8294%)
                            var spend_L30 = Math.round(parseFloat(row.spend_L30 || 0));
                            var sales_L30 = Math.round(parseFloat(row.ad_sales_L30 || 0));
                            var acos = 0;

                            if (sales_L30 >= 1) {
                                acos = (spend_L30 / sales_L30) * 100;
                            } else if (spend_L30 > 0) {
                                acos = 100; // Spend but no/negligible sales
                            } else {
                                acos = 0;
                            }

                            // Calculate SBGT based on ACOS ranges
                            var sbgt = 0;
                            if (acos < 10) {
                                sbgt = 5;
                            } else if (acos >= 10 && acos < 30) {
                                sbgt = 4;
                            } else if (acos >= 30 && acos < 40) {
                                sbgt = 3;
                            } else if (acos >= 40 && acos < 50) {
                                sbgt = 2;
                            } else if (acos >= 50) {
                                sbgt = 1;
                            }

                            return sbgt;
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();

                            var spendA = Math.round(parseFloat(dataA.spend_L30 || 0));
                            var salesA = Math.round(parseFloat(dataA.ad_sales_L30 || 0));
                            var acosA = 0;
                            if (salesA >= 1) {
                                acosA = (spendA / salesA) * 100;
                            } else if (spendA > 0) {
                                acosA = 100;
                            }

                            var spendB = Math.round(parseFloat(dataB.spend_L30 || 0));
                            var salesB = Math.round(parseFloat(dataB.ad_sales_L30 || 0));
                            var acosB = 0;
                            if (salesB >= 1) {
                                acosB = (spendB / salesB) * 100;
                            } else if (spendB > 0) {
                                acosB = 100;
                            }

                            // Calculate SBGT for A
                            var sbgtA = 0;
                            if (acosA < 10) {
                                sbgtA = 5;
                            } else if (acosA >= 10 && acosA < 30) {
                                sbgtA = 4;
                            } else if (acosA >= 30 && acosA < 40) {
                                sbgtA = 3;
                            } else if (acosA >= 40 && acosA < 50) {
                                sbgtA = 2;
                            } else if (acosA >= 50) {
                                sbgtA = 1;
                            }

                            // Calculate SBGT for B
                            var sbgtB = 0;
                            if (acosB < 10) {
                                sbgtB = 5;
                            } else if (acosB >= 10 && acosB < 30) {
                                sbgtB = 4;
                            } else if (acosB >= 30 && acosB < 40) {
                                sbgtB = 3;
                            } else if (acosB >= 40 && acosB < 50) {
                                sbgtB = 2;
                            } else if (acosB >= 50) {
                                sbgtB = 1;
                            }

                            return sbgtA - sbgtB;
                        }
                    },
                    {
                        title: "ACOS L30",
                        field: "acos_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            // Use rounded spend/sales (same as column display) so ACOS matches: 83/1=8300%
                            var spend_L30 = Math.round(parseFloat(row.spend_L30 || 0));
                            var sales_L30 = Math.round(parseFloat(row.ad_sales_L30 || 0));
                            var acos = 0;

                            if (sales_L30 >= 1) {
                                acos = (spend_L30 / sales_L30) * 100;
                            } else if (spend_L30 > 0) {
                                acos = 100;
                            } else {
                                acos = 0;
                            }

                            return Math.round(acos) + "%" +
                                " <i class='fa fa-info-circle text-primary toggle-l7-l1-cols-btn' style='cursor:pointer; margin-left:5px; pointer-events:auto;' title='Click to show/hide L30 columns'></i>";
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();

                            var spendA = Math.round(parseFloat(dataA.spend_L30 || 0));
                            var salesA = Math.round(parseFloat(dataA.ad_sales_L30 || 0));
                            var acosA = (salesA >= 1) ? (spendA / salesA) * 100 : (spendA > 0 ? 100 : 0);

                            var spendB = Math.round(parseFloat(dataB.spend_L30 || 0));
                            var salesB = Math.round(parseFloat(dataB.ad_sales_L30 || 0));
                            var acosB = (salesB >= 1) ? (spendB / salesB) * 100 : (spendB > 0 ? 100 : 0);

                            return acosA - acosB;
                        }
                    },
                    {
                        title: "Clicks L30 ",
                        field: "clicks_L30",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var clicks_L30 = parseFloat(row.clicks_L30) || 0;
                            return clicks_L30;
                        }
                    },
                    {
                        title: "Spend L30",
                        field: "spend_L30",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_L30 = parseFloat(row.spend_L30) || 0;
                            return Math.round(spend_L30);
                        }
                    },
                    {
                        title: "Sales L30",
                        field: "ad_sales_L30",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sales_L30 = parseFloat(row.ad_sales_L30 || 0);
                            return Math.round(sales_L30);
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();
                            var salesA = parseFloat(dataA.ad_sales_L30 || 0);
                            var salesB = parseFloat(dataB.ad_sales_L30 || 0);
                            return salesA - salesB;
                        }
                    },
                    {
                        title: "AD SOLD L30",
                        field: "ad_sold_L30",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L30 = parseFloat(row.ad_sold_L30 || 0);
                            return Math.round(ad_sold_L30);
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();
                            var soldA = parseFloat(dataA.ad_sold_L30 || 0);
                            var soldB = parseFloat(dataB.ad_sold_L30 || 0);
                            return soldA - soldB;
                        }
                    },
                    {
                        title: "AD CVR",
                        field: "ad_cvr",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L30 = parseFloat(row.ad_sold_L30 || 0);
                            var clicks_L30 = parseFloat(row.clicks_L30 || 0);
                            var cvr = 0;

                            // Calculate AD CVR: (AD SOLD / CLICKS) * 100
                            if (clicks_L30 > 0) {
                                cvr = (ad_sold_L30 / clicks_L30) * 100;
                            }

                            return cvr.toFixed(2) + "%";
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();

                            var soldA = parseFloat(dataA.ad_sold_L30 || 0);
                            var clicksA = parseFloat(dataA.clicks_L30 || 0);
                            var cvrA = clicksA > 0 ? (soldA / clicksA) * 100 : 0;

                            var soldB = parseFloat(dataB.ad_sold_L30 || 0);
                            var clicksB = parseFloat(dataB.clicks_L30 || 0);
                            var cvrB = clicksB > 0 ? (soldB / clicksB) * 100 : 0;

                            return cvrA - cvrB;
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "spend_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_L7 = parseFloat(row.spend_L7) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 66) {
                                td.classList.add('red-bg');
                            }

                            return ub7.toFixed(0) + "%";
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();

                            var ubA = dataA.campaignBudgetAmount > 0 ? (parseFloat(dataA.spend_L7) /
                                (parseFloat(dataA.campaignBudgetAmount) * 7)) * 100 : 0;
                            var ubB = dataB.campaignBudgetAmount > 0 ? (parseFloat(dataB.spend_L7) /
                                (parseFloat(dataB.campaignBudgetAmount) * 7)) * 100 : 0;

                            return ubA - ubB;
                        },
                    },
                    {
                        title: "1 UB%",
                        field: "spend_L1",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_L1 = parseFloat(row.spend_L1) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 66 && ub1 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 66) {
                                td.classList.add('red-bg');
                            }

                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "cpc_L7",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L7 = parseFloat(row.cpc_L7) || 0;
                            return cpc_L7.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "cpc_L1",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L1 = parseFloat(row.cpc_L1) || 0;
                            return cpc_L1.toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L1 = parseFloat(row.cpc_L1) || 0;
                            var cpc_L7 = parseFloat(row.cpc_L7) || 0;
                            var sbid;

                            if (cpc_L1 === 0 && cpc_L7 === 0) {
                                sbid = 0.75;
                            } else {
                                sbid = Math.floor(cpc_L7 * 1.10 * 100) / 100;
                            }

                            return sbid.toFixed(2);
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();
                            var sbidA = 0;
                            var sbidB = 0;

                            if (dataA.cpc_L1 === 0 && dataA.cpc_L7 === 0) {
                                sbidA = 0.75;
                            } else {
                                sbidA = Math.floor(parseFloat(dataA.cpc_L7 || 0) * 1.10 * 100) /
                                100;
                            }

                            if (dataB.cpc_L1 === 0 && dataB.cpc_L7 === 0) {
                                sbidB = 0.75;
                            } else {
                                sbidB = Math.floor(parseFloat(dataB.cpc_L7 || 0) * 1.10 * 100) /
                                100;
                            }

                            return sbidA - sbidB;
                        }
                    }
                ],
                initialSort: [{
                    column: "spend_L7",
                    dir: "desc"
                }],
                ajaxResponse: function(url, params, response) {
                    totalSkuCountFromBackend = parseInt(response.total_sku_count) || 0;
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    if (totalSkuCountEl) {
                        totalSkuCountEl.textContent = totalSkuCountFromBackend;
                    }
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    const apr = document.getElementById("apr-all-sbid-btn");
                    if (apr) apr.classList.remove("d-none");
                    document.getElementById("bulk-enable-campaigns-btn").classList.remove("d-none");
                    document.getElementById("bulk-pause-campaigns-btn").classList.remove("d-none");
                } else {
                    const apr = document.getElementById("apr-all-sbid-btn");
                    if (apr) apr.classList.add("d-none");
                    document.getElementById("bulk-enable-campaigns-btn").classList.add("d-none");
                    document.getElementById("bulk-pause-campaigns-btn").classList.add("d-none");
                }
            });

            // Total campaign card click handler
            const totalCampaignCard = document.getElementById('total-campaign-card');
            if (totalCampaignCard) {
                totalCampaignCard.addEventListener('click', function() {
                    showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(6, 182, 212, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // Missing campaign card click handler
            const missingCampaignCard = document.getElementById('missing-campaign-card');
            if (missingCampaignCard) {
                missingCampaignCard.addEventListener('click', function() {
                    showMissingOnly = !showMissingOnly;
                if (showMissingOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // NRA missing card click handler
            const nraMissingCard = document.getElementById('nra-missing-card');
            if (nraMissingCard) {
                nraMissingCard.addEventListener('click', function() {
                    showNraMissingOnly = !showNraMissingOnly;
                if (showNraMissingOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // Zero INV card click handler
            const zeroInvCard = document.getElementById('zero-inv-card');
            if (zeroInvCard) {
                zeroInvCard.addEventListener('click', function() {
                    showZeroInvOnly = !showZeroInvOnly;
                if (showZeroInvOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // NRA card click handler
            const nraCard = document.getElementById('nra-card');
            if (nraCard) {
                nraCard.addEventListener('click', function() {
                    showNraOnly = !showNraOnly;
                if (showNraOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // RA card click handler
            const raCard = document.getElementById('ra-card');
            if (raCard) {
                raCard.addEventListener('click', function() {
                    showRaOnly = !showRaOnly;
                if (showRaOnly) {
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(34, 197, 94, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });
            }

            // Utilization type dropdown handler
            const utilizationTypeSelect = document.getElementById('utilization-type-select');
            if (utilizationTypeSelect) {
                utilizationTypeSelect.addEventListener('change', function() {
                    currentUtilizationType = this.value;
                showMissingOnly = false;
                document.getElementById('missing-campaign-card').style.boxShadow = '';
                showNraMissingOnly = false;
                document.getElementById('nra-missing-card').style.boxShadow = '';
                showCampaignOnly = false;
                document.getElementById('total-campaign-card').style.boxShadow = '';
                showZeroInvOnly = false;
                document.getElementById('zero-inv-card').style.boxShadow = '';
                showNraOnly = false;
                document.getElementById('nra-card').style.boxShadow = '';
                showRaOnly = false;
                document.getElementById('ra-card').style.boxShadow = '';

                if (typeof table !== 'undefined' && table) {
                    setTimeout(function() {
                        table.redraw(true);
                    }, 60);
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                }
            });
            }

            // document.addEventListener("change", function(e){
            //     if(e.target.classList.contains("editable-select")){
            //         let sku   = e.target.getAttribute("data-sku");
            //         let field = e.target.getAttribute("data-field");
            //         let value = e.target.value;

            //         fetch('/update-amazon-nr-nrl-fba', {
            //             method: 'POST',
            //             headers: {
            //                 'Content-Type': 'application/json',
            //                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            //             },
            //             body: JSON.stringify({
            //                 sku: sku,
            //                 field: field,
            //                 value: value
            //             })
            //         })
            //         .then(res => res.json())
            //         .then(data => {
            //             console.log(data);
            //         })
            //         .catch(err => console.error(err));
            //     }
            // });


            // ✅ Combined Filter Function (defined outside so it's accessible)
            function combinedFilter(data) {
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let spend_L7 = parseFloat(data.spend_L7) || 0;
                let spend_L1 = parseFloat(data.spend_L1) || 0;
                let ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;

                // Check if campaign is missing
                const hasCampaign = data.campaign_id && data.campaignName;

                // Apply campaign filters
                if (showCampaignOnly) {
                    if (!hasCampaign) return false;
                } else if (showMissingOnly) {
                    if (hasCampaign) return false;
                    const nrlValueForFilter = data.NRL ? data.NRL.trim() : "";
                    const nraValueForFilter = data.NRA ? data.NRA.trim() : "";
                    if (nrlValueForFilter === 'NRL' || nraValueForFilter === 'NRA') return false;
                } else if (showNraMissingOnly) {
                    if (hasCampaign) return false;
                    const nraValueForNraMissing = data.NRA ? data.NRA.trim() : "";
                    if (nraValueForNraMissing !== 'NRA') return false;
                }

                // Filter by utilization type (matching backend logic from getFilteredCampaignIds)
                // Over: UB7 > 99% AND UB1 > 99%
                // Under: UB7 < 66% AND UB1 < 66%
                if (currentUtilizationType === 'all') {
                    // Show all data (no filter on utilization)
                } else if (currentUtilizationType === 'over') {
                    // Over-utilized: ub7 > 99 AND ub1 > 99
                    if (!(ub7 > 99 && ub1 > 99)) return false;
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: ub7 < 66 AND ub1 < 66
                    if (!(ub7 < 66 && ub1 < 66)) return false;
                }

                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku
                    ?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                let statusVal = $("#status-filter").val();
                if (statusVal) {
                    // For status filter, only apply to items with campaigns
                    const hasCampaign = data.campaign_id && data.campaignName;
                    if (hasCampaign) {
                        // If campaign exists, check status match
                        // Use strict comparison - if campaignStatus is null/undefined, it won't match
                        if (data.campaignStatus !== statusVal) {
                            return false;
                        }
                    } else {
                        // If no campaign exists and status filter is set, exclude it
                        return false;
                    }
                }

                // Apply multi-range filters
                // 1UB range filter
                let ub1Min = $("#1ub-min").val();
                let ub1Max = $("#1ub-max").val();
                if (ub1Min || ub1Max) {
                    if (ub1Min && ub1 < parseFloat(ub1Min)) return false;
                    if (ub1Max && ub1 > parseFloat(ub1Max)) return false;
                }

                // 7UB range filter
                let ub7Min = $("#7ub-min").val();
                let ub7Max = $("#7ub-max").val();
                if (ub7Min || ub7Max) {
                    if (ub7Min && ub7 < parseFloat(ub7Min)) return false;
                    if (ub7Max && ub7 > parseFloat(ub7Max)) return false;
                }

                // SBID range filter
                let sbidMin = $("#sbid-min").val();
                let sbidMax = $("#sbid-max").val();
                if (sbidMin || sbidMax) {
                    let sbid = parseFloat(data.sbid || 0);
                    if (isNaN(sbid)) sbid = 0;

                    if (sbidMin && sbid < parseFloat(sbidMin)) return false;
                    if (sbidMax && sbid > parseFloat(sbidMax)) return false;
                }

                // ACOS L30 range filter
                let acosMin = $("#acos-min").val();
                let acosMax = $("#acos-max").val();
                if (acosMin || acosMax) {
                    let spend_L30 = Math.round(parseFloat(data.spend_L30 || 0));
                    let sales_L30 = Math.round(parseFloat(data.ad_sales_L30 || 0));
                    let acos = (sales_L30 >= 1) ? (spend_L30 / sales_L30) * 100 : (spend_L30 > 0 ? 100 : 0);

                    if (acosMin && acos < parseFloat(acosMin)) return false;
                    if (acosMax && acos > parseFloat(acosMax)) return false;
                }

                // Price range filter
                let priceMin = $("#price-min").val();
                let priceMax = $("#price-max").val();
                if (priceMin || priceMax) {
                    let price = parseFloat(data.price || 0);
                    if (isNaN(price)) price = 0;

                    if (priceMin && price < parseFloat(priceMin)) return false;
                    if (priceMax && price > parseFloat(priceMax)) return false;
                }

                // Apply zero INV filter first (if enabled)
                let inv = parseFloat(data.INV || 0);
                if (showZeroInvOnly) {
                    if (inv > 0) return false;
                } else {
                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal || invFilterVal === '') {
                        // All Inventory - show all (no filtering)
                    } else if (invFilterVal === "INV_GT_0") {
                        if (inv <= 0) return false;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return false;
                    }
                }

                // Apply NRA/RA filters
                let rowNra = data.NRA ? data.NRA.trim() : "";
                if (showNraOnly) {
                    if (rowNra !== 'NRA') return false;
                } else if (showRaOnly) {
                    if (rowNra === 'NRA') return false;
                } else {
                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowNrl = data.NRL ? data.NRL.trim() : "";
                        if (rowNrl !== nrlFilterVal) return false;
                    }
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        if (nraFilterVal === 'NRA' && rowNra !== 'NRA') return false;
                        if (nraFilterVal === 'RA' && rowNra === 'NRA') return false;
                    }
                }

                return true;
            }

            // Load utilization counts for chart cards
            function loadUtilizationCounts() {
                fetch('/google/shopping/get-utilization-counts')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 200) {
                            // 7UB count: sum of over and under (no correctly for Google Shopping)
                            const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0);
                            // 7UB + 1UB count: sum of over and under
                            const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data
                                .under_utilized_7ub_1ub || 0);

                            const count7ubEl = document.getElementById('7ub-count');
                            const count7ub1ubEl = document.getElementById('7ub-1ub-count');

                            if (count7ubEl) count7ubEl.textContent = count7ub || 0;
                            if (count7ub1ubEl) count7ub1ubEl.textContent = count7ub1ub || 0;
                        }
                    })
                    .catch(err => console.error('Error loading counts:', err));
            }

            // Chart card click handlers (set up after DOM is ready)
            setTimeout(function() {
                document.querySelectorAll('.utilization-card').forEach(card => {
                    card.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        showUtilizationChart(type);
                    });
                });
            }, 100);

            let utilizationChartInstance = null;

            function showUtilizationChart(type) {
                const chartTitle = document.getElementById('chart-title');
                const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));

                const titles = {
                    '7ub': '7UB Utilization Trend (Last 30 Days)',
                    '7ub-1ub': '7UB + 1UB Utilization Trend (Last 30 Days)'
                };
                chartTitle.textContent = titles[type] || 'Utilization Trend';

                modal.show();

                fetch('/google/shopping/get-utilization-chart-data?condition=' + type)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 200 && data.data && data.data.length > 0) {
                            const chartData = data.data;
                            const dates = chartData.map(d => d.date);

                            const ctx = document.getElementById('utilizationChart').getContext('2d');

                            if (utilizationChartInstance) {
                                utilizationChartInstance.destroy();
                            }

                            // Show over and under lines (no correctly for Google Shopping)
                            const datasets = [];

                            if (type === '7ub') {
                                datasets.push({
                                    label: 'Over Utilized',
                                    data: chartData.map(d => d.over_utilized_7ub || 0),
                                    borderColor: '#ff01d0',
                                    backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                });
                                datasets.push({
                                    label: 'Under Utilized',
                                    data: chartData.map(d => d.under_utilized_7ub || 0),
                                    borderColor: '#ff2727',
                                    backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                });
                            } else if (type === '7ub-1ub') {
                                datasets.push({
                                    label: 'Over Utilized',
                                    data: chartData.map(d => d.over_utilized_7ub_1ub || 0),
                                    borderColor: '#ff01d0',
                                    backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                });
                                datasets.push({
                                    label: 'Under Utilized',
                                    data: chartData.map(d => d.under_utilized_7ub_1ub || 0),
                                    borderColor: '#ff2727',
                                    backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                });
                            }

                            utilizationChartInstance = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: dates,
                                    datasets: datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            enabled: true,
                                            mode: 'index',
                                            intersect: false,
                                            callbacks: {
                                                title: function(context) {
                                                    return 'Date: ' + context[0].label;
                                                },
                                                label: function(context) {
                                                    return context.dataset.label + ': ' + context
                                                        .parsed.y;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                precision: 0
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    })
                    .catch(err => console.error('Error loading chart:', err));
            }

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);

                let filterTimeout = null;
                table.on("dataFiltered", function() {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                        updatePaginationCount();
                    }, 200);
                });

                table.on("dataProcessed", function() {
                    setTimeout(updatePaginationCount, 100);
                });

                table.on("pageLoaded", function() {
                    setTimeout(updatePaginationCount, 100);
                });
                
                // Update pagination count on page changes
                table.on("pageChanged", function() {
                    setTimeout(updatePaginationCount, 100);
                });

                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nrl-filter, #nra-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                        updatePaginationCount();
                    }, 300);
                });

                // Range filter event listeners
                $("#1ub-min, #1ub-max, #7ub-min, #7ub-max, #sbid-min, #sbid-max, #acos-min, #acos-max, #price-min, #price-max")
                    .on("input", function() {
                        table.setFilter(combinedFilter);
                        setTimeout(function() {
                            updateButtonCounts();
                            updatePaginationCount();
                        }, 300);
                    });

                // Clear range filters button
                $("#clear-range-filters-btn").on("click", function() {
                    // Clear all range filter inputs
                    $("#1ub-min, #1ub-max").val('');
                    $("#7ub-min, #7ub-max").val('');
                    $("#sbid-min, #sbid-max").val('');
                    $("#acos-min, #acos-max").val('');
                    $("#price-min, #price-max").val('');
                    
                    // Apply filter to refresh table
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                        updatePaginationCount();
                    }, 300);
                });

                setTimeout(function() {
                    updateButtonCounts();
                    updatePaginationCount();
                }, 1000);
                loadUtilizationCounts();
            });

            table.on("dataLoaded", function() {
                setTimeout(updatePaginationCount, 100);
                table.setFilter(combinedFilter);
                setTimeout(function() {
                    updateButtonCounts();
                }, 500);
                loadUtilizationCounts();
            });


            // Handle campaign chart button (moved from removed CAMPAIGN column into SKU column)
            document.addEventListener("click", function(e) {
                const btn = e.target.closest(".campaign-chart-btn");
                if (btn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const name = btn.getAttribute("data-campaign-name");
                    if (name) showCampaignChart(name);
                }
            });

            // Handle campaign toggle switch
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("campaign-toggle-switch")) {
                    e.stopPropagation();
                    const switchElement = e.target;
                    const campaignId = switchElement.getAttribute('data-campaign-id');
                    const sku = switchElement.getAttribute('data-sku');
                    const isChecked = switchElement.checked;
                    const status = isChecked ? 'ENABLED' : 'PAUSED';
                    
                    // Disable switch during request
                    switchElement.disabled = true;
                    
                    $.ajax({
                        url: "{{ route('google.shopping.toggle.campaign.status') }}",
                        type: "POST",
                        data: {
                            campaign_id: campaignId,
                            status: status,
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            // If we're in success callback, the HTTP request was successful
                            // Check response body status
                            if (response && response.status === 200) {
                                // Update the row data
                                const row = table.getRows().find(row => {
                                    const rowData = row.getData();
                                    return rowData.campaign_id === campaignId;
                                });
                                
                                if (row) {
                                    row.update({campaignStatus: status});
                                }
                                
                                // Show success message (don't show alert, just log)
                                const message = response.message || (status === 'ENABLED' ? 'Campaign enabled successfully' : 'Campaign paused successfully');
                                console.log(message);
                            } else {
                                // Response indicates error even though HTTP was successful
                                switchElement.checked = !isChecked;
                                alert('Failed to update campaign status: ' + (response?.message || 'Unknown error'));
                            }
                        },
                        error: function(xhr) {
                            // Revert switch state on error
                            switchElement.checked = !isChecked;
                            const errorMsg = xhr.responseJSON?.message || 'Failed to update campaign status';
                            alert(errorMsg);
                        },
                        complete: function() {
                            // Re-enable switch after request
                            switchElement.disabled = false;
                        }
                    });
                }
            });

            // Bulk Enable campaigns
            document.getElementById("bulk-enable-campaigns-btn").addEventListener("click", function() {
                runBulkCampaignToggle("ENABLED");
            });
            // Bulk Pause campaigns
            document.getElementById("bulk-pause-campaigns-btn").addEventListener("click", function() {
                runBulkCampaignToggle("PAUSED");
            });

            function runBulkCampaignToggle(status) {
                const selected = table.getSelectedRows();
                const campaignIds = selected.map(function(r) { return r.getData().campaign_id; }).filter(function(id) { return id; });
                if (campaignIds.length === 0) {
                    alert("Select at least one row with a campaign.");
                    return;
                }
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";
                $.ajax({
                    url: "{{ route('google.shopping.toggle.bulk.campaign.status') }}",
                    type: "POST",
                    data: {
                        campaign_ids: campaignIds,
                        status: status,
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(res) {
                        if (res && res.status === 200 && res.updated > 0) {
                            campaignIds.forEach(function(cid) {
                                const row = table.getRows().find(function(r) { return r.getData().campaign_id === cid; });
                                if (row) row.update({ campaignStatus: status });
                            });
                            if (typeof table !== "undefined" && table) table.redraw(true);
                            alert(res.message || (status === "ENABLED" ? "Campaigns enabled." : "Campaigns paused."));
                        } else {
                            alert((res && res.message) || "Update failed.");
                        }
                    },
                    error: function(xhr) {
                        alert((xhr.responseJSON && xhr.responseJSON.message) || "Request failed.");
                    },
                    complete: function() { overlay.style.display = "none"; }
                });
            }

            // Handle editable-select changes (NRL, NRA)
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    // If NRL is set to "NRL" (red dot), automatically set NRA to "NRA" (red dot)
                    if (field === 'NRL' && value === 'NRL') {
                        // Find the NRA select dropdown for this row
                        let row = table.searchRows('sku', '=', sku);
                        if (row.length > 0) {
                            let rowData = row[0].getData();
                            let nraCell = row[0].getCell('NRA');
                            if (nraCell) {
                                let nraSelect = nraCell.getElement().querySelector('.editable-select');
                                if (nraSelect) {
                                    nraSelect.value = 'NRA';
                                    nraSelect.style.backgroundColor = '#dc3545'; // red
                                    nraSelect.style.color = '#000';

                                    // Update NRA in backend
                                    fetch('/update-google-nr-data', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector(
                                                    'meta[name="csrf-token"]').getAttribute(
                                                    'content')
                                            },
                                            body: JSON.stringify({
                                                sku: sku,
                                                field: 'NRA',
                                                value: 'NRA'
                                            })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.status === 200 && typeof table !== 'undefined' &&
                                                table) {
                                                row[0].update({
                                                    NRA: 'NRA'
                                                });
                                            }
                                            setTimeout(function() {
                                                updateButtonCounts();
                                            }, 200);
                                        })
                                        .catch(err => console.error(err));
                                }
                            }
                        }
                    }

                    // Update color immediately for NRA field
                    if (field === 'NRA') {
                        if (value === 'NRA') {
                            e.target.style.backgroundColor = '#dc3545'; // red
                            e.target.style.color = '#000';
                        } else if (value === 'RA') {
                            e.target.style.backgroundColor = '#28a745'; // green
                            e.target.style.color = '#000';
                        } else if (value === 'LATER') {
                            e.target.style.backgroundColor = '#ffc107'; // yellow
                            e.target.style.color = '#000';
                        }
                    }

                    fetch('/update-google-nr-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                sku: sku,
                                field: field,
                                value: value
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            console.log(data);
                            // Update table data with response
                            if (data.status === 200 && typeof table !== 'undefined' && table) {
                                let row = table.searchRows('sku', '=', sku);
                                if (row.length > 0) {
                                    row[0].update({
                                        [field]: value
                                    });
                                }
                            }
                            // Update button counts after change
                            setTimeout(function() {
                                updateButtonCounts();
                            }, 200);
                        })
                        .catch(err => console.error(err));
                }
            });

            // Bulk Actions: Select All, Mark NRA, Mark RA
            let selectAllMode = false;

            const selectAllRowsBtn = document.getElementById('select-all-rows-btn');
            if (selectAllRowsBtn) {
                selectAllRowsBtn.addEventListener('click', function() {
                    selectAllMode = !selectAllMode;
                    const selectedRows = table.getSelectedRows();

                    if (selectAllMode) {
                        // Select all visible rows
                        const allRows = table.getRows('visible');
                        allRows.forEach(row => row.select());
                        this.innerHTML = '<i class="fa fa-times"></i> Deselect All';
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-primary');
                    } else {
                        // Deselect all
                        table.deselectRow();
                        this.innerHTML = '<i class="fa fa-check-double"></i> All';
                        this.classList.remove('btn-primary');
                        this.classList.add('btn-outline-primary');
                    }

                updateBulkActionButtons();
            });
            }

            // Update bulk action buttons state based on selection
            function updateBulkActionButtons() {
                const selectedRows = table.getSelectedRows();
                const hasSelection = selectedRows.length > 0;

                document.getElementById('mark-nra-btn').disabled = !hasSelection;
                document.getElementById('mark-ra-btn').disabled = !hasSelection;
            }

            // Mark selected rows as NRA
            const markNraBtn = document.getElementById('mark-nra-btn');
            if (markNraBtn) {
                markNraBtn.addEventListener('click', function() {
                    const selectedRows = table.getSelectedRows();
                    if (selectedRows.length === 0) {
                        alert('Please select at least one row');
                        return;
                    }

                    if (!confirm(`Mark ${selectedRows.length} selected row(s) as NRA?`)) {
                        return;
                    }

                    const skus = selectedRows.map(row => row.getData().sku).filter(sku => sku);

                    fetch('/bulk-update-google-nr-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                skus: skus,
                                field: 'NRA',
                                value: 'NRA'
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 200) {
                                // Update table rows
                                selectedRows.forEach(row => {
                                    const rowData = row.getData();
                                    row.update({
                                        NRA: 'NRA'
                                    });

                                    // Update NRA select dropdown if visible
                                    const nraCell = row.getCell('NRA');
                                    if (nraCell) {
                                        const cellElement = nraCell.getElement();
                                        const select = cellElement.querySelector(
                                            '.editable-select');
                                        if (select) {
                                            select.value = 'NRA';
                                            select.style.backgroundColor = '#dc3545';
                                            select.style.color = '#000';
                                        }
                                    }
                                });

                                alert(data.message);
                                updateButtonCounts();
                                table.deselectRow();
                                updateBulkActionButtons();
                                selectAllMode = false;
                                document.getElementById('select-all-rows-btn').innerHTML =
                                    '<i class="fa fa-check-double"></i> All';
                                document.getElementById('select-all-rows-btn').classList.remove(
                                    'btn-primary');
                                document.getElementById('select-all-rows-btn').classList.add(
                                    'btn-outline-primary');
                            } else {
                                alert('Error: ' + (data.message || 'Failed to update'));
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Error updating data');
                        });
                });
            }

            // Mark selected rows as RA
            const markRaBtn = document.getElementById('mark-ra-btn');
            if (markRaBtn) {
                markRaBtn.addEventListener('click', function() {
                    const selectedRows = table.getSelectedRows();
                    if (selectedRows.length === 0) {
                        alert('Please select at least one row');
                        return;
                    }

                    if (!confirm(`Mark ${selectedRows.length} selected row(s) as RA?`)) {
                        return;
                    }

                    const skus = selectedRows.map(row => row.getData().sku).filter(sku => sku);

                    fetch('/bulk-update-google-nr-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                skus: skus,
                                field: 'NRA',
                                value: 'RA'
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 200) {
                                // Update table rows
                                selectedRows.forEach(row => {
                                    const rowData = row.getData();
                                    row.update({
                                        NRA: 'RA'
                                    });

                                    // Update NRA select dropdown if visible
                                    const nraCell = row.getCell('NRA');
                                    if (nraCell) {
                                        const cellElement = nraCell.getElement();
                                        const select = cellElement.querySelector(
                                            '.editable-select');
                                        if (select) {
                                            select.value = 'RA';
                                            select.style.backgroundColor = '#28a745';
                                            select.style.color = '#000';
                                        }
                                    }
                                });

                                alert(data.message);
                                updateButtonCounts();
                                table.deselectRow();
                                updateBulkActionButtons();
                                selectAllMode = false;
                                document.getElementById('select-all-rows-btn').innerHTML =
                                    '<i class="fa fa-check-double"></i> All';
                                document.getElementById('select-all-rows-btn').classList.remove(
                                    'btn-primary');
                                document.getElementById('select-all-rows-btn').classList.add(
                                    'btn-outline-primary');
                            } else {
                                alert('Error: ' + (data.message || 'Failed to update'));
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Error updating data');
                        });
                });
            }

            // Update bulk action buttons when selection changes
            table.on("rowSelectionChanged", function(data, rows) {
                updateBulkActionButtons();
            });

            // Handle info icon toggle for INV column
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains('info-icon-inv-toggle')) {
                    e.stopPropagation();
                    const extraColumnFields = ['L30', 'DIL %', 'NRL', 'NRA'];

                    // Check if any column is visible to determine current state
                    const l30Col = table.getColumn('L30');
                    const anyVisible = l30Col && l30Col.isVisible();

                    // Toggle visibility
                    extraColumnFields.forEach(field => {
                        const col = table.getColumn(field);
                        if (col) {
                            if (anyVisible) {
                                col.hide();
                            } else {
                                col.show();
                            }
                        }
                    });
                }
            });

            // Handle info icon toggle for L30 columns
            document.addEventListener("click", function(e) {
                // Check if clicked element or any parent has the toggle class
                let target = e.target;
                let found = false;
                while (target && target !== document) {
                    if (target.classList && target.classList.contains('toggle-l7-l1-cols-btn')) {
                        found = true;
                        break;
                    }
                    target = target.parentElement;
                }

                if (found) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const l30ColumnFields = ['clicks_L30', 'spend_L30', 'ad_sales_L30', 'ad_sold_L30',
                        'ad_cvr'
                    ];

                    // Check if any L30 column is visible to determine current state
                    const clicksL30Col = table.getColumn('clicks_L30');
                    const anyVisible = clicksL30Col && clicksL30Col.isVisible();

                    // Toggle visibility
                    l30ColumnFields.forEach(field => {
                        const col = table.getColumn(field);
                        if (col) {
                            if (anyVisible) {
                                col.hide();
                            } else {
                                col.show();
                            }
                        }
                    });
                    return false;
                }
            }, true); // Use capture phase to catch event earlier

            // Handle info icon toggle for Price columns (GPFT, PFT, ROI, SPRICE, SPFT)
            document.addEventListener("click", function(e) {
                // Check if clicked element or any parent has the toggle class
                let target = e.target;
                let found = false;
                while (target && target !== document) {
                    if (target.classList && target.classList.contains('toggle-price-cols-btn')) {
                        found = true;
                        break;
                    }
                    target = target.parentElement;
                }

                if (found) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const priceColumnFields = ['GPFT', 'PFT', 'roi', 'SPRICE', 'SPFT'];

                    // Check if any price-related column is visible to determine current state
                    const gpftCol = table.getColumn('GPFT');
                    const anyVisible = gpftCol && gpftCol.isVisible();

                    // Toggle visibility
                    priceColumnFields.forEach(field => {
                        const col = table.getColumn(field);
                        if (col) {
                            if (anyVisible) {
                                col.hide();
                            } else {
                                col.show();
                            }
                        }
                    });
                    return false;
                }
            }, true); // Use capture phase to catch event earlier

            const aprAllSbidBtn = document.getElementById("apr-all-sbid-btn");
            if (aprAllSbidBtn) {
                aprAllSbidBtn.addEventListener("click", function() {
                    const overlay = document.getElementById("progress-overlay");
                    overlay.style.display = "flex";

                    var filteredData = table.getSelectedRows();

                    var campaignIds = [];
                    var bids = [];

                    filteredData.forEach(function(row) {
                        var rowEl = row.getElement();
                        if (rowEl && rowEl.offsetParent !== null) {

                            var rowData = row.getData();
                            var cpc_L1 = parseFloat(rowData.cpc_L1) || 0;
                            var cpc_L7 = parseFloat(rowData.cpc_L7) || 0;

                            var sbid = 0;
                            if (cpc_L1 === 0 && cpc_L7 === 0) {
                                sbid = 0.75;
                            } else {
                                sbid = Math.floor(cpc_L7 * 1.10 * 100) / 100;
                            }

                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbid);
                        }
                    });
                    console.log("Campaign IDs:", campaignIds);
                    console.log("Bids:", bids);
                    fetch('/update-google-ads-bid-price', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                campaign_ids: campaignIds,
                                bids: bids
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            console.log("Backend response:", data);
                            if (data.status === 200) {
                                alert("Keywords updated successfully!");
                            } else {
                                alert("Something went wrong: " + data.message);
                            }
                        })
                        .catch(err => console.error(err))
                        .finally(() => {
                            overlay.style.display = "none";
                        });
                });
            }

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                console.log("Updating bid for Campaign ID:", campaignId, "New Bid:", aprBid);

                fetch('/update-google-ads-bid-price', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            campaign_ids: [campaignId],
                            bids: [aprBid]
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log("Backend response:", data);
                    })
                    .catch(err => console.error(err))
                    .finally(() => {
                        overlay.style.display = "none";
                    });
            }

            // Safe selector function
            function getRowSelectBySkuAndField(sku, field) {
                try {
                    let escapedSku = CSS.escape(sku); // escape special chars
                    return document.querySelector(`select[data-sku="${escapedSku}"][data-field="${field}"]`);
                } catch (e) {
                    console.warn("Invalid selector for SKU:", sku, e);
                    return null;
                }
            }

            const exportBtn = document.getElementById("export-btn");
            if (exportBtn) {
                exportBtn.addEventListener("click", function() {
                    // Get all data regardless of filters
                    let allData = table.getData("all");

                    let exportData = allData.map(row => {
                        let cpc_L1 = parseFloat(row.cpc_L1 || 0);
                        let cpc_L7 = parseFloat(row.cpc_L7 || 0);
                        let sbid;

                        if (cpc_L1 === 0 && cpc_L7 === 0) {
                            sbid = 0.75;
                        } else {
                            sbid = Math.floor(cpc_L7 * 1.10 * 100) / 100;
                        }

                        // Calculate UB7 and UB1
                        let budget = parseFloat(row.campaignBudgetAmount || 0);
                        let spend_L7 = parseFloat(row.spend_L7 || 0);
                        let spend_L1 = parseFloat(row.spend_L1 || 0);
                        let ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;

                        return {
                            Parent: row.parent || "",
                            SKU: row.sku || "",
                            Campaign: row.campaignName || "",
                            Status: row.campaignStatus || "",
                            Budget: budget.toFixed(2),
                            "Clicks L30": parseFloat(row.clicks_L30 || 0),
                            "Spend L30": Math.round(parseFloat(row.spend_L30 || 0)),
                            "7 UB%": ub7.toFixed(0) + "%",
                            "1 UB%": ub1.toFixed(0) + "%",
                            "L7 CPC": parseFloat(row.cpc_L7 || 0).toFixed(2),
                            "L1 CPC": parseFloat(row.cpc_L1 || 0).toFixed(2),
                            SBID: sbid.toFixed(2),
                            INV: parseFloat(row.INV || 0),
                            "OV L30": parseFloat(row.L30 || 0),
                            NRL: row.NRL || "",
                            NRA: row.NRA || ""
                        };
                    });

                    if (exportData.length === 0) {
                        alert("No data available to export!");
                        return;
                    }

                    let ws = XLSX.utils.json_to_sheet(exportData);
                    let wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Google Shopping Data");

                    XLSX.writeFile(wb, "google_shopping_utilized_data.xlsx");
                });
            }

            // document.body.style.zoom = "78%"; // Removed to make UI elements larger
        });
    </script>

    <script>
        const ctx = document.getElementById('campaignChart').getContext('2d');

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dates) !!},
                datasets: [{
                        label: 'Clicks',
                        data: {!! json_encode($clicks) !!},
                        borderColor: 'purple',
                        backgroundColor: 'rgba(128, 0, 128, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    },
                    {
                        label: 'Spend (USD)',
                        data: {!! json_encode($spend) !!},
                        borderColor: 'teal',
                        backgroundColor: 'rgba(0, 128, 128, 0.1)',
                        yAxisID: 'y2',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    },
                    {
                        label: 'Orders',
                        data: {!! json_encode($orders) !!},
                        borderColor: 'magenta',
                        backgroundColor: 'rgba(255, 0, 255, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    },
                    {
                        label: 'Sales (USD)',
                        data: {!! json_encode($sales) !!},
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0, 0, 255, 0.1)',
                        yAxisID: 'y2',
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    tooltip: {
                        backgroundColor: "#fff",
                        titleColor: "#111",
                        bodyColor: "#333",
                        borderColor: "#ddd",
                        borderWidth: 1,
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                if (context.dataset.label.includes("Spend") || context.dataset.label.includes(
                                        "Sales")) {
                                    return `${context.dataset.label}: $${Number(value).toFixed(2)}`;
                                }
                                return `${context.dataset.label}: ${value}`;
                            }
                        }
                    },
                    legend: {
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            padding: 20
                        },
                        onClick: (e, legendItem, legend) => {
                            const index = legendItem.datasetIndex;
                            const ci = legend.chart;
                            const meta = ci.getDatasetMeta(index);
                            meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                            ci.update();
                        }
                    }
                },
                scales: {
                    y1: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Clicks / Orders'
                        }
                    },
                    y2: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Spend / Sales (USD)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.getElementById("toggleChartBtn");
            const chartContainer = document.getElementById("chartContainer");
            const arrowIcon = document.getElementById("chartArrow");

            toggleBtn.addEventListener("click", function() {
                if (chartContainer.style.display === "none") {
                    chartContainer.style.display = "block";
                    arrowIcon.classList.remove("fa-chevron-down");
                    arrowIcon.classList.add("fa-chevron-up");
                } else {
                    chartContainer.style.display = "none";
                    arrowIcon.classList.remove("fa-chevron-up");
                    arrowIcon.classList.add("fa-chevron-down");
                }
            });
        });

        $(function() {
            let picker = $('#daterange-btn').daterangepicker({
                opens: 'right',
                autoUpdateInput: false,
                alwaysShowCalendars: true,
                locale: {
                    format: "D MMM YYYY",
                    cancelLabel: 'Clear'
                },
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                        'month').endOf('month')]
                }
            }, function(start, end) {
                const startDate = start.format("YYYY-MM-DD");
                const endDate = end.format("YYYY-MM-DD");

                $('#daterange-btn span').html("Date range: " + startDate + " - " + endDate);
                fetchChartData(startDate, endDate);
            });

            $('#daterange-btn').on('cancel.daterangepicker', function(ev, picker) {
                $(this).find('span').html("Date range: Select");
                fetchChartData();
            });

        });

        function fetchChartData(startDate, endDate) {
            $.ajax({
                url: "{{ route('google.shopping.chart.filter') }}",
                type: "GET",
                data: {
                    startDate,
                    endDate
                },
                success: function(response) {
                    const formattedDates = response.dates.map(d => moment(d).format('MMM DD'));
                    chart.data.labels = formattedDates;
                    chart.data.datasets[0].data = response.clicks;
                    chart.data.datasets[1].data = response.spend;
                    chart.data.datasets[2].data = response.orders;
                    chart.data.datasets[3].data = response.sales;
                    chart.update();

                    $('.card-clicks').text(response.totals.clicks);
                    $('.card-spend').text('US$' + Math.round(response.totals.spend));
                    $('.card-orders').text(response.totals.orders);
                    $('.card-sales').text('US$' + Math.round(response.totals.sales));
                    
                    // Calculate and update ACOS (sales < 1 = no sales, avoid 10759% type values)
                    const totalSpend = response.totals.spend || 0;
                    const totalSales = response.totals.sales || 0;
                    let acos;
                    if (totalSales >= 1) {
                        acos = (totalSpend / totalSales) * 100;
                    } else if (totalSpend > 0) {
                        acos = 100;
                    } else {
                        acos = 0;
                    }
                    $('.card-acos').text(acos.toFixed(2) + '%');
                    
                    // Calculate and update CVR
                    const totalOrders = response.totals.orders || 0;
                    const totalClicks = response.totals.clicks || 0;
                    const cvr = totalClicks > 0 ? (totalOrders / totalClicks) * 100 : 0;
                    $('.card-cvr').text(cvr.toFixed(2) + '%');
                }
            });
        }

        // Campaign chart functions
        function showCampaignChart(campaignName) {
            console.log('Opening modal for campaign:', campaignName);

            // Update modal title with date range
            const endDate = moment().format('MMM DD, YYYY');
            const startDate = moment().subtract(29, 'days').format('MMM DD, YYYY');
            $('#campaignModalLabel').text(campaignName + ' (' + startDate + ' - ' + endDate + ')');

            // Try both jQuery and Bootstrap 5 methods to show modal
            try {
                const modalElement = document.getElementById('campaignModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    console.log('Modal opened using Bootstrap 5');
                } else {
                    $('#campaignModal').modal('show');
                    console.log('Modal opened using jQuery');
                }

                // Fetch campaign data
                fetchCampaignChartData(campaignName);
            } catch (error) {
                console.error('Error opening modal:', error);
                // Fallback method
                $('#campaignModal').show();
            }
        }

        function fetchCampaignChartData(campaignName) {
            console.log('Fetching campaign chart data for:', campaignName);

            // Default to last 30 days
            const endDate = moment().format('YYYY-MM-DD');
            const startDate = moment().subtract(29, 'days').format('YYYY-MM-DD');

            $.ajax({
                url: '/google/shopping/campaign/chart-data',
                method: 'GET',
                data: {
                    campaignName: campaignName,
                    startDate: startDate,
                    endDate: endDate
                },
                beforeSend: function() {
                    console.log('Sending campaign chart request...');
                    // Show loading state
                    $('#modal-clicks, #modal-spend, #modal-orders, #modal-sales, #modal-impressions, #modal-ctr')
                        .text('Loading...');
                },
                success: function(response) {
                    console.log('Campaign chart data response:', response);

                    // Update modal stats
                    $('#modal-clicks').text(response.totals.clicks);
                    $('#modal-spend').text('US$' + Math.round(response.totals.spend));
                    $('#modal-orders').text(response.totals.orders);
                    $('#modal-sales').text('US$' + Math.round(response.totals.sales));
                    $('#modal-impressions').text(response.totals.impressions);
                    $('#modal-ctr').text(response.totals.ctr + '%');

                    // Update chart
                    updateModalChart(response.chartData);
                },
                error: function(xhr) {
                    console.error('Error fetching campaign chart data:', xhr.responseText);
                }
            });
        }

        function updateModalChart(chartData) {
            const ctx = document.getElementById('campaignModalChart').getContext('2d');

            if (window.campaignModalChartInstance) {
                window.campaignModalChartInstance.destroy();
            }

            window.campaignModalChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Clicks',
                        data: chartData.clicks,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        yAxisID: 'y',
                        tension: 0.4
                    }, {
                        label: 'Spend (US$)',
                        data: chartData.spend,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    spanGaps: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Clicks'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Spend (US$)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }
    </script>

    <!-- Campaign Chart Modal -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignModalLabel">Campaign Performance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Stats Cards -->
                    <div class="row text-center mb-4">
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Clicks</div>
                                <div class="h5 mb-0 fw-bold text-primary" id="modal-clicks">0</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Spend</div>
                                <div class="h5 mb-0 fw-bold text-success" id="modal-spend">US$0</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Orders</div>
                                <div class="h5 mb-0 fw-bold text-danger" id="modal-orders">0</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Sales</div>
                                <div class="h5 mb-0 fw-bold text-info" id="modal-sales">US$0</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Impressions</div>
                                <div class="h5 mb-0 fw-bold text-warning" id="modal-impressions">0</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">CTR</div>
                                <div class="h5 mb-0 fw-bold text-secondary" id="modal-ctr">0%</div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart -->
                    <div id="campaignModalChartContainer">
                        <canvas id="campaignModalChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
