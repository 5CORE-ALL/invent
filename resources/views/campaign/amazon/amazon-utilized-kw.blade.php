@extends('layouts.vertical', ['title' => 'Amazon KW - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        .parent-row,
        .parent-row .tabulator-cell {
            background-color: #dbeafe !important;
            font-weight: 600;
        }
        .parent-row:hover .tabulator-cell {
            background-color: #bfdbfe !important;
        }

        #budget-under-table .tabulator {
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

        /* Ensure table body horizontal scroll syncs with header */
        #budget-under-table .tabulator .tabulator-tableHolder {
            overflow-x: auto !important;
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

        body {
            zoom: 90%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon KW - Utilized',
        'sub_title' => 'Amazon KW - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-4">
                    <div class="mb-4">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Filters Row: All filters in one line with equal spacing -->
                                <div class="d-flex flex-nowrap align-items-end overflow-x-auto mb-3 pb-1 filters-row-equal" style="min-height: 60px; gap: 1rem;">
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>Utilization Type
                                        </label>
                                        <select id="utilization-type-select" class="form-select form-select-sm w-100">
                                            <option value="all" selected>All</option>
                                            <option value="gg">Green+Green</option>
                                            <option value="gp">Green+Pink</option>
                                            <option value="gr">Green+Red</option>
                                            <option value="pg">Pink+Green</option>
                                            <option value="pp">Pink+Pink</option>
                                            <option value="pr">Pink+Red</option>
                                            <option value="rg">Red+Green</option>
                                            <option value="rp">Red+Pink</option>
                                            <option value="rr">Red+Red</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-toggle-on me-1" style="color: #64748b;"></i>Status
                                        </label>
                                        <select id="status-filter" class="form-select form-select-sm w-100">
                                            <option value="ALL">All</option>
                                            <option value="ENABLED" selected>Enabled</option>
                                            <option value="PAUSED">Paused</option>
                                            <option value="ENDED">Ended</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-sm w-100">
                                            <option value="ALL">All</option>
                                            <option value="INV_0">0 INV</option>
                                            <option value="OTHERS" selected>OTHERS</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-layer-group me-1" style="color: #64748b;"></i>Type
                                        </label>
                                        <select id="sku-type-filter" class="form-select form-select-sm w-100">
                                            <option value="">All</option>
                                            <option value="parent">Parent</option>
                                            <option value="sku">Sku</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRA
                                        </label>
                                        <select id="nra-filter" class="form-select form-select-sm w-100">
                                            <option value="">All</option>
                                            <option value="NRA">NRA</option>
                                            <option value="RA">RA</option>
                                            <option value="LATER">LATER</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-dollar-sign me-1" style="color: #64748b;"></i>Price
                                        </label>
                                        <select id="price-slab-filter" class="form-select form-select-sm w-100">
                                            <option value="">All</option>
                                            <option value="lt10">&lt; $10</option>
                                            <option value="10-20">$10 - $20</option>
                                            <option value="20-30">$20 - $30</option>
                                            <option value="30-50">$30 - $50</option>
                                            <option value="50-100">$50 - $100</option>
                                            <option value="gt100">&gt; $100</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-star me-1" style="color: #64748b;"></i>Rating
                                        </label>
                                        <select id="rating-filter" class="form-select form-select-sm w-100">
                                            <option value="">All</option>
                                            <option value="lt3">&lt; 3</option>
                                            <option value="3-3.5">3 - 3.5</option>
                                            <option value="4-4.5">4 - 4.5</option>
                                            <option value="gte4.5">≥ 4.5</option>
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 flex-shrink-0" style="min-width: 0;">
                                        <label class="form-label fw-semibold mb-1" style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>ACOS
                                        </label>
                                        <select id="sbgt-filter" class="form-select form-select-sm w-100">
                                            <option value="">All</option>
                                            <option value="8">&lt; 5%</option>
                                            <option value="7">5-9%</option>
                                            <option value="6">10-14%</option>
                                            <option value="5">15-19%</option>
                                            <option value="4">20-24%</option>
                                            <option value="3">25-29%</option>
                                            <option value="2">30-34%</option>
                                            <option value="1">≥ 35%</option>
                                            <option value="acos35spend10">&gt;35% &amp; SPEND &gt;10</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Count Badges Row -->
                                <div class="row pb-3 border-bottom">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold mb-2 d-block" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-2 flex-wrap align-items-center">
                                            <span class="badge-count-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Total SKU:</span>
                                                <span class="fw-bold" id="total-sku-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Parent Sku:</span>
                                                <span class="fw-bold" id="parent-sku-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item total-campaign-card" id="total-campaign-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Campaign:</span>
                                                <span class="fw-bold" id="total-campaign-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item missing-campaign-card" id="missing-campaign-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Missing:</span>
                                                <span class="fw-bold" id="missing-campaign-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nra-missing-card" id="nra-missing-card" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRA MISSING:</span>
                                                <span class="fw-bold" id="nra-missing-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item zero-inv-card" id="zero-inv-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Zero INV:</span>
                                                <span class="fw-bold" id="zero-inv-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item nra-card" id="nra-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">NRA:</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item ra-card" id="ra-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">RA:</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Total Spend L30:</span>
                                                <span class="fw-bold" id="total-spend-l30" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Total Sales L30:</span>
                                                <span class="fw-bold" id="total-sales-l30" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">Total Ad Sold L30:</span>
                                                <span class="fw-bold" id="total-ad-sold-l30" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item paused-campaigns-card" id="paused-campaigns-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;" title="Click to view paused campaigns">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">PINK DIL PAUSED:</span>
                                                <span class="fw-bold" id="paused-campaigns-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item utilization-card" data-type="7ub" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">7UB:</span>
                                                <span class="fw-bold" id="7ub-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                            <span class="badge-count-item utilization-card" data-type="7ub-1ub" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 4px 10px; border-radius: 6px; color:#000000; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s; white-space: nowrap; font-size: 0.8125rem;">
                                                <span style="font-size: 0.7rem; margin-right: 4px;">7UB + 1UB:</span>
                                                <span class="fw-bold" id="7ub-1ub-count" style="font-size: 1.1rem; color: black;">0</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Multi-Range Filter Row -->
                                <div class="row align-items-end g-2 mt-3 pt-3 border-top">
                                    <div class="col-12">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-sliders me-1" style="color: #64748b;"></i>Range Filters
                                        </label>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">1UB (%)</label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" id="1ub-min" class="form-control form-control-sm" placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                            <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                            <input type="number" id="1ub-max" class="form-control form-control-sm" placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">7UB (%)</label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" id="7ub-min" class="form-control form-control-sm" placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                            <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                            <input type="number" id="7ub-max" class="form-control form-control-sm" placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">Lbid ($)</label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" id="lbid-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="font-size: 0.8rem;">
                                            <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                            <input type="number" id="lbid-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="font-size: 0.8rem;">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.75rem;">ACOS (%)</label>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="number" id="acos-min" class="form-control form-control-sm" placeholder="Min" step="0.1" style="font-size: 0.8rem;">
                                            <span style="color: #64748b; font-size: 0.8rem;">-</span>
                                            <input type="number" id="acos-max" class="form-control form-control-sm" placeholder="Max" step="0.1" style="font-size: 0.8rem;">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- INC/DEC SBID Section and Action Buttons -->
                                <div class="row g-3 align-items-end pt-3 border-top">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-calculator me-1" style="color: #64748b;"></i>INC/DEC SBID
                                        </label>
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" id="inc-dec-btn"
                                                class="btn btn-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                                                aria-expanded="false">
                                                <i class="fa-solid fa-plus-minus me-1"></i>
                                                INC/DEC (By Value)
                                            </button>
                                            <ul class="dropdown-menu" id="inc-dec-dropdown">
                                                <li><a class="dropdown-item" href="#" data-type="value">By Value</a></li>
                                                <li><a class="dropdown-item" href="#" data-type="percentage">By Percentage</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
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
                                    <div class="col-md-3 d-flex gap-2 align-items-end">
                                        <button id="clear-sbid-m-btn" class="btn btn-danger btn-sm flex-fill">
                                            <i class="fa-solid fa-trash me-1"></i>
                                            Clear SBID M (Selected)
                                        </button>
                                        <button id="acos-view-btn" class="btn btn-warning btn-sm" style="min-width: 80px;">
                                            <i class="fa-solid fa-filter me-1"></i>
                                            ACOS
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
                                        <input type="text" id="global-search-table"
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
    <div class="modal fade" id="utilizationChartModal" tabindex="-1" aria-labelledby="utilizationChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="utilizationChartModalLabel">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        <span id="chart-title">Utilization Trend</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <div id="progress-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
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
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let showPinkDilPausedOnly = false; // Filter for Pink DIL paused campaigns only
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;
            let totalL30Purchases = 0;
            let totalSkuCountFromBackend = 0; // Store total SKU count from backend

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
                const allData = table.getData('all');
                const comboCounts = { gg: 0, gp: 0, gr: 0, pg: 0, pp: 0, pr: 0, rg: 0, rp: 0, rr: 0 }; // 7UB×1UB color combos (same as google-utilized)
                let totalForUtilCount = 0; // Rows with valid utilization (campaign + ENABLED + budget) for combo counts
                let totalRowsWhenAllUtilization = 0; // Rows shown when Utilization Type = "All" (matches pagination)
                let missingCount = 0;
                let nraMissingCount = 0; // Count NRA missing (yellow dots)
                let zeroInvCount = 0; // Count zero and negative inventory
                let totalCampaignCount = 0; // Count total campaigns
                let nraCount = 0; // Count NRA
                let raCount = 0; // Count RA
                let validSkuCount = 0; // Count only valid SKUs (not parent, not empty)
                
                // Track processed SKUs to avoid counting duplicates
                const processedSkusForNra = new Set(); // Track SKUs for NRA/RA counting
                const processedSkusForCampaign = new Set(); // Track SKUs for campaign counting
                const processedSkusForMissing = new Set(); // Track SKUs for missing counting
                const processedSkusForNraMissing = new Set(); // Track SKUs for NRA missing counting
                const processedSkusForZeroInv = new Set(); // Track SKUs for zero INV counting
                const processedSkusForValidCount = new Set(); // Track SKUs for valid count
                
                // Total SKU, Parent Sku, Missing, NRA Missing: filter-independent (no filter applied), do not change when user applies filters
                let totalChildSkuCount = 0;
                let totalParentSkuCount = 0;
                let missingCountDirect = 0;   // Missing count (parents only, no campaign, NRL/NRA not NRA) - filter-independent
                let nraMissingCountDirect = 0; // NRA missing count (parents only, no campaign, NRL/NRA) - filter-independent
                const childSkusForTotal = new Set();
                const parentSkusForTotal = new Set();
                const processedMissingDirect = new Set();
                const processedNraMissingDirect = new Set();
                allData.forEach(function(row) {
                    const skuForTotal = row.sku || '';
                    const isParentForTotal = row.is_parent !== undefined ? !!row.is_parent : (skuForTotal || '').toUpperCase().includes('PARENT');
                    if (skuForTotal) {
                        if (isParentForTotal) {
                            if (!parentSkusForTotal.has(skuForTotal)) {
                                parentSkusForTotal.add(skuForTotal);
                                totalParentSkuCount++;
                            }
                            // Missing / NRA Missing: parents only, no filter
                            const hasCampaignDirect = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaignDirect) {
                                const nrlD = row.NRL ? row.NRL.trim() : "";
                                const nraD = row.NRA ? row.NRA.trim() : "";
                                if (nrlD !== 'NRL' && nraD !== 'NRA') {
                                    if (!processedMissingDirect.has(skuForTotal)) {
                                        processedMissingDirect.add(skuForTotal);
                                        missingCountDirect++;
                                    }
                                } else {
                                    if (!processedNraMissingDirect.has(skuForTotal)) {
                                        processedNraMissingDirect.add(skuForTotal);
                                        nraMissingCountDirect++;
                                    }
                                }
                            }
                        } else {
                            if (!childSkusForTotal.has(skuForTotal)) {
                                childSkusForTotal.add(skuForTotal);
                                totalChildSkuCount++;
                            }
                        }
                    }
                });
                
                allData.forEach(function(row) {
                    // Count valid SKUs (exclude parent SKUs and empty SKUs); use backend is_parent when present (matches totalSkuCount: sku LIKE 'PARENT %')
                    const sku = row.sku || '';
                    const isParentRow = row.is_parent !== undefined ? !!row.is_parent : (sku || '').toUpperCase().includes('PARENT');
                    const isValidSku = sku && !isParentRow;
                    let inv = parseFloat(row.INV || 0);
                    
                    // Apply same filters as combinedFilter (same order) so utilization count matches pagination
                    // 1. Type filter
                    const typeFilterForCount = $("#sku-type-filter").val() || '';
                    if (typeFilterForCount === 'parent' && !isParentRow) return;
                    if (typeFilterForCount === 'sku' && isParentRow) return;
                    
                    const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                    if (showCampaignOnly && !hasCampaign) return;
                    if (showMissingOnly) {
                        if (hasCampaign) return;
                        const nrlVal = row.NRL ? row.NRL.trim() : "";
                        const nraVal = row.NRA ? row.NRA.trim() : "";
                        if (nrlVal === 'NRL' || nraVal === 'NRA') return;
                    }
                    if (showNraMissingOnly) {
                        if (hasCampaign) return;
                        if ((row.NRA ? row.NRA.trim() : "") !== 'NRA') return;
                    }
                    if (showPinkDilPausedOnly && !row.pink_dil_paused_at) return;
                    
                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) {
                        return;
                    }
                    
                    // Status filter: default ENABLED only; use "All" to see all campaigns
                    let statusVal = $("#status-filter").val() || 'ENABLED';
                    if (statusVal !== 'ALL' && row.campaignStatus !== statusVal) {
                        return;
                    }
                    
                    // Count zero/negative inventory (INV <= 0) AFTER search and status filters
                    // This ensures zero inv count matches the filtered dataset
                    if (inv <= 0 && isValidSku && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }
                    
                    // Inventory filter (ALL = show all; OTHERS = INV > 0; INV_0 = only 0)
                    let invFilterVal = $("#inv-filter").val() || '';
                    if (invFilterVal === "OTHERS") {
                        if (inv <= 0) return;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return;
                    }
                    // ALL or empty: show/count all (no inv filter)
                    
                    // Count NRA and RA only for valid SKUs and only once per SKU (after filters)
                    if (isValidSku && !processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        // Note: Empty/null NRA defaults to "RA" in the display
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (rowNra === 'NRA') {
                            nraCount++;
                        } else {
                            // If NRA is empty, null, or "RA", it shows as "RA" by default
                            raCount++;
                        }
                    }
                    
                    // NRA filter
                    let rowNra = row.NRA ? row.NRA.trim() : "";
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        if (nraFilterVal === 'RA') {
                            if (rowNra === 'NRA') return;
                        } else {
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    if (showNraOnly && rowNra !== 'NRA') return;
                    if (showRaOnly && rowNra === 'NRA') return;
                    
                    // ACOS filter (sbgt-filter) - same as combinedFilter
                    let acosFilterVal = $("#sbgt-filter").val();
                    if (acosFilterVal) {
                        let acosVal = parseFloat(row.acos || 0);
                        if (isNaN(acosVal)) acosVal = 0;
                        if (acosFilterVal === 'acos35spend10') {
                            let spendVal = parseFloat(row.l30_spend || 0);
                            if (acosVal <= 35 || spendVal <= 10 || isNaN(spendVal)) return;
                        } else {
                            let acosMatch = false;
                            if (acosFilterVal === '8' && acosVal >= 0 && acosVal < 5) acosMatch = true;
                            else if (acosFilterVal === '7' && acosVal >= 5 && acosVal < 10) acosMatch = true;
                            else if (acosFilterVal === '6' && acosVal >= 10 && acosVal < 15) acosMatch = true;
                            else if (acosFilterVal === '5' && acosVal >= 15 && acosVal < 20) acosMatch = true;
                            else if (acosFilterVal === '4' && acosVal >= 20 && acosVal < 25) acosMatch = true;
                            else if (acosFilterVal === '3' && acosVal >= 25 && acosVal < 30) acosMatch = true;
                            else if (acosFilterVal === '2' && acosVal >= 30 && acosVal < 35) acosMatch = true;
                            else if (acosFilterVal === '1' && acosVal >= 35) acosMatch = true;
                            if (!acosMatch) return;
                        }
                    }
                    if (showZeroInvOnly && inv > 0) return;
                    
                    // Price slab filter
                    let priceSlabFilterVal = $("#price-slab-filter").val();
                    if (priceSlabFilterVal) {
                        let price = parseFloat(row.price || 0);
                        if (isNaN(price)) return;
                        if (priceSlabFilterVal === 'lt10' && price >= 10) return;
                        if (priceSlabFilterVal === '10-20' && (price < 10 || price >= 20)) return;
                        if (priceSlabFilterVal === '20-30' && (price < 20 || price >= 30)) return;
                        if (priceSlabFilterVal === '30-50' && (price < 30 || price >= 50)) return;
                        if (priceSlabFilterVal === '50-100' && (price < 50 || price >= 100)) return;
                        if (priceSlabFilterVal === 'gt100' && price < 100) return;
                    }
                    // Rating filter
                    let ratingFilterVal = $("#rating-filter").val();
                    if (ratingFilterVal) {
                        let rating = parseFloat(row.ratings || 0);
                        if (isNaN(rating) || rating <= 0) return;
                        if (ratingFilterVal === 'lt3' && rating >= 3) return;
                        if (ratingFilterVal === '3-3.5' && (rating < 3 || rating >= 4)) return;
                        if (ratingFilterVal === '4-4.5' && (rating < 4 || rating >= 4.5)) return;
                        if (ratingFilterVal === 'gte4.5' && rating < 4.5) return;
                    }
                    // Range filters (1UB, 7UB, lbid, acos) - use same budget as combinedFilter (campaignBudgetAmount for range)
                    let budgetForRange = parseFloat(row.campaignBudgetAmount) || 0;
                    let l7_spend_r = parseFloat(row.l7_spend || 0), l1_spend_r = parseFloat(row.l1_spend || 0);
                    let ub7_r = budgetForRange > 0 ? (l7_spend_r / (budgetForRange * 7)) * 100 : 0, ub1_r = budgetForRange > 0 ? (l1_spend_r / budgetForRange) * 100 : 0;
                    let ub1Min = $("#1ub-min").val(), ub1Max = $("#1ub-max").val();
                    if (ub1Min && ub1_r < parseFloat(ub1Min)) return;
                    if (ub1Max && ub1_r > parseFloat(ub1Max)) return;
                    let ub7Min = $("#7ub-min").val(), ub7Max = $("#7ub-max").val();
                    if (ub7Min && ub7_r < parseFloat(ub7Min)) return;
                    if (ub7Max && ub7_r > parseFloat(ub7Max)) return;
                    let lbidMin = $("#lbid-min").val(), lbidMax = $("#lbid-max").val();
                    if (lbidMin || lbidMax) {
                        let lastSbid = parseFloat(row.last_sbid || 0) || 0;
                        if (lbidMin && lastSbid < parseFloat(lbidMin)) return;
                        if (lbidMax && lastSbid > parseFloat(lbidMax)) return;
                    }
                    let acosMin = $("#acos-min").val(), acosMax = $("#acos-max").val();
                    if (acosMin || acosMax) {
                        let acosR = parseFloat(row.acos || 0) || 0;
                        if (acosMin && acosR < parseFloat(acosMin)) return;
                        if (acosMax && acosR > parseFloat(acosMax)) return;
                    }
                    
                    // Count rows that would be shown when Utilization Type = "All" (same as combinedFilter when currentUtilizationType === 'all')
                    totalRowsWhenAllUtilization++;
                    
                    // Count campaign or missing - for both child SKUs and parent rows (so Type=Parent shows correct Campaign/Missing)
                    if (isValidSku || isParentRow) {
                        if (hasCampaign) {
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                totalCampaignCount++;
                            }
                        } else {
                            // Missing / NRA missing: use filter-independent counts (missingCountDirect, nraMissingCountDirect) - not updated here
                        }
                    }
                    // Count valid SKUs (child only) that pass ALL filters - only once per SKU
                    if (isValidSku) {
                        if (!processedSkusForValidCount.has(sku)) {
                            processedSkusForValidCount.add(sku);
                            validSkuCount++;
                        }
                    }
                    
                    // Check if campaign is missing (red or yellow) - exclude from utilization type counts
                    const hasCampaignForUtil = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                    if (!hasCampaignForUtil) {
                        // This is a missing item (red or yellow), skip utilization type counting
                        return;
                    }
                    // When utilization type is selected, combinedFilter excludes non-ENABLED campaigns; match that so count = pagination
                    const campaignStatusForUtil = row.campaignStatus || 'PAUSED';
                    if (campaignStatusForUtil !== 'ENABLED') return;
                    
                    // Budget: use utilization_budget for PARENT rows (aggregated children budget), else campaignBudgetAmount
                    let budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                    let l7_spend = parseFloat(row.l7_spend || 0);
                    let l1_spend = parseFloat(row.l1_spend || 0);
                    
                    let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                    let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                    
                    // Exclude rows with no valid budget from utilization counts (match combinedFilter)
                    if (!(budget > 0) || isNaN(budget)) return;
                    
                    totalForUtilCount++;
                    const combo = (ub7 >= 66 && ub7 <= 99 ? 'g' : ub7 > 99 ? 'p' : 'r') + (ub1 >= 66 && ub1 <= 99 ? 'g' : ub1 > 99 ? 'p' : 'r');
                    if (comboCounts.hasOwnProperty(combo)) comboCounts[combo]++;
                });
                
                // Count ACOS ranges (SBGT mapping)
                let acosCount8 = 0, acosCount7 = 0, acosCount6 = 0, acosCount5 = 0;
                let acosCount4 = 0, acosCount3 = 0, acosCount2 = 0, acosCount1 = 0;
                let acosCountZero = 0; // Count ACOS = 0 separately
                let acos35Spend10Count = 0; // Count ACOS > 35% AND SPEND > 10
                
                allData.forEach(function(row) {
                    let acosVal = parseFloat(row.acos || 0);
                    
                    // Apply same filters as above
                    const sku = row.sku || '';
                    const isParentRowAcos = row.is_parent !== undefined ? !!row.is_parent : (sku || '').toUpperCase().includes('PARENT');
                    const isValidSku = sku && !isParentRowAcos;
                    if (!isValidSku) return;
                    
                    let inv = parseFloat(row.INV || 0);
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) return;
                    
                    let statusVal = $("#status-filter").val() || 'ENABLED';
                    if (statusVal !== 'ALL' && row.campaignStatus !== statusVal) return;
                    
                    let invFilterVal = $("#inv-filter").val() || '';
                    if (invFilterVal === "OTHERS") {
                        if (inv <= 0) return;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return;
                    }
                    
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (nraFilterVal === 'RA') {
                            if (rowNra === 'NRA') return;
                        } else {
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    
                    // Count ACOS = 0 separately, then skip from range counts
                    if (acosVal === 0 || isNaN(acosVal)) {
                        acosCountZero++;
                        return;
                    }
                    
                    // Count ACOS > 35% AND SPEND > 10
                    let spendVal = parseFloat(row.l30_spend || 0);
                    if (acosVal >= 35 && spendVal > 10) {
                        acos35Spend10Count++;
                    }
                    
                    // Count by ACOS range
                    if (acosVal < 5) acosCount8++;
                    else if (acosVal < 10) acosCount7++;
                    else if (acosVal < 15) acosCount6++;
                    else if (acosVal < 20) acosCount5++;
                    else if (acosVal < 25) acosCount4++;
                    else if (acosVal < 30) acosCount3++;
                    else if (acosVal < 35) acosCount2++;
                    else acosCount1++;
                });
                
                // Update missing campaign count (filter-independent: direct count from full data, parents only)
                const missingCountEl = document.getElementById('missing-campaign-count');
                if (missingCountEl) {
                    missingCountEl.textContent = typeof missingCountDirect !== 'undefined' ? missingCountDirect : missingCount;
                }
                
                // Update NRA missing count (filter-independent: direct count from full data, parents only)
                const nraMissingCountEl = document.getElementById('nra-missing-count');
                if (nraMissingCountEl) {
                    nraMissingCountEl.textContent = typeof nraMissingCountDirect !== 'undefined' ? nraMissingCountDirect : nraMissingCount;
                }
                
                // Update total campaign count
                const totalCampaignCountEl = document.getElementById('total-campaign-count');
                if (totalCampaignCountEl) {
                    totalCampaignCountEl.textContent = totalCampaignCount;
                }
                
                // Update total SKU count (child SKUs only, filter-independent - does not change with filters)
                const totalSkuCountEl = document.getElementById('total-sku-count');
                if (totalSkuCountEl) {
                    totalSkuCountEl.textContent = totalChildSkuCount;
                }
                // Update parent SKU count (parent SKUs only, filter-independent - does not change with filters)
                const parentSkuCountEl = document.getElementById('parent-sku-count');
                if (parentSkuCountEl) {
                    parentSkuCountEl.textContent = totalParentSkuCount;
                }
                // Update Total Spend L30, Total Sales L30, Total Ad Sold L30 (from backend, L30 range)
                const totalSpendL30El = document.getElementById('total-spend-l30');
                if (totalSpendL30El) totalSpendL30El.textContent = typeof totalL30Spend !== 'undefined' ? Number(totalL30Spend).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : '0';
                const totalSalesL30El = document.getElementById('total-sales-l30');
                if (totalSalesL30El) totalSalesL30El.textContent = typeof totalL30Sales !== 'undefined' ? Number(totalL30Sales).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) : '0';
                const totalAdSoldL30El = document.getElementById('total-ad-sold-l30');
                if (totalAdSoldL30El) totalAdSoldL30El.textContent = typeof totalL30Purchases !== 'undefined' ? Number(totalL30Purchases).toLocaleString() : '0';
                
                // Update NRA count
                const nraCountEl = document.getElementById('nra-count');
                if (nraCountEl) {
                    nraCountEl.textContent = nraCount;
                }
                
                // Update RA count
                const raCountEl = document.getElementById('ra-count');
                if (raCountEl) {
                    raCountEl.textContent = raCount;
                }
                
                // Count paused campaigns (campaigns with pink_dil_paused_at) - only valid (child) SKUs
                let pausedCampaignsCount = 0;
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const isParentRowPaused = row.is_parent !== undefined ? !!row.is_parent : (sku || '').toUpperCase().includes('PARENT');
                    if (isParentRowPaused) return;
                    if (row.pink_dil_paused_at) {
                        pausedCampaignsCount++;
                    }
                });
                
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
                
                // Update dropdown option texts with counts (7UB×1UB color combos - same as google-utilized)
                const utilizationSelect = document.getElementById('utilization-type-select');
                const comboLabels = { gg: 'Green+Green', gp: 'Green+Pink', gr: 'Green+Red', pg: 'Pink+Green', pp: 'Pink+Pink', pr: 'Pink+Red', rg: 'Red+Green', rp: 'Red+Pink', rr: 'Red+Red' };
                if (utilizationSelect) {
                    utilizationSelect.options[0].text = `All (${totalRowsWhenAllUtilization})`;
                    for (let i = 1; i <= 9; i++) {
                        const v = utilizationSelect.options[i].value;
                        utilizationSelect.options[i].text = `${comboLabels[v] || v} (${comboCounts[v] || 0})`;
                    }
                }
                
                // Update ACOS filter dropdown with counts
                const sbgtSelect = document.getElementById('sbgt-filter');
                if (sbgtSelect) {
                    const totalAcos = acosCount8 + acosCount7 + acosCount6 + acosCount5 + acosCount4 + acosCount3 + acosCount2 + acosCount1 + acosCountZero;
                    sbgtSelect.options[0].text = `All ACOS (${totalAcos})`;
                    sbgtSelect.options[1].text = `ACOS < 5% (${acosCount8})`;
                    sbgtSelect.options[2].text = `ACOS 5-9% (${acosCount7})`;
                    sbgtSelect.options[3].text = `ACOS 10-14% (${acosCount6})`;
                    sbgtSelect.options[4].text = `ACOS 15-19% (${acosCount5})`;
                    sbgtSelect.options[5].text = `ACOS 20-24% (${acosCount4})`;
                    sbgtSelect.options[6].text = `ACOS 25-29% (${acosCount3})`;
                    sbgtSelect.options[7].text = `ACOS 30-34% (${acosCount2})`;
                    sbgtSelect.options[8].text = `ACOS ≥ 35% (${acosCount1})`;
                    sbgtSelect.options[9].text = `ACOS>35% and SPEND >10 (${acos35Spend10Count})`;
                }
                
                // Count price slabs
                let priceSlabLt10 = 0, priceSlab10_20 = 0, priceSlab20_30 = 0;
                let priceSlab30_50 = 0, priceSlab50_100 = 0, priceSlabGt100 = 0;
                
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    if (!sku) return;
                    let price = parseFloat(row.price || 0);
                    if (isNaN(price) || price <= 0) return;
                    
                    // Apply same filters as above (except price slab filter) - include parent rows (they now have avg price)
                    let inv = parseFloat(row.INV || 0);
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) return;
                    
                    let statusVal = $("#status-filter").val() || 'ENABLED';
                    if (statusVal !== 'ALL' && row.campaignStatus !== statusVal) return;
                    
                    let invFilterVal = $("#inv-filter").val() || '';
                    if (invFilterVal === "OTHERS") {
                        if (inv <= 0) return;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return;
                    }
                    
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (nraFilterVal === 'RA') {
                            if (rowNra === 'NRA') return;
                        } else {
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    
                    // Count by price slab
                    if (price < 10) {
                        priceSlabLt10++;
                    } else if (price >= 10 && price < 20) {
                        priceSlab10_20++;
                    } else if (price >= 20 && price < 30) {
                        priceSlab20_30++;
                    } else if (price >= 30 && price < 50) {
                        priceSlab30_50++;
                    } else if (price >= 50 && price < 100) {
                        priceSlab50_100++;
                    } else if (price >= 100) {
                        priceSlabGt100++;
                    }
                });
                
                // Update price slab filter dropdown with counts
                const priceSlabSelect = document.getElementById('price-slab-filter');
                if (priceSlabSelect) {
                    const totalPrice = priceSlabLt10 + priceSlab10_20 + priceSlab20_30 + priceSlab30_50 + priceSlab50_100 + priceSlabGt100;
                    priceSlabSelect.options[0].text = `All Prices (${totalPrice})`;
                    priceSlabSelect.options[1].text = `< $10 (${priceSlabLt10})`;
                    priceSlabSelect.options[2].text = `$10 - $20 (${priceSlab10_20})`;
                    priceSlabSelect.options[3].text = `$20 - $30 (${priceSlab20_30})`;
                    priceSlabSelect.options[4].text = `$30 - $50 (${priceSlab30_50})`;
                    priceSlabSelect.options[5].text = `$50 - $100 (${priceSlab50_100})`;
                    priceSlabSelect.options[6].text = `> $100 (${priceSlabGt100})`;
                }
                
                // Count ratings
                let ratingLt3 = 0, rating3_35 = 0, rating4_45 = 0, ratingGte45 = 0;
                
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const isParentRowRating = row.is_parent !== undefined ? !!row.is_parent : (sku || '').toUpperCase().includes('PARENT');
                    const isValidSku = sku && !isParentRowRating;
                    if (!isValidSku) return;
                    
                    let rating = parseFloat(row.ratings || 0);
                    if (isNaN(rating) || rating <= 0) return;
                    
                    // Apply same filters as above (except rating filter)
                    let inv = parseFloat(row.INV || 0);
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) return;
                    
                    let statusVal = $("#status-filter").val() || 'ENABLED';
                    if (statusVal !== 'ALL' && row.campaignStatus !== statusVal) return;
                    
                    let invFilterVal = $("#inv-filter").val() || '';
                    if (invFilterVal === "OTHERS") {
                        if (inv <= 0) return;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return;
                    }
                    
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (nraFilterVal === 'RA') {
                            if (rowNra === 'NRA') return;
                        } else {
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    
                    // Count by rating range
                    if (rating < 3) {
                        ratingLt3++;
                    } else if (rating >= 3 && rating < 4) {
                        rating3_35++;
                    } else if (rating >= 4 && rating < 4.5) {
                        rating4_45++;
                    } else if (rating >= 4.5) {
                        ratingGte45++;
                    }
                });
                
                // Update rating filter dropdown with counts
                const ratingSelect = document.getElementById('rating-filter');
                if (ratingSelect) {
                    const totalRating = ratingLt3 + rating3_35 + rating4_45 + ratingGte45;
                    ratingSelect.options[0].text = `All Ratings (${totalRating})`;
                    ratingSelect.options[1].text = `< 3 (${ratingLt3})`;
                    ratingSelect.options[2].text = `3 - 3.5 (${rating3_35})`;
                    ratingSelect.options[3].text = `4 - 4.5 (${rating4_45})`;
                    ratingSelect.options[4].text = `≥ 4.5 (${ratingGte45})`;
                }
            }

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
                showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
                    // Reset dropdown to "All" when showing campaigns only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
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
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
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
                    // Reset Pink DIL Paused filter
                    showPinkDilPausedOnly = false;
                    document.getElementById('paused-campaigns-card').style.boxShadow = '';
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
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

            // Paused campaigns card click handler - filter table instead of showing modal
            document.getElementById('paused-campaigns-card').addEventListener('click', function() {
                showPinkDilPausedOnly = !showPinkDilPausedOnly;
                if (showPinkDilPausedOnly) {
                    // Reset dropdown to "All" when showing paused only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset other filters
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
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
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset NRA/RA filters
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
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset RA filter
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
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA filter
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

            // Utilization type dropdown handler
            document.getElementById('utilization-type-select').addEventListener('change', function() {
                currentUtilizationType = this.value;
                // Reset missing filter when dropdown changes
                showMissingOnly = false;
                document.getElementById('missing-campaign-card').style.boxShadow = '';
                // Reset NRA missing filter
                showNraMissingOnly = false;
                document.getElementById('nra-missing-card').style.boxShadow = '';
                // Reset campaign filter
                showCampaignOnly = false;
                document.getElementById('total-campaign-card').style.boxShadow = '';
                // Reset zero INV filter
                showZeroInvOnly = false;
                document.getElementById('zero-inv-card').style.boxShadow = '';
                // Reset NRA/RA filters
                showNraOnly = false;
                document.getElementById('nra-card').style.boxShadow = '';
                showRaOnly = false;
                document.getElementById('ra-card').style.boxShadow = '';
                    
                    if (typeof table !== 'undefined' && table) {
                        // APR BID remains hidden
                        table.hideColumn('apr_bid');

                        // Give Tabulator a brief moment to recalc layout, then apply filter and redraw
                        setTimeout(function() {
                            try {
                                table.redraw(true);
                            } catch (e) {}
                            table.setFilter(combinedFilter);
                            // Force a small reflow to align header/body scrolling
                            const holder = document.querySelector('#budget-under-table .tabulator .tabulator-tableHolder');
                            if (holder) {
                                holder.style.overflowX = 'auto';
                                // nudge scroll to force sync
                                holder.scrollLeft = holder.scrollLeft;
                            }
                            // Update all button counts after filter is applied
                            setTimeout(function() {
                                updateButtonCounts();
                            }, 200);
                        }, 60);
                    }
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/utilized/kw/ads/data",
                layout: "fitDataFill",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                pagination: "local",
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                initialSort: [
                    {column: "acos", dir: "desc"} // Sort by ACOS % highest first
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';
                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent SKU",
                        field: "parent",
                        hozAlign: "left",
                        visible: false
                    },
                    {
                        title: "Active",
                        field: "campaignStatus",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var campaignId = row.campaign_id;
                            var status = row.campaignStatus || 'PAUSED';
                            var isEnabled = status === 'ENABLED';
                            
                            if (!campaignId) {
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
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        hozAlign: "left",
                        minWidth: 180,
                        formatter: function(cell) {
                            let row = cell.getRow().getData();
                            let sku = cell.getValue();
                            let isParentRow = row.is_parent !== undefined ? !!row.is_parent : (sku || '').toUpperCase().includes('PARENT');
                            let skuHtml = isParentRow
                                ? `<strong>${sku}</strong>`
                                : `<span>${sku}</span>`;
                            let iconHtml = isParentRow ? '' : `
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px;"></i>`;
                            return `${skuHtml}${iconHtml}`;
                        }
                    },
                    {
                        title: "Ratings",
                        field: "ratings",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var rating = parseFloat(row.ratings) || 0;
                            var reviews = row.reviews;
                            
                            // Determine color based on rating
                            var ratingColor = '';
                            if (rating > 0 && rating < 3) {
                                ratingColor = '#dc3545'; // red
                            } else if (rating >= 3 && rating < 4) {
                                ratingColor = '#ffc107'; // yellow
                            } else if (rating >= 4 && rating < 4.5) {
                                ratingColor = '#28a745'; // green
                            } else if (rating >= 4.5) {
                                ratingColor = '#e83e8c'; // magenta
                            } else {
                                ratingColor = '#6c757d'; // gray for no rating
                            }
                            
                            if (rating > 0 && reviews) {
                                return '<div style="display: flex; flex-direction: column; align-items: center;">' +
                                       '<div style="color: ' + ratingColor + '; display: flex; align-items: center; gap: 4px;">' +
                                       '<span style="color: ' + ratingColor + ';">★</span>' +
                                       '<span style="color: ' + ratingColor + ';">' + rating + '</span>' +
                                       '</div>' +
                                       '<div style="font-size: 0.85em; color: #666; margin-top: 2px;">' + reviews + ' reviews</div>' +
                                       '</div>';
                            } else if (rating > 0) {
                                return '<div style="color: ' + ratingColor + '; display: flex; align-items: center; gap: 4px; justify-content: center;">' +
                                       '<span style="color: ' + ratingColor + ';">★</span>' +
                                       '<span style="color: ' + ratingColor + ';">' + rating + '</span>' +
                                       '</div>';
                            } else if (reviews) {
                                return '<div style="display: flex; flex-direction: column; align-items: center;">' +
                                       '<div>-</div>' +
                                       '<div style="font-size: 0.85em; color: #666; margin-top: 2px;">' + reviews + ' reviews</div>' +
                                       '</div>';
                            } else {
                                return '-';
                            }
                        },
                        width: 80
                    },
                    {
                        title: "Missing",
                        field: "hasCampaign",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            // Check if campaign exists: hasCampaign field or if campaign_id/campaignName exists
                            const hasCampaign = row.hasCampaign !== undefined 
                                ? row.hasCampaign 
                                : (row.campaign_id && row.campaignName);
                            
                            // Check if NRL is "NRL" (red dot) OR NRA is "NRA" - if so, show yellow dot
                            const nrlValue = row.NRL ? row.NRL.trim() : "";
                            const nraValue = row.NRA ? row.NRA.trim() : "";
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
                        title: "FBA INV",
                        field: "FBA_INV",
                        visible: false,
                        headerSort: false,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return `<div class="text-center">${value || 0}</div>`;
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
                        title: "AL 30",
                        field: "A_L30",
                        visible: false
                    },
                    {
                        title: "A DIL %",
                        field: "A DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const al30 = parseFloat(data.A_L30);
                            const inv = parseFloat(data.INV);
                            if (!isNaN(al30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (al30 / inv);
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
                            var tpft = parseFloat(row.PFT || 0);
                            var roi = parseFloat(row.roi || 0);
                            var tooltipText = "PFT%: " + tpft.toFixed(2) + "%\nROI%: " + roi.toFixed(2) + "%";
                            
                            return `<div class="text-center">$${value.toFixed(2)}<i class="bi bi-info-circle ms-1 info-icon-price-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        },
                        sorter: "number",
                        width: 90
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
                            
                            // getPftColor logic from inc/dec page (same as eBay)
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
                            
                            // getRoiColor logic from inc/dec page (same as eBay)
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
                        editor: "input",
                        editorParams: {
                            elementAttributes: {
                                maxlength: "10"
                            }
                        },
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const currentPrice = parseFloat(rowData.price) || 0;
                            const sprice = parseFloat(value) || 0;
                            
                            if (!value) return '';
                            
                            // ONLY condition: Show blank if price and SPRICE match
                            if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) {
                                return '';
                            }
                            
                            // Show SPRICE when it's different from current price
                            const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                            
                            // If using default price (not custom), show in blue
                            if (hasCustomSprice === false) {
                                return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                            }
                            
                            return formattedValue;
                        },
                        width: 80
                    },
                    {
                        title: "Accept",
                        field: "_accept",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                                <span>Accept</span>
                                <button type="button" class="btn btn-sm apply-all-prices-btn" title="Apply All Selected Prices to Amazon" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;" onclick="event.stopPropagation(); if(typeof applyAllSelectedPrices === 'function') { applyAllSelectedPrices(); }">
                                    <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                </button>
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData.sku;
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            const status = rowData.SPRICE_STATUS || null;
                            
                            if (!sprice || sprice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }
                            
                            // Determine icon and color based on status
                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745'; // Green for apply
                            let titleText = 'Apply Price to Amazon';
                            
                            if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green
                                titleText = 'Price pushed to Amazon (Double-click to mark as Applied)';
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green
                                titleText = 'Price applied to Amazon (Double-click to change)';
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545'; // Red
                                titleText = 'Error applying price to Amazon';
                            } else if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107'; // Yellow
                                titleText = 'Price pushing in progress...';
                            }
                            
                            // Show only icon with color, no background
                            return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                                ${icon}
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            // Handle button click directly in cellClick
                            const $target = $(e.target);
                            if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                                
                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('error', 'Invalid SKU or price');
                                    return;
                                }
                                
                                // Disable button and show loading state (only clock icon)
                                $btn.prop('disabled', true);
                                // Ensure circular styling
                                $btn.css({
                                    'border-radius': '50%',
                                    'width': '35px',
                                    'height': '35px',
                                    'padding': '0',
                                    'display': 'flex',
                                    'align-items': 'center',
                                    'justify-content': 'center'
                                });
                                $btn.html('<i class="fas fa-clock fa-spin" style="color: black;"></i>');
                                
                                // Use retry function
                                applyPriceWithRetry(sku, price, cell, 5, 5000)
                                    .then((result) => {
                                        // Success - update row data with pushed status
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'pushed';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        // Show green tick icon in circular button
                                        $btn.html('<i class="fas fa-check-circle" style="color: black; font-size: 1.1em;"></i>');
                                    })
                                    .catch((error) => {
                                        // Update row data with error status
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.SPRICE_STATUS = 'error';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        // Show error icon in circular button
                                        $btn.html('<i class="fas fa-times" style="color: black;"></i>');
                                        
                                        console.error('Apply price failed after retries:', error);
                                    });
                                return;
                            }
                            // Don't stop propagation for other clicks
                            e.stopPropagation();
                        },
                        cellDblClick: function(e, cell) {
                            // Handle double-click to manually set status to 'applied'
                            const $target = $(e.target);
                            if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const currentStatus = $btn.attr('data-status') || '';
                                
                                // Only allow setting to 'applied' if current status is 'pushed'
                                if (currentStatus === 'pushed') {
                                    $.ajax({
                                        url: '/update-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                        },
                                        data: {
                                            sku: sku,
                                            status: 'applied'
                                        },
                                        success: function(response) {
                                            // Update row data
                                            const row = cell.getRow();
                                            const rowData = row.getData();
                                            rowData.SPRICE_STATUS = 'applied';
                                            row.update(rowData);
                                            showToast('success', 'Status updated to Applied');
                                        },
                                        error: function(xhr) {
                                            showToast('error', 'Failed to update status');
                                        }
                                    });
                                } else if (currentStatus === 'applied') {
                                    // If already applied, show message
                                    showToast('info', 'Price is already marked as Applied');
                                } else {
                                    showToast('info', 'Please push the price first before marking as Applied');
                                }
                            }
                        },
                        width: 80
                    },
                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S PFT",
                        field: "Spft%",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "FBA",
                        field: "FBA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "FBA") {
                                bgColor = "background-color:#007bff;color:#fff;"; // blue
                            } else if (value === "FBM") {
                                bgColor = "background-color:#6f42c1;color:#fff;"; // purple
                            } else if (value === "BOTH") {
                                bgColor = "background-color:#90ee90;color:#000;"; // light green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="FBA"
                                        style="width: 90px; ${bgColor}">
                                    <option value="FBA" ${value === 'FBA' ? 'selected' : ''}>FBA</option>
                                    <option value="FBM" ${value === 'FBM' ? 'selected' : ''}>FBM</option>
                                    <option value="BOTH" ${value === 'BOTH' ? 'selected' : ''}>BOTH</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "SBGT",
                        field: "sbgt",
                        mutator: function (value, data) {
                            var acos = parseFloat(data.acos || 0);
                            var price = parseFloat(data.price || 0);
                            var sbgt;
                            // Same price-based SBGT for all rows (parent now has avg price)
                            if (acos > 20) {
                                sbgt = 1;
                            } else {
                                sbgt = Math.ceil(price * 0.10);
                                if (sbgt < 1) sbgt = 1;
                                if (sbgt > 5) sbgt = 5;
                            }

                            return sbgt; // ✅ sets row.sbgt
                        }

                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend30 = parseFloat(row.l30_spend || 0);
                            var sales30 = parseFloat(row.l30_sales || 0);
                            var acosRaw = row.acos; 
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            // ACOS must be 0 when Spend L30 and Sales L30 are both 0
                            if (spend30 === 0 && sales30 === 0) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            var clicks30 = parseInt(row.l30_clicks || 0).toLocaleString();
                            var spend30 = parseFloat(row.l30_spend || 0).toFixed(0);
                            var sales30 = parseFloat(row.l30_sales || 0).toFixed(0);
                            var adSold30 = parseInt(row.l30_purchases || 0).toLocaleString();
                            var clicks7 = parseInt(row.l7_clicks || 0).toLocaleString();
                            var spend7 = parseFloat(row.l7_spend || 0).toFixed(2);
                            var sales7 = parseFloat(row.l7_sales || 0).toFixed(2);
                            var adSold7 = parseInt(row.l7_purchases || 0).toLocaleString();
                            var tooltipText = "L30: Clicks " + clicks30 + ", Spend " + spend30 + ", Sales " + sales30 + ", Ad Sold " + adSold30 +
                                "\nL7: Clicks " + clicks7 + ", Spend " + spend7 + ", Sales " + sales7 + ", Ad Sold " + adSold7 +
                                "\n(Click info to show/hide Clicks L7, Spend L7, Sales L7, Ad Sold L7 and L30 columns)";
                            
                            var acosDisplay;
                            if (acos === 0) {
                                acosDisplay = "0%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            }
                            return `<div class="text-center">${acosDisplay}<i class="bi bi-info-circle ms-1 info-icon-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        },
                        sorter: "number"
                    },
                    {
                        title: "Clicks L7",
                        field: "l7_clicks",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            var tooltipL7 = "Click info to show/hide Spend L7, Sales L7, Ad Sold L7";
                            return value.toLocaleString() + '<i class="bi bi-info-circle ms-1 info-icon-l7-toggle" style="cursor: pointer; color: #0d6efd;" title="' + tooltipL7 + '"></i>';
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Spend L7",
                        field: "spend_l7_col",
                        hozAlign: "right",
                        visible: false,
                        mutator: function(value, data) { return data.l7_spend ?? 0; },
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Sales L7",
                        field: "l7_sales",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Ad Sold L7",
                        field: "l7_purchases",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Clicks L30",
                        field: "l30_clicks",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Spend L30",
                        field: "l30_spend",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Sales L30",
                        field: "l30_sales",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Ad Sold L30",
                        field: "l30_purchases",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 90
                    },
                    // ACOS View Columns (Target Issue Columns)
                    {
                        title: "KW Issue",
                        field: "target_kw_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "PT Issue",
                        field: "target_pt_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Variation",
                        field: "variation_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Wrong Prod.",
                        field: "incorrect_product_added",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "-ve KW",
                        field: "target_negative_kw_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Review Target",
                        field: "target_review_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "CVR Target",
                        field: "target_cvr_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Content",
                        field: "content_check",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Price Justify",
                        field: "price_justification_check",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Ad Not Req",
                        field: "ad_not_req",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    {
                        title: "Review",
                        field: "review_issue",
                        hozAlign: "center",
                        editor: "tickCross",
                        formatter: "tickCross",
                        visible: false,
                    },
                    // Action Columns
                    {
                        title: "Issue",
                        field: "issue_found",
                        hozAlign: "left",
                        editor: "textarea",
                        visible: false,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value.length > 50) {
                                return value.substring(0, 50) + '...';
                            }
                            return value || '';
                        },
                        cellEdited: function(cell) {
                            var row = cell.getRow();
                            var rowData = row.getData();
                            saveAcosActionHistory(rowData);
                        },
                        width: 200
                    },
                    {
                        title: "Action",
                        field: "action_taken",
                        hozAlign: "left",
                        editor: "textarea",
                        visible: false,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value.length > 50) {
                                return value.substring(0, 50) + '...';
                            }
                            return value || '';
                        },
                        cellEdited: function(cell) {
                            var row = cell.getRow();
                            var rowData = row.getData();
                            saveAcosActionHistory(rowData);
                        },
                        width: 200
                    },
                    {
                        title: "History",
                        field: "acos_history",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var row = cell.getRow();
                            var rowData = row.getData();
                            var campaignId = rowData.campaign_id || rowData.sku;
                            return `<button class="btn btn-sm btn-info view-history-btn" data-campaign-id="${campaignId}" data-sku="${rowData.sku || ''}" style="padding: 2px 8px;">
                                <i class="fa fa-eye"></i>
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest('.view-history-btn')) {
                                var btn = e.target.closest('.view-history-btn');
                                var campaignId = btn.getAttribute('data-campaign-id');
                                var sku = btn.getAttribute('data-sku');
                                showAcosHistory(campaignId, sku);
                            }
                        },
                        width: 80
                    },
                    {
                        title: "AD CVR",
                        field: "ad_cvr",
                        hozAlign: "right",
                        minWidth: 72,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2) + "%";
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            // Same as combinedFilter: use utilization_budget for PARENT (aggregated children), else campaignBudgetAmount
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            // Color logic based on UB7 only (Amazon rules) - same as over/under/correctly filter
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 66) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            // Same as combinedFilter: use utilization_budget for PARENT (aggregated children), else campaignBudgetAmount
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
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
                        }
                    },
                    {
                        title: "AVG CPC",
                        field: "avg_cpc",
                        hozAlign: "center",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var avg_cpc = parseFloat(row.avg_cpc) || 0;
                            return avg_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        minWidth: 72,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "Last SBID",
                        field: "last_sbid",
                        hozAlign: "center",
                        minWidth: 72,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        minWidth: 72,
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            // Get row data
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            
                            // Calculate SBID for row A (same budget logic as combinedFilter: utilization_budget for PARENT)
                            var aL1Cpc = parseFloat(aData.l1_cpc) || 0;
                            var aL7Cpc = parseFloat(aData.l7_cpc) || 0;
                            var aBudget = (aData.utilization_budget != null && aData.utilization_budget !== '') ? parseFloat(aData.utilization_budget) : (parseFloat(aData.campaignBudgetAmount) || 0);
                            var aUb7 = 0;
                            if (aBudget > 0) {
                                aUb7 = (parseFloat(aData.l7_spend) || 0) / (aBudget * 7) * 100;
                            }
                            var aUb1 = 0;
                            if (aBudget > 0) {
                                aUb1 = (parseFloat(aData.l1_spend) || 0) / aBudget * 100;
                            }
                            
                            // Determine utilization type for row A
                            var aRowType = 'all';
                            if (aUb7 > 99 && aUb1 > 99) {
                                aRowType = 'over';
                            } else if (aUb7 < 66 && aUb1 < 66) {
                                aRowType = 'under';
                            } else if (aUb7 >= 66 && aUb7 <= 99 && aUb1 >= 66 && aUb1 <= 99) {
                                aRowType = 'correctly';
                            }
                            
                            var aSbid = 0;
                            var aPrice = parseFloat(aData.price) || 0;
                            var aUb1 = 0;
                            if (aBudget > 0) {
                                aUb1 = (parseFloat(aData.l1_spend) || 0) / aBudget * 100;
                            }
                            
                            // Special case: If UB7 and UB1 = 0%, use price-based default
                            if (aUb7 === 0 && aUb1 === 0) {
                                if (aPrice < 50) {
                                    aSbid = 0.50;
                                } else if (aPrice >= 50 && aPrice < 100) {
                                    aSbid = 1.00;
                                } else if (aPrice >= 100 && aPrice < 200) {
                                    aSbid = 1.50;
                                } else {
                                    aSbid = 2.00;
                                }
                            } else if (aRowType === 'over') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                                var aAvgCpc = parseFloat(aData.avg_cpc) || 0;
                                if (aL1Cpc > 0) {
                                    aSbid = Math.floor(aL1Cpc * 0.90 * 100) / 100;
                                } else if (aL7Cpc > 0) {
                                    aSbid = Math.floor(aL7Cpc * 0.90 * 100) / 100;
                                } else if (aAvgCpc > 0) {
                                    aSbid = Math.floor(aAvgCpc * 0.90 * 100) / 100;
                                } else {
                                    aSbid = 1.00;
                                }
                            } else if (aRowType === 'under') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00
                                var aAvgCpc = parseFloat(aData.avg_cpc) || 0;
                                if (aL1Cpc > 0) {
                                    aSbid = Math.floor(aL1Cpc * 1.10 * 100) / 100;
                                } else if (aL7Cpc > 0) {
                                    aSbid = Math.floor(aL7Cpc * 1.10 * 100) / 100;
                                } else if (aAvgCpc > 0) {
                                    aSbid = Math.floor(aAvgCpc * 1.10 * 100) / 100;
                                } else {
                                    aSbid = 1.00;
                                }
                            }
                            
                            // Apply price-based caps
                            if (aPrice < 10 && aSbid > 0.10) {
                                aSbid = 0.10;
                            } else if (aPrice >= 10 && aPrice < 20 && aSbid > 0.20) {
                                aSbid = 0.20;
                            }
                            
                            // Calculate SBID for row B (same budget logic as combinedFilter: utilization_budget for PARENT)
                            var bL1Cpc = parseFloat(bData.l1_cpc) || 0;
                            var bL7Cpc = parseFloat(bData.l7_cpc) || 0;
                            var bBudget = (bData.utilization_budget != null && bData.utilization_budget !== '') ? parseFloat(bData.utilization_budget) : (parseFloat(bData.campaignBudgetAmount) || 0);
                            var bUb7 = 0;
                            if (bBudget > 0) {
                                bUb7 = (parseFloat(bData.l7_spend) || 0) / (bBudget * 7) * 100;
                            }
                            var bUb1 = 0;
                            if (bBudget > 0) {
                                bUb1 = (parseFloat(bData.l1_spend) || 0) / bBudget * 100;
                            }
                            
                            // Determine utilization type for row B
                            var bRowType = 'all';
                            if (bUb7 > 99 && bUb1 > 99) {
                                bRowType = 'over';
                            } else if (bUb7 < 66 && bUb1 < 66) {
                                bRowType = 'under';
                            } else if (bUb7 >= 66 && bUb7 <= 99 && bUb1 >= 66 && bUb1 <= 99) {
                                bRowType = 'correctly';
                            }
                            
                            var bSbid = 0;
                            var bPrice = parseFloat(bData.price) || 0;
                            
                            // Special case: If UB7 and UB1 = 0%, use price-based default
                            if (bUb7 === 0 && bUb1 === 0) {
                                if (bPrice < 50) {
                                    bSbid = 0.50;
                                } else if (bPrice >= 50 && bPrice < 100) {
                                    bSbid = 1.00;
                                } else if (bPrice >= 100 && bPrice < 200) {
                                    bSbid = 1.50;
                                } else {
                                    bSbid = 2.00;
                                }
                            } else if (bRowType === 'over') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                                var bAvgCpc = parseFloat(bData.avg_cpc) || 0;
                                if (bL1Cpc > 0) {
                                    bSbid = Math.floor(bL1Cpc * 0.90 * 100) / 100;
                                } else if (bL7Cpc > 0) {
                                    bSbid = Math.floor(bL7Cpc * 0.90 * 100) / 100;
                                } else if (bAvgCpc > 0) {
                                    bSbid = Math.floor(bAvgCpc * 0.90 * 100) / 100;
                                } else {
                                    bSbid = 1.00;
                                }
                            } else if (bRowType === 'under') {
                                // Priority: L1 CPC → L7 CPC → AVG CPC → 1.00
                                var bAvgCpc = parseFloat(bData.avg_cpc) || 0;
                                if (bL1Cpc > 0) {
                                    bSbid = Math.floor(bL1Cpc * 1.10 * 100) / 100;
                                } else if (bL7Cpc > 0) {
                                    bSbid = Math.floor(bL7Cpc * 1.10 * 100) / 100;
                                } else if (bAvgCpc > 0) {
                                    bSbid = Math.floor(bAvgCpc * 1.10 * 100) / 100;
                                } else {
                                    bSbid = 1.00;
                                }
                            }
                            
                            // Apply price-based caps
                            if (bPrice < 10 && bSbid > 0.10) {
                                bSbid = 0.10;
                            } else if (bPrice >= 10 && bPrice < 20 && bSbid > 0.20) {
                                bSbid = 0.20;
                            }
                            
                            return aSbid - bSbid;
                        },
                        visible: true,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                            if (!hasCampaign) return '-';
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            // Same as combinedFilter: use utilization_budget for PARENT (aggregated children), else campaignBudgetAmount
                            var budget = (row.utilization_budget != null && row.utilization_budget !== '') ? parseFloat(row.utilization_budget) : (parseFloat(row.campaignBudgetAmount) || 0);
                            var ub7 = 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            
                            var sbid = 0;
                            var price = parseFloat(row.price) || 0;
                            var ub1 = 0;
                            if (budget > 0) {
                                ub1 = (parseFloat(row.l1_spend) || 0) / budget * 100;
                            }
                            
                            // Determine utilization type for this row (same thresholds as over/under/correctly filter)
                            var rowUtilizationType = 'all';
                            if (ub7 > 99 && ub1 > 99) {
                                rowUtilizationType = 'over';
                            } else if (ub7 < 66 && ub1 < 66) {
                                rowUtilizationType = 'under';
                            } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                                rowUtilizationType = 'correctly';
                            }
                            
                            // Special case: If UB7 and UB1 = 0%, use price-based default
                            if (ub7 === 0 && ub1 === 0) {
                                if (price < 50) {
                                    sbid = 0.50;
                                } else if (price >= 50 && price < 100) {
                                    sbid = 1.00;
                                } else if (price >= 100 && price < 200) {
                                    sbid = 1.50;
                                } else {
                                    sbid = 2.00;
                                }
                            } else if (rowUtilizationType === 'over') {
                                // Over-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                                var avg_cpc = parseFloat(row.avg_cpc) || 0;
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else if (avg_cpc > 0) {
                                    sbid = Math.floor(avg_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            } else if (rowUtilizationType === 'under') {
                                // Under-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00
                                var avg_cpc = parseFloat(row.avg_cpc) || 0;
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                } else if (avg_cpc > 0) {
                                    sbid = Math.floor(avg_cpc * 1.10 * 100) / 100;
                                } else {
                                    sbid = 1.00;
                                }
                            } else {
                                // Correctly-utilized or all: no SBID change needed
                                sbid = 0;
                            }
                            
                            // Apply price-based caps (parent rows now have avg price, so apply caps for all rows)
                            if (price < 10 && sbid > 0.10) {
                                sbid = 0.10;
                            } else if (price >= 10 && price < 20 && sbid > 0.20) {
                                sbid = 0.20;
                            }
                            return sbid === 0 ? '-' : sbid;
                        }
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
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var sbidM = parseFloat(row.sbid_m) || 0;
                            var isApproved = row.sbid_approved || false;
                            
                            if (isApproved) {
                                return '<i class="fas fa-check-circle text-success apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="SBID Approved"></i>';
                            } else {
                                return '<i class="fas fa-check text-primary apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="Click to approve SBID"></i>';
                            }
                        },
                        cellClick: function(e, cell) {
                            var row = cell.getRow();
                            var rowData = row.getData();
                            var campaignId = rowData.campaign_id;
                            var sbidM = parseFloat(rowData.sbid_m) || 0;
                            
                            if (!campaignId || sbidM <= 0) {
                                alert('Please enter a valid SBID M value first');
                                return;
                            }
                            
                            // Show loading
                            cell.getElement().innerHTML = '<i class="fas fa-spinner fa-spin text-primary"></i>';
                            
                            $.ajax({
                                url: '/approve-amazon-sbid',
                                method: 'POST',
                                data: {
                                    campaign_id: campaignId,
                                    sbid_m: sbidM,
                                    campaign_type: 'KW',
                                    _token: '{{ csrf_token() }}'
                                },
                                success: function(response) {
                                    if (response.status === 200) {
                                        // Update row data
                                        rowData.sbid_approved = true;
                                        rowData.sbid = sbidM;
                                        row.update(rowData);
                                        
                                        // Update icon to checkmark
                                        cell.getElement().innerHTML = '<i class="fas fa-check-circle text-success apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="SBID Approved"></i>';
                                    } else {
                                        alert('Error: ' + (response.message || 'Failed to approve SBID'));
                                        cell.getElement().innerHTML = '<i class="fas fa-check text-primary apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="Click to approve SBID"></i>';
                                    }
                                },
                                error: function(xhr) {
                                    alert('Error: ' + (xhr.responseJSON?.message || 'Failed to approve SBID'));
                                    cell.getElement().innerHTML = '<i class="fas fa-check text-primary apr-bid-icon" style="cursor: pointer; font-size: 18px;" title="Click to approve SBID"></i>';
                                }
                            });
                        }
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";

                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }

                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${percent}%
                                </span>
                            `;
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        formatter: function(cell) {
                            const value = cell.getValue()?.toUpperCase();
                            let dotColor = '';
                            
                            if (value === 'ENABLED') {
                                dotColor = 'green';
                            } else if (value === 'PAUSED') {
                                dotColor = 'red';
                            } else {
                                dotColor = 'gray';
                            }
                            
                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${value || ''}"></span>
                                </div>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName",
                        minWidth: 220
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-row-btn">APR BID</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-row-btn")) {
                                var rowData = cell.getRow().getData();
                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                                // Same as combinedFilter: use utilization_budget for PARENT (aggregated children), else campaignBudgetAmount
                                var budget = (rowData.utilization_budget != null && rowData.utilization_budget !== '') ? parseFloat(rowData.utilization_budget) : (parseFloat(rowData.campaignBudgetAmount) || 0);
                                var ub7 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                }
                                
                                var sbid = '';
                                var ub1 = budget > 0 ? (parseFloat(rowData.l1_spend) || 0) / budget * 100 : 0;
                                var isOver = (currentUtilizationType === 'pp') || (currentUtilizationType !== 'all' && currentUtilizationType !== 'rr' && currentUtilizationType !== 'gg' && ub7 > 99 && ub1 > 99);
                                var isUnder = (currentUtilizationType === 'rr') || (currentUtilizationType !== 'all' && currentUtilizationType !== 'pp' && currentUtilizationType !== 'gg' && ub7 < 66 && ub1 < 66);
                                if (isOver) {
                                    if (l7_cpc === 0) {
                                        sbid = 0.75;
                                    } else {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    }
                                } else if (isUnder) {
                                    if (ub7 < 70) {
                                        if (ub7 < 10 || l7_cpc === 0 || l1_cpc === 0) {
                                            sbid = 0.75;
                                        } else {
                                            sbid = Math.floor((l1_cpc * 1.10) * 100) / 100;
                                        }
                                    } else {
                                        sbid = '';
                                    }
                                } else {
                                    sbid = '';
                                }
                                if (sbid !== '') {
                                    updateBid(sbid, rowData.campaign_id);
                                }
                            }
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;
                    totalL30Purchases = parseInt(response.total_l30_purchases) || 0;
                    totalSkuCountFromBackend = parseInt(response.total_sku_count) || 0;
                    // Update pagination and filter-based counts (including Total SKU) after data is loaded
                    setTimeout(function() {
                        if (typeof updateButtonCounts === 'function') updateButtonCounts();
                        if (typeof updatePaginationCount === 'function') updatePaginationCount();
                    }, 200);
                    return response.data;
                }
            });

            // 7UB×1UB color zones: g=66-99 (green), p=>99 (pink), r=<66 (red) - same as google-utilized
            function ubZone(ub) {
                if (ub >= 66 && ub <= 99) return 'g';
                if (ub > 99) return 'p';
                return 'r';
            }

            // Combined filter function
            function combinedFilter(data) {
                const skuStr = (data.sku != null) ? (data.sku + '') : '';
                const isParentRow = data.is_parent !== undefined ? !!data.is_parent : skuStr.toUpperCase().includes('PARENT');
                const typeFilter = $("#sku-type-filter").val() || '';
                // Type filter: All, Parent, Sku
                if (typeFilter === 'parent') {
                    if (!isParentRow) return false;
                    // Fall through so utilization type and other filters still apply to parent rows
                } else if (typeFilter === 'sku') {
                    if (isParentRow) return false;
                }
                // PARENT rows fall through to search, utilization, and other filters (no early bypass)
                let acos = parseFloat(data.acos || 0);
                // Use utilization_budget for PARENT rows (aggregated children budget) for ub7/ub1, else campaignBudgetAmount
                let budget = (data.utilization_budget != null && data.utilization_budget !== '') ? parseFloat(data.utilization_budget) : (parseFloat(data.campaignBudgetAmount) || 0);
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                let rowAcos = parseFloat(acos) || 0;

                // Check if campaign is missing
                const hasCampaign = data.hasCampaign !== undefined 
                    ? data.hasCampaign 
                    : (data.campaign_id && data.campaignName);

                // Apply campaign filters
                if (showCampaignOnly) {
                    // Show only rows with campaigns
                    if (!hasCampaign) return false;
                } else if (showMissingOnly) {
                    // Show only rows without campaigns AND exclude yellow dots (NRL='NRL' OR NRA='NRA')
                    if (hasCampaign) return false;
                    // Exclude yellow dots: if NRL='NRL' OR NRA='NRA', don't show
                    const nrlValueForFilter = data.NRL ? data.NRL.trim() : "";
                    const nraValueForFilter = data.NRA ? data.NRA.trim() : "";
                    if (nrlValueForFilter === 'NRL' || nraValueForFilter === 'NRA') return false;
                } else if (showNraMissingOnly) {
                    // Show only rows without campaigns AND with NRA = 'NRA' (yellow dots only)
                    if (hasCampaign) return false;
                    const nraValueForNraMissing = data.NRA ? data.NRA.trim() : "";
                    if (nraValueForNraMissing !== 'NRA') return false;
                }

                // Pink DIL Paused filter - show only campaigns that were paused by pink DIL cron (all paused campaigns, even without hasCampaign)
                if (showPinkDilPausedOnly) {
                    const pinkDilPausedAt = data.pink_dil_paused_at;
                    if (!pinkDilPausedAt) return false; // Show only if pink_dil_paused_at is not null/empty
                }

                // Apply utilization type filter (7UB×1UB color combos: gg, gp, gr, pg, pp, pr, rg, rp, rr - same as google-utilized)
                if (currentUtilizationType === 'all') {
                    // Show all data (no filter on utilization)
                } else {
                    // When utilization type is selected (one of 9 combos), exclude missing items (red and yellow)
                    if (!hasCampaign) return false;
                    
                    // Exclude yellow dots (NRL='NRL' OR NRA='NRA') when utilization type is selected
                    const nrlValueForUtil = data.NRL ? data.NRL.trim() : "";
                    const nraValueForUtil = data.NRA ? data.NRA.trim() : "";
                    if (nrlValueForUtil === 'NRL' || nraValueForUtil === 'NRA') return false;
                    
                    // Exclude paused campaigns when utilization type is selected
                    const campaignStatus = data.campaignStatus || 'PAUSED';
                    if (campaignStatus !== 'ENABLED') return false;
                    
                    // Exclude rows with no valid budget (budget 0 or NaN) from utilization filter
                    if (!(budget > 0) || isNaN(budget)) return false;
                    
                    const z7 = ubZone(ub7), z1 = ubZone(ub1);
                    if ((z7 + z1) !== currentUtilizationType) return false;
                }

                // Global search filter
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // Status filter: default ENABLED only; use "All" to see all campaigns
                let statusVal = $("#status-filter").val() || 'ENABLED';
                if (statusVal !== 'ALL') {
                    if (statusVal === 'ENABLED' && !hasCampaign) return false;
                    if (data.campaignStatus !== statusVal) return false;
                }

                // ACOS filter (sbgt-filter dropdown)
                let acosFilterVal = $("#sbgt-filter").val();
                if (acosFilterVal) {
                        let acosVal = parseFloat(data.acos || 0);
                    if (isNaN(acosVal)) acosVal = 0;
                    
                    // Special filter for ACOS > 35% AND SPEND > 10
                    if (acosFilterVal === 'acos35spend10') {
                        let spendVal = parseFloat(data.l30_spend || 0);
                        // Show only items where ACOS > 35% AND spend > 10
                        if (acosVal <= 35 || spendVal <= 10 || isNaN(spendVal)) {
                            return false;
                        }
                    } else {
                        // Map dropdown values to ACOS ranges
                        // value="8" → ACOS < 5%
                        // value="7" → ACOS 5-9%
                        // value="6" → ACOS 10-14%
                        // value="5" → ACOS 15-19%
                        // value="4" → ACOS 20-24%
                        // value="3" → ACOS 25-29%
                        // value="2" → ACOS 30-34%
                        // value="1" → ACOS ≥ 35%
                        let match = false;
                        if (acosFilterVal === '8') {
                            // ACOS < 5%
                            if (acosVal >= 0 && acosVal < 5) match = true;
                        } else if (acosFilterVal === '7') {
                            // ACOS 5-9%
                            if (acosVal >= 5 && acosVal < 10) match = true;
                        } else if (acosFilterVal === '6') {
                            // ACOS 10-14%
                            if (acosVal >= 10 && acosVal < 15) match = true;
                        } else if (acosFilterVal === '5') {
                            // ACOS 15-19%
                            if (acosVal >= 15 && acosVal < 20) match = true;
                        } else if (acosFilterVal === '4') {
                            // ACOS 20-24%
                            if (acosVal >= 20 && acosVal < 25) match = true;
                        } else if (acosFilterVal === '3') {
                            // ACOS 25-29%
                            if (acosVal >= 25 && acosVal < 30) match = true;
                        } else if (acosFilterVal === '2') {
                            // ACOS 30-34%
                            if (acosVal >= 30 && acosVal < 35) match = true;
                        } else if (acosFilterVal === '1') {
                            // ACOS ≥ 35%
                            if (acosVal >= 35) match = true;
                        }
                        
                        if (!match) return false;
                    }
                }

                // Apply zero INV filter first (if enabled)
                let inv = parseFloat(data.INV || 0);
                if (showZeroInvOnly) {
                    // Show only zero or negative inventory
                    if (inv > 0) return false;
                } else {
                // Inventory filter (ALL or empty = show all; OTHERS = INV > 0; INV_0 = only 0) - same logic as updateButtonCounts so count matches table
                let invFilterVal = $("#inv-filter").val() || '';
                if (invFilterVal === "OTHERS") {
                    if (inv <= 0) return false;
                } else if (invFilterVal === "INV_0") {
                    if (inv !== 0) return false;
                }
                }
                // ALL or empty: show all (no inv filter)

                // Apply NRA/RA filters first (if enabled)
                // Note: Empty/null NRA defaults to "RA" in the display
                let rowNra = data.NRA ? data.NRA.trim() : "";
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

                // Apply price slab filter
                let priceSlabFilterVal = $("#price-slab-filter").val();
                if (priceSlabFilterVal) {
                    let price = parseFloat(data.price || 0);
                    if (isNaN(price)) return false;
                    
                    if (priceSlabFilterVal === 'lt10') {
                        if (price >= 10) return false;
                    } else if (priceSlabFilterVal === '10-20') {
                        if (price < 10 || price >= 20) return false;
                    } else if (priceSlabFilterVal === '20-30') {
                        if (price < 20 || price >= 30) return false;
                    } else if (priceSlabFilterVal === '30-50') {
                        if (price < 30 || price >= 50) return false;
                    } else if (priceSlabFilterVal === '50-100') {
                        if (price < 50 || price >= 100) return false;
                    } else if (priceSlabFilterVal === 'gt100') {
                        if (price < 100) return false;
                    }
                }

                // Apply rating filter
                let ratingFilterVal = $("#rating-filter").val();
                if (ratingFilterVal) {
                    let rating = parseFloat(data.ratings || 0);
                    if (isNaN(rating) || rating <= 0) return false;
                    
                    if (ratingFilterVal === 'lt3') {
                        if (rating >= 3) return false;
                    } else if (ratingFilterVal === '3-3.5') {
                        if (rating < 3 || rating >= 4) return false;
                    } else if (ratingFilterVal === '4-4.5') {
                        if (rating < 4 || rating >= 4.5) return false;
                    } else if (ratingFilterVal === 'gte4.5') {
                        if (rating < 4.5) return false;
                    }
                }

                // Apply multi-range filters
                // 1UB range filter
                let ub1Min = $("#1ub-min").val();
                let ub1Max = $("#1ub-max").val();
                if (ub1Min || ub1Max) {
                    let budget = parseFloat(data.campaignBudgetAmount) || 0;
                    let l1_spend = parseFloat(data.l1_spend || 0);
                    let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                    
                    if (ub1Min && ub1 < parseFloat(ub1Min)) return false;
                    if (ub1Max && ub1 > parseFloat(ub1Max)) return false;
                }

                // 7UB range filter
                let ub7Min = $("#7ub-min").val();
                let ub7Max = $("#7ub-max").val();
                if (ub7Min || ub7Max) {
                    let budget = parseFloat(data.campaignBudgetAmount) || 0;
                    let l7_spend = parseFloat(data.l7_spend || 0);
                    let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                    
                    if (ub7Min && ub7 < parseFloat(ub7Min)) return false;
                    if (ub7Max && ub7 > parseFloat(ub7Max)) return false;
                }

                // Lbid range filter (using last_sbid)
                let lbidMin = $("#lbid-min").val();
                let lbidMax = $("#lbid-max").val();
                if (lbidMin || lbidMax) {
                    let lastSbid = parseFloat(data.last_sbid || 0);
                    if (isNaN(lastSbid)) lastSbid = 0;
                    
                    if (lbidMin && lastSbid < parseFloat(lbidMin)) return false;
                    if (lbidMax && lastSbid > parseFloat(lbidMax)) return false;
                }

                // ACOS range filter
                let acosMin = $("#acos-min").val();
                let acosMax = $("#acos-max").val();
                if (acosMin || acosMax) {
                    let acos = parseFloat(data.acos || 0);
                    if (isNaN(acos)) acos = 0;
                    
                    if (acosMin && acos < parseFloat(acosMin)) return false;
                    if (acosMax && acos > parseFloat(acosMax)) return false;
                }

                return true;
            }

            // Handle SPRICE cell editing - attach right after table initialization
            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                if (field === 'SPRICE') {
                    const sku = data.sku;
                    // Clean the value - remove $ sign and any whitespace
                    let cleanValue = String(value).replace(/[$\s]/g, '');
                    cleanValue = parseFloat(cleanValue) || 0;
                    
                    $.ajax({
                        url: '/save-amazon-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: cleanValue
                        },
                        success: function(response) {
                            showToast('success', 'SPRICE updated successfully');
                            
                            // Update row data with new values
                            const updateData = {
                                'SPRICE': cleanValue,
                                'has_custom_sprice': true,
                                'SPRICE_STATUS': null // Reset status so formatter shows/hides based on price match
                            };
                            
                            if (response.sgpft_percent !== undefined) {
                                updateData['SGPFT'] = response.sgpft_percent;
                            }
                            if (response.spft_percent !== undefined) {
                                updateData['Spft%'] = response.spft_percent;
                            }
                            if (response.sroi_percent !== undefined) {
                                updateData['SROI'] = response.sroi_percent;
                            }
                            
                            // Update row with all data at once
                            row.update(updateData);
                            
                            // Force redraw of the entire row to ensure all formatters run
                            row.reformat();
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update SPRICE');
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
                        url: '/save-amazon-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: campaignId,
                            sbid_m: cleanValue,
                            campaign_type: 'KW'
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                showToast('success', 'SBID M saved successfully');
                                // Reset approval status when SBID M changes
                                data.sbid_approved = false;
                                data.sbid_m = cleanValue;
                                cell.getRow().update(data);
                                // Explicitly reformat the APR BID cell to show unapproved icon
                                var aprBidCell = cell.getRow().getCell('apr_bid');
                                if (aprBidCell) {
                                    aprBidCell.reformat();
                                }
                            } else {
                                showToast('error', response.message || 'Failed to save SBID M');
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'Failed to save SBID M';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            } else if (xhr.status === 404) {
                                errorMsg = 'Campaign not found. Please ensure the campaign exists.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error. Please try again.';
                            }
                            showToast('error', errorMsg);
                            console.error('SBID M save error:', xhr);
                        }
                    });
                }
            });

            // Handle campaign status toggle
            document.addEventListener("change", function(e) {
                if(e.target.classList.contains("campaign-status-toggle")) {
                    let campaignId = e.target.getAttribute("data-campaign-id");
                    let isEnabled = e.target.checked;
                    let newStatus = isEnabled ? 'ENABLED' : 'PAUSED';
                    
                    if(!campaignId) {
                        alert("Campaign ID not found!");
                        e.target.checked = !isEnabled; // Revert toggle
                        return;
                    }
                    
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    
                    fetch('/toggle-amazon-sp-campaign-status', {
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
                        if(data.status === 200){
                            // Update the row data
                            let rows = table.getRows();
                            for(let i = 0; i < rows.length; i++) {
                                let rowData = rows[i].getData();
                                if(rowData.campaign_id === campaignId) {
                                    rows[i].update({campaignStatus: newStatus});
                                    break;
                                }
                            }
                        } else {
                            alert("Error: " + (data.message || "Failed to update campaign status"));
                            e.target.checked = !isEnabled; // Revert toggle
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Request failed: " + err.message);
                        e.target.checked = !isEnabled; // Revert toggle
                    })
                    .finally(() => {
                        if (overlay) {
                            overlay.style.display = "none";
                        }
                    });
                }
            });

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);
                
                // Ensure APR BID remains hidden
                table.hideColumn('apr_bid');

                // ACOS info icon: toggle all 8 detail columns (Clicks L7, Spend L7, Sales L7, Ad Sold L7, Clicks L30, Spend L30, Sales L30, Ad Sold L30)
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('info-icon-toggle')) {
                        e.stopPropagation();
                        // ACOS info: only Clicks L7 + L30 columns (Spend L7, Sales L7, Ad Sold L7 stay hidden; toggle via Clicks L7 info)
                        var acosDetailFields = ['l7_clicks', 'l30_clicks', 'l30_spend', 'l30_sales', 'l30_purchases'];
                        var firstCol = table.getColumn('l7_clicks');
                        var anyVisible = firstCol && typeof firstCol.isVisible === 'function' && firstCol.isVisible();
                        acosDetailFields.forEach(function(fieldName) {
                            var col = table.getColumn(fieldName);
                            if (col) {
                                if (anyVisible) {
                                    if (typeof col.hide === 'function') col.hide(); else table.hideColumn(fieldName);
                                } else {
                                    if (typeof col.show === 'function') col.show(); else table.showColumn(fieldName);
                                }
                            }
                        });
                    }
                    // Clicks L7 info icon: toggle only Spend L7, Sales L7, Ad Sold L7
                    if (e.target.classList.contains('info-icon-l7-toggle')) {
                        e.stopPropagation();
                        var l7DetailFields = ['spend_l7_col', 'l7_sales', 'l7_purchases'];
                        var spendL7Col = table.getColumn('spend_l7_col');
                        var anyL7Visible = spendL7Col && typeof spendL7Col.isVisible === 'function' && spendL7Col.isVisible();
                        l7DetailFields.forEach(function(fieldName) {
                            var col = table.getColumn(fieldName);
                            if (col) {
                                if (anyL7Visible) {
                                    if (typeof col.hide === 'function') col.hide(); else table.hideColumn(fieldName);
                                } else {
                                    if (typeof col.show === 'function') col.show(); else table.showColumn(fieldName);
                                }
                            }
                        });
                    }
                    // Price info icon toggle for PFT%, ROI%, GPFT, SPRICE, Accept, S GPFT, S PFT, SROI
                    if (e.target.classList.contains('info-icon-price-toggle')) {
                        e.stopPropagation();
                        var pftCol = table.getColumn('PFT');
                        var roiCol = table.getColumn('roi');
                        
                        // Toggle visibility
                        if (pftCol.isVisible()) {
                            table.hideColumn('PFT');
                            table.hideColumn('roi');
                            table.hideColumn('GPFT');
                            table.hideColumn('SPRICE');
                            table.hideColumn('_accept');
                            table.hideColumn('SGPFT');
                            table.hideColumn('Spft%');
                            table.hideColumn('SROI');
                        } else {
                            table.showColumn('PFT');
                            table.showColumn('roi');
                            table.showColumn('GPFT');
                            table.showColumn('SPRICE');
                            table.showColumn('_accept');
                            table.showColumn('SGPFT');
                            table.showColumn('Spft%');
                            table.showColumn('SROI');
                        }
                    }
                    
                    // INV info icon toggle for extra columns (FBA_INV, L30, DIL%, etc.)
                    if (e.target.classList.contains('info-icon-inv-toggle')) {
                        e.stopPropagation();
                        const extraColumnFields = ['FBA_INV', 'L30', 'DIL %', 'A_L30', 'A DIL %', 'NRL', 'NRA'];
                        
                        // Check if any column is visible to determine current state
                        const anyVisible = table.getColumn('FBA_INV').isVisible();
                        
                        // Toggle visibility
                        extraColumnFields.forEach(field => {
                            if (anyVisible) {
                                table.hideColumn(field);
                            } else {
                                table.showColumn(field);
                            }
                        });
                        
                        // Update icon color
                        if (anyVisible) {
                            e.target.style.color = '#6366f1';
                        } else {
                            e.target.style.color = '#10b981';
                        }
                    }
                });

                // Update counts when data is filtered (debounced)
                let filterTimeout = null;
                table.on("dataFiltered", function(filteredRows) {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                });

                // Debounced search for filters section
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                // Debounced search for table search section
                let tableSearchTimeout = null;
                $("#global-search-table").on("keyup", function() {
                    if (tableSearchTimeout) clearTimeout(tableSearchTimeout);
                    tableSearchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                        updatePaginationCount();
                    }, 300);
                });

                // Range filter event listeners
                $("#1ub-min, #1ub-max, #7ub-min, #7ub-max, #lbid-min, #lbid-max, #acos-min, #acos-max").on("input", function() {
                    if (typeof table !== 'undefined' && table) {
                        table.setFilter(combinedFilter);
                        table.redraw(true);
                        updateButtonCounts();
                    }
                });

                $("#status-filter, #inv-filter, #sku-type-filter, #nra-filter, #sbgt-filter, #price-slab-filter, #rating-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                    // Update counts when filter changes - use longer timeout to ensure filter is applied
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 300);
                });

                // INC/DEC SBID variables
                let incDecType = 'value'; // 'value' or 'percentage'
                
                // INC/DEC SBID handlers
                // Dropdown selection handler
                $("#inc-dec-dropdown .dropdown-item").on("click", function(e) {
                    e.preventDefault();
                    incDecType = $(this).data('type');
                    var labelText = incDecType === 'value' ? 'Value (e.g., +0.5 or -0.5)' : 'Percentage (e.g., +10 or -10)';
                    $("#inc-dec-label").text(incDecType === 'value' ? 'Value' : 'Percentage');
                    $("#inc-dec-input").attr('placeholder', labelText);
                    $("#inc-dec-btn").text(incDecType === 'value' ? 'INC/DEC (By Value)' : 'INC/DEC (By %)');
                });
                
                // Helper function to get Last SBID (last_sbid) value for a row - used as base value for INC/DEC
                function getCurrentSbid(rowData) {
                    // Use Last SBID (last_sbid) as the base value
                    var lastSbid = rowData.last_sbid;
                    
                    // Check if Last SBID is empty, null, 0, or invalid
                    if (!lastSbid || lastSbid === '' || lastSbid === '0' || lastSbid === 0) {
                        return null; // No Last SBID value available
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
                        showToast('warning', 'Please select at least one row to apply increment/decrement');
                        return;
                    }
                    
                    // Prepare data for bulk save
                    var campaignSbidMap = {}; // { campaign_id: new_sbid_m }
                    var rowsToUpdate = []; // Store rows for later update
                    
                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var campaignId = rowData.campaign_id;
                        var campaignType = 'KW'; // KW for this file
                        
                        // Skip rows without campaign_id
                        if (!campaignId) {
                            return;
                        }
                        
                        // Get Last SBID (last_sbid) as base value
                        var currentLbid = getCurrentSbid(rowData);
                        if (currentLbid === null || currentLbid === 0) {
                            return; // Skip rows with no Last SBID value
                        }
                        
                        // Calculate new SBID based on Last SBID and INC/DEC type
                        var newSbid = 0;
                        if (incDecType === 'value') {
                            // By value: new = Last SBID + input
                            newSbid = currentLbid + incDecValue;
                        } else {
                            // By percentage: new = Last SBID * (1 + input/100)
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
                        rowsToUpdate.push({ row: row, campaignId: campaignId, newSbid: newSbid, campaignType: campaignType });
                    });
                    
                    if (Object.keys(campaignSbidMap).length === 0) {
                        showToast('warning', 'No selected rows with valid Last SBID and campaign ID found');
                        return;
                    }
                    
                    // Show progress overlay if available
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }
                    
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
                        var rowInfo = campaignRowMap[campaignId];
                        var savePromise = $.ajax({
                            url: '/save-amazon-sbid-m',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                campaign_id: campaignId,
                                sbid_m: newSbidValue,
                                campaign_type: rowInfo.campaignType
                            }
                        }).then(function(response) {
                            return { campaignId: campaignId, response: response, success: true };
                        }).catch(function(error) {
                            return { campaignId: campaignId, error: error, success: false };
                        });
                        savePromises.push(savePromise);
                    });
                    
                    // Wait for all saves to complete
                    Promise.all(savePromises).then(function(results) {
                        var successCount = 0;
                        var errorCount = 0;
                        
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                // Update row data using campaign ID to find the correct row
                                var rowInfo = campaignRowMap[result.campaignId];
                                if (rowInfo) {
                                    var rowData = rowInfo.row.getData();
                                    var currentData = JSON.parse(JSON.stringify(rowData));
                                    currentData.sbid_m = rowInfo.newSbid;
                                    // Clear any approval status when sbid_m is updated
                                    if (currentData.sbid === currentData.sbid_m) {
                                        // If sbid equals sbid_m, keep approval status
                                    } else {
                                        // Clear approval status
                                        currentData.sbid = currentData.sbid_m;
                                    }
                                    rowInfo.row.update(currentData);
                                    setTimeout(function() {
                                        rowInfo.row.reformat();
                                    }, 50);
                                }
                            } else {
                                errorCount++;
                                console.error('Error saving SBID M for campaign:', result.campaignId, result.error || result.response);
                            }
                        });
                        
                        if (overlay) {
                            overlay.style.display = "none";
                        }
                        
                        if (successCount > 0) {
                            showToast('success', 'SBID M saved successfully for ' + successCount + ' campaign(s)');
                            // Redraw table to ensure all updates are visible
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to save SBID M values');
                        }
                        
                        if (errorCount > 0) {
                            console.warn('Some campaigns failed to save:', errorCount);
                        }
                    }).catch(function(error) {
                        if (overlay) {
                            overlay.style.display = "none";
                        }
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
                $(document).on("click", "#clear-sbid-m-btn", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get only selected rows
                    var selectedRows = table.getRows('selected');
                    if (selectedRows.length === 0) {
                        showToast('warning', 'Please select at least one row to clear SBID M');
                        return;
                    }
                    
                    // Confirm before clearing
                    var confirmClear = confirm('Are you sure you want to clear SBID M for ' + selectedRows.length + ' selected row(s)?');
                    if (!confirmClear) {
                        return;
                    }
                    
                    // Prepare campaign IDs
                    var campaignIds = [];
                    var campaignRowMap = {};
                    
                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var campaignId = rowData.campaign_id;
                        
                        if (campaignId) {
                            campaignIds.push(String(campaignId).trim());
                            campaignRowMap[campaignId] = row;
                        }
                    });
                    
                    if (campaignIds.length === 0) {
                        showToast('warning', 'No selected rows with valid campaign ID found');
                        return;
                    }
                    
                    // Clear sbid_m by setting to empty string - will need backend endpoint or handle in frontend
                    // For now, update frontend directly and show message
                    campaignIds.forEach(function(campaignId) {
                        var row = campaignRowMap[campaignId];
                        if (row) {
                            var rowData = row.getData();
                            var currentData = JSON.parse(JSON.stringify(rowData));
                            currentData.sbid_m = ''; // Clear sbid_m
                            row.update(currentData);
                            setTimeout(function() {
                                row.reformat();
                            }, 50);
                        }
                    });
                    
                    showToast('info', 'SBID M cleared in display for ' + campaignIds.length + ' row(s). Database update requires backend endpoint implementation.');
                    table.redraw(true);
                });

                // ACOS View Button Handler
                $(document).on("click", "#acos-view-btn", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // ACOS view columns
                    var acosViewColumns = [
                        'target_kw_issue',
                        'target_pt_issue',
                        'variation_issue',
                        'incorrect_product_added',
                        'target_negative_kw_issue',
                        'target_review_issue',
                        'target_cvr_issue',
                        'content_check',
                        'price_justification_check',
                        'ad_not_req',
                        'review_issue',
                        'issue_found',
                        'action_taken',
                        'acos_history'
                    ];
                    
                    // Check if columns are currently visible (check first column)
                    var isVisible = false;
                    try {
                        var column = table.getColumn('target_kw_issue');
                        if (column) {
                            isVisible = column.isVisible();
                        }
                    } catch(e) {
                        // Column might not exist, assume hidden
                        isVisible = false;
                    }
                    
                    // Toggle visibility
                    acosViewColumns.forEach(function(field) {
                        try {
                            if (isVisible) {
                                table.hideColumn(field);
                            } else {
                                table.showColumn(field);
                            }
                        } catch(e) {
                            console.log('Column not found: ' + field);
                        }
                    });
                    
                    if (!isVisible) {
                        // Sort by ACOS highest to lowest when showing
                        table.setSort([
                            {column: "acos", dir: "desc"}
                        ]);
                        showToast('success', 'ACOS view columns shown and sorted by ACOS (highest to lowest)');
                    } else {
                        showToast('info', 'ACOS view columns hidden');
                    }
                });
                
                // APR ALL SBID button handler
                document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }

                    // Get only actually selected rows
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

                    if (selectedCampaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
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
                    var rowBidMap = [];

                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var sbidM = parseFloat(rowData.sbid_m) || 0;
                        
                        if (sbidM > 0 && rowData.campaign_id) {
                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbidM);
                            rowBidMap.push({
                                row: row,
                                campaignId: rowData.campaign_id,
                                bid: sbidM
                            });
                        }
                    });

                    if (campaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'No valid campaigns with SBID M value found');
                        return;
                    }

                    // Approve all bids
                    var approvePromises = [];
                    rowBidMap.forEach(function(item) {
                        var approvePromise = $.ajax({
                            url: '/approve-amazon-sbid',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                campaign_id: item.campaignId,
                                sbid_m: item.bid,
                                campaign_type: 'KW'
                            }
                        }).then(function(response) {
                            return { campaignId: item.campaignId, response: response, success: true, item: item };
                        }).catch(function(error) {
                            return { campaignId: item.campaignId, error: error, success: false, item: item };
                        });
                        approvePromises.push(approvePromise);
                    });
                    
                    Promise.all(approvePromises).then(function(results) {
                        var successCount = 0;
                        var errorCount = 0;
                        
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                // Update row data
                                var item = result.item;
                                var rowData = item.row.getData();
                                var currentData = JSON.parse(JSON.stringify(rowData));
                                currentData.sbid = item.bid;
                                currentData.sbid_m = item.bid;
                                item.row.update(currentData);
                                setTimeout(function() {
                                    item.row.reformat();
                                }, 50);
                            } else {
                                errorCount++;
                                console.error('Error approving SBID for campaign:', result.campaignId, result.error || result.response);
                            }
                        });
                        
                        if (overlay) overlay.style.display = "none";
                        
                        if (successCount > 0) {
                            showToast('success', 'SBID approved successfully for ' + successCount + ' campaign(s)');
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to approve SBID values');
                        }
                        
                        if (errorCount > 0) {
                            console.warn('Some campaigns failed to approve:', errorCount);
                        }
                    }).catch(function(error) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Error approving SBID values');
                        console.error('Error in bulk approve:', error);
                    });
                });
                
                // SAVE ALL SBID M button handler
                document.getElementById("save-all-sbid-m-btn").addEventListener("click", function() {
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) {
                        overlay.style.display = "flex";
                    }

                    // Get only actually selected rows
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

                    if (selectedCampaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Please select at least one campaign');
                        return;
                    }
                    
                    // Prompt for SBID M value
                    var sbidMValue = prompt('Enter SBID M value for all selected campaigns:');
                    if (!sbidMValue || sbidMValue.trim() === '') {
                        if (overlay) overlay.style.display = "none";
                        return;
                    }

                    var cleanValue = parseFloat(sbidMValue.replace(/[$\s]/g, '')) || 0;
                    if (cleanValue <= 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'SBID M must be greater than 0');
                        return;
                    }
                    
                    // Save all campaigns
                    var savePromises = [];
                    selectedCampaignIds.forEach(function(campaignId) {
                        var savePromise = $.ajax({
                            url: '/save-amazon-sbid-m',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                campaign_id: campaignId,
                                sbid_m: cleanValue,
                                campaign_type: 'KW'
                            }
                        }).then(function(response) {
                            return { campaignId: campaignId, response: response, success: true };
                        }).catch(function(error) {
                            return { campaignId: campaignId, error: error, success: false };
                        });
                        savePromises.push(savePromise);
                    });
                    
                    Promise.all(savePromises).then(function(results) {
                        var successCount = 0;
                        var errorCount = 0;
                        
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                // Update row data
                                var rows = table.getRows().filter(function(row) {
                                    return row.getData().campaign_id === result.campaignId;
                                });
                                rows.forEach(function(row) {
                                    var rowData = row.getData();
                                    var currentData = JSON.parse(JSON.stringify(rowData));
                                    currentData.sbid_m = cleanValue;
                                    row.update(currentData);
                                    setTimeout(function() {
                                        row.reformat();
                                    }, 50);
                                });
                            } else {
                                errorCount++;
                                console.error('Error saving SBID M for campaign:', result.campaignId, result.error || result.response);
                            }
                        });
                        
                        if (overlay) overlay.style.display = "none";
                        
                        if (successCount > 0) {
                            showToast('success', 'SBID M saved successfully for ' + successCount + ' campaign(s)');
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to save SBID M values');
                        }
                        
                        if (errorCount > 0) {
                            console.warn('Some campaigns failed to save:', errorCount);
                        }
                    }).catch(function(error) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Error saving SBID M values');
                        console.error('Error in bulk save:', error);
                    });
                });

                // Initial update of all button counts after data loads
                setTimeout(function() {
                    updateButtonCounts();
                    updatePaginationCount();
                }, 1000);
                
                // Also call updatePaginationCount immediately after table is built
                updatePaginationCount();
            });

            table.on("rowSelectionChanged", function(data, rows) {
                // Show/hide APR ALL SBID and SAVE ALL SBID M buttons based on selection
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                    document.getElementById("save-all-sbid-m-btn").classList.remove("d-none");
                } else {
                document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                    document.getElementById("save-all-sbid-m-btn").classList.add("d-none");
                }
            });

            // Update pagination count on page changes
            table.on("pageLoaded", function(page) {
                updatePaginationCount();
            });

            table.on("pageSizeChanged", function(pageSize) {
                setTimeout(updatePaginationCount, 100);
            });

            table.on("dataLoaded", function(data) {
                setTimeout(updatePaginationCount, 100);
            });

            table.on("dataFiltered", function(filteredRows) {
                setTimeout(updatePaginationCount, 100);
            });

            table.on("dataProcessed", function() {
                setTimeout(updatePaginationCount, 100);
            });

            // Function to update pagination count display
            function updatePaginationCount() {
                try {
                    if (typeof table === 'undefined' || !table) {
                        console.warn('Table not defined in updatePaginationCount');
                        return;
                    }
                    
                    // Calculate actual filtered count to match pagination
                    const filteredData = table.getData('active');
                    if (!filteredData || filteredData.length === undefined) {
                        console.warn('No filtered data available');
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
                        console.log('Pagination count updated:', startRow, endRow, totalRows);
                    } else {
                        console.warn('Pagination count element not found');
                    }
                } catch (e) {
                    console.error('Error updating pagination count:', e);
                }
            }

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

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

                    // Update color immediately for FBA field
                    if (field === 'FBA') {
                        if (value === 'FBA') {
                            e.target.style.backgroundColor = '#007bff'; // blue
                            e.target.style.color = '#fff';
                        } else if (value === 'FBM') {
                            e.target.style.backgroundColor = '#6f42c1'; // purple
                            e.target.style.color = '#fff';
                        } else if (value === 'BOTH') {
                            e.target.style.backgroundColor = '#90ee90'; // light green
                            e.target.style.color = '#000';
                        }
                    }

                    // Use different endpoint for NRL field
                    let endpoint = '/update-amazon-nr-nrl-fba';

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
                        if (data.success && typeof table !== 'undefined' && table) {
                            let row = table.searchRows('sku', '=', sku);
                            if (row.length > 0) {
                                row[0].update({[field]: value});
                            }
                        }
                    })
                    .catch(err => console.error(err));
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let colsToToggle = ["INV", "FBA_INV", "L30", "DIL %", "A_L30", "A DIL %", "NRL", "NRA"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            // Batch APR SBGT handler
            // document.getElementById("apr-all-sbgt-btn").addEventListener("click", function() {
            //     const overlay = document.getElementById("progress-overlay");
            //     overlay.style.display = "flex";

            //     var filteredData = table.getSelectedRows();
            //     var campaignIds = [];
            //     var bgts = [];

            //     filteredData.forEach(function(row) {
            //         var rowEl = row.getElement();
            //         if (rowEl && rowEl.offsetParent !== null) {
            //             var rowData = row.getData();
            //             var acos = parseFloat(rowData.acos || 0);

            //             if (acos > 0) {
            //                 // Compute SBGT based on ACOS (same rule as display)
            //                 var sbgtValue;
            //                 if (acos < 5) {
            //                     sbgtValue = 8;
            //                 } else if (acos < 10) {
            //                     sbgtValue = 7;
            //                 } else if (acos < 15) {
            //                     sbgtValue = 6;
            //                 } else if (acos < 20) {
            //                     sbgtValue = 5;
            //                 } else if (acos < 25) {
            //                     sbgtValue = 4;
            //                 } else if (acos < 30) {
            //                     sbgtValue = 3;
            //                 } else if (acos < 35) {
            //                     sbgtValue = 2;
            //                 } else {
            //                     sbgtValue = 1;
            //                 }

            //                 campaignIds.push(rowData.campaign_id);
            //                 bgts.push(sbgtValue);
            //             }
            //         }
            //     });

            //     fetch('/update-amazon-campaign-bgt-price', {
            //         method: 'PUT',
            //         headers: {
            //             'Content-Type': 'application/json',
            //             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            //         },
            //         body: JSON.stringify({
            //             campaign_ids: campaignIds,
            //             bgts: bgts
            //         })
            //     })
            //     .then(res => res.json())
            //     .then(data => {
            //         if (data.status === 200) {
            //             alert("Campaign budget updated successfully!");
            //         } else {
            //             alert("Something went wrong: " + data.message);
            //         }
            //     })
            //     .catch(err => {
            //         console.error(err);
            //         alert("Request failed: " + err.message);
            //     })
            //     .finally(() => {
            //         overlay.style.display = "none";
            //     });
            // });

            // function updateBgt(sbgtValue, campaignId) {
            //     const overlay = document.getElementById("progress-overlay");
            //     overlay.style.display = "flex";

            //     fetch('/update-amazon-campaign-bgt-price', {
            //         method: 'PUT',
            //         headers: {
            //             'Content-Type': 'application/json',
            //             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            //         },
            //         body: JSON.stringify({
            //             campaign_ids: [campaignId],
            //             bgts: [sbgtValue]
            //         })
            //     })
            //     .then(res => res.json())
            //     .then(data => {
            //         if (data.status === 200) {
            //             alert("Campaign budget updated successfully!");
            //         } else {
            //             alert("Something went wrong: " + data.message);
            //         }
            //     })
            //     .catch(err => {
            //         console.error(err);
            //         alert("Request failed: " + err.message);
            //     })
            //     .finally(() => {
            //         overlay.style.display = "none";
            //     });
            // }

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bids: [aprBid]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        let errorMsg = data.message || "Something went wrong";
                        if (errorMsg.includes("Premium Ads")) {
                            alert("Error: " + errorMsg);
                        } else {
                            alert("Something went wrong: " + errorMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error updating bid");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            // Load counts
            loadUtilizationCounts();

            // Add click handlers to utilization cards
            document.querySelectorAll('.utilization-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    showUtilizationChart(type);
                });
            });

            // ACOS Action History Functions
            function saveAcosActionHistory(rowData) {
                var campaignId = rowData.campaign_id || rowData.sku;
                if (!campaignId) return;

                var targetIssues = {
                    target_kw_issue: rowData.target_kw_issue || false,
                    target_pt_issue: rowData.target_pt_issue || false,
                    variation_issue: rowData.variation_issue || false,
                    incorrect_product_added: rowData.incorrect_product_added || false,
                    target_negative_kw_issue: rowData.target_negative_kw_issue || false,
                    target_review_issue: rowData.target_review_issue || false,
                    target_cvr_issue: rowData.target_cvr_issue || false,
                    content_check: rowData.content_check || false,
                    price_justification_check: rowData.price_justification_check || false,
                    ad_not_req: rowData.ad_not_req || false,
                    review_issue: rowData.review_issue || false
                };

                $.ajax({
                    url: '/amazon/save-acos-action-history',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        campaign_id: campaignId,
                        sku: rowData.sku || '',
                        issue_found: rowData.issue_found || '',
                        action_taken: rowData.action_taken || '',
                        target_issues: JSON.stringify(targetIssues),
                        campaign_type: 'KW'
                    },
                    success: function(response) {
                        if (response.status === 200) {
                            console.log('ACOS action history saved successfully');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error saving ACOS action history:', xhr);
                    }
                });
            }

            function showAcosHistory(campaignId, sku) {
                $.ajax({
                    url: '/amazon/get-acos-action-history',
                    method: 'GET',
                    data: {
                        campaign_id: campaignId,
                        sku: sku,
                        campaign_type: 'KW'
                    },
                    success: function(response) {
                        if (response.status === 200) {
                            var history = response.history || [];
                            var historyHtml = '<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">';
                            historyHtml += '<table class="table table-sm table-bordered">';
                            historyHtml += '<thead class="table-light"><tr>';
                            historyHtml += '<th>Date</th><th>Issue Found</th><th>Action Taken</th><th>Target Issues</th>';
                            historyHtml += '</tr></thead><tbody>';
                            
                            if (history.length === 0) {
                                historyHtml += '<tr><td colspan="4" class="text-center">No history found</td></tr>';
                            } else {
                                history.forEach(function(item) {
                                    var targetIssues = JSON.parse(item.target_issues || '{}');
                                    var issuesList = Object.keys(targetIssues).filter(key => targetIssues[key]).join(', ') || 'None';
                                    historyHtml += '<tr>';
                                    historyHtml += '<td>' + (item.created_at || '') + '</td>';
                                    historyHtml += '<td>' + (item.issue_found || '') + '</td>';
                                    historyHtml += '<td>' + (item.action_taken || '') + '</td>';
                                    historyHtml += '<td><small>' + issuesList + '</small></td>';
                                    historyHtml += '</tr>';
                                });
                            }
                            
                            historyHtml += '</tbody></table></div>';
                            
                            // Show modal
                            var modalHtml = `
                                <div class="modal fade" id="acosHistoryModal" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">ACOS Action History - ${sku || campaignId}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                ${historyHtml}
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Remove existing modal if any
                            $('#acosHistoryModal').remove();
                            
                            // Add modal to body
                            $('body').append(modalHtml);
                            
                            // Show modal
                            var modal = new bootstrap.Modal(document.getElementById('acosHistoryModal'));
                            modal.show();
                            
                            // Remove modal from DOM when hidden
                            $('#acosHistoryModal').on('hidden.bs.modal', function() {
                                $(this).remove();
                            });
                        }
                    },
                    error: function(xhr) {
                        alert('Error loading history: ' + (xhr.responseJSON?.message || 'Failed to load history'));
                    }
                });
            }
        });

        let utilizationChartInstance = null;

        function loadUtilizationCounts() {
            fetch('/amazon/get-utilization-counts?type=KW')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        // 7UB count: sum of all utilization types based on 7UB only
                        const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0) + (data.correctly_utilized_7ub || 0);
                        // 7UB + 1UB count: sum of all utilization types based on both 7UB and 1UB
                        const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0) + (data.correctly_utilized_7ub_1ub || 0);
                        
                        document.getElementById('7ub-count').textContent = count7ub || 0;
                        document.getElementById('7ub-1ub-count').textContent = count7ub1ub || 0;
                        
                        // Button counts are now calculated from table data, not from database
                        // updateButtonCounts() will be called after table loads
                    }
                })
                .catch(err => console.error('Error loading counts:', err));
        }

        function showUtilizationChart(type) {
            const chartTitle = document.getElementById('chart-title');
            const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));
            
            const titles = {
                '7ub': '7UB Utilization Trend (Last 30 Days)',
                '7ub-1ub': '7UB + 1UB Utilization Trend (Last 30 Days)'
            };
            chartTitle.textContent = titles[type] || 'Utilization Trend';

            modal.show();

            fetch('/amazon/get-utilization-chart-data?type=KW&condition=' + type)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);
                        
                        const ctx = document.getElementById('utilizationChart').getContext('2d');
                        
                        if (utilizationChartInstance) {
                            utilizationChartInstance.destroy();
                        }

                        // Show all 3 lines (over, under, correctly) based on condition type
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
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
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
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub_1ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
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
        
        // Toggle extra columns functionality via INV info icon
        let extraColumnsVisible = false;
        const extraColumnFields = ['FBA_INV', 'L30', 'DIL %', 'A_L30', 'A DIL %', 'NRL', 'NRA'];
        
        // This code is already inside document.addEventListener("DOMContentLoaded")
        // so table variable is accessible here
        if (typeof table !== 'undefined') {
            table.on('tableBuilt', function() {
                const invInfoIcon = document.getElementById('inv-info-icon');
                if (invInfoIcon) {
                invInfoIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    extraColumnsVisible = !extraColumnsVisible;
                    
                    extraColumnFields.forEach(field => {
                        if (extraColumnsVisible) {
                            table.showColumn(field);
                        } else {
                            table.hideColumn(field);
                        }
                    });
                    
                    // Update icon appearance
                    if (extraColumnsVisible) {
                        this.style.color = '#10b981';
                    } else {
                        this.style.color = '#6366f1';
                    }
                });
            }
        });
        }

        // Retry function for applying price with up to 5 attempts
        function applyPriceWithRetry(sku, price, cell, maxRetries = 5, delay = 5000) {
            return new Promise((resolve, reject) => {
                let attempt = 0;
                
                function attemptApply() {
                    attempt++;
                    
                    $.ajax({
                        url: '/apply-amazon-price',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            price: price
                        },
                        success: function(response) {
                            // Check for errors in response
                            if (response.errors && response.errors.length > 0) {
                                const errorMsg = response.errors[0].message || 'Unknown error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                
                                // Check if it's an authentication error - don't retry immediately
                                if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || errorMsg.includes('Client authentication failed')) {
                                    // For auth errors, wait longer before retry (10 seconds)
                                    if (attempt < maxRetries) {
                                        console.log(`Auth error - waiting longer before retry ${attempt} for SKU ${sku}...`);
                                        setTimeout(attemptApply, 10000);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku} due to auth error`);
                                        reject({ error: true, response: response, isAuthError: true });
                                    }
                                } else {
                                    // For other errors, retry with normal delay
                                    if (attempt < maxRetries) {
                                        console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({ error: true, response: response });
                                    }
                                }
                            } else {
                                // Success
                                resolve({ success: true, response: response });
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                            console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                            
                            // Check if it's an authentication error
                            if (errorMsg.includes('authentication') || errorMsg.includes('invalid_client') || errorMsg.includes('401') || xhr.status === 401 || errorMsg.includes('Client authentication failed')) {
                                // For auth errors, wait longer before retry
                                if (attempt < maxRetries) {
                                    console.log(`Auth error - waiting longer before retry ${attempt} for SKU ${sku}...`);
                                    setTimeout(attemptApply, 10000);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku} due to auth error`);
                                    reject({ error: true, xhr: xhr, isAuthError: true });
                                }
                            } else {
                                // For other errors, retry with normal delay
                                if (attempt < maxRetries) {
                                    console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                    setTimeout(attemptApply, delay);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku}`);
                                    reject({ error: true, xhr: xhr });
                                }
                            }
                        }
                    });
                }
                
                attemptApply();
            });
        }

        // Toast notification function
        function showToast(type, message) {
            // Create toast container if it doesn't exist
            if (!$('.toast-container').length) {
                $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
            }
            
            const toast = $(`
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        }

    </script>
@endsection
