@extends('layouts.vertical', ['title' => 'To Order Analysis'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Column show/hide menu */
        .toa-columns-wrap { position: relative; }

        /* Search input directly above the table header */
        .toa-table-search-wrap {
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-bottom: 0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .toa-table-search-group {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .toa-table-search-group:focus-within {
            border-color: #4db6ac;
            box-shadow: 0 0 0 3px rgba(77, 182, 172, 0.18);
        }
        .toa-table-search-group .toa-table-search-icon {
            background: transparent;
            border: 0;
            color: #94a3b8;
            padding-left: 14px;
            padding-right: 8px;
        }
        .toa-table-search-group .toa-table-search {
            border: 0;
            background: transparent;
            box-shadow: none !important;
            height: 42px;
            font-size: 0.95rem;
            color: #1e293b;
            padding-left: 4px;
        }
        .toa-table-search-group .toa-table-search::placeholder { color: #94a3b8; }
        .toa-table-search-group .toa-table-search:focus { outline: none; border: 0; }
        .toa-table-search-group .toa-table-search-clear {
            background: transparent;
            border: 0;
            color: #cbd5e1;
            padding: 0 14px;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .toa-table-search-group .toa-table-search-clear:hover { color: #64748b; }
        .toa-table-search-group.has-value .toa-table-search-clear { display: inline-flex; }
        .toa-columns-menu {
            position: absolute; z-index: 4000; top: 100%; left: 0; margin-top: 4px;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
            padding: 8px 10px; min-width: 220px; max-height: 360px; overflow: auto;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }
        .toa-columns-menu .toa-columns-head {
            display: flex; justify-content: space-between; align-items: center;
            gap: 8px; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;
        }
        .toa-columns-menu .form-check { margin-bottom: 3px; }
        .toa-columns-menu .form-check-label { cursor: pointer; }

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
        .to-order-filter-row { gap: 1.25rem; padding: 1rem 0; }
        .filter-item .form-label { white-space: nowrap; font-size: 0.95rem !important; margin-bottom: 0.4rem !important; }
        .filter-item .form-select,
        .filter-item .form-control { min-height: 38px; font-size: 0.95rem; padding: 0.4rem 0.65rem; }
        .filter-item .form-select { min-width: 145px; }
        .to-order-counts-box { padding: 0.75rem 1.25rem; min-width: 200px; }
        .to-order-counts-box .count-label { font-size: 0.85rem; letter-spacing: 0.02em; }
        .to-order-counts-box .count-value { font-size: 2rem; line-height: 1.2; font-weight: 700; }

        .toa-ctn-instr-wrap .toa-ctn-instructions-input {
            font-size: 12px;
        }
        .toa-ctn-instr-wrap .toa-copy-instr {
            flex-shrink: 0;
        }

        /* Instructions item PKG: ~100ch line width, wrap like Dim Wt Master */
        .toa-item-pkg-wrap {
            max-width: 100ch;
            margin: 0 auto;
            text-align: left;
        }
        .toa-item-pkg-textarea {
            width: 100%;
            max-width: 100ch;
            min-width: 12ch;
            min-height: 52px;
            font-size: 12px;
            line-height: 1.35;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-wrap: anywhere;
            resize: vertical;
        }

        .toa-packing-design-instr {
            max-width: 36ch;
            margin: 0 auto;
            text-align: left;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-wrap: anywhere;
            font-size: 12px;
            line-height: 1.35;
            max-height: 88px;
            overflow: auto;
        }

        /* NRP: REQ = green, 2BDC (NR) = red, LATER = yellow — medium dot + invisible select */
        .nrp-dot-cell {
            min-height: 36px;
            min-width: 44px;
        }
        .nrp-dot-cell .nrp-status-dot,
        .toa-data-dot-wrap .toa-status-dot,
        .toa-data-dot-btn .toa-status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
            display: inline-block;
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        }
        .nrp-dot-cell .nrp-status-dot {
            /* inherits shared dot rules above */
        }
        .nrp-dot-cell .nrp-nr-select {
            opacity: 0;
            cursor: pointer;
            margin: 0 !important;
            border: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            -webkit-appearance: none;
            appearance: none;
        }

        .toa-data-dot-wrap {
            min-height: 36px;
            min-width: 44px;
        }
        .toa-data-dot-btn {
            line-height: 1;
            text-decoration: none !important;
        }

        .toa-moq-btn {
            min-width: 44px;
            min-height: 28px;
            line-height: 1.2;
            text-decoration: none !important;
            cursor: pointer;
        }
        .toa-moq-btn:hover {
            text-decoration: underline !important;
        }

        /* Column header tooltips (Tabulator) — up to 2× header size, bold */
        .tabulator-tooltip {
            font-size: 2.16rem !important;
            font-weight: 700 !important;
            line-height: 1.35 !important;
            padding: 12px 18px !important;
            border-radius: 8px !important;
            box-shadow: 0 6px 22px rgba(15, 23, 42, 0.22) !important;
            max-width: min(92vw, 560px);
            white-space: normal;
            text-align: center;
        }

        /* Status-dot hover badges — up to 2× cell text, bold */
        .toa-data-dot-btn,
        .nrp-dot-cell {
            position: relative;
        }
        .purchase-hover-tip-badge {
            display: none;
            position: absolute;
            z-index: 2500;
            left: 50%;
            bottom: calc(100% + 8px);
            transform: translateX(-50%);
            background: #1e293b;
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.35;
            padding: 10px 16px;
            border-radius: 8px;
            white-space: nowrap;
            max-width: min(92vw, 420px);
            overflow: hidden;
            text-overflow: ellipsis;
            pointer-events: none;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.28);
        }
        .purchase-hover-tip-badge--wrap {
            white-space: normal;
            text-align: center;
            overflow: visible;
            text-overflow: unset;
        }
        .toa-data-dot-btn:hover .purchase-hover-tip-badge,
        .nrp-dot-cell:hover .purchase-hover-tip-badge {
            display: block;
        }

        @media (max-width: 768px) {
            .tabulator-tooltip {
                font-size: 1.65rem !important;
                padding: 10px 14px !important;
            }
            .purchase-hover-tip-badge {
                font-size: 1.5rem;
                padding: 8px 12px;
            }
        }
        .supplier-approval-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            padding: 0;
            border: 2px solid rgba(0, 0, 0, 0.12);
            display: inline-block;
            vertical-align: middle;
            cursor: pointer;
        }
        #addSupplierModal .modal-content,
        #addSupplierModal .modal-body {
            background-color: #fff !important;
        }
        .supplier-approval-dot--red { background-color: #dc3545; }
        .supplier-approval-dot--green { background-color: #198754; }
        .supplier-approval-dot--yellow { background-color: #ffc107; }
        .approval-form-dots label:has(input[type="radio"]:checked) {
            font-weight: 600;
        }
        .approval-form-dots input[type="radio"]:checked + span {
            box-shadow: 0 0 0 2px #495057;
            border-radius: 50%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'To Order Analysis', 'sub_title' => 'To Order Analysis'])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">To Order Analysis</h4>
                    </div>

                    {{-- Single row: all filters left, counts right --}}
                    <div class="d-flex flex-wrap align-items-end to-order-filter-row mb-4">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'to_order'])
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">▶️ Navigation</label>
                            <div class="btn-group" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" style="width: 38px; height: 38px;" title="Previous parent"><i class="fas fa-step-backward"></i></button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="width: 38px; height: 38px; display: none;" title="Pause"><i class="fas fa-pause"></i></button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" style="width: 38px; height: 38px;" title="Play"><i class="fas fa-play"></i></button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm" style="width: 38px; height: 38px;" title="Next parent"><i class="fas fa-step-forward"></i></button>
                            </div>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">👤 Executive</label>
                            <select id="executive-filter" class="form-select border border-primary" title="Filter by assigned executive">
                                <option value="" selected>All Executives</option>
                                <option value="__unassigned__">— Unassigned —</option>
                                <option value="Atin">Atin</option>
                                <option value="Jack">Jack</option>
                                <option value="Nitish">Nitish</option>
                                <option value="Ajay">Ajay</option>
                                <option value="Candy">Candy</option>
                                <option value="Sruti">Sruti</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">Parent / Sku</label>
                            <select id="row-data-type" class="form-select border border-primary">
                                <option value="all">🔁 Show All</option>
                                <option value="sku">🔹 SKU</option>
                                <option value="parent">🔸 Parent</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🔹 SKU</label>
                            <div class="position-relative">
                                <input type="search" id="sku-filter" class="form-select border border-primary"
                                    placeholder="Search SKU…" autocomplete="off" style="padding-right: 26px;">
                                <button type="button" id="sku-filter-clear"
                                    class="btn btn-link p-0 position-absolute top-50 translate-middle-y text-muted"
                                    style="right: 6px; display: none; line-height: 1;"
                                    title="Clear" aria-label="Clear SKU filter">
                                    <i class="mdi mdi-close-circle"></i>
                                </button>
                            </div>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">Pending Status</label>
                            <select id="row-data-pending-status" class="form-select border border-primary">
                                <option value="">Color</option>
                                <option value="green">Green <span id="greenCount"></span></option>
                                <option value="yellow">Yellow <span id="yellowCount"></span></option>
                                <option value="red">Red <span id="redCount"></span></option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🎯 Stage</label>
                            <select id="stage-filter" class="form-select">
                                <option value="" selected>All</option>
                                <option value="appr_req">Appr Req</option>
                                <option value="to_order_analysis">2 Order</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block" title="Minimum order quantity">📦 MOQ</label>
                            <select id="moq-filter" class="form-select border border-primary" title="Filter by approved quantity (MOQ)">
                                <option value="" selected>All (0 &amp; &gt;0)</option>
                                <option value="zero">MOQ = 0</option>
                                <option value="gt0">MOQ &gt; 0</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🔍 NRP</label>
                            <select id="nrp-filter" class="form-select">
                                <option value="all" selected>All</option>
                                <option value="show_nr">2BDC</option>
                                <option value="show_later">LATER</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🏢 Supplier</label>
                            <select id="supplier-filter" class="form-select">
                                <option value="">All Suppliers</option>
                                <option value="__blank__">Blank / No supplier</option>
                                @foreach($allSuppliers ?? [] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item">
                            <label class="form-label fw-semibold d-block">🗂️ Category</label>
                            <select id="category-filter" class="form-select">
                                <option value="">All Categories</option>
                                <option value="__blank__">Blank / No category</option>
                                @foreach($allCategories ?? [] as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item toa-columns-wrap">
                            <label class="form-label fw-semibold d-block">Columns</label>
                            <button type="button" class="btn btn-outline-secondary" id="toa-columns-btn">
                                <i class="fas fa-table-columns me-1"></i> Show / Hide
                            </button>
                            <div id="toa-columns-menu" class="toa-columns-menu" style="display:none;"></div>
                        </div>
                        </div>

                        <div class="ms-auto d-flex align-items-end">
                            <div class="d-flex align-items-center to-order-counts-box gap-4 rounded border bg-light">
                                <div class="text-center">
                                    <div class="text-muted count-label fw-semibold mb-1">🕒 Pending</div>
                                    <div id="pendingItemsCount" class="count-value text-primary">00</div>
                                </div>
                                <div class="vr" style="height: 40px;"></div>
                                <div class="text-center">
                                    <div class="text-muted count-label fw-semibold mb-1">📦 CBM</div>
                                    <div id="totalCBM" class="count-value text-success">00</div>
                                </div>
                            </div>
                            <div class="filter-item d-none" id="totalApprovedQty-wrap">
                                <label class="form-label fw-semibold mb-1 d-block small">Approved Qty</label>
                                <div id="totalApprovedQty" class="fw-bold text-primary small">00</div>
                            </div>
                            <button class="btn btn-sm btn-danger d-none align-self-end" id="delete-selected-btn">
                                <i class="fas fa-trash-alt me-1"></i> Delete
                            </button>
                        </div>
                    </div>
                    <div class="toa-table-search-wrap mb-2">
                        <div class="input-group toa-table-search-group">
                            <span class="input-group-text toa-table-search-icon">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="search-input" class="form-control toa-table-search" placeholder="Search SKU, parent, supplier, category...">
                            <button type="button" class="btn toa-table-search-clear" id="search-input-clear" title="Clear search" tabindex="-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="toOrderAnalysis-table"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form id="reviewForm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="reviewModalLabel">📝 To Order Review</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">🏭 Parent</label>
                                <input type="text" class="form-control" id="review_parent" name="parent"
                                    readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🔢 SKU</label>
                                <input type="text" class="form-control" id="review_sku" name="sku" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">🏢 Supplier</label>
                                <input type="text" class="form-control" id="review_supplier" name="supplier"
                                    readonly>
                            </div>

                            <div class="col-md-12">
                                <label for="positive_review" class="form-label">✨ Positive Review <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="positive_review" name="positive_review" rows="3"
                                    placeholder="Enter positive aspects..." required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="negative_review" class="form-label">⚠️ Negative Review <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="negative_review" name="negative_review" rows="3"
                                    placeholder="Enter areas of concern..." required></textarea>
                            </div>
                            <div class="col-md-12">
                                <label for="improvement" class="form-label">📈 Improvement Required <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control" id="improvement" name="improvement" rows="3"
                                    placeholder="Enter suggested improvements..." required></textarea>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="date_updated" class="form-label">📅 Date Updated <span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_updated" name="date_updated"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label for="clink" class="form-label">🔗 Competitor Link</label>
                                    <div class="input-group">
                                        <a href="#" class="btn btn-outline-primary" id="clink"
                                            target="_blank">
                                            <i class="mdi mdi-eye me-1"></i>
                                            View Competitor Link
                                        </a>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Click to view the competitor link</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">❌ Close</button>
                        <button type="submit" class="btn btn-primary">
                            💾 Save Review
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- MONTH VIEW modal (Jan–Dec, same as forecast analysis) --}}
    <div class="modal fade" id="monthModal" tabindex="-1" aria-labelledby="monthModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="modal-title mb-0">MONTH VIEW <span id="month-view-sku" class="ms-1"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="monthModalBody">
                    <div class="d-flex justify-content-between gap-2 flex-nowrap w-100 px-3" id="monthCardWrapper"
                        style="overflow-x: auto;">
                        <!-- Month cards inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- LMP Competitors Modal (same data source as /amazon-tabulator-view) --}}
    <div class="modal fade" id="toaLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> LMP Competitors for SKU: <span id="toaLmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="toaLmpDataList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MOQ edit modal --}}
    <div class="modal fade" id="toaMoqModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title mb-0">
                        MOQ
                        <span class="ms-1 text-white-50 small" id="toaMoqModalSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="toaMoqModalInput" class="form-label fw-semibold">Approved quantity (MOQ)</label>
                    <input type="number" id="toaMoqModalInput" class="form-control" min="0" max="99999" step="1" placeholder="Enter MOQ">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="toaMoqModalSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Instruction data modal (Item Pkg, Instr Carton, Design fields) --}}
    <div class="modal fade" id="toaDataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title mb-0">
                        <span id="toaDataModalTitle">Instructions</span>
                        <span class="ms-1 text-white-50 small" id="toaDataModalSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="toaDataModalEmpty" class="alert alert-warning d-none mb-0">Data required</div>
                    <div id="toaDataModalFileWrap" class="d-none">
                        <p class="mb-2 text-break fw-semibold" id="toaDataModalFileLabel"></p>
                        <a id="toaDataModalFileLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-download me-1"></i> Download file
                        </a>
                    </div>
                    <textarea id="toaDataModalTextarea" class="form-control d-none" rows="8" maxlength="2000"></textarea>
                    <input type="text" id="toaDataModalInput" class="form-control d-none" maxlength="100">
                    <div id="toaDataModalReadonly" class="border rounded p-3 bg-light d-none" style="white-space:pre-wrap;word-break:break-word;"></div>
                    <p id="toaDataModalSourceNote" class="text-muted small mb-0 mt-2 d-none">Source: QC &amp; Packing (<a href="/customer-care/qc-and-packing" target="_blank" rel="noopener">open page</a>)</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary d-none" id="toaDataModalCopyBtn">
                        <i class="far fa-copy me-1"></i> Copy
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary d-none" id="toaDataModalSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Suppliers-by-category modal: lists all suppliers in the row's category -->
    <div class="modal fade" id="supplierCategoryModal" tabindex="-1" aria-labelledby="supplierCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="supplierCategoryModalLabel">
                        <i class="fas fa-truck me-2"></i> Suppliers <span id="supplierCategoryName" class="ms-2 fw-normal"></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <a href="https://www.alibaba.com" target="_blank" rel="noopener noreferrer"
                            class="d-inline-flex align-items-center justify-content-center bg-white rounded p-1"
                            title="Go to Alibaba.com" style="width:34px;height:34px;">
                            <img src="{{ asset('assets/images/alibaba-icon.png') }}" alt="Alibaba" style="width:24px;height:24px;object-fit:contain;">
                        </a>
                        <button type="button" class="btn btn-light btn-sm fw-semibold" id="catModalAddSupplierBtn">
                            <i class="mdi mdi-plus me-1"></i> Add Supplier
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body" id="supplierCategoryModalBody" style="background-color:#fff;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PKG details modal: shows all 6 packaging/design/CDR/issues fields for a SKU -->
    <div class="modal fade" id="pkgModal" tabindex="-1" aria-labelledby="pkgModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="pkgModalLabel">
                        <i class="fas fa-box-open me-2"></i> Packaging Details <span id="pkgModalSku" class="ms-2 fw-normal"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="pkgModalBody" style="background-color:#fff;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QC / Improvement Required Modal (data from /customer-care/qc-and-packing) -->
    <div class="modal fade" id="qcIssueModal" tabindex="-1" aria-labelledby="qcIssueModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="qcIssueModalLabel">
                        <img src="{{ asset('assets/images/improvement.png') }}" alt="" style="width:24px;height:24px;object-fit:contain;" class="me-2">
                        Improvement Required <span id="qcIssueModalSku" class="ms-2 fw-normal"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="background-color:#fff;">
                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-exclamation-triangle me-1"></i> Issues</h6>
                    <div id="qcIssueModalIssues"></div>
                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-clock-rotate-left me-1"></i> History</h6>
                    <div id="qcIssueModalHistory"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal (duplicated from supplier list page; saves via supplier.create) -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-labelledby="supplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('supplier.create') }}" class="needs-validation" novalidate id="addSupplierForm">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="supplierModalLabel">
                            <i class="mdi mdi-account-plus me-2"></i> Add Supplier
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                @php
                                    $supplierTypes = ['Supplier','Forwarders', 'Photographer'];
                                @endphp
                                <select name="type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    @foreach($supplierTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category_id[]" class="form-select select2" data-placeholder="Select Category" multiple required style="min-height: 42px;">
                                    @foreach($categories ?? [] as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="Supplier Name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company</label>
                                <input type="text" name="company" class="form-control" placeholder="Company Name">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Parents</label>
                                <input type="text" name="parent" class="form-control" placeholder="Use commas to separate multiple Parents (e.g., TV-BOX, CAMERA)">
                                <small class="text-muted">Separate multiple parents with commas</small>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Country Code</label>
                                        <input type="text" name="country_code" class="form-control" placeholder="+86">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">Phone</label>
                                        <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City</label>
                                <input type="text" name="city" class="form-control" placeholder="City">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Zone</label>
                                <select name="zone" class="form-select">
                                    <option value="">Select Zone</option>
                                    <option value="GHZ">GHZ</option>
                                    <option value="Ningbo">Ningbo</option>
                                    <option value="Tianjin">Tianjin</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Approved</label>
                                <div class="d-flex align-items-center gap-2 approval-form-dots flex-wrap">
                                    <label class="mb-0 cursor-pointer small text-muted border rounded px-2 py-1" title="Not set">
                                        <input type="radio" name="approval_status" value="" class="d-none" checked> None
                                    </label>
                                    <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="disqualified">
                                        <input type="radio" name="approval_status" value="red" class="d-none">
                                        <span class="d-inline-block supplier-approval-dot supplier-approval-dot--red border-0" title="disqualified"></span>
                                    </label>
                                    <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Qualified">
                                        <input type="radio" name="approval_status" value="green" class="d-none">
                                        <span class="d-inline-block supplier-approval-dot supplier-approval-dot--green border-0" title="Qualified"></span>
                                    </label>
                                    <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Explore">
                                        <input type="radio" name="approval_status" value="yellow" class="d-none">
                                        <span class="d-inline-block supplier-approval-dot supplier-approval-dot--yellow border-0" title="Explore"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control" placeholder="Email Address">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">WhatsApp</label>
                                <input type="text" name="whatsapp" class="form-control" placeholder="WhatsApp Number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">WeChat</label>
                                <input type="text" name="wechat" class="form-control" placeholder="WeChat ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Alibaba</label>
                                <input type="text" name="alibaba" class="form-control" placeholder="Alibaba Profile">
                            </div>
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Website URL</label>
                                        <input type="text" name="website" class="form-control" placeholder="enter website URL">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Others</label>
                                        <input type="text" name="others" class="form-control" placeholder="Other Details">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Address</label>
                                        <input type="text" name="address" class="form-control" placeholder="Full Address">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Bank Details</label>
                                <textarea name="bank_details" class="form-control" rows="2" placeholder="Bank Details"></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary" id="addSupplierSubmitBtn">
                                <i class="mdi mdi-content-save"></i> Save Supplier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Bulk Action modal: opened from the Action column.
         Targets = the row whose Action button was clicked, plus any
         checkbox-selected rows. Only fields with a non-empty value are
         applied; leave any field blank to skip it. --}}
    <div class="modal fade" id="toaActionModal" tabindex="-1" aria-labelledby="toaActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="toaActionModalLabel">
                        <i class="mdi mdi-pencil-box-multiple-outline me-2"></i>
                        Bulk Edit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" role="status">
                        <i class="mdi mdi-information-outline text-primary me-2"></i>
                        <div class="small">
                            Editing <strong id="toa-action-target-count">0</strong> row(s):
                            <span class="text-muted" id="toa-action-target-skus"></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-3">Only fields you fill in below will be applied. Leave a field blank to keep its current value.</p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Supplier</label>
                        <select id="toa-action-supplier" class="form-select form-select-sm">
                            <option value="">— Keep current —</option>
                            @foreach($allSuppliers ?? [] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Executive</label>
                        <select id="toa-action-executive" class="form-select form-select-sm">
                            <option value="">— Keep current —</option>
                            <option value="__unassigned__">— Unassigned —</option>
                            <option value="Atin">Atin</option>
                            <option value="Jack">Jack</option>
                            <option value="Nitish">Nitish</option>
                            <option value="Ajay">Ajay</option>
                            <option value="Candy">Candy</option>
                            <option value="Sruti">Sruti</option>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold">Stage</label>
                        <select id="toa-action-stage" class="form-select form-select-sm">
                            <option value="">— Keep current —</option>
                            <option value="appr_req">Appr. Req</option>
                            <option value="mip">MIP</option>
                            <option value="r2s">R2S</option>
                            <option value="transit">Transit</option>
                            <option value="all_good">😊 All Good</option>
                            <option value="to_order_analysis">2 Order</option>
                        </select>
                        <div class="form-text small">Stage rows must have MOQ &gt; 0; rows without MOQ will be skipped.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="toa-action-apply-btn">
                        <i class="mdi mdi-check-bold me-1"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Add Supplier modal (duplicated from supplier list page) — Select2 + AJAX save
        $(function () {
            const $modal = $('#addSupplierModal');
            if (!$modal.length) return;

            function initCategorySelect2() {
                const $sel = $modal.find('select[name="category_id[]"]');
                if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: $sel.data('placeholder') || 'Select Category',
                        dropdownParent: $modal,
                        allowClear: false
                    });
                }
            }

            $modal.on('shown.bs.modal', function () {
                setTimeout(initCategorySelect2, 100);
            });

            $modal.on('hidden.bs.modal', function () {
                const $sel = $modal.find('select[name="category_id[]"]');
                if ($sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2('destroy');
                }
                $modal.find('form')[0].reset();
                $sel.val(null).trigger('change');
            });

            $('#addSupplierForm').on('submit', function (e) {
                e.preventDefault();
                const form = this;
                const $btn = $('#addSupplierSubmitBtn');

                const fd = new FormData(form);
                const cats = fd.getAll('category_id[]').filter(v => v != null && v !== '');
                if (cats.length === 0) {
                    alert('Please select at least one category.');
                    return;
                }
                if (!fd.get('type')) { alert('Please select a type.'); return; }
                if (!String(fd.get('name') || '').trim()) { alert('Please enter supplier name.'); return; }

                const orig = $btn.html();
                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Saving...');

                $.ajax({
                    url: form.action,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .done(function (res) {
                    if (res && res.success) {
                        const newName = res.supplier && res.supplier.name ? res.supplier.name : '';
                        // Make the new supplier available in the supplier filter immediately
                        if (newName) {
                            const el = document.getElementById('supplier-filter');
                            if (el && !Array.from(el.options).some(o => o.value === newName)) {
                                el.add(new Option(newName, newName));
                            }
                        }
                        const modalInst = bootstrap.Modal.getInstance($modal[0]) || new bootstrap.Modal($modal[0]);
                        modalInst.hide();
                        alert(res.message || 'Supplier successfully created.');
                    } else {
                        alert((res && res.message) ? res.message : 'Something went wrong while saving.');
                    }
                })
                .fail(function (xhr) {
                    let msg = 'Error saving supplier.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                        } else if (xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                    }
                    alert(msg);
                })
                .always(function () {
                    $btn.prop('disabled', false).html(orig);
                });
            });
        });
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.zoom = "80%";

            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const globalPreview = Object.assign(document.createElement("div"), {
                id: "image-hover-preview",
            });

            Object.assign(globalPreview.style, {
                position: "fixed",
                zIndex: 9999,
                border: "1px solid #ccc",
                background: "#fff",
                padding: "4px",
                boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
                display: "none",
            });
            document.body.appendChild(globalPreview);

            let hideTimeout;
            let uniqueSuppliers = [];
            let allSuppliers = @json($allSuppliers ?? []);
            const currentUserEmail = @json(strtolower((string) (auth()->user()->email ?? '')));
            const canEditDoa = currentUserEmail === 'president@5core.com';

            function escapeHtmlAttr(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            const TOA_DATA_FIELD_META = {
                instructions_item_pkg: { title: "Item Pkg.", editable: true, multiline: true, maxLength: 2000 },
                ctn_instructions: { title: "Instr Carton", editable: true, multiline: false, maxLength: 100 },
                packing_instructions: { title: "Design Instr.", editable: false, multiline: true, maxLength: 0 },
                instructions_carton_design: { title: "Design Instr Carton", editable: true, multiline: true, maxLength: 2000 },
                packing_cdr_path: { title: "CDR", editable: false, isFilePath: true },
                issues: { title: "Issues", editable: false, multiline: true, maxLength: 0, qcSource: true },
            };

            let activeToaDataRow = null;
            let activeToaDataField = null;
            let activeMoqRow = null;

            function renderDesignDataDot(rawValue, meta) {
                meta = meta || {};
                const v = (rawValue || "").trim();
                const hasData = v.length > 0;
                const dotColor = hasData ? "#22c55e" : "#dc3545";
                const tip = hasData
                    ? escapeHtmlAttr(meta.tip != null ? meta.tip : v)
                    : "Data required";
                const field = escapeHtmlAttr(meta.field || "");
                const sku = escapeHtmlAttr(meta.sku || "");
                const parent = escapeHtmlAttr(meta.parent || "");
                const pid = meta.pid ? String(meta.pid) : "";
                const editable = meta.editable === false ? "0" : "1";
                const tipHtml = escapeHtmlAttr(tip);
                const wrapClass = hasData && tip.length > 36 ? " purchase-hover-tip-badge--wrap" : "";
                return `<div class="toa-data-dot-wrap d-flex justify-content-center align-items-center w-100">
                    <button type="button" class="toa-data-dot-btn btn btn-link p-0 border-0"
                        data-field="${field}" data-sku="${sku}" data-parent="${parent}" data-pid="${pid}" data-editable="${editable}"
                        aria-label="${tipHtml}">
                        <span class="toa-status-dot" style="background-color:${dotColor};"></span>
                        <span class="purchase-hover-tip-badge${wrapClass}">${tipHtml}</span>
                    </button>
                </div>`;
            }

            function renderToaDataDotCell(cell, fieldKey, opts) {
                opts = opts || {};
                const row = cell.getRow().getData();
                if (row.is_parent) {
                    return '<span class="text-muted small">—</span>';
                }
                if (opts.requireProductId && !row.product_master_id) {
                    return renderDesignDataDot("", { field: fieldKey, sku: row.SKU, parent: row.Parent, editable: false });
                }
                const meta = TOA_DATA_FIELD_META[fieldKey] || {};
                let tipOverride = null;
                if (meta.isFilePath) {
                    const raw = String(cell.getValue() ?? "").trim();
                    tipOverride = raw ? (raw.split(/[/\\]/).pop() || raw) : null;
                }
                if (fieldKey === "issues") {
                    const raw = String(cell.getValue() ?? "").trim();
                    if (raw) {
                        const firstLine = raw.split("\n")[0];
                        tipOverride = firstLine.length > 160 ? firstLine.slice(0, 157) + "..." : firstLine;
                    }
                }
                return renderDesignDataDot(cell.getValue(), {
                    field: fieldKey,
                    sku: row.SKU,
                    parent: row.Parent,
                    pid: row.product_master_id,
                    editable: meta.editable !== false,
                    tip: tipOverride,
                });
            }

            function toaFileUrl(raw) {
                const path = String(raw || "").trim();
                if (!path) return "";
                return /^https?:\/\//i.test(path) ? path : ("/" + path.replace(/^\//, ""));
            }

            // Open the combined PKG modal showing all 6 packaging/design/CDR/issues fields.
            function openPkgModal(rowData) {
                const body = document.getElementById("pkgModalBody");
                const skuEl = document.getElementById("pkgModalSku");
                skuEl.textContent = rowData.SKU ? `( ${rowData.SKU} )` : "";

                const html = Object.keys(TOA_DATA_FIELD_META).map(function(fieldKey) {
                    const meta = TOA_DATA_FIELD_META[fieldKey] || { title: fieldKey };
                    const raw = String(rowData[fieldKey] ?? "").trim();
                    let valueHtml;
                    if (!raw) {
                        valueHtml = '<span class="text-danger fst-italic">No data</span>';
                    } else if (meta.isFilePath) {
                        const url = toaFileUrl(raw);
                        const name = raw.split(/[/\\]/).pop() || raw;
                        valueHtml = `<a href="${escapeHtmlAttr(url)}" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-file-arrow-down me-1"></i>${escapeHtmlAttr(name)}</a>`;
                    } else {
                        const esc = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        valueHtml = `<div style="white-space:pre-wrap;">${esc}</div>`;
                    }
                    return `<div class="pkg-modal-item mb-3">
                        <div class="fw-bold text-primary mb-1">${escapeHtmlAttr(meta.title || fieldKey)}</div>
                        <div class="border rounded p-2 bg-light" style="font-size:13px;">${valueHtml}</div>
                    </div>`;
                }).join("");

                body.innerHTML = html;
                bootstrap.Modal.getOrCreateInstance(document.getElementById("pkgModal")).show();
            }

            // Open a modal listing all suppliers in the given category.
            function openSupplierCategoryModal(category) {
                const nameEl = document.getElementById("supplierCategoryName");
                const body = document.getElementById("supplierCategoryModalBody");
                const cat = (category || "").trim();
                nameEl.textContent = cat ? `( ${cat} )` : "";

                if (!cat) {
                    body.innerHTML = '<div class="text-muted fst-italic">No category set for this row.</div>';
                    bootstrap.Modal.getOrCreateInstance(document.getElementById("supplierCategoryModal")).show();
                    return;
                }

                body.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
                bootstrap.Modal.getOrCreateInstance(document.getElementById("supplierCategoryModal")).show();

                const dotColor = { red: "#dc3545", green: "#198754", yellow: "#ffc107" };
                fetch('{{ route('to.order.analysis.suppliers.by.category') }}?category=' + encodeURIComponent(cat), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(res => {
                    const list = (res && res.suppliers) || [];
                    if (!list.length) {
                        body.innerHTML = '<div class="text-muted fst-italic">No suppliers found for this category.</div>';
                        return;
                    }
                    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    let rows = list.map(function(s) {
                        const dot = s.approval_status && dotColor[s.approval_status]
                            ? `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${dotColor[s.approval_status]};"></span>`
                            : '<span class="text-muted">—</span>';
                        return `<tr>
                            <td class="text-center">${dot}</td>
                            <td>${esc(s.name)}</td>
                            <td>${esc(s.company) || '<span class="text-muted">—</span>'}</td>
                            <td>${esc(s.phone) || '<span class="text-muted">—</span>'}</td>
                            <td>${esc(s.email) || '<span class="text-muted">—</span>'}</td>
                            <td>${esc(s.whatsapp) || '<span class="text-muted">—</span>'}</td>
                            <td>${esc(s.city) || '<span class="text-muted">—</span>'}</td>
                        </tr>`;
                    }).join("");
                    body.innerHTML = `<div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:13px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">Appr</th><th>Name</th><th>Company</th>
                                    <th>Phone</th><th>Email</th><th>WhatsApp</th><th>City</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`;
                })
                .catch(() => {
                    body.innerHTML = '<div class="text-danger">Failed to load suppliers.</div>';
                });
            }

            $(document).on('click', '.toa-supplier-cat-btn', function() {
                openSupplierCategoryModal(this.getAttribute('data-category') || '');
            });

            // Open the QC / Improvement-required modal for a SKU (data from qc-and-packing).
            function openQcIssueModal(sku) {
                const titleSku = document.getElementById("qcIssueModalSku");
                const issuesBody = document.getElementById("qcIssueModalIssues");
                const historyBody = document.getElementById("qcIssueModalHistory");
                titleSku.textContent = sku ? `( ${sku} )` : "";
                issuesBody.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
                historyBody.innerHTML = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById("qcIssueModal")).show();

                const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const txt = v => { const s = esc(v); return s.trim() ? s : '<span class="text-muted">—</span>'; };
                const dot = v => String(v ?? '').trim()
                    ? '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#198754;"></span>'
                    : '<span style="display:inline-block;width:11px;height:11px;border-radius:50%;background:#dc3545;"></span>';

                fetch('{{ route('to.order.analysis.qc.issues') }}?sku=' + encodeURIComponent(sku), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(res => {
                    const issues = (res && res.issues) || [];
                    const history = (res && res.history) || [];

                    if (!issues.length) {
                        issuesBody.innerHTML = '<div class="text-muted fst-italic p-2">No QC / packing issues recorded for this SKU.</div>';
                    } else {
                        const rows = issues.map(function(it) {
                            const found = [it.issue, it.issue_remark].filter(Boolean).join(': ');
                            const fixed = [it.c_action_1, it.c_action_1_remark].filter(Boolean).join(': ');
                            return `<tr>
                                <td>${txt(it.what_happened)}</td>
                                <td class="text-center">${dot(it.issue)}</td>
                                <td>${txt(found)}</td>
                                <td class="text-center">${dot(it.c_action_1)}</td>
                                <td>${txt(fixed)}</td>
                            </tr>`;
                        }).join("");
                        issuesBody.innerHTML = `<div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Issue?</th>
                                        <th class="text-center">RC Found</th>
                                        <th>Root Cause Found</th>
                                        <th class="text-center">RC Fixed</th>
                                        <th>Root Cause Fixed</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>`;
                    }

                    if (!history.length) {
                        historyBody.innerHTML = '<div class="text-muted fst-italic p-2">No history found.</div>';
                    } else {
                        const hrows = history.map(function(h) {
                            const found = [h.issue, h.issue_remark].filter(Boolean).join(': ');
                            const fixed = [h.c_action_1, h.c_action_1_remark].filter(Boolean).join(': ');
                            const when = h.logged_at || h.created_at || '';
                            return `<tr>
                                <td>${txt(when)}</td>
                                <td>${txt(h.event_type)}</td>
                                <td>${txt(h.what_happened)}</td>
                                <td>${txt(found)}</td>
                                <td>${txt(fixed)}</td>
                                <td>${txt(h.created_by)}</td>
                            </tr>`;
                        }).join("");
                        historyBody.innerHTML = `<div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0" style="font-size:12px;">
                                <thead class="table-light">
                                    <tr>
                                        <th>When</th><th>Event</th><th>Issue?</th>
                                        <th>Root Cause Found</th><th>Root Cause Fixed</th><th>By</th>
                                    </tr>
                                </thead>
                                <tbody>${hrows}</tbody>
                            </table>
                        </div>`;
                    }
                })
                .catch(() => {
                    issuesBody.innerHTML = '<div class="text-danger p-2">Failed to load issues.</div>';
                });
            }

            // "Add Supplier" inside the suppliers-by-category modal: close it, then open the Add Supplier modal
            $(document).on('click', '#catModalAddSupplierBtn', function() {
                const catModalEl = document.getElementById('supplierCategoryModal');
                const catInst = bootstrap.Modal.getInstance(catModalEl);
                const openAdd = function() {
                    catModalEl.removeEventListener('hidden.bs.modal', openAdd);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('addSupplierModal')).show();
                };
                catModalEl.addEventListener('hidden.bs.modal', openAdd);
                if (catInst) { catInst.hide(); } else { openAdd(); }
            });

            function openToaMoqModal(rowData) {
                const skuEl = document.getElementById("toaMoqModalSku");
                const inp = document.getElementById("toaMoqModalInput");
                const val = rowData.approved_qty;
                const num = parseInt(val, 10);
                skuEl.textContent = rowData.SKU ? `(${rowData.SKU})` : "";
                inp.value = (val !== "" && val != null && !isNaN(num)) ? num : "";
                bootstrap.Modal.getOrCreateInstance(document.getElementById("toaMoqModal")).show();
                setTimeout(function () { inp.focus(); inp.select(); }, 300);
            }

            async function saveToaMoq(rowData, newValue) {
                const sku = rowData.SKU || "";
                const res = await fetch("/update-link", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ sku: sku, column: "approved_qty", value: newValue }),
                });
                const data = await res.json();
                if (!data.success) {
                    throw new Error(data.message || "Save failed");
                }
                return newValue;
            }

            function openToaDataModal(fieldKey, rowData, opts) {
                opts = opts || {};
                const meta = TOA_DATA_FIELD_META[fieldKey] || { title: fieldKey, editable: false };
                const editable = opts.editable === false ? false : meta.editable !== false;
                const val = String(rowData[fieldKey] ?? "").trim();
                const sku = rowData.SKU || "";
                const modalEl = document.getElementById("toaDataModal");
                const titleEl = document.getElementById("toaDataModalTitle");
                const skuEl = document.getElementById("toaDataModalSku");
                const emptyEl = document.getElementById("toaDataModalEmpty");
                const ta = document.getElementById("toaDataModalTextarea");
                const inp = document.getElementById("toaDataModalInput");
                const ro = document.getElementById("toaDataModalReadonly");
                const fileWrap = document.getElementById("toaDataModalFileWrap");
                const fileLabel = document.getElementById("toaDataModalFileLabel");
                const fileLink = document.getElementById("toaDataModalFileLink");
                const sourceNote = document.getElementById("toaDataModalSourceNote");
                const saveBtn = document.getElementById("toaDataModalSaveBtn");
                const copyBtn = document.getElementById("toaDataModalCopyBtn");

                titleEl.textContent = meta.title || fieldKey;
                skuEl.textContent = sku ? `(${sku})` : "";

                ta.classList.add("d-none");
                inp.classList.add("d-none");
                ro.classList.add("d-none");
                fileWrap.classList.add("d-none");
                sourceNote?.classList.add("d-none");
                emptyEl.classList.add("d-none");
                saveBtn.classList.add("d-none");
                copyBtn.classList.add("d-none");

                if (editable) {
                    if (meta.multiline) {
                        ta.classList.remove("d-none");
                        ta.value = val;
                        ta.maxLength = meta.maxLength || 2000;
                        ta.readOnly = false;
                    } else {
                        inp.classList.remove("d-none");
                        inp.value = val;
                        inp.maxLength = meta.maxLength || 100;
                        inp.readOnly = false;
                    }
                    saveBtn.classList.remove("d-none");
                    if (val) copyBtn.classList.remove("d-none");
                } else {
                    if (val) {
                        if (meta.isFilePath) {
                            fileWrap.classList.remove("d-none");
                            const base = val.split(/[/\\]/).pop() || val;
                            fileLabel.textContent = base;
                            fileLink.href = toaFileUrl(val);
                            fileLink.setAttribute("download", base);
                        } else {
                            ro.classList.remove("d-none");
                            ro.textContent = val;
                            if (meta.qcSource) {
                                sourceNote?.classList.remove("d-none");
                            } else {
                                copyBtn.classList.remove("d-none");
                            }
                        }
                    } else {
                        emptyEl.classList.remove("d-none");
                        if (meta.qcSource) {
                            emptyEl.textContent = "Data required — add issues on QC & packing page.";
                            sourceNote?.classList.remove("d-none");
                        } else {
                            emptyEl.textContent = "Data required";
                        }
                    }
                }

                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }

            async function saveToaDataField(fieldKey, rowData, newText) {
                const pid = parseInt(rowData.product_master_id, 10);
                const sku = rowData.SKU || "";
                const parent = rowData.Parent || "";
                const csrf = document.querySelector('meta[name="csrf-token"]').content;

                if (fieldKey === "instructions_item_pkg") {
                    const res = await fetch("/instructions-item-pkg/update", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
                        body: JSON.stringify({ product_id: pid, sku: sku, instructions: newText }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || "Save failed");
                    return data.instructions != null ? String(data.instructions) : "";
                }

                if (fieldKey === "instructions_carton_design") {
                    const res = await fetch("/instructions-carton-design/update", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
                        body: JSON.stringify({ product_id: pid, sku: sku, instructions: newText }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || "Save failed");
                    return data.instructions != null ? String(data.instructions) : "";
                }

                if (fieldKey === "ctn_instructions") {
                    const res = await fetch("/dim-wt-master/update", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf },
                        body: JSON.stringify({
                            product_id: pid,
                            sku: sku,
                            parent: parent,
                            ctn_instructions: newText.length ? newText : null,
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message || "Save failed");
                    return newText;
                }

                throw new Error("This field cannot be saved here.");
            }

            function openMonthModal(monthData, sku) {
                const wrapper = document.getElementById("monthCardWrapper");
                if (!wrapper) return;
                wrapper.innerHTML = "";

                const monthOrder = [
                    "JAN", "FEB", "MAR", "APR", "MAY", "JUN",
                    "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"
                ];
                const currentDate = new Date();
                const currentYear = currentDate.getFullYear();
                const currentMonth = currentDate.getMonth();
                const monthIndexMap = {
                    "JAN": 0, "FEB": 1, "MAR": 2, "APR": 3,
                    "MAY": 4, "JUN": 5, "JUL": 6, "AUG": 7,
                    "SEP": 8, "OCT": 9, "NOV": 10, "DEC": 11
                };
                const getYearForMonth = (monthIndex) => {
                    if (monthIndex > currentMonth) return currentYear - 1;
                    return currentYear;
                };

                monthOrder.forEach(month => {
                    const value = monthData[month] ?? 0;
                    const monthIndex = monthIndexMap[month];
                    const year = getYearForMonth(monthIndex);
                    const card = document.createElement("div");
                    card.className = "month-card";
                    const title = document.createElement("div");
                    title.className = "month-title";
                    title.innerText = `${month} ${year}`;
                    const count = document.createElement("div");
                    count.className = "month-value";
                    count.innerText = value;
                    card.appendChild(title);
                    card.appendChild(count);
                    wrapper.appendChild(card);
                });

                document.getElementById("month-view-sku").innerText = `( ${sku} )`;
                const modal = new bootstrap.Modal(document.getElementById("monthModal"));
                modal.show();
            }

            const table = new Tabulator("#toOrderAnalysis-table", {
                ajaxURL: "/to-order-analysis/data",
                ajaxConfig: {
                    method: "GET",
                    headers: {
                        "Cache-Control": "no-cache, no-store, must-revalidate",
                        "Pragma": "no-cache"
                    }
                },
                index: "SKU",
                selectableRows: true,
                layout: "fitData",
                height: "700px",
                initialSort: [{ column: "Date of Appr", dir: "asc" }],
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                movableColumns: false,
                resizableColumns: true,
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        width: 50,
                        headerSort: false
                    },
                    {
                        title: "#",
                        field: "Image",
                        headerSort: false,
                        formatter: (cell) => {
                            const url = cell.getValue();
                            return url ?
                                `<img src="${url}" data-full="${url}" class="hover-thumb" 
                                   style="width:30px;height:30px;border-radius:6px;object-fit:contain;
                                   box-shadow:0 1px 4px #0001;cursor: pointer;">` :
                                `<span class="text-muted">N/A</span>`;
                        },
                        cellMouseOver: (e, cell) => {
                            clearTimeout(hideTimeout);

                            const img = cell.getElement().querySelector(".hover-thumb");
                            if (!img) return;

                            globalPreview.innerHTML = `<img src="${img.dataset.full}" style="max-width:350px;max-height:350px;">`;
                            globalPreview.style.display = "block";
                            globalPreview.style.top = `${e.clientY + 15}px`;
                            globalPreview.style.left = `${e.clientX + 15}px`;
                        },
                        cellMouseMove: (e) => {
                            globalPreview.style.top = `${e.clientY + 15}px`;
                            globalPreview.style.left = `${e.clientX + 15}px`;
                        },
                        cellMouseOut: () => {
                            hideTimeout = setTimeout(() => {
                                globalPreview.style.display = "none";
                            }, 150);
                        },
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: " Filter parent...",
                        width: 180,
                        headerFilterLiveFilter: true,
                    },
                    {
                        title: "Executive",
                        titleFormatter: function() {
                            return '<img src="{{ asset('assets/images/executive.png') }}" alt="Executive" title="Executive" style="width:26px;height:26px;object-fit:contain;vertical-align:middle;">';
                        },
                        field: "Exec",
                        hozAlign: "center",
                        width: 72,
                        minWidth: 60,
                        headerSort: true,
                        headerTooltip: "Executive assigned",
                        formatter: function(cell) {
                            const val = (cell.getValue() || "").trim();
                            const label = val || "— Unassigned —";
                            const colors = {
                                "Atin":   { bg: "#3b82f6", text: "#fff" },
                                "Jack":   { bg: "#10b981", text: "#fff" },
                                "Nitish": { bg: "#8b5cf6", text: "#fff" },
                                "Ajay":   { bg: "#f59e0b", text: "#fff" },
                                "Candy":  { bg: "#ec4899", text: "#fff" },
                                "Sruti":  { bg: "#14b8a6", text: "#fff" },
                            };
                            const c = colors[val] || { bg: "#e5e7eb", text: "#6b7280" };
                            const options = ["", "Atin", "Jack", "Nitish", "Ajay", "Candy", "Sruti"]
                                .map(o => `<option value="${o}"${o === val ? " selected" : ""}>${o || "— Unassigned —"}</option>`)
                                .join("");
                            const rowData = cell.getRow().getData();
                            const sku = (rowData.SKU || "").replace(/"/g, "&quot;");
                            const rowId = rowData.id || 0;
                            return `<select class="toa-exec-select"
                                data-sku="${sku}" data-row-id="${rowId}"
                                style="width:100%;border:none;border-radius:6px;padding:3px 6px;font-size:0.82rem;font-weight:600;background:${c.bg};color:${c.text};cursor:pointer;outline:none;">
                                ${options}
                            </select>`;
                        },
                        cellClick: function(e) { e.stopPropagation(); },
                    },
                    {
                        title: "SKU",
                        field: "SKU",
                        width: 180,
                        headerTooltip: "Short name of product",
                    },
                    {
                        title: "MOQ",
                        field: "approved_qty",
                        headerTooltip: "Minimum order quantity",
                        hozAlign: "center",
                        vertAlign: "middle",
                        width: 80,
                        minWidth: 70,
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const val = cell.getValue();
                            const num = parseInt(val, 10);
                            const hasVal = val !== "" && val != null && !isNaN(num);
                            const display = hasVal ? num : "—";
                            const sku = escapeHtmlAttr(row.SKU || "");
                            return `<div class="d-flex justify-content-center align-items-center w-100">
                                <button type="button" class="toa-moq-btn btn btn-link p-0 border-0 text-dark fw-semibold"
                                    data-sku="${sku}" title="Click to edit MOQ">${display}</button>
                            </div>`;
                        }
                    },
                    {
                        title: "MSL",
                        field: "msl",
                        hozAlign: "center",
                        headerSort: true,
                        width: 90,
                        headerTooltip: "Minimum stock level (4 months requirement)",
                        formatter: function(cell) {
                            const msl = cell.getValue();
                            const val = msl != null && msl !== '' ? parseInt(msl, 10) : 0;
                            if (val <= 0) {
                                return '<span class="text-muted">—</span>';
                            }
                            return `
                                <div style="text-align:center; font-weight:bold;">
                                    ${val}
                                    <button class="btn btn-sm btn-link text-info open-month-modal" style="padding: 0 4px;" title="View Monthly">
                                        <i class="bi bi-calendar3"></i>
                                    </button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest(".open-month-modal")) {
                                const row = cell.getRow().getData();
                                const sku = row["SKU"] || '';
                                const monthData = {
                                    "JAN": row["Jan"] ?? 0,
                                    "FEB": row["Feb"] ?? 0,
                                    "MAR": row["Mar"] ?? 0,
                                    "APR": row["Apr"] ?? 0,
                                    "MAY": row["May"] ?? 0,
                                    "JUN": row["Jun"] ?? 0,
                                    "JUL": row["Jul"] ?? 0,
                                    "AUG": row["Aug"] ?? 0,
                                    "SEP": row["Sep"] ?? 0,
                                    "OCT": row["Oct"] ?? 0,
                                    "NOV": row["Nov"] ?? 0,
                                    "DEC": row["Dec"] ?? 0
                                };
                                openMonthModal(monthData, sku);
                            }
                        }
                    },
                    {
                        title: "DOA",
                        field: "Date of Appr",
                        width: 80,
                        minWidth: 72,
                        headerTooltip: canEditDoa
                            ? "Approval (needs to be ordered within 15 days max) - click to edit"
                            : "Approval (needs to be ordered within 15 days max)",
                        sorter: "date",
                        sorterParams: { format: "YYYY-MM-DD", alignEmptyValues: "bottom" },
                        editor: canEditDoa ? "date" : false,
                        cellEdited: function (cell) {
                            saveLinkUpdate(cell, cell.getValue());
                        },
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            let displayText = "-";
                            let bgColor = "";
                            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

                            if (value) {
                                let d;
                                const parts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
                                if (parts) {
                                    d = new Date(parseInt(parts[1], 10), parseInt(parts[2], 10) - 1, parseInt(parts[3], 10));
                                } else {
                                    d = new Date(value);
                                }
                                if (!isNaN(d.getTime())) {
                                    displayText = `${d.getDate()} ${monthNames[d.getMonth()]}`;
                                }

                                const today = new Date();
                                today.setHours(0, 0, 0, 0);
                                d.setHours(0, 0, 0, 0);
                                const diffTime = today - d;
                                const daysDiff = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                                if (daysDiff >= 14) {
                                    bgColor = "color:red; font-weight:700;";
                                } else if (daysDiff >= 7) {
                                    bgColor = "color:#FFC106; font-weight:700;";
                                }
                            }

                            return `<span style="min-width:52px; display:inline-block; ${bgColor}">${displayText}</span>`;
                        }
                    },
                    {
                        title: "Supplier",
                        field: "Supplier",
                        width: 160,
                        minWidth: 130,
                        formatter: function(cell){
                            let value = cell.getValue() || "";
                            const rowData = cell.getRow().getData();
                            let sku = escapeHtmlAttr(rowData.SKU || "");
                            let parentAttr = escapeHtmlAttr(rowData.Parent || "");
                            let list = [...new Set([...(allSuppliers || []), value].filter(Boolean))].sort();
                            let options = list.map(supplier => {
                                let selected = (supplier === value) ? "selected" : "";
                                return `<option value="${(supplier || "").replace(/"/g, "&quot;")}" ${selected}>${(supplier || "").replace(/</g, "&lt;")}</option>`;
                            }).join("");
                            let selectSelected = (!value || value.trim() === "") ? " selected" : "";
                            let categoryAttr = escapeHtmlAttr(rowData.Category || "");
                            return `
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-parent="${parentAttr}" data-column="Supplier" style="width: 110px; max-width: 100%;">
                                        <option value=""${selectSelected}>-- Select --</option>
                                        ${options}
                                    </select>
                                    <button type="button" class="btn btn-sm btn-link p-0 toa-supplier-cat-btn" data-category="${categoryAttr}" title="View suppliers in this category">
                                        <i class="fas fa-search" style="font-size:14px;color:#2563eb;"></i>
                                    </button>
                                </div>`;
                        }
                    },
                    {
                        title: "Category",
                        field: "Category",
                        width: 120,
                        minWidth: 90,
                        headerTooltip: "Category (from the supplier)",
                        formatter: function (cell) {
                            const v = (cell.getValue() || "").trim();
                            return v ? v : '<span class="text-muted">—</span>';
                        }
                    },
                    {
                        title: "PKG",
                        field: "pkg_view",
                        hozAlign: "center",
                        vertAlign: "middle",
                        width: 70,
                        minWidth: 60,
                        headerSort: false,
                        headerTooltip: "View all packaging / design / CDR / issues details",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent) {
                                return '<span class="text-muted small">—</span>';
                            }
                            return `<button type="button" class="btn btn-sm btn-link p-0 toa-pkg-view-btn" title="View packaging details">
                                <i class="fas fa-search" style="font-size:16px;color:#2563eb;"></i>
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest(".toa-pkg-view-btn")) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return;
                                openPkgModal(row);
                            }
                        }
                    },
                    {
                        title: "Item Pkg.",
                        field: "instructions_item_pkg",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Packaging instruction for a single unit",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "instructions_item_pkg", { requireProductId: true });
                        }
                    },
                    {
                        title: "Instr<br>Carton",
                        field: "ctn_instructions",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Packaging instructions for carton",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "ctn_instructions", { requireProductId: true });
                        }
                    },
                    {
                        title: "Design<br>Instr.",
                        field: "packing_instructions",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Instructions for single unit box design",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "packing_instructions");
                        }
                    },
                    {
                        title: "Design<br>Instr<br>Carton",
                        field: "instructions_carton_design",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Design instructions for carton",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "instructions_carton_design", { requireProductId: true });
                        }
                    },
                    {
                        title: "CDR",
                        field: "packing_cdr_path",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Coral draw files for inner and carton box",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "packing_cdr_path");
                        }
                    },
                    {
                        title: "Issues",
                        field: "issues",
                        visible: false,
                        width: 70,
                        minWidth: 60,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Previous issue that needs to be rectified in the next purchase",
                        formatter: function (cell) {
                            return renderToaDataDotCell(cell, "issues");
                        }
                    },
                    {
                        title: "Reviews",
                        titleFormatter: function() {
                            return '<img src="{{ asset('assets/images/improvement.png') }}" alt="Improvement Required" title="Improvement Required" style="width:28px;height:28px;object-fit:contain;vertical-align:middle;">';
                        },
                        field: "Reviews",
                        width: 90,
                        minWidth: 70,
                        hozAlign: "center",
                        vertAlign: "middle",
                        headerSort: false,
                        headerTooltip: "Improvement Required",
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent) {
                                return '<span class="text-muted small">—</span>';
                            }
                            const hasData = String(row.issues || "").trim() !== "";
                            if (hasData) {
                                return `<button type="button" class="btn btn-sm btn-link p-0 toa-qc-issue-btn" title="View improvement / QC issues">
                                    <img src="{{ asset('assets/images/improvement.png') }}" alt="Issues" style="width:24px;height:24px;object-fit:contain;">
                                </button>`;
                            }
                            return `<button type="button" class="btn btn-sm btn-link p-0 toa-qc-issue-btn" title="No issues recorded — view / add">
                                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc3545;"></span>
                            </button>`;
                        },
                        cellClick: function (e, cell) {
                            if (e.target.closest(".toa-qc-issue-btn")) {
                                const row = cell.getRow().getData();
                                if (row.is_parent) return;
                                openQcIssueModal(row.SKU || "");
                            }
                        }
                    },
                    {
                        title: "Amz.",
                        field: "rating",
                        hozAlign: "center",
                        headerSort: false,
                        headerTooltip: "Amazon reviews",
                        formatter: function(cell) {
                            const rating = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const reviews = rowData.reviews || 0;
                            if (!rating || rating === 0) {
                                return '<span style="color: #6c757d;">-</span>';
                            }
                            let ratingColor = '';
                            const ratingVal = parseFloat(rating);
                            if (ratingVal < 3) ratingColor = '#a00211';
                            else if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                            else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                            else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                            else ratingColor = '#e83e8c';
                            const reviewColor = reviews < 4 ? '#a00211' : '#6c757d';
                            return `<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                <span style="color: ${ratingColor}; font-weight: 600;"><i class="fa fa-star"></i> ${parseFloat(rating).toFixed(1)}</span>
                                <span style="font-size: 11px; color: ${reviewColor}; font-weight: 600;">${parseInt(reviews).toLocaleString()} reviews</span>
                            </div>`;
                        },
                        width: 80
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        headerSort: true,
                        headerTooltip: "Lowest market price and competition product links",
                        width: 100,
                        formatter: function (cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent) {
                                return '<span class="text-muted">—</span>';
                            }

                            const lmpPrice = cell.getValue();
                            const sku = rowData.SKU || "";
                            const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
                            const lmpLink = rowData.lmp_link || "";

                            if (!lmpPrice && totalCompetitors === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">';

                            if (lmpPrice) {
                                const priceFormatted = "$" + parseFloat(lmpPrice).toFixed(2);
                                if (lmpLink) {
                                    html += `<a href="${escapeHtmlAttr(lmpLink)}" target="_blank" rel="noopener"
                                        style="color:#28a745;font-weight:600;font-size:14px;text-decoration:none;"
                                        title="Lowest competitor link">${priceFormatted}</a>`;
                                } else {
                                    html += `<span style="color:#28a745;font-weight:600;font-size:14px;">${priceFormatted}</span>`;
                                }
                            }

                            if (totalCompetitors > 0) {
                                html += `<a href="#" class="toa-view-lmp-competitors" data-sku="${escapeHtmlAttr(sku)}"
                                    style="color:#007bff;text-decoration:none;cursor:pointer;font-size:11px;">
                                    <i class="fa fa-eye"></i> View ${totalCompetitors}
                                </a>`;
                            }

                            html += "</div>";
                            return html;
                        }
                    },
                    {
                        title: "Review",
                        field: "Review",
                        formatter: function(cell){
                            const data = cell.getRow().getData();
                            if(data.has_review){
                                return `<button class="btn btn-sm btn-outline-info review-btn" data-action="view"><i class="fas fa-eye"></i> View</button>`;
                            } else {
                                return `<button class="btn btn-sm btn-outline-dark review-btn" data-action="review"><i class="fas fa-plus"></i> Review</button>`;
                            }
                        }
                    },
                    {
                        title: "Amz",
                        field: "buyer_link",
                        hozAlign: "center",
                        headerTooltip: "Our product link to amazon",
                        formatter: function(cell) {
                            const buyerLink = (cell.getRow().getData().buyer_link || "").trim();

                            if (!buyerLink) {
                                return '<span class="text-muted">-</span>';
                            }

                            return `<div style="display:flex;align-items:center;justify-content:center;">
                                <a href="${escapeHtmlAttr(buyerLink)}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-outline-primary py-0 px-2"
                                    title="Our product link to amazon" aria-label="Our product link to amazon">
                                    <i class="mdi mdi-link"></i>
                                </a>
                            </div>`;
                        },
                    },
                    {
                        title: "C link",
                        field: "Clink",
                        headerTooltip: "Comparison link",
                        formatter: linkFormatter,
                        editor: "input",
                        hozAlign: "center",
                        cellEdited: function(cell) {
                            saveLinkUpdate(cell, cell.getValue());
                        }
                    },
                    {
                        title: "RFQ",
                        field: "RFQ Form Link",
                        headerTooltip: "Request for quote form (linked from RFQ Form list)",
                        formatter: rfqFormLinkFormatter,
                        editor: "input",  
                        hozAlign: "center",
                        cellClick: function(e, cell){
                            handleRfqCopyClick(e);
                        },
                        cellEdited: function(cell){
                            saveLinkUpdate(cell, cell.getValue());
                        }
                    },
                    {
                        title: "Adv date",
                        field: "Adv date",
                        visible: false,
                        formatter: function (cell) {
                            const value = cell.getValue() || "";
                            const rowData = cell.getRow().getData();

                            const html = `
                                <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                    <input type="date" class="form-control form-control-sm adv_date_input" value="${value}" style="width:100%; min-width:140px; max-width:145px;">
                                </div>
                            `;

                            setTimeout(() => {
                                const input = cell.getElement().querySelector(".adv_date_input");
                                if (input) {
                                    input.addEventListener("change", function () {
                                        const newValue = this.value;
                                        saveLinkUpdate(cell, newValue);
                                    });
                                }
                            }, 10);

                            return html;
                        }
                    },
                    {
                        title: "Stage",
                        field: "stage",
                        width: 72,
                        minWidth: 60,
                        hozAlign: "center",
                        accessor: row => row?.["stage"] ?? null,
                        headerSort: false,
                        formatter: function(cell, formatterParams, onRendered) {
                            const value = cell.getValue() ?? '';
                            const rowData = cell.getRow().getData();

                            const STAGE_META = {
                                "":                 { label: "Select",   color: "#adb5bd" },
                                "appr_req":         { label: "Appr. Req", color: "#6f42c1" },
                                "mip":              { label: "MIP",       color: "#0d6efd" },
                                "r2s":              { label: "R2S",       color: "#198754" },
                                "transit":          { label: "Transit",   color: "#fd7e14" },
                                "all_good":         { label: "😊 All Good", color: "#20c997" },
                                "to_order_analysis":{ label: "2 Order",   color: "#ffc107" },
                            };
                            const meta = STAGE_META[value] || STAGE_META[""];

                            const html = `
                                <div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" aria-label="${escapeHtmlAttr(meta.label)}">
                                    <span class="nrp-status-dot" style="background-color:${meta.color};" aria-hidden="true"></span>
                                    <span class="purchase-hover-tip-badge">${escapeHtmlAttr(meta.label)}</span>
                                    <select class="form-select form-select-sm editable-select position-absolute top-0 start-0 w-100 h-100"
                                        data-type="Stage"
                                        data-sku='${rowData["SKU"]}'
                                        data-parent='${rowData["Parent"]}'
                                        style="opacity:0;cursor:pointer;"
                                        aria-label="Stage: ${escapeHtmlAttr(meta.label)}">
                                        <option value="">Select</option>
                                        <option value="appr_req" ${value === 'appr_req' ? 'selected' : ''}>Appr. Req</option>
                                        <option value="mip" ${value === 'mip' ? 'selected' : ''}>MIP</option>
                                        <option value="r2s" ${value === 'r2s' ? 'selected' : ''}>R2S</option>
                                        <option value="transit" ${value === 'transit' ? 'selected' : ''}>Transit</option>
                                        <option value="all_good" ${value === 'all_good' ? 'selected' : ''}>😊 All Good</option>
                                        <option value="to_order_analysis" ${value === 'to_order_analysis' ? 'selected' : ''}>2 Order</option>
                                    </select>
                                </div>`;
                            if (onRendered) onRendered(function() {
                                const el = cell.getElement().querySelector('select');
                                if (el) el.value = value;
                            });
                            return html;
                        }
                    },
                    {
                        title: "NRP",
                        field: "nr",
                        headerTooltip: "Required / not required / later",
                        minWidth: 52,
                        hozAlign: "center",
                        accessor: row => {
                            const val = row?.["nr"];
                            // Return null/undefined as empty string, but preserve actual values
                            if (val === null || val === undefined) return '';
                            // Convert to string and normalize
                            const strVal = String(val);
                            const normalized = strVal.trim().toUpperCase();
                            // Return normalized value (even if empty string)
                            return normalized;
                        },
                        headerSort: false,
                        formatter: function(cell, formatterParams, onRendered) {
                            const rowData = cell.getRow().getData();
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') value = rowData["nr"];
                            if (value === null || value === undefined) value = ''; else value = String(value).trim().toUpperCase();
                            if (!value || value === '') value = 'REQ';
                            if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') value = 'REQ';
                            const sku = rowData["SKU"] || '', parent = rowData["Parent"] || '';
                            let dotColor = '#22c55e';
                            let tip = 'REQ';
                            if (value === 'NR') {
                                dotColor = '#dc3545';
                                tip = '2BDC';
                            } else if (value === 'LATER') {
                                dotColor = '#facc15';
                                tip = 'LATER';
                            }
                            const tipLabel = `${tip} (click to change)`;
                            const tipEsc = escapeHtmlAttr(tipLabel);
                            const html = `
                                <div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" aria-label="${tipEsc}">
                                    <span class="nrp-status-dot" style="background-color:${dotColor};" aria-hidden="true"></span>
                                    <span class="purchase-hover-tip-badge">${escapeHtmlAttr(tip)}</span>
                                    <select class="form-select form-select-sm editable-select nrp-nr-select position-absolute top-0 start-0 w-100 h-100"
                                        data-type="NR" data-sku='${sku}' data-parent='${parent}'
                                        aria-label="NRP: ${tip}">
                                        <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                        <option value="NR" ${value === 'NR' ? 'selected' : ''}>2BDC</option>
                                        <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                    </select>
                                </div>`;
                            if (onRendered) onRendered(function() {
                                const el = cell.getElement().querySelector('select');
                                if (el) el.value = value;
                            });
                            return html;
                        }
                    },
                    {
                        // Action column — opens the shared bulk-edit modal.
                        // Targets = {clicked row} ∪ {checkbox-selected rows},
                        // so a single click works for one row OR for many.
                        title: "Action",
                        field: "_action",
                        hozAlign: "center",
                        headerSort: false,
                        download: false,
                        width: 72,
                        minWidth: 64,
                        frozen: false,
                        headerTooltip: "Edit Supplier / Executive / Stage for this row (or all selected rows)",
                        formatter: function (cell) {
                            const row = cell.getRow().getData();
                            const sku = (row.SKU || '').toString().replace(/"/g, '&quot;');
                            return '<button type="button" class="btn btn-sm btn-outline-primary toa-row-action-btn" ' +
                                'data-sku="' + sku + '" title="Bulk-edit this row (or all selected rows)">' +
                                '<i class="mdi mdi-square-edit-outline"></i></button>';
                        },
                        cellClick: function (e) { e.stopPropagation(); }
                    },
                ],
                ajaxResponse: (url, params, response) => {
                    let data = response.data;

                    // Backend already returns the Forecast yellow cohort only; do not drop MIP (or other) stage
                    // rows here — Forecast star count (e.g. 146) can include stage=mip when pipeline qty is still empty.
                    let filtered = data.filter(item => {
                        let isParent = item.SKU && item.SKU.startsWith("PARENT");
                        return !isParent;
                    });

                    uniqueSuppliers = [...new Set(filtered.map(item => item.Supplier))].filter(Boolean);

                    return filtered;
                },
            });

            function isToaSelectableRow(row) {
                const sku = String((row.getData && row.getData().SKU) || '').trim();
                return sku && !sku.startsWith('PARENT');
            }

            let toaBulkSelectionCache = [];

            function dedupeToaRows(rows) {
                const seen = new Set();
                return (rows || []).filter(function (r) {
                    if (!isToaSelectableRow(r)) return false;
                    const sku = String(r.getData().SKU || '').trim();
                    if (!sku || seen.has(sku)) return false;
                    seen.add(sku);
                    return true;
                });
            }

            /** Selected checkbox rows; keeps multi-select when focus moves to a dropdown. */
            function getToaBulkTargetRows(primarySku, extraRows) {
                const live = dedupeToaRows(table.getSelectedRows());
                const cached = dedupeToaRows(toaBulkSelectionCache);
                let selected = cached.length > live.length ? cached : live;
                if (selected.length > 0) return selected;

                const rows = [];
                const seen = new Set();
                (extraRows || []).forEach(function (r) {
                    if (!r || !isToaSelectableRow(r)) return;
                    const sku = String(r.getData().SKU || '').trim();
                    if (seen.has(sku)) return;
                    seen.add(sku);
                    rows.push(r);
                });
                if (rows.length) return rows;
                if (primarySku) {
                    const row = table.searchRows('SKU', '=', primarySku)[0];
                    if (row && isToaSelectableRow(row)) return [row];
                }
                return [];
            }

            function postStageUpdate(sku, parent, value) {
                return new Promise(function (resolve, reject) {
                    $.post('/update-forecast-data', {
                        sku: sku,
                        parent: parent || '',
                        column: 'Stage',
                        value: value,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }).done(function (res) {
                        if (res && res.success) resolve(res);
                        else reject(new Error((res && res.message) ? res.message : 'Save failed'));
                    }).fail(function () { reject(new Error('Network error')); });
                });
            }

            async function applyStageToRow(row, stageVal) {
                const d = row.getData();
                const sku = String(d.SKU || '').trim();
                const parent = String(d.Parent || '').trim();
                const moq = parseInt(d.approved_qty, 10) || 0;
                if (!moq) return { ok: false, skippedMoq: true, sku: sku };
                try {
                    await postStageUpdate(sku, parent, stageVal);
                    if (stageVal === 'mip') {
                        const insertRes = await fetch('/mfrg-progresses/insert', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                parent: d.Parent || '',
                                sku: d.SKU || '',
                                order_qty: d.approved_qty || '',
                                supplier: d.Supplier || '',
                                adv_date: d['Adv date'] || ''
                            })
                        }).then(function (r) { return r.json(); });
                        if (insertRes.success) {
                            row.delete();
                            return { ok: true, deleted: true, sku: sku };
                        }
                        row.update({ stage: stageVal }, true);
                        return { ok: false, error: insertRes.message || 'insert failed', sku: sku };
                    }
                    row.update({ stage: stageVal }, true);
                    return { ok: true, sku: sku };
                } catch (e) {
                    return { ok: false, error: e.message || 'error', sku: sku };
                }
            }

            async function applyStageToRows(rows, stageVal) {
                const skippedMoq = [];
                const failed = [];
                let ok = 0;
                for (let i = 0; i < rows.length; i++) {
                    const res = await applyStageToRow(rows[i], stageVal);
                    if (res.skippedMoq) skippedMoq.push(res.sku);
                    else if (res.ok) ok++;
                    else failed.push(res.sku + (res.error ? ': ' + res.error : ''));
                }
                return { ok: ok, failed: failed, skippedMoq: skippedMoq };
            }

            function applySupplierToRows(rows, supplierName) {
                const skus = rows.map(function (r) { return (r.getData().SKU || '').trim().toUpperCase(); })
                    .filter(function (s) { return s && !s.startsWith('PARENT'); });
                if (skus.length === 0) {
                    return Promise.resolve({ ok: 0, skipped: rows.length });
                }
                return fetch('{{ route('to.order.analysis.bulk.supplier') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ skus: skus, supplier_name: supplierName })
                }).then(function (res) { return res.json(); }).then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.message) ? res.message : 'Supplier update failed');
                    }
                    rows.forEach(function (row) {
                        row.update({ Supplier: supplierName }, true);
                    });
                    return { ok: skus.length, skipped: rows.length - skus.length, message: res.message };
                });
            }

            function applyExecutiveToRows(rows, execValue) {
                const skus = rows.map(function (r) { return (r.getData().SKU || '').trim().toUpperCase(); })
                    .filter(function (s) { return s && !s.startsWith('PARENT'); });
                if (skus.length === 0) {
                    return Promise.resolve({ ok: 0, skipped: rows.length });
                }
                const execName = execValue === '__unassigned__' ? '' : execValue;
                return fetch('/to-order-analysis/bulk-update-exec', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ skus: skus, exec_name: execName })
                }).then(function (res) { return res.json(); }).then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.message) ? res.message : 'Executive update failed');
                    }
                    rows.forEach(function (row) {
                        row.update({ Exec: execName || '' }, true);
                    });
                    return { ok: skus.length, skipped: rows.length - skus.length, message: res.message };
                });
            }

            // Executive column — save on change + update badge colour live (bulk when rows selected)
            const TOA_EXEC_COLORS = {
                "Atin":   { bg: "#3b82f6", text: "#fff" },
                "Jack":   { bg: "#10b981", text: "#fff" },
                "Nitish": { bg: "#8b5cf6", text: "#fff" },
                "Ajay":   { bg: "#f59e0b", text: "#fff" },
                "Candy":  { bg: "#ec4899", text: "#fff" },
                "Sruti":  { bg: "#14b8a6", text: "#fff" },
            };

            document.getElementById("toOrderAnalysis-table").addEventListener("change", async function(e) {
                const sel = e.target.closest(".toa-exec-select");
                if (!sel) return;
                const newVal = sel.value;
                const sku   = sel.dataset.sku;
                const targets = getToaBulkTargetRows(sku);
                if (!targets.length) return;

                targets.forEach(function (row) {
                    const execSel = row.getElement().querySelector('.toa-exec-select');
                    if (execSel) {
                        execSel.value = newVal;
                        const c = TOA_EXEC_COLORS[newVal] || { bg: "#e5e7eb", text: "#6b7280" };
                        execSel.style.background = c.bg;
                        execSel.style.color = c.text;
                    }
                    row.update({ Exec: newVal || '' }, true);
                });

                try {
                    await applyExecutiveToRows(targets, newVal || '__unassigned__');
                } catch (err) {
                    alert("Could not save executive: " + (err.message || 'Save failed'));
                    table.replaceData();
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                toaBulkSelectionCache = dedupeToaRows(rows || table.getSelectedRows());
                if (data.length > 0) {
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            deleteWithSelect();
            initActionColumn();

            // -----------------------------------------------------------------
            // Action column — replaces the previous bulk-actions toolbar.
            //
            // Per-row Action button opens a single shared modal where the user
            // can pick a Supplier, an Executive and/or a Stage. Targets =
            // (clicked row) ∪ (checkbox-selected rows), deduped. Only fields
            // the user actually fills are applied; blank fields are skipped.
            //
            // Backed by the same endpoints the previous bulk toolbar used:
            //   • POST {{ route('to.order.analysis.bulk.supplier') }}
            //   • POST /to-order-analysis/bulk-update-exec
            //   • POST /update-forecast-data  (and /mfrg-progresses/insert for MIP)
            // -----------------------------------------------------------------
            function initActionColumn() {
                const modalEl = document.getElementById('toaActionModal');
                if (!modalEl) return;
                const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const $supplierSel = $('#toa-action-supplier');
                const $execSel     = $('#toa-action-executive');
                const $stageSel    = $('#toa-action-stage');
                const $countEl     = $('#toa-action-target-count');
                const $skusEl      = $('#toa-action-target-skus');
                const applyBtn     = document.getElementById('toa-action-apply-btn');

                // Targets state for the currently-open modal session.
                let currentRows = [];

                // Opening the modal from the per-row Action button.
                $(document).off('click.toaAction').on('click.toaAction', '.toa-row-action-btn', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const clickedRow = table.getRow($(this).closest('.tabulator-row')[0]);
                    if (!clickedRow) return;

                    currentRows = getToaBulkTargetRows(
                        String(clickedRow.getData().SKU || '').trim(),
                        [clickedRow]
                    );

                    // Reset the form between opens so leftover values don't bleed
                    // into the next session.
                    $supplierSel.val('');
                    $execSel.val('');
                    $stageSel.val('');

                    const skuList = currentRows.map(function (r) { return r.getData().SKU; })
                                               .filter(Boolean);
                    $countEl.text(currentRows.length);
                    $skusEl.text(skuList.length <= 6
                        ? skuList.join(', ')
                        : skuList.slice(0, 6).join(', ') + ' (+' + (skuList.length - 6) + ' more)');

                    bsModal.show();
                });

                $(applyBtn).off('click.toaActionApply').on('click.toaActionApply', async function () {
                    if (!currentRows.length) return;

                    const supplierVal = ($supplierSel.val() || '').trim();
                    const execVal     = ($execSel.val() || '').trim();
                    const stageVal    = ($stageSel.val() || '').trim();

                    if (!supplierVal && !execVal && !stageVal) {
                        alert('Pick at least one field to apply (Supplier, Executive, or Stage).');
                        return;
                    }

                    const summary = [];
                    const origHtml = applyBtn.innerHTML;
                    applyBtn.disabled = true;
                    applyBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Applying...';

                    try {
                        if (supplierVal) {
                            const res = await applySupplierToRows(currentRows, supplierVal);
                            summary.push('Supplier → ' + supplierVal + ': ' + res.ok + ' row(s)' +
                                (res.skipped ? ' (' + res.skipped + ' parent/empty skipped)' : ''));
                        }
                        if (execVal) {
                            const res = await applyExecutiveToRows(currentRows, execVal);
                            const label = execVal === '__unassigned__' ? 'Unassigned' : execVal;
                            summary.push('Executive → ' + label + ': ' + res.ok + ' row(s)' +
                                (res.skipped ? ' (' + res.skipped + ' parent/empty skipped)' : ''));
                        }
                        if (stageVal) {
                            const res = await applyStageToRows(currentRows, stageVal);
                            let line = 'Stage → ' + stageVal + ': ' + res.ok + ' row(s)';
                            if (res.skippedMoq.length) line += ' • skipped (MOQ=0): ' + res.skippedMoq.join(', ');
                            if (res.failed.length)     line += ' • errors: ' + res.failed.join('; ');
                            summary.push(line);
                        }

                        bsModal.hide();
                        table.deselectRow();
                        toaBulkSelectionCache = [];
                        alert('Done.\n\n' + summary.join('\n'));
                    } catch (err) {
                        alert('Error: ' + (err.message || 'Something went wrong'));
                    } finally {
                        applyBtn.disabled = false;
                        applyBtn.innerHTML = origHtml;
                    }
                });
            }

            function deleteWithSelect() {
                const deleteBtn = document.getElementById('delete-selected-btn');

                table.on("rowSelectionChanged", function(data, rows) {
                    deleteBtn.disabled = data.length === 0;
                });

                deleteBtn.addEventListener('click', function() {
                    const selectedRows = table.getSelectedRows();

                    if (selectedRows.length === 0) {
                        alert("Please select rows to delete.");
                        return;
                    }

                    if (!confirm(`Are you sure you want to delete ${selectedRows.length} row(s)?`)) return;

                    const idsToDelete = selectedRows.map(row => row.getData().id);

                    fetch('/to-order-analysis/delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                ids: idsToDelete
                            }),
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (response.success) {
                                selectedRows.forEach(row => row.delete());
                            } else {
                                alert('Deletion failed');
                            }
                        })
                        .catch(() => alert('Error deleting rows'));
                });
            }

            function linkFormatter(cell) {
                let url = cell.getValue() || "";
                if (url && url.trim() !== "") {
                    return `
                        <div style="display:flex;align-items:center;justify-content:center;">
                            <a href="${url}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary"
                                title="Open link" aria-label="Open link">
                                <i class="mdi mdi-link"></i>
                            </a>
                        </div>
                    `;
                }
            }

            // RFQ Form column: show form link(s) coming from the RFQ Form list (linked_skus),
            // plus any manually entered link as a fallback.
            function rfqFormLinkFormatter(cell) {
                const rowData = cell.getRow().getData();
                let forms = rowData.rfq_linked_forms || [];
                if (typeof forms === "string") {
                    try { forms = JSON.parse(forms) || []; } catch (e) { forms = []; }
                }
                if (!Array.isArray(forms)) forms = [];

                const manual = cell.getValue() || "";
                let html = '<div style="display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap;">';

                forms.forEach(f => {
                    if (!f || !f.slug) return;
                    const base = window.location.origin + '/api/rfq-form/' + f.slug;
                    const basicsUrl = base + '?part=basics';
                    const detailsUrl = base + '?part=details';
                    const name = (f.name || 'RFQ Form').replace(/"/g, '&quot;');
                    html += `<span style="display:inline-flex;align-items:center;gap:2px;margin:1px 3px;">
                                <a href="${basicsUrl}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-primary" title="Basics - ${name}" aria-label="Basics - ${name}"
                                    style="padding:2px 6px;">
                                    <i class="mdi mdi-file-document-outline"></i> B
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary rfq-copy-btn"
                                    data-copy="${basicsUrl}" title="Copy Basics link" aria-label="Copy Basics link"
                                    style="padding:2px 5px;">
                                    <i class="mdi mdi-content-copy"></i>
                                </button>
                                <a href="${detailsUrl}" target="_blank" rel="noopener noreferrer"
                                    class="btn btn-sm btn-success" title="Details - ${name}" aria-label="Details - ${name}"
                                    style="padding:2px 6px;">
                                    <i class="mdi mdi-file-document-outline"></i> D
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-success rfq-copy-btn"
                                    data-copy="${detailsUrl}" title="Copy Details link" aria-label="Copy Details link"
                                    style="padding:2px 5px;">
                                    <i class="mdi mdi-content-copy"></i>
                                </button>
                            </span>`;
                });

                if (manual && manual.trim() !== "") {
                    html += `<a href="${manual}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary"
                                title="Open link" aria-label="Open link">
                                <i class="mdi mdi-link"></i>
                            </a>`;
                }

                html += '</div>';
                return html;
            }

            // Copy the RFQ link to clipboard when a copy button inside the cell is clicked
            function handleRfqCopyClick(e) {
                const btn = e.target.closest('.rfq-copy-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                const url = btn.getAttribute('data-copy') || '';
                if (!url) return;
                navigator.clipboard.writeText(url).then(() => {
                    const icon = btn.querySelector('i');
                    const prev = icon ? icon.className : '';
                    if (icon) icon.className = 'mdi mdi-check';
                    setTimeout(() => { if (icon) icon.className = prev; }, 1200);
                }).catch(() => {});
            }

            // RFQ Report column: show report link(s) coming from the RFQ Form list (linked_skus),
            // plus any manually entered link as a fallback.
            function rfqReportLinkFormatter(cell) {
                const rowData = cell.getRow().getData();
                let forms = rowData.rfq_linked_forms || [];
                if (typeof forms === "string") {
                    try { forms = JSON.parse(forms) || []; } catch (e) { forms = []; }
                }
                if (!Array.isArray(forms)) forms = [];

                const manual = cell.getValue() || "";
                let html = '<div style="display:flex;align-items:center;justify-content:center;gap:4px;flex-wrap:wrap;">';

                forms.forEach(f => {
                    if (!f || !f.slug) return;
                    const url = window.location.origin + '/rfq-form/reports/' + f.slug;
                    const name = (f.name || 'RFQ Report').replace(/"/g, '&quot;');
                    html += `<a href="${url}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-info"
                                title="${name}" aria-label="${name}">
                                <i class="mdi mdi-chart-box-outline"></i>
                            </a>`;
                });

                if (manual && manual.trim() !== "") {
                    html += `<a href="${manual}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary"
                                title="Open link" aria-label="Open link">
                                <i class="mdi mdi-link"></i>
                            </a>`;
                }

                html += '</div>';
                return html;
            }

            // edit field updated
            function saveLinkUpdate(cell, value) {
                const rowData = cell.getRow().getData();
                let sku = rowData.SKU;
                let column = cell.getColumn().getField();
                const payload = { sku: sku, column: column, value: value };
                if (column === "Reviews") {
                    payload.parent = rowData.Parent || "";
                }

                fetch('/update-link', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(res => {
                    if (!res.success) {
                        alert("Error: " + res.message);
                        return;
                    }
                    if (column === "Reviews") {
                        const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0];
                        if (tbl) {
                            const row = tbl.searchRows("SKU", "=", sku)[0];
                            if (row) {
                                row.update({ Reviews: value }, true);
                            }
                        }
                    }
                })
                .catch(err => console.error(err));
            }

            // Reusable AJAX call for forecast data updates
            function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
                $.post('/update-forecast-data', {
                    ...data,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(res => {
                    if (res.success) {
                        console.log('Saved:', res.message);
                        onSuccess();
                    } else {
                        console.warn('Not saved:', res.message);
                        onFail();
                    }
                }).fail(err => {
                    console.error('AJAX failed:', err);
                    alert('Error saving data.');
                    onFail();
                });
            }

            // Keep row selection when opening inline Stage / NRP / Supplier dropdowns.
            $(document).off('mousedown.toaBulkSelect click.toaBulkSelect', '.editable-select, .toa-exec-select')
                .on('mousedown.toaBulkSelect click.toaBulkSelect', '.editable-select, .toa-exec-select', function (e) {
                    e.stopPropagation();
                });

            // Handle editable select fields (Stage, NRP, Supplier) — applies to all checkbox-selected rows
            $(document).off('change', '.editable-select').on('change', '.editable-select', async function() {
                const $el = $(this);
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                const column = $el.data('column');
                const field = $el.data('type');
                const value = $el.val().trim();
                const targets = getToaBulkTargetRows(sku);

                if (!targets.length) return;

                if (field === 'Stage' || field === 'NR') {
                    if (field === 'Stage') {
                        const skippedMoq = [];
                        targets.forEach(function (row) {
                            const moq = parseInt(row.getData().approved_qty, 10) || 0;
                            if (!moq) skippedMoq.push(row.getData().SKU);
                        });
                        if (skippedMoq.length === targets.length) {
                            alert('MOQ cannot be empty or zero.');
                            $el.val('');
                            return;
                        }

                        targets.forEach(function (row) {
                            const stageSel = row.getElement().querySelector('.editable-select[data-type="Stage"]');
                            if (stageSel) stageSel.value = value;
                        });

                        const failed = [];
                        let ok = 0;
                        for (let i = 0; i < targets.length; i++) {
                            const res = await applyStageToRow(targets[i], value);
                            if (res.skippedMoq) continue;
                            if (res.ok) ok++;
                            else failed.push(res.sku + (res.error ? ': ' + res.error : ''));
                        }
                        if (skippedMoq.length) {
                            alert('Skipped (MOQ=0): ' + skippedMoq.join(', '));
                        }
                        if (failed.length) {
                            alert('Some rows failed: ' + failed.join('; '));
                        }
                        return;
                    }

                    // NR / NRP bulk
                    targets.forEach(function (row) {
                        const nrSel = row.getElement().querySelector('.editable-select[data-type="NR"]');
                        if (nrSel) nrSel.value = value;
                    });

                    let pending = targets.length;
                    let hadError = false;
                    targets.forEach(function (row) {
                        const d = row.getData();
                        updateForecastField({
                            sku: d.SKU,
                            parent: d.Parent || parent || '',
                            column: 'NR',
                            value: value
                        }, function () {
                            row.update({ nr: value }, true);
                            row.reformat();
                            pending--;
                        }, function () {
                            hadError = true;
                            pending--;
                        });
                    });
                    if (hadError) alert('Failed to save NRP on one or more rows.');
                    return;
                }

                if (column === 'nrl') {
                    targets.forEach(function (row) {
                        const d = row.getData();
                        updateForecastField({
                            sku: d.SKU,
                            parent: d.Parent || parent || '',
                            column: 'NR',
                            value: value
                        }, function () {
                            row.update({ nr: value }, true);
                            row.reformat();
                        }, function () {
                            alert('Failed to save NRP.');
                        });
                    });
                    return;
                }

                if (column === 'Supplier') {
                    targets.forEach(function (row) {
                        const supSel = row.getElement().querySelector('.editable-select[data-column="Supplier"]');
                        if (supSel) supSel.value = value;
                    });
                    try {
                        await applySupplierToRows(targets, value);
                    } catch (error) {
                        console.error('Network error:', error);
                        alert('Error saving supplier: ' + (error.message || 'Please try again.'));
                        table.replaceData();
                    }
                    return;
                }

                // Other columns (RFQ links, Adv date, etc.) — single row only
                const payload = { sku, column, value };
                if (column === 'Supplier') {
                    let pSave = $el.data('parent') != null ? String($el.data('parent')).trim() : '';
                    if (!pSave) {
                        const tblP = Tabulator.findTable("#toOrderAnalysis-table")[0];
                        const rP = tblP ? tblP.searchRows("SKU", "=", sku)[0] : null;
                        if (rP) {
                            pSave = (rP.getData().Parent || '').trim();
                        }
                    }
                    payload.parent = pSave;
                }
                fetch('/update-link', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => {
                    if (!res.ok) {
                        return res.json().then(data => { throw new Error(data.message || 'Request failed'); }).catch(() => { throw new Error('Request failed'); });
                    }
                    return res.json();
                })
                .then(result => {
                    if (!result.success) {
                        alert('Update failed: ' + (result.message || 'Unknown error'));
                        return;
                    }
                    const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0];
                    if (tbl && column) {
                        const row = tbl.searchRows("SKU", "=", sku)[0];
                        if (row) {
                            const fieldMap = { 'Supplier': 'Supplier', 'Adv date': 'Adv date', 'RFQ Form Link': 'RFQ Form Link', 'Rfq Report Link': 'Rfq Report Link', 'Reviews': 'Reviews' };
                            const dataField = fieldMap[column] || column;
                            row.update({ [dataField]: value }, true);
                        }
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    alert('Error saving: ' + (error.message || 'Please try again.'));
                });
            });

            let supplierKeys = [];
            let currentIndex = 0;
            let navigationEnabled = false;

            // Update unique suppliers from table
            function updateSupplierKeys() {
                const tableData = table.getData();
                supplierKeys = [...new Set(tableData.map(r => r.Supplier).filter(Boolean))];
            }

            // Get DOA color
            function getRowColor(row) {
                const value = row["Date of Appr"];
                if (!value) return "";
                const doa = new Date(value);
                const today = new Date();
                const diffDays = Math.floor((today - doa) / (1000*60*60*24));
                if(diffDays>=14) return "red";
                if(diffDays>=7) return "yellow";
                return "green";
            }

            // Update counts & totals based on filtered rows
            function updateCounts() {
                const tableData = table.getData("active"); 
                let green=0, yellow=0, red=0;
                let totalApproved=0, pendingItems=0, totalCBM=0;

                tableData.forEach(row => {
                    const color = getRowColor(row);
                    if(color === "green") green++;
                    else if(color === "yellow") yellow++;
                    else if(color === "red") red++;

                    const qty = parseFloat(row["approved_qty"]) || 0;
                    totalApproved += qty;
                    // Every visible row on this page is an item still pending to be ordered,
                    // regardless of whether an MOQ has been set yet.
                    pendingItems++;

                    const cbm = parseFloat(row["total_cbm"]) || 0;
                    totalCBM += cbm;
                });

                // Update the display counts immediately
                document.getElementById("greenCount").innerText = `(${green})`;
                document.getElementById("yellowCount").innerText = `(${yellow})`;
                document.getElementById("redCount").innerText = `(${red})`;
                document.getElementById("pendingItemsCount").innerText = pendingItems.toString();
                document.getElementById("totalApprovedQty").innerText = totalApproved.toString();
                document.getElementById("totalCBM").innerText = totalCBM.toFixed(0);
            }

            // Apply all filters + optional supplier override (from nav play uses supplierOverride)
            function applyFilters(supplierOverride = null) {
                const type = document.getElementById("row-data-type").value;
                const pending = document.getElementById("row-data-pending-status").value;
                const stage = document.getElementById("stage-filter").value.toLowerCase().trim();
                const moqFilter = (document.getElementById("moq-filter") && document.getElementById("moq-filter").value) || "";
                const searchText = document.getElementById("search-input").value.trim().toLowerCase();
                const supplierFilterEl = document.getElementById("supplier-filter");
                const supplierFilter = supplierOverride != null ? supplierOverride : (supplierFilterEl ? supplierFilterEl.value.trim() : '');
                const categoryFilterEl = document.getElementById("category-filter");
                const categoryFilter = categoryFilterEl ? categoryFilterEl.value.trim() : '';
                const executiveFilterEl = document.getElementById("executive-filter");
                const executiveFilter = executiveFilterEl ? executiveFilterEl.value.trim() : '';
                const skuFilterEl = document.getElementById("sku-filter");
                const skuFilter = skuFilterEl ? skuFilterEl.value.trim().toLowerCase() : '';

                table.clearFilter(true);

                table.setFilter(row => {
                    let keep = true;

                    if (type === 'parent') keep = keep && row.is_parent;
                    else if (type === 'sku') keep = keep && !row.SKU.startsWith("PARENT");

                    if (stage) keep = keep && (row.stage || '').toLowerCase() === stage;

                    const moqNum = parseFloat(row.approved_qty);
                    const moqVal = Number.isFinite(moqNum) ? moqNum : 0;
                    if (moqFilter === "zero") keep = keep && moqVal === 0;
                    else if (moqFilter === "gt0") keep = keep && moqVal > 0;

                    if (pending) keep = keep && getRowColor(row) === pending;
                    if (supplierFilter) {
                        if (supplierFilter === '__blank__') {
                            keep = keep && (row.Supplier || '').trim() === '';
                        } else {
                            keep = keep && (row.Supplier || '').trim().toLowerCase() === supplierFilter.toLowerCase();
                        }
                    }
                    if (categoryFilter) {
                        if (categoryFilter === '__blank__') {
                            keep = keep && (row.Category || '').trim() === '';
                        } else {
                            keep = keep && (row.Category || '').trim().toLowerCase() === categoryFilter.toLowerCase();
                        }
                    }
                    if (executiveFilter) {
                        const exec = (row.Exec || '').trim();
                        if (executiveFilter === '__unassigned__') {
                            keep = keep && exec === '';
                        } else {
                            keep = keep && exec.toLowerCase() === executiveFilter.toLowerCase();
                        }
                    }
                    if (skuFilter) {
                        keep = keep && (row.SKU || '').toString().toLowerCase().includes(skuFilter);
                    }
                    if (searchText) keep = keep && Object.values(row).some(val => val && val.toString().toLowerCase().includes(searchText));

                    return keep;
                });

                setTimeout(updateCounts, 0);
            }

            function enableNavigation() {
                navigationEnabled = true;
                document.getElementById("play-auto").style.display = "none";
                document.getElementById("play-pause").style.display = "inline-block";
            }

            function disableNavigation() {
                navigationEnabled = false;
                document.getElementById("play-auto").style.display = "inline-block";
                document.getElementById("play-pause").style.display = "none";
                applyFilters();
            }

            function nextSupplier() {
                updateSupplierKeys();
                if(supplierKeys.length === 0) return;

                if(currentIndex >= supplierKeys.length) currentIndex = 0;
                applyFilters(supplierKeys[currentIndex]);
                currentIndex++;
            }

            function previousSupplier() {
                updateSupplierKeys();
                if(supplierKeys.length === 0) return;

                currentIndex--;
                if(currentIndex < 0) currentIndex = supplierKeys.length - 1;
                applyFilters(supplierKeys[currentIndex]);
            }

            function debounce(func, wait=300) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                }
            }

            // Event Listeners
            document.getElementById("play-auto").addEventListener("click", enableNavigation);
            document.getElementById("play-pause").addEventListener("click", disableNavigation);
            document.getElementById("play-forward").addEventListener("click", nextSupplier);
            document.getElementById("play-backward").addEventListener("click", previousSupplier);

            // Filter change events
            document.getElementById("row-data-type").addEventListener("change", () => applyFilters());
            document.getElementById("row-data-pending-status").addEventListener("change", () => applyFilters());
            document.getElementById("stage-filter").addEventListener("change", () => applyFilters());
            document.getElementById("moq-filter").addEventListener("change", () => applyFilters());
            document.getElementById("supplier-filter").addEventListener("change", () => applyFilters());
            document.getElementById("category-filter").addEventListener("change", () => applyFilters());
            (function () {
                const el = document.getElementById("executive-filter");
                if (el) el.addEventListener("change", () => applyFilters());
            })();
            (function setupSkuFilter() {
                const input = document.getElementById("sku-filter");
                const clearBtn = document.getElementById("sku-filter-clear");
                if (!input) return;
                const syncClear = () => {
                    if (!clearBtn) return;
                    clearBtn.style.display = input.value.length > 0 ? 'inline-block' : 'none';
                };
                input.addEventListener("input", debounce(() => { syncClear(); applyFilters(); }, 200));
                if (clearBtn) {
                    clearBtn.addEventListener("click", () => {
                        input.value = '';
                        syncClear();
                        applyFilters();
                        input.focus();
                    });
                }
                syncClear();
            })();
            document.getElementById("search-input").addEventListener("input", debounce(() => applyFilters(), 300));

            (function setupSearchClear() {
                const input = document.getElementById("search-input");
                const clearBtn = document.getElementById("search-input-clear");
                if (!input || !clearBtn) return;
                const group = input.closest('.toa-table-search-group');
                const sync = () => {
                    if (!group) return;
                    if ((input.value || '').length > 0) group.classList.add('has-value');
                    else group.classList.remove('has-value');
                };
                input.addEventListener('input', sync);
                clearBtn.addEventListener('click', () => {
                    input.value = '';
                    sync();
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                });
                sync();
            })();

            document.getElementById("stage-filter").value = "";
            document.getElementById("moq-filter").value = "";

            // Table events
            table.on("dataLoaded", function() {
                updateSupplierKeys();
                currentIndex = 0;
                document.getElementById("stage-filter").value = "";
                document.getElementById("moq-filter").value = "";
                applyFilters();
            });

            table.on("dataFiltered", updateCounts);
            table.on("dataSorted", updateCounts);
            table.on("dataChanged", updateCounts);
            table.on("cellEdited", updateCounts);

            // ---- Column show/hide menu (shared for all users via channel_tabulator_column_settings) ----
            (function () {
                const TOA_COLUMN_CHANNEL = 'to_order_analysis';
                const TOA_COLUMN_URL = '/tabulator-column-visibility';
                const TOA_CSRF = '{{ csrf_token() }}';
                const colBtn = document.getElementById('toa-columns-btn');
                const colMenu = document.getElementById('toa-columns-menu');
                if (!colBtn || !colMenu) return;

                function escAttr(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
                function columnLabel(col) {
                    const def = col.getDefinition() || {};
                    const field = col.getField();
                    let label = def.title || field || '';
                    const tmp = document.createElement('div');
                    tmp.innerHTML = label;
                    label = (tmp.textContent || tmp.innerText || '').trim();
                    return label || field || '(column)';
                }
                function buildMenu() {
                    let rows = '';
                    table.getColumns().forEach(function (col) {
                        const field = col.getField();
                        if (!field) return;
                        const checked = col.isVisible() ? 'checked' : '';
                        rows += '<div class="form-check">' +
                            '<input class="form-check-input toa-col-toggle" type="checkbox" data-field="' + escAttr(field) + '" id="toacol-' + escAttr(field) + '" ' + checked + '>' +
                            '<label class="form-check-label small" for="toacol-' + escAttr(field) + '">' + escAttr(columnLabel(col)) + '</label>' +
                            '</div>';
                    });
                    colMenu.innerHTML =
                        '<div class="toa-columns-head"><span class="fw-semibold small">Toggle columns</span>' +
                        '<button type="button" class="btn btn-sm btn-link p-0 small" id="toa-columns-all">Show all</button></div>' + rows;
                }
                function saveVisibility() {
                    /* no-op — column visibility is not persisted across refresh */
                }
                function applyVisibility() {
                    /* no-op — always use default column layout on load */
                    return Promise.resolve();
                }

                colBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (colMenu.style.display === 'none' || colMenu.style.display === '') {
                        buildMenu();
                        colMenu.style.display = 'block';
                    } else {
                        colMenu.style.display = 'none';
                    }
                });
                colMenu.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (e.target && e.target.id === 'toa-columns-all') {
                        table.getColumns().forEach(function (col) { if (col.getField()) table.showColumn(col.getField()); });
                        table.redraw(true);
                        buildMenu();
                        saveVisibility();
                    }
                });
                colMenu.addEventListener('change', function (e) {
                    const t = e.target;
                    if (!t.classList.contains('toa-col-toggle')) return;
                    const field = t.dataset.field;
                    if (t.checked) table.showColumn(field); else table.hideColumn(field);
                    table.redraw(true);
                    saveVisibility();
                });
                document.addEventListener('click', function (e) {
                    if (colMenu.style.display === 'block' && !colMenu.contains(e.target) && e.target !== colBtn) {
                        colMenu.style.display = 'none';
                    }
                });
            })();

            // add and edit review
            let activeReviewRow = null;

            document.addEventListener("click", function(e){
                const btn = e.target.closest(".review-btn");
                if (!btn) return;

                const rowEl = btn.closest(".tabulator-row");
                const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0];
                if (!rowEl || !tbl) return;

                const row = tbl.getRow(rowEl);
                if (!row) return;

                activeReviewRow = row;
                const rowData = row.getData();
                const today = new Date().toISOString().split("T")[0];

                document.getElementById("review_parent").value = rowData.Parent || "";
                document.getElementById("review_sku").value = rowData.SKU || "";
                document.getElementById("review_supplier").value = rowData.Supplier || "";
                document.getElementById("positive_review").value = rowData.positive_review || "";
                document.getElementById("negative_review").value = rowData.negative_review || "";
                document.getElementById("improvement").value = rowData.improvement || "";
                document.getElementById("date_updated").value = rowData.date_updated || today;
                document.getElementById("clink").href = rowData.Clink || "#";

                const reviewModal = new bootstrap.Modal(document.getElementById("reviewModal"));
                reviewModal.show();
            });

            document.getElementById("reviewModal")?.addEventListener("hidden.bs.modal", function () {
                activeReviewRow = null;
            });

            document.addEventListener("click", function (e) {
                const moqBtn = e.target.closest(".toa-moq-btn");
                if (moqBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = moqBtn.dataset.sku || "";
                    const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0] || table;
                    const match = sku ? tbl.searchRows("SKU", "=", sku)[0] : null;
                    if (!match) return;
                    activeMoqRow = match;
                    openToaMoqModal(match.getData());
                    return;
                }

                const dotBtn = e.target.closest(".toa-data-dot-btn");
                if (!dotBtn) return;
                e.preventDefault();
                e.stopPropagation();

                const fieldKey = dotBtn.dataset.field;
                const sku = dotBtn.dataset.sku || "";
                if (!fieldKey) return;

                const tbl = Tabulator.findTable("#toOrderAnalysis-table")[0] || table;
                const match = sku ? tbl.searchRows("SKU", "=", sku)[0] : null;
                if (!match) return;

                activeToaDataRow = match;
                activeToaDataField = fieldKey;
                openToaDataModal(fieldKey, match.getData(), {
                    editable: dotBtn.dataset.editable !== "0",
                });
            });

            document.getElementById("toaDataModal")?.addEventListener("hidden.bs.modal", function () {
                activeToaDataRow = null;
                activeToaDataField = null;
            });

            document.getElementById("toaMoqModal")?.addEventListener("hidden.bs.modal", function () {
                activeMoqRow = null;
            });

            document.getElementById("toaMoqModalSaveBtn")?.addEventListener("click", async function () {
                if (!activeMoqRow) return;
                const inp = document.getElementById("toaMoqModalInput");
                const raw = (inp?.value || "").trim();
                if (raw === "" || isNaN(parseInt(raw, 10))) {
                    alert("Please enter a valid MOQ.");
                    inp?.focus();
                    return;
                }
                const newValue = String(Math.max(0, Math.min(99999, parseInt(raw, 10))));
                const saveBtn = document.getElementById("toaMoqModalSaveBtn");
                saveBtn.disabled = true;
                try {
                    await saveToaMoq(activeMoqRow.getData(), newValue);
                    activeMoqRow.update({ approved_qty: newValue });
                    activeMoqRow.reformat();
                    bootstrap.Modal.getInstance(document.getElementById("toaMoqModal"))?.hide();
                } catch (err) {
                    alert(err.message || "Save failed");
                } finally {
                    saveBtn.disabled = false;
                }
            });

            document.getElementById("toaMoqModalInput")?.addEventListener("keydown", function (e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    document.getElementById("toaMoqModalSaveBtn")?.click();
                }
            });

            document.getElementById("toaDataModalCopyBtn")?.addEventListener("click", function () {
                const ta = document.getElementById("toaDataModalTextarea");
                const inp = document.getElementById("toaDataModalInput");
                const ro = document.getElementById("toaDataModalReadonly");
                let text = "";
                if (ta && !ta.classList.contains("d-none")) {
                    text = ta.value;
                } else if (inp && !inp.classList.contains("d-none")) {
                    text = inp.value;
                } else if (ro && !ro.classList.contains("d-none")) {
                    text = ro.textContent;
                }
                text = (text || "").trim();
                if (!text) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).catch(function () {});
                } else {
                    const tmp = document.createElement("textarea");
                    tmp.value = text;
                    document.body.appendChild(tmp);
                    tmp.select();
                    try { document.execCommand("copy"); } catch (err) {}
                    document.body.removeChild(tmp);
                }
            });

            document.getElementById("toaDataModalSaveBtn")?.addEventListener("click", async function () {
                if (!activeToaDataRow || !activeToaDataField) return;
                const meta = TOA_DATA_FIELD_META[activeToaDataField] || {};
                const ta = document.getElementById("toaDataModalTextarea");
                const inp = document.getElementById("toaDataModalInput");
                let newText = "";
                if (meta.multiline) {
                    newText = (ta?.value || "").trim().slice(0, meta.maxLength || 2000);
                } else {
                    newText = (inp?.value || "").trim().slice(0, meta.maxLength || 100);
                }
                const rowData = activeToaDataRow.getData();
                const saveBtn = document.getElementById("toaDataModalSaveBtn");
                saveBtn.disabled = true;
                try {
                    const saved = await saveToaDataField(activeToaDataField, rowData, newText);
                    const upd = {};
                    upd[activeToaDataField] = saved;
                    activeToaDataRow.update(upd);
                    activeToaDataRow.reformat();
                    bootstrap.Modal.getInstance(document.getElementById("toaDataModal"))?.hide();
                } catch (err) {
                    alert(err.message || "Save failed");
                } finally {
                    saveBtn.disabled = false;
                }
            });

            $('#reviewForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);
                $.ajax({
                    url: '{{ route('save.to_order_review') }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    success: res => {
                        if (res.success) {
                            const saved = {
                                has_review: true,
                                positive_review: form.positive_review.value,
                                negative_review: form.negative_review.value,
                                improvement: form.improvement.value,
                                date_updated: form.date_updated.value,
                            };
                            if (activeReviewRow) {
                                activeReviewRow.update(saved);
                            } else {
                                const sku = form.sku.value;
                                const match = table.searchRows("SKU", "=", sku)[0];
                                if (match) {
                                    match.update(saved);
                                }
                            }
                            alert('Review saved successfully!');
                            bootstrap.Modal.getInstance(document.getElementById("reviewModal"))?.hide();
                        } else {
                            alert('Failed to save review: ' + (res.message || 'Unknown error'));
                        }
                    },
                    error: xhr => {
                        alert('Error saving review: ' + (xhr.responseJSON?.message ||
                            'Unknown error occurred'));
                    }
                });
            });

            function renderToaLmpCompetitorsList(competitors, lowestPrice) {
                if (!competitors || competitors.length === 0) {
                    $("#toaLmpDataList").html(
                        '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No competitors found for this SKU</div>'
                    );
                    return;
                }

                let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
                html += `<thead class="table-light"><tr>
                    <th>#</th><th>Image</th><th>ASIN</th><th>Product Title</th><th>Seller</th>
                    <th>Price</th><th>Rating</th><th>Reviews</th><th>Link</th>
                </tr></thead><tbody>`;

                competitors.forEach(function (item, index) {
                    const isLowest = Math.abs(parseFloat(item.price) - parseFloat(lowestPrice)) < 0.01;
                    const rowClass = isLowest ? "table-success" : "";
                    const priceFormatted = "$" + parseFloat(item.price).toFixed(2);
                    const productLink = item.link || item.product_link || "#";
                    const productTitle = item.title || item.product_title || "N/A";
                    const sellerName = item.seller_name || "—";
                    const imageUrl = item.image || "";
                    const imageHtml = imageUrl
                        ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                        : '<span style="color:#999;">—</span>';
                    const rating = item.rating
                        ? `<span style="color:#ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>`
                        : '<span style="color:#999;">—</span>';
                    const reviews = item.reviews
                        ? `<span>${parseInt(item.reviews, 10).toLocaleString()}</span>`
                        : '<span style="color:#999;">—</span>';

                    html += `<tr class="${rowClass}">
                        <td class="text-center"><strong>${index + 1}</strong></td>
                        <td class="text-center">${imageHtml}</td>
                        <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtmlAttr(item.asin || "N/A")}</span></td>
                        <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtmlAttr(productTitle.length > 60 ? productTitle.substring(0, 60) + "…" : productTitle)}</td>
                        <td style="font-size:11px;">${escapeHtmlAttr(sellerName)}</td>
                        <td><strong>${priceFormatted}${isLowest ? ' <i class="fa fa-trophy text-success"></i>' : ""}</strong></td>
                        <td class="text-center">${rating}</td>
                        <td class="text-center">${reviews}</td>
                        <td class="text-center">
                            <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View product">
                                <i class="fa fa-external-link"></i>
                            </a>
                        </td>
                    </tr>`;
                });

                html += "</tbody></table></div>";
                $("#toaLmpDataList").html(html);
            }

            function loadToaLmpModal(sku) {
                $("#toaLmpSku").text(sku);
                const modal = new bootstrap.Modal(document.getElementById("toaLmpModal"));
                modal.show();

                $("#toaLmpDataList").html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading competitors...</p>
                    </div>
                `);

                $.ajax({
                    url: "/amazon/competitors",
                    method: "GET",
                    data: { sku: sku },
                    success: function (response) {
                        if (response.success) {
                            renderToaLmpCompetitorsList(response.competitors, response.lowest_price);
                        } else {
                            $("#toaLmpDataList").html(
                                '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>'
                            );
                        }
                    },
                    error: function () {
                        $("#toaLmpDataList").html(
                            '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> Could not load competitor data.</div>'
                        );
                    }
                });
            }

            $(document).on("click", ".toa-view-lmp-competitors", function (e) {
                e.preventDefault();
                const sku = $(this).data("sku");
                if (sku) {
                    loadToaLmpModal(sku);
                }
            });

            globalPreview.addEventListener("mouseenter", () => clearTimeout(hideTimeout));
            globalPreview.addEventListener("mouseleave", () => {
                globalPreview.style.display = "none";
            });

            // NRP Filter dropdown
            document.getElementById('nrp-filter').addEventListener('change', function() {
                reloadTableWithFilters();
            });

            function reloadTableWithFilters() {
                const stage = document.getElementById("stage-filter").value;
                const searchText = document.getElementById("search-input").value.trim();
                const nrpFilter = document.getElementById("nrp-filter").value;
                
                let showNR = '0';
                let showLATER = '0';
                
                if (nrpFilter === 'show_nr') {
                    showNR = '1';
                } else if (nrpFilter === 'show_later') {
                    showLATER = '1';
                }
                
                // Update AJAX URL with parameters
                const params = new URLSearchParams({
                    stage: stage || '',
                    search: searchText || '',
                    showNR: showNR,
                    showLATER: showLATER
                });
                
                table.setData('/to-order-analysis/data?' + params.toString())
                    .then(() => {
                        updateCounts();
                    })
                    .catch(err => console.error('Error reloading table:', err));
            }
        });
    </script>
@endsection
