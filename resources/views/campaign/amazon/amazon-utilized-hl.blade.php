@extends('layouts.vertical', ['title' => 'Amazon HL - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        /* Professional Card Styling */
        .card.shadow-sm {
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card.shadow-sm:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
            transform: translateY(-2px);
        }

        /* Enhanced Form Controls */
        .form-select,
        .form-control {
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-select:hover,
        .form-control:hover {
            border-color: #cbd5e1;
        }

        .input-group-text {
            border-color: #e2e8f0;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        /* Professional Labels */
        .form-label {
            color: #475569;
            font-size: 0.8125rem;
            letter-spacing: 0.01em;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: #64748b;
        }

        /* Enhanced Border */
        .border-bottom {
            border-color: #e2e8f0 !important;
        }

        /* Better Spacing */
        .card-body.p-3 {
            padding: 1.25rem !important;
        }

        .card-body.p-4 {
            padding: 1.5rem !important;
        }

        /* Professional Button */
        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            border: none;
            font-weight: 500;
            letter-spacing: 0.01em;
            transition: all 0.2s ease;
        }

        .btn-info:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        /* Table Container Enhancement */
        #budget-under-table {
            margin-top: 1.5rem;
        }

        /* Professional Section Spacing */
        .mb-4 {
            margin-bottom: 2rem !important;
        }

        /* Enhanced Card Body */
        .card-body.py-3 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        body {
            zoom: 90%;
        }

        /* Badge Count Item Hover Effects */
        .badge-count-item {
            transition: all 0.2s ease;
        }

        .badge-count-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon HL - Utilized',
        'sub_title' => 'Amazon HL - Utilized',
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
                                    <div class="col-md-2"></div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 justify-content-end align-items-center flex-wrap">
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total
                                                    Parent</span>
                                                <span class="fw-bold" id="total-sku-count"
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
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA MISSING</span>
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
                                                <span class="fw-bold" id="seven-ub-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub-1ub"
                                                style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB +
                                                    1UB</span>
                                                <span class="fw-bold" id="seven-ub-one-ub-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row align-items-end g-2">
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
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-toggle-on me-1" style="color: #64748b;"></i>Status
                                        </label>
                                        <select id="status-filter" class="form-select form-select-md">
                                            <option value="">All Status</option>
                                            <option value="ENABLED">Enabled</option>
                                            <option value="PAUSED">Paused</option>
                                            <option value="ENDED">Ended</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-md">
                                            <option value="" selected>All Inventory</option>
                                            <option value="ALL">ALL</option>
                                            <option value="INV_0">0 INV</option>
                                            <option value="OTHERS">OTHERS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
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
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-star me-1" style="color: #64748b;"></i>Rating
                                        </label>
                                        <select id="rating-filter" class="form-select form-select-md">
                                            <option value="">All Ratings</option>
                                            <option value="lt3">&lt; 3</option>
                                            <option value="3-3.5">3 - 3.5</option>
                                            <option value="4-4.5">4 - 4.5</option>
                                            <option value="gte4.5">≥ 4.5</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-bullseye me-1" style="color: #64748b;"></i>ACOS Filter
                                        </label>
                                        <select id="sbgt-filter" class="form-select form-select-md">
                                            <option value="">All ACOS</option>
                                            <option value="8">ACOS &lt; 5%</option>
                                            <option value="7">ACOS 5-9%</option>
                                            <option value="6">ACOS 10-14%</option>
                                            <option value="5">ACOS 15-19%</option>
                                            <option value="4">ACOS 20-24%</option>
                                            <option value="3">ACOS 25-29%</option>
                                            <option value="2">ACOS 30-34%</option>
                                            <option value="1">ACOS ≥ 35%</option>
                                            <option value="acos35spend10">ACOS>35% and SPEND >10</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <!-- Empty space for alignment -->
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
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing campaigns only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;
            let totalSkuCountFromBackend = 0; // Store total parent SKU count from backend

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts - shows all counts by default
            // Counts are based on filtered data (respects INV, NRA, status, search filters)
            // but shows all utilization types (not filtered by utilization type button)
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
                    // Get all data and apply filters (except utilization type filter)
                    const allData = table.getData('all');

                    // Count for each type (mutually exclusive like controller)
                    let overCount = 0;
                    let underCount = 0;
                    let correctlyCount = 0;
                    let missingCount = 0;
                    let nraMissingCount = 0; // Count NRA missing campaigns
                    let zeroInvCount = 0; // Count zero and negative inventory
                    let totalCampaignCount = 0; // Count total campaigns
                    let nraCount = 0; // Count NRA
                    let raCount = 0; // Count RA
                    let validParentSkuCount = 0; // Count only parent SKUs

                    // Track processed SKUs to avoid counting duplicates
                    const processedSkusForNra = new Set(); // Track parent SKUs for NRA/RA counting
                    const processedSkusForCampaign = new Set(); // Track parent SKUs for campaign counting
                    const processedSkusForMissing = new Set(); // Track parent SKUs for missing counting
                    const processedSkusForNraMissing = new Set(); // Track parent SKUs for NRA missing counting
                    const processedSkusForZeroInv = new Set(); // Track parent SKUs for zero INV counting
                    const processedSkusForValidCount = new Set(); // Track parent SKUs for valid count

                    // First pass: Collect all parent SKUs and determine if they have campaigns
                    const skuCampaignMap = new Map(); // Map to track if SKU has campaign (from any row)
                    const skuZeroInvMap = new Map(); // Map to track if SKU has zero/negative INV
                    const allParentSkus = new Set(); // Track all unique parent SKUs in data

                    allData.forEach(function(row) {
                        const sku = row.sku || '';
                        const isParentSku = sku && sku.toUpperCase().includes('PARENT');

                        if (!isParentSku) {
                            return; // Skip non-parent SKUs
                        }

                        // Track all parent SKUs
                        allParentSkus.add(sku);

                        // Check if this SKU has a campaign (from any row)
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row
                            .campaign_id && row.campaignName);
                        if (!skuCampaignMap.has(sku)) {
                            skuCampaignMap.set(sku, false);
                        }
                        if (hasCampaign) {
                            skuCampaignMap.set(sku, true);
                        }

                        // Check if this SKU has zero/negative inventory (check all rows for this SKU)
                        let inv = parseFloat(row.INV || 0);
                        if (inv <= 0) {
                            skuZeroInvMap.set(sku, true);
                        } else if (!skuZeroInvMap.has(sku)) {
                            // Only set to false if not already set to true
                            skuZeroInvMap.set(sku, false);
                        }
                    });

                    // Debug: Check inventory status of all parent SKUs
                    let parentWithPositiveInv = 0;
                    let parentWithZeroInv = 0;
                    allParentSkus.forEach(function(parentSku) {
                        const row = allData.find(r => r.sku === parentSku);
                        if (row) {
                            let inv = parseFloat(row.INV || 0);
                            if (inv > 0) {
                                parentWithPositiveInv++;
                            } else {
                                parentWithZeroInv++;
                            }
                        }
                    });
                    console.log('HL Debug - Total Parent SKUs:', allParentSkus.size);
                    console.log('HL Debug - Parent SKUs with INV > 0:', parentWithPositiveInv);
                    console.log('HL Debug - Parent SKUs with INV <= 0:', parentWithZeroInv);
                    console.log('HL Debug - Backend Total Count:', totalSkuCountFromBackend);

                    // Second pass: Count based on filtered data
                    allData.forEach(function(row) {
                        // Only count parent SKUs (SKU contains "PARENT")
                        const sku = row.sku || '';
                        const isParentSku = sku && sku.toUpperCase().includes('PARENT');

                        if (!isParentSku) {
                            return; // Skip non-parent SKUs
                        }

                        // Apply all filters except utilization type filter
                        // Global search filter
                        let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                        if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !
                            (row.sku?.toLowerCase().includes(searchVal))) {
                            return;
                        }

                        // Status filter
                        let statusVal = $("#status-filter").val();
                        if (statusVal) {
                            // Normalize status values for comparison
                            let rowStatus = (row.campaignStatus || '').toUpperCase().trim();
                            let filterStatus = statusVal.toUpperCase().trim();

                            // Handle ENABLED and RUNNING as equivalent (for backward compatibility)
                            if (filterStatus === 'ENABLED') {
                                // For ENABLED filter, accept ENABLED, RUNNING, or empty/null (treat empty as enabled if campaign exists)
                                const hasCampaign = row.hasCampaign !== undefined ? row
                                    .hasCampaign : (row.campaign_id && row.campaignName);
                                if (hasCampaign && (rowStatus === '' || rowStatus === 'ENABLED' ||
                                        rowStatus === 'RUNNING')) {
                                    // Allow empty status if campaign exists (default to enabled)
                                } else if (rowStatus !== 'ENABLED' && rowStatus !== 'RUNNING') {
                                    return;
                                }
                            } else if (rowStatus !== filterStatus) {
                                return;
                            }
                        }
                        
                        // Count zero INV AFTER search and status filters for parent SKUs
                        if (skuZeroInvMap.get(sku) && !processedSkusForZeroInv.has(sku)) {
                            processedSkusForZeroInv.add(sku);
                            zeroInvCount++;
                        }

                        // Inventory filter (HL special: default shows all because parent SKUs typically have INV=0)
                        let invFilterVal = $("#inv-filter").val();
                        let inv = parseFloat(row.INV || 0);
                        if (!invFilterVal || invFilterVal === '') {
                            // Default: show all (no filtering) - HL parent SKUs typically have zero inventory
                            // This is different from KW/PT which exclude INV <= 0 by default
                        } else if (invFilterVal === "ALL") {
                            // ALL option shows everything
                        } else if (invFilterVal === "INV_0") {
                            // Show only INV = 0
                            if (inv !== 0) return;
                        } else if (invFilterVal === "OTHERS") {
                            // Show only INV > 0
                            if (inv <= 0) return;
                        }

                        // NRA filter (apply before counting to ensure we only count filtered rows)
                        let nraFilterVal = $("#nra-filter").val();
                        if (nraFilterVal) {
                            let rowNra = row.NRA ? row.NRA.trim() : "";
                            if (nraFilterVal === 'RA') {
                                // For "RA" filter, include empty/null values too
                                if (rowNra === 'NRA') return;
                            } else {
                                // For "NRA" or "LATER", exact match
                                if (rowNra !== nraFilterVal) return;
                            }
                        }

                        // Check if campaign is missing or exists - check this specific row (filtered)
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row
                            .campaign_id && row.campaignName);

                        // Count campaign and missing based on filtered data (after all filters applied)
                        if (hasCampaign) {
                            // Count campaign only once per parent SKU (for filtered data)
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                totalCampaignCount++;
                            }
                        } else {
                            // Count missing only once per parent SKU (for filtered data)
                            if (!processedSkusForMissing.has(sku)) {
                                processedSkusForMissing.add(sku);
                                // Check if this is a red dot (missing AND not yellow)
                                let rowNrlForMissing = row.NRL ? row.NRL.trim() : "";
                                let rowNraForMissing = row.NRA ? row.NRA.trim() : "";
                                // Only count as missing (red dot) if neither NRL='NRL' nor NRA='NRA'
                                if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') {
                                    missingCount++;
                                }
                            }
                            // Count NRA missing separately (yellow dots)
                            if (!processedSkusForNraMissing.has(sku)) {
                                processedSkusForNraMissing.add(sku);
                                let rowNrlForNraMissing = row.NRL ? row.NRL.trim() : "";
                                let rowNraForNraMissing = row.NRA ? row.NRA.trim() : "";
                                if (rowNrlForNraMissing === 'NRL' || rowNraForNraMissing === 'NRA') {
                                    nraMissingCount++;
                                }
                            }
                        }

                        // Count NRA and RA only for parent SKUs and only once per SKU (after all filters)
                        if (!processedSkusForNra.has(sku)) {
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

                        // Count valid parent SKUs that pass all filters - only once per SKU
                        if (!processedSkusForValidCount.has(sku)) {
                            processedSkusForValidCount.add(sku);
                            validParentSkuCount++;
                        }

                        // Now calculate utilization and count
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        // Mutually exclusive categorization (same as controller)
                        let categorized = false;

                        // Over-utilized check (priority 1): ub7 > 99 && ub1 > 99
                        if (!categorized && ub7 > 99 && ub1 > 99) {
                            overCount++;
                            categorized = true;
                        }

                        // Under-utilized check (priority 2: only if not over-utilized): ub7 < 66 && ub1 < 66, campaign must exist, and NRA !== 'NRA'
                        // Note: hasCampaign is already declared above (line 747)
                        let rowNraForUnder = row.NRA ? row.NRA.trim() : "";
                        if (!categorized && ub7 < 66 && ub1 < 66 && hasCampaign && rowNraForUnder !== 'NRA') {
                            underCount++;
                            categorized = true;
                        }

                        // Correctly-utilized check (priority 3: only if not already categorized): ub7 >= 66 && ub7 <= 99
                        if (!categorized && ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                            correctlyCount++;
                        }
                    });
                    
                    // Count ACOS ranges (SBGT mapping)
                    let acosCount8 = 0, acosCount7 = 0, acosCount6 = 0, acosCount5 = 0;
                    let acosCount4 = 0, acosCount3 = 0, acosCount2 = 0, acosCount1 = 0;
                    let acosCountZero = 0;
                    let acos35Spend10Count = 0; // Count ACOS > 35% AND SPEND > 10
                    
                    // Iterate over filtered parent SKUs only (those that passed all filters in utilization counting)
                    processedSkusForValidCount.forEach(function(parentSku) {
                        const row = allData.find(r => r.sku === parentSku);
                        if (!row) return;
                        
                        let acosVal = parseFloat(row.acos || 0);
                        
                        // Handle ACOS = 0 or invalid separately
                        if (acosVal === 0 || isNaN(acosVal)) {
                            acosCountZero++;
                            return;
                        }
                        
                        // Count ACOS > 35% AND SPEND > 10
                        let spendVal = parseFloat(row.l30_spend || 0);
                        if (acosVal >= 35 && spendVal > 10) {
                            acos35Spend10Count++;
                        }
                        
                        if (acosVal < 5) acosCount8++;
                        else if (acosVal < 10) acosCount7++;
                        else if (acosVal < 15) acosCount6++;
                        else if (acosVal < 20) acosCount5++;
                        else if (acosVal < 25) acosCount4++;
                        else if (acosVal < 30) acosCount3++;
                        else if (acosVal < 35) acosCount2++;
                        else acosCount1++;
                    });

                    // Update missing campaign count - based on filtered data
                    const missingCountEl = document.getElementById('missing-campaign-count');
                    if (missingCountEl) {
                        missingCountEl.textContent = missingCount;
                    }

                    // Update NRA missing count
                    const nraMissingCountEl = document.getElementById('nra-missing-count');
                    if (nraMissingCountEl) {
                        nraMissingCountEl.textContent = nraMissingCount;
                    }

                    // Update total campaign count - based on filtered data
                    const totalCampaignCountEl = document.getElementById('total-campaign-count');
                    if (totalCampaignCountEl) {
                        totalCampaignCountEl.textContent = totalCampaignCount;
                    }

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

                    // Update zero INV count - already counted from all parent SKUs
                    const zeroInvCountEl = document.getElementById('zero-inv-count');
                    if (zeroInvCountEl) {
                        zeroInvCountEl.textContent = zeroInvCount;
                    }

                    // Update total parent SKU count - use actual data count (allParentSkus.size)
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    if (totalSkuCountEl) {
                        // Use actual parent SKUs in data (allParentSkus.size) rather than backend count
                        // Backend count may include SKUs that don't appear in the tabulator data
                        totalSkuCountEl.textContent = allParentSkus.size;
                    }

                    // Note: 7UB and 7UB+1UB counts are loaded from backend via loadUtilizationCounts()
                    // Don't update them here as they come from separate API endpoint

                    // Update dropdown option texts with counts
                    // Use validParentSkuCount which respects inventory filter (default excludes INV <= 0)
                    const utilizationSelect = document.getElementById('utilization-type-select');
                    if (utilizationSelect) {
                        utilizationSelect.options[0].text = `All (${validParentSkuCount})`;
                        utilizationSelect.options[1].text = `Over Utilized (${overCount})`;
                        utilizationSelect.options[2].text = `Under Utilized (${underCount})`;
                        utilizationSelect.options[3].text = `Correctly Utilized (${correctlyCount})`;
                    }
                    
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
                    
                    // Count ratings
                    let ratingLt3 = 0, rating3_35 = 0, rating4_45 = 0, ratingGte45 = 0;
                    
                    allData.forEach(function(row) {
                        const sku = row.sku || '';
                        const isValidSku = sku && !sku.toUpperCase().includes('PARENT');
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
                        
                        let statusVal = $("#status-filter").val();
                        if (statusVal && row.campaignStatus !== statusVal) return;
                        
                        let invFilterVal = $("#inv-filter").val();
                        if (!invFilterVal || invFilterVal === '') {
                            if (inv <= 0) return;
                        } else if (invFilterVal === "INV_0") {
                            if (inv !== 0) return;
                        } else if (invFilterVal === "OTHERS") {
                            if (inv <= 0) return;
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
                }, 150);
            }

            // Total campaign card click handler
            const totalCampaignCard = document.getElementById('total-campaign-card');
            if (totalCampaignCard) {
                totalCampaignCard.addEventListener('click', function() {
                    showCampaignOnly = !showCampaignOnly;
                    if (showCampaignOnly) {
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
            }

            // Missing campaign card click handler
            const missingCampaignCard = document.getElementById('missing-campaign-card');
            if (missingCampaignCard) {
                missingCampaignCard.addEventListener('click', function() {
                    showMissingOnly = !showMissingOnly;
                    if (showMissingOnly) {
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
            const nraMissingCampaignCard = document.getElementById('nra-missing-card');
            if (nraMissingCampaignCard) {
                nraMissingCampaignCard.addEventListener('click', function() {
                    showNraMissingOnly = !showNraMissingOnly;
                    if (showNraMissingOnly) {
                        // Reset campaign filter
                        showCampaignOnly = false;
                        document.getElementById('total-campaign-card').style.boxShadow = '';
                        // Reset missing filter
                        showMissingOnly = false;
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
            }

            // Zero INV card click handler
            const zeroInvCard = document.getElementById('zero-inv-card');
            if (zeroInvCard) {
                zeroInvCard.addEventListener('click', function() {
                    showZeroInvOnly = !showZeroInvOnly;
                    if (showZeroInvOnly) {
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
            }

            // NRA card click handler
            const nraCard = document.getElementById('nra-card');
            if (nraCard) {
                nraCard.addEventListener('click', function() {
                    showNraOnly = !showNraOnly;
                    if (showNraOnly) {
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
            }

            // RA card click handler
            const raCard = document.getElementById('ra-card');
            if (raCard) {
                raCard.addEventListener('click', function() {
                    showRaOnly = !showRaOnly;
                    if (showRaOnly) {
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
            }

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/utilized/hl/ads/data",
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
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            // Check if campaign exists: hasCampaign field or if campaign_id/campaignName exists
                            const hasCampaign = row.hasCampaign !== undefined ?
                                row.hasCampaign :
                                (row.campaign_id && row.campaignName);
                            
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
                        visible: false,
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

                            // Rule: If ACOS > 20%, budget = $1
                            if (acos > 20) {
                                sbgt = 1;
                            } else {
                                sbgt = Math.ceil(price * 0.10);
                                if (sbgt < 1) sbgt = 1;
                            }

                            return sbgt; // ✅ sets row.sbgt
                        }

                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acosRaw = row.acos;
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            var clicks = parseInt(row.l30_clicks || 0).toLocaleString();
                            var spend = parseFloat(row.l30_spend || 0).toFixed(0);
                            var adSold = parseInt(row.l30_purchases || 0).toLocaleString();
                            var tooltipText = "Clicks: " + clicks + "\nSpend: " + spend + "\nAd Sold: " + adSold;
                            
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
                        }
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
                    {
                        title: "AD CVR",
                        field: "ad_cvr",
                        hozAlign: "right",
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
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');

                            // Color logic based on UB7 only (Amazon rules)
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
                        }
                    },
                    {
                        title: "AVG CPC",
                        field: "avg_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var avg_cpc = parseFloat(row.avg_cpc) || 0;
                            return avg_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "Last SBID",
                        field: "last_sbid",
                        hozAlign: "center",
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
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            // Get row data
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            
                            // Calculate SBID for row A
                            var aL1Cpc = parseFloat(aData.l1_cpc) || 0;
                            var aL7Cpc = parseFloat(aData.l7_cpc) || 0;
                            var aBudget = parseFloat(aData.campaignBudgetAmount) || 0;
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
                            
                            // Special case: If UB7 and UB1 = 0%, use default value
                            if (aUb7 === 0 && aUb1 === 0) {
                                aSbid = 1.00;
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
                                    aSbid = parseFloat((aL1Cpc * 1.10).toFixed(2));
                                } else if (aL7Cpc > 0) {
                                    aSbid = parseFloat((aL7Cpc * 1.10).toFixed(2));
                                } else if (aAvgCpc > 0) {
                                    aSbid = parseFloat((aAvgCpc * 1.10).toFixed(2));
                                } else {
                                    aSbid = 1.00;
                                }
                            }
                            
                            // Calculate SBID for row B
                            var bL1Cpc = parseFloat(bData.l1_cpc) || 0;
                            var bL7Cpc = parseFloat(bData.l7_cpc) || 0;
                            var bBudget = parseFloat(bData.campaignBudgetAmount) || 0;
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
                            
                            // Special case: If UB7 and UB1 = 0%, use default value
                            if (bUb7 === 0 && bUb1 === 0) {
                                bSbid = 1.00;
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
                                    bSbid = parseFloat((bL1Cpc * 1.10).toFixed(2));
                                } else if (bL7Cpc > 0) {
                                    bSbid = parseFloat((bL7Cpc * 1.10).toFixed(2));
                                } else if (bAvgCpc > 0) {
                                    bSbid = parseFloat((bAvgCpc * 1.10).toFixed(2));
                                } else {
                                    bSbid = 1.00;
                                }
                            }
                            
                            return aSbid - bSbid;
                        },
                        visible: true,
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }

                            var sbid = 0;
                            var ub1 = 0;
                            if (budget > 0) {
                                ub1 = (parseFloat(row.l1_spend) || 0) / budget * 100;
                            }
                            
                            // Determine utilization type for this row
                            var rowUtilizationType = 'all';
                            if (ub7 > 99 && ub1 > 99) {
                                rowUtilizationType = 'over';
                            } else if (ub7 < 66 && ub1 < 66) {
                                rowUtilizationType = 'under';
                            } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                                rowUtilizationType = 'correctly';
                            }

                            // Special case: If UB7 and UB1 = 0%, use default value
                            if (ub7 === 0 && ub1 === 0) {
                                sbid = 1.00;
                            } else if (rowUtilizationType === 'over') {
                                // Over-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
                                var l1_cpc = parseFloat(row.l1_cpc) || 0;
                                var avg_cpc = parseFloat(row.avg_cpc) || 0;
                                if (l1_cpc > 0) {
                                    sbid = (Math.floor(l1_cpc * 0.90 * 100) / 100).toFixed(2);
                                } else if (l7_cpc > 0) {
                                    sbid = (Math.floor(l7_cpc * 0.90 * 100) / 100).toFixed(2);
                                } else if (avg_cpc > 0) {
                                    sbid = (Math.floor(avg_cpc * 0.90 * 100) / 100).toFixed(2);
                                } else {
                                    sbid = 1.00;
                                }
                            } else if (rowUtilizationType === 'under') {
                                // Under-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00
                                var l1_cpc = parseFloat(row.l1_cpc) || 0;
                                var avg_cpc = parseFloat(row.avg_cpc) || 0;
                                if (l1_cpc > 0) {
                                    sbid = (l1_cpc * 1.10).toFixed(2);
                                } else if (l7_cpc > 0) {
                                    sbid = (l7_cpc * 1.10).toFixed(2);
                                } else if (avg_cpc > 0) {
                                    sbid = (avg_cpc * 1.10).toFixed(2);
                                } else {
                                    sbid = 1.00;
                                }
                            } else {
                                // Correctly-utilized or all: no SBID change needed
                                sbid = 0;
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
                                    campaign_type: 'HL',
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
                        formatter: function(cell) {
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

                            if (value === 'RUNNING' || value === 'ENABLED') {
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
                        field: "campaignName"
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
                                var avg_cpc = parseFloat(rowData.avg_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                }
                                var ub1 = 0;
                                if (budget > 0) {
                                    ub1 = (parseFloat(rowData.l1_spend) || 0) / budget * 100;
                                }

                                // Determine utilization type for this row
                                var rowUtilizationType = 'all';
                                if (ub7 > 99 && ub1 > 99) {
                                    rowUtilizationType = 'over';
                                } else if (ub7 < 66 && ub1 < 66) {
                                    rowUtilizationType = 'under';
                                } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                                    rowUtilizationType = 'correctly';
                                }

                                var sbid = 0;
                                // Special case: If UB7 and UB1 = 0%, use default value
                                if (ub7 === 0 && ub1 === 0) {
                                    sbid = 1.00;
                                } else if (rowUtilizationType === 'over') {
                                    // Over-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then decrease by 10%
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
                                    // Under-utilized: Priority - L1 CPC → L7 CPC → AVG CPC → 1.00, then increase by 10%
                                    if (l1_cpc > 0) {
                                        sbid = parseFloat((l1_cpc * 1.10).toFixed(2));
                                    } else if (l7_cpc > 0) {
                                        sbid = parseFloat((l7_cpc * 1.10).toFixed(2));
                                    } else if (avg_cpc > 0) {
                                        sbid = parseFloat((avg_cpc * 1.10).toFixed(2));
                                    } else {
                                        sbid = 1.00;
                                    }
                                } else {
                                    // Correctly-utilized or all: no SBID change needed
                                    sbid = 0;
                                }

                                if (sbid !== 0) {
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
                    totalSkuCountFromBackend = parseFloat(response.total_sku_count) || 0;
                    // Update total count immediately when backend data is received
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    if (totalSkuCountEl && totalSkuCountFromBackend > 0) {
                        totalSkuCountEl.textContent = totalSkuCountFromBackend;
                    }
                    // Force update counts after data loads to use backend count
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 500);
                    // Update pagination count after data is loaded
                    setTimeout(function() {
                        if (typeof updatePaginationCount === 'function') {
                            updatePaginationCount();
                        }
                    }, 200);
                    return response.data;
                }
            });

            // Utilization type dropdown handler
            const utilizationTypeSelect = document.getElementById('utilization-type-select');
            if (utilizationTypeSelect) {
                utilizationTypeSelect.addEventListener('change', function() {
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
                        table.setFilter(combinedFilter);
                        // Redraw cells to update formatter colors and column visibility based on new type
                        table.redraw(true);
                        // Update all button counts after filter is applied
                        setTimeout(function() {
                            updateButtonCounts();
                        }, 200);
                    }
                });
            }

            // Combined filter function
            function combinedFilter(data) {
                // Only show parent SKUs (SKU contains "PARENT")
                const sku = data.sku || '';
                const isParentSku = sku && sku.toUpperCase().includes('PARENT');
                if (!isParentSku) {
                    return false; // Hide non-parent SKUs
                }

                let acos = parseFloat(data.acos || 0);
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                let rowAcos = parseFloat(acos) || 0;

                // Check if campaign is missing
                const hasCampaign = data.hasCampaign !== undefined ?
                    data.hasCampaign :
                    (data.campaign_id && data.campaignName);

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

                // Apply utilization type filter (Amazon rules)
                if (currentUtilizationType === 'all') {
                    // Show all data (no filter on utilization)
                } else {
                    // When utilization type is selected (over/under/correctly), exclude missing items (red and yellow)
                    const hasCampaign = data.hasCampaign !== undefined 
                        ? data.hasCampaign 
                        : (data.campaign_id && data.campaignName);
                    if (!hasCampaign) return false;
                    
                    // Exclude yellow dots (NRL='NRL' OR NRA='NRA') when utilization type is selected
                    const nrlValueForUtil = data.NRL ? data.NRL.trim() : "";
                    const nraValueForUtil = data.NRA ? data.NRA.trim() : "";
                    if (nrlValueForUtil === 'NRL' || nraValueForUtil === 'NRA') return false;
                    
                    // Exclude paused campaigns when utilization type is selected
                    const campaignStatus = data.campaignStatus || 'PAUSED';
                    if (campaignStatus !== 'ENABLED') return false;
                    
                    if (currentUtilizationType === 'over') {
                        // Over-utilized: ub7 > 99 && ub1 > 99
                        if (!(ub7 > 99 && ub1 > 99)) return false;
                    } else if (currentUtilizationType === 'under') {
                        // Under-utilized: ub7 < 66 && ub1 < 66, and NRA !== 'NRA'
                        if (!(ub7 < 66 && ub1 < 66)) return false;
                        const rowNraForUnder = data.NRA ? data.NRA.trim() : "";
                        if (rowNraForUnder === 'NRA') return false;
                    } else if (currentUtilizationType === 'correctly') {
                        // Correctly-utilized: ub7 >= 66 && ub7 <= 99
                        if (!(ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99)) return false;
                    }
                }

                // Global search filter
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                let tableSearchVal = $("#global-search-table").val()?.toLowerCase() || "";
                // Combine both search values
                searchVal = searchVal || tableSearchVal;
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku
                    ?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // Status filter
                let statusVal = $("#status-filter").val();
                if (statusVal) {
                    // Check if campaign exists
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id &&
                        data.campaignName);
                    
                    // Normalize status values for comparison
                    let rowStatus = (data.campaignStatus || '').toUpperCase().trim();
                    let filterStatus = statusVal.toUpperCase().trim();
                    
                    // Handle ENABLED and RUNNING as equivalent (for backward compatibility)
                    if (filterStatus === 'ENABLED') {
                        // When ENABLED is selected, exclude missing items (rows without campaigns)
                        if (!hasCampaign) return false;
                        
                        // For ENABLED filter, accept ENABLED, RUNNING, or empty/null (treat empty as enabled if campaign exists)
                        if (hasCampaign && (rowStatus === '' || rowStatus === 'ENABLED' || rowStatus ===
                            'RUNNING')) {
                            // Allow empty status if campaign exists (default to enabled)
                        } else if (rowStatus !== 'ENABLED' && rowStatus !== 'RUNNING') {
                            return false;
                        }
                    } else if (rowStatus !== filterStatus) {
                        return false;
                    }
                }

                // Apply zero INV filter first (if enabled)
                let inv = parseFloat(data.INV || 0);
                if (showZeroInvOnly) {
                    // Show only zero or negative inventory
                    if (inv > 0) return false;
                } else {
                    // Inventory filter (HL: no default filter, show all by default)
                    let invFilterVal = $("#inv-filter").val();

                    // By default (no filter selected), show all campaigns
                    if (!invFilterVal || invFilterVal === '') {
                        // No filtering - show all
                    } else if (invFilterVal === "ALL") {
                        // ALL option shows everything
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
                let rowNra = data.NRA ? data.NRA.trim() : "";
                if (showNraOnly) {
                    // Show only NRA (explicitly "NRA" only)
                    if (rowNra !== 'NRA') return false;
                } else if (showRaOnly) {
                    // Show only RA (including empty/null which defaults to "RA")
                    if (rowNra === 'NRA') return false;
                } else {
                    // NRA filter
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

                // ACOS filter (sbgt-filter dropdown) based on ACOS ranges
                let acosFilterVal = $("#sbgt-filter").val();
                if (acosFilterVal) {
                    // Special filter for ACOS > 35% AND SPEND > 10
                    if (acosFilterVal === 'acos35spend10') {
                        let spendVal = parseFloat(data.l30_spend || 0);
                        // Show only items where ACOS > 35% AND spend > 10
                        if (rowAcos <= 35 || spendVal <= 10 || isNaN(rowAcos) || isNaN(spendVal)) {
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
                            if (rowAcos >= 0 && rowAcos < 5) match = true;
                        } else if (acosFilterVal === '7') {
                            // ACOS 5-9%
                            if (rowAcos >= 5 && rowAcos < 10) match = true;
                        } else if (acosFilterVal === '6') {
                            // ACOS 10-14%
                            if (rowAcos >= 10 && rowAcos < 15) match = true;
                        } else if (acosFilterVal === '5') {
                            // ACOS 15-19%
                            if (rowAcos >= 15 && rowAcos < 20) match = true;
                        } else if (acosFilterVal === '4') {
                            // ACOS 20-24%
                            if (rowAcos >= 20 && rowAcos < 25) match = true;
                        } else if (acosFilterVal === '3') {
                            // ACOS 25-29%
                            if (rowAcos >= 25 && rowAcos < 30) match = true;
                        } else if (acosFilterVal === '2') {
                            // ACOS 30-34%
                            if (rowAcos >= 30 && rowAcos < 35) match = true;
                        } else if (acosFilterVal === '1') {
                            // ACOS ≥ 35%
                            if (rowAcos >= 35) match = true;
                        }
                        
                        if (!match) return false;
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
                            campaign_type: 'HL'
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

                // Add event listener for info icon click to toggle columns
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('info-icon-toggle')) {
                        e.stopPropagation();
                        var clicksCol = table.getColumn('l30_clicks');
                        var spendCol = table.getColumn('l30_spend');
                        var adSoldCol = table.getColumn('l30_purchases');
                        
                        // Toggle visibility
                        if (clicksCol.isVisible()) {
                            table.hideColumn('l30_clicks');
                            table.hideColumn('l30_spend');
                            table.hideColumn('l30_purchases');
                        } else {
                            table.showColumn('l30_clicks');
                            table.showColumn('l30_spend');
                            table.showColumn('l30_purchases');
                        }
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

                // Debounced search
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
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

                $("#status-filter, #inv-filter, #nra-filter, #sbgt-filter, #rating-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    // Update counts when filter changes - use longer timeout to ensure filter is applied
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 400);
                });

                // INC/DEC SBID variables
                let incDecType = 'value'; // 'value' or 'percentage'
                
                // INC/DEC SBID handlers (same as KW file but with campaign_type: 'HL')
                $("#inc-dec-dropdown .dropdown-item").on("click", function(e) {
                    e.preventDefault();
                    incDecType = $(this).data('type');
                    var labelText = incDecType === 'value' ? 'Value (e.g., +0.5 or -0.5)' : 'Percentage (e.g., +10 or -10)';
                    $("#inc-dec-label").text(incDecType === 'value' ? 'Value' : 'Percentage');
                    $("#inc-dec-input").attr('placeholder', labelText);
                    $("#inc-dec-btn").text(incDecType === 'value' ? 'INC/DEC (By Value)' : 'INC/DEC (By %)');
                });
                
                function getCurrentSbid(rowData) {
                    var lastSbid = rowData.last_sbid;
                    if (!lastSbid || lastSbid === '' || lastSbid === '0' || lastSbid === 0) {
                        return null;
                    }
                    var sbidValue = parseFloat(lastSbid);
                    if (isNaN(sbidValue) || sbidValue <= 0) {
                        return null;
                    }
                    return sbidValue;
                }
                
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
                    var selectedRows = table.getRows('selected');
                    if (selectedRows.length === 0) {
                        showToast('warning', 'Please select at least one row to apply increment/decrement');
                        return;
                    }
                    var campaignSbidMap = {};
                    var rowsToUpdate = [];
                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var campaignId = rowData.campaign_id;
                        if (!campaignId) return;
                        var currentLbid = getCurrentSbid(rowData);
                        if (currentLbid === null || currentLbid === 0) return;
                        var newSbid = 0;
                        if (incDecType === 'value') {
                            newSbid = currentLbid + incDecValue;
                        } else {
                            newSbid = currentLbid * (1 + incDecValue / 100);
                        }
                        if (newSbid < 0) newSbid = 0;
                        newSbid = Math.round(newSbid * 100) / 100;
                        campaignSbidMap[campaignId] = newSbid;
                        rowsToUpdate.push({ row: row, campaignId: campaignId, newSbid: newSbid, campaignType: 'HL' });
                    });
                    if (Object.keys(campaignSbidMap).length === 0) {
                        showToast('warning', 'No selected rows with valid Last SBID and campaign ID found');
                        return;
                    }
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) overlay.style.display = "flex";
                    var savePromises = [];
                    rowsToUpdate.forEach(function(rowInfo) {
                        var savePromise = $.ajax({
                            url: '/save-amazon-sbid-m',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { campaign_id: rowInfo.campaignId, sbid_m: rowInfo.newSbid, campaign_type: 'HL' }
                        }).then(function(response) {
                            return { campaignId: rowInfo.campaignId, response: response, success: true, rowInfo: rowInfo };
                        }).catch(function(error) {
                            return { campaignId: rowInfo.campaignId, error: error, success: false, rowInfo: rowInfo };
                        });
                        savePromises.push(savePromise);
                    });
                    Promise.all(savePromises).then(function(results) {
                        var successCount = 0;
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                var rowInfo = result.rowInfo;
                                var rowData = rowInfo.row.getData();
                                var currentData = JSON.parse(JSON.stringify(rowData));
                                currentData.sbid_m = rowInfo.newSbid;
                                rowInfo.row.update(currentData);
                                setTimeout(function() { rowInfo.row.reformat(); }, 50);
                            }
                        });
                        if (overlay) overlay.style.display = "none";
                        if (successCount > 0) {
                            showToast('success', 'SBID M saved successfully for ' + successCount + ' campaign(s)');
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to save SBID M values');
                        }
                    }).catch(function(error) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Error saving SBID M values');
                    });
                });
                
                $("#clear-inc-dec-btn").on("click", function() {
                    $("#inc-dec-input").val('');
                    showToast('info', 'Input cleared. SBID M values remain saved in database.');
                });
                
                $(document).on("click", "#clear-sbid-m-btn", function(e) {
                    e.preventDefault();
                    var selectedRows = table.getRows('selected');
                    if (selectedRows.length === 0) {
                        showToast('warning', 'Please select at least one row to clear SBID M');
                        return;
                    }
                    if (!confirm('Are you sure you want to clear SBID M for ' + selectedRows.length + ' selected row(s)?')) {
                        return;
                    }
                    selectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var currentData = JSON.parse(JSON.stringify(rowData));
                        currentData.sbid_m = '';
                        row.update(currentData);
                        setTimeout(function() { row.reformat(); }, 50);
                    });
                    showToast('info', 'SBID M cleared in display for ' + selectedRows.length + ' row(s).');
                    table.redraw(true);
                });
                
                document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) overlay.style.display = "flex";
                    var allSelectedRows = table.getRows('selected');
                    var selectedCampaignIds = [];
                    allSelectedRows.forEach(function(row) {
                        var campaignId = row.getData().campaign_id;
                        if (campaignId && !selectedCampaignIds.includes(campaignId)) {
                            selectedCampaignIds.push(campaignId);
                        }
                    });
                    if (selectedCampaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Please select at least one campaign');
                        return;
                    }
                    var rowBidMap = [];
                    allSelectedRows.forEach(function(row) {
                        var rowData = row.getData();
                        var sbidM = parseFloat(rowData.sbid_m) || 0;
                        if (sbidM > 0 && rowData.campaign_id) {
                            rowBidMap.push({ row: row, campaignId: rowData.campaign_id, bid: sbidM });
                        }
                    });
                    if (rowBidMap.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'No valid campaigns with SBID M value found');
                        return;
                    }
                    var approvePromises = rowBidMap.map(function(item) {
                        return $.ajax({
                            url: '/approve-amazon-sbid',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { campaign_id: item.campaignId, sbid_m: item.bid, campaign_type: 'HL' }
                        }).then(function(response) {
                            return { success: true, response: response, item: item };
                        }).catch(function(error) {
                            return { success: false, error: error, item: item };
                        });
                    });
                    Promise.all(approvePromises).then(function(results) {
                        var successCount = 0;
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                var item = result.item;
                                var rowData = item.row.getData();
                                var currentData = JSON.parse(JSON.stringify(rowData));
                                currentData.sbid = item.bid;
                                currentData.sbid_m = item.bid;
                                item.row.update(currentData);
                                setTimeout(function() { item.row.reformat(); }, 50);
                            }
                        });
                        if (overlay) overlay.style.display = "none";
                        if (successCount > 0) {
                            showToast('success', 'SBID approved successfully for ' + successCount + ' campaign(s)');
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to approve SBID values');
                        }
                    });
                });
                
                document.getElementById("save-all-sbid-m-btn").addEventListener("click", function() {
                    const overlay = document.getElementById("progress-overlay");
                    if (overlay) overlay.style.display = "flex";
                    var allSelectedRows = table.getRows('selected');
                    var selectedCampaignIds = [];
                    allSelectedRows.forEach(function(row) {
                        var campaignId = row.getData().campaign_id;
                        if (campaignId && !selectedCampaignIds.includes(campaignId)) {
                            selectedCampaignIds.push(campaignId);
                        }
                    });
                    if (selectedCampaignIds.length === 0) {
                        if (overlay) overlay.style.display = "none";
                        showToast('error', 'Please select at least one campaign');
                        return;
                    }
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
                    var savePromises = selectedCampaignIds.map(function(campaignId) {
                        return $.ajax({
                            url: '/save-amazon-sbid-m',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { campaign_id: campaignId, sbid_m: cleanValue, campaign_type: 'HL' }
                        }).then(function(response) {
                            return { campaignId: campaignId, response: response, success: true };
                        }).catch(function(error) {
                            return { campaignId: campaignId, error: error, success: false };
                        });
                    });
                    Promise.all(savePromises).then(function(results) {
                        var successCount = 0;
                        results.forEach(function(result) {
                            if (result.success && result.response && result.response.status === 200) {
                                successCount++;
                                var rows = table.getRows().filter(function(row) {
                                    return row.getData().campaign_id === result.campaignId;
                                });
                                rows.forEach(function(row) {
                                    var rowData = row.getData();
                                    var currentData = JSON.parse(JSON.stringify(rowData));
                                    currentData.sbid_m = cleanValue;
                                    row.update(currentData);
                                    setTimeout(function() { row.reformat(); }, 50);
                                });
                            }
                        });
                        if (overlay) overlay.style.display = "none";
                        if (successCount > 0) {
                            showToast('success', 'SBID M saved successfully for ' + successCount + ' campaign(s)');
                            table.redraw(true);
                        } else {
                            showToast('error', 'Failed to save SBID M values');
                        }
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
                const aprAllSbidBtn = document.getElementById("apr-all-sbid-btn");
                const saveAllSbidMBtn = document.getElementById("save-all-sbid-m-btn");
                if (aprAllSbidBtn && saveAllSbidMBtn) {
                    if (data.length > 0) {
                        aprAllSbidBtn.classList.remove("d-none");
                        saveAllSbidMBtn.classList.remove("d-none");
                    } else {
                        aprAllSbidBtn.classList.add("d-none");
                        saveAllSbidMBtn.classList.add("d-none");
                    }
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
                    let colsToToggle = ["INV", "L30", "DIL %", "NR", "A_L30", "ADIL %", "NRL", "NRA", "FBA",
                        "FBA_INV"
                    ];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-keywords-bid-price', {
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
        });

        let utilizationChartInstance = null;

        function loadUtilizationCounts() {
            fetch('/amazon/get-utilization-counts?type=HL')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        // 7UB count: sum of all utilization types based on 7UB only
                        const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0) + (data
                            .correctly_utilized_7ub || 0);
                        // 7UB + 1UB count: sum of all utilization types based on both 7UB and 1UB
                        const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0) + (
                            data.correctly_utilized_7ub_1ub || 0);

                        document.getElementById('seven-ub-count').textContent = count7ub || 0;
                        document.getElementById('seven-ub-one-ub-count').textContent = count7ub1ub || 0;
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

            fetch('/amazon/get-utilization-chart-data?type=HL&condition=' + type)
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
