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
                                    <th class="th-has-filter shipping-rate-header" data-pm-ship-col="ebay2">
                                        <div class="th-vertical-label">Ebay2<br>ship</div>
                                        <select id="filterEbay2ShipCol" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Filter Ebay2 ship">
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
                                    <th class="th-has-filter th-parent-sku-col shipping-rate-header">
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
                                        <select id="filterWtAct" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px; max-width: 140px;" title="Filter by Item WT ACT (lb)">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="lb_0">0 lb</option>
                                            <option value="lb_001_025">0.01 – 0.25 lb</option>
                                            <option value="lb_0251_05">0.2501 – 0.5 lb</option>
                                            <option value="lb_0501_075">0.5001 – 0.75 lb</option>
                                            <option value="lb_0751_1">0.7501 – 1 lb</option>
                                            <option value="lb_101_2">1.01 – 2 lb</option>
                                            <option value="lb_201_3">2.01 – 3 lb</option>
                                            <option value="lb_301_4">3.01 – 4 lb</option>
                                            <option value="lb_401_20">4.01 – 20 lb</option>
                                            <option value="lb_2001_30">20.01 – 30 lb</option>
                                            <option value="lb_301_40">30.1 – 40 lb</option>
                                            <option value="lb_401_50">40.1 – 50 lb</option>
                                            <option value="lb_gt50">&gt; 50 lb</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter item-dim-header">
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
                                <label for="editTtShip" class="form-label fw-bold">TT 1 Ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editTtShip" name="tt_ship" placeholder="TT 1 Ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editTemuShip" class="form-label fw-bold">Temu ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editTemuShip" name="temu_ship" placeholder="Temu ship">
                            </div>
                            <div class="col-md-3">
                                <label for="editEbay2Ship" class="form-label fw-bold">Ebay2 ship</label>
                                <input type="number" step="0.01" class="form-control fw-bold" id="editEbay2Ship" name="ebay2_ship" placeholder="Ebay2 ship">
                            </div>
                        </div>
                        <div class="row mb-3">
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
                    tbody.innerHTML = '<tr><td colspan="29" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const isParentRow = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentRow) row.classList.add('shipping-parent-row');
                    const cellVal = (val, decimals) => isParentRow ? '--' : formatNumber(val || 0, decimals);

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

                    const ttShipCell = document.createElement('td');
                    setShippingNumericCell(ttShipCell, item.tt_ship, isParentRow);
                    row.appendChild(ttShipCell);

                    const temuShipCell = document.createElement('td');
                    setShippingNumericCell(temuShipCell, item.temu_ship, isParentRow);
                    row.appendChild(temuShipCell);

                    const ebay2ShipCell = document.createElement('td');
                    setShippingNumericCell(ebay2ShipCell, item.ebay2_ship, isParentRow);
                    row.appendChild(ebay2ShipCell);

                    const gofoCell = document.createElement('td');
                    setShippingNumericCell(gofoCell, item.gofo, isParentRow);
                    row.appendChild(gofoCell);

                    const fedexCell = document.createElement('td');
                    setShippingNumericCell(fedexCell, item.fedex, isParentRow);
                    row.appendChild(fedexCell);

                    const upsCell = document.createElement('td');
                    setShippingNumericCell(upsCell, item.ups, isParentRow);
                    row.appendChild(upsCell);

                    const uspsCell = document.createElement('td');
                    setShippingNumericCell(uspsCell, item.usps, isParentRow);
                    row.appendChild(uspsCell);

                    const uniCell = document.createElement('td');
                    setShippingNumericCell(uniCell, item.uni, isParentRow);
                    row.appendChild(uniCell);

                    const fbaSkuCell = document.createElement('td');
                    fbaSkuCell.className = 'td-sku-col shipping-rate-cell';
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

                    // WT ACT column
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = cellVal(item.wt_act, 2);
                    row.appendChild(wtActCell);

                    // WT DECL column
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center';
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

                    tbody.appendChild(row);
                });
                applyDimWtSectionFilter();
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
                        const isPmShipCol = pmAttr === 'ship' || pmAttr === 'tt' || pmAttr === 'temu' || pmAttr === 'ebay2';
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

            /** Item WT ACT (lb) preset bands (filterWtAct select values). */
            function matchesWtActLbBand(item, band) {
                if (!band || band === 'all') return true;
                if (band === 'missing' || band === 'lb_0') {
                    return isMissing(item.wt_act);
                }
                const w = parseFloat(item.wt_act);
                if (!Number.isFinite(w)) return false;
                switch (band) {
                    case 'lb_001_025':
                        return w >= 0.01 && w <= 0.25;
                    case 'lb_0251_05':
                        return w >= 0.2501 && w <= 0.5;
                    case 'lb_0501_075':
                        return w >= 0.5001 && w <= 0.75;
                    case 'lb_0751_1':
                        return w >= 0.7501 && w <= 1;
                    case 'lb_101_2':
                        return w >= 1.01 && w <= 2;
                    case 'lb_201_3':
                        return w >= 2.01 && w <= 3;
                    case 'lb_301_4':
                        return w >= 3.01 && w <= 4;
                    case 'lb_401_20':
                        return w >= 4.01 && w <= 20;
                    case 'lb_2001_30':
                        return w >= 20.01 && w <= 30;
                    case 'lb_301_40':
                        return w >= 30.1 && w <= 40;
                    case 'lb_401_50':
                        return w >= 40.1 && w <= 50;
                    case 'lb_gt50':
                        return w > 50;
                    default:
                        return true;
                }
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
                const filterTtShipCol = document.getElementById('filterTtShipCol')?.value || 'all';
                const filterTemuShipCol = document.getElementById('filterTemuShipCol')?.value || 'all';
                const filterEbay2ShipCol = document.getElementById('filterEbay2ShipCol')?.value || 'all';
                const filterGofoCol = document.getElementById('filterGofoCol')?.value || 'all';
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

                    // WT ACT (lb) filter — preset bands on item.wt_act
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
                    if (!matchesMarketplaceShipColFilter(item, 'tt_ship', filterTtShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'temu_ship', filterTemuShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'ebay2_ship', filterEbay2ShipCol)) return false;
                    if (!matchesMarketplaceShipColFilter(item, 'gofo', filterGofoCol)) return false;
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
                ['filterShipCol', 'filterTtShipCol', 'filterTemuShipCol', 'filterEbay2ShipCol', 'filterGofoCol', 'filterFedexCol', 'filterUpsCol', 'filterUspsCol', 'filterUniCol'].forEach(id => {
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
                    const columns = ["SKU", "Status", "INV", "Ship", "TT 1 Ship", "Temu ship", "Ebay2 ship", "GOFO", "Fedex", "UPS", "USPS", "UNI", "FBA SKU", "FBA ship", "FBA manual ship", "Weight ACT (Kg)", "WT ACT (LB)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CTN L (CM)", "CTN W (CM)", "CTN H (CM)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)"];

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
                            "TT 1 Ship": {
                                key: "tt_ship"
                        },
                        "Temu ship": {
                            key: "temu_ship"
                        },
                        "Ebay2 ship": {
                            key: "ebay2_ship"
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
                            key: "wt_act"
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
                                        else if (["wt_act_kg", "l", "w", "h", "l_cm", "w_cm", "h_cm", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each", "ship", "tt_ship", "temu_ship", "ebay2_ship", "gofo", "fedex", "ups", "usps", "uni", "fba_ship", "fba_manual_ship"].includes(key)) {
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
                                } else if (["FBA SKU", "Weight ACT (Kg)", "WT ACT (LB)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CTN (CBM)", "CTN (CBM/Each)", "Ship", "TT 1 Ship", "Temu ship", "Ebay2 ship", "GOFO", "Fedex", "UPS", "USPS", "UNI", "FBA ship", "FBA manual ship"].includes(col)) {
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
                        ['SKU', 'Ship', 'TT 1 Ship', 'Temu ship', 'Ebay2 ship', 'GOFO', 'Fedex', 'UPS', 'USPS', 'UNI', 'Weight ACT (Kg)', 'WT ACT (LB)', 'WT DECL (LB)', 'Length (inch)', 'Width (inch)', 'Height (Inch)', 'Length (CM)', 'Width (CM)', 'Height (CM)', 'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)'],
                        ['SKU001', '3.25', '2.95', '3.15', '3.45', '1.50', '4.20', '3.90', '2.80', '3.10', '6.2', '1.5', '1.2', '10.5', '8.3', '5.2', '26.67', '21.08', '13.21', '30', '25', '20', '0.015', '12', '0.00125'],
                        ['SKU002', '4.10', '3.80', '4.00', '4.25', '2.00', '5.10', '4.75', '3.50', '4.00', '9.1', '2.0', '1.8', '12.0', '9.0', '6.0', '30.48', '22.86', '15.24', '35', '28', '22', '0.0216', '15', '0.00144'],
                        ['SKU003', '2.80', '2.60', '2.70', '2.95', '1.20', '3.50', '3.20', '2.40', '2.70', '5.4', '1.2', '1.0', '9.5', '7.5', '4.5', '24.13', '19.05', '11.43', '28', '24', '18', '0.0121', '10', '0.00121']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Ship
                        { wch: 12 }, // TT 1 Ship
                        { wch: 12 }, // Temu ship
                        { wch: 12 }, // Ebay2 ship
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
                document.getElementById('editTtShip').value = shipNum(product.tt_ship);
                document.getElementById('editTemuShip').value = shipNum(product.temu_ship);
                document.getElementById('editEbay2Ship').value = shipNum(product.ebay2_ship);
                document.getElementById('editGofo').value = shipNum(product.gofo);
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
                    addNumericIfPresent('editTtShip', 'tt_ship');
                    addNumericIfPresent('editTemuShip', 'temu_ship');
                    addNumericIfPresent('editEbay2Ship', 'ebay2_ship');
                    addNumericIfPresent('editGofo', 'gofo');
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

            // Initialize (search and playback listeners once to avoid duplicates on reload)
            setupSearch();
            setupProductPlaybackListeners();
            loadData();
            setupExcelExport();
            setupImport();
            setupSelectAll();
            setupBulkEdit();
            setupPushData();
            // Reset bulk edit state when edit modal is closed (e.g. without saving)
            document.getElementById('editDimWtModal').addEventListener('hidden.bs.modal', function() {
                bulkEditList = null;
                document.getElementById('editDimWtModalLabel').textContent = 'Edit Shipping Master';
            });
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

