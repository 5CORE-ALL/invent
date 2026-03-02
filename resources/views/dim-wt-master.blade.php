@extends('layouts.vertical', ['title' => 'Dimensions & Weight Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            padding: 6px 4px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 9px;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            transition: all 0.2s ease;
            vertical-align: bottom;
            height: 115px;
            min-width: 34px;
            width: 34px;
            max-width: 34px;
            text-align: center;
        }
        /* Vertical header label - black bold text, rotated to save horizontal space */
        .table-responsive thead th .th-vertical-label {
            display: inline-block;
            transform: rotate(-90deg);
            transform-origin: center center;
            white-space: nowrap;
            font-size: 10px;
            font-weight: 700;
            color: #000;
            margin-bottom: 40px;
            margin-top: 4px;
            max-width: 105px;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: left;
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
        }
        .table-responsive thead th.th-parent-sku-col {
            min-width: 120px;
            width: 140px;
            max-width: none;
            height: 65px;
            vertical-align: bottom;
        }
        .table-responsive tbody td.td-parent-col,
        .table-responsive tbody td.td-sku-col {
            white-space: nowrap;
            min-width: 120px;
            width: 140px;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table-responsive thead th.th-has-filter {
            min-width: 46px;
            width: 46px;
            max-width: 46px;
        }
        .table-responsive thead th.th-checkbox-col {
            height: auto;
            min-width: 24px;
            width: 24px;
            max-width: 24px;
            vertical-align: middle;
        }

        .table-responsive thead th:hover {
            background: #7aa8fd !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 3px 5px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 4px;
            margin-top: 4px;
            font-size: 9px;
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
            vertical-align: middle;
        }

        .table-responsive tbody td {
            padding: 6px 8px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 11px;
            color: #495057;
            transition: all 0.2s ease;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
            max-width: 120px;
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

        .table-responsive .text-center {
            text-align: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        /* Column width constraints */
        .table-responsive th:nth-child(1),
        .table-responsive td:nth-child(1) {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
        }

        .table-responsive th:nth-child(2),
        .table-responsive td:nth-child(2) {
            width: 50px;
            min-width: 50px;
            max-width: 50px;
        }

        .table-responsive th:nth-child(3),
        .table-responsive td:nth-child(3) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        .table-responsive th:nth-child(4),
        .table-responsive td:nth-child(4) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .table-responsive th:nth-child(5),
        .table-responsive td:nth-child(5) {
            width: 70px;
            min-width: 70px;
            max-width: 70px;
        }

        .table-responsive th:nth-child(6),
        .table-responsive td:nth-child(6) {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .table-responsive th:nth-child(n+7):nth-child(-n+11),
        .table-responsive td:nth-child(n+7):nth-child(-n+11) {
            width: 75px;
            min-width: 75px;
            max-width: 75px;
        }

        .table-responsive th:nth-child(n+12):nth-child(-n+19),
        .table-responsive td:nth-child(n+12):nth-child(-n+19) {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
        }

        .table-responsive th:nth-last-child(2),
        .table-responsive td:nth-last-child(2) {
            width: 56px;
            min-width: 56px;
            max-width: 56px;
        }

        .table-responsive th:last-child,
        .table-responsive td:last-child {
            width: 80px;
            min-width: 80px;
            max-width: 80px;
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

        /* Ensure table fits container */
        #dim-wt-master-datatable {
            width: 100% !important;
            table-layout: fixed;
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
        'page_title' => 'Dimensions & Weight Master',
        'sub_title' => 'Dimensions & Weight Master Analysis',
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
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search" style="font-size: 9px; padding: 2px 4px;">
                                    </th>
                                    <th class="th-has-filter th-parent-sku-col">
                                        <div class="th-horizontal-label" style="font-size: 9px;">SKU</div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
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
                                    <th><span class="th-vertical-label">INV</span></th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">Weight ACT (Kg)</div>
                                        <select id="filterWtActKg" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">WT ACT (LB)</div>
                                        <select id="filterWtAct" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">WT DECL (LB)</div>
                                        <select id="filterWtDecl" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">Length (inch)</div>
                                        <select id="filterL" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">Width (inch)</div>
                                        <select id="filterW" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">Height (Inch)</div>
                                        <select id="filterH" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th><span class="th-vertical-label">Length (CM)</span></th>
                                    <th><span class="th-vertical-label">Width (CM)</span></th>
                                    <th><span class="th-vertical-label">Height (CM)</span></th>
                                    <th class="th-has-filter">
                                        <div class="th-vertical-label" style="font-size: 9px;">CBM</div>
                                        <select id="filterCbm" class="form-control form-control-sm mt-1" style="font-size: 9px; padding: 2px 4px;">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th><span class="th-vertical-label">CTN L (CM)</span></th>
                                    <th><span class="th-vertical-label">CTN W (CM)</span></th>
                                    <th><span class="th-vertical-label">CTN H (CM)</span></th>
                                    <th><span class="th-vertical-label">Carton CBM</span></th>
                                    <th><span class="th-vertical-label">CTN QTY</span></th>
                                    <th><span class="th-vertical-label">Carton CBM each</span></th>
                                    <th><span class="th-vertical-label">CBM E</span></th>
                                    <th><span class="th-vertical-label">CTN Weight (KG)</span></th>
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
                                <label for="editWtActKg" class="form-label">Weight ACT (Kg)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtActKg" name="wt_act_kg" placeholder="Enter Weight ACT (Kg)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtAct" class="form-label">WT ACT (LB)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtAct" name="wt_act" placeholder="Enter WT ACT (LB)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWtDecl" class="form-label">WT DECL (LB)</label>
                                <input type="number" step="0.01" class="form-control" id="editWtDecl" name="wt_decl" placeholder="Enter WT DECL (LB)">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editL" class="form-label">Length (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editL" name="l" placeholder="Enter Length (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editW" class="form-label">Width (inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editW" name="w" placeholder="Enter Width (inch)">
                            </div>
                            <div class="col-md-4">
                                <label for="editH" class="form-label">Height (Inch)</label>
                                <input type="number" step="0.01" class="form-control" id="editH" name="h" placeholder="Enter Height (Inch)">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="editLCm" class="form-label">Length (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editLCm" name="l_cm" placeholder="Enter Length (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editWCm" class="form-label">Width (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editWCm" name="w_cm" placeholder="Enter Width (CM)">
                            </div>
                            <div class="col-md-4">
                                <label for="editHCm" class="form-label">Height (CM)</label>
                                <input type="number" step="0.01" class="form-control" id="editHCm" name="h_cm" placeholder="Enter Height (CM)">
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
                            <div class="col-md-6">
                                <label for="editCtnQty" class="form-label">CTN (QTY)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnQty" name="ctn_qty" placeholder="Enter CTN (QTY)">
                            </div>
                            <div class="col-md-6">
                                <label for="editCtnWeightKg" class="form-label">CTN Weight (KG)</label>
                                <input type="number" step="0.01" class="form-control" id="editCtnWeightKg" name="ctn_weight_kg" placeholder="Enter CTN Weight (KG)">
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
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in the dim & wt data (Weight ACT (Kg), WT ACT (LB), WT DECL (LB), Length (inch), Width (inch), Height (Inch), Length (CM), Width (CM), Height (CM), CTN L (CM), CTN W (CM), CTN H (CM), CTN QTY, CTN Weight (KG))</li>
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
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                            updateStatusBadgesBar();
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

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="26" class="text-center">No data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');
                    const isParentRow = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                    const cellVal = (val, decimals) => isParentRow ? '--' : formatNumber(val || 0, decimals);

                    // Checkbox column
                    const checkboxCell = document.createElement('td');
                    checkboxCell.className = 'text-center';
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
                    row.appendChild(checkboxCell);

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.className = 'text-center';
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:30px;height:30px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column
                    const parentCell = document.createElement('td');
                    parentCell.className = 'td-parent-col';
                    parentCell.title = escapeHtml(item.Parent) || '';
                    parentCell.textContent = escapeHtml(item.Parent) || '-';
                    row.appendChild(parentCell);

                    // SKU column
                    const skuCell = document.createElement('td');
                    skuCell.className = 'td-sku-col';
                    skuCell.title = escapeHtml(item.SKU) || '';
                    skuCell.textContent = escapeHtml(item.SKU) || '-';
                    row.appendChild(skuCell);

                    // Status column – colored dot (same as product master)
                    const statusCell = document.createElement('td');
                    statusCell.className = 'text-center';
                    statusCell.innerHTML = getStatusDot(item.status);
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

                    // Weight ACT (Kg) column
                    const wtActKgCell = document.createElement('td');
                    wtActKgCell.className = 'text-center';
                    wtActKgCell.textContent = cellVal(item.wt_act_kg, 1);
                    row.appendChild(wtActKgCell);

                    // WT ACT column
                    const wtActCell = document.createElement('td');
                    wtActCell.className = 'text-center';
                    wtActCell.textContent = cellVal(item.wt_act, 1);
                    row.appendChild(wtActCell);

                    // WT DECL column
                    const wtDeclCell = document.createElement('td');
                    wtDeclCell.className = 'text-center';
                    wtDeclCell.textContent = cellVal(item.wt_decl, 1);
                    row.appendChild(wtDeclCell);

                    // L column
                    const lCell = document.createElement('td');
                    lCell.className = 'text-center';
                    lCell.textContent = cellVal(item.l, 1);
                    row.appendChild(lCell);

                    // W column
                    const wCell = document.createElement('td');
                    wCell.className = 'text-center';
                    wCell.textContent = cellVal(item.w, 1);
                    row.appendChild(wCell);

                    // H column
                    const hCell = document.createElement('td');
                    hCell.className = 'text-center';
                    hCell.textContent = cellVal(item.h, 1);
                    row.appendChild(hCell);

                    // Length (CM) column
                    const lCmCell = document.createElement('td');
                    lCmCell.className = 'text-center';
                    lCmCell.textContent = cellVal(item.l_cm, 1);
                    row.appendChild(lCmCell);

                    // Width (CM) column
                    const wCmCell = document.createElement('td');
                    wCmCell.className = 'text-center';
                    wCmCell.textContent = cellVal(item.w_cm, 1);
                    row.appendChild(wCmCell);

                    // Height (CM) column
                    const hCmCell = document.createElement('td');
                    hCmCell.className = 'text-center';
                    hCmCell.textContent = cellVal(item.h_cm, 1);
                    row.appendChild(hCmCell);

                    // CBM column
                    const cbmCell = document.createElement('td');
                    cbmCell.className = 'text-center';
                    cbmCell.textContent = cellVal(item.cbm, 1);
                    row.appendChild(cbmCell);

                    // CTN L (CM) column
                    const ctnLCell = document.createElement('td');
                    ctnLCell.className = 'text-center';
                    ctnLCell.textContent = cellVal(item.ctn_l, 0);
                    row.appendChild(ctnLCell);

                    // CTN W (CM) column
                    const ctnWCell = document.createElement('td');
                    ctnWCell.className = 'text-center';
                    ctnWCell.textContent = cellVal(item.ctn_w, 0);
                    row.appendChild(ctnWCell);

                    // CTN H (CM) column
                    const ctnHCell = document.createElement('td');
                    ctnHCell.className = 'text-center';
                    ctnHCell.textContent = cellVal(item.ctn_h, 0);
                    row.appendChild(ctnHCell);

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

                    // CBM (E) column
                    const cbmECell = document.createElement('td');
                    cbmECell.className = 'text-center';
                    cbmECell.textContent = cellVal(item.cbm_e, 0);
                    row.appendChild(cbmECell);

                    // CTN Weight (KG) column
                    const ctnWeightKgCell = document.createElement('td');
                    ctnWeightKgCell.className = 'text-center';
                    ctnWeightKgCell.textContent = cellVal(item.ctn_weight_kg, 0);
                    row.appendChild(ctnWeightKgCell);

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
                            <button class="btn btn-sm btn-outline-warning edit-btn" data-sku="${escapeHtml(item.SKU)}" title="Edit">
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
                        const sku = this.getAttribute('data-sku');
                        const product = tableData.find(d => d.SKU === sku);
                        if (product) {
                            bulkEditList = null;
                            editDimWt(product);
                        }
                    });

                    tbody.appendChild(row);
                });
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
                let cbmMissingCount = 0;

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
                    if (isMissing(item.cbm)) cbmMissingCount++;
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
                setHeaderCount('cbmMissingCount', cbmMissingCount);
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
                const filterCbm = document.getElementById('filterCbm').value;
                const hasMissingDataFilter = filterStatusValue === 'missing' || filterWtActKg === 'missing' || filterWtAct === 'missing' || filterWtDecl === 'missing' ||
                                            filterL === 'missing' || filterW === 'missing' ||
                                            filterH === 'missing' || filterCbm === 'missing';

                const parentSearchVal = (document.getElementById('parentSearch')?.value || '').toLowerCase();
                const skuSearchVal = (document.getElementById('skuSearch')?.value || '').toLowerCase();

                filteredData = tableData.filter(item => {
                    // Exclude parent SKUs when any missing data filter is active
                    if (hasMissingDataFilter) {
                        const isParentSku = item.SKU && String(item.SKU).toUpperCase().includes('PARENT');
                        if (isParentSku) return false;
                    }

                    if (parentSearchVal && !(item.Parent || '').toLowerCase().includes(parentSearchVal)) return false;
                    if (skuSearchVal && !(item.SKU || '').toLowerCase().includes(skuSearchVal)) return false;

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

                    // WT ACT filter
                    if (filterWtAct === 'missing' && !isMissing(item.wt_act)) {
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

                    // CBM filter
                    if (filterCbm === 'missing' && !isMissing(item.cbm)) {
                        return false;
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

            // Setup search and filter listeners (called once at init)
            function setupSearch() {
                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');
                const applyFiltersDebounced = debounce(applyFilters, 180);
                if (parentSearch) parentSearch.addEventListener('input', applyFiltersDebounced);
                if (skuSearch) skuSearch.addEventListener('input', applyFiltersDebounced);

                const filterSTATUSEl = document.getElementById('filterSTATUS');
                if (filterSTATUSEl) filterSTATUSEl.addEventListener('change', applyFilters);
                const filterIds = ['filterWtActKg', 'filterWtAct', 'filterWtDecl', 'filterL', 'filterW', 'filterH', 'filterCbm'];
                filterIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('change', applyFilters);
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
                    const columns = ["SKU", "Status", "INV", "Weight ACT (Kg)", "WT ACT (LB)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CBM", "CTN L (CM)", "CTN W (CM)", "CTN H (CM)", "CTN (CBM)", "CTN (QTY)", "CTN (CBM/Each)", "CBM (E)", "CTN Weight (KG)"];

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
                        "CBM": {
                            key: "cbm"
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
                        "CBM (E)": {
                            key: "cbm_e"
                        },
                        "CTN Weight (KG)": {
                            key: "ctn_weight_kg"
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
                                        // Format numeric columns (WT ACT, WT DECL, L, W, H, CBM, CTN fields, etc.)
                                        else if (["wt_act_kg", "wt_act", "wt_decl", "l", "w", "h", "l_cm", "w_cm", "h_cm", "cbm", "ctn_l", "ctn_w", "ctn_h", "ctn_cbm", "ctn_qty", "ctn_cbm_each", "cbm_e", "ctn_weight_kg"].includes(key)) {
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
                                } else if (["Weight ACT (Kg)", "WT ACT (LB)", "WT DECL (LB)", "Length (inch)", "Width (inch)", "Height (Inch)", "Length (CM)", "Width (CM)", "Height (CM)", "CBM", "CTN (CBM)", "CTN (CBM/Each)", "CBM (E)"].includes(col)) {
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
                        ['SKU', 'Weight ACT (Kg)', 'WT ACT (LB)', 'WT DECL (LB)', 'Length (inch)', 'Width (inch)', 'Height (Inch)', 'Length (CM)', 'Width (CM)', 'Height (CM)', 'CBM', 'CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)', 'CTN (CBM)', 'CTN (QTY)', 'CTN (CBM/Each)', 'CBM (E)', 'CTN Weight (KG)'],
                        ['SKU001', '6.2', '1.5', '1.2', '10.5', '8.3', '5.2', '26.67', '21.08', '13.21', '0.000455', '30', '25', '20', '0.015', '12', '0.00125', '0.0005', '2.5'],
                        ['SKU002', '9.1', '2.0', '1.8', '12.0', '9.0', '6.0', '30.48', '22.86', '15.24', '0.000648', '35', '28', '22', '0.0216', '15', '0.00144', '0.0006', '3.0'],
                        ['SKU003', '5.4', '1.2', '1.0', '9.5', '7.5', '4.5', '24.13', '19.05', '11.43', '0.000321', '28', '24', '18', '0.0121', '10', '0.00121', '0.0004', '1.8']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 16 }, // Weight ACT (Kg)
                        { wch: 14 }, // WT ACT (LB)
                        { wch: 14 }, // WT DECL (LB)
                        { wch: 14 }, // Length (inch)
                        { wch: 12 }, // Width (inch)
                        { wch: 14 }, // Height (Inch)
                        { wch: 12 }, // Length (CM)
                        { wch: 12 }, // Width (CM)
                        { wch: 12 }, // Height (CM)
                        { wch: 15 }, // CBM
                        { wch: 14 }, // CTN L (CM)
                        { wch: 14 }, // CTN W (CM)
                        { wch: 14 }, // CTN H (CM)
                        { wch: 15 }, // CTN (CBM)
                        { wch: 12 }, // CTN (QTY)
                        { wch: 18 }, // CTN (CBM/Each)
                        { wch: 12 }, // CBM (E)
                        { wch: 16 }  // CTN Weight (KG)
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
                    checkedBoxes.forEach(checkbox => {
                        const sku = checkbox.getAttribute('data-sku');
                        if (!sku || String(sku).toUpperCase().includes('PARENT')) return;
                        const item = tableData.find(d => d.SKU === sku);
                        if (item) selected.push(item);
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
                        const sku = checkbox.getAttribute('data-sku');
                        const row = checkbox.closest('tr');
                        if (row && sku) {
                            // Get dimensions and weight from the row data
                            const item = tableData.find(d => d.SKU === sku);
                            if (item) {
                                selectedSkus.push({
                                    sku: sku,
                                    id: item.id,
                                    wt_act_kg: item.wt_act_kg || null,
                                    wt_act: item.wt_act || null,
                                    wt_decl: item.wt_decl || null,
                                    l: item.l || null,
                                    w: item.w || null,
                                    h: item.h || null,
                                    l_cm: item.l_cm || null,
                                    w_cm: item.w_cm || null,
                                    h_cm: item.h_cm || null,
                                    cbm: item.cbm || null
                                });
                            }
                        }
                    });

                    if (selectedSkus.length === 0) {
                        showToast('warning', 'No valid SKUs found to push');
                        return;
                    }

                    // Confirm action with details
                    const skuList = selectedSkus.map(s => s.sku).join(', ');
                    const confirmMessage = `Are you sure you want to push dimensions & weight data for ${selectedSkus.length} SKU(s) to ALL marketplaces?\n\n` +
                        `Selected SKUs: ${skuList.substring(0, 100)}${skuList.length > 100 ? '...' : ''}\n\n` +
                        `Data to be updated:\n` +
                        `- Weight (Weight ACT (Kg), WT ACT (LB), WT DECL (LB))\n` +
                        `- Dimensions (Length/Width/Height in inch and CM)\n` +
                        `- CBM\n\n` +
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
                                messageHtml += `<strong>Success!</strong> Dimensions and weight data have been updated on the marketplace platforms above.`;
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

            // Edit Dimensions & Weight Master
            function editDimWt(product) {
                const modal = new bootstrap.Modal(document.getElementById('editDimWtModal'));
                document.getElementById('editDimWtModalLabel').textContent = (bulkEditList && bulkEditList.length > 0)
                    ? ('Bulk Edit (' + bulkEditList.length + ' items)')
                    : 'Edit Dimensions & Weight Master';
                
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
                document.getElementById('editCtnQty').value = product.ctn_qty || '';
                document.getElementById('editCtnWeightKg').value = product.ctn_weight_kg || '';
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveDimWtBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveDimWt();
                });
                
                modal.show();
            }

            // Save Dimensions & Weight Master (single or bulk)
            async function saveDimWt() {
                const saveBtn = document.getElementById('saveDimWtBtn');
                const originalText = saveBtn.innerHTML;
                
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
                    
                    if (bulkEditList && bulkEditList.length > 0) {
                        let successCount = 0;
                        let failCount = 0;
                        for (const product of bulkEditList) {
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
                        document.getElementById('editDimWtModalLabel').textContent = 'Edit Dimensions & Weight Master';
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
                document.getElementById('editDimWtModalLabel').textContent = 'Edit Dimensions & Weight Master';
            });
        });
    </script>
@endsection

