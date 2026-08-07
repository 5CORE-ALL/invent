@extends('layouts.vertical', ['title' => 'Dim Wt Items', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            font-size: 12px !important;
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
            font-size: 13px !important;
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
            font-size: 13px;
            color: #000;
            text-align: center;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        /* Parent SKU rows – light yellow background */
        .table-responsive tbody tr.parent-row,
        .table-responsive tbody tr.parent-row:nth-child(even) {
            background-color: #fffef2 !important;
        }
        .table-responsive tbody tr.parent-row:hover {
            background-color: #fdf3a8 !important;
        }

        .table-responsive .text-center {
            text-align: center;
        }
        /* Highlight selected item dimension headers */
        .table-responsive thead th.item-dim-header {
            background-color: #fffef2 !important; /* light yellow */
        }
        /* Declared dimension headers — distinct from ACT yellow */
        .table-responsive thead th.item-dim-decl-header {
            background-color: #d4edda !important; /* light green */
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

        /* Verified / N Verify filter badges */
        .badge-filter {
            opacity: 0.85;
            transition: opacity 0.15s ease, box-shadow 0.15s ease;
            user-select: none;
        }
        .badge-filter:hover {
            opacity: 1;
        }
        .badge-filter.badge-filter-active {
            opacity: 1;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(0, 0, 0, 0.35);
        }

        /* Verified column – red/green dot (no outer ring) */
        .verified-data-dropdown {
            width: 28px;
            height: 28px;
            min-width: 28px;
            padding: 0;
            border: none;
            border-radius: 0;
            background-color: transparent;
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
            color: #dc3545;
        }
        .verified-data-dropdown.verified {
            color: #28a745;
        }
        .verified-data-dropdown option[value="0"] { color: #dc3545; }
        .verified-data-dropdown option[value="1"] { color: #28a745; }

        /* Label Type dropdown in Type column — color by value */
        .label-type-dropdown {
            font-size: 10px;
            padding: 2px 4px;
            max-width: 78px;
            min-width: 62px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            background: #fff;
            color: #212529;
            font-weight: 700;
            cursor: pointer;
        }
        .label-type-dropdown.label-type-env {
            background-color: #fecaca;
            border-color: #ef4444;
            color: #991b1b;
        }
        .label-type-dropdown.label-type-std {
            background-color: #bbf7d0;
            border-color: #22c55e;
            color: #166534;
        }
        .label-type-dropdown.label-type-osize {
            background-color: #e9d5ff;
            border-color: #a855f7;
            color: #6b21a8;
        }
        .label-type-dropdown.label-type-pallet {
            background-color: #bfdbfe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        .label-type-dropdown:focus {
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.25);
            outline: none;
        }

        .girth-plus-l-alert {
            color: #dc3545 !important;
            font-weight: 700;
        }

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
        .status-badges-full .status-badge-item.bg-upcoming { background-color: #fefce8; }
        .status-badges-full .status-badge-item.bg-2bdc { background-color: #bfdbfe; }

        /* Ensure table fits container - auto layout so columns fit content */
        #dim-wt-master-datatable {
            width: 100% !important;
            table-layout: auto;
        }

        /* Itm pkg Cover thumbnail */
        #dim-wt-master-datatable th.col-itm-pkg-cover,
        #dim-wt-master-datatable td.col-itm-pkg-cover {
            width: 56px;
            min-width: 56px;
            max-width: 64px;
            text-align: center;
            vertical-align: middle;
        }
        #dim-wt-master-datatable td.col-itm-pkg-cover img.itm-pkg-cover-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
        }

        /* Instructions item PKG: ~100 characters per line, then wrap (100ch ≈ “0” width in this font) */
        #dim-wt-master-datatable th.col-instructions-item-pkg,
        #dim-wt-master-datatable td.col-instructions-item-pkg {
            max-width: 100ch;
            box-sizing: border-box;
        }
        #dim-wt-master-datatable td.col-instructions-item-pkg {
            white-space: pre-wrap !important;
            word-break: break-word;
            overflow-wrap: anywhere;
            vertical-align: top;
            font-size: 11px;
            line-height: 1.35;
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

        /* ── Dim & Wt Master change-history modal (compact) ── */
        .shipping-history-table { font-size: 12px; }
        .shipping-history-table th,
        .shipping-history-table td {
            padding: 4px 8px !important;
            vertical-align: middle;
        }
        .shipping-history-table .shm-field-cell {
            font-weight: 600;
            color: #0a3d91;
            white-space: nowrap;
        }
        .shipping-history-table .shm-field-cell .shm-field-icon {
            color: #6c8fc4;
            margin-right: 4px;
        }
        .shipping-history-table tr.shm-field-first td {
            border-top: 1px solid #c7dbff;
        }
        .shipping-history-table tr.shm-field-cont .shm-field-cell {
            color: #b9c4d6;
            font-weight: 500;
            font-size: 11px;
        }
        .shipping-history-table .shm-when {
            white-space: nowrap;
            color: #6c757d;
            font-size: 11px;
        }
        .shipping-history-table .shm-who .badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 7px;
        }
        .shipping-history-table .shm-old {
            color: #6c757d;
            text-decoration: line-through;
            text-decoration-color: rgba(220, 53, 69, 0.55);
        }
        .shipping-history-table .shm-arrow {
            color: #adb5bd;
            margin: 0 6px;
        }
        .shipping-history-table .shm-new {
            background: #0d6efd;
            color: #ffffff;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .shipping-history-table .shm-empty {
            font-style: italic;
            opacity: 0.85;
        }
        .shipping-history-table .shm-latest-dot {
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #28a745;
            margin-right: 4px;
            vertical-align: middle;
        }
        .shipping-history-table tbody tr:hover {
            background: #f8fbff;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Dim Wt Items',
        'sub_title' => 'Dim Wt Items Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-12">
                            <div class="d-flex align-items-center flex-wrap gap-2 justify-content-between">
                                <div class="d-flex align-items-center flex-wrap gap-2">
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
                                    <div class="d-flex align-items-center gap-1">
                                        <label class="form-label mb-0 small">Section:</label>
                                        <select id="dimWtSectionFilter" class="form-select form-select-sm" style="width: auto; min-width: 120px;">
                                            <option value="item_data">Item Data</option>
                                            <option value="carton_data">Carton Data</option>
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <label for="parentSearch" class="form-label mb-0 small fw-bold">Parent</label>
                                        <input type="text" id="parentSearch" class="form-control form-control-sm" placeholder="Search parent" style="width: 150px;">
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <label for="skuSearch" class="form-label mb-0 small fw-bold">SKU</label>
                                        <input type="text" id="skuSearch" class="form-control form-control-sm" placeholder="Search SKU" style="width: 150px;">
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-danger rounded-1 d-inline-flex align-items-center badge-filter" id="notVerifiedBadge" title="Click to show only Not-Verified SKUs" style="font-size: 0.95rem; padding: 0.5rem 0.9rem; font-weight: 500; cursor: pointer;">
                                            N Verify <span id="notVerifiedCount" class="ms-2 fw-bold">0</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-bolt me-1"></i> Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                                <i class="fas fa-file-upload me-2 text-info"></i> Import
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="exportSkusBtn" data-bs-toggle="modal" data-bs-target="#skuExportModal" title="Export SKU list only">
                                                <i class="fas fa-list me-2 text-secondary"></i> SKU Export
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="downloadExcel">
                                                <i class="fas fa-file-excel me-2 text-success"></i> Download
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
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
                                    <th class="th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 11px;">Parent</div>
                                    </th>
                                    <th class="th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 20px !important;">SKU</div>
                                    </th>
                                    <th>
                                        <span class="th-vertical-label" style="font-size: 9px;">STATUS</span>
                                    </th>
                                    <th><span class="th-vertical-label">INV</span></th>
                                    <th class="th-has-filter" title="Label Type">
                                        <div class="th-vertical-label">Type</div>
                                        <select id="filterLabelType" class="form-control form-control-sm mt-1 missing-data-filter" style="font-size: 9px; padding: 2px 4px; max-width: 100%;" title="Label Type">
                                            <option value="all">All</option>
                                            <option value="ENV">ENV</option>
                                            <option value="STD">STD</option>
                                            <option value="O-Size">O-Size</option>
                                            <option value="Pallet">Pallet</option>
                                        </select>
                                    </th>
                                    <th class="item-dim-header hide-item-wt-act">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item Weight ACT<br>(Kg)</span>
                                    </th>
                                    <th class="item-dim-header">
                                        <span class="th-vertical-label" style="font-size: 9px;">Itm wt GW</span>
                                    </th>
                                    <th class="item-dim-header">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item L IN</span>
                                    </th>
                                    <th class="item-dim-header">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item W IN</span>
                                    </th>
                                    <th class="item-dim-header">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item H IN</span>
                                    </th>
                                    <th class="item-dim-decl-header" title="Copies Itm wt GW when Decl is empty">
                                        <span class="th-vertical-label" style="font-size: 9px;">Itm wt GW Decl</span>
                                    </th>
                                    <th class="item-dim-decl-header" title="Copies Item L IN when Decl is empty">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item L IN Decl</span>
                                    </th>
                                    <th class="item-dim-decl-header" title="Copies Item W IN when Decl is empty">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item W IN Decl</span>
                                    </th>
                                    <th class="item-dim-decl-header" title="Copies Item H IN when Decl is empty">
                                        <span class="th-vertical-label" style="font-size: 9px;">Item H IN Decl</span>
                                    </th>
                                    <th class="item-dim-header" title="Girth = 2 × (Width + Height)">
                                        <span class="th-vertical-label" style="font-size: 9px;">GIRTH</span>
                                    </th>
                                    <th class="item-dim-header" title="GIRTH + Length">
                                        <span class="th-vertical-label" style="font-size: 9px;">GIRTH + L</span>
                                    </th>
                                    <th class="item-dim-header" title="Item CBM (from Product Master Values.cbm)">
                                        <span class="th-vertical-label" style="font-size: 9px;">Itm CBM</span>
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
                                    <th class="col-ctn-pkg text-center"><span class="th-vertical-label" style="font-size: 9px;">Ctn pkg</span></th>
                                    <th class="col-instructions-item-pkg"><span class="th-vertical-label" style="font-size: 9px;">item PKG</span></th>
                                    <th class="col-itm-pkg-cover text-center"><span class="th-vertical-label" style="font-size: 9px;">Itm pkg Cover</span></th>
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
                        <div class="loading-text">Loading Dimensions & Weight Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Dimensions & Weight Master Modal -->
    <div class="modal fade" id="editDimWtModal" tabindex="-1" aria-labelledby="editDimWtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDimWtModalLabel">Edit Dimensions & Weight Master</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="bulkEditOnlyChangedHint" class="alert alert-warning py-2 mb-3" style="display: none; font-size: 13px;">
                        <i class="fas fa-layer-group me-1"></i>
                        <strong>Bulk edit:</strong> only fields you change here are written to all selected SKUs.
                        Unchanged fields keep each SKU&rsquo;s existing value.
                    </div>
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
                                <label for="editWtAct" class="form-label">Itm wt GW</label>
                                <input type="number" step="0.01" class="form-control" id="editWtAct" name="wt_act" placeholder="Enter Itm wt GW">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtDecl" class="form-label">Itm wt GW Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editWtDecl" name="wt_decl" placeholder="Enter Itm wt GW Decl">
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
                                <label for="editLDecl" class="form-label">Item L IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editLDecl" name="l_decl" placeholder="Enter Item L IN Decl">
                            </div>
                            <div class="col-md-4">
                                <label for="editWDecl" class="form-label">Item W IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editWDecl" name="w_decl" placeholder="Enter Item W IN Decl">
                            </div>
                            <div class="col-md-4">
                                <label for="editHDecl" class="form-label">Item H IN Decl</label>
                                <input type="number" step="0.01" class="form-control" id="editHDecl" name="h_decl" placeholder="Enter Item H IN Decl">
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
                            <div class="col-md-4">
                                <label for="editCtnL" class="form-label">CTN L (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnL" name="ctn_l" placeholder="Enter CTN L (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnW" class="form-label">CTN W (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnW" name="ctn_w" placeholder="Enter CTN W (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editCtnH" class="form-label">CTN H (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnH" name="ctn_h" placeholder="Enter CTN H (CM)">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="editCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Ctn pkg</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="editCtnInstructions" class="form-label">Ctn pkg <span class="text-muted small fw-normal">(max 100 characters)</span></label>
                                <input type="text" class="form-control" id="editCtnInstructions" name="ctn_instructions" maxlength="100" placeholder="Carton packaging instructions (max 100 characters)" autocomplete="off">
                                <small class="text-muted">Leave blank to clear. Not saved for PARENT rows.</small>
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Instructions item PKG</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="editInstructionsItemPkg" class="form-label">Instructions</label>
                                <textarea class="form-control" id="editInstructionsItemPkg" name="instructions_item_pkg" rows="3" placeholder="Packaging instructions (saved separately from dimensions)"></textarea>
                                <small class="text-muted">Leave blank to clear. Not saved for PARENT rows.</small>
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Itm pkg Cover</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-center">
                                <div id="editItemPkgCoverPreview"
                                     style="width:120px;height:120px;margin:0 auto;border:1px solid #d1d5db;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;overflow:hidden;">
                                    <span class="text-muted small">No cover</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="editItemPkgCoverInput" class="form-label">Cover image URL / path</label>
                                <input type="text" class="form-control" id="editItemPkgCoverInput"
                                       placeholder="Image URL or path (e.g. storage/... or https://...)"
                                       autocomplete="off">
                                <small class="text-muted">Saved to product master Values.item_pkg_cover (same as PO proforma). Leave blank to clear. Not saved for PARENT rows.</small>
                            </div>
                        </div>

                        <div class="row mb-1">
                            <div class="col-12">
                                <small class="text-secondary fw-semibold">Verified</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editVerified" class="form-label">Verified status</label>
                                <select class="form-select" id="editVerified" name="verified_data">
                                    <option value="0">🔴 Not Verified</option>
                                    <option value="1">🟢 Verified</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="editSaveAlsoToSiblings"
                                           style="border-color:#198754; accent-color:#198754;">
                                    <label class="form-check-label fw-semibold text-success" for="editSaveAlsoToSiblings">
                                        save also to Siblings
                                    </label>
                                    <div class="small text-muted">When checked, changes also apply to all child SKUs under the same parent.</div>
                                </div>
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
                        <i class="fas fa-upload me-2"></i>Import Dimensions & Weight Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Steps:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in the dim & wt data (Weight ACT (Kg), Itm wt GW, Item L IN, Item W IN, Item H IN, Itm wt GW Decl, Item L IN Decl, Item W IN Decl, Item H IN Decl, Length (CM), Width (CM), Height (CM), CTN L (CM), CTN W (CM), CTN H (CM), CTN QTY, Carton CBM columns as applicable)</li>
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

    <!-- Dim & Wt Master History Modal -->
    <div class="modal fade" id="dimWtHistoryModal" tabindex="-1" aria-labelledby="dimWtHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title" id="dimWtHistoryModalLabel">
                        <i class="bi bi-clock-history me-2"></i>Change History — <span id="dimWtHistorySku" class="fw-bold"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="dimWtHistoryLoading" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2 text-muted small mb-0">Loading history…</p>
                    </div>
                    <div id="dimWtHistoryEmpty" class="alert alert-info mb-0" style="display:none;">
                        <i class="fas fa-info-circle me-2"></i> No edits recorded for this SKU yet. Changes made from now on (manual edits and sheet uploads) will be tracked here.
                    </div>
                    <div id="dimWtHistoryError" class="alert alert-danger mb-0" style="display:none;"></div>
                    <div class="table-responsive" id="dimWtHistoryTableWrap" style="display:none; max-height: 65vh;">
                        <table class="table table-sm table-hover mb-0 align-middle shipping-history-table">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                <tr>
                                    <th style="white-space:nowrap; width: 24%;">Field</th>
                                    <th style="white-space:nowrap; width: 16%;">When</th>
                                    <th style="white-space:nowrap; width: 16%;">Who</th>
                                    <th>Change (old → new)</th>
                                </tr>
                            </thead>
                            <tbody id="dimWtHistoryTbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Export Modal -->
    <div class="modal fade" id="skuExportModal" tabindex="-1" aria-labelledby="skuExportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white;">
                    <h5 class="modal-title" id="skuExportModalLabel">
                        <i class="fas fa-list me-2"></i>SKU Export
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <!-- Scope -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Scope</label>
                        <div class="d-flex flex-column gap-1">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportScope" id="skuScopeAll" value="all" checked>
                                <label class="form-check-label" for="skuScopeAll">All SKUs</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportScope" id="skuScopeFiltered" value="filtered">
                                <label class="form-check-label" for="skuScopeFiltered">Filtered SKUs <span id="skuScopeFilteredCount" class="badge bg-secondary ms-1"></span></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportScope" id="skuScopeSelected" value="selected">
                                <label class="form-check-label" for="skuScopeSelected">Selected SKUs <span id="skuScopeSelectedCount" class="badge bg-secondary ms-1"></span></label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- Columns -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Columns to include</label>
                        <div class="d-flex flex-column gap-1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skuColSku" value="sku" checked disabled>
                                <label class="form-check-label" for="skuColSku">SKU <small class="text-muted">(always included)</small></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skuColParent" value="parent">
                                <label class="form-check-label" for="skuColParent">Parent</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skuColStatus" value="status">
                                <label class="form-check-label" for="skuColStatus">Status</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skuColInv" value="inv">
                                <label class="form-check-label" for="skuColInv">INV (Shopify)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="skuColBlankDim" value="blank_dim">
                                <label class="form-check-label" for="skuColBlankDim">Add blank dim/weight columns <small class="text-muted">(ready-to-fill import template)</small></label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- Format -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Format</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportFormat" id="skuFmtXlsx" value="xlsx" checked>
                                <label class="form-check-label" for="skuFmtXlsx"><i class="fas fa-file-excel text-success me-1"></i>Excel (.xlsx)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportFormat" id="skuFmtTsv" value="tsv">
                                <label class="form-check-label" for="skuFmtTsv"><i class="fas fa-file-alt text-secondary me-1"></i>Tab-separated (.tsv)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="skuExportFormat" id="skuFmtCsv" value="csv">
                                <label class="form-check-label" for="skuFmtCsv"><i class="fas fa-file-csv text-primary me-1"></i>CSV (.csv)</label>
                            </div>
                        </div>
                    </div>

                    <div id="skuExportSummary" class="alert alert-light border py-2" style="font-size: 13px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark" id="doSkuExportBtn">
                        <i class="fas fa-download me-1"></i>Export SKUs
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
            let verifiedFilter = null; // null = all, 1 = verified only, 0 = not verified only
            let productUniqueParents = [];
            let isProductNavigationActive = false;
            let currentProductParentIndex = -1;
            // When set (via multi-select + Action column Edit), save updates these products (changed fields only)
            let bulkEditList = null;
            let bulkEditInitialValues = null;

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Change History is restricted to Ritu mam (inventory mail) and President sir only.
            @php
                $__dimWtHistoryEmails = ['inventory@5core.com', 'ritu.kaur013@gmail.com', 'president@5core.com', 'ecomm6@5core.com'];
                $__canViewDimWtHistory = in_array(strtolower(trim((string) (auth()->user()->email ?? ''))), $__dimWtHistoryEmails, true);
            @endphp
            const canViewDimWtHistory = @json($__canViewDimWtHistory);

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

            // Load Dimensions & Weight data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/dim-wt-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            // Preserve the user's active search / verified filter so applied
                            // changes show immediately in the current view (no manual refresh).
                            const hasActiveFilter = verifiedFilter !== null ||
                                (document.getElementById('parentSearch')?.value || '') !== '' ||
                                (document.getElementById('skuSearch')?.value || '') !== '' ||
                                ((document.getElementById('filterLabelType')?.value || 'all') !== 'all');
                            if (hasActiveFilter) {
                                applyFilters();
                            } else {
                                filteredData = [...tableData];
                                renderTable(filteredData);
                            }
                            updateCounts();
                            refreshProductPlaybackState();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load Dimensions & Weight data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            /** Label Type choices for the Type column. */
            const LABEL_TYPE_OPTIONS = ['ENV', 'STD', 'O-Size', 'Pallet'];
            const LABEL_TYPE_COLOR_CLASS = {
                'ENV': 'label-type-env',
                'STD': 'label-type-std',
                'O-Size': 'label-type-osize',
                'Pallet': 'label-type-pallet'
            };

            function normalizeLabelType(raw) {
                const v = String(raw == null ? '' : raw).trim();
                return LABEL_TYPE_OPTIONS.includes(v) ? v : 'STD';
            }

            function applyLabelTypeColor(dropdown, labelType) {
                if (!dropdown) return;
                const type = normalizeLabelType(labelType);
                Object.values(LABEL_TYPE_COLOR_CLASS).forEach(cls => dropdown.classList.remove(cls));
                const colorCls = LABEL_TYPE_COLOR_CLASS[type];
                if (colorCls) dropdown.classList.add(colorCls);
            }

            /**
             * Reorder item inch dims: highest → Length, 2nd → Width, 3rd → Height.
             * Girth = 2 × (Width + Height); GIRTH + L = Girth + Length.
             */
            function getOrganizedItemDims(item) {
                const nums = [item.l, item.w, item.h]
                    .map(v => {
                        if (v === null || v === undefined || v === '') return null;
                        const n = parseFloat(v);
                        return Number.isFinite(n) ? n : null;
                    })
                    .filter(n => n !== null)
                    .sort((a, b) => b - a);

                const length = nums.length > 0 ? nums[0] : null;
                const width = nums.length > 1 ? nums[1] : null;
                const height = nums.length > 2 ? nums[2] : null;
                const girth = (width !== null && height !== null) ? (2 * (width + height)) : null;
                const girthPlusL = (girth !== null && length !== null) ? (girth + length) : null;

                return { length, width, height, girth, girthPlusL };
            }

            /** Item CBM from Values.cbm, or calculated from L×W×H (inch → cm³ → m³). */
            function getItemCbm(item) {
                const stored = parseFloat(item.cbm);
                if (Number.isFinite(stored) && stored > 0) return stored;
                const l = parseFloat(item.l);
                const w = parseFloat(item.w);
                const h = parseFloat(item.h);
                if (![l, w, h].every(n => Number.isFinite(n) && n > 0)) return null;
                return ((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000;
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="28" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const isParentRow = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    if (isParentRow) row.classList.add('parent-row');
                    const cellVal = (val, decimals) => isParentRow ? '--' : formatNumber(val || 0, decimals);

                    // Checkbox column (not shown for parent rows)
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'text-center';
                    if (!isParentRow) {
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.value = escapeHtml(item.SKU);
                        checkbox.setAttribute('data-sku', escapeHtml(item.SKU));
                        checkbox.setAttribute('data-id', escapeHtml(item.id));
                        checkbox.addEventListener('change', function() {
                            updatePushButtonState();
                        });
                        checkboxCell.appendChild(checkbox);
                    }
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

                    // Status column – colored dot (not shown for parent rows)
                    const statusCell = document.createElement('td');
                    statusCell.className = 'text-center';
                    statusCell.innerHTML = isParentRow ? '--' : getStatusDot(item.status);
                    row.appendChild(statusCell);

                    // INV column
                    const invCell = document.createElement('td');
                    if (isParentRow) {
                        invCell.textContent = '--';
                    } else if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    // Type column (Label Type) — ENV / STD / O-Size / Pallet; default STD
                    const labelTypeCell = document.createElement('td');
                    labelTypeCell.className = 'text-center';
                    const labelTypeVal = normalizeLabelType(item.label_type);
                    const labelTypeColorCls = LABEL_TYPE_COLOR_CLASS[labelTypeVal] || 'label-type-std';
                    labelTypeCell.innerHTML = `
                        <select class="label-type-dropdown ${labelTypeColorCls}"
                            data-sku="${escapeHtml(item.SKU || '')}"
                            data-id="${escapeHtml(String(item.id || ''))}"
                            data-prev="${escapeHtml(labelTypeVal)}"
                            title="Label Type">
                            ${LABEL_TYPE_OPTIONS.map(opt =>
                                `<option value="${opt}"${opt === labelTypeVal ? ' selected' : ''}>${opt}</option>`
                            ).join('')}
                        </select>
                    `;
                    row.appendChild(labelTypeCell);

                    // Weight ACT (Kg) column (hidden)
                    const wtActKgCell = document.createElement('td');
                    wtActKgCell.className = 'text-center hide-item-wt-act';
                    wtActKgCell.textContent = cellVal(item.wt_act_kg, 1);
                    row.appendChild(wtActKgCell);

                    // WT ACT column
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = cellVal(item.wt_act, 1);
                    row.appendChild(wtActCell);

                    // Item L/W/H (inch) — reorganized: highest=L, 2nd=W, 3rd=H
                    const orgDims = getOrganizedItemDims(item);
                    const lCell = document.createElement('td');
                    lCell.className = 'text-center';
                    lCell.title = 'Length (highest of L/W/H)';
                    lCell.textContent = cellVal(orgDims.length, 0);
                    row.appendChild(lCell);

                    const wCell = document.createElement('td');
                    wCell.className = 'text-center';
                    wCell.title = 'Width (2nd highest of L/W/H)';
                    wCell.textContent = cellVal(orgDims.width, 0);
                    row.appendChild(wCell);

                    const hCell = document.createElement('td');
                    hCell.className = 'text-center';
                    hCell.title = 'Height (3rd / lowest of L/W/H)';
                    hCell.textContent = cellVal(orgDims.height, 0);
                    row.appendChild(hCell);

                    // Decl columns (fallback to ACT when Decl is empty)
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center';
                    wtDeclCell.title = 'Itm wt GW Decl';
                    wtDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'wt_decl', 'wt_act'), 1);
                    row.appendChild(wtDeclCell);

                    const lDeclCell = document.createElement('td');
                    lDeclCell.className = 'text-center';
                    lDeclCell.title = 'Item L IN Decl';
                    lDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'l_decl', 'l'), 0);
                    row.appendChild(lDeclCell);

                    const wDeclCell = document.createElement('td');
                    wDeclCell.className = 'text-center';
                    wDeclCell.title = 'Item W IN Decl';
                    wDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'w_decl', 'w'), 0);
                    row.appendChild(wDeclCell);

                    const hDeclCell = document.createElement('td');
                    hDeclCell.className = 'text-center';
                    hDeclCell.title = 'Item H IN Decl';
                    hDeclCell.textContent = isParentRow ? '--' : cellVal(itemDeclValue(item, 'h_decl', 'h'), 0);
                    row.appendChild(hDeclCell);

                    // GIRTH = 2 × (Width + Height)
                    const girthCell = document.createElement('td');
                    girthCell.className = 'text-center';
                    girthCell.title = 'Girth = 2 × (Width + Height)';
                    girthCell.textContent = cellVal(orgDims.girth, 0);
                    row.appendChild(girthCell);

                    // GIRTH + L — values > 130 shown in red
                    const girthPlusLCell = document.createElement('td');
                    girthPlusLCell.className = 'text-center';
                    girthPlusLCell.title = 'GIRTH + Length';
                    girthPlusLCell.textContent = cellVal(orgDims.girthPlusL, 0);
                    if (!isParentRow && orgDims.girthPlusL !== null && orgDims.girthPlusL > 130) {
                        girthPlusLCell.classList.add('girth-plus-l-alert');
                    }
                    row.appendChild(girthPlusLCell);

                    // Itm CBM — existing Values.cbm (or calc from L×W×H)
                    const itmCbmCell = document.createElement('td');
                    itmCbmCell.className = 'text-center';
                    itmCbmCell.title = 'Item CBM';
                    itmCbmCell.textContent = isParentRow ? '--' : formatNumber(getItemCbm(item), 4);
                    row.appendChild(itmCbmCell);

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

                    // Ctn pkg (Values.ctn_instructions) – magnifying glass, full text on hover
                    const ctnPkgCell = document.createElement('td');
                    ctnPkgCell.className = 'col-ctn-pkg text-center';
                    if (isParentRow) {
                        ctnPkgCell.textContent = '--';
                    } else {
                        const rawCtnPkg = item.ctn_instructions != null ? String(item.ctn_instructions).trim() : '';
                        const hasCtnPkg = rawCtnPkg !== '';
                        const ctnIconColor = hasCtnPkg ? '#28a745' : '#dc3545';
                        const ctnIconTitle = hasCtnPkg ? rawCtnPkg : 'No instructions available';
                        ctnPkgCell.innerHTML = `<i class="fas fa-search" style="color:${ctnIconColor}; font-size:14px; cursor:pointer;" title="${escapeHtml(ctnIconTitle)}"></i>`;
                    }
                    row.appendChild(ctnPkgCell);

                    // Instructions item PKG (from instructions_item_pkg table)
                    const pkgCell = document.createElement('td');
                    pkgCell.className = 'col-instructions-item-pkg';
                    pkgCell.classList.add('text-center');
                    if (isParentRow) {
                        pkgCell.textContent = '--';
                    } else {
                        const rawPkg = item.instructions_item_pkg != null ? String(item.instructions_item_pkg).trim() : '';
                        const hasPkg = rawPkg !== '';
                        const iconColor = hasPkg ? '#28a745' : '#dc3545';
                        const iconTitle = hasPkg ? rawPkg : 'No instructions available';
                        pkgCell.innerHTML = `<i class="fas fa-search" style="color:${iconColor}; font-size:14px; cursor:pointer;" title="${escapeHtml(iconTitle)}"></i>`;
                    }
                    row.appendChild(pkgCell);

                    // Itm pkg Cover (Values.item_pkg_cover / first packing_images)
                    const coverCell = document.createElement('td');
                    coverCell.className = 'col-itm-pkg-cover text-center';
                    if (isParentRow) {
                        coverCell.textContent = '--';
                    } else {
                        const coverUrl = (item.item_pkg_cover != null ? String(item.item_pkg_cover).trim() : '');
                        if (coverUrl) {
                            coverCell.innerHTML = `<img src="${escapeHtml(coverUrl)}" alt="Itm pkg Cover" class="itm-pkg-cover-thumb" title="Itm pkg Cover">`;
                            const coverImg = coverCell.querySelector('img');
                            if (coverImg) {
                                coverImg.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    window.open(coverUrl, '_blank', 'noopener');
                                });
                            }
                        } else {
                            coverCell.innerHTML = '<span class="text-muted">—</span>';
                        }
                    }
                    row.appendChild(coverCell);

                    // Verified column – red/green dot toggle
                    const isVerified = item.verified_data === 1 || item.verified_data === true ||
                        (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                    const verifiedClass = isVerified ? 'verified' : 'not-verified';
                    const verifiedValue = isVerified ? '1' : '0';
                    const verifiedCell = document.createElement('td');
                    verifiedCell.className = 'text-center';
                    verifiedCell.innerHTML = isParentRow ? '--' : `
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
                    const hasHistory = item.has_history === true || item.has_history === 1;
                    const historyDotColor = hasHistory ? '#28a745' : '#dc3545';
                    const historyDotTitle = hasHistory ? 'History available — click to view' : 'No history yet — click to view';
                    const historyBtnHtml = (canViewDimWtHistory && !isParentRow)
                        ? `<button class="btn btn-sm btn-link p-0 border-0 history-btn" data-id="${item.id != null ? escapeHtml(item.id) : ''}" data-sku="${escapeHtml(item.SKU)}" title="${historyDotTitle}" style="line-height:1;">
                                <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:${historyDotColor};"></span>
                            </button>`
                        : '';
                    const editBtnHtml = isParentRow
                        ? ''
                        : `<button class="btn btn-sm edit-btn p-0 border-0 bg-transparent" data-sku="${escapeHtml(item.SKU)}" title="Edit" style="color:#000;">
                                <i class="bi bi-pencil-square"></i>
                            </button>`;
                    actionCell.innerHTML = `
                        <div class="d-inline-flex gap-1">
                            ${editBtnHtml}
                            ${historyBtnHtml}
                        </div>
                    `;
                    row.appendChild(actionCell);
                    
                    // Add event listener for edit button
                    // Multi-select + pencil = bulk edit (no separate Bulk Edit button)
                    const editBtn = actionCell.querySelector('.edit-btn');
                    if (editBtn) editBtn.addEventListener('click', function() {
                        const sku = this.getAttribute('data-sku');
                        const product = tableData.find(d => d.SKU === sku);
                        if (!product) return;
                        const selected = getSelectedNonParentProducts();
                        const clickedInSelection = selected.some(p =>
                            (p.id != null && product.id != null && String(p.id) === String(product.id)) ||
                            (p.SKU && product.SKU && String(p.SKU) === String(product.SKU))
                        );
                        if (selected.length > 1 && clickedInSelection) {
                            bulkEditList = selected;
                        } else {
                            bulkEditList = null;
                        }
                        editDimWt(product);
                    });

                    // Add event listener for history button
                    const historyBtn = actionCell.querySelector('.history-btn');
                    if (historyBtn) {
                        historyBtn.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const sku = this.getAttribute('data-sku');
                            openDimWtHistoryModal(id, sku);
                        });
                    }

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
                        // Always show checkbox, Img, Parent, SKU (cols 0–3); keep utility tails
                        const isLeadIdentityCol = i < 4;
                        const isTailUtilityCol = headerText.includes('verified') || /\baction\b/.test(headerText);
                        const isInstructionsPkgCol = headerText.includes('pkg');
                        visible = isLeadIdentityCol || isTailUtilityCol || isCtnDim || isCartonMetric || headerText.includes('status') || headerText === 'inv' || isInstructionsPkgCol;
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
                let notVerifiedCount = 0;
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

                    // Not verified (red dots) — child SKUs only
                    const isVerified = item.verified_data === 1 || item.verified_data === true ||
                        (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                    if (!isVerified) notVerifiedCount++;
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

                const notVerifiedCountEl = document.getElementById('notVerifiedCount');
                if (notVerifiedCountEl) notVerifiedCountEl.textContent = notVerifiedCount;
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
                filteredData = [...tableData];
                renderTable(filteredData);
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
                filteredData = tableData.filter(item => item.Parent === currentParent);
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

            /** Decl field value, falling back to ACT when Decl is empty. */
            function itemDeclValue(item, declKey, actKey) {
                if (!isMissing(item[declKey])) return item[declKey];
                return item[actKey];
            }

            // Apply all filters
            function applyFilters() {
                const parentSearchVal = (document.getElementById('parentSearch')?.value || '').toLowerCase();
                const skuSearchVal = (document.getElementById('skuSearch')?.value || '').toLowerCase();
                const filterLabelType = document.getElementById('filterLabelType')?.value || 'all';

                filteredData = tableData.filter(item => {
                    if (parentSearchVal && !(item.Parent || '').toLowerCase().includes(parentSearchVal)) return false;
                    if (skuSearchVal && !(item.SKU || '').toLowerCase().includes(skuSearchVal)) return false;

                    if (filterLabelType && filterLabelType !== 'all') {
                        if (normalizeLabelType(item.label_type) !== filterLabelType) return false;
                    }

                    if (verifiedFilter !== null) {
                        const isParentSku = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                        if (isParentSku) return false;
                        const isVerified = item.verified_data === 1 || item.verified_data === true ||
                            (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                        if (verifiedFilter === 1 && !isVerified) return false;
                        if (verifiedFilter === 0 && isVerified) return false;
                    }

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

            // Click-to-sort on table headers (no sort icons shown)
            let currentSortKey = null;
            let currentSortDir = 1; // 1 = asc, -1 = desc
            function setupSort() {
                const table = document.getElementById('dim-wt-master-datatable');
                if (!table) return;
                const ths = table.querySelectorAll('thead th');
                // Column index -> { key, type }. null = not sortable.
                const sortMap = {
                    2: { key: 'Parent', type: 'text' },
                    3: { key: 'SKU', type: 'text' },
                    4: { key: 'status', type: 'text' },
                    5: { key: 'shopify_inv', type: 'num' },
                    6: { key: 'label_type', type: 'text' },
                    7: { key: 'wt_act_kg', type: 'num' },
                    8: { key: 'wt_act', type: 'num' },
                    9: { key: 'org_l', type: 'num' },
                    10: { key: 'org_w', type: 'num' },
                    11: { key: 'org_h', type: 'num' },
                    12: { key: 'wt_decl', type: 'num' },
                    13: { key: 'l_decl', type: 'num' },
                    14: { key: 'w_decl', type: 'num' },
                    15: { key: 'h_decl', type: 'num' },
                    16: { key: 'girth', type: 'num' },
                    17: { key: 'girth_plus_l', type: 'num' },
                    18: { key: 'cbm', type: 'num' },
                    19: { key: 'l_cm', type: 'num' },
                    20: { key: 'w_cm', type: 'num' },
                    21: { key: 'h_cm', type: 'num' },
                    22: { key: 'ctn_l', type: 'num' },
                    23: { key: 'ctn_w', type: 'num' },
                    24: { key: 'ctn_h', type: 'num' },
                    25: { key: 'ctn_cbm', type: 'num' },
                    26: { key: 'ctn_qty', type: 'num' },
                    27: { key: 'ctn_cbm_each', type: 'num' },
                    28: { key: 'ctn_instructions', type: 'text' },
                    29: { key: 'instructions_item_pkg', type: 'text' },
                    30: { key: 'item_pkg_cover', type: 'text' },
                    31: { key: 'verified_data', type: 'num' },
                };

                const getVal = (item, key) => {
                    if (key === 'status') {
                        const s = (item.status != null && item.status !== '') ? item.status : (item.Values && item.Values.status);
                        return String(s || '');
                    }
                    if (key === 'label_type') {
                        return normalizeLabelType(item.label_type);
                    }
                    if (key === 'org_l' || key === 'org_w' || key === 'org_h' || key === 'girth' || key === 'girth_plus_l') {
                        const d = getOrganizedItemDims(item);
                        if (key === 'org_l') return d.length;
                        if (key === 'org_w') return d.width;
                        if (key === 'org_h') return d.height;
                        if (key === 'girth') return d.girth;
                        return d.girthPlusL;
                    }
                    if (key === 'wt_decl') return itemDeclValue(item, 'wt_decl', 'wt_act');
                    if (key === 'l_decl') return itemDeclValue(item, 'l_decl', 'l');
                    if (key === 'w_decl') return itemDeclValue(item, 'w_decl', 'w');
                    if (key === 'h_decl') return itemDeclValue(item, 'h_decl', 'h');
                    if (key === 'cbm') {
                        return getItemCbm(item);
                    }
                    if (key === 'verified_data') {
                        let v = item.verified_data;
                        if ((v == null) && item.Values) v = item.Values.verified_data;
                        return (v === 1 || v === true) ? 1 : 0;
                    }
                    return item[key];
                };

                ths.forEach((th, idx) => {
                    const cfg = sortMap[idx];
                    if (!cfg) return;
                    th.style.cursor = 'pointer';
                    th.addEventListener('click', function(e) {
                        if (e.target.closest('input, select, button, a, textarea, label')) return;
                        if (currentSortKey === cfg.key) {
                            currentSortDir = -currentSortDir;
                        } else {
                            currentSortKey = cfg.key;
                            currentSortDir = 1;
                        }
                        filteredData.sort((a, b) => {
                            let av = getVal(a, cfg.key);
                            let bv = getVal(b, cfg.key);
                            if (cfg.type === 'num') {
                                av = parseFloat(av); bv = parseFloat(bv);
                                if (isNaN(av)) av = -Infinity;
                                if (isNaN(bv)) bv = -Infinity;
                                return (av - bv) * currentSortDir;
                            }
                            av = String(av || '').toLowerCase();
                            bv = String(bv || '').toLowerCase();
                            return av.localeCompare(bv) * currentSortDir;
                        });
                        renderTable(filteredData);
                    });
                });
            }

            // Setup search and filter listeners (called once at init)
            function setupSearch() {
                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');
                const applyFiltersDebounced = debounce(applyFilters, 180);
                if (parentSearch) parentSearch.addEventListener('input', applyFiltersDebounced);
                if (skuSearch) skuSearch.addEventListener('input', applyFiltersDebounced);
                const filterLabelTypeEl = document.getElementById('filterLabelType');
                if (filterLabelTypeEl) filterLabelTypeEl.addEventListener('change', applyFilters);
                const sectionFilterEl = document.getElementById('dimWtSectionFilter');
                if (sectionFilterEl) sectionFilterEl.addEventListener('change', applyDimWtSectionFilter);

                const notVerifiedBadge = document.getElementById('notVerifiedBadge');
                if (notVerifiedBadge) notVerifiedBadge.addEventListener('click', () => toggleVerifiedFilter(0));
            }

            // Toggle N Verify filter (click again to clear)
            function toggleVerifiedFilter(value) {
                verifiedFilter = (verifiedFilter === value) ? null : value;
                const notVerifiedBadge = document.getElementById('notVerifiedBadge');
                if (notVerifiedBadge) notVerifiedBadge.classList.toggle('badge-filter-active', verifiedFilter === 0);
                applyFilters();
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
                    const columns = ["SKU", "Status", "INV", "Type", "Weight ACT (Kg)", "Itm wt GW", "Item L IN", "Item W IN", "Item H IN", "Itm wt GW Decl", "Item L IN Decl", "Item W IN Decl", "Item H IN Decl", "GIRTH", "GIRTH + L", "Itm CBM", "Length (CM)", "Width (CM)", "Height (CM)", "CTN L (CM)", "CTN W (CM)", "CTN H (CM)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)", "Verified"];

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
                        "Type": {
                            key: "label_type"
                        },
                        "Weight ACT (Kg)": {
                            key: "wt_act_kg"
                        },
                        "Itm wt GW": {
                            key: "wt_act"
                        },
                        "Item L IN": {
                            key: "org_l"
                        },
                        "Item W IN": {
                            key: "org_w"
                        },
                        "Item H IN": {
                            key: "org_h"
                        },
                        "Itm wt GW Decl": {
                            key: "wt_decl"
                        },
                        "Item L IN Decl": {
                            key: "l_decl"
                        },
                        "Item W IN Decl": {
                            key: "w_decl"
                        },
                        "Item H IN Decl": {
                            key: "h_decl"
                        },
                        "GIRTH": {
                            key: "girth"
                        },
                        "GIRTH + L": {
                            key: "girth_plus_l"
                        },
                        "Itm CBM": {
                            key: "cbm"
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
                        },
                        "Verified": {
                            key: "verified_data"
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

                                        if (key === 'label_type') {
                                            value = normalizeLabelType(value);
                                        }

                                        // Organized inch dims + girth (highest=L, 2nd=W, 3rd=H)
                                        if (key === 'org_l' || key === 'org_w' || key === 'org_h' || key === 'girth' || key === 'girth_plus_l') {
                                            const d = getOrganizedItemDims(item);
                                            if (key === 'org_l') value = d.length;
                                            else if (key === 'org_w') value = d.width;
                                            else if (key === 'org_h') value = d.height;
                                            else if (key === 'girth') value = d.girth;
                                            else value = d.girthPlusL;
                                            row.push(value === null || value === undefined ? '' : Math.round(value));
                                            return;
                                        }

                                        if (key === 'wt_decl') {
                                            const v = itemDeclValue(item, 'wt_decl', 'wt_act');
                                            row.push(v === null || v === undefined || v === '' ? '' : parseFloat(v) || 0);
                                            return;
                                        }
                                        if (key === 'l_decl' || key === 'w_decl' || key === 'h_decl') {
                                            const actKey = key === 'l_decl' ? 'l' : (key === 'w_decl' ? 'w' : 'h');
                                            const v = itemDeclValue(item, key, actKey);
                                            row.push(v === null || v === undefined || v === '' ? '' : Math.round(parseFloat(v) || 0));
                                            return;
                                        }

                                        if (key === 'cbm') {
                                            const itmCbm = getItemCbm(item);
                                            row.push(itmCbm === null ? '' : parseFloat(itmCbm.toFixed(4)));
                                            return;
                                        }

                                        // Verified: export as 0/1 (real value stored in Values.verified_data)
                                        if (key === 'verified_data') {
                                            const isVerified = item.verified_data === 1 || item.verified_data === true ||
                                                (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                                            row.push(isVerified ? 1 : 0);
                                            return;
                                        }

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
                                        // Format numeric columns (WT ACT, WT DECL, L, W, H, CBM, CTN fields, etc.)
                                        else if (["wt_act_kg", "wt_act", "wt_decl", "l", "w", "h", "l_decl", "w_decl", "h_decl", "l_cm", "w_cm", "h_cm", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each"].includes(key)) {
                                            value = parseFloat(value) || 0;
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
                                } else if (["Weight ACT (Kg)", "Itm wt GW", "Item L IN", "Item W IN", "Item H IN", "Itm wt GW Decl", "Item L IN Decl", "Item W IN Decl", "Item H IN Decl", "Length (CM)", "Width (CM)", "Height (CM)", "CTN (CBM)", "CTN (CBM/Each)"].includes(col)) {
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
                            XLSX.utils.book_append_sheet(wb, ws, "Dimensions & Weight Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "dim_wt_master_export.xlsx");

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

            // ── SKU Export ──────────────────────────────────────────────────────────
            function setupSkuExport() {
                const modal        = document.getElementById('skuExportModal');
                const doBtn        = document.getElementById('doSkuExportBtn');
                const summary      = document.getElementById('skuExportSummary');
                const selCountEl   = document.getElementById('skuScopeSelectedCount');
                const filtCountEl  = document.getElementById('skuScopeFilteredCount');

                const DIM_HEADERS = [
                    'Weight ACT (Kg)', 'Itm wt GW', 'Item L IN', 'Item W IN', 'Item H IN',
                    'Itm wt GW Decl', 'Item L IN Decl', 'Item W IN Decl', 'Item H IN Decl',
                    'Length (CM)', 'Width (CM)', 'Height (CM)',
                    'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)',
                    'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)'
                ];

                /** Recalculate the summary line shown inside the modal */
                function refreshSummary() {
                    const scope  = document.querySelector('input[name="skuExportScope"]:checked')?.value || 'all';
                    const format = document.querySelector('input[name="skuExportFormat"]:checked')?.value || 'xlsx';
                    const addDim = document.getElementById('skuColBlankDim').checked;

                    const selectedSkus = getSelectedSkus();
                    const filteredNonParent = (filteredData.length > 0 ? filteredData : tableData)
                        .filter(d => d.SKU && !String(d.SKU).toUpperCase().includes('PARENT'));
                    const allNonParent = tableData
                        .filter(d => d.SKU && !String(d.SKU).toUpperCase().includes('PARENT'));

                    selCountEl.textContent  = selectedSkus.length;
                    filtCountEl.textContent = filteredNonParent.length;

                    let count = 0;
                    if (scope === 'all')      count = allNonParent.length;
                    else if (scope === 'filtered') count = filteredNonParent.length;
                    else                      count = selectedSkus.length;

                    const cols  = buildColumns();
                    const fmtLabel = { xlsx: 'Excel (.xlsx)', tsv: 'Tab-separated (.tsv)', csv: 'CSV (.csv)' }[format];
                    summary.innerHTML =
                        `<strong>${count}</strong> SKU(s) &nbsp;·&nbsp; ` +
                        `<strong>${cols.length}</strong> column(s) &nbsp;·&nbsp; ` +
                        `<strong>${fmtLabel}</strong>` +
                        (addDim ? ' &nbsp;·&nbsp; <em>includes blank dim/weight columns</em>' : '');
                }

                /** Return list of { header, getValue(item) } based on checked checkboxes */
                function buildColumns() {
                    const cols = [{ header: 'SKU', get: d => d.SKU || '' }];
                    if (document.getElementById('skuColParent').checked)
                        cols.push({ header: 'Parent', get: d => d.Parent || '' });
                    if (document.getElementById('skuColStatus').checked)
                        cols.push({ header: 'Status', get: d => d.status || '' });
                    if (document.getElementById('skuColInv').checked)
                        cols.push({ header: 'INV', get: d => d.shopify_inv !== undefined && d.shopify_inv !== null ? d.shopify_inv : '' });
                    if (document.getElementById('skuColBlankDim').checked)
                        DIM_HEADERS.forEach(h => cols.push({ header: h, get: () => '' }));
                    return cols;
                }

                /** Return selected (checked) non-parent items from the table */
                function getSelectedSkus() {
                    const selected = [];
                    document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                        const sku = cb.getAttribute('data-sku');
                        if (!sku || String(sku).toUpperCase().includes('PARENT')) return;
                        const item = tableData.find(d => d.SKU === sku);
                        if (item) selected.push(item);
                    });
                    return selected;
                }

                /** Build the rows array [[header...], [val...], ...] */
                function buildRows(items, cols) {
                    const rows = [cols.map(c => c.header)];
                    items.forEach(item => rows.push(cols.map(c => c.get(item))));
                    return rows;
                }

                /** Download as .xlsx */
                function downloadXlsx(rows, filename) {
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(rows);
                    // Column widths
                    ws['!cols'] = rows[0].map(h => ({ wch: h === 'SKU' ? 22 : h === 'Parent' ? 22 : 14 }));
                    // Header row style
                    const range = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = range.s.c; C <= range.e.c; C++) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: '343A40' } },
                            font: { bold: true, color: { rgb: 'FFFFFF' } },
                            alignment: { horizontal: 'center' }
                        };
                    }
                    XLSX.utils.book_append_sheet(wb, ws, 'SKUs');
                    XLSX.writeFile(wb, filename + '.xlsx');
                }

                /** Download as plain-text (TSV or CSV) */
                function downloadText(rows, filename, sep, ext) {
                    const content = rows.map(r =>
                        r.map(v => {
                            const s = String(v ?? '');
                            // Wrap in quotes if value contains the separator or a newline
                            return (s.includes(sep) || s.includes('\n') || s.includes('"'))
                                ? '"' + s.replace(/"/g, '""') + '"' : s;
                        }).join(sep)
                    ).join('\n');
                    const blob = new Blob([content], { type: 'text/plain;charset=utf-8;' });
                    const url  = URL.createObjectURL(blob);
                    const a    = document.createElement('a');
                    a.href     = url;
                    a.download = filename + '.' + ext;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }

                // Refresh summary whenever modal is shown or options change
                modal.addEventListener('show.bs.modal', refreshSummary);
                modal.querySelectorAll('input').forEach(el => el.addEventListener('change', refreshSummary));

                // Export button
                doBtn.addEventListener('click', function () {
                    const scope  = document.querySelector('input[name="skuExportScope"]:checked')?.value || 'all';
                    const format = document.querySelector('input[name="skuExportFormat"]:checked')?.value || 'xlsx';
                    const cols   = buildColumns();

                    const allNonParent      = tableData.filter(d => d.SKU && !String(d.SKU).toUpperCase().includes('PARENT'));
                    const filteredNonParent = (filteredData.length > 0 ? filteredData : tableData)
                        .filter(d => d.SKU && !String(d.SKU).toUpperCase().includes('PARENT'));

                    let items;
                    if (scope === 'all')           items = allNonParent;
                    else if (scope === 'filtered') items = filteredNonParent;
                    else                           items = getSelectedSkus();

                    if (items.length === 0) {
                        showToast('warning', 'No SKUs to export for the selected scope.');
                        return;
                    }

                    const rows     = buildRows(items, cols);
                    const filename = 'sku_export_' + new Date().toISOString().slice(0, 10);

                    try {
                        if (format === 'xlsx')      downloadXlsx(rows, filename);
                        else if (format === 'tsv')  downloadText(rows, filename, '\t', 'tsv');
                        else                         downloadText(rows, filename, ',', 'csv');

                        showToast('success', `Exported ${items.length} SKU(s) successfully!`);
                        bootstrap.Modal.getInstance(modal)?.hide();
                    } catch (e) {
                        console.error('SKU export error:', e);
                        showToast('danger', 'Export failed: ' + e.message);
                    }
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
                    const sampleHeader = ['SKU', 'Weight ACT (Kg)', 'Itm wt GW', 'Item L IN', 'Item W IN', 'Item H IN', 'Itm wt GW Decl', 'Item L IN Decl', 'Item W IN Decl', 'Item H IN Decl', 'Length (CM)', 'Width (CM)', 'Height (CM)', 'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)', 'Verified (0/1)'];
                    const sampleKeys = ['SKU', 'wt_act_kg', 'wt_act', 'l', 'w', 'h', 'wt_decl', 'l_decl', 'w_decl', 'h_decl', 'l_cm', 'w_cm', 'h_cm', 'ctn_l', 'ctn_w', 'ctn_h', 'ctn_cbm', 'ctn_qty', 'ctn_cbm_each', 'verified_data'];

                    // Populate the sample with ALL real SKUs (parent rows excluded)
                    const sampleData = [sampleHeader];
                    (tableData || []).forEach(item => {
                        if (item.SKU && String(item.SKU).toUpperCase().includes('PARENT')) return;
                        const row = sampleKeys.map(key => {
                            if (key === 'SKU') return item.SKU || '';
                            if (key === 'verified_data') {
                                const isVerified = item.verified_data === 1 || item.verified_data === true ||
                                    (item.Values && (item.Values.verified_data === 1 || item.Values.verified_data === true));
                                return isVerified ? 1 : 0;
                            }
                            const v = item[key];
                            return (v !== undefined && v !== null && v !== '') ? v : '';
                        });
                        sampleData.push(row);
                    });

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 16 }, // Weight ACT (Kg)
                        { wch: 14 }, // Itm wt GW
                        { wch: 12 }, // Item L IN
                        { wch: 12 }, // Item W IN
                        { wch: 12 }, // Item H IN
                        { wch: 16 }, // Itm wt GW Decl
                        { wch: 14 }, // Item L IN Decl
                        { wch: 14 }, // Item W IN Decl
                        { wch: 14 }, // Item H IN Decl
                        { wch: 12 }, // Length (CM)
                        { wch: 12 }, // Width (CM)
                        { wch: 12 }, // Height (CM)
                        { wch: 14 }, // CTN L (CM)
                        { wch: 14 }, // CTN W (CM)
                        { wch: 14 }, // CTN H (CM)
                        { wch: 15 }, // CTN (CBM)
                        { wch: 12 }, // CTN (QTY)
                        { wch: 18 }, // CTN (CBM/Each)
                        { wch: 14 }, // Verified (0/1)
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

                    XLSX.utils.book_append_sheet(wb, ws, "Dimensions & Weight Data");
                    XLSX.writeFile(wb, "dim_wt_master_sample.xlsx");
                    
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

                            // Reload data immediately so applied changes show without a refresh
                            loadData();
                            // Close modal after a short delay (keeps the success message visible briefly)
                            setTimeout(() => {
                                const modal = bootstrap.Modal.getInstance(importModal);
                                if (modal) modal.hide();
                                // Reset form
                                importFile.value = '';
                                importBtn.disabled = true;
                                importProgress.style.display = 'none';
                                importResult.style.display = 'none';
                                progressBar.style.width = '0%';
                            }, 1500);
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
                        const rowEl = checkbox.closest('tr');
                        // Only (de)select checkboxes for currently visible (filtered) rows
                        const isVisible = rowEl && rowEl.offsetParent !== null;
                        if (isVisible) {
                            checkbox.checked = selectAllCheckbox.checked;
                        }
                    });
                    updatePushButtonState();
                });
            }

            // Selection helper retained for row checkboxes (bulk via Action column Edit)
            function updatePushButtonState() {}

            /** Checked non-parent products (for bulk edit via Action column pencil). */
            function getSelectedNonParentProducts() {
                const selected = [];
                const seenIds = new Set();
                document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
                    const sku = cb.getAttribute('data-sku');
                    if (!sku || String(sku).toUpperCase().includes('PARENT')) return;
                    const item = tableData.find(d => d.SKU === sku);
                    if (!item) return;
                    const idKey = item.id != null ? String(item.id) : ('sku:' + String(item.SKU));
                    if (seenIds.has(idKey)) return;
                    seenIds.add(idKey);
                    selected.push(item);
                });
                return selected;
            }

            /** All non-parent child SKUs that share any of the given Parent values. */
            function getChildSkusForParents(parentKeys) {
                const parents = new Set(
                    [...(parentKeys || [])]
                        .filter(Boolean)
                        .map(p => String(p).trim())
                        .filter(Boolean)
                );
                if (parents.size === 0) return [];
                const seen = new Set();
                const result = [];
                for (const item of (tableData || [])) {
                    if (!item || !item.Parent || !parents.has(String(item.Parent).trim())) continue;
                    if (isParentSkuString(item.SKU)) continue;
                    const key = item.id != null ? ('id:' + item.id) : ('sku:' + String(item.SKU));
                    if (seen.has(key)) continue;
                    seen.add(key);
                    result.push(item);
                }
                return result;
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

            function isParentSkuString(sku) {
                return sku && String(sku).toUpperCase().includes('PARENT');
            }

            async function saveInstructionsItemPkg(productId, sku, instructionsRaw) {
                const body = {
                    product_id: parseInt(productId, 10),
                    sku: sku || '',
                    instructions: instructionsRaw != null ? String(instructionsRaw) : '',
                };
                const response = await fetch('/instructions-item-pkg/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Failed to save Instructions item PKG');
                }
                return data;
            }

            function setEditItemPkgCoverPreview(url) {
                const box = document.getElementById('editItemPkgCoverPreview');
                if (!box) return;
                const u = (url || '').trim();
                box.innerHTML = u
                    ? `<img src="${escapeHtml(u)}" alt="Cover" style="max-width:100%;max-height:100%;object-fit:contain;">`
                    : '<span class="text-muted small">No cover</span>';
            }

            async function saveItemPkgCover(productId, sku, path) {
                const response = await fetch(@json(route('purchase-order.item-pkg-cover')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        product_id: parseInt(productId, 10),
                        sku: sku || '',
                        path: path != null ? String(path) : '',
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || 'Failed to save Itm pkg Cover');
                }
                return data;
            }

            document.getElementById('editItemPkgCoverInput')?.addEventListener('input', function () {
                setEditItemPkgCoverPreview(this.value || '');
            });

            const BULK_EDIT_TRACKED_FIELDS = [
                { id: 'editWtActKg', key: 'wt_act_kg', type: 'num' },
                { id: 'editWtAct', key: 'wt_act', type: 'num' },
                { id: 'editWtDecl', key: 'wt_decl', type: 'num' },
                { id: 'editL', key: 'l', type: 'num' },
                { id: 'editW', key: 'w', type: 'num' },
                { id: 'editH', key: 'h', type: 'num' },
                { id: 'editLDecl', key: 'l_decl', type: 'num' },
                { id: 'editWDecl', key: 'w_decl', type: 'num' },
                { id: 'editHDecl', key: 'h_decl', type: 'num' },
                { id: 'editLCm', key: 'l_cm', type: 'num' },
                { id: 'editWCm', key: 'w_cm', type: 'num' },
                { id: 'editHCm', key: 'h_cm', type: 'num' },
                { id: 'editCtnL', key: 'ctn_l', type: 'num' },
                { id: 'editCtnW', key: 'ctn_w', type: 'num' },
                { id: 'editCtnH', key: 'ctn_h', type: 'num' },
                { id: 'editCtnQty', key: 'ctn_qty', type: 'num' },
                { id: 'editCtnInstructions', key: 'ctn_instructions', type: 'text100' },
            ];

            function snapshotBulkEditFormValues() {
                const snap = {};
                BULK_EDIT_TRACKED_FIELDS.forEach(f => {
                    const el = document.getElementById(f.id);
                    snap[f.id] = el ? String(el.value ?? '') : '';
                });
                const pkgEl = document.getElementById('editInstructionsItemPkg');
                snap.editInstructionsItemPkg = pkgEl ? String(pkgEl.value ?? '') : '';
                const coverEl = document.getElementById('editItemPkgCoverInput');
                snap.editItemPkgCoverInput = coverEl ? String(coverEl.value ?? '') : '';
                const verifiedEl = document.getElementById('editVerified');
                snap.editVerified = verifiedEl ? String(verifiedEl.value ?? '0') : '0';
                return snap;
            }

            function parseBulkEditFieldValue(field, raw) {
                const t = String(raw ?? '').trim();
                if (field.type === 'text100') {
                    return t === '' ? null : t.slice(0, 100);
                }
                if (t === '') return null;
                const n = parseFloat(t);
                return Number.isFinite(n) ? n : null;
            }

            function getBulkChangedFormData() {
                const data = {};
                if (!bulkEditInitialValues) return data;
                BULK_EDIT_TRACKED_FIELDS.forEach(f => {
                    const el = document.getElementById(f.id);
                    if (!el) return;
                    const now = String(el.value ?? '');
                    const was = bulkEditInitialValues[f.id] ?? '';
                    if (now === was) return;
                    data[f.key] = parseBulkEditFieldValue(f, now);
                });
                if ('ctn_l' in data || 'ctn_w' in data || 'ctn_h' in data || 'ctn_qty' in data) {
                    const ctnL = parseFloat(document.getElementById('editCtnL').value) || 0;
                    const ctnW = parseFloat(document.getElementById('editCtnW').value) || 0;
                    const ctnH = parseFloat(document.getElementById('editCtnH').value) || 0;
                    const ctnQty = parseFloat(document.getElementById('editCtnQty').value) || 0;
                    const ctnCbm = calculateCtnCbm(ctnL, ctnW, ctnH);
                    const ctnCbmEach = calculateCtnCbmEach(ctnCbm, ctnQty);
                    data.ctn_cbm = ctnCbm > 0 ? ctnCbm : null;
                    data.ctn_cbm_each = ctnCbmEach > 0 ? ctnCbmEach : null;
                }
                return data;
            }

            // Edit Dimensions & Weight Master (single, or bulk when multi-selected via pencil)
            function editDimWt(product) {
                const modal = new bootstrap.Modal(document.getElementById('editDimWtModal'));
                const isBulk = !!(bulkEditList && bulkEditList.length > 1);
                document.getElementById('editDimWtModalLabel').textContent = isBulk
                    ? ('Bulk Edit (' + bulkEditList.length + ' items)')
                    : 'Edit Dimensions & Weight Master';
                const bulkHint = document.getElementById('bulkEditOnlyChangedHint');
                if (bulkHint) bulkHint.style.display = isBulk ? 'block' : 'none';
                
                // Populate form fields
                document.getElementById('editProductId').value = product.id || '';
                document.getElementById('editSku').value = product.SKU || '';
                document.getElementById('editParent').value = product.Parent || '';
                document.getElementById('editWtActKg').value = product.wt_act_kg || '';
                document.getElementById('editWtAct').value = product.wt_act || '';
                document.getElementById('editWtDecl').value = product.wt_decl || product.wt_act || '';
                document.getElementById('editL').value = product.l || '';
                document.getElementById('editW').value = product.w || '';
                document.getElementById('editH').value = product.h || '';
                document.getElementById('editLDecl').value = product.l_decl || product.l || '';
                document.getElementById('editWDecl').value = product.w_decl || product.w || '';
                document.getElementById('editHDecl').value = product.h_decl || product.h || '';
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

                document.getElementById('editCtnQty').value = product.ctn_qty || '';

                const ctnPkgEl = document.getElementById('editCtnInstructions');
                const pkgEl = document.getElementById('editInstructionsItemPkg');
                const coverInputEl = document.getElementById('editItemPkgCoverInput');
                const skuStr = product.SKU || '';
                const coverVal = product.item_pkg_cover != null ? String(product.item_pkg_cover) : '';
                if (isParentSkuString(skuStr)) {
                    if (ctnPkgEl) {
                        ctnPkgEl.value = '';
                        ctnPkgEl.disabled = true;
                    }
                    pkgEl.value = '';
                    pkgEl.disabled = true;
                    if (coverInputEl) {
                        coverInputEl.value = '';
                        coverInputEl.disabled = true;
                        coverInputEl.dataset.initial = '';
                    }
                    setEditItemPkgCoverPreview('');
                } else {
                    if (ctnPkgEl) {
                        ctnPkgEl.disabled = false;
                        ctnPkgEl.value = (product.ctn_instructions != null ? String(product.ctn_instructions) : '').slice(0, 100);
                    }
                    pkgEl.disabled = false;
                    pkgEl.value = product.instructions_item_pkg != null ? String(product.instructions_item_pkg) : '';
                    if (coverInputEl) {
                        coverInputEl.disabled = false;
                        coverInputEl.value = coverVal;
                        coverInputEl.dataset.initial = coverVal;
                    }
                    setEditItemPkgCoverPreview(coverVal);
                }

                // Verified status
                const verifiedEl = document.getElementById('editVerified');
                if (verifiedEl) {
                    const isVerified = product.verified_data === 1 || product.verified_data === true ||
                        (product.Values && (product.Values.verified_data === 1 || product.Values.verified_data === true));
                    verifiedEl.value = isVerified ? '1' : '0';
                    verifiedEl.disabled = isParentSkuString(skuStr);
                }

                const siblingsCb = document.getElementById('editSaveAlsoToSiblings');
                if (siblingsCb) {
                    siblingsCb.checked = false;
                    siblingsCb.disabled = isParentSkuString(skuStr);
                }

                bulkEditInitialValues = isBulk ? snapshotBulkEditFormValues() : null;
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveDimWt();
                });
                
                modal.show();
            }

            // Save Dimensions & Weight Master (single, bulk, or + siblings when checkbox ticked)
            async function saveDimWt() {
                const saveBtn = document.getElementById('saveDimWtBtn');
                const originalText = saveBtn.innerHTML;
                const bulkTargets = (bulkEditList && bulkEditList.length > 1) ? bulkEditList.slice() : null;
                const applyToSiblings = !!(document.getElementById('editSaveAlsoToSiblings')?.checked);
                
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
                        l_decl: document.getElementById('editLDecl').value.trim() || null,
                        w_decl: document.getElementById('editWDecl').value.trim() || null,
                        h_decl: document.getElementById('editHDecl').value.trim() || null,
                        l_cm: document.getElementById('editLCm').value.trim() || null,
                        w_cm: document.getElementById('editWCm').value.trim() || null,
                        h_cm: document.getElementById('editHCm').value.trim() || null,
                        ctn_l: document.getElementById('editCtnL').value.trim() || null,
                        ctn_w: document.getElementById('editCtnW').value.trim() || null,
                        ctn_h: document.getElementById('editCtnH').value.trim() || null,
                        ctn_cbm: ctnCbm > 0 ? ctnCbm : null,
                        ctn_qty: document.getElementById('editCtnQty').value.trim() || null,
                        ctn_cbm_each: ctnCbmEach > 0 ? ctnCbmEach : null,
                        ctn_instructions: (document.getElementById('editCtnInstructions')?.value || '').trim().slice(0, 100) || null,
                    };

                    const instructionsRaw = document.getElementById('editInstructionsItemPkg').value;
                    const coverPath = (document.getElementById('editItemPkgCoverInput')?.value || '').trim();
                    const initialCoverPath = bulkEditInitialValues
                        ? String(bulkEditInitialValues.editItemPkgCoverInput ?? '').trim()
                        : String(document.getElementById('editItemPkgCoverInput')?.dataset?.initial || '').trim();
                    const coverChanged = coverPath !== initialCoverPath;
                    const verifiedEl = document.getElementById('editVerified');
                    const verifiedValue = verifiedEl && verifiedEl.value === '1' ? 1 : 0;

                    let targets;
                    if (bulkTargets && bulkTargets.length > 1) {
                        targets = bulkTargets.filter(p => !isParentSkuString(p.SKU));
                    } else {
                        const singleSku = document.getElementById('editSku').value;
                        const singleId = document.getElementById('editProductId').value;
                        let product = (tableData || []).find(d => singleId && String(d.id) === String(singleId))
                            || (tableData || []).find(d => d.SKU === singleSku);
                        if (!product) {
                            product = {
                                id: singleId,
                                SKU: singleSku,
                                Parent: document.getElementById('editParent').value || ''
                            };
                        }
                        targets = isParentSkuString(singleSku) ? [] : [product];
                    }

                    if (applyToSiblings) {
                        const parentKeys = targets.map(t => t.Parent).filter(Boolean);
                        const formParent = document.getElementById('editParent').value;
                        if (formParent) parentKeys.push(formParent);
                        const siblings = getChildSkusForParents(parentKeys);
                        if (siblings.length > 0) {
                            targets = siblings;
                        }
                    }

                    if (!targets || targets.length === 0) {
                        throw new Error('No child SKUs to update');
                    }

                    // Multi-target path: bulk selection and/or save-also-to-siblings
                    if (targets.length > 1 || applyToSiblings || (bulkTargets && bulkTargets.length > 1)) {
                        const isBulkSelection = !!(bulkTargets && bulkTargets.length > 1);
                        // Bulk: only changed fields. Sibling-copy from single edit: keep full form.
                        const payloadFields = isBulkSelection ? getBulkChangedFormData() : baseFormData;
                        const instructionsChanged = !isBulkSelection
                            || !bulkEditInitialValues
                            || String(instructionsRaw ?? '') !== String(bulkEditInitialValues.editInstructionsItemPkg ?? '');
                        const verifiedChanged = !isBulkSelection
                            || !bulkEditInitialValues
                            || String(verifiedValue) !== String(bulkEditInitialValues.editVerified ?? '0');

                        if (isBulkSelection
                            && Object.keys(payloadFields).length === 0
                            && !instructionsChanged
                            && !verifiedChanged
                            && !coverChanged) {
                            showToast('warning', 'No fields changed — nothing to update on selected SKUs.');
                            return;
                        }

                        let successCount = 0;
                        let failCount = 0;
                        for (const product of targets) {
                            if (isParentSkuString(product.SKU)) continue;
                            try {
                                if (Object.keys(payloadFields).length > 0) {
                                    const formData = {
                                        ...payloadFields,
                                        product_id: product.id,
                                        sku: product.SKU,
                                        parent: product.Parent || ''
                                    };
                                    const response = await fetch('/dim-wt-master/update', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken
                                        },
                                        body: JSON.stringify(formData)
                                    });
                                    if (!response.ok) {
                                        failCount++;
                                        continue;
                                    }
                                }
                                if (instructionsChanged) {
                                    try {
                                        await saveInstructionsItemPkg(product.id, product.SKU, instructionsRaw);
                                    } catch (pkgErr) {
                                        console.error('Bulk instructions save error:', pkgErr);
                                    }
                                }
                                if (coverChanged) {
                                    try {
                                        const coverData = await saveItemPkgCover(product.id, product.SKU, coverPath);
                                        if (coverData) {
                                            product.item_pkg_cover = coverData.url != null ? String(coverData.url) : coverPath;
                                        }
                                    } catch (coverErr) {
                                        console.error('Bulk cover save error:', coverErr);
                                    }
                                }
                                if (verifiedChanged) {
                                    try {
                                        await makeRequest('/product_master/update-verified', 'POST', {
                                            sku: product.SKU,
                                            verified_data: verifiedValue
                                        });
                                        product.verified_data = verifiedValue;
                                        if (!product.Values) product.Values = {};
                                        product.Values.verified_data = verifiedValue;
                                    } catch (verErr) {
                                        console.error('Bulk verified save error:', verErr);
                                    }
                                }
                                successCount++;
                            } catch (e) {
                                failCount++;
                            }
                        }
                        bulkEditList = null;
                        bulkEditInitialValues = null;
                        document.getElementById('editDimWtModalLabel').textContent = 'Edit Dimensions & Weight Master';
                        if (failCount === 0) {
                            const msg = applyToSiblings && !isBulkSelection
                                ? (successCount + ' sibling SKU(s) updated successfully!')
                                : (successCount + ' item(s) updated'
                                    + (isBulkSelection ? ' (changed fields only)' : '')
                                    + '!');
                            showToast('success', msg);
                        } else {
                            showToast('warning', successCount + ' updated, ' + failCount + ' failed.');
                        }
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                        modal.hide();
                        loadData();
                        return;
                    }
                    
                    const product = targets[0];
                    const formData = {
                        ...baseFormData,
                        product_id: product.id,
                        sku: product.SKU,
                        parent: product.Parent || ''
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

                    const singleSku = product.SKU;
                    if (!isParentSkuString(singleSku)) {
                        try {
                            await saveInstructionsItemPkg(product.id, singleSku, instructionsRaw);
                        } catch (pkgErr) {
                            showToast('warning', 'Dimensions saved, but Instructions item PKG could not be saved: ' + (pkgErr.message || ''));
                            const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                            modal.hide();
                            loadData();
                            return;
                        }
                        if (coverChanged) {
                            try {
                                const coverData = await saveItemPkgCover(product.id, singleSku, coverPath);
                                if (coverData) {
                                    product.item_pkg_cover = coverData.url != null ? String(coverData.url) : coverPath;
                                }
                            } catch (coverErr) {
                                showToast('warning', 'Dimensions saved, but Itm pkg Cover could not be saved: ' + (coverErr.message || ''));
                                const modal = bootstrap.Modal.getInstance(document.getElementById('editDimWtModal'));
                                modal.hide();
                                loadData();
                                return;
                            }
                        }

                        // Save verified status (non-parent SKUs only)
                        try {
                            await makeRequest('/product_master/update-verified', 'POST', {
                                sku: singleSku,
                                verified_data: verifiedValue
                            });
                            const row = tableData.find(d => d.SKU === singleSku);
                            if (row) {
                                row.verified_data = verifiedValue;
                                if (!row.Values) row.Values = {};
                                row.Values.verified_data = verifiedValue;
                            }
                        } catch (verErr) {
                            console.error('Verified save error:', verErr);
                        }
                    }
                    
                    showToast('success', 'Dimensions & Weight Master updated successfully!');
                    
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
             * Dim & Wt Master change history
             * ----------------------------------------------------------------------------
             * GET /shipping-master/history/{id} returns the per-field edit log written on
             * every manual update AND every sheet upload (import). The History button in
             * the Action column opens openDimWtHistoryModal().
             * ============================================================================
             */
            function dimWtHistoryFmtValue(v) {
                if (v === null || v === undefined || v === '') {
                    return '<span class="shm-empty">empty</span>';
                }
                return escapeHtml(String(v));
            }

            async function openDimWtHistoryModal(productId, sku) {
                if (!canViewDimWtHistory) return;
                const modalEl = document.getElementById('dimWtHistoryModal');
                if (!modalEl) return;
                const skuLabel = document.getElementById('dimWtHistorySku');
                const loadingEl = document.getElementById('dimWtHistoryLoading');
                const emptyEl = document.getElementById('dimWtHistoryEmpty');
                const errorEl = document.getElementById('dimWtHistoryError');
                const tableWrap = document.getElementById('dimWtHistoryTableWrap');
                const tbody = document.getElementById('dimWtHistoryTbody');

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

                    // Group rows by field; the field name only appears on the first
                    // row of each group, subsequent edits show "↳".
                    const groups = new Map();
                    rows.forEach(r => {
                        const key = r.field || '';
                        if (!groups.has(key)) {
                            groups.set(key, { label: r.field_label || r.field || '', items: [] });
                        }
                        groups.get(key).items.push(r);
                    });

                    const parts = [];
                    groups.forEach((group, fieldKey) => {
                        group.items.forEach((r, idx) => {
                            const isFirst = idx === 0;
                            const isLatest = idx === 0;
                            const rowClass = isFirst ? 'shm-field-first' : 'shm-field-cont';
                            const fieldCell = isFirst
                                ? `<i class="bi bi-tag-fill shm-field-icon"></i>${escapeHtml(group.label)}`
                                : `<span style="padding-left:14px;">↳</span>`;
                            parts.push(`
                                <tr class="${rowClass}" data-field="${escapeHtml(fieldKey)}">
                                    <td class="shm-field-cell">${fieldCell}</td>
                                    <td class="shm-when">${isLatest ? '<span class="shm-latest-dot" title="latest"></span>' : ''}${escapeHtml(r.updated_at || '')}</td>
                                    <td class="shm-who"><span class="badge bg-secondary">${escapeHtml(r.updated_by || 'N/A')}</span></td>
                                    <td>
                                        <span class="shm-old">${dimWtHistoryFmtValue(r.old_value)}</span>
                                        <i class="bi bi-arrow-right shm-arrow"></i>
                                        <span class="shm-new">${dimWtHistoryFmtValue(r.new_value)}</span>
                                    </td>
                                </tr>
                            `);
                        });
                    });

                    tbody.innerHTML = parts.join('');
                    if (tableWrap) tableWrap.style.display = 'block';
                } catch (err) {
                    console.error('Dim & Wt history load error:', err);
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
                                updateCounts();
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

            // Type column – Label Type dropdown (ENV / STD / O-Size / Pallet)
            function setupLabelTypeDropdowns() {
                document.addEventListener('change', async function(e) {
                    if (!e.target || !e.target.classList.contains('label-type-dropdown')) return;
                    const dropdown = e.target;
                    const sku = dropdown.getAttribute('data-sku');
                    const productId = dropdown.getAttribute('data-id');
                    const prev = dropdown.getAttribute('data-prev') || 'STD';
                    const labelType = normalizeLabelType(dropdown.value);
                    dropdown.value = labelType;
                    applyLabelTypeColor(dropdown, labelType);
                    dropdown.disabled = true;
                    try {
                        const response = await fetch('/dim-wt-master/update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                product_id: productId ? Number(productId) : undefined,
                                sku: sku,
                                label_type: labelType
                            })
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || 'Failed to update Label Type');
                        }
                        dropdown.setAttribute('data-prev', labelType);
                        const product = tableData.find(d =>
                            (productId && String(d.id) === String(productId)) || d.SKU === sku
                        );
                        if (product) {
                            product.label_type = labelType;
                            if (product.Values && typeof product.Values === 'object') {
                                product.Values.label_type = labelType;
                            }
                        }
                        showToast('success', 'Label Type updated');
                    } catch (err) {
                        const restored = normalizeLabelType(prev);
                        dropdown.value = restored;
                        applyLabelTypeColor(dropdown, restored);
                        showToast('danger', err.message || 'Failed to update Label Type');
                    } finally {
                        dropdown.disabled = false;
                    }
                });
            }
            setupLabelTypeDropdowns();

            // Initialize (search and playback listeners once to avoid duplicates on reload)
            setupSearch();
            setupSort();
            setupProductPlaybackListeners();
            loadData();
            setupExcelExport();
            setupImport();
            setupSkuExport();
            setupSelectAll();
            // Reset bulk edit state when edit modal is closed (e.g. without saving)
            document.getElementById('editDimWtModal').addEventListener('hidden.bs.modal', function() {
                bulkEditList = null;
                bulkEditInitialValues = null;
                document.getElementById('editDimWtModalLabel').textContent = 'Edit Dimensions & Weight Master';
                const bulkHint = document.getElementById('bulkEditOnlyChangedHint');
                if (bulkHint) bulkHint.style.display = 'none';
                const siblingsCb = document.getElementById('editSaveAlsoToSiblings');
                if (siblingsCb) siblingsCb.checked = false;
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

        });
    </script>
@endsection

