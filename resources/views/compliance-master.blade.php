@extends('layouts.vertical', ['title' => 'Compliance Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 15px 18px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.2s ease;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 12px;
            width: 100%;
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

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
            transition: all 0.2s ease;
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

        /* Parent summary rows (SKU or Parent contains PARENT) */
        #compliance-master-datatable tbody tr.compliance-row-parent-keyword > td {
            background-color: #fff9c4 !important;
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword:hover > td {
            background-color: #fff59d !important;
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword > td.compliance-status-col {
            background-color: #fff9c4 !important;
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword:hover > td.compliance-status-col {
            background-color: #fff59d !important;
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword td.compliance-parent-col:hover {
            background-color: #fffde7 !important;
        }

        #compliance-master-datatable tbody tr.compliance-row-parent-keyword:hover td.compliance-parent-col:hover {
            background-color: #fff9c4 !important;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .edit-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        .delete-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
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

        .compliance-thumb-wrap {
            display: inline-block;
            line-height: 0;
            cursor: zoom-in;
        }

        .compliance-thumb-wrap img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .compliance-thumb-wrap:hover img {
            box-shadow: 0 4px 14px rgba(26, 86, 183, 0.35);
            transform: scale(1.05);
        }

        #compliance-img-hover-preview {
            position: fixed;
            z-index: 10050;
            display: none;
            pointer-events: none;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
            border-radius: 8px;
            background: #fff;
            padding: 6px;
            line-height: 0;
        }

        #compliance-img-hover-preview img {
            max-width: min(92vw, 380px);
            max-height: min(85vh, 380px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            border-radius: 4px;
        }

        /* Parent column: ~30% narrower than a typical ~14rem column (14 × 0.7 ≈ 9.8rem) */
        #compliance-master-datatable th.compliance-parent-col,
        #compliance-master-datatable td.compliance-parent-col {
            max-width: 9.8rem;
            width: 9.8rem;
            box-sizing: border-box;
        }

        #compliance-master-datatable td.compliance-parent-col {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap !important;
            text-align: left;
        }

        #compliance-master-datatable td.compliance-parent-col:hover {
            white-space: normal !important;
            word-break: break-word;
            overflow: visible;
            max-width: min(28rem, 78vw);
            width: max-content;
            min-width: 9.8rem;
            position: relative;
            z-index: 25;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            background-color: #fff;
        }

        #compliance-master-datatable tbody tr:nth-child(even) td.compliance-parent-col:hover {
            background-color: #f8fafc;
        }

        #compliance-master-datatable tbody tr:hover td.compliance-parent-col:hover {
            background-color: #e8f0fe;
        }

        /* Status column: CP / product-master style — #284a9e header, pill filter, marble + label cells */
        #compliance-master-datatable thead th.compliance-status-col {
            background: #284a9e !important;
            color: #fff !important;
            text-align: center;
            vertical-align: top;
            padding: 10px 8px 8px;
            text-transform: none;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }

        #compliance-master-datatable thead th.compliance-status-col:hover {
            background: #3257b0 !important;
        }

        .cm-status-header-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-align: center;
            color: #fff;
            margin-bottom: 6px;
        }

        .cm-status-filter-wrap {
            position: relative;
            width: 100%;
        }

        .cm-status-filter-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .cm-status-filter-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .cm-status-filter-menu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 8px;
            background: rgba(30, 34, 42, 0.88);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
            z-index: 4000;
        }

        .cm-status-filter-wrap.is-open .cm-status-filter-menu {
            display: block;
        }

        .cm-status-filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            margin: 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .cm-status-filter-item:hover,
        .cm-status-filter-item.is-selected {
            background: #2563eb;
        }

        .cm-status-filter-check {
            display: inline-flex;
            width: 18px;
            height: 18px;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .cm-status-filter-item-spacer {
            width: 18px;
            flex-shrink: 0;
        }

        .cm-status-marble {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow:
                inset 2px 2px 4px rgba(255, 255, 255, 0.55),
                inset -2px -3px 5px rgba(0, 0, 0, 0.35);
            vertical-align: middle;
        }

        .cm-status-marble--active {
            background: radial-gradient(circle at 32% 28%, #bbf7d0, #22c55e 42%, #14532d);
        }

        .cm-status-marble--inactive,
        .cm-status-marble--dc {
            background: radial-gradient(circle at 32% 28%, #fecaca, #ef4444 42%, #7f1d1d);
        }

        .cm-status-marble--upcoming {
            background: radial-gradient(circle at 32% 28%, #fef9c3, #eab308 45%, #713f12);
        }

        .cm-status-marble--2bdc {
            background: radial-gradient(circle at 32% 28%, #bfdbfe, #2563eb 45%, #1e3a8a);
        }

        .cm-status-marble--muted {
            background: radial-gradient(circle at 32% 28%, #e5e7eb, #9ca3af 45%, #374151);
        }

        #compliance-master-datatable tbody td.compliance-status-col {
            background-color: #f8f9fa !important;
            color: #4a5568;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        #compliance-master-datatable tbody td.compliance-status-col .cm-status-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        #compliance-master-datatable tbody td.compliance-status-col .cm-status-cell-text {
            line-height: 1.2;
        }

        #compliance-master-datatable tbody tr:hover td.compliance-status-col {
            background-color: #eef1f5 !important;
            color: #2d3748;
        }

        #compliance-master-datatable tbody tr:nth-child(even) td.compliance-status-col {
            background-color: #f8f9fa !important;
        }

        #compliance-master-datatable tbody tr:nth-child(even):hover td.compliance-status-col {
            background-color: #eef1f5 !important;
        }

        #compliance-master-datatable th.compliance-checkbox-col,
        #compliance-master-datatable td.compliance-checkbox-cell {
            width: 44px;
            max-width: 44px;
            text-align: center;
            vertical-align: middle;
            padding-left: 8px;
            padding-right: 8px;
        }

        #complianceSelectAllCheckbox {
            cursor: pointer;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-primary {
            background-color: #0d6efd;
            color: #fff;
        }

        .compliance-field-thumb {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
        }

        .compliance-na-badge {
            background-color: #fde047 !important;
            color: #422006 !important;
            font-weight: 600;
            border: 1px solid rgba(113, 63, 18, 0.2);
        }

        .compliance-req-badge {
            background-color: #0dcaf0 !important;
            color: #fff !important;
            font-weight: 600;
        }

        .compliance-pdf-link {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }

        .compliance-pdf-icon-bg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background-color: #22c55e;
            color: #fff;
            font-size: 15px;
            line-height: 1;
            transition: background-color 0.15s ease;
        }

        .compliance-pdf-link:hover .compliance-pdf-icon-bg {
            background-color: #16a34a;
            color: #fff;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__cmFields = [
            'battery' => 'Battery',
            'wireless' => 'Wireless',
            'electric' => 'Electric',
            'gcc' => 'GCC',
            'blanket' => 'Blanket',
            'bluetooth' => 'Bluetooth',
            'logo' => 'Logo',
            'graph' => 'Graph',
        ];
    @endphp

    @include('layouts.shared.page-title', [
        'page_title' => 'Compliance Masters',
        'sub_title' => 'Compliance Masters Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control"
                                    placeholder="Search compliance...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-outline-primary me-2" id="complianceBulkEditBtn" title="Edit compliance fields for all selected rows">
                                <i class="fas fa-pen-to-square me-1"></i> Bulk edit
                            </button>
                            <button type="button" class="btn btn-primary me-2" id="addComplianceBtn">
                                <i class="fas fa-plus me-1"></i> Add Compliance Data
                            </button>
                            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="fas fa-upload me-1"></i> Import Excel
                            </button>
                            <button type="button" class="btn btn-success" id="downloadExcel">
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                        </div>
                    </div>

                    <!-- Import Modal -->
                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="importModalLabel">
                                        <i class="fas fa-upload me-2"></i>Import Compliance Data
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructions:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>Download the sample file below</li>
                                            <li>Use <strong>N/A</strong> or <strong>REQ</strong> per column. After import, open each SKU to attach the <strong>image</strong> and <strong>PDF</strong> required for REQ fields.</li>
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

                    <div class="table-responsive">
                        <table id="compliance-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th class="compliance-parent-col">
                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                            <span>Parent</span>
                                            <span id="parentCount">(0)</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                            <span id="skuCount">(0)</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search SKU">
                                    </th>
                                    <th class="compliance-checkbox-col text-center">
                                        <input type="checkbox" id="complianceSelectAllCheckbox" title="Select all visible rows" aria-label="Select all visible rows">
                                    </th>
                                    <th class="compliance-status-col">
                                        <div class="cm-status-header-label">STATUS</div>
                                        <div class="cm-status-filter-wrap">
                                            <button type="button" class="cm-status-filter-trigger" aria-expanded="false" aria-haspopup="listbox" id="cmStatusFilterTrigger">
                                                <span class="cm-status-filter-trigger-label">All</span>
                                                <span style="font-size:9px;opacity:0.85;" aria-hidden="true">▼</span>
                                            </button>
                                            <input type="hidden" id="filterComplianceStatus" value="all" autocomplete="off">
                                            <div class="cm-status-filter-menu" role="listbox" id="cmStatusFilterMenu">
                                                <button type="button" class="cm-status-filter-item" data-value="all" role="option">
                                                    <span class="cm-status-filter-check" aria-hidden="true">✓</span>
                                                    <span>All</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="missing" role="option">
                                                    <span class="cm-status-filter-item-spacer"></span>
                                                    <span>Missing</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="active" role="option">
                                                    <span class="cm-status-marble cm-status-marble--active"></span>
                                                    <span>Active</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="inactive" role="option">
                                                    <span class="cm-status-marble cm-status-marble--inactive"></span>
                                                    <span>Inactive</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="DC" role="option">
                                                    <span class="cm-status-marble cm-status-marble--dc"></span>
                                                    <span>DC</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="upcoming" role="option">
                                                    <span class="cm-status-marble cm-status-marble--upcoming"></span>
                                                    <span>Upcoming</span>
                                                </button>
                                                <button type="button" class="cm-status-filter-item" data-value="2BDC" role="option">
                                                    <span class="cm-status-marble cm-status-marble--2bdc"></span>
                                                    <span>2BDC</span>
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                    <th>INV</th>
                                    <th>
                                        <div>Battery <span id="batteryMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBattery" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Wireless <span id="wirelessMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterWireless" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Electric <span id="electricMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterElectric" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>GCC <span id="gccMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterGcc" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Blanket <span id="blanketMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBlanket" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Bluetooth <span id="bluetoothMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBluetooth" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Logo <span id="logoMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLogo" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Graph <span id="graphMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterGraph" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <!-- Bulk edit modal -->
                    <div class="modal fade" id="complianceBulkEditModal" tabindex="-1" aria-labelledby="complianceBulkEditModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="complianceBulkEditModalLabel"><i class="fas fa-pen-to-square me-2"></i>Bulk edit compliance</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small mb-2" id="complianceBulkEditCountText">No rows selected.</p>
                                    <p class="small text-secondary mb-3">Each field defaults to <strong>N/A</strong>. Turn on <strong>REQ</strong> to require documentation, then upload an <strong>image</strong> and a <strong>PDF</strong> (both apply to every selected SKU for that field).</p>
                                    <div class="row g-2">
                                        @foreach ($__cmFields as $fkey => $flabel)
                                            <div class="col-md-6">
                                                <div class="compliance-field-block border rounded p-2 h-100" data-bulk-field="{{ $fkey }}">
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                                        <span class="fw-bold">{{ $flabel }}</span>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <input type="radio" class="btn-check" name="bulk_mode_{{ $fkey }}" id="bulk_na_{{ $fkey }}" value="na" checked autocomplete="off">
                                                            <label class="btn btn-outline-secondary" for="bulk_na_{{ $fkey }}">N/A</label>
                                                            <input type="radio" class="btn-check" name="bulk_mode_{{ $fkey }}" id="bulk_req_{{ $fkey }}" value="req" autocomplete="off">
                                                            <label class="btn btn-outline-primary" for="bulk_req_{{ $fkey }}">REQ</label>
                                                        </div>
                                                    </div>
                                                    <div class="bulk-compliance-req-wrap d-none mt-2" data-bulk-req-wrap="{{ $fkey }}">
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Image</label>
                                                            <input type="file" class="form-control form-control-sm bulk-compliance-img-input" accept="image/*" data-field="{{ $fkey }}">
                                                            <div class="small text-muted mt-1" data-bulk-img-status="{{ $fkey }}"></div>
                                                        </div>
                                                        <div>
                                                            <label class="form-label small mb-1">PDF</label>
                                                            <input type="file" class="form-control form-control-sm bulk-compliance-pdf-input" accept=".pdf,application/pdf" data-field="{{ $fkey }}">
                                                            <div class="small text-muted mt-1" data-bulk-pdf-status="{{ $fkey }}"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="text-danger small mt-3 mb-0 d-none" id="complianceBulkEditError"></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="complianceBulkEditApplyBtn" disabled>
                                        <i class="fas fa-save me-1"></i> Apply to selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Compliance Masters Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Compliance Master Modal -->
    <div class="modal fade" id="addComplianceModal" tabindex="-1" aria-labelledby="addComplianceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addComplianceModalLabel">Add Compliance Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addComplianceForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addComplianceSku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <select class="form-control" id="addComplianceSku" name="sku" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            @foreach ($__cmFields as $fkey => $flabel)
                                <div class="col-md-6 mb-3">
                                    <div class="compliance-field-block border rounded p-2 h-100" data-add-field="{{ $fkey }}">
                                        <label class="form-label mb-2">{{ $flabel }}</label>
                                        <div class="btn-group btn-group-sm w-100 mb-2" role="group">
                                            <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_na_{{ $fkey }}" value="na" checked autocomplete="off">
                                            <label class="btn btn-outline-secondary" for="add_na_{{ $fkey }}">N/A</label>
                                            <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_req_{{ $fkey }}" value="req" autocomplete="off">
                                            <label class="btn btn-outline-primary" for="add_req_{{ $fkey }}">REQ</label>
                                        </div>
                                        <input type="hidden" id="add_{{ $fkey }}_img_path" value="">
                                        <input type="hidden" id="add_{{ $fkey }}_pdf_path" value="">
                                        <div class="add-compliance-req-wrap d-none" id="add_{{ $fkey }}_req_wrap">
                                            <label class="form-label small">Image</label>
                                            <input type="file" class="form-control form-control-sm add-compliance-img-input" accept="image/*" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_img_path">
                                            <div class="small text-muted mt-1" id="add_{{ $fkey }}_img_status"></div>
                                            <div class="mt-2" id="add_{{ $fkey }}_img_preview"></div>
                                            <label class="form-label small mt-2">PDF</label>
                                            <input type="file" class="form-control form-control-sm add-compliance-pdf-input" accept=".pdf,application/pdf" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_pdf_path">
                                            <div class="small text-muted mt-1" id="add_{{ $fkey }}_pdf_status"></div>
                                            <div class="small mt-1" id="add_{{ $fkey }}_pdf_link"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddComplianceBtn">
                        <i class="fas fa-save me-2"></i> Save
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
            let complianceFormMode = 'add';
            let complianceEditSku = '';

            const COMPLIANCE_BULK_FIELD_KEYS = ['battery', 'wireless', 'electric', 'gcc', 'blanket', 'bluetooth', 'logo', 'graph'];
            let bulkComplianceUploadPaths = {};
            let bulkCompliancePdfPaths = {};

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            function complianceImagePublicUrl(path) {
                if (path == null || path === '') return '';
                const s = String(path).trim();
                if (s.startsWith('http://') || s.startsWith('https://')) return s;
                return '/' + s.replace(/^\/+/, '');
            }

            function complianceFieldStoredValue(item, key) {
                return item[key] != null ? String(item[key]).trim() : '';
            }

            function complianceFieldImagePath(item, key) {
                const ik = key + '_img';
                return item[ik] != null ? String(item[ik]).trim() : '';
            }

            function complianceFieldPdfPath(item, key) {
                const pk = key + '_pdf';
                return item[pk] != null ? String(item[pk]).trim() : '';
            }

            function isMissingComplianceFieldForItem(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                if (v === '' || v.toUpperCase() === 'N/A') return true;
                if (v.toUpperCase() === 'REQ') return img === '' || pdf === '';
                return false;
            }

            function complianceFieldCellHtml(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                const upper = v.toUpperCase();
                const hasDataFile = img !== '' || pdf !== '';

                let badge = '';
                if (upper === 'REQ') {
                    if (!hasDataFile) {
                        badge = '<span class="badge rounded-pill compliance-req-badge">REQ</span>';
                    }
                } else if (upper === 'N/A' || v === '') {
                    badge = '<span class="badge rounded-pill compliance-na-badge">N/A</span>';
                } else {
                    badge = `<span class="badge rounded-pill bg-info text-dark" title="Legacy value">${escapeHtml(v)}</span>`;
                }
                let thumb = '';
                if (img) {
                    const u = complianceImagePublicUrl(img);
                    thumb = ` <img class="compliance-field-thumb" src="${escapeHtml(u)}" alt="">`;
                }
                let pdfLink = '';
                if (pdf) {
                    const pu = complianceImagePublicUrl(pdf);
                    pdfLink = ` <a href="${escapeHtml(pu)}" target="_blank" rel="noopener" class="compliance-pdf-link" title="Open PDF"><span class="compliance-pdf-icon-bg"><i class="fas fa-file-pdf" aria-hidden="true"></i></span></a>`;
                }
                return `<span class="d-inline-flex align-items-center gap-1 flex-wrap justify-content-center">${badge}${thumb}${pdfLink}</span>`;
            }

            async function uploadComplianceFieldImageToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('image', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data.path || '';
            }

            async function uploadComplianceFieldPdfToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('pdf', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-pdf', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'PDF upload failed');
                }
                return data.path || '';
            }

            function resetBulkComplianceModal() {
                bulkComplianceUploadPaths = {};
                bulkCompliancePdfPaths = {};
                const modal = document.getElementById('complianceBulkEditModal');
                if (!modal) return;
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const na = modal.querySelector(`#bulk_na_${k}`);
                    const req = modal.querySelector(`#bulk_req_${k}`);
                    if (na) na.checked = true;
                    if (req) req.checked = false;
                    const wrap = modal.querySelector(`[data-bulk-req-wrap="${k}"]`);
                    if (wrap) {
                        wrap.classList.add('d-none');
                        const fi = wrap.querySelector('.bulk-compliance-img-input');
                        if (fi) fi.value = '';
                        const fp = wrap.querySelector('.bulk-compliance-pdf-input');
                        if (fp) fp.value = '';
                        const st = wrap.querySelector(`[data-bulk-img-status="${k}"]`);
                        if (st) st.textContent = '';
                        const pst = wrap.querySelector(`[data-bulk-pdf-status="${k}"]`);
                        if (pst) pst.textContent = '';
                    }
                });
            }

            function resetComplianceAddFormFields() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const na = document.getElementById(`add_na_${k}`);
                    const req = document.getElementById(`add_req_${k}`);
                    if (na) na.checked = true;
                    if (req) req.checked = false;
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = '';
                    const wrap = document.getElementById(`add_${k}_req_wrap`);
                    if (wrap) wrap.classList.add('d-none');
                    const st = document.getElementById(`add_${k}_img_status`);
                    if (st) st.textContent = '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    if (pst) pst.textContent = '';
                    const plink = document.getElementById(`add_${k}_pdf_link`);
                    if (plink) plink.innerHTML = '';
                    const prev = document.getElementById(`add_${k}_img_preview`);
                    if (prev) prev.innerHTML = '';
                    const fi = modal.querySelector(`.add-compliance-img-input[data-field="${k}"]`);
                    if (fi) fi.value = '';
                    const fp = modal.querySelector(`.add-compliance-pdf-input[data-field="${k}"]`);
                    if (fp) fp.value = '';
                });
            }

            function setAddComplianceFormFromItem(item) {
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const raw = complianceFieldStoredValue(item, k);
                    const upper = raw.toUpperCase();
                    const isReq = upper === 'REQ' || (raw !== '' && upper !== 'N/A');
                    document.getElementById(`add_na_${k}`).checked = !isReq;
                    document.getElementById(`add_req_${k}`).checked = isReq;
                    const p = complianceFieldImagePath(item, k);
                    const pdfp = complianceFieldPdfPath(item, k);
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = p;
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = pdfp;
                    const wrap = document.getElementById(`add_${k}_req_wrap`);
                    wrap.classList.toggle('d-none', !isReq);
                    const st = document.getElementById(`add_${k}_img_status`);
                    st.textContent = p ? 'Current image on file.' : '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    pst.textContent = pdfp ? 'Current PDF on file.' : '';
                    const prev = document.getElementById(`add_${k}_img_preview`);
                    if (p && isReq) {
                        const u = complianceImagePublicUrl(p);
                        prev.innerHTML = `<img class="compliance-field-thumb" src="${escapeHtml(u)}" alt="">`;
                    } else {
                        prev.innerHTML = '';
                    }
                    const plink = document.getElementById(`add_${k}_pdf_link`);
                    if (pdfp && isReq) {
                        const pu = complianceImagePublicUrl(pdfp);
                        plink.innerHTML = `<a href="${escapeHtml(pu)}" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Open current PDF</a>`;
                    } else {
                        plink.innerHTML = '';
                    }
                });
            }

            function toggleAddComplianceReqWrap(field) {
                const isReq = document.getElementById(`add_req_${field}`)?.checked;
                const wrap = document.getElementById(`add_${field}_req_wrap`);
                if (wrap) wrap.classList.toggle('d-none', !isReq);
            }

            function collectComplianceFormPayload(sku) {
                const o = { sku: String(sku || '').trim() };
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const req = document.querySelector(`#addComplianceModal input[name="add_mode_${k}"]:checked`)?.value === 'req';
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    const imgPath = pathEl ? pathEl.value.trim() : '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    const pdfPath = pdfPathEl ? pdfPathEl.value.trim() : '';
                    if (req) {
                        o[k] = 'REQ';
                        o[k + '_img'] = imgPath;
                        o[k + '_pdf'] = pdfPath;
                    } else {
                        o[k] = 'N/A';
                        o[k + '_img'] = '';
                        o[k + '_pdf'] = '';
                    }
                });
                return o;
            }

            function buildComplianceBulkPayloadForItem(item) {
                const payload = { sku: String(item.SKU || '').trim() };
                const modal = document.getElementById('complianceBulkEditModal');
                COMPLIANCE_BULK_FIELD_KEYS.forEach(key => {
                    const req = modal && modal.querySelector(`input[name="bulk_mode_${key}"]:checked`)?.value === 'req';
                    const imgKey = key + '_img';
                    const pdfKey = key + '_pdf';
                    if (req) {
                        payload[key] = 'REQ';
                        const pendingImg = bulkComplianceUploadPaths[key];
                        const existingImg = complianceFieldImagePath(item, key);
                        payload[imgKey] = pendingImg ? pendingImg : existingImg;
                        const pendingPdf = bulkCompliancePdfPaths[key];
                        const existingPdf = complianceFieldPdfPath(item, key);
                        payload[pdfKey] = pendingPdf ? pendingPdf : existingPdf;
                    } else {
                        payload[key] = 'N/A';
                        payload[imgKey] = '';
                        payload[pdfKey] = '';
                    }
                });
                return payload;
            }

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

            function setupComplianceImageHoverPreview() {
                const tbody = document.getElementById('table-body');
                const tableScroll = document.querySelector('.table-responsive');
                let previewEl = null;
                let activeWrap = null;

                function getPreview() {
                    if (!previewEl) {
                        previewEl = document.createElement('div');
                        previewEl.id = 'compliance-img-hover-preview';
                        const img = document.createElement('img');
                        previewEl.appendChild(img);
                        document.body.appendChild(previewEl);
                    }
                    return previewEl;
                }

                function hidePreview() {
                    activeWrap = null;
                    if (previewEl) previewEl.style.display = 'none';
                }

                function positionPreview(clientX, clientY) {
                    const el = getPreview();
                    if (el.style.display !== 'block') return;
                    const margin = 14;
                    const pad = 8;
                    requestAnimationFrame(() => {
                        const w = el.offsetWidth || 200;
                        const h = el.offsetHeight || 200;
                        let left = clientX + margin;
                        let top = clientY + margin;
                        if (left + w > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - w - pad);
                        if (top + h > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - h - pad);
                        if (left < pad) left = pad;
                        if (top < pad) top = pad;
                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    });
                }

                tbody.addEventListener('mouseover', function(e) {
                    const wrap = e.target.closest('.compliance-thumb-wrap');
                    if (wrap && tbody.contains(wrap)) {
                        const srcImg = wrap.querySelector('img');
                        if (!srcImg || !srcImg.getAttribute('src')) return;
                        activeWrap = wrap;
                        const el = getPreview();
                        const big = el.querySelector('img');
                        if (big.getAttribute('src') !== srcImg.src) big.src = srcImg.src;
                        el.style.display = 'block';
                        positionPreview(e.clientX, e.clientY);
                    } else {
                        hidePreview();
                    }
                });

                tbody.addEventListener('mousemove', function(e) {
                    if (!activeWrap) return;
                    if (!activeWrap.contains(e.target)) return;
                    positionPreview(e.clientX, e.clientY);
                });

                tbody.addEventListener('mouseleave', hidePreview);

                if (tableScroll) {
                    tableScroll.addEventListener('scroll', hidePreview, { passive: true });
                }
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            /** Same field as product-master: Values.status on product_master (merged into row as status). */
            function resolveProductMasterStatus(item) {
                if (!item) return '';
                let v = item.status;
                if (v === undefined || v === null || v === '') {
                    v = item.Status;
                }
                const s = v != null ? String(v).trim() : '';
                return s;
            }

            /** Canonical labels aligned with product-master status badges / select options. */
            function formatProductMasterStatusLabel(raw) {
                const s = String(raw || '').trim();
                if (!s) return '—';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'Active';
                if (lower === 'inactive') return 'Inactive';
                if (upper === 'DC') return 'DC';
                if (lower === 'upcoming') return 'Upcoming';
                if (upper === '2BDC') return '2BDC';
                return s;
            }

            function getComplianceStatusMarbleModifier(raw) {
                const s = String(raw || '').trim();
                if (!s) return 'muted';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'active';
                if (lower === 'inactive') return 'inactive';
                if (upper === 'DC') return 'dc';
                if (lower === 'upcoming') return 'upcoming';
                if (upper === '2BDC') return '2bdc';
                return 'muted';
            }

            function getComplianceStatusCellHtml(item) {
                const raw = resolveProductMasterStatus(item);
                const trimmed = String(raw || '').trim();
                if (!trimmed) {
                    return '<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--muted"></span><span class="cm-status-cell-text">—</span></span>';
                }
                const mod = getComplianceStatusMarbleModifier(trimmed);
                const label = escapeHtml(formatProductMasterStatusLabel(trimmed));
                return `<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--${mod}" title="${escapeHtml(trimmed)}"></span><span class="cm-status-cell-text">${label}</span></span>`;
            }

            function cmStatusFilterOptionLabels() {
                return {
                    all: 'All',
                    missing: 'Missing',
                    active: 'Active',
                    inactive: 'Inactive',
                    DC: 'DC',
                    upcoming: 'Upcoming',
                    '2BDC': '2BDC'
                };
            }

            function positionCmStatusFilterMenu(wrap) {
                const menu = wrap.querySelector('.cm-status-filter-menu');
                const trigger = wrap.querySelector('.cm-status-filter-trigger');
                if (!menu || !trigger) return;
                const r = trigger.getBoundingClientRect();
                const w = Math.max(r.width, 200);
                menu.style.position = 'fixed';
                menu.style.top = (r.bottom + 4) + 'px';
                menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                menu.style.minWidth = w + 'px';
                menu.style.zIndex = '4000';
            }

            function refreshCmStatusFilterUI() {
                const hidden = document.getElementById('filterComplianceStatus');
                const wrap = document.querySelector('#compliance-master-datatable .cm-status-filter-wrap');
                if (!hidden || !wrap) return;
                const trigger = wrap.querySelector('.cm-status-filter-trigger');
                const labelEl = trigger && trigger.querySelector('.cm-status-filter-trigger-label');
                const v = hidden.value || 'all';
                const map = cmStatusFilterOptionLabels();
                if (labelEl) {
                    labelEl.textContent = Object.prototype.hasOwnProperty.call(map, v) ? map[v] : v;
                }
                wrap.querySelectorAll('.cm-status-filter-item').forEach(btn => {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-value') === v);
                });
            }

            let complianceStatusFilterDocClickBound = false;

            function setupComplianceStatusFilter() {
                if (complianceStatusFilterDocClickBound) return;
                if (!document.getElementById('filterComplianceStatus')) return;
                complianceStatusFilterDocClickBound = true;

                document.addEventListener('click', function(e) {
                    const wrap = e.target.closest('.cm-status-filter-wrap');
                    const table = document.getElementById('compliance-master-datatable');
                    if (!table) return;

                    const item = e.target.closest('.cm-status-filter-item');
                    const trigger = e.target.closest('.cm-status-filter-trigger');

                    if (item && wrap && table.contains(wrap)) {
                        e.preventDefault();
                        e.stopPropagation();
                        const val = item.getAttribute('data-value');
                        const hidden = document.getElementById('filterComplianceStatus');
                        if (!hidden) return;
                        hidden.value = val;
                        wrap.classList.remove('is-open');
                        const trg = wrap.querySelector('.cm-status-filter-trigger');
                        if (trg) trg.setAttribute('aria-expanded', 'false');
                        refreshCmStatusFilterUI();
                        applyFilters();
                        return;
                    }

                    if (trigger && wrap && table.contains(wrap)) {
                        e.preventDefault();
                        e.stopPropagation();
                        const wasOpen = wrap.classList.contains('is-open');
                        table.querySelectorAll('.cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                        if (!wasOpen) {
                            wrap.classList.add('is-open');
                            trigger.setAttribute('aria-expanded', 'true');
                            positionCmStatusFilterMenu(wrap);
                        }
                        return;
                    }

                    if (!wrap) {
                        table.querySelectorAll('.cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                    }
                });
            }

            // Load compliance data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/compliance-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                            setupSearch();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load compliance data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            function complianceRowHasParentKeyword(item) {
                const sku = String(item.SKU || '').toUpperCase();
                const par = String(item.Parent || '').toUpperCase();
                return sku.includes('PARENT') || par.includes('PARENT');
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="15" class="text-center">No compliance data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    if (complianceRowHasParentKeyword(item)) {
                        row.classList.add('compliance-row-parent-keyword');
                    }

                    // Image column
                    const imageCell = document.createElement('td');
                    if (item.image_path) {
                        const wrap = document.createElement('span');
                        wrap.className = 'compliance-thumb-wrap';
                        const img = document.createElement('img');
                        img.src = item.image_path;
                        img.alt = '';
                        img.className = 'compliance-thumb-img';
                        wrap.appendChild(img);
                        imageCell.appendChild(wrap);
                    } else {
                        imageCell.textContent = '-';
                    }
                    row.appendChild(imageCell);

                    // Parent column
                    const parentCell = document.createElement('td');
                    parentCell.className = 'compliance-parent-col';
                    const rawParent = item.Parent != null && item.Parent !== '' ? String(item.Parent) : '';
                    parentCell.textContent = rawParent || '-';
                    if (rawParent) parentCell.title = rawParent;
                    row.appendChild(parentCell);

                    // SKU column (server sends normalized spacing; textContent is plain text, no HTML entities)
                    const skuCell = document.createElement('td');
                    skuCell.textContent = item.SKU != null && String(item.SKU) !== '' ? String(item.SKU) : '-';
                    row.appendChild(skuCell);

                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'compliance-checkbox-cell';
                    if (complianceRowHasParentKeyword(item)) {
                        checkboxCell.innerHTML = '<span class="text-muted user-select-none" title="Parent summary rows cannot be bulk-edited">—</span>';
                    } else {
                        const cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.className = 'compliance-row-checkbox';
                        cb.dataset.sku = String(item.SKU || '');
                        cb.setAttribute('aria-label', 'Select row for bulk edit');
                        checkboxCell.appendChild(cb);
                    }
                    row.appendChild(checkboxCell);

                    // Status column (product-master style: marble + label)
                    const statusCell = document.createElement('td');
                    statusCell.className = 'compliance-status-col';
                    statusCell.innerHTML = getComplianceStatusCellHtml(item);
                    row.appendChild(statusCell);

                    // INV column
                    const invCell = document.createElement('td');
                    if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    COMPLIANCE_BULK_FIELD_KEYS.forEach(fk => {
                        const c = document.createElement('td');
                        c.className = 'text-center';
                        if (complianceRowHasParentKeyword(item)) {
                            c.innerHTML = '<span class="text-muted user-select-none">—</span>';
                        } else {
                            c.innerHTML = complianceFieldCellHtml(item, fk);
                        }
                        row.appendChild(c);
                    });

                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    actionCell.innerHTML = `
                        <div class="d-inline-flex">
                            <button type="button" class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="${escapeHtml(item.SKU)}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}">
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    `;
                    row.appendChild(actionCell);

                    tbody.appendChild(row);
                });

                syncComplianceSelectAllCheckbox();
            }

            function getComplianceRowCheckboxes() {
                return [...document.querySelectorAll('#table-body .compliance-row-checkbox')];
            }

            function syncComplianceSelectAllCheckbox() {
                const master = document.getElementById('complianceSelectAllCheckbox');
                if (!master) return;
                const boxes = getComplianceRowCheckboxes();
                const n = boxes.length;
                const checked = boxes.filter(b => b.checked).length;
                master.checked = n > 0 && checked === n;
                master.indeterminate = checked > 0 && checked < n;
            }

            function updateComplianceBulkEditModalState() {
                const n = getComplianceRowCheckboxes().filter(b => b.checked).length;
                const countEl = document.getElementById('complianceBulkEditCountText');
                const applyBtn = document.getElementById('complianceBulkEditApplyBtn');
                if (countEl) {
                    countEl.textContent = n
                        ? `${n} row(s) selected.`
                        : 'No rows selected. Select checkboxes in the table first.';
                }
                if (applyBtn) {
                    applyBtn.disabled = n === 0;
                }
            }

            function setupComplianceBulkEdit() {
                const tbody = document.getElementById('table-body');
                const master = document.getElementById('complianceSelectAllCheckbox');
                const bulkBtn = document.getElementById('complianceBulkEditBtn');
                const applyBtn = document.getElementById('complianceBulkEditApplyBtn');
                const modalEl = document.getElementById('complianceBulkEditModal');

                if (!tbody || !master || !bulkBtn || !applyBtn || !modalEl) return;

                master.addEventListener('change', function() {
                    getComplianceRowCheckboxes().forEach(cb => {
                        cb.checked = master.checked;
                    });
                    syncComplianceSelectAllCheckbox();
                    if (modalEl.classList.contains('show')) {
                        updateComplianceBulkEditModalState();
                    }
                });

                tbody.addEventListener('change', function(e) {
                    if (e.target.classList.contains('compliance-row-checkbox')) {
                        syncComplianceSelectAllCheckbox();
                        if (modalEl.classList.contains('show')) {
                            updateComplianceBulkEditModalState();
                        }
                    }
                });

                modalEl.addEventListener('show.bs.modal', function() {
                    resetBulkComplianceModal();
                });

                modalEl.addEventListener('change', function(e) {
                    const name = e.target.getAttribute('name');
                    if (name && name.startsWith('bulk_mode_')) {
                        const wrap = modalEl.querySelector(`[data-bulk-req-wrap="${name.replace('bulk_mode_', '')}"]`);
                        const isReq = modalEl.querySelector(`input[name="${name}"]:checked`)?.value === 'req';
                        if (wrap) wrap.classList.toggle('d-none', !isReq);
                        updateComplianceBulkEditModalState();
                    }
                });

                modalEl.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.bulk-compliance-img-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const wrap = inp.closest('[data-bulk-req-wrap]');
                    const statusEl = wrap ? wrap.querySelector(`[data-bulk-img-status="${field}"]`) : null;
                    try {
                        if (statusEl) statusEl.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldImageToServer(field, inp.files[0]);
                        bulkComplianceUploadPaths[field] = path;
                        if (statusEl) statusEl.textContent = 'Image ready.';
                    } catch (err) {
                        if (statusEl) statusEl.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                modalEl.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.bulk-compliance-pdf-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const wrap = inp.closest('[data-bulk-req-wrap]');
                    const statusEl = wrap ? wrap.querySelector(`[data-bulk-pdf-status="${field}"]`) : null;
                    try {
                        if (statusEl) statusEl.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldPdfToServer(field, inp.files[0]);
                        bulkCompliancePdfPaths[field] = path;
                        if (statusEl) statusEl.textContent = 'PDF ready.';
                    } catch (err) {
                        if (statusEl) statusEl.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                bulkBtn.addEventListener('click', function() {
                    const n = getComplianceRowCheckboxes().filter(b => b.checked).length;
                    const errEl = document.getElementById('complianceBulkEditError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    updateComplianceBulkEditModalState();
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                    if (n === 0) {
                        showToast('warning', 'Select at least one row using the checkboxes.');
                    }
                });

                modalEl.addEventListener('shown.bs.modal', function() {
                    updateComplianceBulkEditModalState();
                });

                applyBtn.addEventListener('click', async function() {
                    const errEl = document.getElementById('complianceBulkEditError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    const skus = getComplianceRowCheckboxes().filter(b => b.checked).map(b => b.dataset.sku).filter(Boolean);
                    if (!skus.length) {
                        showToast('warning', 'No rows selected.');
                        return;
                    }
                    applyBtn.disabled = true;
                    let ok = 0;
                    const failed = [];
                    try {
                        for (const sku of skus) {
                            const item = findComplianceRowBySku(sku);
                            if (!item) {
                                failed.push(sku + ': row not found');
                                continue;
                            }
                            const payload = buildComplianceBulkPayloadForItem(item);
                            const response = await fetch('/compliance-master/update', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify(payload)
                            });
                            const data = await response.json().catch(() => ({}));
                            if (response.ok && data.success !== false) {
                                ok++;
                            } else {
                                failed.push(sku + ': ' + (data.message || response.status));
                            }
                        }
                        if (failed.length === 0) {
                            showToast('success', `Updated ${ok} row(s).`);
                            bootstrap.Modal.getInstance(modalEl)?.hide();
                            resetBulkComplianceModal();
                            loadData();
                        } else {
                            errEl.textContent = failed.slice(0, 8).join('\n') + (failed.length > 8 ? '\n…' : '');
                            errEl.classList.remove('d-none');
                            showToast('warning', `Updated ${ok}; ${failed.length} failed. See message in modal.`);
                            if (ok > 0) {
                                loadData();
                            }
                        }
                    } catch (e) {
                        console.error(e);
                        showToast('danger', e.message || 'Bulk update failed.');
                    } finally {
                        updateComplianceBulkEditModalState();
                    }
                });
            }

            // Check if value is missing (null, undefined, empty)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let batteryMissingCount = 0;
                let wirelessMissingCount = 0;
                let electricMissingCount = 0;
                let gccMissingCount = 0;
                let blanketMissingCount = 0;
                let bluetoothMissingCount = 0;
                let logoMissingCount = 0;
                let graphMissingCount = 0;

                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Count missing data for each column (REQ without image = missing)
                    if (isMissingComplianceFieldForItem(item, 'battery')) batteryMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'wireless')) wirelessMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'electric')) electricMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'gcc')) gccMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'blanket')) blanketMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'bluetooth')) bluetoothMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'logo')) logoMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'graph')) graphMissingCount++;
                });
                
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
                document.getElementById('batteryMissingCount').textContent = `(${batteryMissingCount})`;
                document.getElementById('wirelessMissingCount').textContent = `(${wirelessMissingCount})`;
                document.getElementById('electricMissingCount').textContent = `(${electricMissingCount})`;
                document.getElementById('gccMissingCount').textContent = `(${gccMissingCount})`;
                document.getElementById('blanketMissingCount').textContent = `(${blanketMissingCount})`;
                document.getElementById('bluetoothMissingCount').textContent = `(${bluetoothMissingCount})`;
                document.getElementById('logoMissingCount').textContent = `(${logoMissingCount})`;
                document.getElementById('graphMissingCount').textContent = `(${graphMissingCount})`;
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Parent search filter
                    const parentSearch = document.getElementById('parentSearch').value.toLowerCase();
                    if (parentSearch && !(item.Parent || '').toLowerCase().includes(parentSearch)) {
                        return false;
                    }

                    // SKU search filter
                    const skuSearch = document.getElementById('skuSearch').value.toLowerCase();
                    if (skuSearch && !(item.SKU || '').toLowerCase().includes(skuSearch)) {
                        return false;
                    }

                    // Custom search filter
                    const customSearch = document.getElementById('customSearch').value.toLowerCase();
                    if (customSearch) {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const statusHaystack = resolveProductMasterStatus(item).toLowerCase();
                        const statusLabel = formatProductMasterStatusLabel(resolveProductMasterStatus(item)).toLowerCase();
                        if (!parent.includes(customSearch) && !sku.includes(customSearch)
                            && !statusHaystack.includes(customSearch) && !statusLabel.includes(customSearch)) {
                            return false;
                        }
                    }

                    const filterComplianceStatusEl = document.getElementById('filterComplianceStatus');
                    const filterComplianceStatus = filterComplianceStatusEl ? filterComplianceStatusEl.value : 'all';
                    if (filterComplianceStatus === 'missing') {
                        if (!isMissing(resolveProductMasterStatus(item))) {
                            return false;
                        }
                    } else if (filterComplianceStatus !== 'all') {
                        const st = resolveProductMasterStatus(item);
                        if (!st || String(st).toLowerCase() !== String(filterComplianceStatus).toLowerCase()) {
                            return false;
                        }
                    }

                    // Battery filter
                    const filterBattery = document.getElementById('filterBattery').value;
                    if (filterBattery === 'missing' && !isMissingComplianceFieldForItem(item, 'battery')) {
                        return false;
                    }

                    // Wireless filter
                    const filterWireless = document.getElementById('filterWireless').value;
                    if (filterWireless === 'missing' && !isMissingComplianceFieldForItem(item, 'wireless')) {
                        return false;
                    }

                    // Electric filter
                    const filterElectric = document.getElementById('filterElectric').value;
                    if (filterElectric === 'missing' && !isMissingComplianceFieldForItem(item, 'electric')) {
                        return false;
                    }

                    if (document.getElementById('filterGcc').value === 'missing' && !isMissingComplianceFieldForItem(item, 'gcc')) {
                        return false;
                    }
                    if (document.getElementById('filterBlanket').value === 'missing' && !isMissingComplianceFieldForItem(item, 'blanket')) {
                        return false;
                    }
                    if (document.getElementById('filterBluetooth').value === 'missing' && !isMissingComplianceFieldForItem(item, 'bluetooth')) {
                        return false;
                    }
                    if (document.getElementById('filterLogo').value === 'missing' && !isMissingComplianceFieldForItem(item, 'logo')) {
                        return false;
                    }

                    // Graph filter
                    const filterGraph = document.getElementById('filterGraph').value;
                    if (filterGraph === 'missing' && !isMissingComplianceFieldForItem(item, 'graph')) {
                        return false;
                    }

                    return true;
                });
                renderTable(filteredData);
            }

            // Setup search functionality
            function setupSearch() {
                // Parent search
                const parentSearch = document.getElementById('parentSearch');
                parentSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // SKU search
                const skuSearch = document.getElementById('skuSearch');
                skuSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Custom search
                const customSearch = document.getElementById('customSearch');
                customSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Clear search
                document.getElementById('clearSearch').addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    // Reset all column filters
                    document.getElementById('filterBattery').value = 'all';
                    document.getElementById('filterWireless').value = 'all';
                    document.getElementById('filterElectric').value = 'all';
                    document.getElementById('filterGcc').value = 'all';
                    document.getElementById('filterBlanket').value = 'all';
                    document.getElementById('filterBluetooth').value = 'all';
                    document.getElementById('filterLogo').value = 'all';
                    document.getElementById('filterGraph').value = 'all';
                    const fcs = document.getElementById('filterComplianceStatus');
                    if (fcs) fcs.value = 'all';
                    document.querySelectorAll('#compliance-master-datatable .cm-status-filter-wrap.is-open').forEach(x => {
                        x.classList.remove('is-open');
                        const t = x.querySelector('.cm-status-filter-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    });
                    refreshCmStatusFilterUI();
                    applyFilters();
                });

                // Column filters
                document.getElementById('filterBattery').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterWireless').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterElectric').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterGcc').addEventListener('change', function() {
                    applyFilters();
                });
                document.getElementById('filterBlanket').addEventListener('change', function() {
                    applyFilters();
                });
                document.getElementById('filterBluetooth').addEventListener('change', function() {
                    applyFilters();
                });
                document.getElementById('filterLogo').addEventListener('change', function() {
                    applyFilters();
                });

                document.getElementById('filterGraph').addEventListener('change', function() {
                    applyFilters();
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
                    // Columns to export (excluding Image and Action)
                    const columns = ["Parent", "SKU", "Status", "INV", "Battery", "Wireless", "Electric", "GCC", "Blanket", "Bluetooth", "Logo", "Graph"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "Status": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Battery": {
                            key: "battery"
                        },
                        "Wireless": {
                            key: "wireless"
                        },
                        "Electric": {
                            key: "electric"
                        },
                        "GCC": {
                            key: "gcc"
                        },
                        "Blanket": {
                            key: "blanket"
                        },
                        "Bluetooth": {
                            key: "bluetooth"
                        },
                        "Logo": {
                            key: "logo"
                        },
                        "Graph": {
                            key: "graph"
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

                            // Add data rows
                            dataToExport.forEach(item => {
                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        if (col === 'Status') {
                                            const lbl = formatProductMasterStatusLabel(resolveProductMasterStatus(item));
                                            value = lbl === '—' ? '' : lbl;
                                        } else if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
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
                                if (["Parent", "SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Status", "Battery", "Wireless", "Electric", "GCC", "Blanket", "Bluetooth", "Logo", "Graph"].includes(col)) {
                                    return { wch: 15 };
                                } else {
                                    return { wch: 10 }; // Default width for numeric columns
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
                            XLSX.utils.book_append_sheet(wb, ws, "Compliance Masters");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "compliance_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Download Excel';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            function findComplianceRowBySku(sku) {
                const s = String(sku || '');
                let row = tableData.find(i => String(i.SKU) === s);
                if (row) return row;
                return filteredData.find(i => String(i.SKU) === s);
            }

            // Setup add button handler
            function setupAddButton() {
                document.getElementById('addComplianceBtn').addEventListener('click', function() {
                    openComplianceModal('add');
                });
            }

            function setupActionButtons() {
                document.getElementById('table-body').addEventListener('click', function(e) {
                    const editBtn = e.target.closest('.edit-btn');
                    if (editBtn && this.contains(editBtn)) {
                        e.preventDefault();
                        const sku = editBtn.getAttribute('data-sku');
                        if (sku) {
                            openComplianceModal('edit', sku);
                        }
                    }
                });
            }

            async function openComplianceModal(mode, editSku = null) {
                const modalElement = document.getElementById('addComplianceModal');
                const modalTitle = document.getElementById('addComplianceModalLabel');
                const skuSelect = document.getElementById('addComplianceSku');

                if (mode === 'edit') {
                    const skuStr = String(editSku || '').trim();
                    if (!skuStr) {
                        showToast('warning', 'Could not determine SKU to edit.');
                        return;
                    }
                    if (skuStr.toUpperCase().includes('PARENT')) {
                        showToast('warning', 'Parent summary rows cannot be edited here.');
                        return;
                    }
                    const item = findComplianceRowBySku(skuStr);
                    if (!item) {
                        showToast('warning', 'Row not found. Try refreshing the page.');
                        return;
                    }
                    complianceFormMode = 'edit';
                    complianceEditSku = skuStr;
                    modalTitle.textContent = 'Edit Compliance Data';
                } else {
                    complianceFormMode = 'add';
                    complianceEditSku = '';
                    modalTitle.textContent = 'Add Compliance Data';
                }

                document.getElementById('addComplianceForm').reset();
                resetComplianceAddFormFields();

                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                $(skuSelect).prop('disabled', false);

                await loadSkusIntoDropdown();

                if (mode === 'edit') {
                    const item = findComplianceRowBySku(complianceEditSku);
                    $(skuSelect).val(complianceEditSku).trigger('change');
                    $(skuSelect).prop('disabled', true);
                    setAddComplianceFormFromItem(item);
                }

                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', async function() {
                    await saveCompliance();
                });

                modalElement.addEventListener('hidden.bs.modal', function complianceModalCleanup() {
                    $(skuSelect).prop('disabled', false);
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                    complianceFormMode = 'add';
                    complianceEditSku = '';
                }, { once: true });

                const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                modal.show();
            }

            // Load SKUs into dropdown
            async function loadSkusIntoDropdown() {
                try {
                    const response = await fetch('/general-specific-master/skus', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const skuSelect = document.getElementById('addComplianceSku');
                        
                        // Destroy Select2 if already initialized
                        if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('destroy');
                        }
                        
                        // Clear existing options except the first one
                        skuSelect.innerHTML = '<option value="">Select SKU</option>';
                        
                        // Add SKU options
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.sku;
                            option.textContent = item.sku;
                            skuSelect.appendChild(option);
                        });
                        
                        // Initialize Select2 with searchable dropdown
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Select SKU',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#addComplianceModal')
                        });
                    }
                } catch (error) {
                    console.error('Error loading SKUs:', error);
                    showToast('warning', 'Failed to load SKUs. Please refresh the page.');
                }
            }

            async function saveCompliance() {
                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const originalText = saveBtn.innerHTML;
                const skuSelect = document.getElementById('addComplianceSku');

                let sku = '';
                if (complianceFormMode === 'edit') {
                    sku = (complianceEditSku || '').trim();
                } else {
                    sku = $(skuSelect).val() ? String($(skuSelect).val()).trim() : '';
                }

                if (!sku) {
                    showToast('warning', complianceFormMode === 'edit' ? 'Missing SKU for update.' : 'Please select SKU');
                    if (complianceFormMode !== 'edit' && $(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('open');
                    }
                    return;
                }

                const url = complianceFormMode === 'edit' ? '/compliance-master/update' : '/compliance-master/store';
                const successMsg = complianceFormMode === 'edit'
                    ? 'Compliance data updated successfully!'
                    : 'Compliance Data added successfully!';

                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;

                    const formData = collectComplianceFormPayload(sku);

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });

                    const data = await response.json();

                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to save data');
                    }

                    showToast('success', successMsg);

                    const modal = bootstrap.Modal.getInstance(document.getElementById('addComplianceModal'));
                    if (modal) modal.hide();

                    loadData();
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
                const importModal = document.getElementById('importModal');
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
                    // Create sample data
                    const sampleData = [
                        ['SKU', 'Battery', 'Wireless', 'Electric', 'GCC', 'Blanket', 'Bluetooth', 'Logo', 'Graph'],
                        ['SKU001', 'N/A', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU002', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU003', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'REQ']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Battery
                        { wch: 12 }, // Wireless
                        { wch: 12 }, // Electric
                        { wch: 12 }, // GCC
                        { wch: 12 }, // Blanket
                        { wch: 12 }, // Bluetooth
                        { wch: 12 }, // Logo
                        { wch: 12 }  // Graph
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

                    XLSX.utils.book_append_sheet(wb, ws, "Compliance Data");
                    XLSX.writeFile(wb, "compliance_master_sample.xlsx");
                    
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
                        const response = await fetch('/compliance-master/import', {
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

            function setupComplianceAddFormListeners() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;

                modal.addEventListener('change', function(e) {
                    const name = e.target.getAttribute('name');
                    if (name && name.startsWith('add_mode_')) {
                        const k = name.replace('add_mode_', '');
                        toggleAddComplianceReqWrap(k);
                    }
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-img-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_img_status`);
                    const prev = document.getElementById(`add_${field}_img_preview`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldImageToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'Image saved.';
                        if (prev) {
                            const u = complianceImagePublicUrl(path);
                            prev.innerHTML = `<img class="compliance-field-thumb" src="${escapeHtml(u)}" alt="">`;
                        }
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-pdf-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_pdf_status`);
                    const linkEl = document.getElementById(`add_${field}_pdf_link`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldPdfToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'PDF saved.';
                        if (linkEl) {
                            const u = complianceImagePublicUrl(path);
                            linkEl.innerHTML = `<a href="${escapeHtml(u)}" target="_blank" rel="noopener"><i class="fas fa-file-pdf me-1"></i>Open PDF</a>`;
                        }
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });
            }

            // Initialize
            setupComplianceImageHoverPreview();
            setupComplianceStatusFilter();
            refreshCmStatusFilterUI();
            setupComplianceAddFormListeners();
            setupActionButtons();
            setupComplianceBulkEdit();
            loadData();
            setupExcelExport();
            setupAddButton();
            setupImport();
        });
    </script>
@endsection

