@extends('layouts.vertical', ['title' => 'Ebay3 - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        /* Hide sorting arrows */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter-element {
            display: none !important;
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

        #budget-under-table .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        /* Ensure table body horizontal scroll syncs with header */
        #budget-under-table .tabulator .tabulator-tableHolder {
            overflow-x: auto !important;
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
            height: 90px;
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

        .utilization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        body {
            zoom: 90%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay3 - Utilized',
        'sub_title' => 'Ebay3 - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-3">
                    <div class="mb-3">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Filters Row: Utilization Type, Status, Inventory, NRA, NRL, SBID M -->
                                <div class="row g-3 align-items-end mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>Utilization Type
                                        </label>
                                        <select id="utilization-type-select" class="form-select form-select-md">
                                            <option value="all" selected>All</option>
                                            <option value="over">Over Utilized</option>
                                            <option value="under">Under Utilized</option>
                                            <option value="correctly">Correctly Utilized</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-toggle-on me-1" style="color: #64748b;"></i>Status
                                        </label>
                                        <select id="status-filter" class="form-select form-select-md">
                                            <option value="">All Status</option>
                                            <option value="RUNNING">Running</option>
                                            <option value="PAUSED">Paused</option>
                                            <option value="ENDED">Ended</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-md">
                                            <option value="">All Inventory</option>
                                            <option value="ALL" selected>ALL</option>
                                            <option value="INV_0">0 INV</option>
                                            <option value="OTHERS">OTHERS</option>
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
                                            <option value="LATER">LATER</option>
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
                                            <option value="REQ">REQ</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>SBID M
                                        </label>
                                        <select id="sbid-m-filter" class="form-select form-select-md">
                                            <option value="">All</option>
                                            <option value="blank">Blank</option>
                                            <option value="data">Data</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Count Badges Row -->
                                <div class="row pb-3 border-bottom">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold mb-2 d-block"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Total Parent SKU:</span>
                                                <span class="fw-bold" id="total-sku-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item ebay-sku-card" id="ebay-sku-card"
                                                style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Ebay SKU:</span>
                                                <span class="fw-bold" id="ebay-sku-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item total-campaign-card" id="total-campaign-card"
                                                style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Campaign:</span>
                                                <span class="fw-bold" id="total-campaign-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item missing-campaign-card" id="missing-campaign-card"
                                                style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Missing:</span>
                                                <span class="fw-bold" id="missing-campaign-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nra-missing-card" id="nra-missing-card"
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRA MISSING:</span>
                                                <span class="fw-bold" id="nra-missing-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item zero-inv-card" id="zero-inv-card"
                                                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Zero INV:</span>
                                                <span class="fw-bold" id="zero-inv-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nra-card" id="nra-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRA:</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nrl-missing-card" id="nrl-missing-card"
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRL MISSING:</span>
                                                <span class="fw-bold" id="nrl-missing-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nrl-card" id="nrl-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRL:</span>
                                                <span class="fw-bold" id="nrl-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item ra-card" id="ra-card"
                                                style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">RA:</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item paused-campaigns-card" id="paused-campaigns-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;"
                                                title="Click to view paused campaigns">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">PINK DIL PAUSED:</span>
                                                <span class="fw-bold" id="paused-campaigns-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item utilization-card" data-type="7ub"
                                                style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">7UB:</span>
                                                <span class="fw-bold" id="7ub-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item utilization-card" data-type="7ub-1ub"
                                                style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">7UB+1UB:</span>
                                                <span class="fw-bold" id="7ub-1ub-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">L30 CLICKS:</span>
                                                <span class="fw-bold" id="l30-total-clicks" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">L30 SPEND:</span>
                                                <span class="fw-bold" id="l30-total-spend" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">L30 AD SOLD:</span>
                                                <span class="fw-bold" id="l30-total-ad-sold" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">AVG ACOS:</span>
                                                <span class="fw-bold" id="avg-acos" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">AVG CVR:</span>
                                                <span class="fw-bold" id="avg-cvr" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi Range Filter Section -->
                                <div class="row g-3 align-items-end pt-2">
                                    <!-- 1UB% Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            1UB% Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-1ub-min"
                                                class="form-control form-control-sm" placeholder="Min" step="0.01"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-1ub-max"
                                                class="form-control form-control-sm" placeholder="Max" step="0.01"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- 7UB% Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            7UB% Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-7ub-min"
                                                class="form-control form-control-sm" placeholder="Min" step="0.01"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-7ub-max"
                                                class="form-control form-control-sm" placeholder="Max" step="0.01"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- LBid Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            LBid Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-lbid-min"
                                                class="form-control form-control-sm" placeholder="Min" step="0.01"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-lbid-max"
                                                class="form-control form-control-sm" placeholder="Max" step="0.01"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- Acos Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            Acos Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-acos-min"
                                                class="form-control form-control-sm" placeholder="Min" step="0.01"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-acos-max"
                                                class="form-control form-control-sm" placeholder="Max" step="0.01"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- Views Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            Views Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-views-min"
                                                class="form-control form-control-sm" placeholder="Min" step="1"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-views-max"
                                                class="form-control form-control-sm" placeholder="Max" step="1"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- L7 Views Filter -->
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            L7 Views Range
                                        </label>
                                        <div class="d-flex gap-2">
                                            <input type="number" id="range-filter-l7-views-min"
                                                class="form-control form-control-sm" placeholder="Min" step="1"
                                                style="border-color: #e2e8f0;">
                                            <input type="number" id="range-filter-l7-views-max"
                                                class="form-control form-control-sm" placeholder="Max" step="1"
                                                style="border-color: #e2e8f0;">
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="col-md-2 d-flex gap-2 align-items-end">
                                        <button id="apply-all-range-filters-btn" class="btn btn-primary btn-sm flex-fill">
                                            <i class="fa-solid fa-filter me-1"></i>
                                            Apply
                                        </button>
                                        <button id="clear-all-range-filters-btn" class="btn btn-secondary btn-sm flex-fill">
                                            <i class="fa-solid fa-times me-1"></i>
                                            Clear All
                                        </button>
                                    </div>
                                </div>

                                <!-- INC/DEC SBID Section and Action Buttons -->
                                <div class="row g-3 align-items-end pt-3 border-top">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-calculator me-1" style="color: #64748b;"></i>INC/DEC
                                            SBID
                                        </label>
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" id="inc-dec-btn"
                                                class="btn btn-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="fa-solid fa-plus-minus me-1"></i>
                                                INC/DEC (By Value)
                                            </button>
                                            <ul class="dropdown-menu" id="inc-dec-dropdown">
                                                <li><a class="dropdown-item" href="#" data-type="value">By
                                                        Value</a></li>
                                                <li><a class="dropdown-item" href="#" data-type="percentage">By
                                                        Percentage</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <span id="inc-dec-label">Value/Percentage</span>
                                        </label>
                                        <input type="number" id="inc-dec-input" class="form-control form-control-md"
                                            placeholder="Enter value (e.g., +0.5 or -0.5)" step="0.01"
                                            style="border-color: #e2e8f0;">
                                    </div>
                                    <div class="col-md-2 d-flex gap-2 align-items-end">
                                        <button id="apply-inc-dec-btn" class="btn btn-success btn-sm flex-fill">
                                            <i class="fa-solid fa-check me-1"></i>
                                            Apply
                                        </button>
                                        <button id="clear-inc-dec-btn" class="btn btn-secondary btn-sm flex-fill">
                                            <i class="fa-solid fa-times me-1"></i>
                                            Clear Input
                                        </button>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button id="clear-sbid-m-btn" class="btn btn-danger btn-sm w-100">
                                            <i class="fa-solid fa-trash me-1"></i>
                                            Clear SBID M (Selected)
                                        </button>
                                    </div>
                                    <div class="col-md-4 d-flex gap-2 align-items-end">
                                        <button id="apr-all-sbid-btn" class="btn btn-info btn-sm flex-fill d-none">
                                            <i class="fa-solid fa-check-double me-1"></i>
                                            APR ALL SBID
                                        </button>
                                        <button id="save-all-sbid-m-btn" class="btn btn-success btn-sm flex-fill d-none">
                                            <i class="fa-solid fa-save me-1"></i>
                                            SAVE ALL SBID M
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campaign Search - Just Above Table -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3">
                                <!-- Search Input -->
                                <div class="flex-grow-1">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"
                                            style="border-color: #e2e8f0;">
                                            <i class="fa-solid fa-search" style="color: #94a3b8;"></i>
                                        </span>
                                        <input type="text" id="global-search"
                                            class="form-control form-control-md border-start-0"
                                            placeholder="Search by campaign name or SKU..."
                                            style="border-color: #e2e8f0;">
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

    <!-- Chart Modal -->
    <div class="modal fade" id="utilizationChartModal" tabindex="-1" aria-labelledby="utilizationChartModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white"
                    style="border-top-left-radius: 8px; border-top-right-radius: 8px;">
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
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.75); z-index: 9999; backdrop-filter: blur(4px);">
        <div
            style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="spinner-border text-primary" role="status"
                style="width: 3.5rem; height: 3.5rem; border-width: 4px;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: #333; font-size: 1.3rem; font-weight: 600;">
                Updating campaigns...
            </div>
            <div style="color: #6c757d; font-size: 0.95rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showNrlMissingOnly = false; // Filter for NRL missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showNrlOnly = false; // Filter for NRL only
            let showRaOnly = false; // Filter for RA only
            let showEbaySkuOnly = false; // Filter for eBay SKUs only
            let showPinkDilPausedOnly = false; // Filter for Pink DIL paused campaigns only
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;
            let totalSkuCountFromBackend = 0; // Store total SKU count from backend
            let ebaySkuCountFromBackend = 0; // Store eBay SKU count from backend

            // Multi range filter variables - allows multiple filters simultaneously
            let rangeFilters = {
                '1ub': {
                    min: null,
                    max: null
                },
                '7ub': {
                    min: null,
                    max: null
                },
                'lbid': {
                    min: null,
                    max: null
                },
                'acos': {
                    min: null,
                    max: null
                },
                'views': {
                    min: null,
                    max: null
                },
                'l7_views': {
                    min: null,
                    max: null
                }
            };

            // INC/DEC SBID variables
            let incDecType = 'value'; // 'value' or 'percentage'

            // Function to store statistics in database
            function storeStatistics() {
                fetch('/ebay-3/store-statistics', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 200) {
                        console.log('✅ Statistics stored successfully:', data.statistics);
                    } else {
                        console.error('❌ Error storing statistics:', data.error);
                    }
                })
                .catch(error => {
                    console.error('❌ Error storing statistics:', error);
                });
            }

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts from table data (calculated directly from frontend)
            // Counts are based on filtered data (respects INV, NRA, status, search filters)
            // but shows all utilization types (not filtered by utilization type button)
            function updateButtonCounts() {
                // Calculate counts directly from table data, not from database
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                // Get filtered data (respects all filters except utilization type)
                // We need to get all data and apply filters manually since utilization type filter is separate
                // Use 'all' to get all data, then apply filters manually to match what's actually shown
                const allData = table.getData('all');
                let overCount = 0;
                let underCount = 0;
                let correctlyCount = 0;
                let missingCount = 0;
                let nraMissingCount = 0; // Count NRA missing (yellow dots)
                let nrlMissingCount = 0; // Count NRL missing (yellow dots)
                let zeroInvCount = 0; // Count zero and negative inventory
                let totalCampaignCount = 0; // Count total campaigns
                let nraCount = 0; // Count NRA
                let nrlCount = 0; // Count NRL
                let raCount = 0; // Count RA
                let validSkuCount = 0; // Count only valid SKUs (not parent, not empty)
                let ub7Count = 0; // Count 7UB
                let ub7Ub1Count = 0; // Count 7UB + 1UB
                let ebaySkuCount = 0; // Count eBay SKUs (SKUs with campaigns) after filters
                let pausedCampaignsCount = 0; // Count paused campaigns

                // Track processed SKUs to avoid counting duplicates
                const processedSkusForNra = new Set(); // Track SKUs for NRA/RA counting
                const processedSkusForNrl = new Set(); // Track SKUs for NRL/REQ counting
                const processedSkusForCampaign = new Set(); // Track SKUs for campaign counting
                const processedSkusForMissing = new Set(); // Track SKUs for missing counting
                const processedSkusForNraMissing = new Set(); // Track SKUs for NRA missing counting
                const processedSkusForNrlMissing = new Set(); // Track SKUs for NRL missing counting
                const processedSkusForZeroInv = new Set(); // Track SKUs for zero INV counting

                allData.forEach(function(row) {
                    // Count valid SKUs (exclude parent SKUs and empty SKUs)
                    const sku = row.sku || '';
                    const isValidSku = sku && !sku.toUpperCase().includes('PARENT');

                    // Count zero/negative inventory (INV <= 0) - count BEFORE filters
                    // This should count all zero INV SKUs regardless of current filter (including PARENT SKUs)
                    let inv = parseFloat(row.INV || 0);
                    if (inv <= 0 && sku && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }

                    // Count campaigns BEFORE filters - campaigns should be counted regardless of INV
                    // This ensures PARENT SKUs with INV = 0 are still counted if they have campaigns
                    // Count by campaign_id to avoid missing campaigns with duplicate SKUs or missing SKUs
                    const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row
                        .campaign_id && row.campaignName);
                    const campaignId = row.campaign_id || '';
                    if (hasCampaign && campaignId && !processedSkusForCampaign.has(campaignId)) {
                        processedSkusForCampaign.add(campaignId);
                        totalCampaignCount++;
                    }

                    // Apply all filters except utilization type filter
                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku
                            ?.toLowerCase().includes(searchVal))) {
                        return;
                    }

                    // Status filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && row.campaignStatus !== statusVal) {
                        return;
                    }

                    // Inventory filter
                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal || invFilterVal === '') {
                        // Default: exclude INV = 0 and negative
                        if (inv <= 0) return;
                    } else if (invFilterVal === "ALL") {
                        // ALL option shows everything
                    } else if (invFilterVal === "INV_0") {
                        // Show only INV = 0
                        if (inv !== 0) return;
                    } else if (invFilterVal === "OTHERS") {
                        // Show only INV > 0
                        if (inv <= 0) return;
                    }

                    // Count NRA and RA for all SKUs (including PARENT SKUs) and only once per SKU (after filters)
                    if (sku && !processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        // Note: Empty/null NRA defaults to "RA" in the display
                        let rowNra = row.NR ? row.NR.trim() : "";
                        if (rowNra === 'NRA') {
                            nraCount++;
                        } else {
                            // If NRA is empty, null, or "RA", it shows as "RA" by default
                            raCount++;
                        }
                    }

                    // Count NRL and REQ for all SKUs (including PARENT SKUs) and only once per SKU (after filters)
                    if (sku && !processedSkusForNrl.has(sku)) {
                        processedSkusForNrl.add(sku);
                        // Note: Empty/null NRL defaults to "REQ" in the display
                        let rowNrl = row.NRL ? row.NRL.trim() : "";
                        if (rowNrl === 'NRL') {
                            nrlCount++;
                        } else {
                            // If NRL is empty, null, or "REQ", it shows as "REQ" by default
                            // REQ count is not tracked separately, only NRL
                        }
                    }

                    // NRA filter
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowNra = row.NR ? row.NR.trim() : "";
                        if (nraFilterVal === 'RA') {
                            // For "RA" filter, include empty/null values too
                            if (rowNra === 'NRA') return;
                        } else {
                            // For "NRA" or "LATER", exact match
                            if (rowNra !== nraFilterVal) return;
                        }
                    }

                    // NRL filter
                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowNrl = row.NRL ? row.NRL.trim() : "";
                        if (nrlFilterVal === 'REQ') {
                            // For "REQ" filter, include empty/null values too
                            if (rowNrl === 'NRL') return;
                        } else {
                            // For "NRL", exact match
                            if (rowNrl !== nrlFilterVal) return;
                        }
                    }

                    // SBID M filter
                    let sbidMFilterVal = $("#sbid-m-filter").val();
                    if (sbidMFilterVal && sbidMFilterVal !== '') {
                        let rowSbidM = row.sbid_m;

                        // Check if sbid_m is blank/null/empty/0
                        let isBlank = false;

                        // Check for null, undefined, or empty string
                        if (rowSbidM === null || rowSbidM === undefined || rowSbidM === '') {
                            isBlank = true;
                        } else {
                            // Convert to string and trim
                            let strValue = String(rowSbidM).trim();

                            // Check for string representations of zero/empty
                            if (strValue === '' || strValue === '0' || strValue === '0.0' || strValue ===
                                '0.00' || strValue === '0.000' || strValue === '-') {
                                isBlank = true;
                            } else {
                                // Try parsing as number
                                let numValue = parseFloat(strValue);
                                if (isNaN(numValue) || numValue === 0 || numValue <= 0) {
                                    isBlank = true;
                                }
                            }
                        }

                        // Apply filter
                        if (sbidMFilterVal === 'blank' && !isBlank) {
                            return; // Filter out non-blank values
                        } else if (sbidMFilterVal === 'data' && isBlank) {
                            return; // Filter out blank values
                        }
                    }

                    // eBay SKU filter - show only SKUs that have campaign (PARENT SKUs don't have price)
                    // Note: hasCampaign is already defined before filters, so we reuse it here
                    if (showEbaySkuOnly) {
                        // Show only if has campaign (price check removed for PARENT SKUs)
                        if (!hasCampaign) return;
                    }

                    // Count eBay SKUs (SKUs with campaigns) after all filters are applied
                    if (hasCampaign && isValidSku) {
                        ebaySkuCount++;
                    }

                    // Count missing campaigns for all SKUs (including PARENT SKUs) after filters
                    // Note: Campaigns are already counted before filters, so we only count missing here
                    if (!hasCampaign && sku && !processedSkusForMissing.has(sku)) {
                        processedSkusForMissing.add(sku);
                        // Check if this is a red dot (missing AND not yellow)
                        let rowNrlForMissing = row.NRL ? row.NRL.trim() : "";
                        let rowNraForMissing = row.NR ? row.NR.trim() : "";
                        // Only count as missing (red dot) if neither NRL='NRL' nor NRA='NRA'
                        if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') {
                            missingCount++;
                        } else {
                            // Count NRL missing (yellow dots) separately
                            if (rowNrlForMissing === 'NRL' && !processedSkusForNrlMissing.has(sku)) {
                                processedSkusForNrlMissing.add(sku);
                                nrlMissingCount++;
                            }
                            // Count NRA missing (yellow dots) separately
                            // If NRL='NRL', NRA should be 'NRA', so count it as NRA missing too
                            if (rowNraForMissing === 'NRA' && !processedSkusForNraMissing.has(sku)) {
                                processedSkusForNraMissing.add(sku);
                                nraMissingCount++;
                            } else if (rowNrlForMissing === 'NRL' && !processedSkusForNraMissing.has(sku)) {
                                // If NRL='NRL' but NRA is not 'NRA', still count as NRA missing
                                // because NRA should be 'NRA' when NRL is 'NRL'
                                processedSkusForNraMissing.add(sku);
                                nraMissingCount++;
                            }
                        }
                    }

                    // Count valid SKUs that pass all filters
                    if (isValidSku) {
                        validSkuCount++;
                    }

                    // Now calculate utilization and count - only for rows with campaigns
                    // Note: hasCampaign is already defined before filters, so we reuse it here
                    if (hasCampaign) {
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        // 7UB + 1UB condition categorization (matches command and filter logic)
                        if (ub7 > 99 && ub1 > 99) {
                            overCount++;
                        } else if (ub7 < 66 && ub1 < 66) {
                            // For under utilized, also check INV > 0 (matches filter logic)
                            let inv = parseFloat(row.INV || 0);
                            if (inv > 0) {
                                underCount++;
                            }
                        } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                            correctlyCount++;
                        }

                        // Count 7UB (ub7 >= 66 && ub7 <= 99) - only for rows with campaigns
                        if (ub7 >= 66 && ub7 <= 99) {
                            ub7Count++;
                        }

                        // Count 7UB + 1UB (both ub7 and ub1 >= 66 && <= 99) - only for rows with campaigns
                        if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                            ub7Ub1Count++;
                        }
                    }

                    // Count paused campaigns (campaigns with pink_dil_paused_at)
                    if (hasCampaign && row.pink_dil_paused_at) {
                        pausedCampaignsCount++;
                    }
                });

                // Update missing campaign count
                const missingCountEl = document.getElementById('missing-campaign-count');
                if (missingCountEl) {
                    missingCountEl.textContent = missingCount;
                }

                // Update NRA missing count
                const nraMissingCountEl = document.getElementById('nra-missing-count');
                if (nraMissingCountEl) {
                    nraMissingCountEl.textContent = nraMissingCount;
                }

                // Update NRL missing count
                const nrlMissingCountEl = document.getElementById('nrl-missing-count');
                if (nrlMissingCountEl) {
                    nrlMissingCountEl.textContent = nrlMissingCount;
                }

                // Update total campaign count
                const totalCampaignCountEl = document.getElementById('total-campaign-count');
                if (totalCampaignCountEl) {
                    totalCampaignCountEl.textContent = totalCampaignCount;
                }

                // Update NRA count
                const nraCountEl = document.getElementById('nra-count');
                if (nraCountEl) {
                    nraCountEl.textContent = nraCount;
                }

                // Update NRL count
                const nrlCountEl = document.getElementById('nrl-count');
                if (nrlCountEl) {
                    nrlCountEl.textContent = nrlCount;
                }

                // Update RA count
                const raCountEl = document.getElementById('ra-count');
                if (raCountEl) {
                    raCountEl.textContent = raCount;
                }

                // Update paused campaigns count
                const pausedCampaignsCountEl = document.getElementById('paused-campaigns-count');
                if (pausedCampaignsCountEl) {
                    pausedCampaignsCountEl.textContent = pausedCampaignsCount;
                }

                // Update zero INV count
                const zeroInvCountEl = document.getElementById('zero-inv-count');
                if (zeroInvCountEl) {
                    zeroInvCountEl.textContent = zeroInvCount;
                }

                // Update 7UB count
                const ub7CountEl = document.getElementById('7ub-count');
                if (ub7CountEl) {
                    ub7CountEl.textContent = ub7Count;
                }

                // Update 7UB + 1UB count
                const ub7Ub1CountEl = document.getElementById('7ub-1ub-count');
                if (ub7Ub1CountEl) {
                    ub7Ub1CountEl.textContent = ub7Ub1Count;
                }

                // Update total SKU count
                const totalSkuCountEl = document.getElementById('total-sku-count');
                if (totalSkuCountEl) {
                    totalSkuCountEl.textContent = totalSkuCountFromBackend || validSkuCount;
                }

                // Update eBay SKU count - use filtered count if showEbaySkuOnly is active, otherwise use backend count
                const ebaySkuCountEl = document.getElementById('ebay-sku-count');
                if (ebaySkuCountEl) {
                    if (showEbaySkuOnly && typeof table !== 'undefined' && table) {
                        // When filter is active, get count from filtered table data to match pagination
                        try {
                            const filteredData = table.getData('active');
                            ebaySkuCountEl.textContent = filteredData.length;
                        } catch (e) {
                            console.error('Error getting filtered eBay SKU count:', e);
                            ebaySkuCountEl.textContent = ebaySkuCount;
                        }
                    } else {
                        // When filter is not active, show the backend count
                        ebaySkuCountEl.textContent = ebaySkuCountFromBackend;
                    }
                }

                // Update dropdown option texts with counts
                // Use totalSkuCountFromBackend to match backend count exactly
                // Utilization counts already exclude missing rows (only count rows with campaigns)
                // Calculate actual filtered count to match pagination
                let actualOverCount = overCount;
                let actualUnderCount = underCount;
                let actualCorrectlyCount = correctlyCount;

                // If utilization type is selected, get count from filtered data to match pagination
                if (currentUtilizationType !== 'all' && typeof table !== 'undefined' && table) {
                    try {
                        const filteredData = table.getData('active');
                        if (currentUtilizationType === 'over') {
                            actualOverCount = filteredData.length;
                        } else if (currentUtilizationType === 'under') {
                            actualUnderCount = filteredData.length;
                        } else if (currentUtilizationType === 'correctly') {
                            actualCorrectlyCount = filteredData.length;
                        }
                    } catch (e) {
                        console.error('Error getting filtered count:', e);
                    }
                }

                const utilizationSelect = document.getElementById('utilization-type-select');
                if (utilizationSelect) {
                    utilizationSelect.options[0].text = `All (${totalSkuCountFromBackend || validSkuCount})`;
                    // Show utilization counts - use actual filtered count if utilization type is selected
                    utilizationSelect.options[1].text =
                        `Over Utilized (${currentUtilizationType === 'over' ? actualOverCount : overCount})`;
                    utilizationSelect.options[2].text =
                        `Under Utilized (${currentUtilizationType === 'under' ? actualUnderCount : underCount})`;
                    utilizationSelect.options[3].text =
                        `Correctly Utilized (${currentUtilizationType === 'correctly' ? actualCorrectlyCount : correctlyCount})`;
                }
            }

            // Function to calculate and update L30 totals (clicks, spend, ad_sold)
            // Variables to store L30 totals from backend
            let totalL30ClicksFromBackend = 0;
            let totalL30SpendFromBackend = 0;
            let totalL30AdSoldFromBackend = 0;

            // Track manually selected rows (exclude header checkbox selections)
            let manuallySelectedRows = new Set();

            function updateL30Totals() {
                // Update L30 CLICKS total from backend
                const l30ClicksEl = document.getElementById('l30-total-clicks');
                if (l30ClicksEl) {
                    l30ClicksEl.textContent = totalL30ClicksFromBackend.toLocaleString();
                }

                // Update L30 SPEND total from backend
                const l30SpendEl = document.getElementById('l30-total-spend');
                if (l30SpendEl) {
                    l30SpendEl.textContent = Math.round(totalL30SpendFromBackend).toLocaleString();
                }

                // Update L30 AD SOLD total from backend
                const l30AdSoldEl = document.getElementById('l30-total-ad-sold');
                if (l30AdSoldEl) {
                    l30AdSoldEl.textContent = totalL30AdSoldFromBackend.toLocaleString();
                }
            }

            // Utilization type dropdown handler
            const utilizationTypeSelect = document.getElementById('utilization-type-select');
            if (utilizationTypeSelect) {
                utilizationTypeSelect.addEventListener('change', function() {
                    currentUtilizationType = this.value;
                    if (typeof table !== 'undefined' && table) {
                        // Update SBID column visibility based on utilization type
                        if (currentUtilizationType === 'correctly') {
                            table.hideColumn('sbid');
                        } else {
                            table.showColumn('sbid');
                        }
                        table.setFilter(combinedFilter);
                        table.redraw(true);
                        // Update counts after filter is applied to get accurate counts
                        setTimeout(function() {
                            updateButtonCounts();
                            updateL30Totals();
                            updatePaginationCount();
                        }, 300);
                    }
                });
            }

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
                showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
                    // Reset dropdown to "All" when showing campaigns only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Show SBID column when showing all
                    if (typeof table !== 'undefined' && table) {
                        table.showColumn('sbid');
                    }
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(6, 182, 212, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // Missing campaign card click handler
            document.getElementById('missing-campaign-card').addEventListener('click', function() {
                showMissingOnly = !showMissingOnly;
                if (showMissingOnly) {
                    // Reset dropdown to "All" when showing missing only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    // Reset Pink DIL Paused filter
                    showPinkDilPausedOnly = false;
                    document.getElementById('paused-campaigns-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // NRA missing card click handler
            document.getElementById('nra-missing-card').addEventListener('click', function() {
                showNraMissingOnly = !showNraMissingOnly;
                if (showNraMissingOnly) {
                    // Reset dropdown to "All" when showing NRA missing only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    // Reset Pink DIL Paused filter
                    showPinkDilPausedOnly = false;
                    document.getElementById('paused-campaigns-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // Zero INV card click handler
            document.getElementById('zero-inv-card').addEventListener('click', function() {
                showZeroInvOnly = !showZeroInvOnly;
                if (showZeroInvOnly) {
                    // Reset dropdown to "All" when showing zero INV only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    updateL30Totals();
                }
            });

            // NRA card click handler
            document.getElementById('nra-card').addEventListener('click', function() {
                showNraOnly = !showNraOnly;
                if (showNraOnly) {
                    // Reset dropdown to "All" when showing NRA only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset RA filter
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // NRL missing card click handler
            document.getElementById('nrl-missing-card').addEventListener('click', function() {
                showNrlMissingOnly = !showNrlMissingOnly;
                if (showNrlMissingOnly) {
                    // Reset dropdown to "All" when showing NRL missing only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // NRL card click handler
            document.getElementById('nrl-card').addEventListener('click', function() {
                showNrlOnly = !showNrlOnly;
                if (showNrlOnly) {
                    // Reset dropdown to "All" when showing NRL only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA filter
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    // Reset RA filter
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // RA card click handler
            document.getElementById('ra-card').addEventListener('click', function() {
                showRaOnly = !showRaOnly;
                if (showRaOnly) {
                    // Reset dropdown to "All" when showing RA only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA filter
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset eBay SKU filter
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(34, 197, 94, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // eBay SKU card click handler
            document.getElementById('ebay-sku-card').addEventListener('click', function() {
                showEbaySkuOnly = !showEbaySkuOnly;
                if (showEbaySkuOnly) {
                    // Reset dropdown to "All" when showing eBay SKU only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRL missing filter
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA filter
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    // Reset NRL filter
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    // Reset RA filter
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    // Reset Pink DIL Paused filter
                    showPinkDilPausedOnly = false;
                    document.getElementById('paused-campaigns-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(139, 92, 246, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // Paused campaigns card click handler - filter table instead of showing modal
            document.getElementById('paused-campaigns-card').addEventListener('click', function() {
                showPinkDilPausedOnly = !showPinkDilPausedOnly;
                if (showPinkDilPausedOnly) {
                    // Reset dropdown to "All" when showing paused only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Show SBID column when showing all
                    if (typeof table !== 'undefined' && table) {
                        table.showColumn('sbid');
                    }
                    // Reset other filters
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showNrlMissingOnly = false;
                    document.getElementById('nrl-missing-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showNrlOnly = false;
                    document.getElementById('nrl-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showEbaySkuOnly = false;
                    document.getElementById('ebay-sku-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                    setTimeout(updatePaginationCount, 100);
                }
            });

            // Function to update header checkbox state (defined before table initialization)
            function updateHeaderCheckboxState() {
                var headerCheckbox = document.querySelector('.tabulator-select-all-checkbox');
                if (headerCheckbox && typeof table !== 'undefined') {
                    var activeRows = table.getRows('active');
                    var currentPage = table.getPage();
                    var pageSize = table.getPageSize();
                    var startIndex = (currentPage - 1) * pageSize;
                    var endIndex = startIndex + pageSize;
                    var currentPageRows = activeRows.slice(startIndex, endIndex);

                    var selectedCurrentPageRows = currentPageRows.filter(function(row) {
                        return row.isSelected();
                    });
                    headerCheckbox.checked = currentPageRows.length > 0 && currentPageRows.length ===
                        selectedCurrentPageRows.length;
                }
            }

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay-3/utilized/ads/data",
                ajaxParams: {
                    all_campaigns: true,
                    limit: 10000
                },
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",
                virtualDom: true,
                pagination: "local",
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';
                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: function(column) {
                            var checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.className = "tabulator-select-all-checkbox";

                            // Function to get current page rows
                            function getCurrentPageRows() {
                                var activeRows = table.getRows(
                                'active'); // All rows that pass filters
                                var currentPage = table.getPage();
                                var pageSize = table.getPageSize();
                                var startIndex = (currentPage - 1) * pageSize;
                                var endIndex = startIndex + pageSize;
                                return activeRows.slice(startIndex, endIndex);
                            }

                            // Function to update checkbox state
                            function updateCheckboxState() {
                                var currentPageRows = getCurrentPageRows();
                                var selectedCurrentPageRows = currentPageRows.filter(function(row) {
                                    return row.isSelected();
                                });
                                checkbox.checked = currentPageRows.length > 0 && currentPageRows
                                    .length === selectedCurrentPageRows.length;
                            }

                            // Initial state - will be updated by external handlers

                            checkbox.addEventListener("change", function(e) {
                                e.stopPropagation();

                                var currentPageRows = getCurrentPageRows();

                                if (checkbox.checked) {
                                    // Select all rows on current page
                                    currentPageRows.forEach(function(row) {
                                        row.select();
                                    });
                                } else {
                                    // Deselect all rows on current page
                                    currentPageRows.forEach(function(row) {
                                        row.deselect();
                                    });
                                }
                            });

                            return checkbox;
                        },
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
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `<span>${sku}</span>`;
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

                            // Check if NRL is "NRL" (red dot) OR NRA is "NRA" - if so, show yellow dot
                            const nrlValue = row.NRL ? row.NRL.trim() : "";
                            const nraValue = row.NR ? row.NR.trim() : "";
                            let dotColor, title;

                            if (nrlValue === 'NRL' || nraValue === 'NRA') {
                                dotColor = 'yellow';
                                title = 'NRL or NRA - Not Required';
                            } else {
                                dotColor = hasCampaign ? 'green' : 'red';
                                title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            }

                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${title}"></span>
                                </div>
                            `;
                        },
                        visible: true,
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: true,
                        width: 60,
                        titleFormatter: function(column) {
                            return `
                                <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <span>INV</span>
                                    <button class="btn btn-sm btn-link p-0 toggle-inv-cols-btn" 
                                            style="font-size: 15px; color: #cf2408; text-decoration: none; padding: 0; line-height: 1;" 
                                            title="Toggle L30, DIL%, NRL, NRA columns">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </button>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false,
                        width: 60
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
                                const percent = dilDecimal * 100;
                                let textColor = '#000000'; // default black
                                let bgColor = 'transparent'; // default background

                                if (percent < 16.66) {
                                    textColor = '#dc3545'; // red
                                } else if (percent >= 16.66 && percent < 25) {
                                    textColor = '#b8860b'; // darker yellow (darkgoldenrod)
                                } else if (percent >= 25 && percent < 50) {
                                    textColor = '#28a745'; // green
                                } else {
                                    // Pink DIL: pink background with white text
                                    bgColor = '#e83e8c'; // pink background
                                    textColor = '#ffffff'; // white text
                                }

                                return `<div class="text-center"><span style="background-color: ${bgColor}; color: ${textColor}; font-weight: bold; padding: 2px 6px; border-radius: 3px; display: inline-block;">${Math.round(percent)}%</span></div>`;
                            }
                            return `<div class="text-center"><span style="color: #dc3545; font-weight: bold;">0%</span></div>`;
                        },
                        visible: false,
                        width: 60
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
                        hozAlign: "center",
                        visible: false,
                        width: 70
                    },
                    {
                        title: "NRA",
                        field: "NR",
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
                                            data-field="NR"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                    </select>
                                `;
                        },
                        hozAlign: "center",
                        visible: false,
                        width: 70
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName",
                        visible: false
                    },
                    {
                        title: "PRICE",
                        field: "price",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var price = parseFloat(cell.getValue() || 0);
                            return "$" + price.toFixed(2);
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "EBAY L30",
                        field: "ebay_l30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                    },
                    {
                        title: "VIEWS",
                        field: "views",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 70
                    },
                    {
                        title: "L7 VIEWS",
                        field: "l7_views",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 70
                    },
                    {
                        title: "CVR",
                        field: "cvr",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(1) + "%";
                        },
                        sorter: "number",
                        width: 70
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "center",
                        formatter: (cell) => parseFloat(cell.getValue() || 0),
                        sorter: "number",
                    },
                    {
                        title: "SBGT",
                        field: "suggestedBudget",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acosRaw = row.acos;
                            var acos = parseFloat(acosRaw);

                            // If acos is 0 (no sales or no ad fees), set it to 100 for budget calculation
                            // This matches the frontend logic: if(acos === 0) { acos = 100; }
                            if (isNaN(acos) || acos === 0) {
                                acos = 100;
                            }

                            // Calculate suggested budget based on ACOS rules:
                            // - If ACOS < 4% then budget = $9
                            // - If 4% ≤ ACOS < 8% then budget = $6
                            // - If ACOS ≥ 8% (including 100% for no sales) then budget = $3
                            var suggestedBudget = 0;
                            if (acos < 4) {
                                suggestedBudget = 9;
                            } else if (acos >= 4 && acos < 8) {
                                suggestedBudget = 6;
                            } else {
                                suggestedBudget = 3;
                            }

                            return suggestedBudget.toFixed(0);
                        },
                        sorter: "number",
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            // Check if spend (adFees) is 0
                            var adFees = parseFloat(row.adFees || 0);

                            // If spend is 0, show "-"
                            if (adFees === 0) {
                                var td = cell.getElement();
                                td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                                return '<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">' +
                                    '-' +
                                    '<i class="fa-solid fa-info-circle toggle-metrics-btn" style="cursor: pointer; font-size: 12px; margin-left: 5px;" title="Toggle Clicks, Spend, Ad Sold"></i></div>';
                            }

                            var acosRaw = row.acos;
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            var acosValue = "";
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                acosValue = "100%";
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosValue = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosValue = acos.toFixed(0) + "%";
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                                acosValue = acos.toFixed(0) + "%";
                            }

                            return '<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">' +
                                acosValue +
                                '<i class="fa-solid fa-info-circle toggle-metrics-btn" style="cursor: pointer; font-size: 12px; margin-left: 5px;" title="Toggle Clicks, Spend, Ad Sold"></i></div>';
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('toggle-metrics-btn') || e.target
                                .closest('.toggle-metrics-btn')) {
                                e.stopPropagation();
                                var clicksVisible = table.getColumn('clicks').isVisible();
                                var spendVisible = table.getColumn('adFees').isVisible();
                                var adSoldVisible = table.getColumn('ad_sold').isVisible();

                                if (clicksVisible || spendVisible || adSoldVisible) {
                                    table.hideColumn('clicks');
                                    table.hideColumn('adFees');
                                    table.hideColumn('ad_sold');
                                } else {
                                    table.showColumn('clicks');
                                    table.showColumn('adFees');
                                    table.showColumn('ad_sold');
                                }
                            }
                        },
                        width: 70
                    },
                    {
                        title: "CLICKS",
                        field: "clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        visible: false,
                        width: 80
                    },
                    {
                        title: "SPEND",
                        field: "adFees",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                        visible: false,
                        width: 80
                    },
                    {
                        title: "AD SOLD",
                        field: "ad_sold",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        visible: false,
                        width: 90
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb7 = parseFloat(aData.campaignBudgetAmount) > 0 ? (parseFloat(
                                aData.l7_spend || 0) / (parseFloat(aData
                                .campaignBudgetAmount) * 7)) * 100 : 0;
                            var bUb7 = parseFloat(bData.campaignBudgetAmount) > 0 ? (parseFloat(
                                bData.l7_spend || 0) / (parseFloat(bData
                                .campaignBudgetAmount) * 7)) * 100 : 0;
                            return aUb7 - bUb7;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');

                            // Different color logic based on utilization type
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: Check UB7 only
                                if (ub7 >= 66 && ub7 <= 99) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 99) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 66) {
                                    td.classList.add('red-bg');
                                }
                            } else {
                                // Under-utilized and Correctly-utilized: Only check UB7 (no ACOS check)
                                if (ub7 >= 66 && ub7 <= 99) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 99) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 66) {
                                    td.classList.add('red-bg');
                                }
                            }
                            return ub7.toFixed(0) + "%";
                        },
                        width: 70
                    },
                    {
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb1 = parseFloat(aData.campaignBudgetAmount) > 0 ? (parseFloat(
                                aData.l1_spend || 0) / parseFloat(aData
                                .campaignBudgetAmount)) * 100 : 0;
                            var bUb1 = parseFloat(bData.campaignBudgetAmount) > 0 ? (parseFloat(
                                bData.l1_spend || 0) / parseFloat(bData
                                .campaignBudgetAmount)) * 100 : 0;
                            return aUb1 - bUb1;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
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
                        },
                        width: 70
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "L BID",
                        field: "last_sbid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            // Check if value is null, 0, or 0.0
                            if (value === null || value === undefined || value === '' || value ===
                                0 || value === 0.0 || parseFloat(value) === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();

                            // Helper function to calculate SBID for a row
                            function calculateSbid(rowData) {
                                // Check if NRA (🔴) is selected
                                var nraValue = rowData.NR ? rowData.NR.trim() : "";
                                if (nraValue === 'NRA') {
                                    return -1; // Special value for sorting (will show as '-')
                                }

                                var l1Cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7Cpc = parseFloat(rowData.l7_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = budget > 0 ? (parseFloat(rowData.l7_spend) || 0) / (
                                    budget * 7) * 100 : 0;
                                var ub1 = budget > 0 ? (parseFloat(rowData.l1_spend) || 0) /
                                    budget * 100 : 0;
                                var sbid = 0;

                                // Helper function to get UB color
                                function getUbColor(ub) {
                                    if (ub >= 66 && ub <= 99) return 'green';
                                    if (ub > 99) return 'pink';
                                    return 'red';
                                }

                                // Check UB7 and UB1 colors (no exclusions)
                                var ub7Color = getUbColor(ub7);
                                var ub1Color = getUbColor(ub1);

                                // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                                // Note: Removed special case for ub7 === 0 && ub1 === 0 to allow UB1-based rules to apply
                                if (ub7 > 99 && ub1 > 99) {
                                    if (l1Cpc > 0) {
                                        return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    } else if (l7Cpc > 0) {
                                        return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    } else {
                                        return 0;
                                    }
                                }

                                if (currentUtilizationType === 'all') {
                                    // For total campaigns, determine individual campaign's utilization status
                                    var rowAcos = parseFloat(rowData.acos) || 0;
                                    if (isNaN(rowAcos) || rowAcos === 0) {
                                        rowAcos = 100;
                                    }

                                    var inv = parseFloat(rowData.INV || 0);
                                    // Determine utilization status
                                    var isOverUtilized = false;
                                    var isUnderUtilized = false;

                                    // Check over-utilized first
                                    if (ub7 > 99 && ub1 > 99) {
                                        isOverUtilized = true;
                                    }

                                    // Check under-utilized
                                    // Remove price >= 20 check to match backend command logic
                                    if (!isOverUtilized && ub7 < 66 && ub1 < 66 && inv > 0) {
                                        isUnderUtilized = true;
                                    }

                                    // Apply SBID logic based on determined status
                                    if (isOverUtilized) {
                                        // If L1 CPC > 1.25, then L1CPC * 0.80, else L1CPC * 0.90
                                        if (l1Cpc > 1.25) {
                                            sbid = Math.floor(l1Cpc * 0.80 * 100) / 100;
                                        } else if (l1Cpc > 0) {
                                            sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0;
                                        }

                                        // Price cap removed for PARENT SKUs (no price available)
                                    } else if (isUnderUtilized) {
                                        // New UB1-based bid increase rules
                                        // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
                                        var lastSbidRaw = rowData.last_sbid;
                                        var baseBid = 0;
                                        
                                        // Parse last_sbid, treat empty/0 as 0
                                        if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0) {
                                            baseBid = 0;
                                        } else {
                                            baseBid = parseFloat(lastSbidRaw);
                                            if (isNaN(baseBid)) {
                                                baseBid = 0;
                                            }
                                        }
                                        
                                        // If last_sbid is 0, use L1_CPC or L7_CPC as fallback
                                        if (baseBid === 0) {
                                            baseBid = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : 
                                                     ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                        }
                                        
                                        if (baseBid > 0) {
                                            // If UB1 < 33%: increase bid by 0.10
                                            if (ub1 < 33) {
                                                sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                            }
                                            // If UB1 is 33% to 66%: increase bid by 10%
                                            else if (ub1 >= 33 && ub1 < 66) {
                                                sbid = Math.floor((baseBid * 1.10) * 100) / 100;
                                            } else {
                                                // For UB1 >= 66%, use base bid (no increase)
                                                sbid = Math.floor(baseBid * 100) / 100;
                                            }
                                        } else {
                                            sbid = 0;
                                        }
                                    } else {
                                        // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                        if (l1Cpc > 0) {
                                            sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                        } else if (l7Cpc > 0) {
                                            sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0;
                                        }
                                    }
                                } else if (currentUtilizationType === 'over') {
                                    // Over-utilized: If L1 CPC > 1.25, then L1CPC * 0.80, else l1_cpc * 0.90, fallback to l7_cpc * 0.90, then 0.50
                                    if (l1Cpc > 1.25) {
                                        sbid = Math.floor(l1Cpc * 0.80 * 100) / 100;
                                    } else if (l1Cpc > 0) {
                                        sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    } else if (l7Cpc > 0) {
                                        sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50;
                                    }

                                    // Price cap removed for PARENT SKUs (no price available)
                                } else if (currentUtilizationType === 'under') {
                                    // New UB1-based bid increase rules
                                    // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
                                    var lastSbidRaw = rowData.last_sbid;
                                    var baseBid = 0;
                                    
                                    // Parse last_sbid, treat empty/0 as 0
                                    if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0) {
                                        baseBid = 0;
                                    } else {
                                        baseBid = parseFloat(lastSbidRaw);
                                        if (isNaN(baseBid)) {
                                            baseBid = 0;
                                        }
                                    }
                                    
                                    // If last_sbid is 0, use L1_CPC or L7_CPC as fallback
                                    if (baseBid === 0) {
                                        baseBid = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : 
                                                 ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                    }
                                    
                                    if (baseBid > 0) {
                                        // If UB1 < 33%: increase bid by 0.10
                                        if (ub1 < 33) {
                                            sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                        }
                                        // If UB1 is 33% to 66%: increase bid by 10%
                                        else if (ub1 >= 33 && ub1 < 66) {
                                            sbid = Math.floor((baseBid * 1.10) * 100) / 100;
                                        } else {
                                            // For UB1 >= 66%, use base bid (no increase)
                                            sbid = Math.floor(baseBid * 100) / 100;
                                        }
                                    } else {
                                        sbid = 0;
                                    }
                                } else {
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                }

                                // Check if SBID is 0
                                if (sbid === 0) {
                                    return -1; // Special value for sorting (will show as '-')
                                }

                                return sbid;
                            }

                            var aSbid = calculateSbid(aData);
                            var bSbid = calculateSbid(bData);

                            return aSbid - bSbid;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();

                            // Check if NRA (🔴) is selected
                            var nraValue = row.NR ? row.NR.trim() : "";
                            if (nraValue === 'NRA') {
                                return '-';
                            }

                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            var ub1 = budget > 0 ? (parseFloat(row.l1_spend) || 0) / budget * 100 :
                                0;

                            // Helper function to get UB color
                            function getUbColor(ub) {
                                if (ub >= 66 && ub <= 99) return 'green';
                                if (ub > 99) return 'pink';
                                return 'red';
                            }

                            var sbid = 0;

                            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                            // Note: Removed special case for ub7 === 0 && ub1 === 0 to allow UB1-based rules to apply
                            if (ub7 > 99 && ub1 > 99) {
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0;
                                }
                                // Check if SBID is 0
                                if (sbid === 0) {
                                    return '-';
                                }
                                return sbid.toFixed(2);
                            }

                            if (currentUtilizationType === 'all') {
                                // For total campaigns, determine individual campaign's utilization status
                                var rowAcos = parseFloat(row.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }

                                // Determine utilization status (same logic as combinedFilter)
                                var isOverUtilized = false;
                                var isUnderUtilized = false;

                                // Check over-utilized first (priority 1)
                                if (ub7 > 99 && ub1 > 99) {
                                    isOverUtilized = true;
                                }

                                // Check under-utilized (priority 2: only if not over-utilized)
                                var inv = parseFloat(row.INV || 0);
                                // Remove price >= 20 check to match backend command logic
                                if (!isOverUtilized && ub7 < 66 && ub1 < 66 && inv > 0) {
                                    isUnderUtilized = true;
                                }

                                // Apply SBID logic based on determined status
                                if (isOverUtilized) {
                                    // If L1 CPC > 1.25, then L1CPC * 0.80, else L1CPC * 0.90
                                    if (l1_cpc > 1.25) {
                                        sbid = Math.floor(l1_cpc * 0.80 * 100) / 100;
                                    } else if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }

                                    // Price cap removed for PARENT SKUs (no price available)
                                } else if (isUnderUtilized) {
                                    // New UB1-based bid increase rules
                                    // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
                                    var lastSbidRaw = row.last_sbid;
                                    var baseBid = 0;
                                    
                                    // Parse last_sbid, treat empty/0 as 0
                                    if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0) {
                                        baseBid = 0;
                                    } else {
                                        baseBid = parseFloat(lastSbidRaw);
                                        if (isNaN(baseBid)) {
                                            baseBid = 0;
                                        }
                                    }
                                    
                                    // If last_sbid is 0, use L1_CPC or L7_CPC as fallback
                                    if (baseBid === 0) {
                                        baseBid = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : 
                                                 ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                    }
                                    
                                    if (baseBid > 0) {
                                        // If UB1 < 33%: increase bid by 0.10
                                        if (ub1 < 33) {
                                            sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                        }
                                        // If UB1 is 33% to 66%: increase bid by 10%
                                        else if (ub1 >= 33 && ub1 < 66) {
                                            sbid = Math.floor((baseBid * 1.10) * 100) / 100;
                                        } else {
                                            // For UB1 >= 66%, use base bid (no increase)
                                            sbid = Math.floor(baseBid * 100) / 100;
                                        }
                                    } else {
                                        sbid = 0;
                                    }
                                } else {
                                    // Correctly-utilized or other: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }
                                }
                            } else if (currentUtilizationType === 'over') {
                                // Over-utilized: If L1 CPC > 1.25, then L1CPC * 0.80, else l1_cpc * 0.90, fallback to l7_cpc * 0.90, then 0.50
                                if (l1_cpc > 1.25) {
                                    sbid = Math.floor(l1_cpc * 0.80 * 100) / 100;
                                } else if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0.50;
                                }

                                // Price cap removed for PARENT SKUs (no price available)
                            } else if (currentUtilizationType === 'under') {
                                // New UB1-based bid increase rules
                                // Get base bid from last_sbid, fallback to L1_CPC or L7_CPC if last_sbid is 0
                                var lastSbidRaw = row.last_sbid;
                                var baseBid = 0;
                                
                                // Parse last_sbid, treat empty/0 as 0
                                if (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0' || lastSbidRaw === 0) {
                                    baseBid = 0;
                                } else {
                                    baseBid = parseFloat(lastSbidRaw);
                                    if (isNaN(baseBid)) {
                                        baseBid = 0;
                                    }
                                }
                                
                                // If last_sbid is 0, use L1_CPC or L7_CPC as fallback
                                if (baseBid === 0) {
                                    baseBid = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : 
                                             ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                }
                                
                                if (baseBid > 0) {
                                    // If UB1 < 33%: increase bid by 0.10
                                    if (ub1 < 33) {
                                        sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                    }
                                    // If UB1 is 33% to 66%: increase bid by 10%
                                    else if (ub1 >= 33 && ub1 < 66) {
                                        sbid = Math.floor((baseBid * 1.10) * 100) / 100;
                                    } else {
                                        // For UB1 >= 66%, use base bid (no increase)
                                        sbid = Math.floor(baseBid * 100) / 100;
                                    }
                                } else {
                                    sbid = 0;
                                }
                            } else {
                                // Correctly-utilized: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0;
                                }
                            }

                            // Check if SBID is 0
                            if (sbid === 0) {
                                return '-';
                            }

                            return sbid.toFixed(2);
                        },
                        visible: true,
                        width: 70
                    },
                    {
                        title: "SBID M",
                        field: "sbid_m",
                        hozAlign: "center",
                        editor: "input",
                        editorParams: {
                            elementAttributes: {
                                maxlength: "10"
                            }
                        },
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: true,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var apprSbid = rowData.apprSbid || '';

                            if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) {
                                // Green checkmark circle icon when bid is pushed
                                return `
                                    <div style="display: flex; justify-content: center; align-items: center;">
                                        <i class="fa-solid fa-circle-check update-bid-icon" style="color: #28a745; font-size: 20px; cursor: default;" title="Bid pushed: ${apprSbid}"></i>
                                </div>
                            `;
                            } else {
                                // Check icon (clickable) when bid is not pushed
                                return `
                                    <div style="display: flex; justify-content: center; align-items: center;">
                                        <i class="fa-solid fa-check update-bid-icon" style="color: #6c757d; font-size: 18px; cursor: pointer;" title="Click to push bid"></i>
                                    </div>
                                `;
                            }
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-bid-icon") || e.target.closest(
                                    ".update-bid-icon")) {
                                var rowData = cell.getRow().getData();

                                // Check if bid is already pushed
                                var apprSbid = rowData.apprSbid || '';
                                if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) {
                                    return; // Don't allow re-push
                                }

                                // Check if NRA (🔴) is selected
                                var nraValue = rowData.NR ? rowData.NR.trim() : "";
                                if (nraValue === 'NRA') {
                                    showToast('error', 'Cannot update bid for NRA campaigns');
                                    return; // Skip update if NRA is selected
                                }

                                // Get sbid_m value (saved value)
                                var sbidM = parseFloat(rowData.sbid_m) || 0;

                                if (sbidM <= 0) {
                                    showToast('error',
                                        'SBID M value is required. Please save SBID M first.');
                                    return;
                                }

                                if (!rowData.campaign_id) {
                                    showToast('error', 'Campaign ID not found');
                                    return;
                                }

                                // Use sbid_m value to update eBay site
                                updateBid(sbidM, rowData.campaign_id, cell);
                            }
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var campaignId = row.campaign_id || '';
                            var status = row.campaignStatus || 'PAUSED';
                            var isEnabled = status === 'RUNNING';
                            
                            // Check if campaignId is empty, null, or undefined
                            if (!campaignId || campaignId === '' || campaignId === null || campaignId === undefined) {
                                return '<span style="color: #999;">-</span>';
                            }
                            
                            return `
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input campaign-status-toggle" 
                                           type="checkbox" 
                                           role="switch" 
                                           data-campaign-id="${campaignId}"
                                           ${isEnabled ? 'checked' : ''}
                                           style="cursor: pointer; width: 3rem; height: 1.5rem;">
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            // Prevent default to handle toggle manually
                            if (e.target.classList.contains('campaign-status-toggle')) {
                                e.stopPropagation();
                            }
                        },
                        width: 80
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;
                    totalSkuCountFromBackend = parseFloat(response.total_sku_count) || 0;
                    ebaySkuCountFromBackend = parseFloat(response.ebay_sku_count) || 0;

                    // Get L30 totals from backend (L30 report_range data)
                    totalL30ClicksFromBackend = parseInt(response.total_l30_clicks || 0);
                    totalL30SpendFromBackend = parseFloat(response.total_l30_spend || 0);
                    totalL30AdSoldFromBackend = parseInt(response.total_l30_ad_sold || 0);

                    // Update average ACOS and CVR from backend
                    const avgAcosEl = document.getElementById('avg-acos');
                    if (avgAcosEl) {
                        avgAcosEl.textContent = (parseFloat(response.avg_acos) || 0).toFixed(2) + '%';
                    }

                    const avgCvrEl = document.getElementById('avg-cvr');
                    if (avgCvrEl) {
                        avgCvrEl.textContent = (parseFloat(response.avg_cvr) || 0).toFixed(2) + '%';
                    }

                    // Update L30 totals after data is loaded
                    setTimeout(function() {
                        updateL30Totals();
                    }, 100);

                    // Update eBay SKU count
                    const ebaySkuCountEl = document.getElementById('ebay-sku-count');
                    if (ebaySkuCountEl) {
                        ebaySkuCountEl.textContent = ebaySkuCountFromBackend;
                    }
                    
                    // Automatically store statistics in database
                    storeStatistics();
                    
                    return response.data;
                }
            });

            // Combined filter function
            function combinedFilter(data) {
                // Apply missing/campaign/zero INV/NRA/RA filters first (if enabled)
                if (showMissingOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    if (hasCampaign) return false;
                    // Check if this is a red dot (missing AND not yellow)
                    let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                    let rowNraForMissing = data.NR ? data.NR.trim() : "";
                    // Only show as missing (red dot) if neither NRL='NRL' nor NRA='NRA'
                    if (rowNrlForMissing === 'NRL' || rowNraForMissing === 'NRA') return false;
                }

                if (showNraMissingOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    if (hasCampaign) return false;
                    // Show only NRA missing (yellow dots)
                    // If NRL='NRL', NRA should be 'NRA', so show it as NRA missing too
                    let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                    let rowNraForMissing = data.NR ? data.NR.trim() : "";
                    // Show if NRL='NRL' OR NRA='NRA' (because NRL='NRL' means NRA should be 'NRA')
                    if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') return false;
                }

                if (showNrlMissingOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    if (hasCampaign) return false;
                    // Show only NRL missing (yellow dots)
                    let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                    if (rowNrlForMissing !== 'NRL') return false;
                }

                if (showCampaignOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    if (!hasCampaign) return false;
                }

                // eBay SKU filter - show only SKUs that have campaign or price > 0 (eBay listing exists)
                if (showEbaySkuOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    const price = parseFloat(data.price || 0);
                    // Show if has campaign OR has price (eBay listing exists)
                    if (!hasCampaign && price <= 0) return false;
                }

                // Pink DIL Paused filter - show only campaigns that were paused by pink DIL cron
                if (showPinkDilPausedOnly) {
                    const pinkDilPausedAt = data.pink_dil_paused_at;
                    if (!pinkDilPausedAt) return false; // Show only if pink_dil_paused_at is not null/empty
                }

                // Global search filter
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku
                        ?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // Status filter
                let statusVal = $("#status-filter").val();
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
                }

                // Apply zero INV filter first (if enabled)
                let inv = parseFloat(data.INV || 0);
                if (showZeroInvOnly) {
                    // Show only zero or negative inventory
                    if (inv > 0) return false;
                } else {
                    // Inventory filter - Default to INV > 0 (exclude INV = 0 and negative)
                    let invFilterVal = $("#inv-filter").val();

                    // By default (no filter selected), show only INV > 0 (exclude INV = 0 and negative)
                    if (!invFilterVal || invFilterVal === '') {
                        if (inv <= 0) return false;
                    } else if (invFilterVal === "ALL") {
                        // ALL option shows everything (including INV = 0 and negative), so no filtering needed
                    } else if (invFilterVal === "INV_0") {
                        // Show only INV = 0
                        if (inv !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        // Show only INV > 0 (exclude INV = 0 and negative)
                        if (inv <= 0) return false;
                    }
                }

                // Apply NRA/RA filters first (if enabled)
                // Note: Empty/null NRA defaults to "RA" in the display
                let rowNra = data.NR ? data.NR.trim() : "";
                if (showNraOnly) {
                    // Show only NRA (explicitly "NRA" only)
                    if (rowNra !== 'NRA') return false;
                } else if (showRaOnly) {
                    // Show only RA (includes empty/null which defaults to "RA")
                    if (rowNra === 'NRA') return false;
                } else {
                    // NRA filter from dropdown
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        if (nraFilterVal === 'RA') {
                            // For "RA" filter, include empty/null values too
                            if (rowNra === 'NRA') return false;
                        } else {
                            // For "NRA" or "LATER", exact match
                            if (rowNra !== nraFilterVal) return false;
                        }
                    }
                }

                // Apply NRL/REQ filters first (if enabled)
                // Note: Empty/null NRL defaults to "REQ" in the display
                let rowNrl = data.NRL ? data.NRL.trim() : "";
                if (showNrlOnly) {
                    // Show only NRL (explicitly "NRL" only)
                    if (rowNrl !== 'NRL') return false;
                } else {
                    // NRL filter from dropdown
                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        if (nrlFilterVal === 'REQ') {
                            // For "REQ" filter, include empty/null values too
                            if (rowNrl === 'NRL') return false;
                        } else {
                            // For "NRL", exact match
                            if (rowNrl !== nrlFilterVal) return false;
                        }
                    }
                }

                // SBID M filter
                let sbidMFilterVal = $("#sbid-m-filter").val();
                if (sbidMFilterVal && sbidMFilterVal !== '') {
                    let rowSbidM = data.sbid_m;

                    // Check if sbid_m is blank/null/empty/0
                    let isBlank = false;

                    // Check for null, undefined, or empty string
                    if (rowSbidM === null || rowSbidM === undefined || rowSbidM === '') {
                        isBlank = true;
                    } else {
                        // Convert to string and trim
                        let strValue = String(rowSbidM).trim();

                        // Check for string representations of zero/empty
                        if (strValue === '' || strValue === '0' || strValue === '0.0' || strValue === '0.00' ||
                            strValue === '0.000' || strValue === '-') {
                            isBlank = true;
                        } else {
                            // Try parsing as number
                            let numValue = parseFloat(strValue);
                            if (isNaN(numValue) || numValue === 0 || numValue <= 0) {
                                isBlank = true;
                            }
                        }
                    }

                    // Apply filter
                    if (sbidMFilterVal === 'blank' && !isBlank) {
                        return false; // Filter out non-blank values
                    } else if (sbidMFilterVal === 'data' && isBlank) {
                        return false; // Filter out blank values
                    }
                }

                // Exclude missing rows (rows without campaigns) when utilization type is over, under, or correctly
                // This includes both red dots (missing) and yellow dots (NRA/NRL missing)
                if (currentUtilizationType === 'over' || currentUtilizationType === 'under' ||
                    currentUtilizationType === 'correctly') {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    // Check for yellow dots (NRA/NRL missing) - these are also missing rows
                    let rowNrl = data.NRL ? data.NRL.trim() : "";
                    let rowNra = data.NR ? data.NR.trim() : "";
                    // Exclude if no campaign OR if it's a yellow dot (NRL='NRL' or NRA='NRA')
                    if (!hasCampaign || rowNrl === 'NRL' || rowNra === 'NRA') {
                        return false; // Hide missing rows (both red and yellow dots)
                    }
                }

                // Apply utilization type filter
                // Only calculate utilization for rows with campaigns (already checked above)
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend || 0);
                let l1_spend = parseFloat(data.l1_spend || 0);
                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                if (currentUtilizationType === 'all') {
                    // All - no utilization filter
                } else if (currentUtilizationType === 'over') {
                    // Over utilized: ub7 > 99 && ub1 > 99
                    if (!(ub7 > 99 && ub1 > 99)) {
                        return false;
                    }
                } else if (currentUtilizationType === 'under') {
                    // For under utilized, check ub7 < 66 && ub1 < 66 && INV > 0 (matches count logic)
                    let inv = parseFloat(data.INV || 0);
                    if (!(ub7 < 66 && ub1 < 66 && inv > 0)) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Strict criteria: both UB7 and UB1 must be between 66% and 99% (both green)
                    if (!((ub7 >= 66 && ub7 <= 99) && (ub1 >= 66 && ub1 <= 99))) return false;
                }

                // Apply all range filters (multiple filters can be active simultaneously)
                // 1UB% filter
                if (rangeFilters['1ub'].min !== null || rangeFilters['1ub'].max !== null) {
                    if (rangeFilters['1ub'].min !== null && ub1 < rangeFilters['1ub'].min) {
                        return false;
                    }
                    if (rangeFilters['1ub'].max !== null && ub1 > rangeFilters['1ub'].max) {
                        return false;
                    }
                }

                // 7UB% filter
                if (rangeFilters['7ub'].min !== null || rangeFilters['7ub'].max !== null) {
                    if (rangeFilters['7ub'].min !== null && ub7 < rangeFilters['7ub'].min) {
                        return false;
                    }
                    if (rangeFilters['7ub'].max !== null && ub7 > rangeFilters['7ub'].max) {
                        return false;
                    }
                }

                // LBid filter
                if (rangeFilters['lbid'].min !== null || rangeFilters['lbid'].max !== null) {
                    let lbidRaw = data.last_sbid;
                    let lbidValue = 0;
                    // Parse LBid, treat empty/0 as 0
                    if (!lbidRaw || lbidRaw === '' || lbidRaw === '0' || lbidRaw === 0) {
                        lbidValue = 0;
                    } else {
                        lbidValue = parseFloat(lbidRaw);
                        if (isNaN(lbidValue)) {
                            lbidValue = 0;
                        }
                    }

                    if (rangeFilters['lbid'].min !== null && lbidValue < rangeFilters['lbid'].min) {
                        return false;
                    }
                    if (rangeFilters['lbid'].max !== null && lbidValue > rangeFilters['lbid'].max) {
                        return false;
                    }
                }

                // Acos filter
                if (rangeFilters['acos'].min !== null || rangeFilters['acos'].max !== null) {
                    let acosRaw = data.acos;
                    let acosValue = parseFloat(acosRaw);
                    if (isNaN(acosValue) || acosValue === 0) {
                        acosValue = 100; // Treat 0 ACOS as 100%
                    }

                    if (rangeFilters['acos'].min !== null && acosValue < rangeFilters['acos'].min) {
                        return false;
                    }
                    if (rangeFilters['acos'].max !== null && acosValue > rangeFilters['acos'].max) {
                        return false;
                    }
                }
                
                // Views filter
                if (rangeFilters['views'].min !== null || rangeFilters['views'].max !== null) {
                    let viewsRaw = data.views;
                    let viewsValue = parseFloat(viewsRaw) || 0;
                    if (isNaN(viewsValue)) {
                        viewsValue = 0;
                    }
                    
                    if (rangeFilters['views'].min !== null && viewsValue < rangeFilters['views'].min) {
                        return false;
                    }
                    if (rangeFilters['views'].max !== null && viewsValue > rangeFilters['views'].max) {
                        return false;
                    }
                }
                
                // L7 Views filter
                if (rangeFilters['l7_views'].min !== null || rangeFilters['l7_views'].max !== null) {
                    let l7ViewsRaw = data.l7_views;
                    let l7ViewsValue = parseFloat(l7ViewsRaw) || 0;
                    if (isNaN(l7ViewsValue)) {
                        l7ViewsValue = 0;
                    }
                    
                    if (rangeFilters['l7_views'].min !== null && l7ViewsValue < rangeFilters['l7_views'].min) {
                        return false;
                    }
                    if (rangeFilters['l7_views'].max !== null && l7ViewsValue > rangeFilters['l7_views'].max) {
                        return false;
                    }
                }

                return true;
            }

            table.on("tableBuilt", function() {
                updateL30Totals();
                table.setFilter(combinedFilter);

                // Set initial column visibility based on current utilization type
                if (currentUtilizationType === 'correctly') {
                    table.hideColumn('sbid');
                } else {
                    table.showColumn('sbid');
                }
                // Show APR BID column
                table.showColumn('apr_bid');

                // Add click handler for INV "i" button to toggle L30, DIL%, NRL, NRA columns
                setTimeout(function() {
                    var invToggleBtn = document.querySelector('.toggle-inv-cols-btn');
                    if (invToggleBtn) {
                        invToggleBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            // Toggle visibility of L30, DIL%, NRL, NRA columns
                            var l30Col = table.getColumn('L30');
                            var dilCol = table.getColumn('DIL %');
                            var nrlCol = table.getColumn('NRL');
                            var nraCol = table.getColumn('NR');

                            if (l30Col && dilCol && nrlCol && nraCol) {
                                var isL30Visible = l30Col.isVisible();
                                // Toggle all columns
                                if (isL30Visible) {
                                    l30Col.hide();
                                    dilCol.hide();
                                    nrlCol.hide();
                                    nraCol.hide();
                                } else {
                                    l30Col.show();
                                    dilCol.show();
                                    nrlCol.show();
                                    nraCol.show();
                                }
                            }
                        });
                    }
                }, 100);

                // Add click handler for toggle metrics button
                setTimeout(function() {
                    var acosHeader = document.querySelector(
                        '.tabulator-col[data-field="acos"] .toggle-metrics-btn');
                    if (acosHeader) {
                        acosHeader.addEventListener('click', function(e) {
                            e.stopPropagation();
                            var clicksVisible = table.getColumn('clicks').isVisible();
                            var spendVisible = table.getColumn('adFees').isVisible();
                            var adSoldVisible = table.getColumn('ad_sold').isVisible();

                            if (clicksVisible || spendVisible || adSoldVisible) {
                                table.hideColumn('clicks');
                                table.hideColumn('adFees');
                                table.hideColumn('ad_sold');
                            } else {
                                table.showColumn('clicks');
                                table.showColumn('adFees');
                                table.showColumn('ad_sold');
                            }
                        });
                    }
                }, 100);

                // Update counts when data is filtered (debounced)
                let filterTimeout = null;
                table.on("dataFiltered", function(filteredRows) {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                        updateL30Totals();
                        updatePaginationCount();
                    }, 300);
                });

                // Update pagination count on page changes
                table.on("pageLoaded", function() {
                    updatePaginationCount();
                    // Update header checkbox state when page changes
                    setTimeout(updateHeaderCheckboxState, 50);
                });

                table.on("dataProcessed", function() {
                    setTimeout(updatePaginationCount, 100);
                });

                // Debounced search
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nra-filter, #nrl-filter, #sbid-m-filter").on("change",
                    function() {
                        table.setFilter(combinedFilter);
                        // Update counts when filter changes - use longer timeout to ensure filter is applied
                        setTimeout(function() {
                            updateButtonCounts();
                            updateL30Totals();
                            updatePaginationCount();
                        }, 300);
                    });

                // Multi range filter handlers - apply all filters simultaneously
                $("#apply-all-range-filters-btn").on("click", function() {
                    let hasError = false;

                    // Get and validate 1UB% filter
                    let ub1Min = $("#range-filter-1ub-min").val();
                    let ub1Max = $("#range-filter-1ub-max").val();
                    rangeFilters['1ub'].min = ub1Min !== '' ? parseFloat(ub1Min) : null;
                    rangeFilters['1ub'].max = ub1Max !== '' ? parseFloat(ub1Max) : null;
                    if (rangeFilters['1ub'].min !== null && rangeFilters['1ub'].max !== null &&
                        rangeFilters['1ub'].min > rangeFilters['1ub'].max) {
                        alert('1UB%: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }

                    // Get and validate 7UB% filter
                    let ub7Min = $("#range-filter-7ub-min").val();
                    let ub7Max = $("#range-filter-7ub-max").val();
                    rangeFilters['7ub'].min = ub7Min !== '' ? parseFloat(ub7Min) : null;
                    rangeFilters['7ub'].max = ub7Max !== '' ? parseFloat(ub7Max) : null;
                    if (rangeFilters['7ub'].min !== null && rangeFilters['7ub'].max !== null &&
                        rangeFilters['7ub'].min > rangeFilters['7ub'].max) {
                        alert('7UB%: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }

                    // Get and validate LBid filter
                    let lbidMin = $("#range-filter-lbid-min").val();
                    let lbidMax = $("#range-filter-lbid-max").val();
                    rangeFilters['lbid'].min = lbidMin !== '' ? parseFloat(lbidMin) : null;
                    rangeFilters['lbid'].max = lbidMax !== '' ? parseFloat(lbidMax) : null;
                    if (rangeFilters['lbid'].min !== null && rangeFilters['lbid'].max !== null &&
                        rangeFilters['lbid'].min > rangeFilters['lbid'].max) {
                        alert('LBid: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }

                    // Get and validate Acos filter
                    let acosMin = $("#range-filter-acos-min").val();
                    let acosMax = $("#range-filter-acos-max").val();
                    rangeFilters['acos'].min = acosMin !== '' ? parseFloat(acosMin) : null;
                    rangeFilters['acos'].max = acosMax !== '' ? parseFloat(acosMax) : null;
                    if (rangeFilters['acos'].min !== null && rangeFilters['acos'].max !== null &&
                        rangeFilters['acos'].min > rangeFilters['acos'].max) {
                        alert('Acos: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }
                    
                    // Get and validate Views filter
                    let viewsMin = $("#range-filter-views-min").val();
                    let viewsMax = $("#range-filter-views-max").val();
                    rangeFilters['views'].min = viewsMin !== '' ? parseFloat(viewsMin) : null;
                    rangeFilters['views'].max = viewsMax !== '' ? parseFloat(viewsMax) : null;
                    if (rangeFilters['views'].min !== null && rangeFilters['views'].max !== null &&
                        rangeFilters['views'].min > rangeFilters['views'].max) {
                        alert('Views: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }
                    
                    // Get and validate L7 Views filter
                    let l7ViewsMin = $("#range-filter-l7-views-min").val();
                    let l7ViewsMax = $("#range-filter-l7-views-max").val();
                    rangeFilters['l7_views'].min = l7ViewsMin !== '' ? parseFloat(l7ViewsMin) : null;
                    rangeFilters['l7_views'].max = l7ViewsMax !== '' ? parseFloat(l7ViewsMax) : null;
                    if (rangeFilters['l7_views'].min !== null && rangeFilters['l7_views'].max !== null &&
                        rangeFilters['l7_views'].min > rangeFilters['l7_views'].max) {
                        alert('L7 Views: Minimum value cannot be greater than maximum value');
                        hasError = true;
                    }

                    if (hasError) {
                        return;
                    }

                    // Apply all filters
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                        updateL30Totals();
                        updatePaginationCount();
                    }, 300);
                });

                $("#clear-all-range-filters-btn").on("click", function() {
                    // Clear all filter values
                    rangeFilters['1ub'].min = null;
                    rangeFilters['1ub'].max = null;
                    rangeFilters['7ub'].min = null;
                    rangeFilters['7ub'].max = null;
                    rangeFilters['lbid'].min = null;
                    rangeFilters['lbid'].max = null;
                    rangeFilters['acos'].min = null;
                    rangeFilters['acos'].max = null;
                    rangeFilters['views'].min = null;
                    rangeFilters['views'].max = null;
                    rangeFilters['l7_views'].min = null;
                    rangeFilters['l7_views'].max = null;

                    // Clear all input fields
                    $("#range-filter-1ub-min").val('');
                    $("#range-filter-1ub-max").val('');
                    $("#range-filter-7ub-min").val('');
                    $("#range-filter-7ub-max").val('');
                    $("#range-filter-lbid-min").val('');
                    $("#range-filter-lbid-max").val('');
                    $("#range-filter-acos-min").val('');
                    $("#range-filter-acos-max").val('');
                    $("#range-filter-views-min").val('');
                    $("#range-filter-views-max").val('');
                    $("#range-filter-l7-views-min").val('');
                    $("#range-filter-l7-views-max").val('');

                    // Apply cleared filters
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                        updateL30Totals();
                        updatePaginationCount();
                    }, 300);
                });

                // Auto-apply filters when input values change (debounced)
                let rangeFilterTimeout = null;

                function applyRangeFiltersOnChange() {
                    if (rangeFilterTimeout) clearTimeout(rangeFilterTimeout);
                    rangeFilterTimeout = setTimeout(function() {
                        // Update filter values from inputs
                        let ub1Min = $("#range-filter-1ub-min").val();
                        let ub1Max = $("#range-filter-1ub-max").val();
                        rangeFilters['1ub'].min = ub1Min !== '' ? parseFloat(ub1Min) : null;
                        rangeFilters['1ub'].max = ub1Max !== '' ? parseFloat(ub1Max) : null;

                        let ub7Min = $("#range-filter-7ub-min").val();
                        let ub7Max = $("#range-filter-7ub-max").val();
                        rangeFilters['7ub'].min = ub7Min !== '' ? parseFloat(ub7Min) : null;
                        rangeFilters['7ub'].max = ub7Max !== '' ? parseFloat(ub7Max) : null;

                        let lbidMin = $("#range-filter-lbid-min").val();
                        let lbidMax = $("#range-filter-lbid-max").val();
                        rangeFilters['lbid'].min = lbidMin !== '' ? parseFloat(lbidMin) : null;
                        rangeFilters['lbid'].max = lbidMax !== '' ? parseFloat(lbidMax) : null;

                        let acosMin = $("#range-filter-acos-min").val();
                        let acosMax = $("#range-filter-acos-max").val();
                        rangeFilters['acos'].min = acosMin !== '' ? parseFloat(acosMin) : null;
                        rangeFilters['acos'].max = acosMax !== '' ? parseFloat(acosMax) : null;
                        
                        let viewsMin = $("#range-filter-views-min").val();
                        let viewsMax = $("#range-filter-views-max").val();
                        rangeFilters['views'].min = viewsMin !== '' ? parseFloat(viewsMin) : null;
                        rangeFilters['views'].max = viewsMax !== '' ? parseFloat(viewsMax) : null;
                        
                        let l7ViewsMin = $("#range-filter-l7-views-min").val();
                        let l7ViewsMax = $("#range-filter-l7-views-max").val();
                        rangeFilters['l7_views'].min = l7ViewsMin !== '' ? parseFloat(l7ViewsMin) : null;
                        rangeFilters['l7_views'].max = l7ViewsMax !== '' ? parseFloat(l7ViewsMax) : null;

                        // Apply filters (skip validation for auto-apply)
                        table.setFilter(combinedFilter);
                        setTimeout(function() {
                            updateButtonCounts();
                            updateL30Totals();
                            updatePaginationCount();
                        }, 300);
                    }, 500); // 500ms debounce
                }

                // Add change event listeners to all range filter inputs
                $("#range-filter-1ub-min, #range-filter-1ub-max, #range-filter-7ub-min, #range-filter-7ub-max, #range-filter-lbid-min, #range-filter-lbid-max, #range-filter-acos-min, #range-filter-acos-max, #range-filter-views-min, #range-filter-views-max, #range-filter-l7-views-min, #range-filter-l7-views-max")
                    .on("input change", function() {
                        applyRangeFiltersOnChange();
                    });

                // INC/DEC SBID handlers
                // Dropdown selection handler
                $("#inc-dec-dropdown .dropdown-item").on("click", function(e) {
                    e.preventDefault();
                    incDecType = $(this).data('type');
                    var labelText = incDecType === 'value' ? 'Value (e.g., +0.5 or -0.5)' :
                        'Percentage (e.g., +10 or -10)';
                    $("#inc-dec-label").text(incDecType === 'value' ? 'Value' : 'Percentage');
                    $("#inc-dec-input").attr('placeholder', labelText);
                    $("#inc-dec-btn").text(incDecType === 'value' ? 'INC/DEC (By Value)' :
                        'INC/DEC (By %)');
                });

                // Helper function to get L Bid (last_sbid) value for a row - used as base value for INC/DEC
                function getCurrentSbid(rowData) {
                    // Use L Bid (last_sbid) as the base value
                    var lastSbid = rowData.last_sbid;

                    // Check if L Bid is empty, null, 0, or invalid
                    if (!lastSbid || lastSbid === '' || lastSbid === '0' || lastSbid === 0) {
                        return null; // No L Bid value available
                    }

                    var sbidValue = parseFloat(lastSbid);
                    if (isNaN(sbidValue) || sbidValue <= 0) {
                        return null;
                    }

                    return sbidValue;
                }

                // Apply INC/DEC button handler
                $("#apply-inc-dec-btn").on("click", function() {
                    var inputValue = $("#inc-dec-input").val();
                    if (!inputValue || inputValue === '') {
                        alert('Please enter a value');
                        return;
                    }

                    var incDecValue = parseFloat(inputValue);
                    if (isNaN(incDecValue)) {
                        alert('Please enter a valid number');
                        return;
                    }

                    // Get only selected rows
                    var selectedRows = table.getRows('selected');
                    if (selectedRows.length === 0) {
                        showToast('warning',
                            'Please select at least one row to apply increment/decrement');
                        return;
                    }

                    // Prepare data for bulk save
                    var campaignSbidMap = {}; // { campaign_id: new_sbid_m }
                    var rowsToUpdate = []; // Store rows for later update

                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var campaignId = rowData.campaign_id;

                        // Skip rows without campaign_id
                        if (!campaignId) {
                            return;
                        }

                        // Get L Bid (last_sbid) as base value
                        var currentLbid = getCurrentSbid(rowData);
                        if (currentLbid === null || currentLbid === 0) {
                            return; // Skip rows with no L Bid value
                        }

                        // Calculate new SBID based on L Bid and INC/DEC type
                        var newSbid = 0;
                        if (incDecType === 'value') {
                            // By value: new = L Bid + input
                            newSbid = currentLbid + incDecValue;
                        } else {
                            // By percentage: new = L Bid * (1 + input/100)
                            newSbid = currentLbid * (1 + incDecValue / 100);
                        }

                        // Ensure new SBID is not negative
                        if (newSbid < 0) {
                            newSbid = 0;
                        }

                        // Round to 2 decimal places
                        newSbid = Math.round(newSbid * 100) / 100;

                        // Store campaign ID and calculated SBID
                        campaignSbidMap[campaignId] = newSbid;
                        rowsToUpdate.push({
                            row: row,
                            campaignId: campaignId,
                            newSbid: newSbid
                        });
                    });

                    if (Object.keys(campaignSbidMap).length === 0) {
                        showToast('warning',
                            'No selected rows with valid L Bid and campaign ID found');
                        return;
                    }

                    // Show progress overlay
                    const overlay = document.getElementById("progress-overlay");
                    overlay.style.display = "flex";

                    // Prepare campaign IDs and SBID M values arrays
                    var campaignIds = Object.keys(campaignSbidMap);

                    // Save all calculated values individually for each campaign
                    var savePromises = [];
                    var campaignRowMap = {}; // Map campaign ID to row info for easy lookup

                    rowsToUpdate.forEach(function(rowInfo) {
                        campaignRowMap[rowInfo.campaignId] = rowInfo;
                    });

                    campaignIds.forEach(function(campaignId) {
                        var newSbidValue = campaignSbidMap[campaignId];
                        var savePromise = $.ajax({
                            url: '/save-ebay3-sbid-m',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr(
                                    'content')
                            },
                            data: {
                                campaign_id: campaignId,
                                sbid_m: newSbidValue
                            }
                        }).then(function(response) {
                            return {
                                campaignId: campaignId,
                                response: response,
                                success: true
                            };
                        }).catch(function(error) {
                            return {
                                campaignId: campaignId,
                                error: error,
                                success: false
                            };
                        });
                        savePromises.push(savePromise);
                    });

                    // Wait for all saves to complete
                    Promise.all(savePromises).then(function(results) {
                        var successCount = 0;
                        var errorCount = 0;

                        results.forEach(function(result) {
                            if (result.success && result.response && result.response
                                .status === 200) {
                                successCount++;
                                // Update row data using campaign ID to find the correct row
                                var rowInfo = campaignRowMap[result.campaignId];
                                if (rowInfo) {
                                    var rowData = rowInfo.row.getData();
                                    var currentData = JSON.parse(JSON.stringify(
                                        rowData));
                                    currentData.sbid_m = rowInfo.newSbid;
                                    currentData.apprSbid =
                                    ''; // Clear apprSbid when sbid_m is updated
                                    rowInfo.row.update(currentData);
                                    setTimeout(function() {
                                        rowInfo.row.reformat();
                                    }, 50);
                                }
                            } else {
                                errorCount++;
                                console.error('Error saving SBID M for campaign:',
                                    result.campaignId, result.error || result
                                    .response);
                            }
                        });

                        overlay.style.display = "none";

                        if (successCount > 0) {
                            showToast('success', 'SBID M saved successfully for ' +
                                successCount + ' campaign(s)');
                            // Redraw table to ensure all updates are visible
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to save SBID M values');
                        }

                        if (errorCount > 0) {
                            console.warn('Some campaigns failed to save:', errorCount);
                        }
                    }).catch(function(error) {
                        overlay.style.display = "none";
                        showToast('error', 'Error saving SBID M values');
                        console.error('Error in bulk save:', error);
                    });
                });

                // Clear INC/DEC button handler
                $("#clear-inc-dec-btn").on("click", function() {
                    // Clear input field only - sbid_m values remain in database
                    $("#inc-dec-input").val('');
                    showToast('info', 'Input cleared. SBID M values remain saved in database.');
                });

                // Clear SBID M button handler - clears sbid_m for selected rows
                // Attach handler using event delegation to ensure it works
                $(document).on("click", "#clear-sbid-m-btn", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Clear SBID M button clicked');

                    // Check if table is available
                    if (typeof table === 'undefined' || !table) {
                        showToast('error', 'Table not initialized yet. Please wait.');
                        return;
                    }

                    // Get only selected rows
                    var selectedRows = table.getRows('selected');
                    console.log('Selected rows:', selectedRows.length);

                    if (selectedRows.length === 0) {
                        showToast('warning', 'Please select at least one row to clear SBID M');
                        return;
                    }

                    // Confirm before clearing
                    var confirmClear = confirm('Are you sure you want to clear SBID M for ' +
                        selectedRows.length + ' selected row(s)?');
                    if (!confirmClear) {
                        return;
                    }

                    // Show progress overlay
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }

                    // Prepare campaign IDs and rows to update
                    var campaignIds = [];
                    var campaignRowMap = {};

                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var campaignId = rowData.campaign_id;

                        // Skip rows without campaign_id
                        if (!campaignId) {
                            return;
                        }

                        campaignIds.push(String(campaignId).trim());
                        campaignRowMap[campaignId] = row;
                    });

                    console.log('Campaign IDs to clear:', campaignIds);

                    if (campaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('warning', 'No selected rows with valid campaign ID found');
                        return;
                    }

                    // Since the save endpoint requires sbid_m > 0, we need to clear via direct database update
                    // Use a custom endpoint or update directly via AJAX to set sbid_m to NULL/empty
                    $.ajax({
                        url: '/clear-ebay-sbid-m-bulk',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_ids: campaignIds
                        },
                        success: function(response) {
                            console.log('Clear response:', response);
                            if (response && response.status === 200) {
                                var successCount = response.updated_count || campaignIds
                                    .length;

                                // Update row data - clear sbid_m and apprSbid
                                campaignIds.forEach(function(campaignId) {
                                    var row = campaignRowMap[campaignId];
                                    if (row) {
                                        var rowData = row.getData();
                                        var currentData = JSON.parse(JSON
                                            .stringify(rowData));
                                        currentData.sbid_m = ''; // Clear sbid_m
                                        currentData.apprSbid =
                                        ''; // Clear apprSbid
                                        row.update(currentData);
                                        setTimeout(function() {
                                            row.reformat();
                                        }, 50);
                                    }
                                });

                                if (overlay) overlay.style.display = "none";
                                showToast('success',
                                    'SBID M cleared successfully for ' +
                                    successCount + ' campaign(s)');
                                table.redraw(true);
                            } else {
                                if (overlay) overlay.style.display = "none";
                                showToast('error', response.message ||
                                    'Failed to clear SBID M values');
                            }
                        },
                        error: function(xhr) {
                            console.error('Error clearing SBID M:', xhr);
                            // If endpoint doesn't exist, update frontend directly
                            var successCount = 0;
                            campaignIds.forEach(function(campaignId) {
                                var row = campaignRowMap[campaignId];
                                if (row) {
                                    var rowData = row.getData();
                                    var currentData = JSON.parse(JSON.stringify(
                                        rowData));
                                    currentData.sbid_m =
                                    ''; // Clear sbid_m in frontend
                                    currentData.apprSbid = ''; // Clear apprSbid
                                    row.update(currentData);
                                    setTimeout(function() {
                                        row.reformat();
                                    }, 50);
                                    successCount++;
                                }
                            });

                            if (overlay) overlay.style.display = "none";
                            showToast('info', 'SBID M cleared in display for ' +
                                successCount +
                                ' row(s). Database update requires backend endpoint implementation.'
                                );
                            table.redraw(true);
                        }
                    });
                });

                // Initial update of all button counts after data loads
                setTimeout(function() {
                    updateButtonCounts();
                    updatePaginationCount();
                }, 1000);
            });

            // Function to update pagination count display
            function updatePaginationCount() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                try {
                    const filteredData = table.getData('active');
                    const totalRows = filteredData.length;
                    const pageSize = table.getPageSize();
                    const currentPage = table.getPage();

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
                } catch (e) {
                    console.error('Error updating pagination count:', e);
                }
            }

            // Track selection changes to maintain selection list (including bulk selections)
            table.on("rowSelectionChanged", function(data, rows) {
                // Sync the Set with all currently selected rows
                var allSelectedRows = table.getRows('selected');
                manuallySelectedRows.clear();
                allSelectedRows.forEach(function(row) {
                    var rowData = row.getData();
                    var campaignId = rowData.campaign_id;
                    if (campaignId) {
                        manuallySelectedRows.add(campaignId);
                    }
                });

                // Update header checkbox state based on current page rows
                setTimeout(updateHeaderCheckboxState, 50);

                if (manuallySelectedRows.size > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                    document.getElementById("save-all-sbid-m-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                    document.getElementById("save-all-sbid-m-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    // Update color immediately for NRA/NRL fields
                    if (field === 'NR' || field === 'NRL') {
                        // For emoji dropdown, we don't need to change background color
                        // The emoji itself changes based on selection
                    }

                    fetch('/update-ebay3-nr-data', {
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
                            console.log('Update response:', data); // Debug log
                            // Update table data with response
                            if (data.success && typeof table !== 'undefined' && table) {
                                let row = table.searchRows('sku', '=', sku);
                                if (row.length > 0) {
                                    // If NRL is set to "NRL", automatically set NRA to "NRA" (only if NRA is not already set)
                                    if (field === 'NRL' && value === 'NRL') {
                                        console.log('NRL set to NRL, updating NRA...'); // Debug log
                                        // Get current row data BEFORE update
                                        let rowData = row[0].getData();
                                        let currentNra = rowData.NR ? String(rowData.NR).trim() : '';

                                        // Get NRA value from backend response (backend always sets it to 'NRA' when NRL is 'NRL')
                                        let backendNra = '';
                                        if (data.updated_json && data.updated_json.NR) {
                                            backendNra = String(data.updated_json.NR).trim();
                                        }

                                        console.log('Current NRA:', currentNra, 'Backend NRA:',
                                            backendNra); // Debug log

                                        // Always update both NRL and NR when NRL is set to "NRL"
                                        // Backend always sets NR to "NRA" when NRL is "NRL"
                                        let updateData = {
                                            NRL: value,
                                            NR: backendNra ||
                                                'NRA' // Use backend value (should be 'NRA') or default to 'NRA'
                                        };

                                        console.log('Updating row with:', updateData); // Debug log

                                        // Update the row data immediately
                                        row[0].update(updateData);

                                        // Force update NRA column display immediately
                                        setTimeout(function() {
                                            // Reformat the row to update all formatters (especially NRA column)
                                            row[0].reformat();
                                        }, 50);

                                        // Second attempt to ensure UI updates
                                        setTimeout(function() {
                                            row[0].reformat();
                                            // Redraw the table to ensure all cells are updated
                                            if (typeof table !== 'undefined' && table) {
                                                table.redraw(true);
                                            }
                                        }, 200);
                                    } else {
                                        // Update the field from backend response
                                        let updatedData = {};
                                        if (data.updated_json && data.updated_json[field] !==
                                            undefined) {
                                            updatedData[field] = data.updated_json[field];
                                        } else {
                                            updatedData[field] = value;
                                        }
                                        row[0].update(updatedData);
                                    }
                                }
                            }
                        })
                        .catch(err => {
                            console.error('Error updating field:', err);
                            // Show error message to user
                            alert('Error updating field. Please try again.');
                        });
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                // Get only actually selected rows - verify selection state
                var allSelectedRows = table.getRows('selected');
                var selectedCampaignIds = [];
                var seenCampaignIds = new Set();

                allSelectedRows.forEach(function(row) {
                    var rowData = row.getData();
                    var campaignId = rowData.campaign_id;
                    if (campaignId && !seenCampaignIds.has(campaignId)) {
                        selectedCampaignIds.push(campaignId);
                        seenCampaignIds.add(campaignId);
                    }
                });

                console.log('APR ALL: Total rows in table:', table.getRows('all').length);
                console.log('APR ALL: Selected rows count:', allSelectedRows.length);
                console.log('APR ALL: Unique selected campaign IDs:', selectedCampaignIds.length);

                if (selectedCampaignIds.length === 0) {
                    overlay.style.display = "none";
                    showToast('error', 'Please select at least one campaign');
                    return;
                }

                // Get rows for selected campaign IDs
                var selectedRows = allSelectedRows.filter(function(row) {
                    var campaignId = row.getData().campaign_id;
                    return campaignId && selectedCampaignIds.includes(campaignId);
                });

                var campaignIds = [];
                var bids = [];

                // Store row and bid mapping for updating apprSbid after success
                var rowBidMap = [];

                selectedRows.forEach(function(row) {
                    var rowData = row.getData();

                    // Check if NRA (🔴) is selected
                    var nraValue = rowData.NR ? rowData.NR.trim() : "";
                    if (nraValue === 'NRA') {
                        return; // Skip update if NRA is selected
                    }

                    // Get sbid_m value (saved value)
                    var sbidM = parseFloat(rowData.sbid_m) || 0;

                    // Only add if sbid_m exists and is greater than 0
                    if (sbidM > 0 && rowData.campaign_id) {
                        campaignIds.push(rowData.campaign_id);
                        bids.push(sbidM);
                        // Store row and bid mapping for later update
                        rowBidMap.push({
                            row: row,
                            campaignId: rowData.campaign_id,
                            bid: sbidM
                        });
                    }
                });

                if (campaignIds.length === 0) {
                    overlay.style.display = "none";
                    showToast('error', 'No valid campaigns with SBID M value found');
                    return;
                }

                console.log("Campaign IDs:", campaignIds);
                console.log("Bids:", bids);

                fetch('/update-ebay3-keywords-bid-price', {
                        method: 'PUT',
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
                        if (data.status === 200) {
                            showToast('success', 'Keywords updated successfully for ' + campaignIds
                                .length + ' campaign(s)');

                            // Update apprSbid for all successfully updated rows
                            rowBidMap.forEach(function(item) {
                                var row = item.row;
                                var rowData = row.getData();

                                // Update apprSbid with the bid value that was pushed
                                rowData.apprSbid = item.bid;

                                // Update the row
                                row.update(rowData);

                                // Reformat to update APR BID column icon
                                setTimeout(function() {
                                    row.reformat();
                                }, 50);
                            });
                        } else {
                            let errorMsg = data.message || "Something went wrong";
                            if (errorMsg.includes("Premium Ads")) {
                                showToast('error', 'Error: ' + errorMsg);
                            } else {
                                showToast('error', 'Something went wrong: ' + errorMsg);
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('error', 'Error updating bids');
                    })
                    .finally(() => {
                        overlay.style.display = "none";
                    });
            });

            // Bulk save SBID M for selected campaigns
            document.getElementById("save-all-sbid-m-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                // Get only actually selected rows - verify selection state
                var allSelectedRows = table.getRows('selected');
                var selectedCampaignIds = [];
                var seenCampaignIds = new Set();

                allSelectedRows.forEach(function(row) {
                    var rowData = row.getData();
                    var campaignId = rowData.campaign_id;
                    if (campaignId && !seenCampaignIds.has(campaignId)) {
                        selectedCampaignIds.push(campaignId);
                        seenCampaignIds.add(campaignId);
                    }
                });

                console.log('Total rows in table:', table.getRows('all').length);
                console.log('Selected rows count:', allSelectedRows.length);
                console.log('Unique selected campaign IDs:', selectedCampaignIds.length);
                console.log('First 10 campaign IDs:', selectedCampaignIds.slice(0, 10));

                if (selectedCampaignIds.length === 0) {
                    overlay.style.display = "none";
                    showToast('error', 'Please select at least one campaign');
                    return;
                }

                // Safety check: if too many rows are selected, warn the user
                if (selectedCampaignIds.length > 100) {
                    var confirmUpdate = confirm('You have selected ' + selectedCampaignIds.length +
                        ' campaigns. Are you sure you want to update all of them?');
                    if (!confirmUpdate) {
                        overlay.style.display = "none";
                        return;
                    }
                }

                // Get rows for selected campaign IDs
                var selectedRows = [];
                selectedCampaignIds.forEach(function(campaignId) {
                    var rows = table.getRows().filter(function(row) {
                        return row.getData().campaign_id === campaignId;
                    });
                    selectedRows = selectedRows.concat(rows);
                });

                // Prompt for SBID M value
                var sbidMValue = prompt('Enter SBID M value for all selected campaigns:');
                if (!sbidMValue || sbidMValue.trim() === '') {
                    overlay.style.display = "none";
                    return;
                }

                var cleanValue = parseFloat(sbidMValue.replace(/[$\s]/g, '')) || 0;
                if (cleanValue <= 0) {
                    overlay.style.display = "none";
                    showToast('error', 'SBID M must be greater than 0');
                    return;
                }

                // Use the tracked campaign IDs directly
                var campaignIds = selectedCampaignIds.map(function(id) {
                    return String(id).trim();
                }).filter(function(id) {
                    return id !== '' && id !== null && id !== undefined;
                });

                if (campaignIds.length === 0) {
                    overlay.style.display = "none";
                    showToast('error',
                        'No valid campaigns selected. Please select campaigns with valid campaign IDs.');
                    return;
                }

                // Debug: Log how many campaigns are being sent
                console.log('Sending ' + campaignIds.length + ' campaign IDs for update:', campaignIds);

                $.ajax({
                    url: '/save-ebay3-sbid-m-bulk',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        campaign_ids: campaignIds,
                        sbid_m: cleanValue
                    },
                    success: function(response) {
                        if (response.status === 200) {
                            showToast('success', response.message ||
                                'SBID M saved successfully for ' + response.updated_count +
                                ' campaign(s)');
                            // Update the selected rows with the new sbid_m value instead of refreshing entire table
                            // Clear apprSbid when sbid_m is updated
                            selectedRows.forEach(function(row) {
                                var rowData = row.getData();
                                if (rowData.campaign_id && campaignIds.includes(rowData
                                        .campaign_id)) {
                                    // Get current data and create a copy to avoid reference issues
                                    var currentData = JSON.parse(JSON.stringify(
                                        rowData));

                                    // Update sbid_m and clear apprSbid so new bid can be pushed
                                    currentData.sbid_m = cleanValue;
                                    currentData.apprSbid =
                                    ''; // Clear apprSbid to allow new bid push

                                    // Update the row with complete data
                                    row.update(currentData);

                                    // Force redraw of the row to refresh all formatters including APR BID
                                    setTimeout(function() {
                                        row.reformat();
                                    }, 50);
                                }
                            });
                        } else {
                            showToast('error', response.message || 'Failed to save SBID M');
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Failed to save SBID M';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        showToast('error', errorMsg);
                        console.error('SBID M bulk save error:', xhr);
                    },
                    complete: function() {
                        overlay.style.display = "none";
                    }
                });
            });

            function updateBid(aprBid, campaignId, cell) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-ebay3-keywords-bid-price', {
                        method: 'PUT',
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
                        if (data.status === 200) {
                            showToast('success', 'Keywords updated successfully!');

                            // Update row data with apprSbid value
                            if (cell) {
                                var rowData = cell.getRow().getData();
                                rowData.apprSbid = aprBid;
                                cell.getRow().update(rowData);

                                // Reformat the cell to show green checkmark
                                cell.getRow().reformat();
                            }
                        } else {
                            let errorMsg = data.message || "Something went wrong";
                            if (errorMsg.includes("Premium Ads")) {
                                showToast('error', 'Error: ' + errorMsg);
                            } else {
                                showToast('error', 'Something went wrong: ' + errorMsg);
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('error', 'Error updating bid');
                    })
                    .finally(() => {
                        overlay.style.display = "none";
                    });
            }

            // Handle campaign status toggle
            document.addEventListener("change", function(e) {
                if(e.target.classList.contains("campaign-status-toggle")) {
                    let campaignId = e.target.getAttribute("data-campaign-id");
                    let isEnabled = e.target.checked; // Checkbox state = what user wants
                    let newStatus = isEnabled ? 'ENABLED' : 'PAUSED';
                    let originalChecked = isEnabled; // Store for error revert
                    
                    if(!campaignId) {
                        alert("Campaign ID not found!");
                        e.target.checked = !isEnabled; // Revert toggle
                        return;
                    }
                    
                    // Find the row for updating
                    let rows = table.getRows();
                    let currentRow = null;
                    for(let i = 0; i < rows.length; i++) {
                        let rowData = rows[i].getData();
                        if(rowData.campaign_id === campaignId) {
                            currentRow = rows[i];
                            break;
                        }
                    }
                    
                    if(!currentRow) {
                        alert("Row not found!");
                        e.target.checked = !isEnabled; // Revert toggle
                        return;
                    }
                    
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    
                    fetch('/toggle-ebay3-campaign-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            campaign_id: campaignId,
                            status: newStatus
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.status === 200 || data.status === '200'){
                            // Convert ENABLED to RUNNING for database, PAUSED stays PAUSED
                            let dbStatus = newStatus === 'ENABLED' ? 'RUNNING' : 'PAUSED';
                            // Update the row data
                            currentRow.update({campaignStatus: dbStatus});
                            
                            // Immediately update checkbox state before reformat
                            // This ensures the checkbox reflects the new state right away
                            e.target.checked = (dbStatus === 'RUNNING');
                            
                            // Force reformat to ensure formatter runs and creates new checkbox with correct state
                            let statusCell = currentRow.getCell('campaignStatus');
                            if(statusCell) {
                                statusCell.reformat();
                                // After reformat, ensure the new checkbox has correct state
                                setTimeout(() => {
                                    let cellElement = statusCell.getElement();
                                    if(cellElement) {
                                        let newCheckbox = cellElement.querySelector('.campaign-status-toggle');
                                        if(newCheckbox) {
                                            newCheckbox.checked = (dbStatus === 'RUNNING');
                                        }
                                    }
                                }, 10);
                            }
                            
                            // Show success message
                            showToast('success', data.message || 'Campaign status updated successfully');
                        } else {
                            showToast('error', data.message || "Failed to update campaign status");
                            // Revert checkbox to original state (opposite of what user wanted)
                            e.target.checked = !originalChecked;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Request failed: " + err.message);
                        // Revert checkbox to original state
                        e.target.checked = !originalChecked;
                    })
                    .finally(() => {
                        if (overlay) {
                            overlay.style.display = "none";
                        }
                    });
                }
            });

            // Handle SBID M cell edit
            table.on("cellEdited", function(cell) {
                const field = cell.getField();
                if (field === "sbid_m") {
                    const data = cell.getRow().getData();
                    const campaignId = data.campaign_id;
                    let value = cell.getValue();

                    if (!campaignId) {
                        showToast('error', 'Campaign ID not found');
                        return;
                    }

                    // Clean the value
                    let cleanValue = String(value).replace(/[$\s]/g, '');
                    cleanValue = parseFloat(cleanValue) || 0;

                    if (cleanValue <= 0) {
                        showToast('error', 'SBID M must be greater than 0');
                        cell.setValue('');
                        return;
                    }

                    $.ajax({
                        url: '/save-ebay3-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: campaignId,
                            sbid_m: cleanValue
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                // Update row data - clear apprSbid when sbid_m is updated
                                var row = cell.getRow();
                                // Get current data and create a copy to avoid reference issues
                                var currentData = JSON.parse(JSON.stringify(row.getData()));

                                // Update sbid_m and clear apprSbid so new bid can be pushed
                                currentData.sbid_m = cleanValue;
                                currentData.apprSbid =
                                ''; // Clear apprSbid to allow new bid push

                                // Update the row with complete data
                                row.update(currentData);

                                // Force redraw of the row to refresh all formatters including APR BID
                                setTimeout(function() {
                                    row.reformat();
                                }, 50);

                                showToast('success', 'SBID M saved successfully');
                            } else {
                                showToast('error', response.message || 'Failed to save SBID M');
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'Failed to save SBID M';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            } else if (xhr.status === 404) {
                                errorMsg =
                                    'Campaign not found. Please ensure the campaign exists.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error. Please try again.';
                            }
                            showToast('error', errorMsg);
                            console.error('SBID M save error:', xhr);
                        }
                    });
                }
            });

            // Add click handlers to utilization cards (if they exist)
            document.querySelectorAll('.utilization-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    showUtilizationChart(type);
                });
            });
        });

        let utilizationChartInstance = null;

        function showUtilizationChart(type) {
            const chartTitle = document.getElementById('chart-title');
            const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));

            const titles = {
                'over': 'Over Utilized Trend (Last 30 Days)',
                'under': 'Under Utilized Trend (Last 30 Days)',
                'correctly': 'Correctly Utilized Trend (Last 30 Days)'
            };
            chartTitle.textContent = titles[type] || 'Utilization Trend';

            modal.show();

            fetch('/ebay/get-utilization-chart-data?type=' + type)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);

                        let dataset = [];
                        let label = '';
                        let color = '';
                        let bgColor = '';

                        if (type === 'over') {
                            dataset = chartData.map(d => d.over_utilized);
                            label = 'Over Utilized';
                            color = '#ff01d0';
                            bgColor = 'rgba(255, 1, 208, 0.1)';
                        } else if (type === 'under') {
                            dataset = chartData.map(d => d.under_utilized);
                            label = 'Under Utilized';
                            color = '#ff2727';
                            bgColor = 'rgba(255, 39, 39, 0.1)';
                        } else if (type === 'correctly') {
                            dataset = chartData.map(d => d.correctly_utilized);
                            label = 'Correctly Utilized';
                            color = '#28a745';
                            bgColor = 'rgba(40, 167, 69, 0.1)';
                        }

                        const ctx = document.getElementById('utilizationChart').getContext('2d');

                        if (utilizationChartInstance) {
                            utilizationChartInstance.destroy();
                        }

                        utilizationChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: label,
                                    data: dataset,
                                    borderColor: color,
                                    backgroundColor: bgColor,
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                }]
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
                                                return context.dataset.label + ': ' + context.parsed.y;
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

        // Toast notification function
        function showToast(type, message) {
            // Create toast container if it doesn't exist
            if (!$('.toast-container').length) {
                $('body').append(
                    '<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
            }

            // Determine background color based on type
            let bgClass = 'bg-info'; // default
            if (type === 'success') {
                bgClass = 'bg-success';
            } else if (type === 'error') {
                bgClass = 'bg-danger';
            } else if (type === 'warning') {
                bgClass = 'bg-warning';
            } else if (type === 'info') {
                bgClass = 'bg-info';
            }

            const toast = $(`
                <div class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }
    </script>
@endsection
