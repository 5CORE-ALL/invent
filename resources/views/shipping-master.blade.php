@extends('layouts.vertical', ['title' => 'Shipping Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
            width: 100%;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: #8fb9fe !important;
            color: white;
            z-index: 10;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 9px;
            letter-spacing: 0.2px;
            text-transform: lowercase;
            transition: all 0.2s ease;
            height: auto;
            min-height: 72px;
            min-width: 52px;
            width: auto;
            text-align: center;
            padding: 8px 6px;
        }
        /* Header label - horizontal (no rotation), allows <br> for two lines */
        .table-responsive thead th .th-vertical-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #000;
            margin-bottom: 2px;
            text-align: center;
            line-height: 1.25;
            text-transform: lowercase;
        }
        /* Horizontal header label - no rotation (Parent, SKU) */
        .table-responsive thead th .th-horizontal-label {
            display: block;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            color: #000;
            text-align: center;
            margin-bottom: 2px;
            text-transform: lowercase;
        }
        .table-responsive thead th.th-parent-sku-col {
            min-width: 64px;
            width: auto;
            min-height: 72px;
        }
        .table-responsive tbody td.td-parent-col,
        .table-responsive tbody td.td-sku-col {
            white-space: nowrap;
            min-width: 0;
            width: auto;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table-responsive thead th.th-has-filter {
            min-width: 52px;
            width: auto;
        }
        .table-responsive thead th.th-checkbox-col {
            height: auto;
            min-width: 24px;
            width: 24px;
            max-width: 24px;
        }

        .table-responsive thead th:hover {
            background: #7aa8fd !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 3px;
            margin-top: 4px;
            font-size: 9px;
            width: 3em;
            min-width: 3em;
            max-width: 3em;
            transition: all 0.2s;
        }
        .table-responsive thead input.header-search-120 {
            width: 20ch;
            min-width: 20ch;
            max-width: 20ch;
        }

        .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 3px;
            margin-top: 4px;
            font-size: 9px;
            width: 3em;
            min-width: 3em;
            max-width: 3em;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead input::placeholder {
            color: #8e9ab4;
            font-style: italic;
        }

        .table-responsive thead select.missing-data-filter {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            transition: all 0.2s;
        }
        .table-responsive thead select.missing-data-filter:focus {
            background-color: white;
            border-color: #1a56b7;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }
        .table-responsive thead select.missing-data-filter option[value="missing"]:checked {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
        }
        .table-responsive thead select.missing-data-filter[value="missing"] {
            background-color: #fecaca;
            color: #dc2626;
            font-weight: bold;
            border-color: #ef4444;
        }

        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .table-responsive tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr.shipping-parent-row {
            background-color: #dbeafe !important;
        }
        .table-responsive tbody tr.shipping-parent-row:nth-child(even) {
            background-color: #bfdbfe !important;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        .table-responsive tbody tr.shipping-parent-row:hover {
            background-color: #93c5fd !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(37, 99, 235, 0.15);
        }
        .table-responsive tbody tr.shipping-parent-row:hover td {
            color: #0f172a;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .shipping-rate-header,
        .shipping-rate-header .th-vertical-label,
        .shipping-rate-header .th-horizontal-label {
            font-weight: 700 !important;
            font-size: 12px !important;
        }
        .shipping-rate-cell {
            font-weight: 700;
            font-size: 13px;
        }
        .shipping-rate-cell.shipping-rate-alert {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-alert {
            color: #b02a37 !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-high {
            color: #dc3545 !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low {
            color: #198754 !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low-2 {
            color: #0d6efd !important;
        }
        .table-responsive tbody td.shipping-rate-cell.shipping-rate-low-3 {
            color: #ca8a04 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-high,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-high,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-high {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low {
            color: #198754 !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low-2,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low-2,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low-2 {
            color: #0d6efd !important;
        }
        .table-responsive tbody tr:hover td.shipping-rate-cell.shipping-rate-low-3,
        .table-responsive tbody tr.shipping-parent-row td.shipping-rate-cell.shipping-rate-low-3,
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-rate-cell.shipping-rate-low-3 {
            color: #ca8a04 !important;
        }

        /* Product Master Ship column only: light yellow bg, black text */
        .table-responsive thead th.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive thead th.shipping-ship-col .th-vertical-label {
            color: #000000 !important;
        }
        .table-responsive tbody td.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr:hover td.shipping-ship-col {
            background-color: #fef08a !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr.shipping-parent-row td.shipping-ship-col {
            background-color: #fef9c3 !important;
            color: #000000 !important;
        }
        .table-responsive tbody tr.shipping-parent-row:hover td.shipping-ship-col {
            background-color: #fef08a !important;
            color: #000000 !important;
        }
        .table-responsive tbody td.shipping-ship-col.shipping-rate-alert {
            color: #dc3545 !important;
        }
        .table-responsive tbody tr:hover td.shipping-ship-col.shipping-rate-alert {
            color: #b02a37 !important;
        }

        /* Missing data indicator (same look + behaviour as Product Master,
           sized for the compact shipping-master cells). Used on every
           child-SKU cell whose value is null / empty / NaN. Clicking the
           badge opens the row's edit modal and focuses the missing field. */
        .missing-data-indicator {
            display: inline-block;
            color: #dc3545;
            font-weight: bold;
            font-size: 11px;
            line-height: 1;
            background-color: #ffebee;
            padding: 3px 7px;
            border-radius: 4px;
            border: 1px solid #dc3545;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 22px;
            text-align: center;
            user-select: none;
        }
        .missing-data-indicator:hover {
            background-color: #dc3545;
            color: #fff;
            transform: scale(1.08);
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        }
        .missing-data-indicator:active {
            transform: scale(0.96);
        }
        .table-responsive tbody tr:hover td .missing-data-indicator {
            background-color: #dc3545;
            color: #fff;
        }
        /* Field highlight when the modal opens via an M click — lets the user
           immediately see which field they jumped to. */
        .form-control.missing-field-highlight,
        .form-select.missing-field-highlight {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2) !important;
            background-color: #fff5f5 !important;
            transition: all 0.25s ease;
        }
        /* Cells holding only the M badge shouldn't inherit the red "alert"
           text color or the yellow ship-col background — the badge speaks for
           itself. */
        .table-responsive tbody td.shipping-rate-cell.has-missing-indicator,
        .table-responsive tbody td.shipping-ship-col.has-missing-indicator {
            color: inherit !important;
        }

        /* Highlight selected item dimension headers */
        .table-responsive thead th.item-dim-header {
            background-color: #fff9c4 !important; /* light yellow */
        }
        /* Always hide CTN L/W/H (CM) columns from view */
        .table-responsive thead th.ctn-cm-col,
        .table-responsive tbody td.ctn-cm-col {
            display: none;
        }
        /* Always hide Item Length/Width/Height (CM) columns from view */
        .table-responsive thead th.item-cm-col,
        .table-responsive tbody td.item-cm-col {
            display: none;
        }
        /* Hide Item Weight ACT (Kg) column */
        .table-responsive thead th.hide-item-wt-act,
        .table-responsive tbody td.hide-item-wt-act {
            display: none;
        }
        /* Hide Item Weight DECL (LB) column */
        .table-responsive thead th.hide-item-wt-decl,
        .table-responsive tbody td.hide-item-wt-decl {
            display: none;
        }
        /* Hide FBA SKU column */
        .table-responsive thead th.hide-fba-sku-col,
        .table-responsive tbody td.hide-fba-sku-col {
            display: none;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: auto;
        }

        /* Verified column – red/green dot dropdown */
        .verified-data-dropdown {
            width: 28px;
            height: 28px;
            min-width: 28px;
            padding: 0;
            border-radius: 50%;
            border: 2px solid rgba(0,0,0,0.15);
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            background-repeat: no-repeat;
            background-position: center;
        }
        .verified-data-dropdown.not-verified {
            background-color: #fff;
            border-color: #dc3545;
            color: #dc3545;
        }
        .verified-data-dropdown.not-verified:hover {
            background-color: rgba(220, 53, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
        }
        .verified-data-dropdown.verified {
            background-color: #fff;
            border-color: #28a745;
            color: #28a745;
        }
        .verified-data-dropdown.verified:hover {
            background-color: rgba(40, 167, 69, 0.1);
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }
        .verified-data-dropdown option[value="0"] { color: #dc3545; }
        .verified-data-dropdown option[value="1"] { color: #28a745; }

        .status-badges-full {
            width: 100%;
            flex-wrap: wrap;
        }
        .status-badges-full .status-badge-item {
            flex: 1;
            min-width: 80px;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #000;
            text-align: center;
            white-space: nowrap;
        }
        .status-badges-full .status-badge-item.bg-active { background-color: #bbf7d0; }
        .status-badges-full .status-badge-item.bg-inactive { background-color: #fecaca; }
        .status-badges-full .status-badge-item.bg-dc { background-color: #fecaca; }
        .status-badges-full .status-badge-item.bg-upcoming { background-color: #fef08a; }
        .status-badges-full .status-badge-item.bg-2bdc { background-color: #bfdbfe; }

        /* Ensure table fits container - auto layout so columns fit content */
        #dim-wt-master-datatable {
            width: 100% !important;
            table-layout: auto;
        }

        /* Prevent horizontal overflow */
        .card-body {
            overflow-x: hidden;
        }

        .edit-btn {
            border-radius: 4px;
            padding: 3px 6px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
            font-size: 11px;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        .delete-btn {
            border-radius: 4px;
            padding: 3px 6px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
            font-size: 11px;
        }

        .delete-btn:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(220, 53, 69, 0.2);
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        .row-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #selectAll {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1a56b7;
        }

        #pushDataBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .d-flex.gap-2 > button {
            flex-shrink: 0;
        }

        .time-navigation-group {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }
        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }
        .time-navigation-group button:hover:not(:disabled) {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .time-navigation-group button i { font-size: 1rem; }
        #play-auto { color: #28a745; }
        #play-auto:hover:not(:disabled) { background-color: #28a745 !important; color: white !important; }
        #play-pause { color: #ffc107; display: none; }
        #play-pause:hover:not(:disabled) { background-color: #ffc107 !important; color: white !important; }
        #play-backward, #play-forward { color: #007bff; }
        #play-backward:hover:not(:disabled), #play-forward:hover:not(:disabled) { background-color: #007bff !important; color: white !important; }

        @media (max-width: 768px) {
            .d-flex.justify-content-end {
                justify-content: flex-start !important;
            }
            
            .d-flex.gap-2 > button {
                flex: 1 1 auto;
                min-width: 0;
            }
        }

        /* Slab Rates modal — compact carrier inputs (slab column scrolls with table) */
        #slabRatesTable .slab-rates-sticky-col {
            background: transparent;
            white-space: nowrap;
        }
        #slabRatesTable tbody tr.slab-row-empty .slab-rates-sticky-col {
            color: #94a3b8;
        }
        #slabRatesTable thead th {
            white-space: nowrap;
        }
        #slabRatesTable th.slab-rates-carrier-col,
        #slabRatesTable td.slab-rates-carrier-cell {
            min-width: 86px;
            width: 86px;
            text-align: center;
        }
        #slabRatesTable td.slab-rates-carrier-cell input.slab-rate-input {
            width: 78px;
            text-align: right;
            font-size: 12px;
            padding: 2px 6px;
            height: 28px;
        }
        #slabRatesTable td.slab-count-cell .badge { font-size: 11px; }
        #slabRatesTable tbody tr.slab-row-empty td.slab-rates-carrier-cell input.slab-rate-input {
            background-color: #f1f5f9;
        }
        /* "Prefilled from table" hint: input shows the current value with a
           subtle background so the user can tell it isn't a fresh edit yet,
           but the digits themselves stay upright and readable. */
        #slabRatesTable input.slab-rate-input.slab-rate-prefilled {
            color: #212529;
            background-color: #f8fafc;
            border-color: #e2e8f0;
        }
        #slabRatesTable input.slab-rate-input.slab-rate-prefilled:focus {
            background-color: #fff;
            border-color: #86b7fe;
        }
        #slabRatesTable input.slab-rate-input.slab-rate-mixed {
            background-color: #fffbeb;
            border-color: #fde68a;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Shipping Master',
        'sub_title' => 'Shipping Master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                                    <button type="button" id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                        <i class="fas fa-step-backward"></i>
                                    </button>
                                    <button type="button" id="play-pause" class="btn btn-light rounded-circle" title="Show all"
                                        style="display: none;">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <button type="button" id="play-auto" class="btn btn-light rounded-circle" title="Start parent navigation">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button type="button" id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                        <i class="fas fa-step-forward"></i>
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="form-label mb-0">Section:</label>
                                    <select id="dimWtSectionFilter" class="form-select form-select-sm" style="width: auto; min-width: 140px;">
                                        <option value="item_data">Item Data</option>
                                        <option value="carton_data">Carton Data</option>
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="form-label mb-0" for="shippingRowTypeFilter">Rows:</label>
                                    <select id="shippingRowTypeFilter" class="form-select form-select-sm" style="width: auto; min-width: 118px;" title="Show parent SKUs, child SKUs, or both">
                                        <option value="all">All</option>
                                        <option value="parent">Parent</option>
                                        <option value="sku" selected>SKU</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end flex-wrap gap-2">
                                <button type="button" class="btn btn-primary" id="pushDataBtn" disabled>
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Push Data
                                </button>
                                <button type="button" class="btn btn-warning" id="bulkEditBtn" disabled title="Edit selected SKUs in bulk">
                                    <i class="fas fa-edit me-1"></i> Bulk Edit
                                </button>
                                <button type="button" class="btn btn-dark" id="slabRatesBtn" title="Apply GOFO rate to every SKU in a weight slab">
                                    <i class="fas fa-layer-group me-1"></i> Slab Rates
                                </button>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                    <i class="fas fa-file-upload me-1"></i> Import
                                </button>
                                <button type="button" class="btn btn-success" id="downloadExcel">
                                    <i class="fas fa-file-excel me-1"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-12">
                            <div id="statusBadgesBar" class="status-badges-full d-flex align-items-center justify-content-between gap-2"></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="dim-wt-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="text-center th-checkbox-col">
                                        <input type="checkbox" id="selectAll" title="Select All" style="width: 16px; height: 16px;">
                                    </th>
                                    <th><span class="th-vertical-label">Img</span></th>
                                    <th class="th-has-filter th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">Parent</div>
                                        <input type="text" id="parentSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th class="th-has-filter th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">SKU</div>
                                        <input type="text" id="skuSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">STATUS</div>
                                        <select id="filterSTATUS" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px;" data-column="STATUS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="active">🟢 Active</option>
                                            <option value="inactive">🔴 Inactive</option>
                                            <option value="DC">🔴 DC</option>
                                            <option value="upcoming">🟡 Upcoming</option>
                                            <option value="2BDC">🔵 2BDC</option>
                                        </select>
                                    </th>
                                    <th class="shipping-rate-header"><span class="th-vertical-label">INV</span></th>
                                    <th class="th-has-filter shipping-rate-header shipping-ship-col" data-pm-ship-col="ship">
                                        <div class="th-vertical-label">Ship</div>
                                        <select id="filterShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ship column">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="ship_bb">
                                        <div class="th-vertical-label">Ship<br>BB</div>
                                        <select id="filterShipBbCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ship BB column">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="tt">
                                        <div class="th-vertical-label">TT 1<br>Ship</div>
                                        <select id="filterTtShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter TT 1 Ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="temu">
                                        <div class="th-vertical-label">Temu<br>ship</div>
                                        <select id="filterTemuShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Temu ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="temu_gofo">
                                        <div class="th-vertical-label">Temu<br>GOFO</div>
                                        <select id="filterTemuGofoCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Temu GOFO">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="ebay2">
                                        <div class="th-vertical-label">Ebay2<br>ship</div>
                                        <select id="filterEbay2ShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ebay2 ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="shein">
                                        <div class="th-vertical-label">Shein<br>ship</div>
                                        <select id="filterSheinShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Shein ship">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="gofo">
                                        <div class="th-vertical-label">GOFO</div>
                                        <select id="filterGofoCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter GOFO">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="fedex">
                                        <div class="th-vertical-label">Fedex</div>
                                        <select id="filterFedexCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Fedex">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="ups">
                                        <div class="th-vertical-label">UPS</div>
                                        <select id="filterUpsCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter UPS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="usps">
                                        <div class="th-vertical-label">USPS</div>
                                        <select id="filterUspsCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter USPS">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="uni">
                                        <div class="th-vertical-label">UNI</div>
                                        <select id="filterUniCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter UNI">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="dash">− / —</option>
                                            <option value="zero">0</option>
                                        </select>
                                    </th>
                                    <th class="shipping-rate-header pick-pack-col" title="Pick &amp; pack fee ($1 per SKU)">
                                        <span class="th-vertical-label">Pick<br>Pack</span>
                                    </th>
                                    <th class="shipping-rate-header" title="Average of TT 1 Ship, Temu, GOFO, Fedex, UPS, USPS, UNI (excludes Pick Pack and Ebay2)">
                                        <span class="th-vertical-label">Avg</span>
                                    </th>
                                    <th class="th-has-filter th-parent-sku-col shipping-rate-header hide-fba-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">FBA SKU</div>
                                        <input type="text" id="fbaSkuSearch" class="form-control-sm header-search-120"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th class="shipping-rate-header"><span class="th-vertical-label">FBA<br>ship</span></th>
                                    <th class="shipping-rate-header"><span class="th-vertical-label">FBA manual<br>ship</span></th>
                                    <th class="th-has-filter item-dim-header hide-item-wt-act">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Weight ACT<br>(Kg)</div>
                                        <select id="filterWtActKg" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter item-dim-header th-wt-act-lb-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item WT ACT<br>(LB)</div>
                                        <select id="filterWtAct" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 180px;" title="Filter by Item WT ACT (oz / lb)">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="item-dim-header" title="Item Weight ACT (lb) converted to ounces — 16 oz = 1 lb">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item Weight<br>(OZ)</span>
                                    </th>
                                    <th class="th-has-filter item-dim-header hide-item-wt-decl">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item WT DECL<br>(LB)</div>
                                        <select id="filterWtDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Length<br>(inch)</div>
                                        <select id="filterL" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Width<br>(inch)</div>
                                        <select id="filterW" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter item-dim-header">
                                        <div class="th-vertical-label" style="font-size: 9px;">Item Height<br>(Inch)</div>
                                        <select id="filterH" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="item-cm-col"><span class="th-vertical-label">Item Length<br>(CM)</span></th>
                                    <th class="item-cm-col"><span class="th-vertical-label">Item Width<br>(CM)</span></th>
                                    <th class="item-cm-col"><span class="th-vertical-label">Item Height<br>(CM)</span></th>
                                    <th class="ctn-cm-col"><span class="th-vertical-label">CTN L<br>(CM)</span></th>
                                    <th class="ctn-cm-col"><span class="th-vertical-label">CTN W<br>(CM)</span></th>
                                    <th class="ctn-cm-col"><span class="th-vertical-label">CTN H<br>(CM)</span></th>
                                    <th><span class="th-vertical-label">Carton<br>CBM</span></th>
                                    <th><span class="th-vertical-label">CTN<br>QTY</span></th>
                                    <th><span class="th-vertical-label">Carton CBM<br>each</span></th>
                                    <th class="text-center"><span class="th-vertical-label">Verified</span></th>
                                    <th><span class="th-vertical-label">Action</span></th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Shipping Master data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Shipping Master Modal -->
    <div class="modal fade" id="editDimWtModal" tabindex="-1" aria-labelledby="editDimWtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDimWtModalLabel">Edit Shipping Master</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDimWtForm">
                        <input type="hidden" id="editProductId" name="product_id">
                        <input type="hidden" id="editSku" name="sku">
                        <input type="hidden" id="editParent" name="parent">
                        
                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Item Dimension</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editWtActKg" class="form-label">Item Weight ACT (Kg)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtActKg" name="wt_act_kg" placeholder="Enter Item Weight ACT (Kg)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtAct" class="form-label">Item WT ACT (LB)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtAct" name="wt_act" placeholder="Enter Item WT ACT (LB)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtDecl" class="form-label">Item WT DECL (LB)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtDecl" name="wt_decl" placeholder="Enter Item WT DECL (LB)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editL" class="form-label">Item Length (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editL" name="l" placeholder="Enter Item Length (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editW" class="form-label">Item Width (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editW" name="w" placeholder="Enter Item Width (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editH" class="form-label">Item Height (Inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editH" name="h" placeholder="Enter Item Height (Inch)">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editLCm" class="form-label">Item Length (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editLCm" name="l_cm" placeholder="Enter Item Length (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWCm" class="form-label">Item Width (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editWCm" name="w_cm" placeholder="Enter Item Width (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editHCm" class="form-label">Item Height (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editHCm" name="h_cm" placeholder="Enter Item Height (CM)">
                            </div>
                        </div>
                        
                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">CARTON Dimension section</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label for="editCtnL" class="form-label">CTN L (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnL" name="ctn_l" placeholder="Enter CTN L (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnLInch" class="form-label">CTN L (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnLInch" name="ctn_l_inch" placeholder="Auto from CM" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnW" class="form-label">CTN W (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnW" name="ctn_w" placeholder="Enter CTN W (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnWInch" class="form-label">CTN W (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnWInch" name="ctn_w_inch" placeholder="Auto from CM" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnH" class="form-label">CTN H (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnH" name="ctn_h" placeholder="Enter CTN H (CM)">
                            </div>
                            <div class="col-md-2">
                                <label for="editCtnHInch" class="form-label">CTN H (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnHInch" name="ctn_h_inch" placeholder="Auto from CM" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                            <div class="col-md-6">
                                <label for="editCtnWeightKg" class="form-label">CTN Weight (KG)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnWeightKg" name="ctn_weight_kg" placeholder="Enter CTN Weight (KG)">
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Marketplace ship (Product Master)</small>
                                <div class="text-muted small">Leave a field blank when saving to keep its current value for each SKU.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="editShip" class="form-label fw-bold">Ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editShip" name="ship" placeholder="Ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editShipBb" class="form-label fw-bold">Ship BB</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editShipBb" name="ship_bb" placeholder="Ship BB">
                            </div>
                            <div class="col-md-3">
                                <label for="editTtShip" class="form-label fw-bold">TT 1 Ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editTtShip" name="tt_ship" placeholder="TT 1 Ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editTemuShip" class="form-label fw-bold">Temu ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editTemuShip" name="temu_ship" placeholder="Temu ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editTemuGofo" class="form-label fw-bold">Temu GOFO</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editTemuGofo" name="temu_gofo" placeholder="Temu GOFO">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="editEbay2Ship" class="form-label fw-bold">Ebay2 ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editEbay2Ship" name="ebay2_ship" placeholder="Ebay2 ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editSheinShip" class="form-label fw-bold">Shein ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editSheinShip" name="shein_ship" placeholder="Shein ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editGofo" class="form-label fw-bold">GOFO</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editGofo" name="gofo" placeholder="GOFO">
                            </div>
                            <div class="col-md-3">
                                <label for="editFedex" class="form-label fw-bold">Fedex</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editFedex" name="fedex" placeholder="Fedex">
                            </div>
                            <div class="col-md-3">
                                <label for="editUps" class="form-label fw-bold">UPS</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editUps" name="ups" placeholder="UPS">
                            </div>
                            <div class="col-md-3">
                                <label for="editUsps" class="form-label fw-bold">USPS</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editUsps" name="usps" placeholder="USPS">
                            </div>
                            <div class="col-md-3">
                                <label for="editUni" class="form-label fw-bold">UNI</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editUni" name="uni" placeholder="UNI">
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">FBA ship (FBA calculation table, not Product Master)</small>
                                <div class="text-muted small">Leave both FBA fields empty when saving to keep existing FBA values. FBA manual ship updates manual fee so fee + send cost equals this total.</div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="editFbaShip" class="form-label fw-bold">FBA ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editFbaShip" name="fba_ship_calculation" placeholder="FBA ship calculation">
                            </div>
                            <div class="col-md-6">
                                <label for="editFbaManualShip" class="form-label fw-bold">FBA manual ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editFbaManualShip" name="fba_manual_ship" placeholder="Manual + send (total)">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDimWtBtn">
                        <i class="fas fa-save me-2"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="importExcelModalLabel">
                        <i class="fas fa-upload me-2"></i>Import Shipping Master Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in Shipping Master fields per the sample columns (item dims, CTN L/W/H in CM, carton CBM / QTY / each, ship rates). Optional columns such as CBM, CBM (E), or CTN Weight (KG) can still be imported if present.</li>
                            <li>Upload the completed file</li>
                        </ol>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleBtn">
                            <i class="fas fa-download me-2"></i>Download Sample File
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="importFile" class="form-label fw-bold">Select Excel File</label>
                        <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                        <div id="fileError" class="text-danger mt-2" style="display: none;"></div>
                    </div>

                    <div id="importProgress" class="progress mb-3" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>

                    <div id="importResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="importBtn" disabled>
                        <i class="fas fa-upload me-2"></i>Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping Master History Modal -->
    <div class="modal fade" id="shippingHistoryModal" tabindex="-1" aria-labelledby="shippingHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title" id="shippingHistoryModalLabel">
                        <i class="bi bi-clock-history me-2"></i>Change History — <span id="shippingHistorySku" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="shippingHistoryLoading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 text-muted small mb-0">Loading history…</p>
                    </div>
                    <div id="shippingHistoryEmpty" class="alert alert-info mb-0" style="display:none;">
                        <i class="fas fa-info-circle me-2"></i> No edits recorded for this SKU yet. Changes made from now on will be tracked here.
                    </div>
                    <div id="shippingHistoryError" class="alert alert-danger mb-0" style="display:none;"></div>
                    <div class="table-responsive" id="shippingHistoryTableWrap" style="display:none; max-height: 65vh;">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="white-space:nowrap;">When</th>
                                    <th>Who</th>
                                    <th>Field</th>
                                    <th>Old value</th>
                                    <th>New value</th>
                                </tr>
                            </thead>
                            <tbody id="shippingHistoryTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Push Data Success Modal -->
    <div class="modal fade" id="pushDataSuccessModal" tabindex="-1" aria-labelledby="pushDataSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <h5 class="modal-title" id="pushDataSuccessModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Push Data Success
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="pushDataSuccessMessage" style="font-size: 15px; line-height: 1.6;">
                        <!-- Message will be inserted here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" id="pushDataOkBtn" data-bs-dismiss="modal">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Data Modal (small, single-field) — mirrors Product Master's
         "Enter Missing Data" dialog so clicking an M badge lets the user edit
         just that one value instead of opening the full edit modal. -->
    <div class="modal fade" id="missingDataModal" tabindex="-1" aria-labelledby="missingDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                    <h5 class="modal-title" id="missingDataModalLabel">
                        <i class="fas fa-edit me-2"></i>Enter Missing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">SKU:</label>
                        <p class="form-control-plaintext mb-0" id="missingDataSku"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Field:</label>
                        <p class="form-control-plaintext mb-0" id="missingDataField"></p>
                    </div>
                    <div class="mb-3">
                        <label for="missingDataValue" class="form-label fw-bold">Enter Value:</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="missingDataValue" placeholder="Enter value here&hellip;" autocomplete="off">
                        <small class="form-text text-muted" id="missingDataHint">Enter a numeric value.</small>
                    </div>
                    <div id="missingDataError" class="alert alert-danger" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="saveMissingDataBtn">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Slab Rates Modal -->
    <div class="modal fade" id="slabRatesModal" tabindex="-1" aria-labelledby="slabRatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%); color: white;">
                    <h5 class="modal-title" id="slabRatesModalLabel">
                        <i class="fas fa-layer-group me-2"></i>Slab Rates &mdash; Shipping Carriers
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3" style="font-size: 13px;">
                        <i class="fas fa-info-circle me-2"></i>
                        Enter a rate in any <em>(slab &times; carrier)</em> cell. On <strong>Apply</strong>, each rate is
                        written to its carrier column for every non-parent SKU in that slab. Empty cells are skipped.
                        <div class="text-muted mt-1">
                            <span class="badge bg-light text-dark border me-1" style="background-color: #f8fafc;">5.49</span>
                            <span class="me-2">= current value already shared by every SKU in the slab (kept as-is unless you edit it).</span>
                            <span class="badge bg-warning-subtle text-dark border me-1" style="background-color: #fffbeb;">mixed</span>
                            <span>= SKUs in this slab have different values; type a number to override them all.</span>
                        </div>
                        <div class="text-muted mt-1">
                            Slabs use <strong>Item WT ACT (LB)</strong> &mdash; same bands as the column filter.
                            Carriers: Ship, Ship BB, TT 1 Ship, Temu ship, Temu GOFO, Ebay2 ship, GOFO, Fedex, UPS, USPS, UNI.
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2 gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label class="form-label mb-0 small fw-semibold" for="slabRatesScope">SKU scope:</label>
                            <select id="slabRatesScope" class="form-select form-select-sm" style="width: auto; min-width: 220px;" title="Which SKUs to update inside each slab">
                                <option value="all" selected>All SKUs in slab (overwrite)</option>
                                <option value="missing">Only SKUs missing that carrier's value</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="d-flex align-items-center gap-1">
                                <label class="form-label mb-0 small fw-semibold" for="slabRatesFillRow">Fill row:</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="slabRatesFillRow" placeholder="$" style="width: 90px;" title="Type a value and click Fill row to copy it into every empty carrier cell of one slab">
                                <select id="slabRatesFillRowTarget" class="form-select form-select-sm" style="width: auto; min-width: 160px;" title="Pick the slab to fill">
                                    <option value="">— pick slab —</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="slabRatesFillRowBtn" title="Copy the value into every empty carrier cell of the selected slab">Fill</button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="slabRatesClearBtn" title="Clear all rate inputs">
                                <i class="fas fa-eraser me-1"></i> Clear inputs
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: 62vh; border: 1px solid #e9ecef; border-radius: 8px;">
                        <table class="table table-sm mb-0 align-middle" id="slabRatesTable">
                            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 3;">
                                <tr id="slabRatesHeadRow">
                                    <th class="slab-rates-sticky-col" style="font-size: 12px; min-width: 220px;">Weight Slab</th>
                                    <th class="text-center" style="font-size: 12px; width: 70px;"># SKUs</th>
                                    <!-- carrier <th>s injected here -->
                                </tr>
                            </thead>
                            <tbody id="slabRatesBody">
                                <tr><td colspan="13" class="text-center text-muted py-3">Loading slabs&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="slabRatesProgress" class="mt-3" style="display: none;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span id="slabRatesProgressLabel">Applying&hellip;</span>
                            <span id="slabRatesProgressCount">0 / 0</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-dark" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark" id="slabRatesApplyBtn">
                        <i class="fas fa-check me-2"></i> Apply Rates
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];
            let productUniqueParents = [];
            let bulkEditList = null; // When set, save will update all these products with form values
            let isProductNavigationActive = false;
            let currentProductParentIndex = -1;

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            /** Format SKU for display: add spaces between letter/digit segments (e.g. WF81202PCS4OHM -> WF 8120 2PCS 4 OHM) */
            function formatSkuDisplay(sku) {
                if (sku == null || String(sku).trim() === '') return '';
                let s = String(sku).replace(/[-_]/g, ' ');
                s = s.replace(/([A-Za-z])([0-9])/g, '$1 $2').replace(/([0-9])([A-Za-z])/g, '$1 $2');
                return s.replace(/\s+/g, ' ').trim();
            }
            /** Max characters to show for SKU/Parent in table cell; rest shown in tooltip */
            const SKU_DISPLAY_MAX_CHARS = 25;
            function formatSkuDisplayLimited(sku) {
                const full = formatSkuDisplay(sku);
                if (!full) return '';
                if (full.length <= SKU_DISPLAY_MAX_CHARS) return full;
                return full.substring(0, SKU_DISPLAY_MAX_CHARS) + '…';
            }
            function limitDisplayText(text, maxChars) {
                if (text == null || String(text).trim() === '') return '';
                const s = String(text).trim();
                if (s.length <= maxChars) return s;
                return s.substring(0, maxChars) + '…';
            }

            function getStatusDot(status) {
                const raw = String(status || '').trim();
                const s = raw.toLowerCase();
                const upper = raw.toUpperCase();
                let color = '#9ca3af';
                if (s === 'active') color = '#22c55e';
                else if (s === 'inactive') color = '#dc2626';
                else if (upper === 'DC') color = '#dc2626';
                else if (s === 'upcoming') color = '#eab308';
                else if (upper === '2BDC') color = '#2563eb';
                const title = raw || '-';
                return `<span class="status-dot" style="background-color:${color}" title="${escapeHtml(title)}"></span>`;
            }

            // Load Shipping Master data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/shipping-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            applyFilters();
                            updateCounts();
                            updateStatusBadgesBar();
                            refreshProductPlaybackState();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load Shipping Master data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="34" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const isParentRow = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentRow) row.classList.add('shipping-parent-row');
                    // Returns:
                    //   '--' for parent rows (aggregate placeholder)
                    //   '-'  for missing values on child rows (post-pass turns
                    //        these into the red "M" indicator, same as Product
                    //        Master)
                    //   formatted number otherwise (explicit 0 stays as "0").
                    const cellVal = (val, decimals) => {
                        if (isParentRow) return '--';
                        if (val === null || val === undefined || val === '' ||
                            (typeof val === 'string' && val.trim() === '')) {
                            return '-';
                        }
                        const n = parseFloat(val);
                        if (!Number.isFinite(n)) return '-';
                        return formatNumber(n, decimals);
                    };

                    // Checkbox column
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'text-center';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'row-checkbox';
                    checkbox.value = item.SKU != null ? String(item.SKU) : '';
                    checkbox.setAttribute('data-sku', item.SKU != null ? String(item.SKU) : '');
                    checkbox.setAttribute('data-id', item.id != null ? String(item.id) : '');
                    checkbox.addEventListener('change', function() {
                        updatePushButtonState();
                    });
                    checkboxCell.appendChild(checkbox);
                    row.appendChild(checkboxCell);

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.className = 'text-center';
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:30px;height:30px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column – limited characters; full value in tooltip (same as SKU)
                    const parentCell = document.createElement('td');
                    parentCell.className = 'td-parent-col';
                    parentCell.title = escapeHtml(item.Parent) || '';
                    const parentDisplay = limitDisplayText(item.Parent, SKU_DISPLAY_MAX_CHARS);
                    parentCell.textContent = parentDisplay ? escapeHtml(parentDisplay) : '-';
                    row.appendChild(parentCell);

                    // SKU column – display with spaces, limited characters; full name in tooltip
                    const skuCell = document.createElement('td');
                    skuCell.className = 'td-sku-col';
                    skuCell.title = escapeHtml(item.SKU) || '';
                    const skuDisplay = formatSkuDisplayLimited(item.SKU);
                    skuCell.textContent = skuDisplay ? escapeHtml(skuDisplay) : '-';
                    row.appendChild(skuCell);

                    // Status column – colored dot (same as product master)
                    const statusCell = document.createElement('td');
                    statusCell.className = 'text-center';
                    statusCell.innerHTML = getStatusDot(item.status);
                    row.appendChild(statusCell);

                    // INV column (bold; child 0 / missing = red)
                    const invCell = document.createElement('td');
                    invCell.className = 'text-center shipping-rate-cell';
                    if (isParentRow) {
                        invCell.textContent = '--';
                    } else if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                        invCell.classList.add('shipping-rate-alert');
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                        invCell.classList.add('shipping-rate-alert');
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    const setShippingNumericCell = (td, rawValue, isParentRow) => {
                        td.className = 'text-center shipping-rate-cell';
                        const emptyOrZero = (v) => {
                            if (v === null || v === undefined || v === '') return true;
                            const n = parseFloat(v);
                            return !Number.isFinite(n) || n === 0;
                        };
                        if (isParentRow) {
                            if (emptyOrZero(rawValue)) {
                                td.textContent = '-';
                            } else {
                                const n = parseFloat(rawValue);
                                td.textContent = Number.isFinite(n) ? formatNumber(n, 2) : '-';
                            }
                            return;
                        }
                        if (rawValue === null || rawValue === undefined || rawValue === '') {
                            td.textContent = '-';
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        const n = parseFloat(rawValue);
                        if (!Number.isFinite(n)) {
                            td.textContent = '-';
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        if (n === 0) {
                            td.textContent = formatNumber(0, 2);
                            td.classList.add('shipping-rate-alert');
                            return;
                        }
                        td.textContent = formatNumber(n, 2);
                    };

                    const shipPmCell = document.createElement('td');
                    setShippingNumericCell(shipPmCell, item.ship, isParentRow);
                    shipPmCell.classList.add('shipping-ship-col');
                    row.appendChild(shipPmCell);

                    const shipBbPmCell = document.createElement('td');
                    setShippingNumericCell(shipBbPmCell, item.ship_bb, isParentRow);
                    row.appendChild(shipBbPmCell);

                    const carrierShipHighlight = [];
                    const appendCarrierShipCell = (rawValue) => {
                        const td = document.createElement('td');
                        setShippingNumericCell(td, rawValue, isParentRow);
                        const value = carrierShipNumericFromCell(td);
                        if (value !== null) carrierShipHighlight.push({ td, value });
                        row.appendChild(td);
                    };

                    appendCarrierShipCell(item.tt_ship);
                    appendCarrierShipCell(item.temu_ship);
                    appendCarrierShipCell(item.temu_gofo);
                    appendCarrierShipCell(item.ebay2_ship);
                    appendCarrierShipCell(item.shein_ship);
                    appendCarrierShipCell(item.gofo);
                    appendCarrierShipCell(item.fedex);
                    appendCarrierShipCell(item.ups);
                    appendCarrierShipCell(item.usps);
                    appendCarrierShipCell(item.uni);
                    highlightCarrierShipMinMax(carrierShipHighlight);

                    const pickPackCell = document.createElement('td');
                    pickPackCell.className = 'text-center shipping-rate-cell pick-pack-col';
                    pickPackCell.textContent = formatNumber(PICK_PACK_RATE, 2);
                    row.appendChild(pickPackCell);

                    const uniAvgCell = document.createElement('td');
                    uniAvgCell.className = 'text-center shipping-rate-cell';
                    const uniAvg = avgUniShipCarrierRates(item);
                    uniAvgCell.textContent = uniAvg === null ? '-' : formatNumber(uniAvg, 2);
                    row.appendChild(uniAvgCell);

                    const fbaSkuCell = document.createElement('td');
                    fbaSkuCell.className = 'td-sku-col shipping-rate-cell hide-fba-sku-col';
                    const fbaSkuVal = item.fba_sku != null && String(item.fba_sku).trim() !== '' ? String(item.fba_sku).trim() : '';
                    if (isParentRow) {
                        fbaSkuCell.textContent = fbaSkuVal ? formatSkuDisplayLimited(fbaSkuVal) : '-';
                        fbaSkuCell.title = fbaSkuVal || '';
                    } else {
                        fbaSkuCell.title = fbaSkuVal;
                        if (!fbaSkuVal) {
                            fbaSkuCell.textContent = '-';
                            fbaSkuCell.classList.add('shipping-rate-alert');
                        } else {
                            fbaSkuCell.textContent = formatSkuDisplayLimited(fbaSkuVal);
                        }
                    }
                    row.appendChild(fbaSkuCell);

                    const fbaShipCell = document.createElement('td');
                    setShippingNumericCell(fbaShipCell, item.fba_ship, isParentRow);
                    row.appendChild(fbaShipCell);

                    const fbaManualShipCell = document.createElement('td');
                    setShippingNumericCell(fbaManualShipCell, item.fba_manual_ship, isParentRow);
                    row.appendChild(fbaManualShipCell);

                    // Weight ACT (Kg) column (hidden)
                    const wtActKgCell = document.createElement('td');
                    wtActKgCell.className = 'text-center hide-item-wt-act';
                    wtActKgCell.textContent = cellVal(item.wt_act_kg, 1);
                    row.appendChild(wtActKgCell);

                    // WT ACT (LB) — actual weight converted to lb when only Kg is stored
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = itemWeightActLbDisplay(item, isParentRow);
                    row.appendChild(wtActCell);

                    const wtActOzCell = document.createElement('td');
                    wtActOzCell.className = 'text-center item-weight-oz-col';
                    wtActOzCell.textContent = itemWeightActOzDisplay(item, isParentRow);
                    row.appendChild(wtActOzCell);

                    // WT DECL column
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center hide-item-wt-decl';
                    wtDeclCell.textContent = cellVal(item.wt_decl, 2);
                    row.appendChild(wtDeclCell);

                    // L column (inch) - round to whole number
                    const lCell = document.createElement('td');
                    lCell.className = 'text-center';
                    lCell.textContent = cellVal(item.l, 0);
                    row.appendChild(lCell);

                    // W column (inch) - round to whole number
                    const wCell = document.createElement('td');
                    wCell.className = 'text-center';
                    wCell.textContent = cellVal(item.w, 0);
                    row.appendChild(wCell);

                    // H column (inch) - round to whole number
                    const hCell = document.createElement('td');
                    hCell.className = 'text-center';
                    hCell.textContent = cellVal(item.h, 0);
                    row.appendChild(hCell);

                    // Length (CM) column (use stored value or convert from inch) - hidden
                    const lCmCell = document.createElement('td');
                    lCmCell.className = 'text-center item-cm-col';
                    const lCmVal = item.l_cm != null && item.l_cm !== undefined && item.l_cm !== ''
                        ? item.l_cm
                        : (parseFloat(item.l) || 0) * 2.54;
                    lCmCell.textContent = cellVal(lCmVal, 0);
                    row.appendChild(lCmCell);

                    // Width (CM) column (use stored value or convert from inch) - hidden
                    const wCmCell = document.createElement('td');
                    wCmCell.className = 'text-center item-cm-col';
                    const wCmVal = item.w_cm != null && item.w_cm !== undefined && item.w_cm !== ''
                        ? item.w_cm
                        : (parseFloat(item.w) || 0) * 2.54;
                    wCmCell.textContent = cellVal(wCmVal, 0);
                    row.appendChild(wCmCell);

                    // Height (CM) column (use stored value or convert from inch) - hidden
                    const hCmCell = document.createElement('td');
                    hCmCell.className = 'text-center item-cm-col';
                    const hCmVal = item.h_cm != null && item.h_cm !== undefined && item.h_cm !== ''
                        ? item.h_cm
                        : (parseFloat(item.h) || 0) * 2.54;
                    hCmCell.textContent = cellVal(hCmVal, 0);
                    row.appendChild(hCmCell);

                    // CTN L (CM) column (hidden by CSS)
                    const ctnLenCmCell = document.createElement('td');
                    ctnLenCmCell.className = 'text-center ctn-cm-col';
                    ctnLenCmCell.textContent = cellVal(item.ctn_l, 0);
                    row.appendChild(ctnLenCmCell);

                    // CTN W (CM) column (hidden by CSS)
                    const ctnWidCmCell = document.createElement('td');
                    ctnWidCmCell.className = 'text-center ctn-cm-col';
                    ctnWidCmCell.textContent = cellVal(item.ctn_w, 0);
                    row.appendChild(ctnWidCmCell);

                    // CTN H (CM) column (hidden by CSS)
                    const ctnHeiCmCell = document.createElement('td');
                    ctnHeiCmCell.className = 'text-center ctn-cm-col';
                    ctnHeiCmCell.textContent = cellVal(item.ctn_h, 0);
                    row.appendChild(ctnHeiCmCell);

                    // CTN CBM column (calculated: CTN L * CTN W * CTN H / 1000000)
                    const ctnCbmCalculated = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                    const ctnCbmCell = document.createElement('td');
                    ctnCbmCell.className = 'text-center';
                    ctnCbmCell.textContent = cellVal(ctnCbmCalculated, 1);
                    row.appendChild(ctnCbmCell);

                    // CTN (QTY) column
                    const ctnQtyCell = document.createElement('td');
                    ctnQtyCell.className = 'text-center';
                    ctnQtyCell.textContent = cellVal(item.ctn_qty, 0);
                    row.appendChild(ctnQtyCell);

                    // CTN CBM each column (calculated: CTN CBM / CTN Qty)
                    const ctnQtyVal = parseFloat(item.ctn_qty) || 0;
                    const ctnCbmEachCalculated = ctnQtyVal > 0 ? ctnCbmCalculated / ctnQtyVal : 0;
                    const ctnCbmEachCell = document.createElement('td');
                    ctnCbmEachCell.className = 'text-center';
                    ctnCbmEachCell.textContent = cellVal(ctnCbmEachCalculated, 1);
                    row.appendChild(ctnCbmEachCell);

                    // Verified column – red/green dot toggle
                    const isVerified = item.verified_data === 1 || item.verified_data === true ||
                        (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                    const verifiedClass = isVerified ? 'verified' : 'not-verified';
                    const verifiedValue = isVerified ? '1' : '0';
                    const verifiedCell = document.createElement('td');
                    verifiedCell.className = 'text-center';
                    verifiedCell.innerHTML = `
                        <select class="verified-data-dropdown ${verifiedClass}"
                            data-sku="${escapeHtml(item.SKU)}" data-id="${escapeHtml(item.id)}"
                            title="${isVerified ? 'Verified' : 'Not verified'}">
                            <option value="0" ${!isVerified ? 'selected' : ''}>🔴</option>
                            <option value="1" ${isVerified ? 'selected' : ''}>🟢</option>
                        </select>
                    `;
                    row.appendChild(verifiedCell);

                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    actionCell.innerHTML = `
                        <div class="d-inline-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-id="${item.id != null ? String(item.id) : ''}" data-sku="${escapeHtml(item.SKU)}" title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info history-btn" data-id="${item.id != null ? String(item.id) : ''}" data-sku="${escapeHtml(item.SKU)}" title="History — see who changed what">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}" title="Delete">
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    `;
                    row.appendChild(actionCell);
                    
                    // Add event listener for edit button
                    const editBtn = actionCell.querySelector('.edit-btn');
                    editBtn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const sku = this.getAttribute('data-sku');
                        let product = null;
                        if (id != null && id !== '') {
                            product = tableData.find(d => String(d.id) === String(id));
                        }
                        if (!product && sku) {
                            const key = normalizeSkuKey(sku);
                            product = tableData.find(d => normalizeSkuKey(d.SKU) === key);
                        }
                        if (product) {
                            bulkEditList = null;
                            editDimWt(product);
                        }
                    });

                    // Add event listener for history button
                    const historyBtn = actionCell.querySelector('.history-btn');
                    if (historyBtn) {
                        historyBtn.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const sku = this.getAttribute('data-sku');
                            openShippingHistoryModal(id, sku);
                        });
                    }

                    if (!isParentRow) convertMissingDashesToIndicator(row);
                    tbody.appendChild(row);
                });
                applyDimWtSectionFilter();
            }

            /** Cell column index → field key on `item` (and on the
             *  /dim-wt-master/update payload). Used by the missing-indicator
             *  click handler to focus the right input. */
            const SHIPPING_COLUMN_INDEX_TO_FIELD = {
                5:  'inv',
                6:  'ship',
                7:  'ship_bb',
                8:  'tt_ship',
                9:  'temu_ship',
                10: 'temu_gofo',
                11: 'ebay2_ship',
                12: 'shein_ship',
                13: 'gofo',
                14: 'fedex',
                15: 'ups',
                16: 'usps',
                17: 'uni',
                20: 'fba_sku',
                21: 'fba_ship',
                22: 'fba_manual_ship',
                23: 'wt_act_kg',
                24: 'wt_act',
                25: 'wt_act',          // Item Weight (OZ) is derived from wt_act — focus the LB input
                26: 'wt_decl',
                27: 'l',
                28: 'w',
                29: 'h',
                30: 'l_cm',
                31: 'w_cm',
                32: 'h_cm',
                33: 'ctn_l',
                34: 'ctn_w',
                35: 'ctn_h',
                37: 'ctn_qty'
            };

            /** Human-readable field labels used by the small "Enter Missing
             *  Data" modal title. */
            const SHIPPING_FIELD_LABELS = {
                wt_act_kg:       'Item Weight ACT (Kg)',
                wt_act:          'Item WT ACT (LB)',
                wt_decl:         'Item WT DECL (LB)',
                l:               'Item Length (inch)',
                w:               'Item Width (inch)',
                h:               'Item Height (inch)',
                l_cm:            'Item Length (CM)',
                w_cm:            'Item Width (CM)',
                h_cm:            'Item Height (CM)',
                ctn_l:           'CTN L (CM)',
                ctn_w:           'CTN W (CM)',
                ctn_h:           'CTN H (CM)',
                ctn_qty:         'CTN (QTY)',
                ship:            'Ship',
                ship_bb:         'Ship BB',
                tt_ship:         'TT 1 Ship',
                temu_ship:       'Temu ship',
                temu_gofo:       'Temu GOFO',
                ebay2_ship:      'Ebay2 ship',
                shein_ship:      'Shein ship',
                gofo:            'GOFO',
                fedex:           'Fedex',
                ups:             'UPS',
                usps:            'USPS',
                uni:             'UNI',
                fba_ship:        'FBA ship',
                fba_manual_ship: 'FBA manual ship',
                inv:             'INV (Shopify)',
                fba_sku:         'FBA SKU'
            };

            /** Per-field input step (most fields are dollars / cm, so 0.01;
             *  ctn_qty is a whole-number count). */
            const SHIPPING_FIELD_STEP = {
                ctn_qty: '1'
            };

            /** Fields that the small modal cannot save: they live outside the
             *  ProductMaster.Values JSON. On M click we explain that instead
             *  of opening the editor. */
            const SHIPPING_READONLY_FIELDS = {
                inv:     'INV comes from Shopify and is not editable here. Update inventory in Shopify (or in the Inventory Master).',
                fba_sku: 'FBA SKU lives in the FBA calculation table. Update it from the FBA module.'
            };

            /** Markup for the red "M" missing-data badge — identical look to
             *  Product Master so the two pages stay visually consistent. */
            function missingDataIndicatorHtml(field) {
                const f = field ? ` data-field="${field}"` : '';
                return `<span class="missing-data-indicator" title="Missing Data — click to edit"${f}>M</span>`;
            }

            /** Walk a non-parent row and replace any cell that shows only "-"
             *  (single dash, set by cellVal / itemWeight*Display / setShipping*)
             *  with the M badge. Cells containing form controls, links, the
             *  status dot, etc. are skipped because they have child elements.
             *  The badge gets a `data-field` matching the cell's column so the
             *  click handler can focus the right input in the edit modal. */
            function convertMissingDashesToIndicator(row) {
                if (!row) return;
                row.querySelectorAll('td').forEach(td => {
                    if (td.children.length > 0) return;
                    const text = (td.textContent || '').trim();
                    if (text !== '-') return;
                    const field = SHIPPING_COLUMN_INDEX_TO_FIELD[td.cellIndex] || '';
                    td.innerHTML = missingDataIndicatorHtml(field);
                    td.classList.add('has-missing-indicator');
                });
            }

            /** Reference to the M badge that triggered the small modal, so we
             *  can update the cell in place after a successful save. */
            let currentMissingDataButton = null;

            /** Click handler for M badges: open the small "Enter Missing Data"
             *  modal so the user can edit ONLY that one field — same UX as
             *  Product Master. The full edit modal is reachable from the
             *  pencil icon in the Action column if the user needs broader
             *  edits. */
            function setupMissingIndicatorClicks() {
                const tableEl = document.getElementById('dim-wt-master-datatable');
                if (!tableEl) return;
                tableEl.addEventListener('click', function (e) {
                    const indicator = e.target.closest('.missing-data-indicator');
                    if (!indicator) return;
                    e.preventDefault();
                    e.stopPropagation();

                    const row = indicator.closest('tr');
                    if (!row) return;
                    const checkbox = row.querySelector('.row-checkbox');
                    if (!checkbox) return;
                    const product = findProductByRowRef(checkbox);
                    if (!product) {
                        showToast('warning', 'Could not find the matching SKU for that row.');
                        return;
                    }

                    const fieldKey = indicator.getAttribute('data-field') || '';
                    if (SHIPPING_READONLY_FIELDS[fieldKey]) {
                        showToast('info', SHIPPING_READONLY_FIELDS[fieldKey]);
                        return;
                    }
                    if (!fieldKey || !SHIPPING_FIELD_LABELS[fieldKey]) {
                        // Calculated columns (avg, ctn_cbm, ctn_cbm_each) — no
                        // single field to edit. Fall back to the full modal.
                        bulkEditList = null;
                        editDimWt(product);
                        return;
                    }

                    openMissingDataModal({
                        product,
                        field: fieldKey,
                        indicator
                    });
                });

                // Save handler for the small modal
                const saveBtn = document.getElementById('saveMissingDataBtn');
                if (saveBtn) {
                    saveBtn.addEventListener('click', saveMissingData);
                }

                // Reset state when the small modal is dismissed
                const missingModalEl = document.getElementById('missingDataModal');
                if (missingModalEl) {
                    missingModalEl.addEventListener('hidden.bs.modal', function () {
                        currentMissingDataButton = null;
                        const valEl = document.getElementById('missingDataValue');
                        if (valEl) valEl.value = '';
                        const errEl = document.getElementById('missingDataError');
                        if (errEl) errEl.style.display = 'none';
                    });

                    // Submit on Enter inside the value input
                    const valEl = document.getElementById('missingDataValue');
                    if (valEl) {
                        valEl.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                saveMissingData();
                            }
                        });
                    }
                }
            }

            /** Open the small modal pre-configured for one field on one SKU. */
            function openMissingDataModal({ product, field, indicator }) {
                const label = SHIPPING_FIELD_LABELS[field] || field;
                const step = SHIPPING_FIELD_STEP[field] || '0.01';

                currentMissingDataButton = indicator;

                document.getElementById('missingDataSku').textContent = product.SKU || '';
                document.getElementById('missingDataField').textContent = label;
                document.getElementById('missingDataModalLabel').innerHTML =
                    '<i class="fas fa-edit me-2"></i>Enter Missing ' + escapeHtml(label);

                const valEl = document.getElementById('missingDataValue');
                valEl.value = '';
                valEl.step = step;
                valEl.placeholder = 'Enter ' + label + '…';
                valEl.setAttribute('data-sku', product.SKU || '');
                valEl.setAttribute('data-product-id', product.id != null ? String(product.id) : '');
                valEl.setAttribute('data-parent', product.Parent || '');
                valEl.setAttribute('data-field', field);

                const hintEl = document.getElementById('missingDataHint');
                if (hintEl) {
                    hintEl.textContent = step === '1'
                        ? 'Enter a whole-number value.'
                        : 'Enter a numeric value (decimals allowed).';
                }

                const errEl = document.getElementById('missingDataError');
                if (errEl) errEl.style.display = 'none';

                const modalEl = document.getElementById('missingDataModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();

                setTimeout(() => valEl.focus(), 250);
            }

            /** Persist a single field via the existing /dim-wt-master/update
             *  endpoint, then update the cell in place so the M badge becomes
             *  the new value without a full table reload. */
            async function saveMissingData() {
                if (!currentMissingDataButton) {
                    showToast('danger', 'Lost reference to the cell. Please re-open the row.');
                    return;
                }

                const valEl = document.getElementById('missingDataValue');
                const errEl = document.getElementById('missingDataError');
                const saveBtn = document.getElementById('saveMissingDataBtn');
                const originalSaveHtml = saveBtn ? saveBtn.innerHTML : '';

                const raw = String(valEl.value || '').trim();
                const productId = valEl.getAttribute('data-product-id');
                const sku = valEl.getAttribute('data-sku');
                const parent = valEl.getAttribute('data-parent') || '';
                const field = valEl.getAttribute('data-field');

                if (!sku || !field) {
                    errEl.textContent = 'SKU or field is missing — please close and try again.';
                    errEl.style.display = 'block';
                    return;
                }
                if (raw === '') {
                    errEl.textContent = 'Please enter a value.';
                    errEl.style.display = 'block';
                    return;
                }
                const numValue = parseFloat(raw);
                if (!Number.isFinite(numValue) || numValue < 0) {
                    errEl.textContent = 'Please enter a valid non-negative number.';
                    errEl.style.display = 'block';
                    return;
                }

                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';
                }

                try {
                    const payload = {
                        product_id: productId ? Number(productId) : undefined,
                        sku: sku,
                        parent: parent,
                        [field]: numValue
                    };

                    const response = await fetch('/dim-wt-master/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || ('Failed to save (HTTP ' + response.status + ')'));
                    }

                    // Mirror the saved value into the in-memory table data so
                    // subsequent renders / slab summaries reflect the change
                    // without a full reload.
                    if (Array.isArray(tableData)) {
                        const matchId = productId ? String(productId) : null;
                        const target = tableData.find(d =>
                            (matchId && String(d.id) === matchId) ||
                            (d.SKU && sku && String(d.SKU) === String(sku))
                        );
                        if (target) target[field] = numValue;
                    }

                    // Swap the M badge with the formatted value in place.
                    const cell = currentMissingDataButton.closest('td');
                    if (cell) {
                        cell.classList.remove('has-missing-indicator');
                        const display = (field === 'ctn_qty') ? String(Math.round(numValue)) : formatNumber(numValue, 2);
                        cell.innerHTML = '';
                        cell.textContent = display;
                    }

                    showToast('success', (SHIPPING_FIELD_LABELS[field] || field) + ' saved.');

                    bootstrap.Modal.getInstance(document.getElementById('missingDataModal')).hide();
                } catch (err) {
                    console.error('Missing data save failed:', err);
                    errEl.textContent = err.message || 'Failed to save data.';
                    errEl.style.display = 'block';
                } finally {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalSaveHtml || '<i class="fas fa-save me-1"></i>Save';
                    }
                }
            }

            // Section filter: controls visibility of item vs carton metrics
            function applyDimWtSectionFilter() {
                const table = document.getElementById('dim-wt-master-datatable');
                const sectionEl = document.getElementById('dimWtSectionFilter');
                if (!table || !sectionEl) return;
                const theadRow = table.querySelector('thead tr');
                const tbody = document.getElementById('table-body');
                if (!theadRow || !tbody) return;
                const ths = theadRow.querySelectorAll('th');
                const section = sectionEl.value;

                for (let i = 0; i < ths.length; i++) {
                    const th = ths[i];
                    const headerText = (th.textContent || '').toLowerCase();

                    const isCtnDim =
                        headerText.includes('ctn l') ||
                        headerText.includes('ctn w') ||
                        headerText.includes('ctn h');

                    const isCartonMetric =
                        (!isCtnDim && headerText.includes('carton')) ||
                        headerText.includes('ctn cbm') ||
                        (headerText.includes('ctn') && headerText.includes('qty'));

                    let visible = true;

                    if (section === 'item_data') {
                        // Show all item columns + CTN dimensions; hide only carton summary metrics
                        visible = !isCartonMetric;
                    } else if (section === 'carton_data') {
                        // Focus on CTN dimensions and carton metrics; hide most pure item-only metrics
                        const hNorm = headerText.replace(/\s+/g, ' ').trim();
                        const pmAttr = th.getAttribute('data-pm-ship-col');
                        const isPmShipCol = pmAttr === 'ship' || pmAttr === 'ship_bb' || pmAttr === 'tt' || pmAttr === 'temu' || pmAttr === 'ebay2';
                        const isFbaShipCol = hNorm.includes('fba sku') || hNorm.includes('fba ship') || hNorm.includes('fba manual');
                        visible = isCtnDim || isCartonMetric || headerText.includes('status') || headerText === 'inv' || isPmShipCol || isFbaShipCol;
                    }

                    th.style.display = visible ? '' : 'none';
                    tbody.querySelectorAll('tr').forEach(tr => {
                        const cell = tr.cells[i];
                        if (cell) cell.style.display = visible ? '' : 'none';
                    });
                }
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let wtActKgMissingCount = 0;
                let wtActMissingCount = 0;
                let wtDeclMissingCount = 0;
                let lMissingCount = 0;
                let wMissingCount = 0;
                let hMissingCount = 0;
                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Skip parent SKUs when counting missing data
                    const isParentSku = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentSku) {
                        return; // Skip parent SKUs
                    }
                    
                    // Count missing data for each column (only for child SKUs)
                    if (isMissing(item.wt_act_kg)) wtActKgMissingCount++;
                    if (isMissing(item.wt_act)) wtActMissingCount++;
                    if (isMissing(item.wt_decl)) wtDeclMissingCount++;
                    if (isMissing(item.l)) lMissingCount++;
                    if (isMissing(item.w)) wMissingCount++;
                    if (isMissing(item.h)) hMissingCount++;
                });
                
                const setHeaderCount = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = `(${val})`; };
                setHeaderCount('parentCount', parentSet.size);
                setHeaderCount('skuCount', skuCount);
                setHeaderCount('wtActKgMissingCount', wtActKgMissingCount);
                setHeaderCount('wtActMissingCount', wtActMissingCount);
                setHeaderCount('wtDeclMissingCount', wtDeclMissingCount);
                setHeaderCount('lMissingCount', lMissingCount);
                setHeaderCount('wMissingCount', wMissingCount);
                setHeaderCount('hMissingCount', hMissingCount);
            }

            function updateStatusBadgesBar() {
                const bar = document.getElementById('statusBadgesBar');
                if (!bar) return;
                const statusCounts = { active: 0, inactive: 0, DC: 0, upcoming: 0, '2BDC': 0 };
                (tableData || []).forEach(item => {
                    const sku = String(item.SKU || item.sku || '').toUpperCase();
                    if (sku.includes('PARENT')) return;
                    let raw = item.status;
                    if ((raw == null || raw === '') && item.Values) {
                        const V = typeof item.Values === 'string' ? (function(){ try { return JSON.parse(item.Values); } catch(e) { return {}; } })() : item.Values;
                        raw = V && V.status;
                    }
                    const s = String(raw || '').trim();
                    const lower = s.toLowerCase();
                    if (lower === 'active') statusCounts.active++;
                    else if (lower === 'inactive') statusCounts.inactive++;
                    else if (s.toUpperCase() === 'DC') statusCounts.DC++;
                    else if (lower === 'upcoming') statusCounts.upcoming++;
                    else if (s.toUpperCase() === '2BDC') statusCounts['2BDC']++;
                });
                bar.innerHTML = `
                    <span class="status-badge-item bg-active">Active ${statusCounts.active}</span>
                    <span class="status-badge-item bg-inactive">Inactive ${statusCounts.inactive}</span>
                    <span class="status-badge-item bg-dc">DC ${statusCounts.DC}</span>
                    <span class="status-badge-item bg-upcoming">Upcoming ${statusCounts.upcoming}</span>
                    <span class="status-badge-item bg-2bdc">2BDC ${statusCounts['2BDC']}</span>
                `;
            }

            function refreshProductPlaybackState() {
                productUniqueParents = [...new Set((tableData || []).map(item => item.Parent).filter(Boolean))];
                updateProductButtonStates();
            }

            function setupProductPlaybackListeners() {
                const playBackward = document.getElementById('play-backward');
                const playForward = document.getElementById('play-forward');
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playBackward) playBackward.addEventListener('click', productPreviousParent);
                if (playForward) playForward.addEventListener('click', productNextParent);
                if (playPause) playPause.addEventListener('click', productStopNavigation);
                if (playAuto) playAuto.addEventListener('click', productStartNavigation);
            }

            function productStartNavigation() {
                if (productUniqueParents.length === 0) return;
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                showCurrentProductParent();
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playAuto) { playAuto.style.display = 'none'; }
                if (playPause) { playPause.style.display = 'inline-flex'; playPause.classList.remove('btn-light'); }
                updateProductButtonStates();
            }

            function productStopNavigation() {
                isProductNavigationActive = false;
                currentProductParentIndex = -1;
                const playPause = document.getElementById('play-pause');
                const playAuto = document.getElementById('play-auto');
                if (playPause) { playPause.style.display = 'none'; }
                if (playAuto) { playAuto.style.display = 'inline-flex'; playAuto.classList.remove('btn-success', 'btn-warning', 'btn-danger'); playAuto.classList.add('btn-light'); }
                applyFilters();
                updateProductButtonStates();
            }

            function productNextParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;
                currentProductParentIndex++;
                showCurrentProductParent();
            }

            function productPreviousParent() {
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;
                currentProductParentIndex--;
                showCurrentProductParent();
            }

            function showCurrentProductParent() {
                if (!isProductNavigationActive || currentProductParentIndex === -1) return;
                const currentParent = productUniqueParents[currentProductParentIndex];
                filteredData = tableData.filter(item => item.Parent === currentParent && matchesRowTypeFilter(item));
                renderTable(filteredData);
                updateProductButtonStates();
            }

            function updateProductButtonStates() {
                const playBackward = document.getElementById('play-backward');
                const playForward = document.getElementById('play-forward');
                const playAuto = document.getElementById('play-auto');
                if (playBackward) {
                    playBackward.disabled = !isProductNavigationActive || currentProductParentIndex <= 0;
                    playBackward.classList.toggle('btn-primary', isProductNavigationActive);
                    playBackward.classList.toggle('btn-light', !isProductNavigationActive);
                }
                if (playForward) {
                    playForward.disabled = !isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1;
                    playForward.classList.toggle('btn-primary', isProductNavigationActive);
                    playForward.classList.toggle('btn-light', !isProductNavigationActive);
                }
                if (playAuto) playAuto.title = isProductNavigationActive ? 'Show all' : 'Start parent navigation';
            }

            // Check if value is missing (null, undefined, empty, or 0)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || value === 0 || parseFloat(value) === 0;
            }

            /** Match ProductMaster SKU to checkbox / attribute (NBSP vs space, trim). */
            function normalizeSkuKey(s) {
                if (s == null) return '';
                return String(s).replace(/\u00a0/g, ' ').trim();
            }

            function findProductByRowRef(checkbox) {
                const id = checkbox.getAttribute('data-id');
                if (id != null && id !== '') {
                    const row = tableData.find(d => String(d.id) === String(id));
                    if (row) return row;
                }
                const sku = checkbox.getAttribute('data-sku');
                if (!sku) return null;
                const key = normalizeSkuKey(sku);
                return tableData.find(d => normalizeSkuKey(d.SKU) === key) || null;
            }

            function isParentSkuItem(item) {
                return !!(item.SKU && String(item.SKU).toUpperCase().includes('PARENT'));
            }

            /** Rows filter: all | parent | sku (default sku = child rows only). */
            function matchesRowTypeFilter(item) {
                const el = document.getElementById('shippingRowTypeFilter');
                const mode = el ? el.value : 'sku';
                if (mode === 'sku') return !isParentSkuItem(item);
                if (mode === 'parent') return isParentSkuItem(item);
                return true;
            }

            function marketplaceShipFieldStrictBlank(v) {
                return v === null || v === undefined || v === '' || (typeof v === 'string' && v.trim() === '');
            }

            /** Fixed pick &amp; pack fee per row (display only; not included in Avg). */
            const PICK_PACK_RATE = 1;

            /** Fields averaged for the Avg column (excludes Pick Pack and Ebay2). */
            const UNI_AVG_SHIP_FIELDS = ['tt_ship', 'temu_ship', 'temu_gofo', 'gofo', 'fedex', 'ups', 'usps', 'uni'];

            function roundCarrierShipValue(n) {
                return Math.round(n * 100) / 100;
            }

            /** Numeric value shown in a carrier ship cell (matches displayed text, includes 0). */
            function carrierShipNumericFromCell(td) {
                const text = (td.textContent || '').trim();
                if (text === '-' || text === '') return null;
                const n = parseFloat(text.replace(/,/g, ''));
                return Number.isFinite(n) ? roundCarrierShipValue(n) : null;
            }

            function parseComparableCarrierShipValue(raw) {
                if (raw === null || raw === undefined || raw === '') return null;
                const n = parseFloat(String(raw).replace(/,/g, ''));
                if (!Number.isFinite(n)) return null;
                return roundCarrierShipValue(n);
            }

            const CARRIER_SHIP_RANK_STYLES = [
                { cls: 'shipping-rate-low', color: '#198754' },
                { cls: 'shipping-rate-low-2', color: '#0d6efd' },
                { cls: 'shipping-rate-low-3', color: '#ca8a04' }
            ];

            function applyCarrierShipRankStyle(entry, style) {
                entry.td.classList.add(style.cls);
                entry.td.style.color = style.color;
            }

            function highlightCarrierShipMinMax(entries) {
                if (!entries || entries.length === 0) return;
                if (entries.length === 1) {
                    applyCarrierShipRankStyle(entries[0], { cls: 'shipping-rate-high', color: '#dc3545' });
                    return;
                }
                const max = Math.max(...entries.map(e => e.value));
                const uniqueAsc = [...new Set(entries.map(e => e.value))].sort((a, b) => a - b);
                if (uniqueAsc.length === 1) return;

                entries.forEach(e => {
                    if (e.value === max) {
                        applyCarrierShipRankStyle(e, { cls: 'shipping-rate-high', color: '#dc3545' });
                    }
                });

                for (let i = 0; i < Math.min(3, uniqueAsc.length); i++) {
                    const rankValue = uniqueAsc[i];
                    if (rankValue === max) continue;
                    const style = CARRIER_SHIP_RANK_STYLES[i];
                    entries.forEach(e => {
                        if (e.value === rankValue) applyCarrierShipRankStyle(e, style);
                    });
                }
            }

            function avgUniShipCarrierRates(item) {
                const nums = [];
                for (const key of UNI_AVG_SHIP_FIELDS) {
                    const n = parseComparableCarrierShipValue(item[key]);
                    if (n !== null && n > 0) nums.push(n);
                }
                if (nums.length === 0) return null;
                return nums.reduce((a, b) => a + b, 0) / nums.length;
            }

            /** Same rules as setShippingNumericCell: parent shows "-" when empty/0; child "-" when blank/invalid, "0.00" when zero. */
            function marketplaceShipDisplayKind(raw, isParent) {
                const blank = marketplaceShipFieldStrictBlank(raw);
                const n = parseFloat(raw);
                const finite = Number.isFinite(n);
                if (isParent) {
                    if (blank || !finite || n === 0) return 'dash';
                    return 'value';
                }
                if (blank || !finite) return 'dash';
                if (n === 0) return 'zero';
                return 'value';
            }

            function matchesMarketplaceShipColFilter(item, fieldName, mode) {
                if (!mode || mode === 'all') return true;
                const isP = isParentSkuItem(item);
                const raw = item[fieldName];
                const kind = marketplaceShipDisplayKind(raw, isP);
                if (mode === 'zero') return kind === 'zero';
                if (mode === 'dash') return kind === 'dash';
                if (mode === 'missing') return kind !== 'value';
                return true;
            }

            /** OZ → LB (16 oz = 1 lb), rounded to 2 decimals — matches conversion table. */
            function wtActOzToLb(oz) {
                return Math.round((oz / 16) * 100) / 100;
            }

            const KG_TO_LB = 2.2046226218;

            /** Item Weight ACT → lb: use WT ACT (lb), else convert Kg to lb. */
            function itemWeightActLbResolved(item) {
                const lb = parseFloat(item.wt_act);
                if (Number.isFinite(lb) && lb > 0) {
                    return Math.round(lb * 100) / 100;
                }
                const kg = parseFloat(item.wt_act_kg);
                if (Number.isFinite(kg) && kg > 0) {
                    return Math.round(kg * KG_TO_LB * 100) / 100;
                }
                return null;
            }

            function itemWeightActOzFromLb(lb) {
                if (lb === null || !Number.isFinite(lb)) return null;
                return Math.round(lb * 16 * 100) / 100;
            }

            function itemWeightActMissing(item) {
                return isMissing(item.wt_act) && isMissing(item.wt_act_kg);
            }

            function itemWeightActLbDisplay(item, isParentRow) {
                if (isParentRow) return '--';
                if (itemWeightActMissing(item)) return '-';
                const lb = itemWeightActLbResolved(item);
                return lb === null ? '-' : formatNumber(lb, 2);
            }

            function itemWeightActOzDisplay(item, isParentRow) {
                if (isParentRow) return '--';
                if (itemWeightActMissing(item)) return '-';
                const oz = itemWeightActOzFromLb(itemWeightActLbResolved(item));
                return oz === null ? '-' : formatNumber(oz, 2);
            }

            /** 1–15 oz upper limits (lb) from conversion table. */
            const WT_ACT_OZ_LB_UPPER = [0.06, 0.13, 0.19, 0.25, 0.31, 0.38, 0.44, 0.50, 0.56, 0.63, 0.69, 0.75, 0.81, 0.88, 0.94];

            /** Oz bands shown in Item WT ACT (LB) filter dropdown. */
            const WT_ACT_OZ_FILTER_OPTIONS = [2, 4, 6, 12];

            /** Custom oz slab ranges (oz min/max); others use adjacent table limits. */
            const WT_ACT_OZ_FILTER_SLABS = {
                2: { ozMin: 0.01, ozMax: 2, label: '0.01–2 oz (0.01 – 0.125 lb)' },
                4: { ozMin: 2.01, ozMax: 4, label: '2.01–4 oz (0.126 – 0.25 lb)' },
                6: { ozMin: 4.01, ozMax: 8, label: '4.01–8 oz (0.251 – 0.5 lb)' },
                12: { ozMin: 8.01, ozMax: 12, label: '8.01–12 oz (0.51 – 0.75 lb)' },
            };

            const WT_ACT_OZ_1599_SLAB = { ozMin: 12.01, ozMax: 15.99, label: '12.01–15.99 oz (0.751 – 1 lb)' };

            function wtActOzFilterSlabBounds(oz) {
                const custom = WT_ACT_OZ_FILTER_SLABS[oz];
                if (custom) {
                    return {
                        ozMin: custom.ozMin,
                        ozMax: custom.ozMax,
                        lbMin: custom.ozMin === 0.01 ? 0.01 : custom.ozMin / 16,
                        lbMax: custom.ozMax / 16,
                    };
                }
                return {
                    ozMin: oz - 1,
                    ozMax: oz,
                    lbMin: WT_ACT_OZ_LB_UPPER[oz - 2],
                    lbMax: WT_ACT_OZ_LB_UPPER[oz - 1],
                };
            }

            function wtActOzFilterSlabLabel(oz) {
                const custom = WT_ACT_OZ_FILTER_SLABS[oz];
                if (custom && custom.label) return custom.label;
                const b = wtActOzFilterSlabBounds(oz);
                return `${b.ozMin}–${b.ozMax} oz (${wtActOzToLb(b.ozMin)} – ${wtActOzToLb(b.ozMax)} lb)`;
            }

            function wtActOz1599SlabLabel() {
                const s = WT_ACT_OZ_1599_SLAB;
                if (s.label) return s.label;
                return `${s.ozMin}–${s.ozMax} oz (${wtActOzToLb(s.ozMin)} – ${wtActOzToLb(s.ozMax)} lb)`;
            }

            /** Upward bands (lb); labels show oz range + converted lb unless `label` is set. */
            const WT_ACT_UPWARD_LB_BANDS = [
                { key: 'lb_101_2', lbMin: 1, lbMax: 2, label: '1 lb – 2 lb' },
                { key: 'lb_201_3', lbMin: 2.01, lbMax: 3, label: '2.01 lb – 3 lb' },
                { key: 'lb_301_4', lbMin: 3.01, lbMax: 4, label: '3.01 lb – 4 lb' },
                { key: 'lb_401_5', lbMin: 4.01, lbMax: 5, label: '4.01 lb – 5 lb' },
                { key: 'lb_501_6', lbMin: 5.01, lbMax: 6, label: '5.01 lb – 6 lb' },
                { key: 'lb_601_7', lbMin: 6.01, lbMax: 7, label: '6.01 lb – 7 lb' },
                { key: 'lb_701_8', lbMin: 7.01, lbMax: 8, label: '7.01 lb – 8 lb' },
                { key: 'lb_801_9', lbMin: 8.01, lbMax: 9, label: '8.01 lb – 9 lb' },
                { key: 'lb_901_10', lbMin: 9.01, lbMax: 10, label: '9.01 lb – 10 lb' },
                { key: 'lb_1001_11', lbMin: 10.01, lbMax: 11, label: '10.01 lb – 11 lb' },
                { key: 'lb_1101_12', lbMin: 11.01, lbMax: 12, label: '11.01 lb – 12 lb' },
                { key: 'lb_1201_13', lbMin: 12.01, lbMax: 13, label: '12.01 lb – 13 lb' },
                { key: 'lb_1301_14', lbMin: 13.01, lbMax: 14, label: '13.01 lb – 14 lb' },
                { key: 'lb_1401_20', lbMin: 14.01, lbMax: 20, label: '14.01 lb – 20 lb' },
                { key: 'lb_20_30', lbMin: 20.01, lbMax: 25, label: '20.01 lb – 25 lb' },
                { key: 'lb_2501_30', lbMin: 25.01, lbMax: 30, label: '25.01 lb – 30 lb' },
                { key: 'lb_30_40', lbMin: 30.01, lbMax: 40, label: '30.01 lb – 40 lb' },
                { key: 'lb_40_50', lbMin: 40.01, lbMax: 50, label: '40.01 lb – 50 lb' },
                { key: 'lb_gt50', lbMin: 50.01, lbMax: null, label: '> 50.01 lb' }
            ];

            function wtActLbBandOzMin(lb) {
                return Math.ceil(lb * 16 - 1e-9);
            }

            function wtActLbBandOzMax(lb) {
                return Math.floor(lb * 16 + 1e-9);
            }

            function wtActUpwardBandPrevMaxLb(index) {
                return index === 0 ? 1 : WT_ACT_UPWARD_LB_BANDS[index - 1].lbMax;
            }

            function wtActUpwardBandLabel(band, index) {
                if (band.label) return band.label;
                if (band.lbMax === null) {
                    const ozMin = Math.floor(wtActUpwardBandPrevMaxLb(index) * 16) + 1;
                    return `> ${ozMin} oz (> ${wtActOzToLb(ozMin)} lb)`;
                }
                const ozMin = Math.floor(wtActUpwardBandPrevMaxLb(index) * 16) + 1;
                const ozMax = Math.floor(band.lbMax * 16);
                return `${ozMin} oz – ${ozMax} oz (${wtActOzToLb(ozMin)} – ${wtActOzToLb(ozMax)} lb)`;
            }

            function populateWtActLbFilterOptions() {
                const sel = document.getElementById('filterWtAct');
                if (!sel) return;
                while (sel.options.length > 2) {
                    sel.remove(2);
                }
                const add = (value, label) => {
                    const o = document.createElement('option');
                    o.value = value;
                    o.textContent = label;
                    sel.appendChild(o);
                };
                add('lb_0', '0 lb');
                WT_ACT_OZ_FILTER_OPTIONS.forEach(oz => {
                    add(`oz_${oz}`, wtActOzFilterSlabLabel(oz));
                });
                add('oz_1599', wtActOz1599SlabLabel());
                WT_ACT_UPWARD_LB_BANDS.forEach((b, i) => add(b.key, wtActUpwardBandLabel(b, i)));
            }

            function matchesWtActOzLbBand(w, band) {
                if (band === 'oz_1599') {
                    const s = WT_ACT_OZ_1599_SLAB;
                    return w >= s.ozMin / 16 && w <= s.ozMax / 16;
                }
                const m = /^oz_(\d+)$/.exec(band);
                if (!m) return false;
                const oz = parseInt(m[1], 10);
                if (oz < 1 || oz > 15) return false;
                const b = wtActOzFilterSlabBounds(oz);
                if (WT_ACT_OZ_FILTER_SLABS[oz]) {
                    return w >= b.lbMin && w <= b.lbMax;
                }
                if (oz === 1) return w >= 0.01 && w <= b.lbMax;
                return w > b.lbMin && w <= b.lbMax;
            }

            function matchesWtActUpwardLbBand(w, band) {
                const idx = WT_ACT_UPWARD_LB_BANDS.findIndex(b => b.key === band);
                if (idx === -1) return false;
                const def = WT_ACT_UPWARD_LB_BANDS[idx];
                if (def.lbMax === null) {
                    const lower = def.lbMin != null ? def.lbMin : wtActUpwardBandPrevMaxLb(idx);
                    return def.lbMin != null ? w >= lower : w > lower;
                }
                if (def.lbMin != null) {
                    return w >= def.lbMin && w <= def.lbMax;
                }
                const lowerExclusive = wtActUpwardBandPrevMaxLb(idx);
                return w > lowerExclusive && w <= def.lbMax;
            }

            /** Item WT ACT (lb) preset bands (filterWtAct select values). */
            function matchesWtActLbBand(item, band) {
                if (!band || band === 'all') return true;
                if (band === 'missing') {
                    return itemWeightActMissing(item);
                }
                if (band === 'lb_0') {
                    const w = itemWeightActLbResolved(item);
                    return w === null || w === 0;
                }
                const w = itemWeightActLbResolved(item);
                if (w === null || !Number.isFinite(w)) return false;
                if (band === 'oz_1599' || /^oz_\d+$/.test(band)) {
                    return matchesWtActOzLbBand(w, band);
                }
                if (WT_ACT_UPWARD_LB_BANDS.some(b => b.key === band)) {
                    return matchesWtActUpwardLbBand(w, band);
                }
                return true;
            }

            // Apply all filters
            function applyFilters() {
                const filterSTATUS = document.getElementById('filterSTATUS');
                const filterStatusValue = filterSTATUS ? filterSTATUS.value : 'all';
                const filterWtActKg = document.getElementById('filterWtActKg').value;
                const filterWtAct = document.getElementById('filterWtAct').value;
                const filterWtDecl = document.getElementById('filterWtDecl').value;
                const filterL = document.getElementById('filterL').value;
                const filterW = document.getElementById('filterW').value;
                const filterH = document.getElementById('filterH').value;
                const filterShipCol = document.getElementById('filterShipCol')?.value || 'all';
                const filterShipBbCol = document.getElementById('filterShipBbCol')?.value || 'all';
                const filterTtShipCol = document.getElementById('filterTtShipCol')?.value || 'all';
                const filterTemuShipCol = document.getElementById('filterTemuShipCol')?.value || 'all';
                const filterEbay2ShipCol = document.getElementById('filterEbay2ShipCol')?.value || 'all';
                const filterSheinShipCol = document.getElementById('filterSheinShipCol')?.value || 'all';
                const filterGofoCol = document.getElementById('filterGofoCol')?.value || 'all';
                const filterTemuGofoCol = document.getElementById('filterTemuGofoCol')?.value || 'all';
                const filterFedexCol = document.getElementById('filterFedexCol')?.value || 'all';
                const filterUpsCol = document.getElementById('filterUpsCol')?.value || 'all';
                const filterUspsCol = document.getElementById('filterUspsCol')?.value || 'all';
                const filterUniCol = document.getElementById('filterUniCol')?.value || 'all';
                const hasMissingDataFilter = filterStatusValue === 'missing' || filterWtActKg === 'missing' || filterWtAct === 'missing' || filterWtAct === 'lb_0' || filterWtDecl === 'missing' ||
                                            filterL === 'missing' || filterW === 'missing' ||
                                            filterH === 'missing';

                const parentSearchVal = (document.getElementById('parentSearch')?.value || '').toLowerCase();
                const skuSearchVal = (document.getElementById('skuSearch')?.value || '').toLowerCase();
                const fbaSkuSearchVal = (document.getElementById('fbaSkuSearch')?.value || '').toLowerCase();

                filteredData = tableData.filter(item => {
                    if (!matchesRowTypeFilter(item)) return false;

                    // Exclude parent SKUs when any missing data filter is active
                    if (hasMissingDataFilter) {
                        if (isParentSkuItem(item)) return false;
                    }

                    if (parentSearchVal && !(item.Parent || '').toLowerCase().includes(parentSearchVal)) return false;
                    if (skuSearchVal && !(item.SKU || '').toLowerCase().includes(skuSearchVal)) return false;
                    if (fbaSkuSearchVal && !String(item.fba_sku || '').toLowerCase().includes(fbaSkuSearchVal)) return false;

                    // STATUS filter (same as product master: by value or missing)
                    if (filterStatusValue && filterStatusValue !== 'all') {
                        const statusVal = (item.status != null ? item.status : (item.Values && item.Values.status));
                        const raw = String(statusVal ?? '').trim();
                        if (filterStatusValue === 'missing') {
                            if (raw !== '') return false;
                        } else {
                            if (raw.toLowerCase() !== String(filterStatusValue).toLowerCase()) return false;
                        }
                    }

                    // Weight ACT (Kg) filter
                    if (filterWtActKg === 'missing' && !isMissing(item.wt_act_kg)) {
                        return false;
                    }

                    // WT ACT (lb) filter — resolved lb (from lb or converted kg)
                    if (filterWtAct !== 'all' && !matchesWtActLbBand(item, filterWtAct)) {
                        return false;
                    }

                    // WT DECL filter
                    if (filterWtDecl === 'missing' && !isMissing(item.wt_decl)) {
                        return false;
                    }

                    // L filter
                    if (filterL === 'missing' && !isMissing(item.l)) {
                        return false;
                    }

                    // W filter
                    if (filterW === 'missing' && !isMissing(item.w)) {
                        return false;
                    }

                    // H filter
                    if (filterH === 'missing' && !isMissing(item.h)) {
                        return false;
                    }

                    if (!matchesMarketplaceShipColFilter(item, 'ship', filterShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ship_bb', filterShipBbCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'tt_ship', filterTtShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'temu_ship', filterTemuShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ebay2_ship', filterEbay2ShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'shein_ship', filterSheinShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'gofo', filterGofoCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'temu_gofo', filterTemuGofoCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'fedex', filterFedexCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ups', filterUpsCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'usps', filterUspsCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'uni', filterUniCol)) return false;

                    return true;
                });
                renderTable(filteredData);
            }

            // Debounce helper for search inputs
            function debounce(fn, ms) {
                let t;
                return function() {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, arguments), ms);
                };
            }

            // Setup search and filter listeners (called once at init)
            function setupSearch() {
                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');
                const fbaSkuSearch = document.getElementById('fbaSkuSearch');
                const applyFiltersDebounced = debounce(applyFilters, 180);
                if (parentSearch) parentSearch.addEventListener('input', applyFiltersDebounced);
                if (skuSearch) skuSearch.addEventListener('input', applyFiltersDebounced);
                if (fbaSkuSearch) fbaSkuSearch.addEventListener('input', applyFiltersDebounced);

                const filterSTATUSEl = document.getElementById('filterSTATUS');
                if (filterSTATUSEl) filterSTATUSEl.addEventListener('change', applyFilters);
                const filterIds = ['filterWtActKg', 'filterWtAct', 'filterWtDecl', 'filterL', 'filterW', 'filterH'];
                filterIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('change', applyFilters);
                });
                ['filterShipCol', 'filterShipBbCol', 'filterTtShipCol', 'filterTemuShipCol', 'filterTemuGofoCol', 'filterEbay2ShipCol', 'filterSheinShipCol', 'filterGofoCol', 'filterFedexCol', 'filterUpsCol', 'filterUspsCol', 'filterUniCol'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('change', applyFilters);
                });
                const sectionFilterEl = document.getElementById('dimWtSectionFilter');
                if (sectionFilterEl) sectionFilterEl.addEventListener('change', applyDimWtSectionFilter);
                const rowTypeFilterEl = document.getElementById('shippingRowTypeFilter');
                if (rowTypeFilterEl) rowTypeFilterEl.addEventListener('change', function() {
                    if (isProductNavigationActive) {
                        showCurrentProductParent();
                    } else {
                        applyFilters();
                    }
                });
            }

            // Toast notification function
            function showToast(type, message) {
                // Remove existing toasts
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());
                
                const toast = document.createElement('div');
                toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    // Columns to export (excluding Image, Action, and Parent)
                    const columns = ["SKU", "Status", "INV", "Ship", "Ship BB", "TT 1 Ship", "Temu ship", "Temu GOFO", "Ebay2 ship", "Shein ship", "GOFO", "Fedex", "UPS", "USPS", "UNI", "Pick Pack", "Avg", "FBA SKU", "FBA ship", "FBA manual ship", "Weight ACT (Kg)", "WT ACT (LB)", "Item Weight (OZ)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CTN L (CM)", "CTN W (CM)", "CTN H (CM)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "SKU": {
                            key: "SKU"
                        },
                        "Status": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Ship": {
                            key: "ship"
                        },
                        "Ship BB": {
                            key: "ship_bb"
                        },
                            "TT 1 Ship": {
                                key: "tt_ship"
                        },
                        "Temu ship": {
                            key: "temu_ship"
                        },
                        "Temu GOFO": {
                            key: "temu_gofo"
                        },
                        "Ebay2 ship": {
                            key: "ebay2_ship"
                        },
                        "Shein ship": {
                            key: "shein_ship"
                        },
                        "GOFO": {
                            key: "gofo"
                        },
                        "Fedex": {
                            key: "fedex"
                        },
                        "UPS": {
                            key: "ups"
                        },
                        "USPS": {
                            key: "usps"
                        },
                        "UNI": {
                            key: "uni"
                        },
                        "Pick Pack": {
                            computed: "pick_pack"
                        },
                        "Avg": {
                            computed: "uni_avg"
                        },
                        "FBA SKU": {
                            key: "fba_sku"
                        },
                        "FBA ship": {
                            key: "fba_ship"
                        },
                        "FBA manual ship": {
                            key: "fba_manual_ship"
                        },
                        "Weight ACT (Kg)": {
                            key: "wt_act_kg"
                        },
                        "WT ACT (LB)": {
                            computed: "item_weight_lb"
                        },
                        "Item Weight (OZ)": {
                            computed: "item_weight_oz"
                        },
                        "WT DECL (LB)": {
                            key: "wt_decl"
                        },
                        "Length (inch)": {
                            key: "l"
                        },
                        "Width (inch)": {
                            key: "w"
                        },
                        "Height (Inch)": {
                            key: "h"
                        },
                        "Length (CM)": {
                            key: "l_cm"
                        },
                        "Width (CM)": {
                            key: "w_cm"
                        },
                        "Height (CM)": {
                            key: "h_cm"
                        },
                        "CTN L (CM)": {
                            key: "ctn_l"
                        },
                        "CTN W (CM)": {
                            key: "ctn_w"
                        },
                        "CTN H (CM)": {
                            key: "ctn_h"
                        },
                        "CTN (CBM)": {
                            key: "ctn_cbm"
                        },
                        "CTN (QTY)": {
                            key: "ctn_qty"
                        },
                        "CTN (CBM/Each)": {
                            key: "ctn_cbm_each"
                        }
                    };

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    document.getElementById('downloadExcel').disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Use filteredData if available, otherwise use tableData
                            const dataToExport = filteredData.length > 0 ? filteredData : tableData;

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows - exclude parent SKUs
                            dataToExport.forEach(item => {
                                // Skip parent SKUs (SKU contains "PARENT")
                                if (item.SKU && String(item.SKU).toUpperCase().includes('PARENT')) {
                                    return;
                                }
                                
                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        if (colDef.computed === 'pick_pack') {
                                            row.push(PICK_PACK_RATE);
                                            return;
                                        }
                                        if (colDef.computed === 'item_weight_lb') {
                                            const lb = itemWeightActLbResolved(item);
                                            row.push(lb === null ? '' : parseFloat(lb.toFixed(2)));
                                            return;
                                        }
                                        if (colDef.computed === 'item_weight_oz') {
                                            const oz = itemWeightActOzFromLb(itemWeightActLbResolved(item));
                                            row.push(oz === null ? '' : parseFloat(oz.toFixed(2)));
                                            return;
                                        }
                                        if (colDef.computed === 'uni_avg') {
                                            const avg = avgUniShipCarrierRates(item);
                                            row.push(avg === null ? '' : parseFloat(avg.toFixed(2)));
                                            return;
                                        }
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        // CTN CBM: calculated as CTN L * CTN W * CTN H / 1000000
                                        if (key === 'ctn_cbm') {
                                            value = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                                        }
                                        // CTN CBM each: calculated as CTN CBM / CTN Qty
                                        if (key === 'ctn_cbm_each') {
                                            const cbm = (parseFloat(item.ctn_l) || 0) * (parseFloat(item.ctn_w) || 0) * (parseFloat(item.ctn_h) || 0) / 1000000;
                                            const qty = parseFloat(item.ctn_qty) || 0;
                                            value = qty > 0 ? cbm / qty : 0;
                                        }
                                        // Format INV column
                                        if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
                                        }
                                        else if (key === "fba_sku") {
                                            value = value !== undefined && value !== null && value !== '' ? String(value) : '';
                                        }
                                        else if (key === "wt_act" || key === "wt_decl") {
                                            value = value === '' || value === null || value === undefined ? '' : parseFloat((parseFloat(value) || 0).toFixed(2));
                                        }
                                        // Format numeric columns (WT ACT KG, L, W, H, CBM, CTN fields, etc.)
                                        else if (["wt_act_kg", "l", "w", "h", "l_cm", "w_cm", "h_cm", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each", "ship", "tt_ship", "temu_ship", "ebay2_ship", "shein_ship", "gofo", "temu_gofo", "fedex", "ups", "usps", "uni", "fba_ship", "fba_manual_ship"].includes(key)) {
                                            value = value === '' || value === null || value === undefined ? '' : (parseFloat(value) || 0);
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Status"].includes(col)) {
                                    return { wch: 12 };
                                } else if (["FBA SKU", "Weight ACT (Kg)", "WT ACT (LB)", "Item Weight (OZ)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CTN (CBM)", "CTN (CBM/Each)", "Ship", "Ship BB", "TT 1 Ship", "Temu ship", "Temu GOFO", "Ebay2 ship", "GOFO", "Fedex", "UPS", "USPS", "UNI", "Pick Pack", "Avg", "FBA ship", "FBA manual ship"].includes(col)) {
                                    return { wch: 15 }; // Width for weight and CBM columns
                                } else {
                                    return { wch: 12 }; // Default width for numeric columns
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Shipping Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "shipping_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Download';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
                const importModal = document.getElementById('importExcelModal');
                const fileError = document.getElementById('fileError');
                const importProgress = document.getElementById('importProgress');
                const importResult = document.getElementById('importResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data with all columns
                    const sampleData = [
                        ['SKU', 'Ship', 'Ship BB', 'TT 1 Ship', 'Temu ship', 'Ebay2 ship', 'Shein ship', 'GOFO', 'Fedex', 'UPS', 'USPS', 'UNI', 'Weight ACT (Kg)', 'WT ACT (LB)', 'WT DECL (LB)', 'Length (inch)', 'Width (inch)', 'Height (Inch)', 'Length (CM)', 'Width (CM)', 'Height (CM)', 'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)'],
                        ['SKU001', '3.25', '3.10', '2.95', '3.15', '3.45', '3.20', '1.50', '4.20', '3.90', '2.80', '3.10', '6.2', '1.5', '1.2', '10.5', '8.3', '5.2', '26.67', '21.08', '13.21', '30', '25', '20', '0.015', '12', '0.00125'],
                        ['SKU002', '4.10', '3.95', '3.80', '4.00', '4.25', '4.05', '2.00', '5.10', '4.75', '3.50', '4.00', '9.1', '2.0', '1.8', '12.0', '9.0', '6.0', '30.48', '22.86', '15.24', '35', '28', '22', '0.0216', '15', '0.00144'],
                        ['SKU003', '2.80', '2.65', '2.60', '2.70', '2.95', '2.75', '1.20', '3.50', '3.20', '2.40', '2.70', '5.4', '1.2', '1.0', '9.5', '7.5', '4.5', '24.13', '19.05', '11.43', '28', '24', '18', '0.0121', '10', '0.00121']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Ship
                        { wch: 12 }, // Ship BB
                        { wch: 12 }, // TT 1 Ship
                        { wch: 12 }, // Temu ship
                        { wch: 12 }, // Ebay2 ship
                        { wch: 12 }, // Shein ship
                        { wch: 10 }, // GOFO
                        { wch: 10 }, // Fedex
                        { wch: 10 }, // UPS
                        { wch: 10 }, // USPS
                        { wch: 10 }, // UNI
                        { wch: 16 }, // Weight ACT (Kg)
                        { wch: 14 }, // WT ACT (LB)
                        { wch: 14 }, // WT DECL (LB)
                        { wch: 14 }, // Length (inch)
                        { wch: 12 }, // Width (inch)
                        { wch: 14 }, // Height (Inch)
                        { wch: 12 }, // Length (CM)
                        { wch: 12 }, // Width (CM)
                        { wch: 12 }, // Height (CM)
                        { wch: 14 }, // CTN L (CM)
                        { wch: 14 }, // CTN W (CM)
                        { wch: 14 }, // CTN H (CM)
                        { wch: 15 }, // CTN (CBM)
                        { wch: 12 }, // CTN (QTY)
                        { wch: 18 }  // CTN (CBM/Each)
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "Shipping Master Sample");
                    XLSX.writeFile(wb, "shipping_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/dim-wt-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                            
                        });
 
                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            // Select All checkbox functionality
            function setupSelectAll() {
                const selectAllCheckbox = document.getElementById('selectAll');
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.row-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updatePushButtonState();
                });
            }

            // Update Push Button State and Bulk Edit button
            function updatePushButtonState() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const pushBtn = document.getElementById('pushDataBtn');
                const bulkEditBtn = document.getElementById('bulkEditBtn');
                // Count non-parent selected (parent SKUs excluded from bulk edit)
                let nonParentCount = 0;
                checkedBoxes.forEach(cb => {
                    const sku = (cb.getAttribute('data-sku') || '').toUpperCase();
                    if (sku && !sku.includes('PARENT')) nonParentCount++;
                });
                if (checkedBoxes.length > 0) {
                    pushBtn.disabled = false;
                    pushBtn.innerHTML = `<i class="fas fa-cloud-upload-alt me-1"></i> Push Data (${checkedBoxes.length})`;
                } else {
                    pushBtn.disabled = true;
                    pushBtn.innerHTML = '<i class="fas fa-cloud-upload-alt me-1"></i> Push Data';
                }
                if (nonParentCount > 0) {
                    if (bulkEditBtn) {
                        bulkEditBtn.disabled = false;
                        bulkEditBtn.innerHTML = nonParentCount > 1
                            ? `<i class="fas fa-edit me-1"></i> Bulk Edit (${nonParentCount})`
                            : '<i class="fas fa-edit me-1"></i> Bulk Edit';
                    }
                } else {
                    if (bulkEditBtn) {
                        bulkEditBtn.disabled = true;
                        bulkEditBtn.innerHTML = '<i class="fas fa-edit me-1"></i> Bulk Edit';
                    }
                }
            }

            // Bulk Edit: open edit modal with first selected item; save updates all selected
            function setupBulkEdit() {
                const bulkEditBtn = document.getElementById('bulkEditBtn');
                if (!bulkEditBtn) return;
                bulkEditBtn.addEventListener('click', function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    const selected = [];
                    const seenIds = new Set();
                    checkedBoxes.forEach(checkbox => {
                        const sku = checkbox.getAttribute('data-sku');
                        if (!sku || String(sku).toUpperCase().includes('PARENT')) return;
                        const item = findProductByRowRef(checkbox);
                        if (!item || seenIds.has(String(item.id))) return;
                        seenIds.add(String(item.id));
                        selected.push(item);
                    });
                    if (selected.length === 0) {
                        showToast('warning', 'Please select at least one non-parent SKU to bulk edit');
                        return;
                    }
                    bulkEditList = selected;
                    editDimWt(selected[0]);
                });
            }

            // Push Data functionality
            function setupPushData() {
                document.getElementById('pushDataBtn').addEventListener('click', async function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    
                    if (checkedBoxes.length === 0) {
                        showToast('warning', 'Please select at least one SKU to push data');
                        return;
                    }

                    // Get selected SKUs and their data
                    const selectedSkus = [];
                    checkedBoxes.forEach(checkbox => {
                        const item = findProductByRowRef(checkbox);
                        if (item && item.SKU) {
                            selectedSkus.push({
                                sku: item.SKU,
                                id: item.id,
                                wt_act_kg: item.wt_act_kg || null,
                                wt_act: item.wt_act || null,
                                wt_decl: item.wt_decl || null,
                                l: item.l || null,
                                w: item.w || null,
                                h: item.h || null,
                                l_cm: item.l_cm || null,
                                w_cm: item.w_cm || null,
                                h_cm: item.h_cm || null
                            });
                        }
                    });

                    if (selectedSkus.length === 0) {
                        showToast('warning', 'No valid SKUs found to push');
                        return;
                    }

                    // Confirm action with details
                    const skuList = selectedSkus.map(s => s.sku).join(', ');
                    const confirmMessage = `Are you sure you want to push Shipping Master data (dimensions & weight) for ${selectedSkus.length} SKU(s) to ALL marketplaces?\n\n` +
                        `Selected SKUs: ${skuList.substring(0, 100)}${skuList.length > 100 ? '...' : ''}\n\n` +
                        `Data to be updated:\n` +
                        `- Weight (Weight ACT (Kg), WT ACT (LB), WT DECL (LB))\n` +
                        `- Dimensions (Length/Width/Height in inch and CM)\n\n` +
                        `This will update the data in: Amazon, eBay, Shopify, Walmart, Doba, Temu, and all other connected marketplaces.`;
                    
                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    const pushBtn = document.getElementById('pushDataBtn');
                    const originalText = pushBtn.innerHTML;
                    
                    try {
                        pushBtn.disabled = true;
                        pushBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Pushing...';
                        
                        const response = await makeRequest('/dim-wt-master/push-data', 'POST', {
                            skus: selectedSkus
                        });

                        const data = await response.json();
                        
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to push data');
                        }

                        // Build detailed success message
                        let messageHtml = `<div class="mb-3">`;
                        messageHtml += `<p class="mb-2"><strong><i class="fas fa-database text-info me-2"></i>Data saved to database for ${selectedSkus.length} SKU(s).</strong></p>`;
                        
                        if (data.results) {
                            const implementedPlatforms = ['amazon', 'shopify', 'ebay', 'ebay2', 'ebay3', 'walmart'];
                            const hasSuccess = Object.values(data.results).some(r => r.success > 0);
                            const hasFailures = Object.values(data.results).some(r => r.failed > 0);
                            
                            messageHtml += `<div class="mt-3">`;
                            messageHtml += `<p class="mb-2"><strong>Platform Update Results:</strong></p>`;
                            messageHtml += `<ul class="list-unstyled mb-0">`;
                            
                            Object.entries(data.results).forEach(([platform, result]) => {
                                const platformName = platform.charAt(0).toUpperCase() + platform.slice(1).replace(/_/g, ' ');
                                const isImplemented = implementedPlatforms.includes(platform.toLowerCase());
                                
                                if (result.success > 0) {
                                    messageHtml += `<li class="mb-1">`;
                                    messageHtml += `<i class="fas fa-check-circle text-success me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> `;
                                    messageHtml += `<span class="text-success">${result.success} updated successfully</span>`;
                                    if (result.failed > 0) {
                                        messageHtml += `, <span class="text-danger">${result.failed} failed</span>`;
                                    }
                                    messageHtml += `</li>`;
                                } else if (result.failed > 0) {
                                    messageHtml += `<li class="mb-1">`;
                                    messageHtml += `<i class="fas fa-times-circle text-danger me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> `;
                                    messageHtml += `<span class="text-danger">${result.failed} failed</span>`;
                                    messageHtml += `</li>`;
                                } else if (!isImplemented) {
                                    messageHtml += `<li class="mb-1 text-muted">`;
                                    messageHtml += `<i class="fas fa-clock me-2"></i>`;
                                    messageHtml += `<strong>${platformName}:</strong> API integration pending`;
                                    messageHtml += `</li>`;
                                }
                            });
                            
                            messageHtml += `</ul>`;
                            
                            if (hasSuccess) {
                                messageHtml += `<div class="alert alert-success mt-3 mb-0">`;
                                messageHtml += `<i class="fas fa-check-circle me-2"></i>`;
                                messageHtml += `<strong>Success!</strong> Shipping Master data has been updated on the marketplace platforms above.`;
                                messageHtml += `</div>`;
                            }
                            
                            if (data.errors && data.errors.length > 0) {
                                messageHtml += `<div class="alert alert-warning mt-2 mb-0">`;
                                messageHtml += `<i class="fas fa-exclamation-triangle me-2"></i>`;
                                messageHtml += `<strong>Some errors occurred:</strong>`;
                                messageHtml += `<ul class="mb-0 mt-2 small">`;
                                data.errors.slice(0, 5).forEach(error => {
                                    messageHtml += `<li>${error}</li>`;
                                });
                                if (data.errors.length > 5) {
                                    messageHtml += `<li><em>... and ${data.errors.length - 5} more errors</em></li>`;
                                }
                                messageHtml += `</ul>`;
                                messageHtml += `</div>`;
                            }
                            
                            messageHtml += `</div>`;
                        }
                        messageHtml += `</div>`;

                        // Show success modal
                        const successModal = document.getElementById('pushDataSuccessModal');
                        const messageDiv = document.getElementById('pushDataSuccessMessage');
                        messageDiv.innerHTML = messageHtml;
                        
                        const modal = new bootstrap.Modal(successModal);
                        modal.show();
                        
                        // Uncheck all checkboxes
                        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
                        document.getElementById('selectAll').checked = false;
                        updatePushButtonState();
                        
                    } catch (error) {
                        console.error('Error pushing data:', error);
                        showToast('danger', error.message || 'Failed to push data to platforms');
                    } finally {
                        pushBtn.innerHTML = originalText;
                        pushBtn.disabled = false;
                        updatePushButtonState();
                    }
                });
            }

            // Calculate CTN (CBM) = CTN L (CM) × CTN W (CM) × CTN H (CM) / 1000000
            function calculateCtnCbm(ctnL, ctnW, ctnH) {
                if (!ctnL || !ctnW || !ctnH) return 0;
                const l = parseFloat(ctnL) || 0;
                const w = parseFloat(ctnW) || 0;
                const h = parseFloat(ctnH) || 0;
                return (l * w * h) / 1000000;
            }

            // Calculate CTN (CBM/Each) = CTN (CBM) / CTN (QTY)
            function calculateCtnCbmEach(ctnCbm, ctnQty) {
                if (!ctnCbm || !ctnQty || parseFloat(ctnQty) === 0) return 0;
                const cbm = parseFloat(ctnCbm) || 0;
                const qty = parseFloat(ctnQty) || 0;
                return qty > 0 ? cbm / qty : 0;
            }

            // Edit Shipping Master (modal)
            function editDimWt(product) {
                const modalEl = document.getElementById('editDimWtModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                document.getElementById('editDimWtModalLabel').textContent = (bulkEditList && bulkEditList.length > 0)
                    ? ('Bulk Edit (' + bulkEditList.length + ' items)')
                    : 'Edit Shipping Master';
                
                // Populate form fields
                document.getElementById('editProductId').value = product.id || '';
                document.getElementById('editSku').value = product.SKU || '';
                document.getElementById('editParent').value = product.Parent || '';
                document.getElementById('editWtActKg').value = product.wt_act_kg || '';
                document.getElementById('editWtAct').value = product.wt_act || '';
                document.getElementById('editWtDecl').value = product.wt_decl || '';
                document.getElementById('editL').value = product.l || '';
                document.getElementById('editW').value = product.w || '';
                document.getElementById('editH').value = product.h || '';
                document.getElementById('editLCm').value = product.l_cm || '';
                document.getElementById('editWCm').value = product.w_cm || '';
                document.getElementById('editHCm').value = product.h_cm || '';
                document.getElementById('editCtnL').value = product.ctn_l || '';
                document.getElementById('editCtnW').value = product.ctn_w || '';
                document.getElementById('editCtnH').value = product.ctn_h || '';
                // Populate auto-calculated inch fields
                // Auto-populate Item CM fields from inch values if CM is missing
                const lValInch = parseFloat(product.l) || 0;
                const wValInch = parseFloat(product.w) || 0;
                const hValInch = parseFloat(product.h) || 0;
                if (!product.l_cm && lValInch) {
                    document.getElementById('editLCm').value = (lValInch * 2.54).toFixed(2);
                }
                if (!product.w_cm && wValInch) {
                    document.getElementById('editWCm').value = (wValInch * 2.54).toFixed(2);
                }
                if (!product.h_cm && hValInch) {
                    document.getElementById('editHCm').value = (hValInch * 2.54).toFixed(2);
                }

                const ctnLVal = parseFloat(product.ctn_l) || 0;
                const ctnWVal = parseFloat(product.ctn_w) || 0;
                const ctnHVal = parseFloat(product.ctn_h) || 0;
                document.getElementById('editCtnLInch').value = ctnLVal ? (ctnLVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnWInch').value = ctnWVal ? (ctnWVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnHInch').value = ctnHVal ? (ctnHVal / 2.54).toFixed(2) : '';
                document.getElementById('editCtnQty').value = product.ctn_qty || '';
                document.getElementById('editCtnWeightKg').value = product.ctn_weight_kg || '';

                const shipNum = (v) => (v !== null && v !== undefined && v !== '' && !Number.isNaN(parseFloat(v))) ? String(parseFloat(v)) : '';
                document.getElementById('editShip').value = shipNum(product.ship);
                document.getElementById('editShipBb').value = shipNum(product.ship_bb);
                document.getElementById('editTtShip').value = shipNum(product.tt_ship);
                document.getElementById('editTemuShip').value = shipNum(product.temu_ship);
                document.getElementById('editEbay2Ship').value = shipNum(product.ebay2_ship);
                document.getElementById('editSheinShip').value = shipNum(product.shein_ship);
                document.getElementById('editGofo').value = shipNum(product.gofo);
                document.getElementById('editTemuGofo').value = shipNum(product.temu_gofo);
                document.getElementById('editFedex').value = shipNum(product.fedex);
                document.getElementById('editUps').value = shipNum(product.ups);
                document.getElementById('editUsps').value = shipNum(product.usps);
                document.getElementById('editUni').value = shipNum(product.uni);
                document.getElementById('editFbaShip').value = shipNum(product.fba_ship);
                document.getElementById('editFbaManualShip').value = shipNum(product.fba_manual_ship);
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveDimWt();
                });
                
                modal.show();
            }

            // Save Shipping Master (single or bulk)
            async function saveDimWt() {
                const saveBtn = document.getElementById('saveDimWtBtn');
                if (!saveBtn) return;
                const originalText = saveBtn.innerHTML;
                const bulkTargets = (bulkEditList && bulkEditList.length > 0) ? bulkEditList.slice() : null;
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    // Calculate CTN CBM and CTN CBM/Each
                    const ctnL = parseFloat(document.getElementById('editCtnL').value) || 0;
                    const ctnW = parseFloat(document.getElementById('editCtnW').value) || 0;
                    const ctnH = parseFloat(document.getElementById('editCtnH').value) || 0;
                    const ctnQty = parseFloat(document.getElementById('editCtnQty').value) || 0;
                    const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                    const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);

                    const baseFormData = {
                        wt_act_kg: document.getElementById('editWtActKg').value.trim() || null,
                        wt_act: document.getElementById('editWtAct').value.trim() || null,
                        wt_decl: document.getElementById('editWtDecl').value.trim() || null,
                        l: document.getElementById('editL').value.trim() || null,
                        w: document.getElementById('editW').value.trim() || null,
                        h: document.getElementById('editH').value.trim() || null,
                        l_cm: document.getElementById('editLCm').value.trim() || null,
                        w_cm: document.getElementById('editWCm').value.trim() || null,
                        h_cm: document.getElementById('editHCm').value.trim() || null,
                        ctn_l: document.getElementById('editCtnL').value.trim() || null,
                        ctn_w: document.getElementById('editCtnW').value.trim() || null,
                        ctn_h: document.getElementById('editCtnH').value.trim() || null,
                        ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
                        ctn_qty: document.getElementById('editCtnQty').value.trim() || null,
                        ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
                        ctn_weight_kg: document.getElementById('editCtnWeightKg').value.trim() || null,
                        ctn_weight_lb: (parseFloat(document.getElementById('editCtnWeightKg').value) || 0) * 2.21
                    };

                    const addNumericIfPresent = (inputId, propName) => {
                        const t = document.getElementById(inputId).value.trim();
                        if (t === '') return;
                        const n = parseFloat(t);
                        if (Number.isFinite(n)) baseFormData[propName] = n;
                    };
                    addNumericIfPresent('editShip', 'ship');
                    addNumericIfPresent('editShipBb', 'ship_bb');
                    addNumericIfPresent('editTtShip', 'tt_ship');
                    addNumericIfPresent('editTemuShip', 'temu_ship');
                    addNumericIfPresent('editEbay2Ship', 'ebay2_ship');
                    addNumericIfPresent('editSheinShip', 'shein_ship');
                    addNumericIfPresent('editGofo', 'gofo');
                    addNumericIfPresent('editTemuGofo', 'temu_gofo');
                    addNumericIfPresent('editFedex', 'fedex');
                    addNumericIfPresent('editUps', 'ups');
                    addNumericIfPresent('editUsps', 'usps');
                    addNumericIfPresent('editUni', 'uni');

                    const fbaShipStr = document.getElementById('editFbaShip').value.trim();
                    const fbaManualStr = document.getElementById('editFbaManualShip').value.trim();
                    if (fbaShipStr !== '' || fbaManualStr !== '') {
                        if (fbaShipStr !== '') {
                            const n = parseFloat(fbaShipStr);
                            if (Number.isFinite(n)) baseFormData.fba_ship_calculation = n;
                        }
                        if (fbaManualStr !== '') {
                            const n = parseFloat(fbaManualStr);
                            if (Number.isFinite(n)) baseFormData.fba_manual_ship = n;
                        }
                    }
                    
                    if (bulkTargets && bulkTargets.length > 0) {
                        let successCount = 0;
                        let failCount = 0;
                        for (const product of bulkTargets) {
                            const formData = {
                                ...baseFormData,
                                product_id: product.id,
                                sku: product.SKU,
                                parent: product.Parent || ''
                            };
                            try {
                                const response = await fetch('/dim-wt-master/update', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify(formData)
                                });
                                const data = await response.json();
                                if (response.ok) successCount++; else failCount++;
                            } catch (e) {
                                failCount++;
                            }
                        }
                        bulkEditList = null;
                        document.getElementById('editDimWtModalLabel').textContent = 'Edit Shipping Master';
                        if (failCount === 0) {
                            showToast('success', successCount + ' item(s) updated successfully!');
                        } else {
                            showToast('warning', successCount + ' updated, ' + failCount + ' failed.');
                        }
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                        modal.hide();
                        loadData();
                        updatePushButtonState();
                        return;
                    }
                    
                    const formData = {
                        ...baseFormData,
                        product_id: document.getElementById('editProductId').value,
                        sku: document.getElementById('editSku').value,
                        parent: document.getElementById('editParent').value
                    };
                    
                    const response = await fetch('/dim-wt-master/update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to save data');
                    }
                    
                    showToast('success', 'Shipping Master updated successfully!');
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                    modal.hide();
                    
                    loadData();
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            /*
             * ============================================================================
             * Shipping Master change history
             * ----------------------------------------------------------------------------
             * GET /shipping-master/history/{id} returns the per-field edit log written by
             * CategoryController::updateDimWtMaster (one row per changed field per save).
             * The History button in the Action column triggers openShippingHistoryModal().
             * ============================================================================
             */
            function shippingHistoryFmtValue(v) {
                if (v === null || v === undefined || v === '') {
                    return '<span class="text-muted fst-italic">empty</span>';
                }
                return escapeHtml(String(v));
            }

            async function openShippingHistoryModal(productId, sku) {
                const modalEl = document.getElementById('shippingHistoryModal');
                if (!modalEl) return;
                const skuLabel = document.getElementById('shippingHistorySku');
                const loadingEl = document.getElementById('shippingHistoryLoading');
                const emptyEl = document.getElementById('shippingHistoryEmpty');
                const errorEl = document.getElementById('shippingHistoryError');
                const tableWrap = document.getElementById('shippingHistoryTableWrap');
                const tbody = document.getElementById('shippingHistoryTbody');

                if (skuLabel) skuLabel.textContent = sku || '';
                if (loadingEl) loadingEl.style.display = 'block';
                if (emptyEl) emptyEl.style.display = 'none';
                if (errorEl) { errorEl.style.display = 'none'; errorEl.textContent = ''; }
                if (tableWrap) tableWrap.style.display = 'none';
                if (tbody) tbody.innerHTML = '';

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();

                if (productId == null || productId === '') {
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (errorEl) {
                        errorEl.textContent = 'This row does not have an internal id, so history cannot be loaded.';
                        errorEl.style.display = 'block';
                    }
                    return;
                }

                try {
                    const response = await fetch(`/shipping-master/history/${encodeURIComponent(productId)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load history.');
                    }

                    const rows = Array.isArray(data.history) ? data.history : [];
                    if (rows.length === 0) {
                        if (emptyEl) emptyEl.style.display = 'block';
                        return;
                    }

                    const html = rows.map(r => `
                        <tr>
                            <td style="white-space:nowrap;font-size:12px;">${escapeHtml(r.updated_at || '')}</td>
                            <td style="white-space:nowrap;"><span class="badge bg-secondary">${escapeHtml(r.updated_by || 'N/A')}</span></td>
                            <td><strong>${escapeHtml(r.field_label || r.field || '')}</strong></td>
                            <td class="text-danger">${shippingHistoryFmtValue(r.old_value)}</td>
                            <td class="text-success fw-semibold">${shippingHistoryFmtValue(r.new_value)}</td>
                        </tr>
                    `).join('');
                    tbody.innerHTML = html;
                    if (tableWrap) tableWrap.style.display = 'block';
                } catch (err) {
                    console.error('Shipping history load error:', err);
                    if (errorEl) {
                        errorEl.textContent = err.message || 'Failed to load history.';
                        errorEl.style.display = 'block';
                    }
                } finally {
                    if (loadingEl) loadingEl.style.display = 'none';
                }
            }

            // Verified column – red/green dot toggle (event delegation)
            function setupVerifiedDropdowns() {
                document.addEventListener('change', function(e) {
                    if (!e.target || !e.target.classList.contains('verified-data-dropdown')) return;
                    const dropdown = e.target;
                    const sku = dropdown.getAttribute('data-sku');
                    const isVerified = dropdown.value === '1';
                    dropdown.classList.toggle('verified', isVerified);
                    dropdown.classList.toggle('not-verified', !isVerified);
                    dropdown.title = isVerified ? 'Verified' : 'Not verified';
                    const verifiedValue = isVerified ? 1 : 0;
                    makeRequest('/product_master/update-verified', 'POST', { sku: sku, verified_data: verifiedValue })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const product = tableData.find(d => d.SKU === sku);
                                if (product) {
                                    product.verified_data = verifiedValue;
                                    if (product.Values) product.Values.verified_data = verifiedValue;
                                    else if (!product.Values) product.Values = { verified_data: verifiedValue };
                                }
                            } else {
                                showToast('danger', data.message || 'Failed to update verified status');
                                dropdown.value = verifiedValue === 1 ? '0' : '1';
                                dropdown.classList.toggle('verified', verifiedValue === 0);
                                dropdown.classList.toggle('not-verified', verifiedValue === 1);
                            }
                        })
                        .catch(() => {
                            showToast('danger', 'Failed to update verified status');
                            dropdown.value = verifiedValue === 1 ? '0' : '1';
                            dropdown.classList.toggle('verified', verifiedValue !== 1);
                            dropdown.classList.toggle('not-verified', verifiedValue === 1);
                            dropdown.title = verifiedValue === 1 ? 'Not verified' : 'Verified';
                        });
                });
            }
            setupVerifiedDropdowns();

            populateWtActLbFilterOptions();

            // Initialize (search and playback listeners once to avoid duplicates on reload)
            setupSearch();
            setupProductPlaybackListeners();
            loadData();
            setupExcelExport();
            setupImport();
            setupSelectAll();
            setupBulkEdit();
            setupPushData();
            setupSlabRates();
            setupMissingIndicatorClicks();
            // Reset bulk edit state when edit modal is closed (e.g. without saving)
            document.getElementById('editDimWtModal').addEventListener('hidden.bs.modal', function() {
                bulkEditList = null;
                document.getElementById('editDimWtModalLabel').textContent = 'Edit Shipping Master';
            });

            // Slab Rates: apply per-carrier rates to all SKUs in a weight slab.
            // Uses the same Item WT ACT (LB) bands as the filter dropdown so
            // "what you filter" matches "what gets the rate".

            // Carriers shown as columns in the slab matrix. Keys match the
            // backend /dim-wt-master/update payload fields (and Values keys).
            const SLAB_RATE_CARRIERS = [
                { key: 'ship',       label: 'Ship' },
                { key: 'ship_bb',    label: 'Ship BB' },
                { key: 'tt_ship',    label: 'TT 1 Ship' },
                { key: 'temu_ship',  label: 'Temu ship' },
                { key: 'temu_gofo',  label: 'Temu GOFO' },
                { key: 'ebay2_ship', label: 'Ebay2 ship' },
                { key: 'shein_ship', label: 'Shein ship' },
                { key: 'gofo',       label: 'GOFO' },
                { key: 'fedex',      label: 'Fedex' },
                { key: 'ups',        label: 'UPS' },
                { key: 'usps',       label: 'USPS' },
                { key: 'uni',        label: 'UNI' }
            ];

            function getSlabDefinitions() {
                const slabs = [{ key: 'lb_0', label: '0 lb' }];
                WT_ACT_OZ_FILTER_OPTIONS.forEach(oz => {
                    slabs.push({ key: `oz_${oz}`, label: wtActOzFilterSlabLabel(oz) });
                });
                slabs.push({ key: 'oz_1599', label: wtActOz1599SlabLabel() });
                WT_ACT_UPWARD_LB_BANDS.forEach((b, i) => {
                    slabs.push({ key: b.key, label: wtActUpwardBandLabel(b, i) });
                });
                return slabs;
            }

            function getNonParentItemsInSlab(slabKey) {
                if (!Array.isArray(tableData)) return [];
                return tableData.filter(item =>
                    item && !isParentSkuItem(item) && matchesWtActLbBand(item, slabKey)
                );
            }

            function isCarrierValueMissing(item, carrierKey) {
                const v = item ? item[carrierKey] : null;
                if (v === null || v === undefined || v === '') return true;
                const n = parseFloat(v);
                return !Number.isFinite(n);
            }

            // Round to 2 decimals so 5.4900 and 5.49 are treated as equal when
            // deciding whether all SKUs in a slab share the same carrier rate.
            function normalizeSlabRate(n) {
                if (!Number.isFinite(n)) return null;
                return Math.round(n * 100) / 100;
            }

            /** Summarize what the table currently holds for one (slab, carrier).
             *  - uniformValue: the single numeric value if every non-missing SKU
             *    in the slab shares it (and at least one SKU is non-missing),
             *    otherwise null.
             *  - distinctValues: sorted list of distinct rounded numeric values.
             *  - filled / missing: counts. */
            function computeSlabCarrierSummary(items, carrierKey) {
                const distinctSet = new Map(); // rounded -> count
                let filled = 0;
                let missing = 0;
                items.forEach(it => {
                    const raw = it ? it[carrierKey] : null;
                    if (raw === null || raw === undefined || raw === '') { missing++; return; }
                    const n = parseFloat(raw);
                    if (!Number.isFinite(n)) { missing++; return; }
                    filled++;
                    const r = normalizeSlabRate(n);
                    distinctSet.set(r, (distinctSet.get(r) || 0) + 1);
                });
                const distinctValues = Array.from(distinctSet.keys()).sort((a, b) => a - b);
                const uniformValue = (filled > 0 && distinctValues.length === 1 && missing === 0)
                    ? distinctValues[0]
                    : null;
                return { uniformValue, distinctValues, filled, missing };
            }

            function formatSlabRate(n) {
                if (!Number.isFinite(n)) return '';
                return (Math.round(n * 100) / 100).toFixed(2);
            }

            function buildSlabRatesTableHead() {
                const headRow = document.getElementById('slabRatesHeadRow');
                if (!headRow) return;
                // Remove any previously injected carrier headers (keep first two columns)
                while (headRow.children.length > 2) headRow.removeChild(headRow.lastChild);
                SLAB_RATE_CARRIERS.forEach(c => {
                    const th = document.createElement('th');
                    th.className = 'text-center slab-rates-carrier-col';
                    th.style.fontSize = '12px';
                    th.title = `${c.label} rate ($)`;
                    th.textContent = c.label;
                    headRow.appendChild(th);
                });
            }

            function populateFillRowSlabTarget(slabs) {
                const sel = document.getElementById('slabRatesFillRowTarget');
                if (!sel) return;
                while (sel.options.length > 1) sel.remove(1);
                slabs.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.key;
                    opt.textContent = s.label;
                    sel.appendChild(opt);
                });
            }

            function buildSlabRatesTable() {
                buildSlabRatesTableHead();
                const body = document.getElementById('slabRatesBody');
                if (!body) return;
                const slabs = getSlabDefinitions();
                populateFillRowSlabTarget(slabs);
                body.innerHTML = '';
                const carrierCols = SLAB_RATE_CARRIERS.length;
                slabs.forEach(slab => {
                    const items = getNonParentItemsInSlab(slab.key);
                    const total = items.length;
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-slab-key', slab.key);
                    if (total === 0) tr.classList.add('slab-row-empty');

                    const tdSlab = document.createElement('td');
                    tdSlab.className = 'slab-rates-sticky-col';
                    tdSlab.style.fontSize = '12px';
                    tdSlab.textContent = slab.label;
                    tr.appendChild(tdSlab);

                    const tdCount = document.createElement('td');
                    tdCount.className = 'text-center slab-count-cell';
                    tdCount.style.fontSize = '12px';
                    tdCount.innerHTML = `<span class="badge bg-secondary" title="${total} non-parent SKU(s) match this slab">${total}</span>`;
                    tr.appendChild(tdCount);

                    SLAB_RATE_CARRIERS.forEach(c => {
                        const td = document.createElement('td');
                        td.className = 'slab-rates-carrier-cell';
                        const summary = total === 0
                            ? { uniformValue: null, distinctValues: [], filled: 0, missing: 0 }
                            : computeSlabCarrierSummary(items, c.key);
                        const inp = document.createElement('input');
                        inp.type = 'number';
                        inp.step = '0.01';
                        inp.min = '0';
                        inp.className = 'form-control form-control-sm slab-rate-input';
                        inp.setAttribute('data-slab-key', slab.key);
                        inp.setAttribute('data-carrier-key', c.key);
                        inp.placeholder = '—';

                        const baseInfo = total === 0
                            ? 'No SKUs in this slab'
                            : `${c.label} — ${total} SKU(s) in slab, ${summary.missing} missing this value`;

                        if (summary.uniformValue !== null) {
                            // Every SKU in the slab already shares the same value.
                            // Pre-fill so the user can see "what's currently in the table".
                            const formatted = formatSlabRate(summary.uniformValue);
                            inp.value = formatted;
                            inp.setAttribute('data-original', formatted);
                            inp.classList.add('slab-rate-prefilled');
                            inp.title = `${baseInfo}\nAll ${total} SKU(s) currently have ${c.label} = $${formatted}.\nEdit the value to overwrite; leave as-is to skip.`;
                        } else if (summary.distinctValues.length > 0) {
                            // Mixed: at least two different non-missing values OR
                            // some missing + some filled. Don't pre-fill (it would
                            // be misleading) but tell the user what's in there.
                            const sample = summary.distinctValues
                                .slice(0, 6)
                                .map(v => '$' + formatSlabRate(v))
                                .join(', ');
                            const moreCount = summary.distinctValues.length - 6;
                            const more = moreCount > 0 ? ` +${moreCount} more` : '';
                            inp.placeholder = 'mixed';
                            inp.classList.add('slab-rate-mixed');
                            inp.setAttribute('data-original', '');
                            inp.title = `${baseInfo}\nCurrent values: ${sample}${more}\n${summary.filled} filled, ${summary.missing} missing.\nType a value to set it for every SKU in this slab.`;
                        } else {
                            inp.setAttribute('data-original', '');
                            inp.title = baseInfo;
                        }

                        if (total === 0) inp.disabled = true;

                        // Remove "prefilled" hint as soon as the user actually
                        // edits the cell so they can see their input is recognised.
                        inp.addEventListener('input', function () {
                            inp.classList.remove('slab-rate-prefilled');
                            inp.classList.remove('slab-rate-mixed');
                        });

                        td.appendChild(inp);
                        tr.appendChild(td);
                    });

                    body.appendChild(tr);
                });

                // Adjust the loading-placeholder colspan (now 2 + N carriers)
                const placeholder = body.querySelector('td[colspan]');
                if (placeholder) placeholder.setAttribute('colspan', String(2 + carrierCols));
            }

            function openSlabRatesModal() {
                if (!Array.isArray(tableData) || tableData.length === 0) {
                    showToast('warning', 'Data is still loading. Please try again in a moment.');
                    return;
                }
                buildSlabRatesTable();
                const progress = document.getElementById('slabRatesProgress');
                if (progress) progress.style.display = 'none';
                const modalEl = document.getElementById('slabRatesModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }

            async function applySlabRates() {
                const inputs = document.querySelectorAll('#slabRatesBody .slab-rate-input');
                const scope = (document.getElementById('slabRatesScope') || {}).value || 'all';

                // Group writes by SKU so each SKU is sent once with every relevant carrier.
                // perSku[id] = { item, fields: { carrierKey: rate, ... } }
                const perSku = new Map();
                let totalCellsApplied = 0;
                let totalWritesPlanned = 0;
                const carriersTouched = new Set();
                const slabsTouched = new Set();

                inputs.forEach(inp => {
                    const raw = String(inp.value || '').trim();
                    if (raw === '') return;
                    const rate = parseFloat(raw);
                    if (!Number.isFinite(rate) || rate < 0) return;
                    const slabKey = inp.getAttribute('data-slab-key');
                    const carrierKey = inp.getAttribute('data-carrier-key');
                    if (!slabKey || !carrierKey) return;

                    // Skip prefilled values the user didn't touch — opening the
                    // modal and hitting Apply without edits should be a no-op.
                    const original = inp.getAttribute('data-original') || '';
                    if (original !== '' && !inp.classList.contains('slab-rate-mixed')) {
                        const origNum = parseFloat(original);
                        if (Number.isFinite(origNum) && normalizeSlabRate(origNum) === normalizeSlabRate(rate)) {
                            return;
                        }
                    }

                    let items = getNonParentItemsInSlab(slabKey);
                    if (scope === 'missing') items = items.filter(it => isCarrierValueMissing(it, carrierKey));
                    if (items.length === 0) return;

                    totalCellsApplied++;
                    carriersTouched.add(carrierKey);
                    slabsTouched.add(slabKey);

                    items.forEach(item => {
                        const id = String(item.id);
                        if (!perSku.has(id)) perSku.set(id, { item, fields: {} });
                        perSku.get(id).fields[carrierKey] = rate;
                        totalWritesPlanned++;
                    });
                });

                if (perSku.size === 0) {
                    showToast('warning', 'Nothing to apply. Type a new rate (or change a prefilled one) in at least one (slab × carrier) cell that has matching SKUs.');
                    return;
                }

                const skuList = Array.from(perSku.values()).map(v => v.item.SKU);
                const previewSkus = skuList.slice(0, 5).join(', ');
                const moreSkus = skuList.length > 5 ? `, +${skuList.length - 5} more` : '';
                const carrierLabel = Array.from(carriersTouched)
                    .map(k => (SLAB_RATE_CARRIERS.find(c => c.key === k) || {}).label || k)
                    .join(', ');
                const confirmMsg =
                    `Apply rates to ${perSku.size} SKU(s) across ${slabsTouched.size} slab(s)?\n\n` +
                    `Carriers updated: ${carrierLabel}\n` +
                    `Total cell writes: ${totalWritesPlanned}\n\n` +
                    `Sample SKUs: ${previewSkus}${moreSkus}\n\n` +
                    (scope === 'missing'
                        ? 'Scope: only SKUs missing that carrier value will be updated.'
                        : 'Scope: existing carrier values will be overwritten.');
                if (!confirm(confirmMsg)) return;

                const applyBtn = document.getElementById('slabRatesApplyBtn');
                const progressWrap = document.getElementById('slabRatesProgress');
                const progressBar = progressWrap ? progressWrap.querySelector('.progress-bar') : null;
                const progressCount = document.getElementById('slabRatesProgressCount');
                const progressLabel = document.getElementById('slabRatesProgressLabel');
                const originalText = applyBtn ? applyBtn.innerHTML : '';

                if (applyBtn) { applyBtn.disabled = true; applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Applying…'; }
                if (progressWrap) progressWrap.style.display = 'block';
                if (progressLabel) progressLabel.textContent = 'Applying slab rates…';

                const entries = Array.from(perSku.values());
                let success = 0;
                let failed = 0;

                for (let i = 0; i < entries.length; i++) {
                    const { item, fields } = entries[i];
                    const payload = {
                        product_id: item.id,
                        sku: item.SKU,
                        parent: item.Parent || '',
                        ...fields
                    };
                    try {
                        const response = await fetch('/dim-wt-master/update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(payload)
                        });
                        if (response.ok) success++; else failed++;
                    } catch (e) {
                        failed++;
                    }

                    const done = i + 1;
                    const pct = Math.round((done / entries.length) * 100);
                    if (progressBar) progressBar.style.width = pct + '%';
                    if (progressCount) progressCount.textContent = `${done} / ${entries.length}`;
                }

                if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = originalText; }

                if (failed === 0) {
                    showToast('success', `Applied to ${success} SKU(s) across ${slabsTouched.size} slab(s).`);
                } else {
                    showToast('warning', `${success} updated, ${failed} failed.`);
                }

                const modalEl = document.getElementById('slabRatesModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                loadData();
            }

            function fillSlabRow() {
                const valueEl = document.getElementById('slabRatesFillRow');
                const slabSel = document.getElementById('slabRatesFillRowTarget');
                if (!valueEl || !slabSel) return;
                const raw = String(valueEl.value || '').trim();
                const slabKey = slabSel.value;
                if (raw === '' || !slabKey) {
                    showToast('warning', 'Enter a value and pick a slab to fill.');
                    return;
                }
                const n = parseFloat(raw);
                if (!Number.isFinite(n) || n < 0) {
                    showToast('warning', 'Enter a valid non-negative number to fill.');
                    return;
                }
                const row = document.querySelector(`#slabRatesBody tr[data-slab-key="${CSS.escape(slabKey)}"]`);
                if (!row) return;
                let filled = 0;
                row.querySelectorAll('.slab-rate-input').forEach(inp => {
                    if (inp.disabled) return;
                    if (String(inp.value || '').trim() === '') {
                        inp.value = String(n);
                        filled++;
                    }
                });
                if (filled === 0) showToast('info', 'No empty carrier cells were filled (all already had values).');
            }

            function setupSlabRates() {
                const openBtn = document.getElementById('slabRatesBtn');
                if (openBtn) openBtn.addEventListener('click', openSlabRatesModal);

                const applyBtn = document.getElementById('slabRatesApplyBtn');
                if (applyBtn) applyBtn.addEventListener('click', applySlabRates);

                const clearBtn = document.getElementById('slabRatesClearBtn');
                if (clearBtn) clearBtn.addEventListener('click', function () {
                    document.querySelectorAll('#slabRatesBody .slab-rate-input').forEach(i => { i.value = ''; });
                });

                const fillBtn = document.getElementById('slabRatesFillRowBtn');
                if (fillBtn) fillBtn.addEventListener('click', fillSlabRow);
            }
        });
    </script>
    <script>
        // Auto conversions between inch and CM
        document.addEventListener('DOMContentLoaded', function () {
            const inchToCm = (inch) => (parseFloat(inch) || 0) * 2.54;
            const cmToInch = (cm) => (parseFloat(cm) || 0) / 2.54;

            // Item dimensions: inch -> CM
            const lInchInput = document.getElementById('editL');
            const wInchInput = document.getElementById('editW');
            const hInchInput = document.getElementById('editH');
            const lCmInput = document.getElementById('editLCm');
            const wCmInput = document.getElementById('editWCm');
            const hCmInput = document.getElementById('editHCm');

            if (lInchInput && lCmInput) {
                lInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    lCmInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (wInchInput && wCmInput) {
                wInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    wCmInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (hInchInput && hCmInput) {
                hInchInput.addEventListener('input', function () {
                    const val = inchToCm(this.value);
                    hCmInput.value = val ? val.toFixed(2) : '';
                });
            }

            // Item dimensions: CM -> inch (for manual CM entry)
            if (lCmInput && lInchInput) {
                lCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    lInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (wCmInput && wInchInput) {
                wCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    wInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (hCmInput && hInchInput) {
                hCmInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    hInchInput.value = val ? val.toFixed(2) : '';
                });
            }

            // CTN dimensions: CM -> inch (display only)
            const ctnLInput = document.getElementById('editCtnL');
            const ctnWInput = document.getElementById('editCtnW');
            const ctnHInput = document.getElementById('editCtnH');
            const ctnLInchInput = document.getElementById('editCtnLInch');
            const ctnWInchInput = document.getElementById('editCtnWInch');
            const ctnHInchInput = document.getElementById('editCtnHInch');

            if (ctnLInput && ctnLInchInput) {
                ctnLInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnLInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (ctnWInput && ctnWInchInput) {
                ctnWInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnWInchInput.value = val ? val.toFixed(2) : '';
                });
            }
            if (ctnHInput && ctnHInchInput) {
                ctnHInput.addEventListener('input', function () {
                    const val = cmToInch(this.value);
                    ctnHInchInput.value = val ? val.toFixed(2) : '';
                });
            }
        });
    </script>
@endsection

