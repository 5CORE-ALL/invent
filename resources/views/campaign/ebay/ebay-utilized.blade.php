@extends('layouts.vertical', ['title' => 'Ebay - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
            height: 80px !important;
            min-height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 8px 4px;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
            height: 80px !important;
            vertical-align: middle;
            position: relative;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-90deg);
            white-space: nowrap;
            overflow: visible;
            width: auto;
            height: auto;
            display: block;
            text-align: center;
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
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.1);
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
            padding: 8px 12px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            font-size: 10.5px !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        }

        .utilization-type-btn.active {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15) !important;
            border-width: 2px;
        }

        .utilization-type-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }

        body {
            zoom: 90%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay - Utilized',
        'sub_title' => 'Ebay - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e5e7eb;">
                <div class="card-body py-4 px-4">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-4 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            eBay Campaign Utilization Dashboard
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Utilization Type Selector -->
                            <div class="col-md-8">
                                <div class="d-flex gap-2 align-items-start flex-wrap">
                                    <span class="fw-bold me-2 mt-2" style="font-size: 0.95rem; color: #495057;">Filter by Type:</span>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn active" data-type="total" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border-color: #0056b3; width: 120px;">Total Campaigns</button>
                                        <i class="fa fa-eye text-primary mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-primary" id="total-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="over" style="background: linear-gradient(135deg, #ff01d0 0%, #ff6ec7 100%); color: white; border-color: #ff01d0; width: 120px;">Over Utilized</button>
                                        <i class="fa fa-eye text-primary mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-primary" id="over-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="under" style="background: linear-gradient(135deg, #ff2727 0%, #ff6b6b 100%); color: white; border-color: #ff2727; width: 120px;">Under Utilized</button>
                                        <i class="fa fa-eye text-danger mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-danger" id="under-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="under-above-20" style="background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%); color: white; border-color: #8B0000; width: 120px;">Under $20+</button>
                                        <i class="fa fa-eye text-danger mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-danger" id="under-above-20-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="correctly" style="background: linear-gradient(135deg, #28a745 0%, #5cb85c 100%); color: white; border-color: #28a745; width: 120px;">Correctly Utilized</button>
                                        <i class="fa fa-eye text-success mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-success" id="correctly-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="transition" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; border-color: #f59e0b; width: 120px;">Transition</button>
                                        <i class="fa fa-eye text-warning mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-warning" id="transition-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="ub7-green-ub1-red" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border-color: #138496; width: 120px;">UB7 Green/UB1 Red</button>
                                        <i class="fa fa-eye text-info mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-info" id="ub7-green-ub1-red-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="ub7-green-ub1-pink" style="background: linear-gradient(135deg, #e83e8c 0%, #d91a72 100%); color: white; border-color: #d91a72; width: 120px;">UB7 Green/UB1 Pink</button>
                                        <i class="fa fa-eye" style="color: #e83e8c; margin-top: 4px; font-size: 14px;"></i>
                                        <span class="btn-count fw-bold" style="color: #e83e8c; font-size: 12px;" id="ub7-green-ub1-pink-btn-count">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="zero-inv" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white; border-color: #495057; width: 120px;">0 Inventory</button>
                                        <i class="fa fa-eye text-secondary mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-secondary" id="zero-inv-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                    <div class="d-flex flex-column align-items-center">
                                        <button class="utilization-type-btn" data-type="low-price" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-color: #c82333; width: 120px;">Price < $20</button>
                                        <i class="fa fa-eye text-danger mt-1" style="font-size: 14px;"></i>
                                        <span class="btn-count fw-bold text-danger" id="low-price-btn-count" style="font-size: 12px;">0</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-4">
                                <div class="d-flex gap-3 justify-content-end align-items-center flex-wrap">
                                    <!-- Total Stats -->
                                    <div class="d-flex gap-3 me-3">
                                        <div class="text-end px-2 py-1" style="border-left: 3px solid #007bff; background: #f8f9fa; border-radius: 4px;">
                                            <div class="text-muted small" style="font-size: 0.85rem;">L30 Spend</div>
                                            <div class="fw-bold text-primary" style="font-size: 1.05rem;" id="total-l30-spend">$0.00</div>
                                        </div>
                                        <div class="text-end px-2 py-1" style="border-left: 3px solid #28a745; background: #f8f9fa; border-radius: 4px;">
                                            <div class="text-muted small" style="font-size: 0.85rem;">L30 Sales</div>
                                            <div class="fw-bold text-success" style="font-size: 1.05rem;" id="total-l30-sales">$0.00</div>
                                        </div>
                                        <div class="text-end px-2 py-1" style="border-left: 3px solid #ffc107; background: #f8f9fa; border-radius: 4px;">
                                            <div class="text-muted small" style="font-size: 0.85rem;">Avg ACOS</div>
                                            <div class="fw-bold text-warning" style="font-size: 1.05rem;" id="total-acos">0.00%</div>
                                        </div>
                                    </div>
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none shadow-sm" style="border-radius: 6px; font-weight: 600;">
                                        <i class="fa-solid fa-check-double me-1"></i>
                                        Apply All SBID
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="input-group shadow-sm" style="border-radius: 6px;">
                                    <span class="input-group-text bg-light" style="border-right: none;">
                                        <i class="fa fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="global-search" class="form-control" placeholder="Search campaigns..." style="border-left: none;">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select id="status-filter" class="form-select form-select-md shadow-sm" style="border-radius: 6px; font-weight: 500;">
                                    <option value="">All Status</option>
                                    <option value="RUNNING">Running</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="ENDED">Ended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="inv-filter" class="form-select form-select-md shadow-sm" style="border-radius: 6px; font-weight: 500;">
                                    <option value="">All Inventory</option>
                                    <option value="ALL">All</option>
                                    <option value="INV_0">0 Inventory</option>
                                    <option value="OTHERS">Others</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="nra-filter" class="form-select form-select-md shadow-sm" style="border-radius: 6px; font-weight: 500;">
                                    <option value="">All NRA Status</option>
                                    <option value="NRA">NRA</option>
                                    <option value="RA">RA</option>
                                    <option value="LATER">Later</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Info -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div id="pagination-info" class="text-muted small fw-semibold" style="background: #f8f9fa; padding: 8px 12px; border-radius: 6px; display: inline-block;"></div>
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
                <div class="modal-header bg-primary text-white" style="border-top-left-radius: 8px; border-top-right-radius: 8px;">
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

    <div id="progress-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.75); z-index: 9999; backdrop-filter: blur(4px);">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem; border-width: 4px;">
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
            let currentUtilizationType = 'total'; // Default to total
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts - shows all counts on all buttons
            let countUpdateTimeout = null;
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                // Debounce for performance
                if (countUpdateTimeout) {
                    clearTimeout(countUpdateTimeout);
                }
                countUpdateTimeout = setTimeout(function() {
                    // Get filtered data to show current filter results
                    const allData = table.getData();
                    
                    // Count for each type (mutually exclusive like controller)
                    let totalCount = allData.length;
                    let overCount = 0;
                    let underCount = 0;
                    let underAbove20Count = 0;
                    let correctlyCount = 0;
                    let transitionCount = 0;
                    let ub7GreenUb1RedCount = 0;
                    let ub7GreenUb1PinkCount = 0;
                    let zeroInvCount = 0;
                    let lowPriceCount = 0;

                    allData.forEach(function(row) {
                        let acos = parseFloat(row.acos || 0);
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);
                        let price = parseFloat(row.price || 0);
                        let inv = parseFloat(row.INV || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        // Helper function to get UB color
                        function getUbColor(ub) {
                            if (ub < 70) return 'red';
                            if (ub >= 70 && ub <= 90) return 'green';
                            return 'pink';
                        }

                        let rowAcos = parseFloat(acos) || 0;
                        if (isNaN(rowAcos) || rowAcos === 0) {
                            rowAcos = 100;
                        }

                        // Check DIL color for debugging only
                        let l30 = parseFloat(row.L30 || 0);
                        let dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                        let dilColor = getDilColor(dilDecimal);

                        // Get UB colors
                        let ub7Color = getUbColor(ub7);
                        let ub1Color = getUbColor(ub1);

                        // Mutually exclusive categorization with comprehensive coverage
                        let categorized = false;

                        // Priority 1: Over-utilized check
                        if (!categorized && ub7 > 90 && ub1 > 90) {
                            overCount++;
                            categorized = true;
                        }

                        // Priority 2: Under-utilized check (no exclusions)
                        if (!categorized && ub7 <= 70 && ub1 <= 70) {
                            underCount++;
                            categorized = true;
                        }

                        // Priority 3: Transition check (UB7 and UB1 have different colors)
                        if (!categorized && ub7Color !== ub1Color) {
                            transitionCount++;
                            categorized = true;
                        }

                        // Count specific case: UB7 green and UB1 red (separate from other categories)
                        if (ub7Color === 'green' && ub1Color === 'red') {
                            ub7GreenUb1RedCount++;
                        }
                        
                        // Count specific case: UB7 green and UB1 pink (separate from other categories)
                        if (ub7Color === 'green' && ub1Color === 'pink') {
                            ub7GreenUb1PinkCount++;
                        }

                        // Priority 4: Correctly-utilized check (both UB7 and UB1 in 70-90% range)
                        if (!categorized && ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90) {
                            correctlyCount++;
                            categorized = true;
                        }

                        // Remaining campaigns are uncategorized edge cases
                        
                        // Count campaigns with 0 inventory (separate from other categories)
                        if (inv === 0) {
                            zeroInvCount++;
                        }
                        
                        // Count campaigns with price < $20 and inventory > 0 (separate from other categories)
                        if (price < 20 && inv > 0) {
                            lowPriceCount++;
                        }
                        
                        // Count under-utilized campaigns with price >= $20 and inventory > 0 (separate from other categories)
                        if (ub7 <= 70 && ub1 <= 70 && price >= 20 && inv > 0) {
                            underAbove20Count++;
                        }
                    });

                    // Update all button counts - show counts under eye icons
                    const totalBtnCount = document.getElementById('total-btn-count');
                    const overBtnCount = document.getElementById('over-btn-count');
                    const underBtnCount = document.getElementById('under-btn-count');
                    const underAbove20BtnCount = document.getElementById('under-above-20-btn-count');
                    const correctlyBtnCount = document.getElementById('correctly-btn-count');
                    const transitionBtnCount = document.getElementById('transition-btn-count');
                    const ub7GreenUb1RedBtnCount = document.getElementById('ub7-green-ub1-red-btn-count');
                    const ub7GreenUb1PinkBtnCount = document.getElementById('ub7-green-ub1-pink-btn-count');
                    const zeroInvBtnCount = document.getElementById('zero-inv-btn-count');
                    const lowPriceBtnCount = document.getElementById('low-price-btn-count');
                    
                    // Update counts silently (debug logs removed for production)
                    
                    if (totalBtnCount) totalBtnCount.textContent = totalCount;
                    if (overBtnCount) overBtnCount.textContent = overCount;
                    if (underBtnCount) underBtnCount.textContent = underCount;
                    if (underAbove20BtnCount) underAbove20BtnCount.textContent = underAbove20Count;
                    if (correctlyBtnCount) correctlyBtnCount.textContent = correctlyCount;
                    if (transitionBtnCount) transitionBtnCount.textContent = transitionCount;
                    if (ub7GreenUb1RedBtnCount) ub7GreenUb1RedBtnCount.textContent = ub7GreenUb1RedCount;
                    if (ub7GreenUb1PinkBtnCount) ub7GreenUb1PinkBtnCount.textContent = ub7GreenUb1PinkCount;
                    if (zeroInvBtnCount) zeroInvBtnCount.textContent = zeroInvCount;
                    if (lowPriceBtnCount) lowPriceBtnCount.textContent = lowPriceCount;
                }, 150);
            }

            // Utilization type button handlers
            document.querySelectorAll('.utilization-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.utilization-type-btn').forEach(b => {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    currentUtilizationType = this.getAttribute('data-type');
                    
                    if (typeof table !== 'undefined' && table) {
                        table.setFilter(combinedFilter);
                        table.redraw(true);
                        // Force immediate count update
                        updateButtonCounts();
                    }
                });
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay/utilized/ads/data",
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
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: "rows",
                paginationCounterElement: "#pagination-info",
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
                        title: "Parent",
                        field: "parent"
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
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue() || '';
                            
                            if (value === 'RA') {
                                // Green dot for RA
                                return '<div style="display: flex; justify-content: center; align-items: center;"><span style="width: 12px; height: 12px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span></div>';
                            } else if (value === 'NRA') {
                                // Red button for NRA
                                return '<div style="display: flex; justify-content: center; align-items: center;"><button class="btn btn-sm" style="background-color: #dc3545; color: white; border: none; padding: 2px 8px; font-size: 10px;">NRA</button></div>';
                            } else if (value === 'LATER') {
                                // Keep dropdown for LATER (editable)
                                return `
                                    <select class="form-select form-select-sm editable-select" 
                                            data-sku="${sku}" 
                                            data-field="NR"
                                            style="width: 90px; background-color: #ffc107; color: white; border: none;">
                                        <option value="RA" style="background-color: #28a745; color: white;">RA</option>
                                        <option value="NRA" style="background-color: #dc3545; color: white;">NRA</option>
                                        <option value="LATER" selected style="background-color: #ffc107; color: white;">LATER</option>
                                    </select>
                                `;
                            } else {
                                // Default dropdown for empty or unknown values
                                return `
                                    <select class="form-select form-select-sm editable-select" 
                                            data-sku="${sku}" 
                                            data-field="NR"
                                            style="width: 90px;">
                                        <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                        <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                        <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                    </select>
                                `;
                            }
                        },
                        hozAlign: "center",
                        visible: true,
                        width: 90
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
                        hozAlign: "right",
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return "$" + value.toFixed(2);
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
                            return value.toFixed(2) + "%";
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
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                return "100%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                            }
                            return acos.toFixed(0) + "%";
                        },
                        width: 70
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb7 = parseFloat(aData.campaignBudgetAmount) > 0 ? (parseFloat(aData.l7_spend || 0) / (parseFloat(aData.campaignBudgetAmount) * 7)) * 100 : 0;
                            var bUb7 = parseFloat(bData.campaignBudgetAmount) > 0 ? (parseFloat(bData.l7_spend || 0) / (parseFloat(bData.campaignBudgetAmount) * 7)) * 100 : 0;
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
                                // Over-utilized: Check ACOS first, then UB7
                                var rowAcos = parseFloat(row.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }
                                if (rowAcos > totalACOSValue) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 >= 70 && ub7 <= 90) {
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
                            var aUb1 = parseFloat(aData.campaignBudgetAmount) > 0 ? (parseFloat(aData.l1_spend || 0) / parseFloat(aData.campaignBudgetAmount)) * 100 : 0;
                            var bUb1 = parseFloat(bData.campaignBudgetAmount) > 0 ? (parseFloat(bData.l1_spend || 0) / parseFloat(bData.campaignBudgetAmount)) * 100 : 0;
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
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            
                            // Helper function to calculate SBID for a row
                            function calculateSbid(rowData) {
                                var l1Cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7Cpc = parseFloat(rowData.l7_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = budget > 0 ? (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100 : 0;
                                var ub1 = budget > 0 ? (parseFloat(rowData.l1_spend) || 0) / budget * 100 : 0;
                                var sbid = 0;
                                
                                // Helper function to get UB color
                                function getUbColor(ub) {
                                    if (ub >= 70 && ub <= 90) return 'green';
                                    if (ub > 90) return 'pink';
                                    return 'red';
                                }
                                
                                // Check UB7 and UB1 colors (no exclusions)
                                var ub7Color = getUbColor(ub7);
                                var ub1Color = getUbColor(ub1);
                                
                                // Global rule: If L7_CPC is 0, set SBID to 0.75 regardless of utilization type
                                if (l7Cpc === 0) {
                                    return 0.75;
                                }
                                
                                // Rule: If both UB7 and UB1 are below 70%, set SBID as L7_CPC + 0.10
                                if (ub7 < 70 && ub1 < 70) {
                                    return parseFloat((l7Cpc + 0.10).toFixed(2));
                                }
                                
                                // Rule: If both UB7 and UB1 are above 90%, set SBID as L1_CPC * 0.90
                                if (ub7 > 90 && ub1 > 90) {
                                    if (l1Cpc > 0) {
                                        return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    } else if (l7Cpc > 0) {
                                        return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    } else {
                                        return 0;
                                    }
                                }
                                
                                if (currentUtilizationType === 'total') {
                                    // For total campaigns, determine individual campaign's utilization status
                                    var rowAcos = parseFloat(rowData.acos) || 0;
                                    if (isNaN(rowAcos) || rowAcos === 0) {
                                        rowAcos = 100;
                                    }
                                    
                                    // Check DIL color
                                    var l30 = parseFloat(rowData.L30 || 0);
                                    var inv = parseFloat(rowData.INV || 0);
                                    var dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                                    var dilColor = getDilColor(dilDecimal);
                                    var isPink = (dilColor === "pink");
                                    
                                    // Determine utilization status
                                    var isOverUtilized = false;
                                    var isUnderUtilized = false;
                                    
                                    // Check over-utilized first
                                    if (!isPink) {
                                        var condition1 = (rowAcos > totalACOSValue && ub7 > 90);
                                        var condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                                        if (condition1 || condition2) {
                                            isOverUtilized = true;
                                        }
                                    }
                                    
                                    // Check under-utilized
                                    if (!isOverUtilized && ub7 < 70 && ub1 < 70 && parseFloat(rowData.price || 0) >= 20 && inv > 0 && !isPink) {
                                        isUnderUtilized = true;
                                    }
                                    
                                    // Apply SBID logic based on determined status
                                    if (isOverUtilized) {
                                        sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    } else if (isUnderUtilized) {
                                        if (ub7 >= 10 && ub7 <= 50) {
                                            sbid = Math.floor(l7Cpc * 1.20 * 100) / 100;
                                        } else {
                                            sbid = Math.floor(l7Cpc * 1.10 * 100) / 100;
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
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 >= 10 && ub7 <= 50) {
                                        sbid = Math.floor(l7Cpc * 1.20 * 100) / 100;
                                    } else {
                                        sbid = Math.floor(l7Cpc * 1.10 * 100) / 100;
                                    }
                                } else {
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                }
                                
                                return sbid;
                            }
                            
                            var aSbid = calculateSbid(aData);
                            var bSbid = calculateSbid(bData);
                            
                            return aSbid - bSbid;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            var ub1 = budget > 0 ? (parseFloat(row.l1_spend) || 0) / budget * 100 : 0;
                            
                            // Helper function to get UB color
                            function getUbColor(ub) {
                                if (ub >= 70 && ub <= 90) return 'green';
                                if (ub > 90) return 'pink';
                                return 'red';
                            }
                            
                            // Check if UB7 and UB1 colors match
                            var ub7Color = getUbColor(ub7);
                            var ub1Color = getUbColor(ub1);
                            
                            if (ub7Color !== ub1Color) {
                                return '0.00'; // No SBID suggestion if colors don't match
                            }
                            
                            // Debug logging for specific campaigns
                            var campaignName = row.campaignName || '';
                            var isDebugCampaign = campaignName.includes('WF 10 140 PP 4OHM') || campaignName.includes('WF 15 156 PP 4OHM 2PCS') || campaignName.includes('RM 8 BG');
                            
                            var sbid = 0;
                            
                            // Global rule: If L7_CPC is 0, set SBID to 0.75 regardless of utilization type
                            if (l7_cpc === 0) {
                                sbid = 0.75;
                                if (isDebugCampaign) console.log('Global Rule Applied - L7_CPC = 0 - SBID:', sbid);
                                return sbid.toFixed(2);
                            }
                            
                            // Rule: If both UB7 and UB1 are below 70%, set SBID as L7_CPC + 0.10
                            if (ub7 < 70 && ub1 < 70) {
                                sbid = parseFloat((l7_cpc + 0.10).toFixed(2));
                                if (isDebugCampaign) console.log('UB7 & UB1 < 70% Rule Applied - SBID:', sbid, '(L7_CPC + 0.10)');
                                return sbid.toFixed(2);
                            }
                            
                            // Rule: If both UB7 and UB1 are above 90%, set SBID as L1_CPC * 0.90
                            if (ub7 > 90 && ub1 > 90) {
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    if (isDebugCampaign) console.log('UB7 & UB1 > 90% Rule Applied - SBID:', sbid, '(L1_CPC * 0.90)');
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    if (isDebugCampaign) console.log('UB7 & UB1 > 90% Rule Applied - SBID:', sbid, '(L7_CPC * 0.90 fallback)');
                                } else {
                                    sbid = 0;
                                    if (isDebugCampaign) console.log('UB7 & UB1 > 90% Rule Applied - SBID:', sbid, '(No CPC data)');
                                }
                                return sbid.toFixed(2);
                            }
                            
                            if (currentUtilizationType === 'total') {
                                // For total campaigns, determine individual campaign's utilization status
                                var rowAcos = parseFloat(row.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }
                                
                                // Check DIL color
                                var l30 = parseFloat(row.L30 || 0);
                                var inv = parseFloat(row.INV || 0);
                                var dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                                var dilColor = getDilColor(dilDecimal);
                                var isPink = (dilColor === "pink");
                                
                                // Determine utilization status (same logic as combinedFilter)
                                var isOverUtilized = false;
                                var isUnderUtilized = false;
                                
                                // Check over-utilized first (priority 1)
                                if (!isPink) {
                                    var condition1 = (rowAcos > totalACOSValue && ub7 > 90);
                                    var condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                                    if (condition1 || condition2) {
                                        isOverUtilized = true;
                                    }
                                }
                                
                                // Check under-utilized (priority 2: only if not over-utilized)
                                if (!isOverUtilized && ub7 < 70 && ub1 < 70 && parseFloat(row.price || 0) >= 20 && inv > 0 && !isPink) {
                                    isUnderUtilized = true;
                                }
                                
                                // Debug logging
                                if (isDebugCampaign) {
                                    console.log('=== SBID Debug for:', campaignName, '===');
                                    console.log('L1_CPC:', l1_cpc, 'L7_CPC:', l7_cpc);
                                    console.log('Budget:', budget, 'UB7:', ub7.toFixed(2), 'UB1:', ub1.toFixed(2));
                                    console.log('ACOS:', rowAcos, 'Total ACOS:', totalACOSValue);
                                    console.log('Price:', row.price, 'INV:', inv, 'L30:', l30);
                                    console.log('DIL Decimal:', dilDecimal, 'DIL Color:', dilColor, 'Is Pink:', isPink);
                                    console.log('Is Over Utilized:', isOverUtilized);
                                    console.log('Is Under Utilized:', isUnderUtilized);
                                }
                                
                                // Apply SBID logic based on determined status
                                if (isOverUtilized) {
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                        if (isDebugCampaign) console.log('Over Utilized - SBID:', sbid, '(L1_CPC * 0.90)');
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                        if (isDebugCampaign) console.log('Over Utilized - SBID:', sbid, '(L7_CPC * 0.90 fallback)');
                                    } else {
                                        sbid = 0;
                                        if (isDebugCampaign) console.log('Over Utilized - SBID:', sbid, '(No CPC data available)');
                                    }
                                } else if (isUnderUtilized) {
                                    if (ub7 >= 10 && ub7 <= 50) {
                                        sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                        if (isDebugCampaign) console.log('Under Utilized (UB7 10-50%) - SBID:', sbid, '(L7_CPC * 1.20)');
                                    } else {
                                        sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                        if (isDebugCampaign) console.log('Under Utilized (UB7 > 50%) - SBID:', sbid, '(L7_CPC * 1.10)');
                                    }
                                } else {
                                    // Correctly-utilized or other: SBID = L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                        if (isDebugCampaign) console.log('Correctly Utilized/Other - SBID:', sbid, '(L1_CPC * 0.90)');
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                        if (isDebugCampaign) console.log('Correctly Utilized/Other - SBID:', sbid, '(L7_CPC * 0.90 fallback)');
                                    } else {
                                        sbid = 0;
                                        if (isDebugCampaign) console.log('Correctly Utilized/Other - SBID:', sbid, '(No CPC data available)');
                                    }
                                }
                                
                                if (isDebugCampaign) {
                                    console.log('Final SBID:', sbid);
                                    console.log('=== End Debug ===');
                                }
                            } else if (currentUtilizationType === 'over') {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (currentUtilizationType === 'under') {
                                if (ub7 >= 10 && ub7 <= 50) {
                                    sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                } else {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
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
                            return sbid.toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
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
                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = 0;
                                var ub1 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                    ub1 = (parseFloat(rowData.l1_spend) || 0) / budget * 100;
                                }
                                
                                // Helper function to get UB color
                                function getUbColor(ub) {
                                    if (ub >= 70 && ub <= 90) return 'green';
                                    if (ub > 90) return 'pink';
                                    return 'red';
                                }
                                
                                // Check if UB7 and UB1 colors match
                                var ub7Color = getUbColor(ub7);
                                var ub1Color = getUbColor(ub1);
                                
                                if (ub7Color !== ub1Color) {
                                    return; // No SBID update if colors don't match
                                }
                                
                                var sbid = 0;
                                
                                // Global rule: If L7_CPC is 0, set SBID to 0.75 regardless of utilization type
                                if (l7_cpc === 0) {
                                    sbid = 0.75;
                                } else if (ub7 < 70 && ub1 < 70) {
                                    // Rule: If both UB7 and UB1 are below 70%, set SBID as L7_CPC + 0.10
                                    sbid = parseFloat((l7_cpc + 0.10).toFixed(2));
                                } else if (ub7 > 90 && ub1 > 90) {
                                    // Rule: If both UB7 and UB1 are above 90%, set SBID as L1_CPC * 0.90
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }
                                } else if (currentUtilizationType === 'total') {
                                    // For total campaigns, determine individual campaign's utilization status
                                    var rowAcos = parseFloat(rowData.acos) || 0;
                                    if (isNaN(rowAcos) || rowAcos === 0) {
                                        rowAcos = 100;
                                    }
                                    
                                    // Check DIL color
                                    var l30 = parseFloat(rowData.L30 || 0);
                                    var inv = parseFloat(rowData.INV || 0);
                                    var dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                                    var dilColor = getDilColor(dilDecimal);
                                    var isPink = (dilColor === "pink");
                                    
                                    // Determine utilization status
                                    var isOverUtilized = false;
                                    var isUnderUtilized = false;
                                    
                                    // Check over-utilized first
                                    if (!isPink) {
                                        var condition1 = (rowAcos > totalACOSValue && ub7 > 90);
                                        var condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                                        if (condition1 || condition2) {
                                            isOverUtilized = true;
                                        }
                                    }
                                    
                                    // Check under-utilized
                                    if (!isOverUtilized && ub7 < 70 && ub1 < 70 && parseFloat(rowData.price || 0) >= 20 && inv > 0 && !isPink) {
                                        isUnderUtilized = true;
                                    }
                                    
                                    // Apply SBID logic based on determined status
                                    if (isOverUtilized) {
                                        if (l1_cpc > 0) {
                                            sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                        } else if (l7_cpc > 0) {
                                            sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0;
                                        }
                                    } else if (isUnderUtilized) {
                                        if (ub7 >= 10 && ub7 <= 50) {
                                            sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                        } else {
                                            sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                        }
                                    } else {
                                        // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                        if (l1_cpc > 0) {
                                            sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                        } else if (l7_cpc > 0) {
                                            sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                        } else {
                                            sbid = 0;
                                        }
                                    }
                                } else if (currentUtilizationType === 'over') {
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 >= 10 && ub7 <= 50) {
                                        sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                    } else {
                                        sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                    }
                                } else {
                                    // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                    if (l1_cpc > 0) {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    } else if (l7_cpc > 0) {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    } else {
                                        sbid = 0;
                                    }
                                }
                                updateBid(sbid, rowData.campaign_id);
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
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;
                    
                    console.log('Total campaigns loaded:', response.data ? response.data.length : 0);
                    
                    // Update total display elements
                    document.getElementById("total-l30-spend").innerText = "$" + totalL30Spend.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById("total-l30-sales").innerText = "$" + totalL30Sales.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    document.getElementById("total-acos").innerText = totalACOSValue.toFixed(2) + "%";
                    
                    return response.data;
                }
            });

            // Combined filter function
            function combinedFilter(data) {
                let acos = parseFloat(data.acos || 0);
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                let price = parseFloat(data.price || 0);

                // Helper function to get UB color
                function getUbColor(ub) {
                    if (ub < 70) return 'red';
                    if (ub >= 70 && ub <= 90) return 'green';
                    return 'pink';
                }

                let rowAcos = parseFloat(acos) || 0;
                if (isNaN(rowAcos) || rowAcos === 0) {
                    rowAcos = 100;
                }

                // Apply utilization type filter
                if (currentUtilizationType === 'total') {
                    // Total campaigns - no utilization filter, just other filters
                } else if (currentUtilizationType === 'over') {
                    if (!(ub7 > 90 && ub1 > 90)) {
                        return false;
                    }
                } else if (currentUtilizationType === 'under') {
                    if (!(ub7 <= 70 && ub1 <= 70)) return false;
                    // No additional exclusions - show all under-utilized campaigns
                } else if (currentUtilizationType === 'under-above-20') {
                    // Under-utilized with price >= $20 and inventory > 0
                    if (!(ub7 <= 70 && ub1 <= 70)) return false;
                    if (parseFloat(data.price || 0) < 20) return false;
                    if (parseFloat(data.INV || 0) <= 0) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Strict criteria: both UB7 and UB1 must be between 70% and 90% (both green)
                    if (!((ub7 >= 70 && ub7 <= 90) && (ub1 >= 70 && ub1 <= 90))) return false;
                } else if (currentUtilizationType === 'transition') {
                    // UB7 and UB1 have different colors
                    let ub7Color = getUbColor(ub7);
                    let ub1Color = getUbColor(ub1);
                    if (!(ub7Color !== ub1Color)) return false;
                } else if (currentUtilizationType === 'ub7-green-ub1-red') {
                    // UB7 is green (70-90%) and UB1 is red (<70%)
                    let ub7Color = getUbColor(ub7);
                    let ub1Color = getUbColor(ub1);
                    if (!(ub7Color === 'green' && ub1Color === 'red')) return false;
                } else if (currentUtilizationType === 'ub7-green-ub1-pink') {
                    // UB7 is green (70-90%) and UB1 is pink (>90%)
                    let ub7Color = getUbColor(ub7);
                    let ub1Color = getUbColor(ub1);
                    if (!(ub7Color === 'green' && ub1Color === 'pink')) return false;
                } else if (currentUtilizationType === 'zero-inv') {
                    // Show only campaigns with 0 inventory
                    if (parseFloat(data.INV) !== 0) return false;
                } else if (currentUtilizationType === 'low-price') {
                    // Show only campaigns with price < $20 and inventory > 0
                    if (parseFloat(data.price || 0) >= 20) return false;
                    if (parseFloat(data.INV || 0) <= 0) return false;
                }

                // Global search filter
                let searchVal = document.getElementById("global-search")?.value?.toLowerCase() || "";
                if (searchVal && searchVal.trim() !== "") {
                    let campaignName = (data.campaignName || "").toLowerCase();
                    let sku = (data.sku || "").toLowerCase();
                    let parent = (data.parent || "").toLowerCase();
                    
                    if (!campaignName.includes(searchVal) && 
                        !sku.includes(searchVal) && 
                        !parent.includes(searchVal)) {
                        return false;
                    }
                }

                // Status filter
                let statusVal = document.getElementById("status-filter")?.value || "";
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
                }

                // Inventory filter - implement proper logic
                let invFilterVal = document.getElementById("inv-filter")?.value || "";
                if (invFilterVal) {
                    let inv = parseFloat(data.INV || 0);
                    if (invFilterVal === 'INV_0' && inv !== 0) {
                        return false;
                    } else if (invFilterVal === 'OTHERS' && inv === 0) {
                        return false;
                    }
                    // 'ALL' shows everything, so no filter needed
                }

                // NR filter
                let nraFilterVal = document.getElementById("nra-filter")?.value || "";
                if (nraFilterVal) {
                    let rowVal = data.NR || "";
                    if (rowVal !== nraFilterVal) return false;
                }

                return true;
            }

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);
                // Initial count update
                updateButtonCounts();
            });

            // Update counts when data is filtered
            table.on("dataFiltered", function(filters, rows) {
                updateButtonCounts();
            });

            // Update counts when data is loaded
            table.on("dataLoaded", function(data) {
                updateButtonCounts();
            });

            // Debounced search
            let searchTimeout = null;
            const searchInput = document.getElementById("global-search");
            if (searchInput) {
                searchInput.addEventListener("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });
            }

            // Filter change handlers
            const statusFilter = document.getElementById("status-filter");
            const invFilter = document.getElementById("inv-filter");
            const nraFilter = document.getElementById("nra-filter");
            
            if (statusFilter) {
                statusFilter.addEventListener("change", function() {
                    table.setFilter(combinedFilter);
                });
            }
            
            if (invFilter) {
                invFilter.addEventListener("change", function() {
                    table.setFilter(combinedFilter);
                });
            }
            
            if (nraFilter) {
                nraFilter.addEventListener("change", function() {
                    table.setFilter(combinedFilter);
                });
            }

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-ebay-nr-data', {
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
                    })
                    .catch(err => console.error(err));
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
                        var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                        var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                        var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                        var ub7 = 0;
                        if (budget > 0) {
                            ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                        }
                        
                        var sbid = 0;
                        
                        // Global rule: If L7_CPC is 0, set SBID to 0.75 regardless of utilization type
                        if (l7_cpc === 0) {
                            sbid = 0.75;
                        } else if (ub7 < 70 && ub1 < 70) {
                            // Rule: If both UB7 and UB1 are below 70%, set SBID as L7_CPC + 0.10
                            sbid = parseFloat((l7_cpc + 0.10).toFixed(2));
                        } else if (ub7 > 90 && ub1 > 90) {
                            // Rule: If both UB7 and UB1 are above 90%, set SBID as L1_CPC * 0.90
                            if (l1_cpc > 0) {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (l7_cpc > 0) {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            } else {
                                sbid = 0;
                            }
                        } else if (currentUtilizationType === 'total') {
                            // For total campaigns, determine individual campaign's utilization status
                            var rowAcos = parseFloat(rowData.acos) || 0;
                            if (isNaN(rowAcos) || rowAcos === 0) {
                                rowAcos = 100;
                            }
                            
                            // Check DIL color
                            var l30 = parseFloat(rowData.L30 || 0);
                            var inv = parseFloat(rowData.INV || 0);
                            var dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                            var dilColor = getDilColor(dilDecimal);
                            var isPink = (dilColor === "pink");
                            
                            // Determine utilization status
                            var isOverUtilized = false;
                            var isUnderUtilized = false;
                            var ub1 = budget > 0 ? (parseFloat(rowData.l1_spend) || 0) / budget * 100 : 0;
                            
                            // Check over-utilized first
                            if (!isPink) {
                                var condition1 = (rowAcos > totalACOSValue && ub7 > 90);
                                var condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                                if (condition1 || condition2) {
                                    isOverUtilized = true;
                                }
                            }
                            
                            // Check under-utilized
                            if (!isOverUtilized && ub7 < 70 && ub1 < 70 && parseFloat(rowData.price || 0) >= 20 && inv > 0 && !isPink) {
                                isUnderUtilized = true;
                            }
                            
                            // Apply SBID logic based on determined status
                            if (isOverUtilized) {
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0;
                                }
                            } else if (isUnderUtilized) {
                                if (ub7 >= 10 && ub7 <= 50) {
                                    sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                } else {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                }
                            } else {
                                // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                                if (l1_cpc > 0) {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (l7_cpc > 0) {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                } else {
                                    sbid = 0;
                                }
                            }
                        } else if (currentUtilizationType === 'over') {
                            if (l1_cpc > 0) {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (l7_cpc > 0) {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            } else {
                                sbid = 0;
                            }
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 >= 10 && ub7 <= 50) {
                                sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                            } else {
                                sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                            }
                        } else {
                            // Correctly-utilized: use L1_CPC * 0.90, fallback to L7_CPC if L1_CPC is 0
                            if (l1_cpc > 0) {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (l7_cpc > 0) {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            } else {
                                sbid = 0;
                            }
                        }

                        campaignIds.push(rowData.campaign_id);
                        bids.push(sbid);
                    }
                });

                fetch('/update-ebay-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bids: bids
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
                    alert("Error updating bids");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-ebay-keywords-bid-price', {
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
    </script>
@endsection
