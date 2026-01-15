@extends('layouts.vertical', ['title' => 'Ebay 2 - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        'page_title' => 'Ebay 2 - Utilized',
        'sub_title' => 'Ebay 2 - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-4">
                    <div class="mb-4">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Type Filter and Count Cards Row -->
                                <div class="row g-4 align-items-end mb-3 pb-3 border-bottom">
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
                                    <div class="col-md-10">
                                        <label class="form-label fw-semibold mb-2 d-block"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 flex-wrap align-items-center">
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total
                                                    SKU</span>
                                                <span class="fw-bold" id="total-sku-count"
                                                    style="font-size: 1.1rem;">0</span>
                            </div>
                                            <div class="badge-count-item ebay-sku-card" id="ebay-sku-card"
                                                style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Ebay
                                                    SKU</span>
                                                <span class="fw-bold" id="ebay-sku-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item total-campaign-card" id="total-campaign-card"
                                                style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Campaign</span>
                                                <span class="fw-bold" id="total-campaign-count"
                                                    style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item missing-campaign-card" id="missing-campaign-card"
                                                style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Missing</span>
                                                <span class="fw-bold" id="missing-campaign-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item nra-missing-card" id="nra-missing-card"
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA
                                                    MISSING</span>
                                                <span class="fw-bold" id="nra-missing-count"
                                                    style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item zero-inv-card" id="zero-inv-card"
                                                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Zero
                                                    INV</span>
                                                <span class="fw-bold" id="zero-inv-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item nra-card" id="nra-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item nrl-missing-card" id="nrl-missing-card"
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRL
                                                    MISSING</span>
                                                <span class="fw-bold" id="nrl-missing-count"
                                                    style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item nrl-card" id="nrl-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRL</span>
                                                <span class="fw-bold" id="nrl-count" style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item ra-card" id="ra-card"
                                                style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">RA</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem;">0</span>
                                    </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub"
                                                style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB</span>
                                                <span class="fw-bold" id="7ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub-1ub"
                                                style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB +
                                                    1UB</span>
                                                <span class="fw-bold" id="7ub-1ub-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">L30 CLICKS</span>
                                                <span class="fw-bold" id="l30-total-clicks"
                                                    style="font-size: 1.1rem;">0</span>
                                </div>
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">L30 SPEND</span>
                                                <span class="fw-bold" id="l30-total-spend"
                                                    style="font-size: 1.1rem;">0</span>
                                </div>
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">L30 AD SOLD</span>
                                                <span class="fw-bold" id="l30-total-ad-sold"
                                                    style="font-size: 1.1rem;">0</span>
                                </div>
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">AVG ACOS</span>
                                                <span class="fw-bold" id="avg-acos"
                                                    style="font-size: 1.1rem;">0</span>
                                </div>
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">AVG CVR</span>
                                                <span class="fw-bold" id="avg-cvr"
                                                    style="font-size: 1.1rem;">0</span>
                                </div>
                                </div>
                            </div>
                        </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row g-3 align-items-end mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-search me-1" style="color: #64748b;"></i>Search Campaign
                                        </label>
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
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>SKU Type
                                        </label>
                                        <select id="sku-type-select" class="form-select form-select-md">
                                            <option value="all">All</option>
                                            <option value="parent">Parent</option>
                                            <option value="sku" selected>SKU</option>
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
                                            <option value="" selected>INV > 0</option>
                                    <option value="ALL">ALL</option>
                                    <option value="INV_0">0 INV</option>
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
                                    <div class="col-md-4 d-flex gap-2 align-items-end mt-2">
                                        <button id="apr-all-sbid-btn" class="btn btn-info btn-sm flex-fill d-none">
                                            <i class="fa-solid fa-check-double me-1"></i>
                                            APR ALL SBID
                                        </button>
                                        <button id="bulk-update-sbid-m-btn" class="btn btn-warning btn-sm flex-fill d-none">
                                            <i class="fa-solid fa-save me-1"></i>
                                            Bulk Update SBID M
                                        </button>
                                        <button id="export-data-btn" class="btn btn-success btn-sm">
                                            <i class="fa-solid fa-download me-1"></i>
                                            Export Data
                                        </button>
                                    </div>
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
            let currentSkuType = 'sku'; // Default to SKU (hide PARENT by default)
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showNrlMissingOnly = false; // Filter for NRL missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showNrlOnly = false; // Filter for NRL only
            let showRaOnly = false; // Filter for RA only
            let showEbaySkuOnly = false; // Filter for eBay SKUs only
            let processedEbaySkus = new Set(); // Track processed SKUs when showing eBay SKUs only (for uniqueness)
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;
            let totalSkuCountFromBackend = 0; // Store total SKU count from backend
            let ebaySkuCountFromBackend = 0; // Store eBay SKU count from backend
            let totalCampaignCountFromBackend = 0; // Store total campaign count from backend
            let zeroInvCountFromBackend = 0; // Store zero INV count from backend

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

                // Track processed SKUs to avoid counting duplicates
                const processedSkusForNra = new Set(); // Track SKUs for NRA/RA counting
                const processedSkusForNrl = new Set(); // Track SKUs for NRL/REQ counting
                const processedSkusForCampaign = new Set(); // Track SKUs for campaign counting
                const processedSkusForMissing = new Set(); // Track SKUs for missing counting
                const processedSkusForNraMissing = new Set(); // Track SKUs for NRA missing counting
                const processedSkusForNrlMissing = new Set(); // Track SKUs for NRL missing counting
                const processedSkusForOver = new Set(); // Track SKUs for over-utilized counting
                const processedSkusForUnder = new Set(); // Track SKUs for under-utilized counting
                const processedSkusForCorrectly = new Set(); // Track SKUs for correctly-utilized counting
                const processedSkusForValid = new Set(); // Track SKUs for valid SKU counting
                const processedSkusFor7Ub = new Set(); // Track SKUs for 7UB counting
                const processedSkusFor7Ub1Ub = new Set(); // Track SKUs for 7UB+1UB counting

                    allData.forEach(function(row) {
                    // Count valid SKUs (exclude PARENT SKUs, exclude empty SKUs)
                    const sku = row.sku || '';
                    const normalizedSku = sku.toUpperCase().trim();
                    const isParentSku = normalizedSku.startsWith('PARENT');
                    const isValidSku = sku && !isParentSku; // Valid SKU = non-PARENT SKU

                    // Count zero/negative inventory (INV <= 0)
                    // Count from allData to get total count (before filters)
                    // This count represents all rows with INV <= 0 that pass basic validation (non-PARENT SKUs)
                    let inv = parseFloat(row.INV || 0);
                    if (inv <= 0 && isValidSku) {
                        zeroInvCount++;
                    }

                    // Count campaigns, missing, and NRA BEFORE filters - count all regardless of filters
                    if (isValidSku) {
                        // Count NRA only for valid SKUs and only once per SKU - BEFORE filters
                        // RA will be calculated as Total - NRA later
                        if (!processedSkusForNra.has(sku)) {
                            processedSkusForNra.add(sku);
                            // Count NRA: explicitly marked as 'NRA' OR if NRL is 'NR'/'NRL' (which means NRA should be set)
                            let rowNra = row.NR ? row.NR.trim() : "";
                            let rowNrl = row.NRL ? row.NRL.trim() : "";
                            // Count as NRA if NR='NRA' OR if NRL='NR'/'NRL' (since NRL='NR' means NRA should be set)
                            if (rowNra === 'NRA' || rowNrl === 'NR' || rowNrl === 'NRL') {
                                nraCount++;
                            }
                            // Note: We don't increment raCount here - it will be calculated as Total - NRA
                        }
                        
                        // Count NRL only for valid SKUs and only once per SKU - BEFORE filters
                        if (!processedSkusForNrl.has(sku)) {
                            processedSkusForNrl.add(sku);
                            // Note: Empty/null NRL defaults to "REQ" in the display
                            // Count as NRL if value is 'NRL' or 'NR' (since dropdown stores 'NR' but database may have 'NRL')
                            let rowNrl = row.NRL ? row.NRL.trim() : "";
                            if (rowNrl === 'NRL' || rowNrl === 'NR') {
                                nrlCount++;
                            }
                        }

                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row
                            .campaign_id && row.campaignName);

                        if (hasCampaign) {
                            // Count campaign only once per SKU - BEFORE filters
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                totalCampaignCount++;
                            }
                        } else {
                            // Count missing only once per SKU - BEFORE filters
                            if (!processedSkusForMissing.has(sku)) {
                                processedSkusForMissing.add(sku);
                                let rowNrlForMissing = row.NRL ? row.NRL.trim() : "";
                                let rowNraForMissing = row.NR ? row.NR.trim() : "";
                                let inv = parseFloat(row.INV || 0);
                                
                                // Only count as missing (red dot) if neither NRL='NRL'/'NR' nor NRA='NRA'
                                if (rowNrlForMissing !== 'NRL' && rowNrlForMissing !== 'NR' && rowNraForMissing !== 'NRA') {
                                    // Count as missing (red dot) if INV > 0
                                    if (inv > 0) {
                                        missingCount++;
                                    }
                                } else {
                                    // Count NRL missing (yellow dots) separately - both 'NR' and 'NRL' are treated as NRL
                                    // Count ALL rows where NRL is 'NRL' or 'NR' (regardless of NRA value)
                                    if ((rowNrlForMissing === 'NRL' || rowNrlForMissing === 'NR') && !processedSkusForNrlMissing.has(sku)) {
                                        processedSkusForNrlMissing.add(sku);
                                        nrlMissingCount++;
                                    }
                                    // Count NRA missing (yellow dots) separately
                                    // If NRL='NRL'/'NR', NRA should be 'NRA', so count it as NRA missing too
                                    if (rowNraForMissing === 'NRA' && !processedSkusForNraMissing.has(sku)) {
                                        processedSkusForNraMissing.add(sku);
                                        nraMissingCount++;
                                    } else if ((rowNrlForMissing === 'NRL' || rowNrlForMissing === 'NR') && !processedSkusForNraMissing.has(sku)) {
                                        // If NRL='NRL'/'NR' but NRA is not 'NRA', still count as NRA missing
                                        // because NRA should be 'NRA' when NRL is 'NRL'/'NR'
                                        processedSkusForNraMissing.add(sku);
                                        nraMissingCount++;
                                    }
                                }
                            }
                        }
                    }

                    // Apply all filters except utilization type filter
                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) {
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
                            if (rowNrl === 'NRL' || rowNrl === 'NR') return;
                        } else {
                            // For "NRL", exact match
                            if (rowNrl !== nrlFilterVal && rowNrl !== 'NR') return;
                        }
                    }

                    // eBay SKU filter - show only SKUs that have campaign
                    if (showEbaySkuOnly) {
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                        if (!hasCampaign) return;
                    }

                    // Count valid SKUs that pass all filters
                    if (isValidSku) {
                        validSkuCount++;
                    }

                    // Now calculate utilization and count - only for rows with campaigns
                    const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
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
                            if (inv > 0) {
                                underCount++;
                            }
                        } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                            correctlyCount++;
                        }

                        // Count 7UB (ub7 >= 70 && ub7 <= 90) - only for rows with campaigns
                        if (ub7 >= 70 && ub7 <= 90 && !processedSkusFor7Ub.has(sku)) {
                            processedSkusFor7Ub.add(sku);
                            ub7Count++;
                        }

                        // Count 7UB + 1UB (both ub7 and ub1 >= 70 && <= 90) - only for rows with campaigns
                        if (ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90 && !processedSkusFor7Ub1Ub.has(sku)) {
                            processedSkusFor7Ub1Ub.add(sku);
                            ub7Ub1Count++;
                        }
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

                // Update total campaign count - use backend count if available, otherwise use calculated count
                const totalCampaignCountEl = document.getElementById('total-campaign-count');
                if (totalCampaignCountEl) {
                    if (totalCampaignCountFromBackend > 0) {
                        totalCampaignCountEl.textContent = totalCampaignCountFromBackend;
                    } else {
                        totalCampaignCountEl.textContent = totalCampaignCount;
                    }
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

                // Update RA count - RA = Total SKUs - NRA
                const raCountEl = document.getElementById('ra-count');
                if (raCountEl) {
                    // Use backend total count (255) as base, subtract NRA count
                    var totalCount = totalSkuCountFromBackend > 0 ? totalSkuCountFromBackend : (nraCount + raCount);
                    var calculatedRaCount = totalCount - nraCount;
                    // Ensure RA count is not negative
                    calculatedRaCount = calculatedRaCount >= 0 ? calculatedRaCount : 0;
                    raCountEl.textContent = calculatedRaCount;
                }

                // Update zero INV count
                // If INV_0 filter is selected, use filtered data count to match pagination
                const zeroInvCountEl = document.getElementById('zero-inv-count');
                if (zeroInvCountEl) {
                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal === "INV_0") {
                        // When INV_0 filter is selected, count from active/filtered data to match pagination
                        let activeData = table.getData('active');
                        let filteredZeroInvCount = 0;
                        activeData.forEach(function(row) {
                            let inv = parseFloat(row.INV || 0);
                            if (inv <= 0) {
                                filteredZeroInvCount++;
                            }
                        });
                        zeroInvCountEl.textContent = filteredZeroInvCount;
                    } else {
                        // When INV_0 filter is not selected, use calculated count from allData
                    zeroInvCountEl.textContent = zeroInvCount;
                    }
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

                // Update total SKU count - Always use backend count (255) if available
                const totalSkuCountEl = document.getElementById('total-sku-count');
                if (totalSkuCountEl) {
                    // Use backend count (255) as primary source
                    totalSkuCountEl.textContent = totalSkuCountFromBackend > 0 ? totalSkuCountFromBackend : validSkuCount;
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
                    utilizationSelect.options[1].text = `Over Utilized (${currentUtilizationType === 'over' ? actualOverCount : overCount})`;
                    utilizationSelect.options[2].text = `Under Utilized (${currentUtilizationType === 'under' ? actualUnderCount : underCount})`;
                    utilizationSelect.options[3].text = `Correctly Utilized (${currentUtilizationType === 'correctly' ? actualCorrectlyCount : correctlyCount})`;
                }
            }

            // Function to calculate and update L30 totals (clicks, spend, ad_sold)
            // Variables to store L30 totals from backend
            let totalL30ClicksFromBackend = 0;
            let totalL30SpendFromBackend = 0;
            let totalL30AdSoldFromBackend = 0;

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
                        // Clear processed SKUs set before filtering (filter will rebuild it)
                        if (showEbaySkuOnly) {
                            processedEbaySkus.clear();
                        }
                        // Update SBID column visibility based on utilization type
                        if (currentUtilizationType === 'correctly') {
                            table.hideColumn('sbid');
                        } else {
                            table.showColumn('sbid');
                        }
                        table.setFilter(combinedFilter);
                        table.redraw(true);
                        updateButtonCounts();
                        updateL30Totals();
                    }
                });
            }

            // SKU type dropdown handler
            const skuTypeSelect = document.getElementById('sku-type-select');
            if (skuTypeSelect) {
                skuTypeSelect.addEventListener('change', function() {
                    currentSkuType = this.value;
                    if (typeof table !== 'undefined' && table) {
                        // Clear processed SKUs set before filtering (filter will rebuild it)
                        if (showEbaySkuOnly) {
                            processedEbaySkus.clear();
                        }
                        table.setFilter(combinedFilter);
                        table.redraw(true);
                            updateButtonCounts();
                        updateL30Totals();
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
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
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
                    this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
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
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
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
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
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
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
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
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
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
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
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
                }
            });

            // eBay SKU card click handler
            document.getElementById('ebay-sku-card').addEventListener('click', function() {
                showEbaySkuOnly = !showEbaySkuOnly;
                // Clear processed SKUs set when toggling filter
                processedEbaySkus.clear();
                
                if (showEbaySkuOnly) {
                    // Reset dropdown to "All" when showing eBay SKU only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset SKU type filter to "All" to show all SKUs with campaigns
                    document.getElementById('sku-type-select').value = 'all';
                    currentSkuType = 'all';
                    // Reset inventory filter to "ALL" to show all campaigns regardless of inventory
                    document.getElementById('inv-filter').value = 'ALL';
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
                    this.style.boxShadow = '0 4px 12px rgba(139, 92, 246, 0.5)';
                } else {
                    this.style.boxShadow = '';
                    // Reset inventory filter back to default when disabling filter
                    document.getElementById('inv-filter').value = '';
                    // Reset SKU type filter back to default
                    document.getElementById('sku-type-select').value = 'sku';
                    currentSkuType = 'sku';
                }

                if (typeof table !== 'undefined' && table) {
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    processedEbaySkus.clear();
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Store edited SBID M values separately to prevent cross-contamination
            // This object maintains edited values per row using unique identifier (campaign_id + sku)
            var editedSbidMValues = {}; // key: unique row id (campaign_id + sku), value: edited sbid_m value
            
            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay-2/utilized/ads/data",
                ajaxParams: {
                    all_campaigns: true,
                    limit: 10000
                },
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                pagination: true,
                paginationMode: "local",
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100, 200, 500],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    // totalRows from Tabulator is already the filtered row count after all filters are applied
                    // Use it directly instead of recalculating
                    var endRow = Math.min(currentRow + pageSize - 1, totalRows);
                    return "Showing " + currentRow + " to " + endRow + " of " + totalRows;
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';
                    if (sku.toUpperCase().includes("PARENT")) {
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

                            if ((nrlValue === 'NR' || nrlValue === 'NRL') || nraValue === 'NRA') {
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
                        width: 80
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: true,
                        width: 60
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: true,
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

                                if (percent < 16.66) {
                                    textColor = '#dc3545'; // red
                                } else if (percent >= 16.66 && percent < 25) {
                                    textColor = '#b8860b'; // darker yellow (darkgoldenrod)
                                } else if (percent >= 25 && percent < 50) {
                                    textColor = '#28a745'; // green
                                } else {
                                    textColor = '#e83e8c'; // pink
                                }

                                return `<div class="text-center"><span style="color: ${textColor}; font-weight: bold;">${Math.round(percent)}%</span></div>`;
                            }
                            return `<div class="text-center"><span style="color: #dc3545; font-weight: bold;">0%</span></div>`;
                        },
                        visible: true,
                        width: 60
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            let value = cell.getValue() || "REQ"; // Default to REQ
                            // Normalize: if value is "NRL", treat it as "NR" for display
                            if (value === 'NRL') {
                                value = 'NR';
                            }

                            return `
                                    <select class="form-select form-select-sm editable-select" 
                                            data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="REQ" ${(value === 'REQ' ? 'selected' : '')}>🟢</option>
                                    <option value="NR" ${((value === 'NR' || value === 'NRL') ? 'selected' : '')}>🔴</option>
                                    </select>
                                `;
                        },
                        hozAlign: "center",
                        visible: true,
                        width: 70
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const rowData = row.getData();
                            // If NRL is 'NR' or 'NRL' (red dot), default to NRA, otherwise default to RA
                            const nrlValue = rowData.NRL || "REQ";
                            const defaultValue = (nrlValue === 'NR' || nrlValue === 'NRL') ? "NRA" : "RA";
                            const value = (cell.getValue()?.trim()) || defaultValue;

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${(value === 'RA' ? 'selected' : '')}>🟢</option>
                                    <option value="NRA" ${(value === 'NRA' ? 'selected' : '')}>🔴</option>
                                    <option value="LATER" ${(value === 'LATER' ? 'selected' : '')}>🟡</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: true,
                        width: 70
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName",
                        visible: false
                    },
                    {
                        title: "EBAY L30",
                        field: "ebay_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                        width: 80
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
                        title: "CVR",
                        field: "cvr",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0) + "%";
                        },
                        sorter: "number",
                        width: 70
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0),
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
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

                            return '<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">' + acosValue + '<i class="fa-solid fa-info-circle toggle-metrics-btn" style="cursor: pointer; font-size: 12px; margin-left: 5px;" title="Toggle Clicks, Spend, Ad Sold"></i></div>';
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('toggle-metrics-btn') || e.target.closest('.toggle-metrics-btn')) {
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
                            
                            // Different color logic based on utilization type (ebay2 specific)
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: Check UB7 only
                                if (ub7 >= 70 && ub7 <= 90) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 90) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 70) {
                                    td.classList.add('red-bg');
                                }
                            } else {
                                // Under-utilized and Correctly-utilized: Only check UB7 (no ACOS check)
                                if (ub7 >= 70 && ub7 <= 90) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 90) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 70) {
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
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
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
                                var ub7 = budget > 0 ? (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100 : 0;
                                var ub1 = budget > 0 ? (parseFloat(rowData.l1_spend) || 0) / budget * 100 : 0;
                                var sbid = 0;
                                
                                // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50 (ebay2 doesn't have price field)
                                if (ub7 === 0 && ub1 === 0) {
                                    return 0.50;
                                }
                                
                                // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                                if (ub7 > 99 && ub1 > 99) {
                                    if (l1Cpc > 0) {
                                        return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    } else if (l7Cpc > 0) {
                                        return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    } else {
                                        return 0;
                                    }
                                }
                                
                                if (currentUtilizationType === 'over') {
                                    // If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                    var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                    if (cpcToUse > 1.25) {
                                        sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                    } else if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    // Under-utilized SBID calculation rules
                                    if (ub7 === 0 && ub1 === 0) {
                                        sbid = 0.50;
                                    } else {
                                        var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                        if (cpcToUse > 0) {
                                            if (cpcToUse < 0.10) {
                                                sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                            } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                            } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                            } else {
                                                sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                            }
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                } else if (currentUtilizationType === 'correctly') {
                                    // Correctly-utilized: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                    if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                } else {
                                    // For 'all' type, determine utilization status and apply appropriate rule
                                    var rowAcos = parseFloat(rowData.acos) || 0;
                                    if (isNaN(rowAcos) || rowAcos === 0) {
                                        rowAcos = 100;
                                    }
                                    
                                    // Determine utilization status
                                    var isOverUtilized = false;
                                    var isUnderUtilized = false;
                                    
                                    // Check over-utilized first (priority 1) - Double condition: both UB7 AND UB1 must be > 99
                                    if (ub7 > 99 && ub1 > 99) {
                                        isOverUtilized = true;
                                    }
                                    
                                    // Check under-utilized (priority 2: only if not over-utilized) - Double condition: both UB7 AND UB1 must be < 66
                                    if (!isOverUtilized && ub7 < 66 && ub1 < 66) {
                                        isUnderUtilized = true;
                                    }
                                    
                                    // Apply SBID logic based on determined status
                                    if (isOverUtilized) {
                                        var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                        if (cpcToUse > 1.25) {
                                            sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                        } else if (cpcToUse > 0) {
                                            sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    } else if (isUnderUtilized) {
                                        if (ub7 === 0 && ub1 === 0) {
                                            sbid = 0.50;
                                        } else {
                                            var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                            if (cpcToUse > 0) {
                                                if (cpcToUse < 0.10) {
                                                    sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                                } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                    sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                                } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                    sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                                } else {
                                                    sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                                }
                                            } else {
                                                sbid = 0.50; // Fallback when both CPCs are 0
                                            }
                                        }
                                    } else {
                                        // Correctly-utilized or other
                                        var cpcToUse = (l1Cpc && !isNaN(l1Cpc) && l1Cpc > 0) ? l1Cpc : ((l7Cpc && !isNaN(l7Cpc) && l7Cpc > 0) ? l7Cpc : 0);
                                        if (cpcToUse > 0) {
                                            sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                }
                                
                                // Check if SBID is 0 (should not happen after fallback, but keep as safety)
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
                            var ub1 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                                ub1 = (parseFloat(row.l1_spend) || 0) / budget * 100;
                            }
                            
                            var sbid = 0;
                            
                            // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50 (ebay2 doesn't have price field)
                            if (ub7 === 0 && ub1 === 0) {
                                sbid = 0.50;
                                return sbid.toFixed(2);
                            }
                            
                            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
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
                            
                            // eBay2 SBID calculation logic
                            if (currentUtilizationType === 'over') {
                                // If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                if (l1_cpc > 1.25) {
                                    sbid = Math.floor(l1_cpc * 0.80 * 100) / 100;
                                } else {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                }
                            } else if (currentUtilizationType === 'under') {
                                // Under-utilized SBID calculation rules (from ebay-utilized, without price logic)
                                // If UB7 = 0% AND UB1 = 0%, SBID = 0.50
                                if (ub7 === 0 && ub1 === 0) {
                                    sbid = 0.50;
                                } else {
                                    // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                        l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                            l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        if (cpcToUse < 0.10) {
                                            sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                        } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                            sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                        } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                            sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                        } else {
                                            sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                }
                            } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                }
                            } else if (currentUtilizationType === 'correctly') {
                                // Correctly-utilized: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : (
                                    (l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                if (cpcToUse > 0) {
                                    sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0.50; // Fallback when both CPCs are 0
                                }
                            } else {
                                // For 'all' type, determine utilization status and apply appropriate rule
                                var rowAcos = parseFloat(row.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }

                                // Determine utilization status
                                var isOverUtilized = false;
                                var isUnderUtilized = false;

                                // Check over-utilized first (priority 1) - Double condition: both UB7 AND UB1 must be > 99
                                if (ub7 > 99 && ub1 > 99) {
                                    isOverUtilized = true;
                                }

                                // Check under-utilized (priority 2: only if not over-utilized) - Double condition: both UB7 AND UB1 must be < 66
                                if (!isOverUtilized && ub7 < 66 && ub1 < 66) {
                                    isUnderUtilized = true;
                                }

                                // Apply SBID logic based on determined status
                                if (isOverUtilized) {
                                    // Over-utilized: If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                        l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                            l7_cpc : 0);
                                    if (cpcToUse > 1.25) {
                                        sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                    } else if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                } else if (isUnderUtilized) {
                                    // Under-utilized: Use the under-utilized rule
                                    if (ub7 === 0 && ub1 === 0) {
                                        sbid = 0.50;
                                    } else {
                                        // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                        var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                            l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                                l7_cpc : 0);
                                        if (cpcToUse > 0) {
                                            if (cpcToUse < 0.10) {
                                                sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                            } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                            } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                            } else {
                                                sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                            }
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                } else {
                                    // Correctly-utilized or other: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                        l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                            l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                }
                            }

                            // Check if SBID is 0 (should not happen after fallback, but keep as safety)
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
                            if (e.target.classList.contains("update-bid-icon") || e.target.closest(".update-bid-icon")) {
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
                                    showToast('error', 'SBID M value is required. Please save SBID M first.');
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
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var status = cell.getValue();
                            if (status === 'RUNNING') {
                                return '<div style="display: flex; justify-content: center; align-items: center;"><span style="width: 12px; height: 12px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span></div>';
                            } else if (status === 'PAUSED') {
                                return '<div style="display: flex; justify-content: center; align-items: center;"><span style="width: 12px; height: 12px; background-color: #ffc107; border-radius: 50%; display: inline-block;"></span></div>';
                            } else if (status === 'ENDED') {
                                return '<div style="display: flex; justify-content: center; align-items: center;"><span style="width: 12px; height: 12px; background-color: #dc3545; border-radius: 50%; display: inline-block;"></span></div>';
                            }
                            return status;
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid_old",
                        hozAlign: "center",
                        visible: false,
                        width: 100,
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

                                // Check if NRA (🔴) is selected
                                var nraValue = rowData.NR ? rowData.NR.trim() : "";
                                if (nraValue === 'NRA') {
                                    return; // Skip update if NRA is selected
                                }

                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = 0;
                                var ub1 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                    ub1 = (parseFloat(rowData.l1_spend) || 0) / budget * 100;
                                }
                                
                                var sbid = 0;
                                
                                // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50 (ebay2 doesn't have price field)
                                if (ub7 === 0 && ub1 === 0) {
                                    sbid = 0.50;
                                } else if (ub7 > 99 && ub1 > 99) {
                                    // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }
                                } else if (currentUtilizationType === 'over') {
                                    // If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                    if (l1_cpc > 1.25) {
                                        sbid = Math.floor(l1_cpc * 0.80 * 100) / 100;
                                    } else {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    // Under-utilized SBID calculation rules (from ebay-utilized, without price logic)
                                    // If UB7 = 0% AND UB1 = 0%, SBID = 0.50
                                    if (ub7 === 0 && ub1 === 0) {
                                        sbid = 0.50;
                                    } else {
                                        // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                        var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                            l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                                l7_cpc : 0);
                                        if (cpcToUse > 0) {
                                            if (cpcToUse < 0.10) {
                                                sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                            } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                            } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                            } else {
                                                sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                    }
                                } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                } else if (currentUtilizationType === 'correctly') {
                                    // Correctly-utilized: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                        l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                            l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                } else {
                                    // For 'all' type, determine utilization status and apply appropriate rule
                                    var rowAcos = parseFloat(rowData.acos) || 0;
                                    if (isNaN(rowAcos) || rowAcos === 0) {
                                        rowAcos = 100;
                                    }

                                    // Determine utilization status
                                    var isOverUtilized = false;
                                    var isUnderUtilized = false;

                                    // Check over-utilized first (priority 1) - Double condition: both UB7 AND UB1 must be > 99
                                    if (ub7 > 99 && ub1 > 99) {
                                        isOverUtilized = true;
                                    }

                                    // Check under-utilized (priority 2: only if not over-utilized) - Double condition: both UB7 AND UB1 must be < 66
                                    if (!isOverUtilized && ub7 < 66 && ub1 < 66) {
                                        isUnderUtilized = true;
                                    }

                                    // Apply SBID logic based on determined status
                                    if (isOverUtilized) {
                                        // Over-utilized: If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                        var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                            l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                                l7_cpc : 0);
                                        if (cpcToUse > 1.25) {
                                            sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                        } else if (cpcToUse > 0) {
                                            sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    } else if (isUnderUtilized) {
                                        // Under-utilized: Use the under-utilized rule
                                        if (ub7 === 0 && ub1 === 0) {
                                            sbid = 0.50;
                                        } else {
                                            // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                            var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc >
                                                0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc >
                                                    0) ? l7_cpc : 0);
                                            if (cpcToUse > 0) {
                                                if (cpcToUse < 0.10) {
                                                    sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                                } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                    sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                                } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                    sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                                } else {
                                                    sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                                }
                                            } else {
                                                sbid = 0.50; // Fallback when both CPCs are 0
                                            }
                                        }
                                    } else {
                                        // Correctly-utilized or other: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                        var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                            l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                                l7_cpc : 0);
                                        if (cpcToUse > 0) {
                                            sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                }
                                updateBid(sbid, rowData.campaign_id);
                            }
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;

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
                    totalSkuCountFromBackend = parseFloat(response.total_sku_count) || 0;
                    ebaySkuCountFromBackend = parseFloat(response.ebay_sku_count) || 0;
                    totalCampaignCountFromBackend = parseFloat(response.total_campaign_count) || 0;
                    zeroInvCountFromBackend = parseFloat(response.zero_inv_count) || 0;

                    // Update eBay SKU count
                    const ebaySkuCountEl = document.getElementById('ebay-sku-count');
                    if (ebaySkuCountEl) {
                        ebaySkuCountEl.textContent = ebaySkuCountFromBackend;
                    }

                    // Update total campaign count from backend
                    const totalCampaignCountEl = document.getElementById('total-campaign-count');
                    if (totalCampaignCountEl) {
                        totalCampaignCountEl.textContent = totalCampaignCountFromBackend;
                    }

                    // Update zero INV count from backend
                    const zeroInvCountEl = document.getElementById('zero-inv-count');
                    if (zeroInvCountEl) {
                        zeroInvCountEl.textContent = zeroInvCountFromBackend;
                    }
                    
                    // When data is loaded from server, sync editedSbidMValues with loaded data
                    // This ensures saved values from database are reflected
                    if (response.data && Array.isArray(response.data)) {
                        response.data.forEach(function(rowData) {
                            var uniqueId = (rowData.campaign_id || '') + '_' + (rowData.sku || '');
                            // If row has sbid_m from database, use it (it's the saved value)
                            if (rowData.sbid_m !== null && rowData.sbid_m !== undefined && rowData.sbid_m !== '') {
                                // If edited value matches loaded value, we can clear it (it's already saved)
                                if (editedSbidMValues[uniqueId] === rowData.sbid_m) {
                                    delete editedSbidMValues[uniqueId];
                                }
                                // If edited value exists but doesn't match, keep it (unsaved edit)
                                // Otherwise, loaded value from database will be used
                            }
                        });
                    }

                    return response.data;
                }
            });

            // Helper function to apply filter (clears processed SKUs set before filtering when needed)
            function applyFilter() {
                // Clear processed SKUs set before filtering (filter will rebuild it)
                if (showEbaySkuOnly) {
                    processedEbaySkus.clear();
                }
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            }

            // Combined filter function
            function combinedFilter(data) {
                // eBay SKU filter should be checked FIRST to bypass other conflicting filters
                // This ensures that when showing eBay SKUs, we show unique SKUs that exist in Ebay2Metric table (not campaigns)
                // IMPORTANT: When showEbaySkuOnly is true, we need to show only ONE row per unique SKU
                if (showEbaySkuOnly) {
                    // Check if SKU exists in Ebay2Metric table - use hasEbaySku field from controller
                    // hasEbaySku = true means SKU exists in Ebay2Metric table (which is what ebay_sku_count represents)
                    // IMPORTANT: Only use hasEbaySku field, NOT ebay_l30 (because ebay_l30 is set to 0 for all rows, even when SKU doesn't exist in Ebay2Metric)
                    const hasEbaySkuField = data.hasEbaySku === true || data.hasEbaySku === 1;
                    
                    // Show only SKUs that exist in Ebay2Metric table
                    // hasEbaySku field is set by controller when SKU is found in Ebay2Metric table
                    if (!hasEbaySkuField) return false;
                    
                    // Ensure uniqueness: Only show first occurrence of each SKU (normalize SKU for comparison)
                    // Normalize SKU to handle case-insensitive comparison
                    const sku = (data.sku || '').toUpperCase().trim();
                    if (!sku || sku === '') return false; // Skip rows without SKU
                    
                    // Check if this SKU has already been processed in this filter run
                    if (processedEbaySkus.has(sku)) {
                        // This SKU has already been shown, skip this duplicate row
                        return false;
                    }
                    // Mark this SKU as processed (first occurrence only)
                    processedEbaySkus.add(sku);
                    
                    // When showing eBay SKUs, bypass missing/NRA missing/SKU type/inventory filters (they conflict with showing eBay SKUs)
                    // Continue to other filters (search, status, NRA, utilization)
                    // SKU type and inventory filters will be skipped below
                } else {
                    // Apply missing/campaign/zero INV/NRA/RA filters only when NOT showing eBay SKUs
                    if (showMissingOnly) {
                        const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                            data.campaignName);
                        if (hasCampaign) return false;
                        // Check if this is a red dot (missing AND not yellow) AND INV > 0
                        let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                        let rowNraForMissing = data.NR ? data.NR.trim() : "";
                        let inv = parseFloat(data.INV || 0);
                        // Only show as missing (red dot) if neither NRL='NR'/'NRL' nor NRA='NRA' AND INV > 0
                        if ((rowNrlForMissing === 'NR' || rowNrlForMissing === 'NRL') || rowNraForMissing === 'NRA' || inv <= 0) return false;
                    }

                    if (showNraMissingOnly) {
                        const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                            data.campaignName);
                        if (hasCampaign) return false;
                        // Show only NRA missing (yellow dots) - if NRA='NRA' OR NRL='NR'/'NRL' (because NRL='NR'/'NRL' means NRA should be 'NRA')
                        let rowNraForMissing = data.NR ? data.NR.trim() : "";
                        let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                        // Show if NRA='NRA' OR NRL='NR'/'NRL' (because NRL='NR'/'NRL' means NRA should be 'NRA')
                        if (rowNraForMissing !== 'NRA' && rowNrlForMissing !== 'NR' && rowNrlForMissing !== 'NRL') {
                            return false;
                        }
                    }

                    if (showNrlMissingOnly) {
                        const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                            data.campaignName);
                        if (hasCampaign) return false;
                        // Show only NRL missing (yellow dots) - both 'NR' and 'NRL' are treated as NRL
                        let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                        if (rowNrlForMissing !== 'NRL' && rowNrlForMissing !== 'NR') return false;
                    }

                    if (showCampaignOnly) {
                        const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                            data.campaignName);
                        if (!hasCampaign) return false;
                    }
                }

                // Apply SKU type filter only when NOT showing eBay SKUs
                // When showEbaySkuOnly is true, skip SKU type filter to show all SKUs with campaigns
                if (!showEbaySkuOnly) {
                    let sku = (data.sku || '').toUpperCase().trim();
                    let isParentSku = sku.startsWith('PARENT');
                    
                    if (currentSkuType === 'parent') {
                        // Show only PARENT SKUs
                        if (!isParentSku) return false;
                    } else if (currentSkuType === 'sku') {
                        // Show only non-PARENT SKUs (hide PARENT SKUs)
                        if (isParentSku) return false;
                    } else {
                        // currentSkuType === 'all' - show all SKUs
                    }
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
                    // Inventory filter - get current filter value
                let invFilterVal = $("#inv-filter").val();

                    // When showEbaySkuOnly is active, show all campaigns regardless of inventory
                    if (showEbaySkuOnly) {
                        // Don't filter by inventory when showing eBay SKUs
                    } else {
                        // Check inventory filter value explicitly
                        if (invFilterVal === null || invFilterVal === undefined || invFilterVal === '') {
                            // Default/empty value: hide INV <= 0 (show only INV > 0)
                            if (inv <= 0) return false;
                    } else if (invFilterVal === "ALL") {
                            // ALL option: show everything (including INV = 0 and negative), no filtering
                            // Do nothing - allow all rows through
                    } else if (invFilterVal === "INV_0") {
                            // INV_0 option: show INV <= 0 (zero and negative inventory) to match zero INV count
                            if (inv > 0) return false;
                        } else {
                            // Unknown filter value - default to INV > 0 for safety
                        if (inv <= 0) return false;
                        }
                    }
                }

                // Apply NRA/RA filters first (if enabled)
                // Note: Empty/null NRA defaults to "RA" in the display
                let rowNra = data.NR ? data.NR.trim() : "";
                let rowNrl = data.NRL ? data.NRL.trim() : "";
                if (showNraOnly) {
                    // Show only NRA: if NR='NRA' OR if NRL='NR'/'NRL' (since NRL='NR' means NRA should be 'NRA')
                    if (rowNra !== 'NRA' && rowNrl !== 'NR' && rowNrl !== 'NRL') return false;
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
                // rowNrl is already declared above
                if (showNrlOnly) {
                    // Show only NRL (both 'NRL' and 'NR' are treated as NRL)
                    if (rowNrl !== 'NRL' && rowNrl !== 'NR') return false;
                } else {
                    // NRL filter from dropdown
                    let nrlFilterVal = $("#nrl-filter").val();
                if (nrlFilterVal) {
                        if (nrlFilterVal === 'REQ') {
                            // For "REQ" filter, include empty/null values too
                            // Exclude both 'NRL' and 'NR'
                            if (rowNrl === 'NRL' || rowNrl === 'NR') return false;
                        } else if (nrlFilterVal === 'NR' || nrlFilterVal === 'NRL') {
                            // For "NR" or "NRL" filter, match both 'NR' and 'NRL' values
                            if (rowNrl !== 'NRL' && rowNrl !== 'NR') return false;
                        } else {
                            // For other values, exact match
                            if (rowNrl !== nrlFilterVal) return false;
                        }
                    }
                }

                // Exclude missing rows (rows without campaigns) when utilization type is over, under, or correctly
                // This includes both red dots (missing) and yellow dots (NRA/NRL missing)
                if (currentUtilizationType === 'over' || currentUtilizationType === 'under' || currentUtilizationType === 'correctly') {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id && data.campaignName);
                    // Check for yellow dots (NRA/NRL missing) - these are also missing rows
                    let rowNrl = data.NRL ? data.NRL.trim() : "";
                    let rowNra = data.NR ? data.NR.trim() : "";
                    // Exclude if no campaign OR if it's a yellow dot (NRL='NR'/'NRL' or NRA='NRA')
                    if (!hasCampaign || rowNrl === 'NRL' || rowNrl === 'NR' || rowNra === 'NRA') {
                        return false; // Hide missing rows (both red and yellow dots)
                    }
                }

                // Apply utilization type filter (ebay2 specific logic)
                // Only calculate utilization for rows with campaigns (already checked above)
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend || 0);
                let l1_spend = parseFloat(data.l1_spend || 0);
                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                if (currentUtilizationType === 'all') {
                    // All - no utilization filter
                } else if (currentUtilizationType === 'over') {
                    // Double condition: both UB7 AND UB1 must be > 99
                    if (!(ub7 > 99 && ub1 > 99)) {
                        return false;
                    }
                } else if (currentUtilizationType === 'under') {
                    // Double condition: both UB7 AND UB1 must be < 66
                    if (!(ub7 < 66 && ub1 < 66)) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Double condition: both UB7 AND UB1 must be between 66% and 99% (both green)
                    if (!((ub7 >= 66 && ub7 <= 99) && (ub1 >= 66 && ub1 <= 99))) return false;
                }

                return true;
            }

            table.on("tableBuilt", function() {
                // Clear processed SKUs set when table is built/rebuilt
                processedEbaySkus.clear();
                table.setFilter(combinedFilter);

                // Set initial column visibility based on current utilization type
                if (currentUtilizationType === 'correctly') {
                    table.hideColumn('sbid');
                } else {
                    table.showColumn('sbid');
                }
                // Show APR BID column
                table.showColumn('apr_bid');
            });

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
                        url: '/update-ebay2-sbid-m',
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
                                currentData.apprSbid = ''; // Clear apprSbid to allow new bid push
                                
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
                                errorMsg = 'Campaign not found. Please ensure the campaign exists.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error. Please try again.';
                            }
                            showToast('error', errorMsg);
                            // Revert the cell value on error
                            cell.restoreOldValue();
                        }
                    });
                }
            });

            // Update counts when data is filtered (debounced)
            let filterTimeout = null;
            table.on("dataFiltered", function(filteredRows) {
                // Don't clear processed SKUs here - it's maintained by the filter function
                if (filterTimeout) clearTimeout(filterTimeout);
                filterTimeout = setTimeout(function() {
                    updateButtonCounts();
                    updateL30Totals();
                }, 200);
            });

            // Debounced search
            let searchTimeout = null;
            $("#global-search").on("keyup", function() {
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    // Clear processed SKUs set before filtering (filter will rebuild it)
                    if (showEbaySkuOnly) {
                        processedEbaySkus.clear();
                    }
                    table.setFilter(combinedFilter);
                }, 300);
            });

            $("#status-filter, #inv-filter, #nra-filter, #nrl-filter").on("change", function() {
                // Clear processed SKUs set before filtering (filter will rebuild it)
                if (showEbaySkuOnly) {
                    processedEbaySkus.clear();
                }
                table.setFilter(combinedFilter);
                // Update counts when filter changes - use longer timeout to ensure filter is applied
                setTimeout(function() {
                    updateButtonCounts();
                    updateL30Totals();
                }, 300);
            });

            // Initial update of all button counts after data loads
            setTimeout(function() {
                updateButtonCounts();
            }, 1000);

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                    document.getElementById("bulk-update-sbid-m-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                    document.getElementById("bulk-update-sbid-m-btn").classList.add("d-none");
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

                    fetch('/update-ebay2-nr-data', {
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
                        console.log('Ebay2 Update Response:', data); // Debug log
                        // Update table data with response
                        if (data.success && typeof table !== 'undefined' && table) {
                            let row = table.searchRows('sku', '=', sku);
                            if (row.length > 0) {
                                // If NRL is set to "NRL" (or "NR" from dropdown), automatically set NRA to "NRA"
                                if (field === 'NRL' && (value === 'NR' || value === 'NRL')) {
                                    console.log('Ebay2: NRL set to', value, 'updating NRA...'); // Debug log
                                    
                                    // Get current row data BEFORE update
                                    let rowData = row[0].getData();
                                    let currentNra = rowData.NR ? String(rowData.NR).trim() : '';
                                    
                                    // Get NRA value from backend response (backend always sets it to 'NRA' when NRL is 'NRL')
                                    let backendNra = '';
                                    if (data.updated_json && data.updated_json.NR) {
                                        backendNra = String(data.updated_json.NR).trim();
                                    }
                                    
                                    console.log('Ebay2: Current NRA:', currentNra, 'Backend NRA:', backendNra); // Debug log
                                    console.log('Ebay2: Updated JSON:', data.updated_json); // Debug log
                                    
                                    // Always update both NRL and NR when NRL is set to "NRL" or "NR"
                                    // Backend always sets NR to "NRA" when NRL is "NRL"
                                    let updateData = {
                                        NRL: value === 'NR' ? 'NRL' : value, // Store as 'NRL' in data
                                        NR: backendNra || 'NRA' // Use backend value (should be 'NRA') or default to 'NRA'
                                    };
                                    
                                    console.log('Ebay2: Updating row with:', updateData); // Debug log
                                    
                                    // Update the row data immediately
                                    row[0].update(updateData);
                                    
                                    // Immediately update NRA cell value
                                    let nraCell = row[0].getCell('NR');
                                    if (nraCell) {
                                        nraCell.setValue('NRA');
                                    }
                                    
                                    // Force immediate row reformat to trigger formatter re-run
                                    row[0].reformat();
                                    
                                    // Directly replace the NRA select dropdown HTML to force visual update
                                    setTimeout(function() {
                                        let nraCell = row[0].getCell('NR');
                                        if (nraCell) {
                                            let cellElement = nraCell.getElement();
                                            if (cellElement) {
                                                let nraSelect = cellElement.querySelector('select[data-field="NR"]');
                                                if (nraSelect) {
                                                    // Store the current event listeners by cloning
                                                    let sku = nraSelect.getAttribute('data-sku');
                                                    
                                                    // Replace the select with new HTML that has NRA selected
                                                    let newSelectHTML = `
                                                        <select class="form-select form-select-sm editable-select" 
                                                                data-sku="${sku}" 
                                                                data-field="NR"
                                                                style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                                            <option value="RA">🟢</option>
                                                            <option value="NRA" selected>🔴</option>
                                                            <option value="LATER">🟡</option>
                                                        </select>
                                                    `;
                                                    cellElement.innerHTML = newSelectHTML;
                                                    
                                                    // The new select will automatically have the change event listener
                                                    // because it has the 'editable-select' class
                                                }
                                            }
                                        }
                                    }, 10);
                                    
                                    // Second update with reformat
                                    setTimeout(function() {
                                        // Reformat again to ensure formatter runs with updated data
                                        row[0].reformat();
                                        
                                        // Double-check select is set to NRA
                                        let nraCell = row[0].getCell('NR');
                                        if (nraCell) {
                                            let cellElement = nraCell.getElement();
                                            if (cellElement) {
                                                let nraSelect = cellElement.querySelector('select[data-field="NR"]');
                                                if (nraSelect && nraSelect.value !== 'NRA') {
                                                    nraSelect.value = 'NRA';
                                                }
                                            }
                                        }
                                    }, 50);
                                    
                                    // Final update with table redraw
                                    setTimeout(function() {
                                        // One more reformat
                                        row[0].reformat();
                                        
                                        // Redraw entire table
                                        if (typeof table !== 'undefined' && table) {
                                            table.redraw(true);
                                        }
                                        
                                        // Final select update check
                                        let nraCell = row[0].getCell('NR');
                                        if (nraCell) {
                                            let cellElement = nraCell.getElement();
                                            if (cellElement) {
                                                let nraSelect = cellElement.querySelector('select[data-field="NR"]');
                                                if (nraSelect && nraSelect.value !== 'NRA') {
                                                    nraSelect.value = 'NRA';
                                                }
                                            }
                                        }
                                        
                                        // Update counts
                                        updateButtonCounts();
                                        updateL30Totals();
                                    }, 150);
                                } else {
                                    // Update the field from backend response
                                    let updatedData = {};
                                    if (data.updated_json && data.updated_json[field] !== undefined) {
                                        updatedData[field] = data.updated_json[field];
                                    } else {
                                        updatedData[field] = value;
                                    }
                                    row[0].update(updatedData);
                                    
                                    // If NRL field is updated, reformat the row to update NRA column (which depends on NRL)
                                    if (field === 'NRL') {
                                        row[0].reformat();
                                    }
                                    
                                    // Update counts after field update
                                    if (field === 'NRL' || field === 'NR') {
                                        setTimeout(function() {
                                            updateButtonCounts();
                                            updateL30Totals();
                                        }, 300);
                                    }
                                }
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Error updating field:', err);
                        showToast('error', 'Error updating field. Please try again.');
                    });
                }
                
                // Handle SBID M input changes
                if (e.target.classList.contains("sbid-m-input")) {
                    // Prevent event from bubbling
                    e.stopPropagation();
                    
                    let campaignId = e.target.getAttribute("data-campaign-id");
                    let uniqueId = e.target.getAttribute("data-unique-id");
                    let sku = e.target.getAttribute("data-sku");
                    let newValue = e.target.value;
                    
                    if (!campaignId) {
                        return;
                    }
                    
                    // Get row element and find the corresponding Tabulator row
                    let rowElement = e.target.closest('.tabulator-row');
                    if (!rowElement) return;
                    
                    // Get row using Tabulator's row component directly from element
                    let row = table.getRow(rowElement);
                    if (!row) {
                        // Fallback: find by position
                        let rowIndex = Array.from(rowElement.parentNode.children).indexOf(rowElement);
                        row = table.getRowFromPosition(rowIndex);
                    }
                    
                    if (!row) return;
                    
                    let rowData = row.getData();
                    
                    // Verify we have the correct row by comparing SKU and campaign_id
                    let currentUniqueId = (rowData.campaign_id || '') + '_' + (rowData.sku || '');
                    if (uniqueId !== currentUniqueId) {
                        console.warn("Row mismatch detected, skipping update. Expected:", uniqueId, "Got:", currentUniqueId);
                        return;
                    }
                    
                    // Check if NRA (🔴) is selected
                    let nraValue = rowData.NR ? rowData.NR.trim() : "";
                    if (nraValue === 'NRA') {
                        // Restore previous value
                        let prevValue = editedSbidMValues[uniqueId] !== undefined ? editedSbidMValues[uniqueId] : rowData.sbid_m;
                        if (prevValue !== null && prevValue !== undefined && prevValue !== '') {
                            e.target.value = parseFloat(prevValue).toFixed(2);
                        } else {
                            e.target.value = '';
                        }
                        showToast('error', 'Cannot edit SBID M for NRA campaigns');
                        return;
                    }
                    
                    let editedValue = null;
                    let prevValue = editedSbidMValues[uniqueId] !== undefined ? editedSbidMValues[uniqueId] : rowData.sbid_m;
                    
                    if (newValue !== null && newValue !== undefined && newValue !== '') {
                        editedValue = parseFloat(newValue);
                        if (!isNaN(editedValue) && editedValue >= 0) {
                            // Round to 2 decimal places
                            editedValue = Math.round(editedValue * 100) / 100;
                            e.target.value = editedValue.toFixed(2);
                        } else {
                            // Invalid value - restore previous value
                            if (prevValue !== null && prevValue !== undefined && prevValue !== '') {
                                e.target.value = parseFloat(prevValue).toFixed(2);
                            } else {
                                e.target.value = '';
                            }
                            showToast('error', 'Please enter a valid number (>= 0)');
                            return;
                        }
                    }
                    
                    // Store edited value immediately to prevent cross-contamination
                    editedSbidMValues[uniqueId] = editedValue;
                    
                    // Save to database
                    fetch('/update-ebay2-sbid-m', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            campaign_id: campaignId,
                            sbid_m: editedValue
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 200) {
                            showToast('success', 'SBID M saved successfully');
                            // Success - value already stored in editedSbidMValues
                            // Clear apprSbid when sbid_m is updated so new bid can be pushed
                            if (row) {
                                var currentRowData = row.getData();
                                currentRowData.apprSbid = ''; // Clear apprSbid to allow new bid push
                                // Update the row to refresh APR BID column icon
                                row.update(currentRowData);
                                setTimeout(function() {
                                    row.reformat();
                                }, 50);
                            }
                        } else {
                            // Restore previous value on error
                            editedSbidMValues[uniqueId] = prevValue;
                            if (prevValue !== null && prevValue !== undefined && prevValue !== '') {
                                e.target.value = parseFloat(prevValue).toFixed(2);
                            } else {
                                e.target.value = '';
                            }
                            showToast('error', data.message || 'Failed to save SBID M');
                        }
                    })
                    .catch(err => {
                        // Restore previous value on error
                        editedSbidMValues[uniqueId] = prevValue;
                        if (prevValue !== null && prevValue !== undefined && prevValue !== '') {
                            e.target.value = parseFloat(prevValue).toFixed(2);
                        } else {
                            e.target.value = '';
                        }
                        console.error("Error saving SBID M:", err);
                    });
                }
            });
            
            // Handle blur event for SBID M input (when user finishes editing)
            document.addEventListener("blur", function(e) {
                if (e.target.classList.contains("sbid-m-input")) {
                    // Use change event handler logic instead of duplicating
                    // The change event will handle saving, blur just ensures value is validated
                    e.stopPropagation();
                }
            }, true);

            // Export data button handler
            document.getElementById("export-data-btn").addEventListener("click", function() {
                if (typeof table !== 'undefined' && table) {
                    // Get all data from table
                    var allData = table.getData('all');
                    
                    // Get column definitions
                    var columns = table.getColumns().filter(function(col) {
                        var def = col.getDefinition();
                        return def.field && def.title && def.visible !== false;
                    });
                    
                    // Helper function to calculate SBID (same logic as formatter)
                    function calculateSbidForExport(row) {
                        var nraValue = row.NR ? row.NR.trim() : "";
                        if (nraValue === 'NRA') {
                            return '-';
                        }
                        
                        var l1_cpc = parseFloat(row.l1_cpc) || 0;
                        var l7_cpc = parseFloat(row.l7_cpc) || 0;
                        var budget = parseFloat(row.campaignBudgetAmount) || 0;
                        var ub7 = 0;
                        var ub1 = 0;
                        if (budget > 0) {
                            ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            ub1 = (parseFloat(row.l1_spend) || 0) / budget * 100;
                        }
                        
                        var sbid = 0;
                        
                        // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50 (ebay2 doesn't have price field)
                        if (ub7 === 0 && ub1 === 0) {
                            return (0.50).toFixed(2);
                        }
                        
                        // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                        if (ub7 > 99 && ub1 > 99) {
                            if (l1_cpc > 0) {
                                return (Math.floor(l1_cpc * 0.90 * 100) / 100).toFixed(2);
                            } else if (l7_cpc > 0) {
                                return (Math.floor(l7_cpc * 0.90 * 100) / 100).toFixed(2);
                            } else {
                                return '-';
                            }
                        }
                        
                        if (currentUtilizationType === 'over') {
                            var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                            if (cpcToUse > 1.25) {
                                sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                            } else if (cpcToUse > 0) {
                                sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                            } else {
                                sbid = 0.50;
                            }
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 === 0 && ub1 === 0) {
                                sbid = 0.50;
                            } else {
                                var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                if (cpcToUse > 0) {
                                    if (cpcToUse < 0.10) {
                                        sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                    } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                        sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                    } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                        sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                    } else {
                                        sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                    }
                                } else {
                                    sbid = 0.50;
                                }
                            }
                        } else if (currentUtilizationType === 'correctly') {
                            var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                            if (cpcToUse > 0) {
                                sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                            } else {
                                sbid = 0.50;
                            }
                        } else {
                            // For 'all' type
                            var rowAcos = parseFloat(row.acos) || 0;
                            if (isNaN(rowAcos) || rowAcos === 0) {
                                rowAcos = 100;
                            }
                            
                            var isOverUtilized = false;
                            var isUnderUtilized = false;
                            
                            if (ub7 > 99 && ub1 > 99) {
                                isOverUtilized = true;
                            }
                            
                            if (!isOverUtilized && ub7 < 66 && ub1 < 66) {
                                isUnderUtilized = true;
                            }
                            
                            if (isOverUtilized) {
                                var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                if (cpcToUse > 1.25) {
                                    sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                } else if (cpcToUse > 0) {
                                    sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0.50;
                                }
                            } else if (isUnderUtilized) {
                                if (ub7 === 0 && ub1 === 0) {
                                    sbid = 0.50;
                                } else {
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        if (cpcToUse < 0.10) {
                                            sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                        } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                            sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                        } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                            sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                        } else {
                                            sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                        }
                                    } else {
                                        sbid = 0.50;
                                    }
                                }
                            } else {
                                var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                if (cpcToUse > 0) {
                                    sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0.50;
                                }
                            }
                        }
                        
                        return (sbid === 0) ? '-' : sbid.toFixed(2);
                    }
                    
                    // Build CSV content
                    var csvContent = [];
                    
                    // Add headers
                    var headers = [];
                    columns.forEach(function(col) {
                        headers.push(col.getDefinition().title);
                    });
                    csvContent.push(headers.join(','));
                    
                    // Add data rows
                    allData.forEach(function(row) {
                        var rowData = [];
                        // Pre-calculate SBID and NR values
                        var calculatedSbid = calculateSbidForExport(row);
                        var nrValue = row.NR ? row.NR.trim() : '';
                        var processedNr = nrValue || 'RA';
                        
                        columns.forEach(function(col) {
                            var field = col.getField();
                            var value;
                            
                            // Use calculated values for SBID and NR fields
                            if (field === 'sbid') {
                                value = calculatedSbid;
                            } else if (field === 'NR') {
                                value = processedNr;
                            } else if (field === 'sbid_m') {
                                // Get sbid_m value from row data (should be updated from input)
                                value = row.sbid_m !== null && row.sbid_m !== undefined ? parseFloat(row.sbid_m).toFixed(2) : '';
                            } else {
                                value = row[field];
                            }
                            
                            // Handle special formatting
                            if (value === null || value === undefined) {
                                value = '';
                            } else if (typeof value === 'string' && value.includes(',')) {
                                value = '"' + value + '"';
                            }
                            rowData.push(value);
                        });
                        csvContent.push(rowData.join(','));
                    });
                    
                    // Download CSV
                    var csvString = csvContent.join('\n');
                    var blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    var url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'ebay2-utilized-data.csv');
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();
                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();

                        // Check if NRA (🔴) is selected
                        var nraValue = rowData.NR ? rowData.NR.trim() : "";
                        if (nraValue === 'NRA') {
                            return; // Skip update if NRA is selected
                        }

                        // Check if manually edited SBID M exists, use it first (read from input field)
                        var sbid = 0;
                        var sbidMInput = rowEl.querySelector('.sbid-m-input');
                        if (sbidMInput && sbidMInput.value !== null && sbidMInput.value !== undefined && sbidMInput.value !== '') {
                            var editedSbidM = parseFloat(sbidMInput.value);
                            if (!isNaN(editedSbidM) && editedSbidM > 0) {
                                sbid = editedSbidM;
                                campaignIds.push(rowData.campaign_id);
                                bids.push(sbid);
                                return; // Use edited value, skip calculation
                            }
                        }
                        
                        var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                        var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                        var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                        var ub7 = 0;
                        var ub1 = 0;
                        if (budget > 0) {
                            ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                            ub1 = (parseFloat(rowData.l1_spend) || 0) / budget * 100;
                        }
                        
                        // Special rule: If UB7 = 0% and UB1 = 0%, set SBID to 0.50 (ebay2 doesn't have price field)
                        if (ub7 === 0 && ub1 === 0) {
                            sbid = 0.50;
                        } else if (ub7 > 99 && ub1 > 99) {
                            // Rule: If both UB7 and UB1 are above 99%, set SBID as L1_CPC * 0.90
                            if (l1_cpc > 0) {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (l7_cpc > 0) {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            } else {
                                sbid = 0;
                            }
                        } else {
                            // Continue with utilization type checks only if global checks didn't match
                            if (currentUtilizationType === 'over') {
                                // If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                if (l1_cpc > 1.25) {
                                    sbid = Math.floor(l1_cpc * 0.80 * 100) / 100;
                                } else {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                }
                            } else if (currentUtilizationType === 'under') {
                                // Under-utilized SBID calculation rules (from ebay-utilized, without price logic)
                                // If UB7 = 0% AND UB1 = 0%, SBID = 0.50
                                if (ub7 === 0 && ub1 === 0) {
                                    sbid = 0.50;
                                } else {
                                    // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : (
                                        (l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        if (cpcToUse < 0.10) {
                                            sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                        } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                            sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                        } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                            sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                        } else {
                                            sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                        }
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                }
                            } else if (currentUtilizationType === 'correctly') {
                                // Correctly-utilized: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : ((
                                    l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                if (cpcToUse > 0) {
                                    sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0.50; // Fallback when both CPCs are 0
                                }
                            } else {
                                // For 'all' type, determine utilization status and apply appropriate rule
                                var rowAcos = parseFloat(rowData.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }

                                // Determine utilization status
                                var isOverUtilized = false;
                                var isUnderUtilized = false;

                                // Check over-utilized first (priority 1) - Double condition: both UB7 AND UB1 must be > 99
                                if (ub7 > 99 && ub1 > 99) {
                                    isOverUtilized = true;
                                }

                                // Check under-utilized (priority 2: only if not over-utilized) - Double condition: both UB7 AND UB1 must be < 66
                                if (!isOverUtilized && ub7 < 66 && ub1 < 66) {
                                    isUnderUtilized = true;
                                }

                                // Apply SBID logic based on determined status
                                if (isOverUtilized) {
                                    // Over-utilized: If L1 CPC > 1.25, use L1 CPC * 0.80, otherwise use L1 CPC * 0.90
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : (
                                        (l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                    if (cpcToUse > 1.25) {
                                        sbid = Math.floor(cpcToUse * 0.80 * 100) / 100;
                                    } else if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                } else if (isUnderUtilized) {
                                    // Under-utilized: Use the under-utilized rule
                                    if (ub7 === 0 && ub1 === 0) {
                                        sbid = 0.50;
                                    } else {
                                        // Use L1CPC if available (not 0, not NaN), otherwise use L7CPC
                                        var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ?
                                            l1_cpc : ((l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ?
                                                l7_cpc : 0);
                                        if (cpcToUse > 0) {
                                            if (cpcToUse < 0.10) {
                                                sbid = Math.floor(cpcToUse * 2.00 * 100) / 100;
                                            } else if (cpcToUse >= 0.10 && cpcToUse <= 0.20) {
                                                sbid = Math.floor(cpcToUse * 1.50 * 100) / 100;
                                            } else if (cpcToUse >= 0.21 && cpcToUse <= 0.30) {
                                                sbid = Math.floor(cpcToUse * 1.25 * 100) / 100;
                                            } else {
                                                sbid = Math.floor(cpcToUse * 1.10 * 100) / 100;
                                            }
                                        } else {
                                            sbid = 0.50; // Fallback when both CPCs are 0
                                        }
                                    }
                                } else {
                                    // Correctly-utilized or other: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    var cpcToUse = (l1_cpc && !isNaN(l1_cpc) && l1_cpc > 0) ? l1_cpc : (
                                        (l7_cpc && !isNaN(l7_cpc) && l7_cpc > 0) ? l7_cpc : 0);
                                    if (cpcToUse > 0) {
                                        sbid = Math.floor(cpcToUse * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0.50; // Fallback when both CPCs are 0
                                    }
                                }
                            }
                        }
                        
                        // Only push if we haven't already (sbid_m case already pushed and returned)
                        if (sbid > 0 && rowData.campaign_id) {
                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbid);
                        }
                    }
                });

                // fetch('/update-ebay2-keywords-bid-price', {
                //     method: 'PUT',
                //     headers: {
                //         'Content-Type': 'application/json',
                //             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                //                 .getAttribute('content')
                //     },
                //     body: JSON.stringify({
                //         campaign_ids: campaignIds,
                //         bids: bids
                //     })
                // })
                // .then(res => res.json())
                // .then(data => {
                //     if (data.status === 200) {
                //         alert("Keywords updated successfully!");
                //     } else {
                //         let errorMsg = data.message || "Something went wrong";
                //         if (errorMsg.includes("Premium Ads")) {
                //             alert("Error: " + errorMsg);
                //         } else {
                //             alert("Something went wrong: " + errorMsg);
                //         }
                //     }
                // })
                // .catch(err => {
                //     console.error(err);
                //     alert("Error updating bids");
                // })
                // .finally(() => {
                //     overlay.style.display = "none";
                // });
            });

            // Bulk Update SBID M for selected campaigns
            document.getElementById("bulk-update-sbid-m-btn").addEventListener("click", function() {
                var selectedRows = table.getSelectedRows();
                
                if (selectedRows.length === 0) {
                    alert("Please select at least one campaign to update SBID M.");
                    return;
                }

                // Filter out NRA campaigns and collect valid campaign IDs
                var validCampaigns = [];
                var nraCampaigns = [];
                
                selectedRows.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();
                        
                        // Check if NRA (🔴) is selected - skip NRA campaigns
                        var nraValue = rowData.NR ? rowData.NR.trim() : "";
                        if (nraValue === 'NRA') {
                            nraCampaigns.push(rowData.campaign_id || rowData.sku || 'Unknown');
                            return; // Skip NRA campaigns
                        }
                        
                        if (rowData.campaign_id) {
                            validCampaigns.push({
                                campaign_id: rowData.campaign_id,
                                sku: rowData.sku || '',
                                row: row
                            });
                        }
                    }
                });

                if (validCampaigns.length === 0) {
                    if (nraCampaigns.length > 0) {
                        alert("Cannot update SBID M for NRA campaigns. Please select non-NRA campaigns.");
                    } else {
                        alert("No valid campaigns selected.");
                    }
                    return;
                }

                // Show prompt to enter SBID M value
                var sbidMInput = prompt(
                    "Enter SBID M value to apply to " + validCampaigns.length + " selected campaign(s):\n\n" +
                    (nraCampaigns.length > 0 ? "Note: " + nraCampaigns.length + " NRA campaign(s) will be skipped.\n\n" : "") +
                    "Enter a number (>= 0) or leave empty to clear:",
                    ""
                );

                // If user cancelled, return
                if (sbidMInput === null) {
                    return;
                }

                // Parse and validate SBID M value
                var sbidMValue = null;
                if (sbidMInput !== null && sbidMInput !== undefined && sbidMInput.trim() !== '') {
                    var parsedValue = parseFloat(sbidMInput.trim());
                    if (isNaN(parsedValue) || parsedValue < 0) {
                        alert("Please enter a valid number (>= 0) or leave empty to clear.");
                        return;
                    }
                    // Round to 2 decimal places
                    sbidMValue = Math.round(parsedValue * 100) / 100;
                }

                // Confirm before updating
                var confirmMessage = "Are you sure you want to set SBID M = " + 
                    (sbidMValue !== null ? sbidMValue.toFixed(2) : "empty") + 
                    " for " + validCampaigns.length + " campaign(s)?";
                
                if (!confirm(confirmMessage)) {
                    return;
                }

                // Show overlay
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                // Prepare campaign IDs (same value for all campaigns)
                var campaignIds = [];
                
                validCampaigns.forEach(function(campaign) {
                    campaignIds.push(campaign.campaign_id);
                    
                    // Update local editedSbidMValues for immediate UI feedback
                    var uniqueId = campaign.campaign_id + '_' + campaign.sku;
                    if (sbidMValue !== null) {
                        editedSbidMValues[uniqueId] = sbidMValue;
                    } else {
                        delete editedSbidMValues[uniqueId];
                    }
                });

                // Validate sbidMValue before sending
                if (sbidMValue === null || sbidMValue === undefined || sbidMValue === '') {
                    overlay.style.display = "none";
                    showToast('error', 'SBID M value is required');
                    return;
                }

                // Send bulk update request (same format as main eBay)
                fetch('/bulk-update-ebay2-sbid-m', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        sbid_m: sbidMValue
                    })
                })
                .then(res => res.json())
                .then(data => {
                    overlay.style.display = "none";
                    if (data.status === 200) {
                        // Update input fields in UI for immediate feedback
                        validCampaigns.forEach(function(campaign) {
                            var rowEl = campaign.row.getElement();
                            if (rowEl) {
                                var sbidMInputField = rowEl.querySelector('.sbid-m-input');
                                if (sbidMInputField) {
                                    if (sbidMValue !== null) {
                                        sbidMInputField.value = sbidMValue.toFixed(2);
                                    } else {
                                        sbidMInputField.value = '';
                                    }
                                }
                            }
                        });
                        
                        showToast('success', data.message || 'SBID M saved successfully for ' + (data.updated_count || data.total_count || 0) + ' campaign(s)');
                        
                        // Update the selected rows with the new sbid_m value and clear apprSbid
                        validCampaigns.forEach(function(campaign) {
                            var row = campaign.row;
                            if (row) {
                                var rowData = row.getData();
                                if (rowData.campaign_id && campaignIds.includes(rowData.campaign_id)) {
                                    // Update sbid_m and clear apprSbid so new bid can be pushed
                                    rowData.sbid_m = sbidMValue;
                                    rowData.apprSbid = ''; // Clear apprSbid to allow new bid push
                                    
                                    // Update the row
                                    row.update(rowData);
                                    
                                    // Force redraw of the row to refresh all formatters including APR BID
                                    setTimeout(function() {
                                        row.reformat();
                                    }, 50);
                                }
                            }
                        });
                    } else {
                        showToast('error', data.message || 'Failed to save SBID M');
                    }
                })
                .catch(err => {
                    overlay.style.display = "none";
                    console.error("Error bulk updating SBID M:", err);
                    showToast('error', 'Error updating SBID M values. Please try again.');
                });
            });

            function updateBid(aprBid, campaignId, cell) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-ebay2-keywords-bid-price', {
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

            // Add click handlers to utilization cards (if they exist)
            document.querySelectorAll('.utilization-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    if (type && (type === '7ub' || type === '7ub-1ub')) {
                        // For 7UB and 7UB+1UB cards, we don't show charts, just filter
                        // You can add filter logic here if needed
                        return;
                    }
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

            fetch('/ebay-2/get-utilization-chart-data?type=' + type)
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

        function showToast(type, message) {
            // Create toast container if it doesn't exist
            if (!$('.toast-container').length) {
                $('body').append(
                    '<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
            }

            const toast = $(
                `<div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>`
            );
            $('.toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
@endsection
