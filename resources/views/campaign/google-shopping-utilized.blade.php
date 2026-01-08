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
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
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
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Clicks</div>
                                <div class="h3 mb-0 fw-bold text-primary card-clicks">{{ $clicks->sum() }}</div>
                            </div>
                        </div>

                        <!-- Spend -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Spend</div>
                                <div class="h3 mb-0 fw-bold text-success card-spend">
                                    US${{ number_format($spend->sum(), 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Orders -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Orders</div>
                                <div class="h3 mb-0 fw-bold text-danger card-orders">{{ $orders->sum() }}</div>
                            </div>
                        </div>

                        <!-- Sales -->
                        <div class="col-md-3">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Sales</div>
                                        <div class="h3 mb-0 fw-bold text-info card-sales">
                                            US${{ number_format($sales->sum(), 2) }}
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
                <div class="card-body py-4">
                    <div class="mb-4">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Type Filter and Count Cards Row -->
                                <div class="row g-4 align-items-end mb-3 pb-3 border-bottom">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>Utilization Type
                                        </label>
                                        <select id="utilization-type-select" class="form-select form-select-md">
                                            <option value="all" selected>All</option>
                                            <option value="over">Over Utilized</option>
                                            <option value="under">Under Utilized</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2"></div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold mb-2 d-block" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 flex-wrap align-items-center">
                                            <div class="badge-count-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total SKU</span>
                                                <span class="fw-bold" id="total-sku-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item total-campaign-card" id="total-campaign-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Campaign</span>
                                                <span class="fw-bold" id="total-campaign-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item missing-campaign-card" id="missing-campaign-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Missing</span>
                                                <span class="fw-bold" id="missing-campaign-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item nra-missing-card" id="nra-missing-card" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA MISSING</span>
                                                <span class="fw-bold" id="nra-missing-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item zero-inv-card" id="zero-inv-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Zero INV</span>
                                                <span class="fw-bold" id="zero-inv-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item nra-card" id="nra-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item ra-card" id="ra-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">RA</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB</span>
                                                <span class="fw-bold" id="7ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub-1ub" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB + 1UB</span>
                                                <span class="fw-bold" id="7ub-1ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-search me-1" style="color: #64748b;"></i>Search Campaign
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0" style="border-color: #e2e8f0;">
                                                <i class="fa-solid fa-search" style="color: #94a3b8;"></i>
                                            </span>
                                            <input type="text" id="global-search" class="form-control form-control-md border-start-0" 
                                                   placeholder="Search by campaign name or SKU..."
                                                   style="border-color: #e2e8f0;">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
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
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-md">
                                            <option value="">All Inventory</option>
                                            <option value="INV_GT_0" selected>INV > 0</option>
                                            <option value="INV_0">0 INV</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRL
                                        </label>
                                        <select id="nrl-filter" class="form-select form-select-md">
                                            <option value="">All NRL</option>
                                            <option value="NRL">NRL</option>
                                            <option value="RL">RL</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem; visibility: hidden;">
                                            Export
                                        </label>
                                        <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success w-100 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                        </a>
                                    </div>
                                    <div class="col-md-2 d-none">
                                        <button id="apr-all-sbid-btn" class="btn btn-info btn-sm w-100 d-none">
                                            <i class="fa-solid fa-check-double me-1"></i>
                                            APR ALL SBID
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

            document.body.style.zoom = "90%";

            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let totalSkuCountFromBackend = 0; // Store total SKU count from backend

            const invFilter  = document.querySelector("#inv-filter");
            const nrlFilter  = document.querySelector("#nrl-filter");

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
                
                const allData = table.getData('all');
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
                
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const isValidSku = sku && !sku.toUpperCase().includes('PARENT');
                    
                    let inv = parseFloat(row.INV || 0);
                    
                    // Apply all filters except utilization type filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) {
                        return;
                    }
                    
                    let statusVal = $("#status-filter").val();
                    if (statusVal && row.campaignStatus !== statusVal) {
                        return;
                    }
                    
                    if (inv <= 0 && isValidSku && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }
                    
                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal || invFilterVal === '') {
                        // All Inventory - show all (no filtering)
                    } else if (invFilterVal === "INV_GT_0") {
                        if (inv <= 0) return;
                    } else if (invFilterVal === "INV_0") {
                        if (inv !== 0) return;
                    }
                    
                    if (isValidSku && !processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (rowNra === 'NRA') {
                            nraCount++;
                        } else {
                            raCount++;
                        }
                    }
                    
                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowNrl = row.NRL ? row.NRL.trim() : "";
                        if (rowNrl !== nrlFilterVal) return;
                    }
                    
                    if (isValidSku) {
                        const hasCampaign = row.campaign_id && row.campaignName;
                        
                        if (hasCampaign) {
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                totalCampaignCount++;
                            }
                        } else {
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
                        
                        if (!processedSkusForValidCount.has(sku)) {
                            processedSkusForValidCount.add(sku);
                            validSkuCount++;
                        }
                    }
                    
                    let budget = parseFloat(row.campaignBudgetAmount) || 0;
                    let spend_L7 = parseFloat(row.spend_L7 || 0);
                    let spend_L1 = parseFloat(row.spend_L1 || 0);
                    
                    let ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                    let ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;
                    
                    // Count utilization types (matching backend logic from getFilteredCampaignIds)
                    // Over: UB7 > 90% (only UB7, not UB1)
                    // Under: UB7 < 70% (only UB7, not UB1)
                    if (ub7 > 90) {
                        overCount++;
                    } else if (ub7 < 70) {
                        underCount++;
                    }
                    
                    // Count 7UB (based on 7UB only)
                    if (ub7 > 90 || ub7 < 70) {
                        count7ub++;
                    }
                    
                    // Count 7UB + 1UB (based on both 7UB and 1UB)
                    if ((ub7 > 90 && ub1 > 90) || (ub7 < 70 && ub1 < 70)) {
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

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/google/shopping/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';

                    if (sku && sku.toUpperCase().includes("PARENT")) {
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
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        hozAlign: "left",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px;"></i>
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
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                    },
                    {
                        title: "Clicks L30 ",
                        field: "clicks_L30",
                        hozAlign: "right",
                        formatter: function(cell){
                            var row = cell.getRow().getData();
                            var clicks_L30 = parseFloat(row.clicks_L30) || 0;
                            return clicks_L30;
                        }
                    },
                    {
                        title: "Spend L7",
                        field: "spend_L7",
                        hozAlign: "right",
                        formatter: function(cell){
                            var row = cell.getRow().getData();
                            var spend_L7 = parseFloat(row.spend_L7) || 0;
                            return spend_L7.toFixed(2);
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
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                            }

                            return ub7.toFixed(0) + "%";
                        },
                        sorter: function(a, b, aRow, bRow, column, dir) {
                            var dataA = aRow.getData();
                            var dataB = bRow.getData();

                            var ubA = dataA.campaignBudgetAmount > 0 ? (parseFloat(dataA.spend_L7) / (parseFloat(dataA.campaignBudgetAmount) * 7)) * 100 : 0;
                            var ubB = dataB.campaignBudgetAmount > 0 ? (parseFloat(dataB.spend_L7) / (parseFloat(dataB.campaignBudgetAmount) * 7)) * 100 : 0;

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
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
                                td.classList.add('red-bg');
                            }

                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "cpc_L7",
                        hozAlign: "center",
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
                            // Use backend calculated sbid value directly
                            var sbid = parseFloat(row.sbid) || 0;
                            
                            // Only show SBID if it matches current filter
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var spend_L7 = parseFloat(row.spend_L7) || 0;
                            var spend_L1 = parseFloat(row.spend_L1) || 0;
                            var ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                            var ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;
                            
                            var rowUtilizationType = 'all';
                            if (ub7 > 90 && ub1 > 90) {
                                rowUtilizationType = 'over';
                            } else if (ub7 < 70 && ub1 < 70) {
                                rowUtilizationType = 'under';
                            }
                            
                            // Only show SBID if row matches current filter OR if filter is 'all'
                            if (currentUtilizationType === 'all' || currentUtilizationType === rowUtilizationType) {
                                return sbid === 0 ? '-' : sbid.toFixed(2);
                            }
                            
                            return '-';
                        },
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
                        formatter: function(cell) {
                            const campaignName = cell.getValue();
                            const rowData = cell.getRow().getData();
                            return `<span>${campaignName || ''}</span> <button class="btn btn-sm btn-outline-primary ms-2" onclick="showCampaignChart('${campaignName || ''}')"><i class="fas fa-chart-line"></i></button>`;
                        }
                    }
                ],
                initialSort: [
                    { column: "spend_L7", dir: "desc" }
                ],
                ajaxResponse: function(url, params, response) {
                    totalSkuCountFromBackend = parseInt(response.total_sku_count) || 0;
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    if (totalSkuCountEl) {
                        totalSkuCountEl.textContent = totalSkuCountFromBackend;
                    }
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
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

            // Missing campaign card click handler
            document.getElementById('missing-campaign-card').addEventListener('click', function() {
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

            // NRA missing card click handler
            document.getElementById('nra-missing-card').addEventListener('click', function() {
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

            // Zero INV card click handler
            document.getElementById('zero-inv-card').addEventListener('click', function() {
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

            // NRA card click handler
            document.getElementById('nra-card').addEventListener('click', function() {
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

            // RA card click handler
            document.getElementById('ra-card').addEventListener('click', function() {
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

            // Utilization type dropdown handler
            document.getElementById('utilization-type-select').addEventListener('change', function() {
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
                // Over: UB7 > 90% (only UB7, not UB1) - matches google/shopping/over/utilize
                // Under: UB7 < 70% (only UB7, not UB1) - matches google/shopping/under/utilize
                if (currentUtilizationType === 'all') {
                    // Show all data (no filter on utilization)
                } else if (currentUtilizationType === 'over') {
                    // Over-utilized: ub7 > 90 (only UB7)
                    if (!(ub7 > 90)) return false;
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: ub7 < 70 (only UB7)
                    if (!(ub7 < 70)) return false;
                }

                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                let statusVal = $("#status-filter").val();
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
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
                            const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0);
                            
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

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);

                let filterTimeout = null;
                table.on("dataFiltered", function() {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                });

                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nrl-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 300);
                });

                setTimeout(function() {
                    updateButtonCounts();
                }, 1000);
                loadUtilizationCounts();
            });

            table.on("dataLoaded", function() {
                table.setFilter(combinedFilter);
                setTimeout(function() {
                    updateButtonCounts();
                }, 500);
                loadUtilizationCounts();
            });


            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let colsToToggle = ["INV", "L30", "DIL %", "NRL", "NRA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            // Handle editable-select changes (NRL, NRA)
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

                    fetch('/update-google-nr-data', {
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
                        if (data.status === 200 && typeof table !== 'undefined' && table) {
                            let row = table.searchRows('sku', '=', sku);
                            if (row.length > 0) {
                                row[0].update({[field]: value});
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

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();

                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if(rowEl && rowEl.offsetParent !== null){
                        
                        var rowData = row.getData();
                        var cpc_L1 = parseFloat(rowData.cpc_L1) || 0;
                        var cpc_L7 = parseFloat(rowData.cpc_L7) || 0;
                        var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                        var spend_L7 = parseFloat(rowData.spend_L7) || 0;
                        var ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;

                        var spend_L1 = parseFloat(rowData.spend_L1 || 0);
                        var ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;
                        var rowUtilizationType = 'all';
                        if (ub7 > 90) {
                            rowUtilizationType = 'over';
                        } else if (ub7 < 70) {
                            rowUtilizationType = 'under';
                        }

                        var sbid = 0;
                        if (currentUtilizationType === 'over' && rowUtilizationType === 'over') {
                            if (cpc_L7 === 0) {
                                sbid = 0.75;
                            } else {
                                sbid = Math.floor(cpc_L7 * 0.90 * 100) / 100;
                            }
                        } else if (currentUtilizationType === 'under' && rowUtilizationType === 'under') {
                            if (cpc_L1 === 0 && cpc_L7 === 0) {
                                sbid = 0.75;
                            } else if (ub7 < 10 || cpc_L7 === 0) {
                                sbid = 0.75;
                            } else if (cpc_L7 > 0 && cpc_L7 < 0.30) {
                                sbid = parseFloat((cpc_L7 + 0.20).toFixed(2));
                            } else {
                                sbid = Math.floor(cpc_L7 * 1.10 * 100) / 100;
                            }
                        }

                        if (sbid > 0) {
                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbid);
                        }
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

            document.getElementById("export-btn").addEventListener("click", function () {
                let filteredData = table.getData("active");

                let exportData = filteredData.map(row => {
                    let cpc_L1 = parseFloat(row.cpc_L1 || 0);
                    let cpc_L7 = parseFloat(row.cpc_L7 || 0);
                    let budget = parseFloat(row.campaignBudgetAmount) || 0;
                    let spend_L7 = parseFloat(row.spend_L7) || 0;
                    let ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;
                    let spend_L1 = parseFloat(row.spend_L1 || 0);
                    let ub1 = budget > 0 ? (spend_L1 / budget) * 100 : 0;
                    let rowUtilizationType = 'all';
                    if (ub7 > 90) {
                        rowUtilizationType = 'over';
                    } else if (ub7 < 70) {
                        rowUtilizationType = 'under';
                    }

                    let sbid = 0;
                    if (currentUtilizationType === 'over' && rowUtilizationType === 'over') {
                        sbid = parseFloat((cpc_L7 * 0.90).toFixed(2));
                    } else if (currentUtilizationType === 'under' && rowUtilizationType === 'under') {
                        if (cpc_L1 === 0 && cpc_L7 === 0) {
                            sbid = 0.75;
                        } else if (ub7 < 10 || cpc_L7 === 0) {
                            sbid = 0.75;
                        } else if (cpc_L7 > 0 && cpc_L7 < 0.30) {
                            sbid = parseFloat((cpc_L7 + 0.20).toFixed(2));
                        } else {
                            sbid = parseFloat((cpc_L7 * 1.10).toFixed(2));
                        }
                    }


                    return {
                        campaignName: row.campaignName || "",
                        sbid: sbid > 0 ? sbid.toFixed(2) : '-'
                    };
                });

                if (exportData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_over_acos_pink.xlsx");
            });

            document.body.style.zoom = "78%";
        });
    </script>

    <script>
        const ctx = document.getElementById('campaignChart').getContext('2d');

        const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($dates) !!},
            datasets: [
                {
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
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 },
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            if (context.dataset.label.includes("Spend") || context.dataset.label.includes("Sales")) {
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
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end) {
            const startDate = start.format("YYYY-MM-DD");
            const endDate   = end.format("YYYY-MM-DD");

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
            data: { startDate, endDate },
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
                $('#modal-clicks, #modal-spend, #modal-orders, #modal-sales, #modal-impressions, #modal-ctr').text('Loading...');
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
